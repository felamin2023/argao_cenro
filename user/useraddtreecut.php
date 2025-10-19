<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';

$notifs = [];
$unreadCount = 0;

/* AJAX endpoints used by the header JS:
   - POST ?ajax=mark_all_read
   - POST ?ajax=mark_read&notif_id=...
*/
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where "to" = :uid and (is_read is null or is_read = false)
            ');
            $u->execute([':uid' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_read') {
            $nid = $_GET['notif_id'] ?? '';
            if (!$nid) {
                echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
                exit;
            }
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where notif_id = :nid and "to" = :uid
            ');
            $u->execute([':nid' => $nid, ':uid' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* Load the latest notifications for the current user */
try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['role'] = $role;

            header("Location: user_home.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Registration Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
            --accent-color: #3a86ff;
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
        }

        .as-item {
            position: relative;
        }

        /* Bell button */
        .as-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            background: rgb(233, 255, 242);
            color: #000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            transition: var(--as-trans);
        }

        .as-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .as-icon i {
            font-size: 1.3rem;
        }

        /* Base dropdown */
        .as-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 300px;
            background: #fff;
            border-radius: var(--as-radius);
            box-shadow: var(--as-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--as-trans);
            padding: 0;
            z-index: 1000;
        }

        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* Notifications-specific sizing */
        .as-notifications {
            min-width: 350px;
            max-height: 500px;
        }

        /* Sticky header */
        .as-notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--as-primary);
            font-size: 1.1rem;
        }

        .as-mark-all {
            color: var(--as-primary);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--as-trans);
        }

        .as-mark-all:hover {
            color: var(--as-primary-dark);
            transform: scale(1.05);
        }

        /* Scroll body */
        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

        /* Rows */
        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--as-trans);
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05);
        }

        .as-notif-item:hover {
            background: #f9f9f9;
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .as-notif-icon {
            color: var(--as-primary);
            font-size: 1.2rem;
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--as-primary);
            margin-bottom: 4px;
        }

        .as-notif-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .as-notif-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        /* Sticky footer */
        .as-notif-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fff;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .as-view-all {
            color: var(--as-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .as-view-all:hover {
            text-decoration: underline;
        }

        /* Red badge on bell */
        .as-badge {
            position: absolute;
            top: 2px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ff4757;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding-top: 100px;
            color: #333;
            line-height: 1.6;
        }

        /* Header Styles */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0 30px;
            height: 58px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Logo */
        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative;
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%;
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }


        /* Navigation Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Navigation Items */
        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgb(233, 255, 242);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: black;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
            transition: color 0.3s ease;
        }

        .nav-icon.active {
            position: relative;
        }

        .nav-icon.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 2px;
            background-color: var(--white);
            border-radius: 2px;
        }


        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        .dropdown-item.active-page {
            background-color: rgb(225, 255, 220);
            color: var(--primary-dark);
            font-weight: bold;
            border-left: 4px solid var(--primary-color);
        }


        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition), transform 0.2s ease;
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, 0.05);
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .notification-message {
            color: var(--primary-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .dropdown-menu.center {
            left: 50%;
            transform: translateX(-50%) translateY(10px);
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent;
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        /* Dropdown Items */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        /* Notification Badge */
        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px;
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .notification-link:hover {
            background-color: #f9f9f9;
        }

        /* Content Styles */
        .content {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -1%;
            padding: 0 20px;
            margin-bottom: 2%;
        }

        .page-title {
            color: #005117;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #005117;
            padding-bottom: 10px;
            width: 80%;
            max-width: 800px;
        }

        .profile-form {
            background-color: #fff;
            padding: 30px;
            border: 2px solid #005117;
            max-width: 800px;
            width: 90%;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
        }

        .form-group input,
        .form-group textarea,
        .form-group input[type="file"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #005117;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1 0 200px;
            padding: 0 10px;
            margin-bottom: 25px;
        }

        .form-group.full-width {
            flex: 1 0 100%;
        }

        .form-group.two-thirds {
            flex: 2 0 400px;
        }

        .form-group.one-third {
            flex: 1 0 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000;
            font-size: 14px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .form-group textarea {
            height: 180px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            width: 100%;
            height: 40px;
            padding: 5px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            background-color: #ffffff;
            box-sizing: border-box;
        }

        /* Make input[type="date"] same height as file input */
        .form-group input[type="date"] {
            height: 40px;
            box-sizing: border-box;
        }

        /* Make all inputs, textarea, select height 40px to match user_requestseedlings.php */
        .form-group input,
        .form-group textarea,
        .form-group select {
            height: 40px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn,
        .view-records-btn {
            background-color: #005117;
            color: #fff;
            border: none;
            padding: 12px 40px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            text-align: center;
        }

        .view-records-btn {
            background-color: #005117;
        }

        .save-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        .view-records-btn:hover {
            background-color: #006622;
            transform: translateY(-2px);
        }

        /* Records Table Styles */
        .records-container {
            background-color: #fff;
            border: 2px solid #005117;
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 6px 20px rgba(0, 81, 23, 0.1);
            display: none;
        }

        .records-title {
            color: #005117;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #005117;
            padding-bottom: 10px;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .records-table th,
        .records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        /* Center first column header and data cells text to match user_requestseedlings.php */
        .records-table th:first-child,
        .records-table td:first-child {
            text-align: center;
        }

        .records-table th {
            background-color: #005117;
            color: white;
            font-weight: 600;
        }

        .records-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .records-table tr:hover {
            background-color: #f1f1f1;
        }

        .status-pending {
            color: #4caf50;
            font-weight: 600;
        }

        .status-approved {
            color: #4caf50;
            font-weight: 600;
        }

        .status-rejected {
            color: #4caf50;
            font-weight: 600;
        }

        .no-records {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .mobile-toggle {
                display: block;
            }

            /* Header Styles */
            header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: var(--primary-color);
                color: var(--white);
                padding: 0 30px;
                height: 58px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* Logo */
        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative;
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%;
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        /* Navigation Container */
        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Navigation Items - Larger Icons */
        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            /* smaller width */
            height: 40px;
            /* smaller height */
            background: rgb(233, 255, 242);
            /* slightly brighter background */
            border-radius: 12px;
            /* softer corners */
            cursor: pointer;
            transition: var(--transition);
            color: black;
            /* changed icon color to black */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            /* subtle shadow for depth */
        }

        .nav-icon:hover {
            background: rgba(224, 204, 204, 0.3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .nav-icon i {
            font-size: 1.3rem;
            /* smaller icon size */
            color: inherit;
            transition: color 0.3s ease;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        /* Notification-specific dropdown styles */
        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition), transform 0.2s ease;
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            /* Slightly darker color on hover */
            transform: scale(1.1);
            /* Slightly bigger on hover */
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, 0.05);
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .notification-message {
            color: var(--primary-color);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .dropdown-menu.center {
            left: 50%;
            transform: translateX(-50%) translateY(10px);
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent;
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        /* Larger Dropdown Items */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        /* Notification Badge - Larger */
        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Mobile Menu Toggle - Larger */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px;
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .notification-link:hover {
            background-color: #f9f9f9;
        }


        /* Main Content */
        .main-container {
            margin-top: -0.5%;
            padding: 30px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            margin-top: -3%;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: nowrap;
            justify-content: center;
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }

        .btn {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
            min-width: 120px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--light-gray);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            border: 2px solid var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* Requirements Form */
        .requirements-form {
            margin-top: -1%;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 1px solid var(--medium-gray);
        }

        .form-header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--primary-dark);
        }

        .form-header h2 {
            text-align: center;
            font-size: 1.5rem;
            margin: 0;
        }

        .form-body {
            padding: 30px;
        }

        .requirements-list {
            display: grid;
            gap: 20px;
        }

        .requirement-item {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 20px;
            background: var(--light-gray);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .requirement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirement-number {
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-right: 10px;
            flex-shrink: 0;
            /* Add this to prevent shrinking */
            line-height: 25px;
            /* Add this to ensure vertical centering */
            text-align: center;
            /* Add this for horizontal centering */
        }

        .new-number {
            display: inline;
        }

        .renewal-number {
            display: none;
        }

        .file-upload {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-input-label {
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .file-input-label:hover {
            background: var(--primary-dark);
        }

        .file-input {
            display: none;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--dark-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .uploaded-files {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--medium-gray);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }

        .file-icon {
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .file-action-btn {
            background: none;
            border: none;
            color: var(--dark-gray);
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
        }

        .file-action-btn:hover {
            color: var(--primary-color);
        }

        .form-footer {
            padding: 20px 30px;
            background: var(--light-gray);
            border-top: 1px solid var(--medium-gray);
            display: flex;
            justify-content: flex-end;
        }

        /* Fee Information */
        .fee-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(43, 102, 37, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }

        .fee-info p {
            margin: 5px 0;
            color: var(--primary-dark);
            font-weight: 500;
        }

        /* File Preview Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: var(--border-radius);
            position: relative;
        }

        .close-modal {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: black;
        }

        .file-preview {
            width: 100%;
            height: 70vh;
            border: none;
            margin-top: 20px;
        }

        /* Download button styles */
        .download-btn {
            display: inline-flex;
            align-items: center;
            background-color: #2b6625;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .download-btn:hover {
            background-color: #1e4a1a;
        }

        .download-btn i {
            margin-right: 8px;
        }

        /* Permit Type Selector */
        .permit-type-selector {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .permit-type-btn {
            padding: 12px 25px;
            margin: 0 10px 0 0;
            border: 2px solid #2b6625;
            background-color: white;
            color: #2b6625;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .permit-type-btn.active {
            background-color: #2b6625;
            color: white;
        }

        .permit-type-btn:hover {
            background-color: #2b6625;
            color: white;
        }

        /* Add new styles for name fields */
        .name-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .name-field {
            flex: 1;
            min-width: 200px;
        }

        .name-field input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
            height: 40px;
            box-sizing: border-box;
        }

        .name-field input:focus {
            outline: none;
            border-color: #2b6625;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .name-field input::placeholder {
            color: #999;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .notifications-dropdown {
                width: 320px;
            }

            .main-container {
                padding: 20px;
            }

            .form-body {
                padding: 20px;
            }

            .requirement-item {
                padding: 15px;
            }

            .requirement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .file-input-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .permit-type-selector {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }

            .permit-type-btn {
                flex: 0 0 auto;
                margin: 0 5px 0 0;
                padding: 10px 15px;
            }
        }

        @media (max-width: 576px) {
            header {
                padding: 0 15px;
            }

            .nav-container {
                gap: 15px;
            }

            .notifications-dropdown {
                width: 280px;
                right: -50px;
            }

            .notifications-dropdown:before {
                right: 65px;
            }

            .action-buttons {
                margin-top: -6%;
                gap: 8px;
                padding-bottom: 5px;
            }

            .btn {
                padding: 10px 10px;
                font-size: 0.85rem;
                min-width: 80px;
            }

            .btn i {
                font-size: 0.85rem;
                margin-right: 5px;
            }

            .form-header {
                padding: 15px 20px;
            }

            .form-header h2 {
                font-size: 1.3rem;
            }

            .permit-type-btn {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h2 {
            background-color: #2b6625;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2b6625;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #2b6625;
            outline: none;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #e9f5e8;
            color: #2b6625;
        }

        .add-row {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .add-row:hover {
            background-color: #1e4a1a;
        }

        .declaration {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            border-left: 4px solid #2b6625;
            margin-bottom: 25px;
        }

        .declaration p {
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .signature-date {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .signature-box {
            width: 100%;
            margin-top: 20px;
        }

        .signature-pad-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: white;
        }

        #signature-pad {
            width: 100%;
            height: 150px;
            cursor: crosshair;
        }

        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .signature-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .clear-signature {
            background-color: #ff4757;
            color: white;
        }

        .save-signature {
            background-color: #2b6625;
            color: white;
        }

        .signature-preview {
            margin-top: 15px;
            text-align: center;
        }

        #signature-image {
            max-width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .download-btn {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto 0;
            transition: background-color 0.3s;
        }

        .download-btn:hover {
            background-color: #1e4a1a;
        }

        .hidden {
            display: none;
        }

        .declaration-input {
            border: none;
            border-bottom: 1px solid #999;
            border-radius: 0;
            padding: 0 5px;
            width: auto;
            display: inline-block;
            background: transparent;
        }

        .declaration-input:focus {
            border-bottom: 2px solid #2b6625;
            outline: none;
            box-shadow: none;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .signature-date {
                flex-direction: column;
                gap: 20px;
            }

            .declaration-input {
                width: 100%;
                margin: 5px 0;
            }
        }

        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
            color: #2b6625;
        }

        .loading i {
            margin-right: 10px;
        }

        /* Print-specific styles */
        @media print {

            .download-btn,
            .add-row,
            .signature-actions,
            .signature-pad-container {
                display: none !important;
            }

            body {
                background-color: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border: none;
                padding: 15px;
            }
        }

        /* Inline validation */
        .input-error {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, .12)
        }

        .field-hint {
            display: none;
            margin-top: 6px;
            font-size: .85rem;
            color: #dc2626
        }

        /* show group-level errors (e.g., radio group, file block, table) */
        .has-error .field-hint {
            display: block
        }

        .has-error .file-input-label {
            outline: 2px solid #dc2626;
            outline-offset: 2px
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="user_home.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Mobile menu toggle -->
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">

                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Report Incident</span>
                    </a>

                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>

                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>

                    <a href="useraddtreecut.php" class="dropdown-item active-page">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing Permit</span>
                    </a>
                    <a href="useraddchainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Permit</span>
                    </a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>

                </div>
            </div>

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge">
                            <?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <!-- sticky header -->
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <!-- scrollable body -->
                    <div class="notifcontainer"><!-- this holds the records -->
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread = empty($n['is_read']);
                                $ts     = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                $title  = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\\s*\\(?\\b(rejection\\s*reason|reason)\\b\\s*[:\\-–]\\s*.*$/i', '', $t);
                                    $t = preg_replace('/\\s*\\b(because|due\\s+to)\\b\\s*.*/i', '', $t);
                                    return trim(preg_replace('/\\s{2,}/', ' ', $t)) ?: 'There’s an update.';
                                })($n['message'] ?? '');
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        <?= !empty($n['approval_id']) ? 'data-approval-id="' . htmlspecialchars((string)$n['approval_id'], ENT_QUOTES) . '"' : '' ?>
                                        <?= !empty($n['incident_id']) ? 'data-incident-id="' . htmlspecialchars((string)$n['incident_id'], ENT_QUOTES) . '"' : '' ?>>
                                        <div class="as-notif-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div class="as-notif-content">
                                            <div class="as-notif-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
                                            <div class="as-notif-message"><?= htmlspecialchars($cleanMsg, ENT_QUOTES) ?></div>
                                            <div class="as-notif-time" data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>">just now</div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <!-- sticky footer -->
                    <div class="as-notif-footer">
                        <a href="user_notification.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>



            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="requirements-form">
            <div class="form-header">
                <h2>Tree Cutting Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <!-- ===================== APPLICATION FIELDS (UI only) ===================== -->
                <div class="name-fields">
                    <div class="name-field">
                        <input type="text" id="first-name" placeholder="First Name" required>
                    </div>
                    <div class="name-field">
                        <input type="text" id="middle-name" placeholder="Middle Name">
                    </div>
                    <div class="name-field">
                        <input type="text" id="last-name" placeholder="Last Name" required>
                    </div>
                </div>

                <!-- Address / Contact -->
                <div class="form-row" style="margin-top:12px;">
                    <div class="form-group">
                        <label for="street">Sitio/Street:</label>
                        <input type="text" id="street">
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay:</label>
                        <input type="text" id="barangay">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="municipality">Municipality:</label>
                        <input type="text" id="municipality">
                    </div>
                    <div class="form-group">
                        <label for="province">Province:</label>
                        <input type="text" id="province">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact-number">Contact No.:</label>
                        <input type="text" id="contact-number">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email">
                    </div>
                </div>
                <div class="form-group">
                    <label for="registration-number">If Corporation: SEC/DTI Registration No.</label>
                    <input type="text" id="registration-number">
                </div>

                <!-- Cutting details -->
                <div class="form-group" style="margin-top:10px;">
                    <label for="location">Location of Area/Trees to be Cut:</label>
                    <input type="text" id="location">
                </div>

                <div class="form-group">
                    <label>Ownership of Land:</label>
                    <div style="display:flex;gap:20px;margin-top:10px;flex-wrap:wrap;">
                        <label style="display:flex;align-items:center;gap:6px;">
                            <input type="radio" name="ownership" value="Private"> Private
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;">
                            <input type="radio" name="ownership" value="Government"> Government
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;">
                            <input type="radio" name="ownership" value="Others"> Others:
                            <input type="text" id="other-ownership" style="margin-left:5px;width:160px;">
                        </label>
                    </div>
                </div>
                <!-- Land details -->
                <div class="form-row" style="margin-top:10px;">
                    <div class="form-group one-third">
                        <label for="tax-declaration">Tax Declaration No.</label>
                        <input type="text" id="tax-declaration" placeholder="e.g., TD-12345">
                    </div>
                    <div class="form-group one-third">
                        <label for="lot-no">Lot No.</label>
                        <input type="text" id="lot-no" placeholder="e.g., Lot 12">
                    </div>
                    <div class="form-group one-third">
                        <label for="contained-area">Contained Area</label>
                        <input type="text" id="contained-area" placeholder="e.g., 1,500 sq.m or 0.15 ha">
                    </div>
                </div>


                <!-- Species table -->
                <div class="form-group" style="margin-top:10px;">
                    <label>Number and Species of Trees Applied for Cutting:</label>
                    <table class="suppliers-table" id="species-table" style="margin-top:6px;">
                        <thead>
                            <tr>
                                <th>Species</th>
                                <th>No. of Trees</th>
                                <th>Net Volume (cu.m)</th>
                                <th style="width:60px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="species-table-body">
                            <tr>
                                <td><input type="text" class="species-name"></td>
                                <td><input type="number" class="species-count" min="0"></td>
                                <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                                <td><button type="button" class="remove-btn small">Remove</button></td>
                            </tr>
                            <tr>
                                <td><input type="text" class="species-name"></td>
                                <td><input type="number" class="species-count" min="0"></td>
                                <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                                <td><button type="button" class="remove-btn small">Remove</button></td>
                            </tr>
                            <tr>
                                <td><input type="text" class="species-name"></td>
                                <td><input type="number" class="species-count" min="0"></td>
                                <td><input type="number" class="species-volume" step="0.01" min="0"></td>
                                <td><button type="button" class="remove-btn small">Remove</button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="text-align:right;"><strong>TOTAL</strong></td>
                                <td><input type="number" id="total-count" readonly style="background:#f0f0f0;"></td>
                                <td><input type="number" id="total-volume" readonly style="background:#f0f0f0;"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" class="add-row" id="add-row-btn"><i class="fas fa-plus"></i> Add Row</button>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label for="purpose">Purpose of Application for Tree Cutting Permit:</label>
                    <textarea id="purpose" rows="4" placeholder="e.g., land development, safety hazard removal, construction, farming, etc."></textarea>
                </div>

                <!-- Declaration & signature -->
                <div class="form-subsection" style="margin-top:12px;">
                    <h4>Declaration</h4>
                    <p>I hereby certify that the information provided in this application is true and correct. I understand that the approval of this application is subject to verification and evaluation by DENR, and that I shall comply with all terms and conditions of the Tree Cutting Permit once issued.</p>

                    <div class="signature-date">
                        <div class="signature-box">
                            <label>Signature Over Printed Name:</label>
                            <div class="signature-pad-container" style="border:1px solid #ccc;border-radius:8px;">
                                <canvas id="signature-pad" style="width:100%;height:200px;display:block;"></canvas>
                            </div>
                            <div class="signature-actions" style="margin-top:8px;display:flex;gap:8px;">
                                <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                <!-- <button type="button" class="signature-btn save-signature" id="save-signature">Save Signature</button> -->
                            </div>
                            <div class="signature-preview" style="margin-top:8px;">
                                <img id="signature-image" class="hidden" alt="Signature" style="max-width:240px;display:none;border:1px solid #ddd;padding:3px;border-radius:4px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Download (kept for user copy) -->
                <!-- <div class="form-subsection" style="margin-top:14px;display:flex;align-items:center;gap:12px;">
                    <button type="button" class="download-btn" id="downloadBtn">
                        <i class="fas fa-download"></i> Download Application as Word Document
                    </button>
                    <div class="loading" id="loadingIndicator" style="display:none;">
                        <i class="fas fa-spinner fa-spin"></i> Generating document, please wait...
                    </div>
                </div> -->
                <!-- ===================== /APPLICATION FIELDS ===================== -->

                <div class="requirements-list" style="margin-top:18px;">
                    <!-- 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Certificate of Verification (COV)- 2 copies for CENRO signature or OIC
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-1"></div>
                        </div>
                    </div>

                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Memorandom Report (2 copies signed by inspecting officer subscribed by register forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-3"></div>
                        </div>
                    </div>

                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Tally sheets (inventory sheet of forest product)- 2 copies signed by inspecting officer subscribed by registered forester
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-4"></div>
                        </div>
                    </div>

                    <!-- 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Geo-tagged photos of forest products (2 copies signed by inspecting officer subscribed by registered forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-5"></div>
                        </div>
                    </div>

                    <!-- 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Sworn Statement (2 copies signed by inspecting officer subscribed by registered forester)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-6"></div>
                        </div>
                    </div>

                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Certificate of Transport Agreement duly notarized (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of OR/CR of conveyance</p>
                                <div class="file-input-container">
                                    <label for="file-7a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-7a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-7a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Drivers License</p>
                                <div class="file-input-container">
                                    <label for="file-7b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-7b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-7b"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Purchase Order(Signed by the Consignee - 2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-8" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-8" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8"></div>
                        </div>
                    </div>

                    <!-- 8 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Photocopy of approved TCP/ SPTLP/ PLTP/ STCP (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Tally sheets and stand/ stock table</p>
                                <div class="file-input-container">
                                    <label for="file-10a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-10a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Tree Charting</p>
                                <div class="file-input-container">
                                    <label for="file-10b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-10b"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </div>
    </div>

    <!-- Toast / Notifications -->
    <div id="toast" aria-live="polite" aria-atomic="true" style="position:fixed; top:12px; left:50%; transform:translateX(-50%); z-index:9999; display:none;">
        <div id="toast-content" style="background:#222; color:#fff; padding:14px 18px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,.25); font-weight:500; min-width:240px; text-align:center;"></div>
    </div>

    <!-- Old compact banner kept for backwards-compat (hidden by default) -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9998; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span id="closeFilePreviewModal" class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width:420px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this tree cutting permit request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <!-- Result Modal (ERRORS ONLY) -->
    <div id="resultModal" class="modal">
        <div class="modal-content" style="max-width:520px;">
            <span id="closeResultModal" class="close-modal">&times;</span>
            <div id="resultIcon" style="font-size:30px; margin-bottom:6px;"></div>
            <h3 id="resultTitle">Submission Result</h3>
            <p id="resultMessage" style="margin-top:6px;"></p>
            <div id="resultDetails" style="margin-top:10px; font-size:.95rem; color:#444;"></div>
            <div style="margin-top:14px; text-align:right;">
                <button class="btn btn-primary" id="resultOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Fullscreen Global Loader -->
    <div id="globalLoader" style="display:none; position:fixed; inset:0; background:rgba(17,17,17,.45); z-index:10000; backdrop-filter: blur(2px);">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:22px 26px; border-radius:14px; box-shadow:0 16px 40px rgba(0,0,0,.20); min-width:280px; text-align:center;">
            <div class="spinner" style="margin:2px auto 10px; width:28px; height:28px; border:3px solid #e5e7eb; border-top-color:#2563eb; border-radius:50%; animation:spin 1s linear infinite;"></div>
            <div id="globalLoaderText" style="font-weight:600; color:#111;">Saving your request…</div>
            <div id="globalLoaderSub" style="font-size:.92rem; color:#555; margin-top:4px;">Generating application, uploading files, and creating records.</div>
        </div>
    </div>

    <!-- Small CSS hook for spinner animation -->
    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.35);
        }

        .modal-content {
            background: #fff;
            margin: 8% auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            width: 90%;
            max-width: 700px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 12px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .close-modal:hover {
            color: #000;
        }

        .btn.btn-outline {
            background: #fff;
            border: 1px solid #ddd;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* ======================== Helpers ======================== */
            const $ = (sel, root) => (root || document).querySelector(sel);
            const $all = (sel, root) => Array.from((root || document).querySelectorAll(sel));
            const getVal = (id) => (document.getElementById(id)?.value ?? '').trim();

            /* ======================== Mobile menu ======================== */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });
            }

            /* ======================== Toast / Modal / Loader ======================== */
            function showToast(message, duration = 2000) { // 2s default
                const toast = $('#toast');
                const content = $('#toast-content');
                if (!toast || !content) return;
                content.textContent = message;
                toast.style.display = 'block';
                toast.style.opacity = '1';
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => (toast.style.display = 'none'), 250);
                }, duration);
            }
            const openModal = (el) => {
                if (el) el.style.display = 'block';
            };
            const closeModal = (el) => {
                if (el) el.style.display = 'none';
            };

            function showGlobalLoader(text = 'Saving your request…', sub = 'Generating application, uploading files, and creating records.') {
                const gl = $('#globalLoader');
                const t = $('#globalLoaderText');
                const s = $('#globalLoaderSub');
                if (t) t.textContent = text;
                if (s) s.textContent = sub;
                if (gl) gl.style.display = 'block';
            }

            function hideGlobalLoader() {
                const gl = $('#globalLoader');
                if (gl) gl.style.display = 'none';
            }

            /* ======================== File inputs ======================== */
            const fileInputs = [{
                    id: 'file-1',
                    uploaded: 'uploaded-files-1'
                },
                {
                    id: 'file-3',
                    uploaded: 'uploaded-files-3'
                },
                {
                    id: 'file-4',
                    uploaded: 'uploaded-files-4'
                },
                {
                    id: 'file-5',
                    uploaded: 'uploaded-files-5'
                },
                {
                    id: 'file-6',
                    uploaded: 'uploaded-files-6'
                },
                {
                    id: 'file-7a',
                    uploaded: 'uploaded-files-7a'
                },
                {
                    id: 'file-7b',
                    uploaded: 'uploaded-files-7b'
                },
                {
                    id: 'file-8',
                    uploaded: 'uploaded-files-8'
                },
                {
                    id: 'file-10a',
                    uploaded: 'uploaded-files-10a'
                },
                {
                    id: 'file-10b',
                    uploaded: 'uploaded-files-10b'
                },
            ];
            let selectedFiles = {};
            fileInputs.forEach(cfg => {
                const inp = document.getElementById(cfg.id);
                const target = document.getElementById(cfg.uploaded);
                if (!inp) return;
                inp.addEventListener('change', function() {
                    if (target) target.innerHTML = '';
                    const file = this.files[0];
                    const nameEl = this.parentElement.querySelector('.file-name');
                    if (nameEl) nameEl.textContent = file ? file.name : 'No file chosen';
                    selectedFiles[cfg.id] = file || null;
                });
            });

            /* ======================== Species table ======================== */
            const speciesTbody = $('#species-table-body');
            const addRowBtn = $('#add-row-btn');

            function hookRemoveButtons() {
                $all('#species-table-body .remove-btn').forEach(btn => {
                    btn.onclick = function() {
                        const tr = btn.closest('tr');
                        if (speciesTbody && speciesTbody.rows.length > 1 && tr) {
                            tr.parentNode.removeChild(tr);
                            calculateTotals();
                        }
                    };
                });
            }
            hookRemoveButtons();

            function addSpeciesRow(name, count, vol) {
                if (!speciesTbody) return;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="species-name" value="' + (name || '') + '"></td>' +
                    '<td><input type="number" class="species-count" min="0" value="' + (count || '') + '"></td>' +
                    '<td><input type="number" class="species-volume" step="0.01" min="0" value="' + (vol || '') + '"></td>' +
                    '<td><button type="button" class="remove-btn small">Remove</button></td>';
                speciesTbody.appendChild(tr);
                hookRemoveButtons();
            }
            if (addRowBtn) addRowBtn.addEventListener('click', () => addSpeciesRow('', '', ''));

            function calculateTotals() {
                let totalCount = 0,
                    totalVolume = 0;
                $all('.species-count').forEach(inp => totalCount += Number(inp.value) || 0);
                $all('.species-volume').forEach(inp => totalVolume += Number(inp.value) || 0);
                const tc = $('#total-count'),
                    tv = $('#total-volume');
                if (tc) tc.value = totalCount;
                if (tv) tv.value = Number.isFinite(totalVolume) ? totalVolume.toFixed(2) : '0.00';
            }
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('species-count') || e.target.classList.contains('species-volume')) {
                    calculateTotals();
                }
            });
            calculateTotals();

            function readSpeciesRows() {
                const rows = [];
                $all('#species-table-body tr').forEach(row => {
                    const name = (row.querySelector('.species-name')?.value || '').trim();
                    const count = (row.querySelector('.species-count')?.value || '').trim();
                    const volume = (row.querySelector('.species-volume')?.value || '').trim();
                    if (name || count || volume) rows.push({
                        name,
                        count,
                        volume
                    });
                });
                return rows;
            }

            /* ======================== Signature pad ======================== */
            const sigCanvas = document.getElementById('signature-pad');
            const clearSigBtn = document.getElementById('clear-signature');
            const saveSigBtn = document.getElementById('save-signature');
            const sigPreview = document.getElementById('signature-image');

            let drawing = false,
                last = {
                    x: 0,
                    y: 0
                },
                strokes = [],
                currentStroke = [];

            function sigCtxStyle(ctx) {
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
            }

            function repaintSignature(fillBg) {
                if (!sigCanvas) return;
                const ctx = sigCanvas.getContext('2d');
                const ratio = window.devicePixelRatio || 1;
                const cssW = sigCanvas.width / ratio,
                    cssH = sigCanvas.height / ratio;
                if (fillBg) {
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, cssW, cssH);
                }
                sigCtxStyle(ctx);
                for (let s of strokes) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                    ctx.stroke();
                }
            }

            function resizeSigCanvas() {
                if (!sigCanvas) return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssWidth = sigCanvas.clientWidth || 600;
                const cssHeight = sigCanvas.clientHeight || 200;
                sigCanvas.width = Math.floor(cssWidth * ratio);
                sigCanvas.height = Math.floor(cssHeight * ratio);
                const ctx = sigCanvas.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                repaintSignature(true);
            }
            resizeSigCanvas();
            window.addEventListener('resize', resizeSigCanvas);

            function getCanvasPos(e) {
                const rect = sigCanvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : null;
                const cx = t ? t.clientX : e.clientX;
                const cy = t ? t.clientY : e.clientY;
                return {
                    x: cx - rect.left,
                    y: cy - rect.top
                };
            }

            function startSig(e) {
                drawing = true;
                currentStroke = [];
                const p = getCanvasPos(e);
                last = {
                    x: p.x,
                    y: p.y
                };
                currentStroke.push(last);
                e.preventDefault?.();
            }

            function drawSig(e) {
                if (!drawing) return;
                const p = getCanvasPos(e);
                const ctx = sigCanvas.getContext('2d');
                ctx.beginPath();
                ctx.moveTo(last.x, last.y);
                ctx.lineTo(p.x, p.y);
                sigCtxStyle(ctx);
                ctx.stroke();
                last = {
                    x: p.x,
                    y: p.y
                };
                currentStroke.push(last);
                e.preventDefault?.();
            }

            function endSig() {
                if (!drawing) return;
                drawing = false;
                if (currentStroke.length > 1) strokes.push(currentStroke);
                currentStroke = [];
            }
            if (sigCanvas) {
                sigCanvas.addEventListener('mousedown', startSig);
                sigCanvas.addEventListener('mousemove', drawSig);
                window.addEventListener('mouseup', endSig);
                sigCanvas.addEventListener('touchstart', startSig, {
                    passive: false
                });
                sigCanvas.addEventListener('touchmove', drawSig, {
                    passive: false
                });
                window.addEventListener('touchend', endSig);
            }

            function hasSignature() {
                return strokes.some(s => s && s.length > 1);
            }

            function getSignatureDataURLScaled(targetW = 240, targetH = 80) {
                if (!hasSignature()) return {
                    dataURL: '',
                    w: 0,
                    h: 0
                };
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const srcW = sigCanvas.width / ratio,
                    srcH = sigCanvas.height / ratio;
                if (!targetW && !targetH) {
                    targetW = 240;
                    targetH = 80;
                }
                if (!targetW) targetW = Math.round(srcW * (targetH / srcH));
                if (!targetH) targetH = Math.round(srcH * (targetW / srcW));
                const off = document.createElement('canvas');
                off.width = Math.max(1, Math.floor(targetW * ratio));
                off.height = Math.max(1, Math.floor(targetH * ratio));
                const ctx = off.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, targetW, targetH);
                const sx = targetW / srcW,
                    sy = targetH / srcH;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.lineWidth = 2 * Math.min(sx, sy);
                for (let s of strokes) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x * sx, s[0].y * sy);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x * sx, s[i].y * sy);
                    ctx.stroke();
                }
                return {
                    dataURL: off.toDataURL('image/png'),
                    w: targetW,
                    h: targetH
                };
            }
            clearSigBtn?.addEventListener('click', () => {
                strokes = [];
                repaintSignature(true);
                if (sigPreview) {
                    sigPreview.src = '';
                    sigPreview.style.display = 'none';
                }
            });
            saveSigBtn?.addEventListener('click', () => {
                if (!hasSignature()) return showToast('Please provide a signature first.');
                const s = getSignatureDataURLScaled(240, 80);
                if (sigPreview) {
                    sigPreview.src = s.dataURL;
                    sigPreview.style.display = 'inline-block';
                }
                showToast('Signature saved.');
            });

            /* ======================== Word document generation ======================== */
            function buildOwnershipString() {
                const radios = document.getElementsByName('ownership');
                let out = [];
                ['Private', 'Government', 'Others'].forEach(v => {
                    let checked = false;
                    for (const r of radios) {
                        if (r.value === v && r.checked) {
                            checked = true;
                            break;
                        }
                    }
                    if (v === 'Others') {
                        const t = getVal('other-ownership');
                        out.push((checked ? '☒ ' : '☐ ') + 'Others' + (t ? (': ' + t) : ':'));
                    } else {
                        out.push((checked ? '☒ ' : '☐ ') + v);
                    }
                });
                return out.join(' ');
            }

            function buildTreeCutDocHTML(sigLocation, includeSignature, sigW, sigH) {
                const taxDecl = getVal('tax-declaration'),
                    lotNo = getVal('lot-no'),
                    contAr = getVal('contained-area');

                const first = getVal('first-name'),
                    middle = getVal('middle-name'),
                    last = getVal('last-name');
                const applicantName = [first, middle, last].filter(Boolean).join(' ');
                const street = getVal('street'),
                    barangay = getVal('barangay'),
                    municipality = getVal('municipality'),
                    province = getVal('province');
                const contact = getVal('contact-number'),
                    email = getVal('email'),
                    regno = getVal('registration-number');
                const location = getVal('location'),
                    purpose = getVal('purpose');
                const ownershipValue = buildOwnershipString();
                const speciesRows = readSpeciesRows();
                const totCount = $('#total-count')?.value || '0';
                const totVol = $('#total-volume')?.value || '0.00';
                const sigBlock = includeSignature ?
                    ('<img src="' + sigLocation + '" width="' + sigW + '" height="' + sigH + '" style="display:block;margin:8px 0 6px 0;border:1px solid #000;" alt="Signature">') :
                    '';

                return '<!DOCTYPE html>' +
                    '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">' +
                    '<head><meta charset="UTF-8"><title>Application for Tree Cutting Permit</title>' +
                    '<style>body,div,p,td{font-family:Arial,sans-serif;font-size:11pt;margin:0;line-height:1.5;padding:0;}' +
                    '.underline{text-decoration:underline;} table{border-collapse:collapse;width:100%;}' +
                    'table.bordered-table{border:1px solid #000;} table.bordered-table td,table.bordered-table th{border:1px solid #000;padding:5px;vertical-align:top;}' +
                    '.text-center{text-align:center;} .section-title{margin:15pt 0 6pt 0;font-weight:bold;} .signature-line{margin-top:24pt;border-top:1px solid #000;width:50%;padding-top:3pt;}' +
                    '</style><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]></head>' +
                    '<body>' +
                    '<div class="text-center"><p>Republic of the Philippines</p><p>Department of Environment and Natural Resources (DENR)</p>' +
                    '<p>Community Environment and Natural Resources Office (CENRO)</p><p>Lamacan, Argao, Cebu, Philippines 6021</p>' +
                    '<p>Tel. Nos. (+6332) 4600-711 | E-mail: <span class="underline">cenroargao@denr.gov.ph</span></p></div>' +
                    '<h3 class="text-center">APPLICATION FOR TREE CUTTING PERMIT</h3>' +
                    '<p class="section-title">PART I. APPLICANT\'S INFORMATION</p>' +
                    '<p>1. Applicant: ' + applicantName + '</p>' +
                    '<p>2. Address:</p>' +
                    '<p>&nbsp;&nbsp;&nbsp;&nbsp;Sitio/Street: ' + street + '</p>' +
                    '<p>&nbsp;&nbsp;&nbsp;&nbsp;Barangay: ' + barangay + '</p>' +
                    '<p>&nbsp;&nbsp;&nbsp;&nbsp;Municipality: ' + municipality + '</p>' +
                    '<p>&nbsp;&nbsp;&nbsp;&nbsp;Province: ' + province + '</p>' +
                    '<p>3. Contact No.: ' + contact + '</p>' +
                    '<p>4. Email Address: ' + email + '</p>' +
                    '<p>5. If Corporation: SEC/DTI Registration No. ' + regno + '</p>' +
                    '<p class="section-title">PART II. TREE CUTTING DETAILS</p>' +
                    '<p>1. Location of Area/Trees to be Cut: ' + location + '</p>' +
                    '<p>2. Ownership of Land: ' + ownershipValue + '</p>' +
                    '<p style="margin-left:18px;">Tax Declaration No.: ' + taxDecl + ' &nbsp;|&nbsp; Lot No.: ' + lotNo + ' &nbsp;|&nbsp; Contained Area: ' + contAr + '</p>' +

                    '<p>3. Number and Species of Trees Applied for Cutting:</p>' +
                    '<table class="bordered-table"><tr><th>Species</th><th>No. of Trees</th><th>Net Volume (cu.m)</th></tr>' +
                    (speciesRows.length ? speciesRows.map(s => '<tr><td>' + (s.name || '') + '</td><td>' + (s.count || '') + '</td><td>' + (s.volume || '') + '</td></tr>').join('') : '') +
                    '<tr><td><strong>TOTAL</strong></td><td><strong>' + totCount + '</strong></td><td><strong>' + totVol + '</strong></td></tr></table>' +
                    '<p>4. Purpose of Application for Tree Cutting Permit:</p><p>' + purpose + '</p>' +
                    '<p class="section-title">PART III. DECLARATION OF APPLICANT</p>' +
                    '<p>I hereby certify that the information provided in this application is true and correct. I understand that the approval of this application is subject to verification and evaluation by DENR, and that I shall comply with all terms and conditions of the Tree Cutting Permit once issued.</p>' +
                    sigBlock +
                    '<div class="signature-line">Signature Over Printed Name</div>' +
                    '</body></html>';
            }

            function makeMHTML(html, parts) {
                parts = parts || [];
                const boundary = '----=_NextPart_' + Date.now().toString(16);
                const header = [
                    'MIME-Version: 1.0',
                    'Content-Type: multipart/related; type="text/html"; boundary="' + boundary + '"',
                    '',
                    '--' + boundary,
                    'Content-Type: text/html; charset="utf-8"',
                    'Content-Transfer-Encoding: 8bit',
                    '',
                    html
                ].join('\r\n');
                const bodyParts = parts.map(p => {
                    const wrapped = (p.base64 || '').replace(/.{1,76}/g, '$&\r\n');
                    return [
                        '',
                        '--' + boundary,
                        'Content-Location: ' + p.location,
                        'Content-Transfer-Encoding: base64',
                        'Content-Type: ' + p.contentType,
                        '',
                        wrapped
                    ].join('\r\n');
                }).join('');
                return header + bodyParts + '\r\n--' + boundary + '--';
            }

            function dataURLtoBlob(dataURL, mime = 'image/png') {
                const base64 = dataURL.split(',')[1] || '';
                const byteChars = atob(base64);
                const byteNumbers = new Array(byteChars.length);
                for (let i = 0; i < byteChars.length; i++) byteNumbers[i] = byteChars.charCodeAt(i);
                return new Blob([new Uint8Array(byteNumbers)], {
                    type: mime
                });
            }

            function downloadMHTML(filename, mhtmlString) {
                const blob = new Blob([mhtmlString], {
                    type: 'application/msword'
                });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            /* ======================== Download (user copy) ======================== */
            const downloadBtn = document.getElementById('downloadBtn');
            const loading = document.getElementById('loadingIndicator');

            function showInlineLoader(on) {
                if (loading) loading.style.display = on ? 'block' : 'none';
            }

            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    showInlineLoader(true);
                    try {
                        const first = getVal('first-name'),
                            last = getVal('last-name');
                        if (!first || !last) {
                            showToast('First and last name are required.');
                            return;
                        }
                        calculateTotals();

                        const hasSig = hasSignature();
                        const TARGET_W = 160,
                            TARGET_H = 70;
                        const sigObj = hasSig ? getSignatureDataURLScaled(TARGET_W, TARGET_H) : null;

                        const html = buildTreeCutDocHTML('signature.png', !!sigObj, sigObj ? sigObj.w : TARGET_W, sigObj ? sigObj.h : TARGET_H);
                        const parts = sigObj ? [{
                            location: 'signature.png',
                            contentType: 'image/png',
                            base64: (sigObj.dataURL.split(',')[1] || '')
                        }] : [];
                        const mhtml = makeMHTML(html, parts);
                        downloadMHTML('Tree_Cutting_Permit_Application.doc', mhtml);
                    } finally {
                        setTimeout(() => showInlineLoader(false), 400);
                    }
                });
            }

            /* ======================== Submit flow ======================== */
            const confirmModal = document.getElementById('confirmModal');
            const closeConfirmModal = document.getElementById('closeConfirmModal');
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const submitApplicationBtn = document.getElementById('submitApplication');

            const resultModal = document.getElementById('resultModal');
            const closeResultModal = document.getElementById('closeResultModal');
            const resultOkBtn = document.getElementById('resultOkBtn');
            const resultIcon = document.getElementById('resultIcon');
            const resultTitle = document.getElementById('resultTitle');
            const resultMessage = document.getElementById('resultMessage');
            const resultDetails = document.getElementById('resultDetails');

            if (submitApplicationBtn) {
                submitApplicationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const firstName = getVal('first-name'),
                        lastName = getVal('last-name');
                    if (!firstName || !lastName) {
                        showToast('First name and last name are required.');
                        return;
                    }
                    if (!selectedFiles['file-1']) {
                        showToast('Please upload your Certificate of Verification.');
                        return;
                    }
                    openModal(confirmModal);
                });
            }
            if (closeConfirmModal) closeConfirmModal.addEventListener('click', () => closeModal(confirmModal));
            if (cancelSubmitBtn) cancelSubmitBtn.addEventListener('click', () => closeModal(confirmModal));

            function ownershipPicked() {
                const radios = document.getElementsByName('ownership');
                for (const r of radios)
                    if (r.checked) return r.value;
                return '';
            }

            async function handleSubmit() {
                closeModal(confirmModal);
                showGlobalLoader('Submitting your request…', 'Preparing documents and uploading to server.');

                try {
                    /* Build FormData with fields */
                    const formData = new FormData();
                    // Required identity
                    formData.append('first_name', getVal('first-name'));
                    formData.append('middle_name', getVal('middle-name'));
                    formData.append('last_name', getVal('last-name'));

                    // Contact & location
                    formData.append('street', getVal('street'));
                    formData.append('barangay', getVal('barangay'));
                    formData.append('municipality', getVal('municipality'));
                    formData.append('province', getVal('province'));
                    formData.append('contact_number', getVal('contact-number'));
                    formData.append('email', getVal('email'));
                    formData.append('registration_number', getVal('registration-number'));

                    // Tree cutting
                    formData.append('location', getVal('location'));
                    formData.append('ownership', ownershipPicked());
                    formData.append('other_ownership', getVal('other-ownership'));
                    formData.append('purpose', getVal('purpose'));
                    formData.append('tax_declaration', getVal('tax-declaration'));
                    formData.append('lot_no', getVal('lot-no'));
                    formData.append('contained_area', getVal('contained-area'));


                    // Species rows JSON
                    formData.append('species_rows_json', JSON.stringify(readSpeciesRows()));

                    // Always none for treecut (no renewal)
                    formData.append('permit_type', 'none');


                    /* Generate signature (optional) */
                    if (hasSignature()) {
                        const sigObj = getSignatureDataURLScaled(240, 80);
                        const sigBlob = dataURLtoBlob(sigObj.dataURL, 'image/png');
                        formData.append('signature_file', sigBlob, 'signature.png');
                    }

                    /* Generate Application .doc (MHTML) and attach */
                    const TARGET_W = 160,
                        TARGET_H = 70;
                    const sigObj2 = hasSignature() ? getSignatureDataURLScaled(TARGET_W, TARGET_H) : null;
                    const html = buildTreeCutDocHTML('signature.png', !!sigObj2, sigObj2 ? sigObj2.w : TARGET_W, sigObj2 ? sigObj2.h : TARGET_H);
                    const parts = sigObj2 ? [{
                        location: 'signature.png',
                        contentType: 'image/png',
                        base64: (sigObj2.dataURL.split(',')[1] || '')
                    }] : [];
                    const mhtml = makeMHTML(html, parts);
                    const appBlob = new Blob([mhtml], {
                        type: 'application/msword'
                    });
                    formData.append('application_doc', appBlob, 'Tree_Cutting_Permit_Application.doc');

                    /* Attach selected requirement files */
                    ['file-1', 'file-3', 'file-4', 'file-5', 'file-6', 'file-7a', 'file-7b', 'file-8', 'file-10a', 'file-10b']
                    .forEach(id => {
                        if (selectedFiles[id]) formData.append(id.replace('-', '_'), selectedFiles[id]);
                    });

                    /* Submit */
                    const endpoint = '../backend/users/treecut/addtreecut.php';
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data || data.success !== true) {
                        const errMsg = (data && data.errors && data.errors.join('\n')) || 'Failed to submit request.';
                        throw new Error(errMsg);
                    }

                    /* Success UI (NO SUCCESS MODAL) */
                    // Clear inputs
                    ['first-name', 'middle-name', 'last-name', 'street', 'barangay', 'municipality', 'province', 'contact-number', 'email', 'registration-number', 'location', 'other-ownership', 'purpose',
                        'tax-declaration', 'lot-no', 'contained-area'
                    ]
                    .forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });

                    // Reset species rows to 3 blank
                    while (speciesTbody && speciesTbody.rows.length > 0) speciesTbody.deleteRow(0);
                    addSpeciesRow('', '', '');
                    addSpeciesRow('', '', '');
                    addSpeciesRow('', '', '');
                    calculateTotals();

                    // Reset files
                    fileInputs.forEach(cfg => {
                        const fileInput = document.getElementById(cfg.id);
                        const uploadedFilesContainer = document.getElementById(cfg.uploaded);
                        if (fileInput) {
                            fileInput.value = '';
                            const nameEl = fileInput.parentElement.querySelector('.file-name');
                            if (nameEl) nameEl.textContent = 'No file chosen';
                        }
                        if (uploadedFilesContainer) uploadedFilesContainer.innerHTML = '';
                    });
                    selectedFiles = {};

                    // Clear signature
                    strokes = [];
                    repaintSignature(true);
                    const sigPreviewImg = document.getElementById('signature-image');
                    if (sigPreviewImg) {
                        sigPreviewImg.src = '';
                        sigPreviewImg.style.display = 'none';
                    }

                    // Only a short toast
                    showToast('Application submitted!', 2000);

                } catch (err) {
                    // Error path: show modal
                    if (resultIcon) resultIcon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc2626;"></i>';
                    if (resultTitle) resultTitle.textContent = 'Submission failed';
                    if (resultMessage) resultMessage.textContent = (err && err.message) ? err.message : 'Unexpected error occurred.';
                    if (resultDetails) resultDetails.innerHTML = '';
                    openModal(resultModal);
                } finally {
                    hideGlobalLoader();
                }
            }

            if (confirmSubmitBtn) confirmSubmitBtn.addEventListener('click', handleSubmit);
            if (closeResultModal) closeResultModal.addEventListener('click', () => closeModal(resultModal));
            if (resultOkBtn) resultOkBtn.addEventListener('click', () => closeModal(resultModal));
        });
    </script>
    <script>
        /* ===== VALIDATION-ONLY SCRIPT (drop-in replacement for your small validation block) ===== */
        (() => {
            const NAME_RE = /^[A-Za-zÀ-ÖØ-öø-ÿÑñ\s'.-]+$/; // letters, spaces, apostrophe, hyphen, dot
            const PHONE_RE = /^09\d{9}$/; // PH mobile: starts with 09 + 9 digits = 11 total
            const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;

            const $id = (id) => document.getElementById(id);

            const tracked = [];

            function ensureHint(input) {
                const group = input.closest('.name-field, .form-group, td') || input.parentElement;
                let hint = group.querySelector('.field-hint');
                if (!hint) {
                    hint = document.createElement('div');
                    hint.className = 'field-hint';
                    group.appendChild(hint);
                }
                return hint;
            }

            function attachValidator(input, {
                required = false,
                pattern = null,
                min = 0,
                max = 0,
                message = 'Invalid value.'
            }) {
                if (!input) return;
                const hint = ensureHint(input);

                function show(msg) {
                    input.classList.add('input-error');
                    hint.textContent = msg;
                    hint.style.display = 'block';
                    return false;
                }

                function clear() {
                    input.classList.remove('input-error');
                    hint.textContent = '';
                    hint.style.display = 'none';
                    return true;
                }

                function validate() {
                    const v = (input.value || '').trim();
                    if (!v) return required ? show('This field is required.') : clear();
                    if (min && v.length < min) return show(`Must be at least ${min} characters.`);
                    if (max && v.length > max) return show(`Must be at most ${max} characters.`);
                    if (pattern && !pattern.test(v)) return show(message);
                    return clear();
                }

                input.addEventListener('input', validate);
                input.addEventListener('blur', validate);
                tracked.push({
                    input,
                    validate
                });
            }

            /* ---------- Text fields ---------- */
            attachValidator($id('first-name'), {
                required: true,
                pattern: NAME_RE,
                message: "Letters, spaces, ' - . only."
            });
            attachValidator($id('middle-name'), {
                required: false,
                pattern: NAME_RE,
                message: "Letters, spaces, ' - . only."
            });
            attachValidator($id('last-name'), {
                required: true,
                pattern: NAME_RE,
                message: "Letters, spaces, ' - . only."
            });

            // Address & misc: min 2 chars if provided (toggle required:true if you want them mandatory)
            ['street', 'barangay', 'municipality', 'province', 'location', 'purpose', 'tax-declaration', 'lot-no', 'contained-area']
            .forEach(id => attachValidator($id(id), {
                required: false,
                min: 2,
                message: 'Must be at least 2 characters.'
            }));

            // Contact No.: PH mobile only (starts with 09, 11 digits)
            attachValidator($id('contact-number'), {
                required: true,
                pattern: PHONE_RE,
                message: 'Enter a valid PH mobile (starts with 09 and 11 digits).'
            });

            // Email (optional; set required:true if you want to enforce)
            attachValidator($id('email'), {
                required: false,
                pattern: EMAIL_RE,
                message: 'Enter a valid email (name@host.tld).'
            });

            /* ---------- Species table constraints ---------- */
            const tbody = $id('species-table-body');

            const isCtrl = (e) =>
                e.ctrlKey || e.metaKey || ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Tab', 'Escape', 'Enter'].includes(e.key);

            if (tbody) {
                // Block invalid keystrokes
                tbody.addEventListener('keydown', (e) => {
                    const t = e.target;
                    if (!t || !t.classList) return;

                    if (t.classList.contains('species-name')) {
                        if (!isCtrl(e) && !/^[\p{L}\s]$/u.test(e.key)) e.preventDefault(); // letters + spaces only
                    }

                    if (t.classList.contains('species-count')) {
                        if (!isCtrl(e) && !/^[0-9]$/.test(e.key)) e.preventDefault(); // digits only
                    }

                    if (t.classList.contains('species-volume')) {
                        if (!isCtrl(e) && !/^[0-9.]$/.test(e.key)) e.preventDefault(); // digits or dot
                        if (e.key === '.' && t.value.includes('.')) e.preventDefault(); // single dot
                    }
                });

                // Guard paste/drag-drop
                tbody.addEventListener('beforeinput', (e) => {
                    const t = e.target,
                        d = e.data || '';
                    if (!t || !t.classList || !d) return;
                    if (t.classList.contains('species-name') && !/^[\p{L}\s]+$/u.test(d)) e.preventDefault();
                    if (t.classList.contains('species-count') && !/^[0-9]+$/.test(d)) e.preventDefault();
                    if (t.classList.contains('species-volume') && !/^[0-9.]+$/.test(d)) e.preventDefault();
                });

                // Sanitize + tiny inline hint for species min length
                tbody.addEventListener('input', (e) => {
                    const t = e.target;
                    if (!t || !t.classList) return;

                    if (t.classList.contains('species-name')) {
                        t.value = (t.value || '').replace(/[^\p{L}\s]/gu, '');
                        const group = t.closest('td');
                        const hint = ensureHint(t);
                        if (t.value.trim() && t.value.trim().length < 2) {
                            t.classList.add('input-error');
                            hint.textContent = 'At least 2 letters.';
                            hint.style.display = 'block';
                        } else {
                            t.classList.remove('input-error');
                            hint.textContent = '';
                            hint.style.display = 'none';
                        }
                    }

                    if (t.classList.contains('species-count')) {
                        t.value = (t.value || '').replace(/\D/g, '');
                    }

                    if (t.classList.contains('species-volume')) {
                        let v = (t.value || '').replace(/[^0-9.]/g, '');
                        const dot = v.indexOf('.');
                        if (dot !== -1) v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
                        v = v.replace(/^(\d+)(\.\d{0,2})?.*$/, '$1$2'); // limit to 2 decimals
                        t.value = v;
                    }
                });
            }

            // Expose a single function your submit flow can call
            window.validateAllFields = function() {
                let ok = true,
                    firstBad = null;
                for (const {
                        input,
                        validate
                    }
                    of tracked) {
                    if (!validate() && !firstBad) {
                        firstBad = input;
                        ok = false;
                    }
                }
                if (!ok && firstBad) firstBad.focus();
                return ok;
            };
        })();
    </script>


</body>





</html>