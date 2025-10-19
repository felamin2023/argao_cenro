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
    <title>Lumber Dealer Permit Application</title>
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
            border: 1px solid #ddd;
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
            color: #555;
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
            border: 1px solid #ddd;
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
            color: #555;
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
            border-top: 1px solid #ddd;
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
            padding: 3px 10px;
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

        .form-section h2,
        .form-section h3 {
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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            transition: border-color 0.3s;
            min-height: 48px;
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
            width: 300px;
            display: inline-block;
            background: transparent;
        }

        .declaration-input:focus {
            border-bottom: 2px solid #2b6625;
            outline: none;
            box-shadow: none;
        }

        .required::after {
            content: " *";
            color: #ff4757;
        }

        .suppliers-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .suppliers-table th,
        .suppliers-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        .suppliers-table th {
            background-color: #f2f2f2;
        }

        .suppliers-table input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            min-height: 48px;
        }

        .supplier-name {
            width: 70%;
        }

        .supplier-volume {
            width: 25%;
        }

        .add-row {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .remove-btn {
            background-color: #ff4757;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .govt-employee-container {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .govt-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .govt-option input[type="radio"] {
            width: auto;
            min-height: auto;
        }

        .govt-option label {
            margin-bottom: 0;
            font-weight: normal;
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

            .suppliers-table {
                display: block;
                overflow-x: auto;
            }

            .govt-employee-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
            color: #2b6625;
            font-weight: bold;
        }

        .loading i {
            margin-right: 10px;
        }

        /* Print-specific styles */
        @media print {

            .download-btn,
            .add-row,
            .signature-actions,
            .signature-pad-container,
            .remove-btn {
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
        <button type="button" class="mobile-toggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active" data-dropdown-toggle>
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-exclamation-triangle"></i>
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
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item active-page">
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
                <div class="nav-icon" data-dropdown-toggle>
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
        <!-- <div class="action-buttons">
            <button type="button" class="btn btn-primary" id="addFilesBtn">
                <i class="fas fa-plus-circle"></i> Add
            </button>
            <a href="usereditlumber.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewlumber.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div> -->

        <div class="requirements-form">
            <div class="form-header">
                <h2>Lumber Dealer Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <!-- Permit Type Selector -->
                <div class="permit-type-selector">
                    <button type="button" class="permit-type-btn active" data-type="new">New Permit</button>
                    <button type="button" class="permit-type-btn" data-type="renewal">Renewal</button>
                </div>

                <!-- ===================== NEW LUMBER DEALER PERMIT APPLICATION (UI only) ===================== -->
                <div class="form-section" id="lumber-application-section" style="margin-top:16px;">
                    <!-- Applicant Information -->
                    <div class="form-subsection">
                        <h2>Applicant Information</h2>
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="applicant-age" class="required">Age:</label>
                                <input type="number" id="applicant-age" min="18" placeholder="18+">
                            </div>

                            <div class="form-group">
                                <label class="required">Government Employee:</label>
                                <div class="govt-employee-container" style="display:flex; gap:16px;">
                                    <div class="govt-option">
                                        <input type="radio" id="govt-employee-no" name="govt-employee" value="no" checked>
                                        <label for="govt-employee-no">No</label>
                                    </div>
                                    <div class="govt-option">
                                        <input type="radio" id="govt-employee-yes" name="govt-employee" value="yes">
                                        <label for="govt-employee-yes">Yes</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="business-name" class="required">Business Name:</label>
                            <input type="text" id="business-name" placeholder="Registered business name">
                        </div>


                        <div class="form-group">
                            <label for="business-address" class="required">Business Address:</label>
                            <input type="text" id="business-address" placeholder="Full business address">
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h3>Business Information</h3>

                        <div class="form-group">
                            <label for="operation-place" class="required">Proposed Place of Operation:</label>
                            <input type="text" id="operation-place" placeholder="Full address of operation place">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="annual-volume" class="required">Expected Gross Annual Volume of Business:</label>
                                <input type="text" id="annual-volume" placeholder="e.g., 1,000 bd ft">
                            </div>
                            <div class="form-group">
                                <label for="annual-worth" class="required">Worth:</label>
                                <input type="text" id="annual-worth" placeholder="e.g., ₱500,000">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="employees-count" class="required">Total Number of Employees:</label>
                                <input type="number" id="employees-count" min="0" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label for="dependents-count" class="required">Total Number of Dependents:</label>
                                <input type="number" id="dependents-count" min="0" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- Suppliers Information -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h3>Suppliers Information</h3>

                        <table class="suppliers-table" id="suppliers-table">
                            <thead>
                                <tr>
                                    <th width="70%">Suppliers Name/Company</th>
                                    <th width="25%">Volume</th>
                                    <th width="5%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="supplier-name" placeholder="Supplier name"></td>
                                    <td><input type="text" class="supplier-volume" placeholder="Volume"></td>
                                    <td><button type="button" class="remove-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row" id="add-supplier-row">Add Supplier</button>
                    </div>

                    <!-- Market & Experience -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h4>Market Information</h4>

                        <div class="form-group">
                            <label for="intended-market" class="required">Intended Market (Barangays and Municipalities to be served):</label>
                            <textarea id="intended-market" rows="3" placeholder="List barangays and municipalities"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="experience" class="required">Experience as a Lumber Dealer:</label>
                            <textarea id="experience" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
                        </div>
                    </div>

                    <!-- Declaration & Signature -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h4>Declaration</h4>

                        <div class="declaration">
                            <p>I will fully comply with Republic Act No. 123G and the rules and regulations of the Forest Management Bureau.</p>

                            <p>I understand that false statements or omissions may result in:</p>
                            <ul style="margin-left: 20px; margin-bottom: 15px;">
                                <li>Disapproval of this application</li>
                                <li>Cancellation of registration</li>
                                <li>Forfeiture of bond</li>
                                <li>Criminal liability</li>
                            </ul>

                            <p>
                                I, <input type="text" id="declaration-name" class="declaration-input" placeholder="Enter your full name">,
                                after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.
                            </p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <!-- Width/height set in style so CSS size = drawing size (prevents “dead zone”) -->
                                        <canvas id="signature-pad" style="width:100%;height:220px;display:block;"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                        <!-- <button type="button" class="signature-btn save-signature" id="save-signature">Save Signature</button> -->
                                    </div>
                                    <div class="signature-preview">
                                        <img id="signature-image" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ===================== /NEW LUMBER DEALER PERMIT APPLICATION ===================== -->

                <!-- ===================== RENEWAL LUMBER DEALER PERMIT APPLICATION ===================== -->
                <div class="form-section" id="renewal-application-section" style="margin-top:16px; display:none;">
                    <!-- Applicant Information -->
                    <div class="form-subsection">
                        <h2>Applicant Information (Renewal)</h2>

                        <!-- split into First/Middle/Last -->
                        <div class="name-fields">
                            <div class="name-field">
                                <input type="text" id="first-name-ren" placeholder="First Name" required>
                            </div>
                            <div class="name-field">
                                <input type="text" id="middle-name-ren" placeholder="Middle Name">
                            </div>
                            <div class="name-field">
                                <input type="text" id="last-name-ren" placeholder="Last Name" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="applicant-age-ren" class="required">Age:</label>
                            <input type="number" id="applicant-age-ren" min="18">
                        </div>
                        <div class="form-group">
                            <label for="business-name-ren" class="required">Business Name:</label>
                            <input type="text" id="business-name-ren" placeholder="Registered business name">
                        </div>


                        <div class="form-group">
                            <label for="business-address-ren" class="required">Business Address:</label>
                            <input type="text" id="business-address-ren" placeholder="Full business address">
                        </div>

                        <div class="form-group">
                            <label class="required">Government Employee:</label>
                            <div class="govt-employee-container" style="display:flex; gap:16px;">
                                <div class="govt-option">
                                    <input type="radio" id="govt-employee-ren-no" name="govt-employee-ren" value="no" checked>
                                    <label for="govt-employee-ren-no">No</label>
                                </div>
                                <div class="govt-option">
                                    <input type="radio" id="govt-employee-ren-yes" name="govt-employee-ren" value="yes">
                                    <label for="govt-employee-ren-yes">Yes</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h3>Business Information (Renewal)</h3>

                        <div class="form-group">
                            <label for="operation-place-ren" class="required">Place of Operation:</label>
                            <input type="text" id="operation-place-ren" placeholder="Full address of operation place">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="annual-volume-ren" class="required">Expected Gross Annual Volume of Business:</label>
                                <input type="text" id="annual-volume-ren" placeholder="e.g., 1000 board feet">
                            </div>

                            <div class="form-group">
                                <label for="annual-worth-ren" class="required">Value:</label>
                                <input type="text" id="annual-worth-ren" placeholder="e.g., ₱500,000">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="employees-count-ren" class="required">Total Number of Employees:</label>
                                <input type="number" id="employees-count-ren" min="0">
                            </div>

                            <div class="form-group">
                                <label for="dependents-count-ren" class="required">Total Number of Dependents:</label>
                                <input type="number" id="dependents-count-ren" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Suppliers Information -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h3>Suppliers Information (Renewal)</h3>

                        <table class="suppliers-table" id="suppliers-table-ren">
                            <thead>
                                <tr>
                                    <th width="70%">SUPPLIERS NAME/COMPANY</th>
                                    <th width="25%">VOLUME</th>
                                    <th width="5%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="supplier-name-ren" placeholder="Supplier name"></td>
                                    <td><input type="text" class="supplier-volume-ren" placeholder="Volume"></td>
                                    <td><button type="button" class="remove-btn-ren">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row" id="add-supplier-row-ren">Add Supplier</button>
                    </div>

                    <!-- Business Details -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h3>Business Details (Renewal)</h3>

                        <div class="form-group">
                            <label for="intended-market-ren" class="required">Selling Products To:</label>
                            <textarea id="intended-market-ren" rows="3" placeholder="List adjacent barangays and municipalities"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="experience-ren" class="required">Experience as a Lumber Dealer:</label>
                            <textarea id="experience-ren" rows="3" placeholder="Describe your experience in the lumber business"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="prev-certificate-ren">Previous Certificate of Registration No.:</label>
                            <input type="text" id="prev-certificate-ren" placeholder="Certificate number">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="issued-date-ren">Issued On:</label>
                                <input type="date" id="issued-date-ren">
                            </div>

                            <div class="form-group">
                                <label for="expiry-date-ren">Expires On:</label>
                                <input type="date" id="expiry-date-ren">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="cr-license-ren">C.R. License No.:</label>
                                <input type="text" id="cr-license-ren" placeholder="License number">
                            </div>

                            <div class="form-group">
                                <label for="sawmill-permit-ren">Sawmill Permit No.:</label>
                                <input type="text" id="sawmill-permit-ren" placeholder="Permit number">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="required">Buying logs or lumber from other sources:</label>
                            <div class="govt-employee-container" style="display:flex; gap:16px;">
                                <div class="govt-option">
                                    <input type="radio" id="other-sources-ren-no" name="other-sources-ren" value="no" checked>
                                    <label for="other-sources-ren-no">No</label>
                                </div>
                                <div class="govt-option">
                                    <input type="radio" id="other-sources-ren-yes" name="other-sources-ren" value="yes">
                                    <label for="other-sources-ren-yes">Yes</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Declaration & Signature (RENEWAL) -->
                    <div class="form-subsection" style="margin-top:18px;">
                        <h4>Declaration (Renewal)</h4>

                        <div class="declaration">
                            <p>I will faithfully comply with all provisions of Rep. Act No. 1239 as well as rules and regulations of the Forest Management Bureau.</p>
                            <p>I fully understand that false statements or material omissions may cause the cancellation of the registration and the forfeiture of the bond, without prejudice to criminal action that the government may take against me.</p>
                            <p>I FINALLY UNDERSTAND THAT THE MERE FILING OF THE APPLICATION AND/OR PAYMENT OF THE NECESSARY FEES DOES NOT ENTITLE ME TO START OPERATION WHICH MUST COMMENCE ONLY AFTER THE ISSUANCE OF THE CERTIFICATE OF REGISTRATION.</p>

                            <p style="text-align: center; font-weight: bold;">REPUBLIC OF THE PHILIPPINES</p>

                            <p>
                                I, <input type="text" id="declaration-name-ren" class="declaration-input" placeholder="Enter your full name">,
                                the applicant after having been sworn to upon my oath, depose and say: That I have thoroughly read the foregoing application and that every statement therein is true to the best of my knowledge and belief.
                            </p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <!-- Same fix: match CSS size & drawing size -->
                                        <canvas id="signature-pad-ren" style="width:100%;height:220px;display:block;"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="clear-signature-ren">Clear</button>
                                        <button type="button" class="signature-btn save-signature" id="save-signature-ren">Save Signature</button>
                                    </div>
                                    <div class="signature-preview">
                                        <img id="signature-image-ren" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ===================== /RENEWAL LUMBER DEALER PERMIT APPLICATION ===================== -->

                <!-- Requirements List -->
                <div class="requirements-list">
                    <!-- Requirement 1 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">1</span>
                                <span class="requirement-number renewal-number" style="display:none">1</span>
                                Complete Staff Work (CSW) by the inspecting officer- 3 copies from inspecting officer for signature of RPS chief
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
                        </div>
                    </div>

                    <!-- Requirement 2 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">2</span>
                                <span class="requirement-number renewal-number" style="display:none">2</span>
                                Geo-tagged pictures of the business establishment (3 copies from inspecting officer for signature of RPS chief)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <!-- Requirement 3 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">3</span>
                                <span class="requirement-number renewal-number" style="display:none">3</span>
                                Application form duly accomplished (3 copies)
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
                        </div>
                    </div>

                    <!-- Requirement 4 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">4</span>
                                <span class="requirement-number renewal-number" style="display:none">4</span>
                                Log/Lumber Supply Contract (approved by RED) - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 5 (New Only) -->
                    <div class="requirement-item" id="requirement-5">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">5</span>
                                Business Management Plan (3 copies)
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
                        </div>
                    </div>

                    <!-- Requirement 6 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">6</span>
                                <span class="requirement-number renewal-number" style="display:none">5</span>
                                Mayor's Permit - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 7 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">7</span>
                                <span class="requirement-number renewal-number" style="display:none">6</span>
                                Certificate of Registration by DTI/SEC - 3 copies
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <!-- Requirement 8 (New Only) -->
                    <div class="requirement-item" id="requirement-8">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">8</span>
                                Latest Annual Income Tax Return - 3 copies
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
                        </div>
                    </div>

                    <!-- Requirement 9 (Renewal Only) -->
                    <div class="requirement-item" id="requirement-9">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number renewal-number">7</span>
                                Monthly and Quarterly Reports from the date issued to date (for renewal only) - 3 copies
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-9" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                        </div>
                    </div>

                    <!-- Requirement 10 (Common) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">9</span>
                                <span class="requirement-number renewal-number">8</span>
                                Regulatory Fees
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Official Receipt</p>
                                <div class="file-input-container">
                                    <label for="file-10a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Photocopy of Order of Payment</p>
                                <div class="file-input-container">
                                    <label for="file-10b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-10b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Requirements List -->
            </div>

            <div class="form-footer">
                <button type="button" class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Submit Application</div>
            <div style="padding:16px 20px;line-height:1.6">
                Please confirm you want to submit this <b>Lumber Dealer</b> application. Files will be uploaded and your request will enter review.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
                <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
            </div>
        </div>
    </div>

    <!-- Loading overlay (used during final save) -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998;">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;">Working…</div>
    </div>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Need Released NEW modal (attempting renewal without released NEW) -->
    <div id="needApprovedNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Action Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                To request a <b>renewal</b>, you must have a <b>RELEASED NEW</b> lumber dealer permit on record.<br><br>
                You can switch to a NEW permit request. We’ll copy over what you’ve already entered.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="needApprovedNewOk" class="btn btn-outline" type="button">Okay</button>
                <button id="needApprovedNewSwitch" class="btn btn-primary" type="button">Request new</button>
            </div>
        </div>
    </div>


    <!-- Pending NEW request modal -->
    <div id="pendingNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Pending Request</div>
            <div style="padding:16px 20px;line-height:1.6">
                You already have a pending <b>NEW</b> lumber dealer permit request. Please wait for updates before submitting another one.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="pendingNewOk" class="btn btn-primary" type="button">Okay</button>
            </div>
        </div>
    </div>

    <!-- Offer renewal modal (when user tries NEW but has approved NEW) -->
    <div id="offerRenewalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Renewal Available</div>
            <div style="padding:16px 20px;line-height:1.6">
                You can’t request a <b>new</b> lumber dealer permit because you already have a <b>RELEASED NEW</b>. You’re allowed to request a <b>renewal</b> instead.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="offerRenewalOk" class="btn btn-outline" type="button">Okay</button>
                <button id="offerRenewalSwitch" class="btn btn-primary" type="button">Request renewal</button>
            </div>
        </div>
    </div>
    <!-- Use Existing Client modal -->
    <div id="existingClientModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Client Found</div>
            <div style="padding:16px 20px;line-height:1.6">
                We found an existing <b>client record</b> with the same name.<br><br>
                Do you want to <b>use the existing client</b> for this <b>lumber</b> application,
                or create a new client record?
                <div id="existingClientHint" style="margin-top:10px;color:#666;font-size:.95rem;"></div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="existingClientCancel" class="btn btn-outline" type="button">Cancel</button>
                <button id="existingClientNew" class="btn btn-outline" type="button">Create new</button>
                <button id="existingClientUse" class="btn btn-primary" type="button">Use existing</button>
            </div>
        </div>
    </div>
    <!-- Use Existing Client (RENEWAL) -->
    <div id="existingClientModalRen" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Client Found</div>
            <div style="padding:16px 20px;line-height:1.6">
                We found an existing <b>client record</b> that matches your details for a <b>renewal</b>.
                <div id="existingClientHintRen" style="margin-top:10px;color:#666;font-size:.95rem;"></div>
                <div style="margin-top:10px;">Is this the correct client?</div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="existingClientRenCancel" class="btn btn-outline" type="button">Cancel</button>
                <button id="existingClientRenConfirm" class="btn btn-primary" type="button">Confirm</button>
            </div>
        </div>
    </div>

    <!-- For Payment (global block) -->
    <div id="forPaymentModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:540px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Payment Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                You still have an unpaid lumber permit on record (<b>for payment</b>).<br>
                Please settle this <b>personally at the office</b> before filing another request.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="forPaymentOk" class="btn btn-primary" type="button">Okay</button>
            </div>
        </div>
    </div>


    <script>
        (function() {
            'use strict';

            /* =========================
               Constants / Endpoints
            ========================== */
            const SAVE_URL = new URL('../backend/users/lumber/save_lumber.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/lumber/precheck_lumber.php', window.location.href).toString();

            /* =========================
               Tiny helpers
            ========================== */
            const $ = (sel, root) => (root || document).querySelector(sel);
            const $all = (sel, root) => Array.from((root || document).querySelectorAll(sel));
            const show = (el, on = true) => {
                if (el) el.style.display = on ? '' : 'none';
            };
            const v = (id) => (document.getElementById(id)?.value || '').trim();
            const display = (el, val) => {
                if (el && el.style) el.style.display = val;
            }; // safe show/hide

            function toast(msg) {
                const n = document.getElementById('profile-notification');
                if (!n) return;
                n.textContent = msg;
                display(n, 'block');
                n.style.opacity = '1';
                setTimeout(() => {
                    n.style.opacity = '0';
                    setTimeout(() => {
                        display(n, 'none');
                        n.style.opacity = '1';
                    }, 350);
                }, 2400);
            }

            function dataURLToBlob(dataURL) {
                if (!dataURL) return null;
                const [meta, b64] = dataURL.split(',');
                const mime = (meta.match(/data:(.*?);base64/) || [])[1] || 'application/octet-stream';
                const bin = atob(b64 || '');
                const u8 = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                return new Blob([u8], {
                    type: mime
                });
            }

            function makeMHTML(html, parts = []) {
                const boundary = '----=_NextPart_' + Date.now().toString(16);
                const header = [
                    'MIME-Version: 1.0',
                    `Content-Type: multipart/related; type="text/html"; boundary="${boundary}"`,
                    'X-MimeOLE: Produced By Microsoft MimeOLE',
                    '',
                    `--${boundary}`,
                    'Content-Type: text/html; charset="utf-8"',
                    'Content-Transfer-Encoding: 8bit',
                    '',
                    html
                ].join('\r\n');
                const bodyParts = parts.map(p => {
                    const wrapped = (p.base64 || '').replace(/.{1,76}/g, '$&\r\n');
                    return [
                        '', `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        'Content-Transfer-Encoding: base64',
                        `Content-Type: ${p.contentType}`, '',
                        wrapped
                    ].join('\r\n');
                }).join('');
                return header + bodyParts + `\r\n--${boundary}--`;
            }

            function svgToPngDataUrl(svgString) {
                return new Promise((resolve) => {
                    try {
                        const svgBlob = new Blob([svgString], {
                            type: 'image/svg+xml;charset=utf-8'
                        });
                        const url = URL.createObjectURL(svgBlob);
                        const img = new Image();
                        img.onload = function() {
                            const c = document.createElement('canvas');
                            c.width = img.width;
                            c.height = img.height;
                            c.getContext('2d').drawImage(img, 0, 0);
                            URL.revokeObjectURL(url);
                            try {
                                resolve(c.toDataURL('image/png'));
                            } catch {
                                resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                            }
                        };
                        img.onerror = () => resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                        img.src = url;
                    } catch {
                        resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
                    }
                });
            }

            function wordHeaderStyles() {
                return `
      body, div, p { line-height:1.8; font-family: Arial; font-size:11pt; margin:0; padding:0; }
      .section-title { font-weight:700; margin:15pt 0 6pt 0; text-decoration:underline; }
      .info-line { margin:12pt 0; }
      .underline { display:inline-block; min-width:300px; border-bottom:1px solid #000; padding:0 5px; margin:0 5px; }
      .declaration { margin-top:15pt; }
      .signature-line { margin-top:36pt; border-top:1px solid #000; width:50%; padding-top:3pt; }
      .header-container { position:relative; margin-bottom:20px; width:100%; }
      .header-logo { width:80px; height:80px; }
      .header-content { text-align:center; margin:0 auto; width:100%; }
      .header-content p { margin:0; padding:0; }
      .bold { font-weight:700; }
      .suppliers-table { width:100%; border-collapse:collapse; margin:15px 0; }
      .suppliers-table th, .suppliers-table td { border:1px solid #000; padding:8px; text-align:left; }
      .suppliers-table th { background:#f2f2f2; }
    `;
            }

            function esc(s) {
                return String(s || '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function suppliersTableHTML(suppliers) {
                const body = suppliers.length ?
                    suppliers.map(s => `<tr><td>${esc(s.name)}</td><td>${esc(s.volume)}</td></tr>`).join('') :
                    `<tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr>`;
                return `
    <table class="suppliers-table">
      <tr><th>SUPPLIERS NAME/COMPANY</th><th>VOLUME</th></tr>
      ${body}
    </table>`;
            }


            function buildNewDocHTML(logoHref, sigHref, F, suppliers) {
                const notStr = F.govEmp === 'yes' ? '' : 'not';
                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Lumber Dealer Permit Application</title>
  <style>${wordHeaderStyles()}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="${logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">NEW LUMBER DEALER PERMIT APPLICATION</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">${esc(F.fullName)}</span>, <span class="underline">${esc(F.applicantAge)}</span> years old, with business address at <span class="underline">${esc(F.businessAddress)}</span>, hereby apply for registration as a Lumber Dealer.</p>


  <p class="info-line">1. I am ${notStr} a government employee and have ${notStr} received any compensation from the government.</p>

  <p class="info-line">2. Proposed place of operation: <span class="underline">${F.operationPlace || ''}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">${F.annualVolume || ''}</span> worth <span class="underline">${F.annualWorth || ''}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">${F.employeesCount || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">${F.dependentsCount || ''}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  ${suppliersTableHTML(suppliers)}

  <p class="info-line">6. Intended market (barangays and municipalities to be served): <span class="underline">${F.intendedMarket || ''}</span></p>

  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">${F.experience || ''}</span></p>

  <p class="info-line">8. I will fully comply with Republic Act No. 123G and the rules and regulations of the Forest Management Bureau.</p>

  <p class="info-line">9. I understand that false statements or omissions may result in:</p>
  <ul style="margin-left:40px;">
    <li>Disapproval of this application</li>
    <li>Cancellation of registration</li>
    <li>Forfeiture of bond</li>
    <li>Criminal liability</li>
  </ul>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">${F.declarationName || ''}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      ${sigHref
        ? `
          <!--[if gte mso 9]><v:shape style="width:300px;height:110px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${sigHref}" o:title="Signature"/></v:shape><![endif]-->
          <!--[if !mso]><!-- --><img src="${sigHref}" width="300" height="110" alt="Signature"/><!--<![endif]-->
          <p>Signature of Applicant</p>
        `
        : `
          <div class="signature-line"></div>
          <p>Signature of Applicant</p>
        `}
    </div>
  </div>
</body>
</html>`;
            }

            function buildRenewalDocHTML(logoHref, sigHref, F, suppliers) {
                const notStr = F.govEmp === 'yes' ? '' : 'not';
                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Renewal – Lumber Dealer Permit</title>
  <style>${wordHeaderStyles()}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="${logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">RENEWAL OF LUMBER DEALER PERMIT</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">${F.fullName || ''}</span>, <span class="underline">${F.applicantAge || ''}</span> years old, with business address at <span class="underline">${F.businessAddress || ''}</span>, hereby apply for <b>renewal</b> of registration as a Lumber Dealer.</p>

  <p class="info-line">1. I am ${notStr} a government employee and have ${notStr} received any compensation from the government.</p>

  <p class="info-line">2. Place of operation: <span class="underline">${F.operationPlace || ''}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">${F.annualVolume || ''}</span> worth <span class="underline">${F.annualWorth || ''}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">${F.employeesCount || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">${F.dependentsCount || ''}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  ${suppliersTableHTML(suppliers)}

  <p class="info-line">6. Selling products to: <span class="underline">${F.intendedMarket || ''}</span></p>
  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">${F.experience || ''}</span></p>

  <p class="info-line">8. Previous Certificate of Registration No.: <span class="underline">${F.prevCert || ''}</span></p>
  <p class="info-line" style="margin-left:20px;">Issued On: <span class="underline">${F.issuedDate || ''}</span> &nbsp; Expires On: <span class="underline">${F.expiryDate || ''}</span></p>

  <p class="info-line">9. C.R. License No.: <span class="underline">${F.crLicense || ''}</span> &nbsp;&nbsp; Sawmill Permit No.: <span class="underline">${F.sawmillPermit || ''}</span></p>
  <p class="info-line">10. Buying logs/lumber from other sources: <span class="underline">${(F.buyingOther || '').toUpperCase()}</span></p>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">${F.declarationName || ''}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      ${sigHref
        ? `
          <!--[if gte mso 9]><v:shape style="width:300px;height:110px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="${sigHref}" o:title="Signature"/></v:shape><![endif]-->
          <!--[if !mso]><!-- --><img src="${sigHref}" width="300" height="110" alt="Signature"/><!--<![endif]-->
          <p>Signature of Applicant</p>
        `
        : `
          <div class="signature-line"></div>
          <p>Signature of Applicant</p>
        `}
    </div>
  </div>
</body>
</html>`;
            }

            /* =========================
               Mobile menu + header dropdowns
            ========================== */
            const mobileToggle = $('.mobile-toggle');
            mobileToggle?.addEventListener('click', () => {
                const nav = $('.nav-container');
                nav?.classList.toggle('active');
                document.body.style.overflow = nav?.classList.contains('active') ? 'hidden' : '';
            });

            const dropdownIcons = $all('.nav-item.dropdown [data-dropdown-toggle], .nav-item.dropdown .nav-icon');
            dropdownIcons.forEach(icon => {
                icon.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const item = icon.closest('.nav-item');
                    const menu = item?.querySelector('.dropdown-menu');
                    $all('.dropdown-menu._open').forEach(m => {
                        if (m !== menu) {
                            m.classList.remove('_open');
                            Object.assign(m.style, {
                                opacity: 0,
                                visibility: 'hidden',
                                pointerEvents: 'none',
                                transform: 'translateY(10px)'
                            });
                        }
                    });
                    if (!menu) return;
                    const open = !menu.classList.contains('_open');
                    menu.classList.toggle('_open', open);
                    Object.assign(menu.style, open ? {
                        opacity: 1,
                        visibility: 'visible',
                        pointerEvents: 'auto',
                        transform: 'translateY(0)'
                    } : {
                        opacity: 0,
                        visibility: 'hidden',
                        pointerEvents: 'none',
                        transform: 'translateY(10px)'
                    });
                });
            });
            document.addEventListener('click', () => {
                $all('.dropdown-menu._open').forEach(m => {
                    m.classList.remove('_open');
                    Object.assign(m.style, {
                        opacity: 0,
                        visibility: 'hidden',
                        pointerEvents: 'none',
                        transform: 'translateY(10px)'
                    });
                });
            });

            /* =========================
               Permit Type toggle (NEW/RENEWAL)
            ========================== */
            const requirement5 = document.getElementById('requirement-5');
            const requirement8 = document.getElementById('requirement-8');
            const requirement9 = document.getElementById('requirement-9');

            const newSection = document.getElementById('lumber-application-section');
            const renSection = document.getElementById('renewal-application-section');
            const permitTypeBtns = $all('.permit-type-btn');

            function activePermitType() {
                return (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');
            }

            function setPermitType(type) {
                const isNew = type === 'new';
                show(newSection, isNew);
                show(renSection, !isNew);
                show(requirement5, isNew);
                show(requirement8, isNew);
                show(requirement9, !isNew);
                $all('.new-number').forEach(el => show(el, isNew));
                $all('.renewal-number').forEach(el => show(el, !isNew));
                setTimeout(() => {
                    if (isNew) resizeSigCanvas();
                    else resizeSigCanvasRen();
                }, 0);
            }

            permitTypeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    permitTypeBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    setPermitType(btn.getAttribute('data-type'));
                });
            });
            document.querySelector('.permit-type-btn[data-type="new"]')?.classList.add('active');
            setPermitType('new');

            /* =========================
               Suppliers tables
            ========================== */
            const suppliersTbody = $('#suppliers-table tbody');
            $('#add-supplier-row')?.addEventListener('click', () => addSupplierRow());

            function addSupplierRow(name = '', vol = '') {
                if (!suppliersTbody) return;
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td><input type="text" class="supplier-name" placeholder="Supplier name" value="${name}"></td>
      <td><input type="text" class="supplier-volume" placeholder="Volume" value="${vol}"></td>
      <td><button type="button" class="remove-btn">Remove</button></td>`;
                suppliersTbody.appendChild(tr);
                tr.querySelector('.remove-btn').addEventListener('click', () => {
                    if (suppliersTbody.rows.length > 1) tr.remove();
                });
            }

            const suppliersTbodyRen = $('#suppliers-table-ren tbody');
            $('#add-supplier-row-ren')?.addEventListener('click', () => addSupplierRowRen());

            function addSupplierRowRen(name = '', vol = '') {
                if (!suppliersTbodyRen) return;
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td><input type="text" class="supplier-name-ren" placeholder="Supplier name" value="${name}"></td>
      <td><input type="text" class="supplier-volume-ren" placeholder="Volume" value="${vol}"></td>
      <td><button type="button" class="remove-btn-ren">Remove</button></td>`;
                suppliersTbodyRen.appendChild(tr);
                tr.querySelector('.remove-btn-ren').addEventListener('click', () => {
                    if (suppliersTbodyRen.rows.length > 1) tr.remove();
                });
            }

            function gatherSuppliers(isRenewal) {
                const rows = isRenewal ? $all('#suppliers-table-ren tbody tr') : $all('#suppliers-table tbody tr');
                return rows.map(r => {
                    const name = (r.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name')?.value || '').trim();
                    const volume = (r.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume')?.value || '').trim();
                    if (!name && !volume) return null;
                    return {
                        name,
                        volume
                    };
                }).filter(Boolean);
            }

            /* =========================
               File names on select
            ========================== */
            const fileIds = ['file-1', 'file-2', 'file-3', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8', 'file-9', 'file-10a', 'file-10b'];
            fileIds.forEach(id => {
                const input = document.getElementById(id);
                if (!input) return;
                input.addEventListener('change', () => {
                    const nameEl = input.parentElement?.querySelector('.file-name');
                    if (nameEl) nameEl.textContent = input.files?.[0]?.name ?? 'No file chosen';
                });
                document.querySelector(`label[for="${id}"]`)?.addEventListener('click', (e) => {
                    e.preventDefault();
                    input.click();
                });
            });

            /* =========================
               Signature NEW
            ========================== */
            const sigCanvas = document.getElementById('signature-pad');
            const clearSigBtn = document.getElementById('clear-signature');
            const saveSigBtn = document.getElementById('save-signature');
            const sigPreview = document.getElementById('signature-image');

            let strokes = [],
                currentStroke = [],
                drawing = false,
                lastPt = {
                    x: 0,
                    y: 0
                };

            function sizeCanvasToCSS(canvas) {
                if (!canvas) return;
                const rect = canvas.getBoundingClientRect();
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.style.width = rect.width + 'px';
                canvas.style.height = rect.height + 'px';
                canvas.width = Math.round(rect.width * ratio);
                canvas.height = Math.round(rect.height * ratio);
                const ctx = canvas.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, rect.width, rect.height);
            }

            function getCanvasPos(e, canvas) {
                const r = canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : null;
                const cx = t ? t.clientX : e.clientX;
                const cy = t ? t.clientY : e.clientY;
                return {
                    x: cx - r.left,
                    y: cy - r.top
                };
            }

            function repaintSignature(bg = true) {
                if (!sigCanvas) return;
                const ctx = sigCanvas.getContext('2d');
                const rect = sigCanvas.getBoundingClientRect();
                if (bg) {
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, rect.width, rect.height);
                }
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                for (const s of strokes) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                    ctx.stroke();
                }
            }

            function resizeSigCanvas() {
                sizeCanvasToCSS(sigCanvas);
                repaintSignature(true);
            }
            window.addEventListener('resize', () => {
                if (newSection?.style.display !== 'none') resizeSigCanvas();
            });
            resizeSigCanvas();

            function startSig(e) {
                if (!sigCanvas) return;
                drawing = true;
                currentStroke = [];
                lastPt = getCanvasPos(e, sigCanvas);
                currentStroke.push(lastPt);
                e.preventDefault?.();
            }

            function drawSig(e) {
                if (!drawing || !sigCanvas) return;
                const ctx = sigCanvas.getContext('2d');
                const p = getCanvasPos(e, sigCanvas);
                ctx.beginPath();
                ctx.moveTo(lastPt.x, lastPt.y);
                ctx.lineTo(p.x, p.y);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.stroke();
                lastPt = p;
                currentStroke.push(p);
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

                clearSigBtn?.addEventListener('click', () => {
                    strokes = [];
                    repaintSignature(true);
                    if (sigPreview) {
                        sigPreview.src = '';
                        sigPreview.classList.add('hidden');
                    }
                });
                saveSigBtn?.addEventListener('click', () => {
                    if (!strokes.some(s => s && s.length > 1)) {
                        alert('Please provide a signature first.');
                        return;
                    }
                    const img = getSignatureDataURLScaled(300, 110);
                    sigPreview.src = img.dataURL;
                    sigPreview.classList.remove('hidden');
                });
            }

            function getSignatureDataURLScaled(targetW = 300, targetH = 110) {
                if (!sigCanvas || !strokes.some(s => s && s.length > 1)) return {
                    dataURL: '',
                    w: 0,
                    h: 0
                };
                const rect = sigCanvas.getBoundingClientRect();
                const off = document.createElement('canvas');
                off.width = targetW;
                off.height = targetH;
                const ctx = off.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, targetW, targetH);
                const sx = targetW / rect.width,
                    sy = targetH / rect.height;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.lineWidth = 2 * Math.min(sx, sy);
                for (const s of strokes) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x * sx, s[0].y * sy);
                    for (let j = 1; j < s.length; j++) ctx.lineTo(s[j].x * sx, s[j].y * sy);
                    ctx.stroke();
                }
                return {
                    dataURL: off.toDataURL('image/png'),
                    w: targetW,
                    h: targetH
                };
            }

            /* =========================
               Signature RENEWAL
            ========================== */
            const sigCanvasRen = document.getElementById('signature-pad-ren');
            const clearSigBtnRen = document.getElementById('clear-signature-ren');
            const saveSigBtnRen = document.getElementById('save-signature-ren');
            const sigPreviewRen = document.getElementById('signature-image-ren');

            let strokesRen = [],
                currentStrokeRen = [],
                drawingRen = false,
                lastPtRen = {
                    x: 0,
                    y: 0
                };

            function repaintSignatureRen(bg = true) {
                if (!sigCanvasRen) return;
                const ctx = sigCanvasRen.getContext('2d');
                const rect = sigCanvasRen.getBoundingClientRect();
                if (bg) {
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, rect.width, rect.height);
                }
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                for (const s of strokesRen) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                    ctx.stroke();
                }
            }

            function resizeSigCanvasRen() {
                sizeCanvasToCSS(sigCanvasRen);
                repaintSignatureRen(true);
            }
            window.addEventListener('resize', () => {
                if (renSection?.style.display !== 'none') resizeSigCanvasRen();
            });

            function startSigRen(e) {
                if (!sigCanvasRen) return;
                drawingRen = true;
                currentStrokeRen = [];
                lastPtRen = getCanvasPos(e, sigCanvasRen);
                currentStrokeRen.push(lastPtRen);
                e.preventDefault?.();
            }

            function drawSigRen(e) {
                if (!drawingRen || !sigCanvasRen) return;
                const ctx = sigCanvasRen.getContext('2d');
                const p = getCanvasPos(e, sigCanvasRen);
                ctx.beginPath();
                ctx.moveTo(lastPtRen.x, lastPtRen.y);
                ctx.lineTo(p.x, p.y);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.stroke();
                lastPtRen = p;
                currentStrokeRen.push(p);
                e.preventDefault?.();
            }

            function endSigRen() {
                if (!drawingRen) return;
                drawingRen = false;
                if (currentStrokeRen.length > 1) strokesRen.push(currentStrokeRen);
                currentStrokeRen = [];
            }

            if (sigCanvasRen) {
                sigCanvasRen.addEventListener('mousedown', startSigRen);
                sigCanvasRen.addEventListener('mousemove', drawSigRen);
                window.addEventListener('mouseup', endSigRen);
                sigCanvasRen.addEventListener('touchstart', startSigRen, {
                    passive: false
                });
                sigCanvasRen.addEventListener('touchmove', drawSigRen, {
                    passive: false
                });
                window.addEventListener('touchend', endSigRen);

                clearSigBtnRen?.addEventListener('click', () => {
                    strokesRen = [];
                    repaintSignatureRen(true);
                    if (sigPreviewRen) {
                        sigPreviewRen.src = '';
                        sigPreviewRen.classList.add('hidden');
                    }
                });
                saveSigBtnRen?.addEventListener('click', () => {
                    if (!strokesRen.some(s => s && s.length > 1)) {
                        alert('Please provide a signature first.');
                        return;
                    }
                    const img = getSignatureDataURLScaledRen(300, 110);
                    sigPreviewRen.src = img.dataURL;
                    sigPreviewRen.classList.remove('hidden');
                });
            }

            function getSignatureDataURLScaledRen(targetW = 300, targetH = 110) {
                if (!sigCanvasRen || !strokesRen.some(s => s && s.length > 1)) return {
                    dataURL: '',
                    w: 0,
                    h: 0
                };
                const rect = sigCanvasRen.getBoundingClientRect();
                const off = document.createElement('canvas');
                off.width = targetW;
                off.height = targetH;
                const ctx = off.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, targetW, targetH);
                const sx = targetW / rect.width,
                    sy = targetH / rect.height;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
                ctx.lineWidth = 2 * Math.min(sx, sy);
                for (const s of strokesRen) {
                    if (!s || s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x * sx, s[0].y * sy);
                    for (let j = 1; j < s.length; j++) ctx.lineTo(s[j].x * sx, s[j].y * sy);
                    ctx.stroke();
                }
                return {
                    dataURL: off.toDataURL('image/png'),
                    w: targetW,
                    h: targetH
                };
            }

            /* =========================
               Autofill NEW <-> RENEWAL
            ========================== */
            function autofillRenewalFromNew() {
                const map = [
                    ['first-name', 'first-name-ren'],
                    ['middle-name', 'middle-name-ren'],
                    ['last-name', 'last-name-ren'],
                    ['business-name', 'business-name-ren'], // <-- added
                    ['business-address', 'business-address-ren'],
                    ['operation-place', 'operation-place-ren'],
                    ['annual-volume', 'annual-volume-ren'],
                    ['annual-worth', 'annual-worth-ren'],
                    ['employees-count', 'employees-count-ren'],
                    ['dependents-count', 'dependents-count-ren'],
                    ['intended-market', 'intended-market-ren'],
                    ['experience', 'experience-ren'],
                    ['declaration-name', 'declaration-name-ren'],
                ];
                map.forEach(([a, b]) => {
                    const src = document.getElementById(a),
                        dst = document.getElementById(b);
                    if (src && dst) dst.value = src.value;
                });
                (document.getElementById('govt-employee-ren-yes') || {}).checked = !!document.getElementById('govt-employee-yes')?.checked;
                (document.getElementById('govt-employee-ren-no') || {}).checked = !!document.getElementById('govt-employee-no')?.checked;

                const rows = $all('#suppliers-table tbody tr');
                $all('#suppliers-table-ren tbody tr').forEach((r, i) => {
                    if (i > 0) r.remove();
                });
                const firstRen = $('#suppliers-table-ren tbody tr');
                rows.forEach((r, idx) => {
                    const name = r.querySelector('.supplier-name')?.value || '';
                    const vol = r.querySelector('.supplier-volume')?.value || '';
                    if (idx === 0 && firstRen) {
                        firstRen.querySelector('.supplier-name-ren').value = name;
                        firstRen.querySelector('.supplier-volume-ren').value = vol;
                    } else {
                        addSupplierRowRen(name, vol);
                    }
                });
            }

            function autofillNewFromRenewal() {
                const map = [
                    ['first-name-ren', 'first-name'],
                    ['middle-name-ren', 'middle-name'],
                    ['last-name-ren', 'last-name'],
                    ['business-name-ren', 'business-name'], // <-- added
                    ['business-address-ren', 'business-address'],
                    ['operation-place-ren', 'operation-place'],
                    ['annual-volume-ren', 'annual-volume'],
                    ['annual-worth-ren', 'annual-worth'],
                    ['employees-count-ren', 'employees-count'],
                    ['dependents-count-ren', 'dependents-count'],
                    ['intended-market-ren', 'intended-market'],
                    ['experience-ren', 'experience'],
                    ['declaration-name-ren', 'declaration-name'],
                ];
                map.forEach(([a, b]) => {
                    const src = document.getElementById(a),
                        dst = document.getElementById(b);
                    if (src && dst) dst.value = src.value;
                });
                (document.getElementById('govt-employee-yes') || {}).checked = !!document.getElementById('govt-employee-ren-yes')?.checked;
                (document.getElementById('govt-employee-no') || {}).checked = !!document.getElementById('govt-employee-ren-no')?.checked;

                const rows = $all('#suppliers-table-ren tbody tr');
                $all('#suppliers-table tbody tr').forEach((r, i) => {
                    if (i > 0) r.remove();
                });
                const first = $('#suppliers-table tbody tr');
                rows.forEach((r, idx) => {
                    const name = r.querySelector('.supplier-name-ren')?.value || '';
                    const vol = r.querySelector('.supplier-volume-ren')?.value || '';
                    if (idx === 0 && first) {
                        first.querySelector('.supplier-name').value = name;
                        first.querySelector('.supplier-volume').value = vol;
                    } else {
                        addSupplierRow(name, vol);
                    }
                });
            }

            /* =========================
               Modals (guarded)
            ========================== */
            const confirmModal = document.getElementById('confirmModal');
            const btnCancelConfirm = document.getElementById('btnCancelConfirm') || document.getElementById('cancelSubmitBtn');
            const btnOkConfirm = document.getElementById('btnOkConfirm') || document.getElementById('confirmSubmitBtn');

            // Add these with the other modal refs:
            const forPaymentModal = document.getElementById('forPaymentModal');
            const forPaymentOk = document.getElementById('forPaymentOk');

            const pendingNewModal = document.getElementById('pendingNewModal');
            const pendingNewOk = document.getElementById('pendingNewOk');

            const offerRenewalModal = document.getElementById('offerRenewalModal');
            const offerRenewalOk = document.getElementById('offerRenewalOk');
            const offerRenewalSwitch = document.getElementById('offerRenewalSwitch');

            const needApprovedNewModal = document.getElementById('needApprovedNewModal');
            const needApprovedNewOk = document.getElementById('needApprovedNewOk');
            const needApprovedNewSwitch = document.getElementById('needApprovedNewSwitch');

            pendingNewOk?.addEventListener('click', () => {
                display(pendingNewModal, 'none');
            });
            offerRenewalOk?.addEventListener('click', () => {
                display(offerRenewalModal, 'none');
            });
            needApprovedNewOk?.addEventListener('click', () => {
                display(needApprovedNewModal, 'none');
            });

            offerRenewalSwitch?.addEventListener('click', () => {
                display(offerRenewalModal, 'none');
                permitTypeBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === 'renewal'));
                setPermitType('renewal');
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            needApprovedNewSwitch?.addEventListener('click', () => {
                display(needApprovedNewModal, 'none');
                permitTypeBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === 'new'));
                setPermitType('new');
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            btnCancelConfirm?.addEventListener('click', () => {
                display(confirmModal, 'none');
            });

            forPaymentOk?.addEventListener('click', () => {
                display(forPaymentModal, 'none');
            });

            /* =========================
   Existing Client modal (choose reuse vs new)
========================== */
            let chosenClientId = null; // if user picks "Use existing"
            let forceNewClient = false; // if user picks "Create new"





            const existingClientModal = document.getElementById('existingClientModal');
            const existingClientHint = document.getElementById('existingClientHint');
            const existingClientUse = document.getElementById('existingClientUse');
            const existingClientNew = document.getElementById('existingClientNew');
            const existingClientCancel = document.getElementById('existingClientCancel');

            // RENEWAL-only modal refs
            const existingClientModalRen = document.getElementById('existingClientModalRen');
            const existingClientHintRen = document.getElementById('existingClientHintRen');
            const existingClientRenConfirm = document.getElementById('existingClientRenConfirm');
            const existingClientRenCancel = document.getElementById('existingClientRenCancel');

            if (existingClientModal) {
                existingClientModal.dataset.clientId = '';
                if (existingClientHint) existingClientHint.textContent = '';
            }
            if (existingClientModalRen) {
                existingClientModalRen.dataset.clientId = '';
                if (existingClientHintRen) existingClientHintRen.textContent = '';
            }


            // DB name parts returned by precheck (when it finds/suggests a client)
            let existingClientFirst = null,
                existingClientMiddle = null,
                existingClientLast = null;


            function openExistingClientModal(clientId, displayName) {
                chosenClientId = null;
                forceNewClient = false;
                if (existingClientModal) {
                    existingClientModal.style.display = 'flex';
                    existingClientModal.dataset.clientId = String(clientId || '');
                }
                if (existingClientHint) existingClientHint.textContent = displayName ? `Matched: ${displayName}` : '';
            }

            function closeExistingClientModal() {
                if (existingClientModal) existingClientModal.style.display = 'none';
            }

            function openExistingClientModalRen(clientId, displayName) {
                if (!existingClientModalRen) return;
                existingClientModalRen.style.display = 'flex';
                existingClientModalRen.dataset.clientId = String(clientId || '');
                if (existingClientHintRen) {
                    existingClientHintRen.textContent = displayName ? `Matched: ${esc(displayName)}` : '';
                }
            }

            function closeExistingClientModalRen() {
                if (!existingClientModalRen) return;
                existingClientModalRen.style.display = 'none';
                existingClientModalRen.dataset.clientId = '';
                if (existingClientHintRen) existingClientHintRen.textContent = '';
            }


            existingClientUse?.addEventListener('click', async () => {
                const overlay = document.getElementById('loadingIndicator');
                const btn = existingClientUse;
                const prevLabel = btn?.textContent;

                // Start loading state
                btn && (btn.disabled = true, btn.setAttribute('aria-busy', 'true'), btn.textContent = 'Working…');
                display(overlay, 'flex');

                chosenClientId = existingClientModal?.dataset?.clientId || null;
                forceNewClient = false;

                // Mirror DB names (FIRST, MIDDLE, LAST) into the visible fields + declaration
                const dbFirst = existingClientFirst || '';
                const dbMiddle = existingClientMiddle || ''; // may be empty
                const dbLast = existingClientLast || '';
                const declVal = (dbFirst + ' ' + dbLast).trim(); // declaration stays FIRST + LAST

                // NEW section
                const fN = document.getElementById('first-name');
                const mN = document.getElementById('middle-name');
                const lN = document.getElementById('last-name');
                const dN = document.getElementById('declaration-name');
                if (fN) fN.value = dbFirst;
                if (mN) mN.value = dbMiddle; // set middle or empty
                if (lN) lN.value = dbLast;
                if (dN) dN.value = declVal;

                // RENEWAL section
                const fR = document.getElementById('first-name-ren');
                const mR = document.getElementById('middle-name-ren');
                const lR = document.getElementById('last-name-ren');
                const dR = document.getElementById('declaration-name-ren');
                if (fR) fR.value = dbFirst;
                if (mR) mR.value = dbMiddle; // set middle or empty
                if (lR) lR.value = dbLast;
                if (dR) dR.value = declVal;

                try {
                    // Re-run PRECHECK with the chosen client id so server can return the same block -> modal
                    const type = activePermitType();
                    const fd = new FormData();
                    fd.append('first_name', type === 'renewal' ? (document.getElementById('first-name-ren')?.value || '') : (document.getElementById('first-name')?.value || ''));
                    fd.append('middle_name', type === 'renewal' ? (document.getElementById('middle-name-ren')?.value || '') : (document.getElementById('middle-name')?.value || ''));
                    fd.append('last_name', type === 'renewal' ? (document.getElementById('last-name-ren')?.value || '') : (document.getElementById('last-name')?.value || ''));
                    fd.append('desired_permit_type', type);
                    fd.append('use_client_id', chosenClientId || '');

                    const res = await fetch(PRECHECK_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                    const json = await res.json();

                    // Same UI handling as initial precheck
                    if (json.block === 'for_payment') {
                        display(forPaymentModal, 'flex');
                        closeExistingClientModal();
                        return;
                    }
                    if (json.block === 'pending_new') {
                        display(pendingNewModal, 'flex');
                        closeExistingClientModal();
                        return;
                    }
                    if (json.block === 'pending_renewal') {
                        toast('You already have a pending lumber renewal. Please wait for the update first.');
                        closeExistingClientModal();
                        return;
                    }
                    if (json.block === 'need_released_new' ||
                        json.block === 'need_approved_new') {
                        display(needApprovedNewModal, 'flex');
                        closeExistingClientModal();
                        return;
                    }
                    if (json.offer === 'renewal' && type === 'new') {
                        display(offerRenewalModal, 'flex');
                        closeExistingClientModal();
                        return;
                    }
                } catch (err) {
                    // If precheck fails, fall through to confirm so the user isn’t stuck
                    console.error(err);
                } finally {
                    // End loading state
                    btn && (btn.disabled = false, btn.removeAttribute('aria-busy'), btn.textContent = prevLabel || 'Use existing');
                    display(overlay, 'none');
                }

                closeExistingClientModal();
                document.getElementById('confirmModal').style.display = 'flex';
            });



            existingClientNew?.addEventListener('click', () => {
                chosenClientId = null;
                forceNewClient = true;
                closeExistingClientModal();
                document.getElementById('confirmModal').style.display = 'flex';
            });
            existingClientCancel?.addEventListener('click', () => {
                chosenClientId = null;
                forceNewClient = false;
                closeExistingClientModal();
            });
            // Renewal: Cancel just closes
            existingClientRenCancel?.addEventListener('click', () => {
                closeExistingClientModalRen();
            });

            // Renewal: Confirm = use existing client, then re-precheck
            existingClientRenConfirm?.addEventListener('click', async () => {
                const cid = existingClientModalRen?.dataset?.clientId || null;
                if (!cid) {
                    closeExistingClientModalRen();
                    return;
                }

                const overlay = document.getElementById('loadingIndicator');
                const btn = existingClientRenConfirm;
                const prevLabel = btn?.textContent;

                // Start loading state
                btn && (btn.disabled = true, btn.setAttribute('aria-busy', 'true'), btn.textContent = 'Working…');
                display(overlay, 'flex');

                chosenClientId = cid;
                forceNewClient = false;

                // Mirror DB names (FIRST, MIDDLE, LAST) into both NEW & RENEWAL + declaration
                const dbFirst = existingClientFirst || '';
                const dbMiddle = existingClientMiddle || ''; // may be empty
                const dbLast = existingClientLast || '';
                const declVal = (dbFirst + ' ' + dbLast).trim(); // declaration stays FIRST + LAST

                // NEW section
                const fN = document.getElementById('first-name');
                const mN = document.getElementById('middle-name');
                const lN = document.getElementById('last-name');
                const dN = document.getElementById('declaration-name');
                if (fN) fN.value = dbFirst;
                if (mN) mN.value = dbMiddle; // set middle or empty
                if (lN) lN.value = dbLast;
                if (dN) dN.value = declVal;

                // RENEWAL section
                const fR = document.getElementById('first-name-ren');
                const mR = document.getElementById('middle-name-ren');
                const lR = document.getElementById('last-name-ren');
                const dR = document.getElementById('declaration-name-ren');
                if (fR) fR.value = dbFirst;
                if (mR) mR.value = dbMiddle; // set middle or empty
                if (lR) lR.value = dbLast;
                if (dR) dR.value = declVal;

                try {
                    // re-run PRECHECK specifically as renewal with chosen client id
                    const fd = new FormData();
                    fd.append('desired_permit_type', 'renewal');
                    fd.append('use_client_id', cid);

                    const res = await fetch(PRECHECK_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                    const json = await res.json();

                    if (json.block === 'for_payment') {
                        display(forPaymentModal, 'flex');
                        closeExistingClientModalRen();
                        return;
                    }
                    if (json.block === 'pending_renewal') {
                        toast('You already have a pending lumber renewal. Please wait for the update first.');
                        closeExistingClientModalRen();
                        return;
                    }
                    if (json.block === 'need_released_new' ||
                        json.block === 'need_approved_new') {
                        display(needApprovedNewModal, 'flex');
                        closeExistingClientModalRen();
                        return;
                    }
                } catch (e) {
                    console.error(e);
                    // fall through to confirm so user isn’t stuck
                } finally {
                    // End loading state
                    btn && (btn.disabled = false, btn.removeAttribute('aria-busy'), btn.textContent = prevLabel || 'Confirm');
                    display(overlay, 'none');
                }

                closeExistingClientModalRen();
                document.getElementById('confirmModal').style.display = 'flex';
            });



            /* =========================
               PRECHECK → CONFIRM → SUBMIT
            ========================== */
            document.getElementById('submitApplication')?.addEventListener('click', async () => {
                const overlay = document.getElementById('loadingIndicator');
                display(overlay, 'flex');
                try {
                    const type = activePermitType();
                    const first = type === 'renewal' ? v('first-name-ren') : v('first-name');
                    const middle = type === 'renewal' ? v('middle-name-ren') : v('middle-name');
                    const last = type === 'renewal' ? v('last-name-ren') : v('last-name');

                    const fd = new FormData();
                    fd.append('first_name', first);
                    fd.append('middle_name', middle);
                    fd.append('last_name', last);
                    fd.append('desired_permit_type', type);

                    const res = await fetch(PRECHECK_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });

                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message || 'Precheck failed');

                    // Stash DB name parts (used if user picks “Use existing” and for doc/filename)
                    existingClientFirst = json.existing_client_first || null;
                    existingClientMiddle = json.existing_client_middle || null;
                    existingClientLast = json.existing_client_last || null;

                    // hard blocks (same)
                    // hard blocks (updated)
                    if (json.block === 'for_payment') {
                        // NEW: global payment blocker
                        display(forPaymentModal, 'flex');
                        return;
                    }

                    if (json.block === 'pending_new') {
                        display(pendingNewModal, 'flex');
                        return;
                    }
                    if (json.block === 'pending_renewal') {
                        toast('You already have a pending lumber renewal. Please wait for the update first.');
                        return;
                    }
                    if (json.block === 'need_released_new' || json.block === 'need_approved_new') {
                        display(needApprovedNewModal, 'flex');
                        return;
                    }

                    if (json.offer === 'renewal' && type === 'new') {
                        display(offerRenewalModal, 'flex');
                        return;
                    }

                    // Offer "use existing client" if backend suggested one (supports both old and new response shapes)
                    const suggestedId =
                        json.existing_client_id || json?.suggest_existing_client?.client_id;

                    if (suggestedId && !forceNewClient) {
                        const displayName = (
                            json.existing_client_name ||
                            json?.suggest_existing_client?.full_name || [first, middle, last].filter(Boolean).join(' ')
                        ).trim();

                        if (type === 'renewal') {
                            openExistingClientModalRen(String(suggestedId), displayName); // Confirm/Cancel only
                        } else {
                            openExistingClientModal(String(suggestedId), displayName); // New: Use / Create new / Cancel
                        }
                        return; // wait for user choice
                    }




                    display(confirmModal, 'flex');
                } catch (e) {
                    console.error(e);
                    toast(e?.message || 'Precheck failed. Please try again.');
                    return; // <- don't open confirm if precheck failed
                } finally {
                    display(overlay, 'none');
                }

            });


            btnOkConfirm?.addEventListener('click', async () => {
                display(confirmModal, 'none');
                const overlay = document.getElementById('loadingIndicator');
                display(overlay, 'flex');
                try {
                    await doSubmit();
                    toast("Application submitted. We'll notify you once reviewed.");
                    resetForm();
                } catch (e) {
                    console.error(e);
                    const msg = String(e?.message || '').toLowerCase();
                    if (msg.includes('for payment')) {
                        // Show the same modal the precheck path uses
                        display(forPaymentModal, 'flex');
                    } else {
                        toast(e?.message || 'Submission failed. Please try again.');
                    }
                } finally {
                    display(overlay, 'none');
                }
            });


            /* =========================
               Final submit (generate doc + upload files)
            ========================== */
            async function doSubmit() {
                const type = activePermitType();
                const isRenewal = type === 'renewal';

                // Scale signature and gather suppliers
                const sigScaled = isRenewal ?
                    getSignatureDataURLScaledRen(300, 110) :
                    getSignatureDataURLScaled(300, 110);
                const hasSignature = !!(sigScaled && sigScaled.dataURL);
                const suppliersArr = gatherSuppliers(isRenewal);

                // -------- collect UI values --------
                let firstName, middleName, lastName,
                    applicantAge, businessName, businessAddress, govEmp,
                    operationPlace, annualVolume, annualWorth,
                    employeesCount, dependentsCount, intendedMarket, experience,
                    typedDeclarationName, prevCert, issuedDate, expiryDate, crLicense, sawmillPermit, buyingOther;

                if (isRenewal) {
                    firstName = v('first-name-ren');
                    middleName = v('middle-name-ren');
                    lastName = v('last-name-ren');

                    applicantAge = v('applicant-age-ren');
                    businessName = v('business-name-ren');
                    businessAddress = v('business-address-ren');

                    govEmp = (document.getElementById('govt-employee-ren-yes')?.checked ? 'yes' : 'no');
                    operationPlace = v('operation-place-ren');
                    annualVolume = v('annual-volume-ren');
                    annualWorth = v('annual-worth-ren');

                    employeesCount = v('employees-count-ren');
                    dependentsCount = v('dependents-count-ren');

                    intendedMarket = v('intended-market-ren');
                    experience = v('experience-ren');

                    typedDeclarationName = v('declaration-name-ren');

                    prevCert = v('prev-certificate-ren');
                    issuedDate = v('issued-date-ren');
                    expiryDate = v('expiry-date-ren');
                    crLicense = v('cr-license-ren');
                    sawmillPermit = v('sawmill-permit-ren');
                    buyingOther = (document.getElementById('other-sources-ren-yes')?.checked ? 'yes' : 'no');
                } else {
                    firstName = v('first-name');
                    middleName = v('middle-name');
                    lastName = v('last-name');

                    applicantAge = v('applicant-age');
                    businessName = v('business-name'); // added mapping in save_lumber.php
                    businessAddress = v('business-address');

                    govEmp = (document.getElementById('govt-employee-yes')?.checked ? 'yes' : 'no');
                    operationPlace = v('operation-place');
                    annualVolume = v('annual-volume');
                    annualWorth = v('annual-worth');

                    employeesCount = v('employees-count');
                    dependentsCount = v('dependents-count');

                    intendedMarket = v('intended-market');
                    experience = v('experience');

                    typedDeclarationName = v('declaration-name');
                }

                // -------- prefer DB names when user chose an existing client --------
                // (existingClientFirst/Last are set during PRECHECK; fall back to typed if missing)
                const effFirst = chosenClientId ? (existingClientFirst || firstName) : firstName;
                const effLast = chosenClientId ? (existingClientLast || lastName) : lastName;

                // Keep middle name as typed so the user can include or leave blank
                const fullName = [effFirst, middleName, effLast].filter(Boolean).join(' ').trim();

                // Declaration must be exactly FIRST + LAST from DB when using existing client
                const declarationName = chosenClientId ? [effFirst, effLast].join(' ').trim() :
                    typedDeclarationName;

                // -------- build Word document (MHTML) --------
                const svgLogo = `
    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="45" fill="#2b6625"/>
      <path d="M35 35L65 65" stroke="white" stroke-width="5"/>
      <path d="M65 35L35 65" stroke="white" stroke-width="5"/>
      <circle cx="50" cy="50" r="20" stroke="white" stroke-width="3" fill="none"/>
    </svg>`;
                const logoDataUrl = await svgToPngDataUrl(svgLogo);

                const fields = {
                    fullName,
                    applicantAge,
                    businessAddress,
                    govEmp,
                    operationPlace,
                    annualVolume,
                    annualWorth,
                    employeesCount,
                    dependentsCount,
                    intendedMarket,
                    experience,
                    declarationName,
                    prevCert,
                    issuedDate,
                    expiryDate,
                    crLicense,
                    sawmillPermit,
                    buyingOther
                };

                const logoLoc = 'logo.png';
                const sigLoc = 'signature.png';

                const docHTML = isRenewal ?
                    buildRenewalDocHTML(logoLoc, hasSignature ? sigLoc : '', fields, suppliersArr) :
                    buildNewDocHTML(logoLoc, hasSignature ? sigLoc : '', fields, suppliersArr);

                const parts = [];
                if (logoDataUrl) {
                    parts.push({
                        location: logoLoc,
                        contentType: 'image/png',
                        base64: (logoDataUrl.split(',')[1] || '')
                    });
                }
                if (hasSignature) {
                    parts.push({
                        location: sigLoc,
                        contentType: 'image/png',
                        base64: (sigScaled.dataURL.split(',')[1] || '')
                    });
                }

                const mhtml = makeMHTML(docHTML, parts);
                const docBlob = new Blob([mhtml], {
                    type: 'application/msword'
                });

                // Filename: when using existing client, force "First_Last"
                const nameForFile = chosenClientId ? ([effFirst, effLast].join(' ').trim()) : fullName;
                const docFileName = `${isRenewal ? 'Lumber_Renewal' : 'Lumber_New'}_${(nameForFile || 'Applicant').replace(/\s+/g, '_')}.doc`;
                const docFile = new File([docBlob], docFileName, {
                    type: 'application/msword'
                });

                // -------- assemble FormData --------
                const fd = new FormData();
                fd.append('permit_type', isRenewal ? 'renewal' : 'new');

                // Base identity fields (typed; server may override when use_client_id is set)
                fd.append('first_name', firstName);
                fd.append('middle_name', middleName);
                fd.append('last_name', lastName);

                // App-specific fields
                fd.append('applicant_age', applicantAge);
                fd.append('business_name', businessName); // mapped to company_name on server
                fd.append('business_address', businessAddress);
                fd.append('is_government_employee', govEmp);
                fd.append('proposed_place_of_operation', operationPlace);
                fd.append('expected_annual_volume', annualVolume);
                fd.append('estimated_annual_worth', annualWorth);
                fd.append('total_number_of_employees', employeesCount);
                fd.append('total_number_of_dependents', dependentsCount);
                fd.append('intended_market', intendedMarket);
                fd.append('my_experience_as_alumber_dealer', experience);

                // Enforced declaration (DB first + last if using existing)
                fd.append('declaration_name', declarationName);

                // Suppliers
                fd.append('suppliers_json', JSON.stringify(suppliersArr));

                // Renewal-only extras
                if (isRenewal) {
                    fd.append('prev_certificate_no', prevCert);
                    fd.append('issued_date', issuedDate);
                    fd.append('expiry_date', expiryDate);
                    fd.append('cr_license_no', crLicense);
                    fd.append('sawmill_permit_no', sawmillPermit);
                    fd.append('buying_from_other_sources', buyingOther);
                }

                // Generated application document + signature
                fd.append('application_doc', docFile);
                if (hasSignature) {
                    const sigBlob = dataURLToBlob(sigScaled.dataURL);
                    if (sigBlob) {
                        fd.append('signature_file', new File([sigBlob], 'signature.png', {
                            type: 'image/png'
                        }));
                    }
                }

                // Attach uploads from the form
                const pick = (id) => document.getElementById(id)?.files?.[0] || null;
                const filesCommon = {
                    lumber_csw_document: pick('file-1'),
                    geo_photos: pick('file-2'),
                    application_form: null, // generated above
                    lumber_supply_contract: pick('file-4'),
                    lumber_mayors_permit: pick('file-6'),
                    lumber_registration_certificate: pick('file-7'),
                    lumber_or_copy: pick('file-10a'),
                    lumber_op_copy: pick('file-10b'),
                };
                const filesNew = {
                    lumber_business_plan: pick('file-5'),
                    lumber_tax_return: pick('file-8')
                };
                const filesRenewal = {
                    lumber_monthly_reports: pick('file-9')
                };

                Object.entries(filesCommon).forEach(([k, f]) => {
                    if (f) fd.append(k, f);
                });
                if (isRenewal) {
                    Object.entries(filesRenewal).forEach(([k, f]) => {
                        if (f) fd.append(k, f);
                    });
                } else {
                    Object.entries(filesNew).forEach(([k, f]) => {
                        if (f) fd.append(k, f);
                    });
                }

                // Pass the client-choice flags to the server (server enforces DB names when use_client_id is present)
                if (chosenClientId) {
                    fd.append('use_client_id', String(chosenClientId));
                }
                if (forceNewClient) {
                    fd.append('force_new_client', '1');
                }

                // -------- submit --------
                const res = await fetch(SAVE_URL, {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                });

                let json;
                try {
                    json = await res.json();
                } catch {
                    const text = await res.text();
                    throw new Error(`HTTP ${res.status} – ${text.slice(0, 200)}`);
                }
                if (!res.ok || !json.ok) {
                    throw new Error(json?.error || `HTTP ${res.status}`);
                }
            }


            /* =========================
               Reset form after success
            ========================== */
            function resetForm() {
                $all('input[type="text"], input[type="number"], input[type="date"]').forEach(i => i.value = '');
                (document.getElementById('govt-employee-no') || {}).checked = true;
                (document.getElementById('govt-employee-ren-no') || {}).checked = true;
                (document.getElementById('other-sources-ren-no') || {}).checked = true;

                $all('input[type="file"]').forEach(fi => {
                    fi.value = '';
                    const nameSpan = fi.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = 'No file chosen';
                });

                $all('#suppliers-table tbody tr').forEach((r, i) => {
                    if (i > 0) r.remove();
                });
                const first = $('#suppliers-table tbody tr');
                if (first) {
                    first.querySelector('.supplier-name').value = '';
                    first.querySelector('.supplier-volume').value = '';
                }

                $all('#suppliers-table-ren tbody tr').forEach((r, i) => {
                    if (i > 0) r.remove();
                });
                const firstRen = $('#suppliers-table-ren tbody tr');
                if (firstRen) {
                    firstRen.querySelector('.supplier-name-ren').value = '';
                    firstRen.querySelector('.supplier-volume-ren').value = '';
                }

                strokes = [];
                repaintSignature(true);
                if (sigPreview) {
                    sigPreview.src = '';
                    sigPreview.classList.add('hidden');
                }
                strokesRen = [];
                repaintSignatureRen(true);
                if (sigPreviewRen) {
                    sigPreviewRen.src = '';
                    sigPreviewRen.classList.add('hidden');
                }

                permitTypeBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === 'new'));
                setPermitType('new');
                chosenClientId = null;
                forceNewClient = false;

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        })();
    </script>
    <script>
        /* ===========================================================
   Lumber Dealer — Client-side Validation (separate script)
   - Shows red error TEXT under each input (no red borders)
   - Validates NEW & RENEWAL sections
   - Intercepts Submit button (capture phase) to block submit when invalid
   - Does NOT validate: file inputs, signature canvases, middle names
   - Safe to paste after your existing scripts
=========================================================== */
        (function() {
            'use strict';

            /* ------------------ tiny helpers (scoped) ------------------ */
            const $ = (s, r = document) => r.querySelector(s);
            const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

            function isBlank(str) {
                return !str || !str.trim();
            }

            function minChars(str, n) {
                return (str || '').trim().length >= n;
            }

            function hasLetters(str) {
                return /[A-Za-z]/.test(str || '');
            }

            function hasDigits(str) {
                return /\d/.test(str || '');
            }

            function normalizeNum(str) {
                if (!str) return '';
                return str.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1');
            }

            // create or get an error holder div right after a field
            function ensureErrorEl(el) {
                if (!el) return null;
                let next = el.nextElementSibling;
                if (!(next && next.classList && next.classList.contains('field-error'))) {
                    next = document.createElement('div');
                    next.className = 'field-error';
                    next.style.cssText = 'color:#d32f2f;margin-top:6px;font-size:.9rem;display:none;';
                    el.insertAdjacentElement('afterend', next);
                }
                return next;
            }

            function setError(el, msg) {
                if (!el) return;
                const holder = ensureErrorEl(el);
                if (!holder) return;
                if (msg) {
                    holder.textContent = msg;
                    holder.style.display = 'block';
                } else {
                    holder.textContent = '';
                    holder.style.display = 'none';
                }
                // keep borders clean
                el.classList.remove('error', 'is-invalid');
                el.style.borderColor = '';
                el.style.outlineColor = '';
                el.style.boxShadow = '';
            }

            /* ------------------ per-field rules ------------------ */
            // Names: allow letters incl. accents + space/hyphen/apostrophe
            const NAME_RX = /^[A-Za-zÀ-ž' -]{2,50}$/;
            const MID_RX = /^[A-Za-zÀ-ž' -]{1,50}$/;
            const GENERIC_ID_RX = /^[A-Za-z0-9\-\/\s]{3,}$/; // for IDs/permits

            const rules = {
                // NEW
                'first-name': v => {
                    if (isBlank(v)) return 'First name is required.';
                    if (!NAME_RX.test(v)) return 'First name must be 2–50 letters (no numbers).';
                },
                'middle-name': v => {
                    if (v && !MID_RX.test(v)) return 'Middle name should contain letters only.';
                }, // optional
                'last-name': v => {
                    if (isBlank(v)) return 'Last name is required.';
                    if (!NAME_RX.test(v)) return 'Last name must be 2–50 letters (no numbers).';
                },
                'applicant-age': v => {
                    if (isBlank(v)) return 'Age is required.';
                    const n = parseInt(v, 10);
                    if (Number.isNaN(n)) return 'Age must be a number.';
                    if (n < 18 || n > 120) return 'Age must be between 18 and 120.';
                },
                'business-name': v => {
                    if (isBlank(v)) return 'Business name is required.';
                    if (!minChars(v, 3) || !/[A-Za-z0-9]/.test(v)) return 'Enter a valid business name (min 3 chars).';
                },
                'business-address': v => {
                    if (isBlank(v)) return 'Business address is required.';
                    if (!minChars(v, 10)) return 'Business address must be at least 10 characters.';
                    if (!hasLetters(v) || !hasDigits(v)) return 'Include both street/house number and area (letters and numbers).';
                },
                'operation-place': v => {
                    if (isBlank(v)) return 'Proposed place of operation is required.';
                    if (!minChars(v, 5)) return 'Enter at least 5 characters.';
                },
                'annual-volume': v => {
                    if (isBlank(v)) return 'Expected annual volume is required.';
                    if (!hasDigits(v)) return 'Include a numeric volume (e.g., 1,000 bd ft).';
                },
                'annual-worth': v => {
                    if (isBlank(v)) return 'Worth is required.';
                    const num = normalizeNum(v);
                    if (!num) return 'Enter a numeric worth (e.g., ₱500,000).';
                    if (parseFloat(num) <= 0) return 'Worth must be greater than 0.';
                },
                'employees-count': v => {
                    if (isBlank(v)) return 'Total employees is required.';
                    const n = parseInt(v, 10);
                    if (!Number.isInteger(n) || n < 0 || n > 5000) return 'Enter a whole number 0–5000.';
                },
                'dependents-count': v => {
                    if (isBlank(v)) return 'Total dependents is required.';
                    const n = parseInt(v, 10);
                    if (!Number.isInteger(n) || n < 0 || n > 5000) return 'Enter a whole number 0–5000.';
                },
                'intended-market': v => {
                    if (isBlank(v)) return 'Intended market is required.';
                    if (!minChars(v, 10)) return 'Describe the intended market (10+ chars).';
                },
                'experience': v => {
                    if (isBlank(v)) return 'Experience is required.';
                    if (!minChars(v, 10)) return 'Describe your experience in at least 10 characters.';
                },
                'declaration-name': v => {
                    if (isBlank(v)) return 'Declaration name is required.';
                    if (!/^[A-Za-zÀ-ž' .-]{4,100}$/.test(v)) return 'Use letters, spaces, and punctuation (.-\').';
                    const f = $('#first-name')?.value?.trim().toLowerCase();
                    const l = $('#last-name')?.value?.trim().toLowerCase();
                    if (f && l) {
                        const lower = v.trim().toLowerCase();
                        if (!(lower.includes(f) && lower.includes(l))) return 'Include your first and last name here.';
                    }
                },

                // RENEWAL (mirror NEW + extras)
                'first-name-ren': v => rules['first-name'](v),
                'middle-name-ren': v => rules['middle-name'](v),
                'last-name-ren': v => rules['last-name'](v),
                'applicant-age-ren': v => rules['applicant-age'](v),
                'business-name-ren': v => rules['business-name'](v),
                'business-address-ren': v => rules['business-address'](v),
                'operation-place-ren': v => rules['operation-place'](v),
                'annual-volume-ren': v => rules['annual-volume'](v),
                'annual-worth-ren': v => rules['annual-worth'](v),
                'employees-count-ren': v => rules['employees-count'](v),
                'dependents-count-ren': v => rules['dependents-count'](v),
                'intended-market-ren': v => rules['intended-market'](v),
                'experience-ren': v => rules['experience'](v),
                'declaration-name-ren': v => {
                    if (!v || !v.trim()) return 'Declaration name is required.';
                    if (!/^[A-Za-zÀ-ž' .-]{4,100}$/.test(v)) return 'Use letters, spaces, and punctuation (.-\').';
                    const f = document.querySelector('#first-name-ren')?.value?.trim().toLowerCase();
                    const l = document.querySelector('#last-name-ren')?.value?.trim().toLowerCase();
                    if (f && l) {
                        const lower = v.trim().toLowerCase();
                        if (!(lower.includes(f) && lower.includes(l))) return 'Include your first and last name here.';
                    }
                },
                'prev-certificate-ren': v => {
                    if (v && !GENERIC_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                },
                'issued-date-ren': v => {
                    const exp = $('#expiry-date-ren')?.value;
                    if (v && exp && v > exp) return 'Issued date must be on/before the expiry date.';
                },
                'expiry-date-ren': v => {
                    const iss = $('#issued-date-ren')?.value;
                    if (v && iss && iss > v) return 'Expiry date must be on/after issued date.';
                },
                'cr-license-ren': v => {
                    if (v && !GENERIC_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                },
                'sawmill-permit-ren': v => {
                    if (v && !GENERIC_ID_RX.test(v)) return 'Use letters/numbers, slashes or dashes.';
                }
            };

            /* ------------------ suppliers table validation (updated) ------------------ */
            function validateSuppliers(isRenewal) {
                let ok = true;
                const rows = isRenewal ?
                    Array.from(document.querySelectorAll('#suppliers-table-ren tbody tr')) :
                    Array.from(document.querySelectorAll('#suppliers-table tbody tr'));

                let hasAnyContent = false;
                let hasOneComplete = false;

                rows.forEach(tr => {
                    const nameEl = tr.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                    const volEl = tr.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');

                    ensureErrorEl(nameEl);
                    ensureErrorEl(volEl);

                    const name = (nameEl?.value || '').trim();
                    const vol = (volEl?.value || '').trim();

                    // blank row -> ignore
                    if (!name && !vol) {
                        setError(nameEl, '');
                        setError(volEl, '');
                        return;
                    }

                    hasAnyContent = true;

                    // per-field messages for partially filled rows
                    if (!name) {
                        setError(nameEl, 'Supplier name is required.');
                        ok = false;
                    } else if (!/^[A-Za-zÀ-ž0-9' .,-]{3,}$/.test(name)) {
                        setError(nameEl, 'Enter a valid name (3+ chars).');
                        ok = false;
                    } else {
                        setError(nameEl, '');
                    }

                    if (!vol) {
                        setError(volEl, 'Volume is required.');
                        ok = false;
                    } else if (!/\d/.test(vol)) {
                        setError(volEl, 'Include a numeric value.');
                        ok = false;
                    } else {
                        setError(volEl, '');
                    }

                    if (name && vol && /\d/.test(vol)) hasOneComplete = true;
                });

                // Only show the table-level prompt when truly nothing is entered anywhere
                if (!hasAnyContent) {
                    const first = rows[0];
                    if (first) {
                        const nameEl = first.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                        const volEl = first.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');
                        setError(nameEl, 'Add at least one supplier.');
                        setError(volEl, 'Add at least one supplier.');
                    }
                    ok = false;
                }

                return ok && hasOneComplete;
            }

            /* ------------------ section validation ------------------ */
            function activeType() {
                return (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');
            }

            function validateSection(type) {
                const isRenewal = type === 'renewal';
                const ids = isRenewal ? [
                    'first-name-ren', 'middle-name-ren', 'last-name-ren', 'applicant-age-ren',
                    'business-name-ren', 'business-address-ren',
                    'operation-place-ren', 'annual-volume-ren', 'annual-worth-ren',
                    'employees-count-ren', 'dependents-count-ren',
                    'intended-market-ren', 'experience-ren', 'declaration-name-ren',
                    'prev-certificate-ren', 'issued-date-ren', 'expiry-date-ren',
                    'cr-license-ren', 'sawmill-permit-ren'
                ] : [
                    'first-name', 'middle-name', 'last-name', 'applicant-age',
                    'business-name', 'business-address',
                    'operation-place', 'annual-volume', 'annual-worth',
                    'employees-count', 'dependents-count',
                    'intended-market', 'experience', 'declaration-name'
                ];

                let ok = true;

                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    ensureErrorEl(el);

                    const rule = rules[id];
                    let msg = rule ? (rule(el.value) || '') : '';

                    // pair logic for issued/expiry (both or none)
                    if (id === 'issued-date-ren' || id === 'expiry-date-ren') {
                        const iss = $('#issued-date-ren')?.value;
                        const exp = $('#expiry-date-ren')?.value;
                        if ((iss && !exp) || (!iss && exp)) msg = 'Provide both issued and expiry dates.';
                        else if (rule) msg = rule(el.value) || '';
                    }

                    if (msg) {
                        setError(el, msg);
                        ok = false;
                    } else setError(el, '');
                });

                // hard check: numbers in names (guard)
                ['first-name', 'last-name', 'first-name-ren', 'last-name-ren'].forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (/\d/.test(el.value)) {
                        setError(el, 'Names cannot contain numbers.');
                        ok = false;
                    }
                });

                if (!validateSuppliers(isRenewal)) ok = false;

                return ok;
            }

            /* ------------------ live validation + submit interception ------------------ */
            function attachLiveValidation() {
                const allIds = [
                    'first-name', 'middle-name', 'last-name', 'applicant-age', 'business-name', 'business-address',
                    'operation-place', 'annual-volume', 'annual-worth', 'employees-count', 'dependents-count',
                    'intended-market', 'experience', 'declaration-name',
                    'first-name-ren', 'middle-name-ren', 'last-name-ren', 'applicant-age-ren', 'business-name-ren', 'business-address-ren',
                    'operation-place-ren', 'annual-volume-ren', 'annual-worth-ren', 'employees-count-ren', 'dependents-count-ren',
                    'intended-market-ren', 'experience-ren', 'declaration-name-ren',
                    'prev-certificate-ren', 'issued-date-ren', 'expiry-date-ren', 'cr-license-ren', 'sawmill-permit-ren'
                ];
                allIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    ensureErrorEl(el);
                    const handler = () => {
                        const rule = rules[id];
                        let msg = rule ? (rule(el.value) || '') : '';
                        if (id === 'issued-date-ren' || id === 'expiry-date-ren') {
                            const iss = $('#issued-date-ren')?.value;
                            const exp = $('#expiry-date-ren')?.value;
                            if ((iss && !exp) || (!iss && exp)) msg = 'Provide both issued and expiry dates.';
                            else if (rule) msg = rule(el.value) || '';
                        }
                        setError(el, msg);
                    };
                    el.addEventListener('input', handler);
                    el.addEventListener('blur', handler);
                    // in case previous CSS/classes were adding red borders:
                    el.classList.remove('error', 'is-invalid');
                });

                // suppliers: attach per row + when adding rows
                function hookRows(tableSel, isRenewal) {
                    $$(tableSel + ' tbody tr').forEach(tr => {
                        const nameEl = tr.querySelector(isRenewal ? '.supplier-name-ren' : '.supplier-name');
                        const volEl = tr.querySelector(isRenewal ? '.supplier-volume-ren' : '.supplier-volume');
                        if (nameEl) {
                            ensureErrorEl(nameEl);
                            nameEl.addEventListener('input', () => validateSuppliers(isRenewal));
                        }
                        if (volEl) {
                            ensureErrorEl(volEl);
                            volEl.addEventListener('input', () => validateSuppliers(isRenewal));
                        }
                    });
                }
                hookRows('#suppliers-table', false);
                hookRows('#suppliers-table-ren', true);

                $('#add-supplier-row')?.addEventListener('click', () => setTimeout(() => hookRows('#suppliers-table', false), 0));
                $('#add-supplier-row-ren')?.addEventListener('click', () => setTimeout(() => hookRows('#suppliers-table-ren', true), 0));
            }

            function interceptSubmitClicks() {
                const guard = (e) => {
                    const ok = validateSection(activeType());
                    if (!ok) {
                        e.stopImmediatePropagation?.();
                        e.preventDefault?.();
                        const firstErr = document.querySelector('.field-error:not([style*="display: none"])');
                        if (firstErr && firstErr.previousElementSibling?.focus) firstErr.previousElementSibling.focus();
                    }
                };
                // capture phase => runs before the existing click handler you already have
                document.getElementById('submitApplication')?.addEventListener('click', guard, true);
                // also guard the confirm button in case someone reaches it directly
                document.getElementById('btnOkConfirm')?.addEventListener('click', guard, true);
            }

            // expose manual trigger if you ever want to call it elsewhere
            window.validateLumberForm = () => validateSection(activeType());

            // init
            attachLiveValidation();
            interceptSubmitClicks();
        })();
    </script>



</body>






</html>