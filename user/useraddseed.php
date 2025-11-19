<?php

declare(strict_types=1);

/**
 * User-only gate for user_home.php
 * - Requires a logged-in session
 * - Role must be 'User'
 * - Status must be 'Verified'
 * - Verifies against DB on each hit (defense-in-depth)
 */

session_start();

// Optional: extra safety headers (helps on back/forward caching)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Quick session check first
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

// DB check to ensure the session still matches a User, Verified account
require_once __DIR__ . '/../backend/connection.php'; // must expose $pdo (PDO -> Supabase PG)

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
    // prepare statement to detect seedling approvals when rendering notification titles
    $stApprovalType = $pdo->prepare("SELECT seedl_req_id FROM public.approval WHERE approval_id = :aid LIMIT 1");
} catch (Throwable $e) {
    error_log('[NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

try {
    $st = $pdo->prepare("
        select role, status
        from public.users
        where user_id = :id
        limit 1
    ");
    $st->execute([':id' => $_SESSION['user_id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $roleOk   = $row && strtolower((string)$row['role']) === 'user';
    $statusOk = $row && strtolower((string)$row['status']) === 'verified';

    if (!$roleOk || !$statusOk) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: user_login.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[USER-HOME GUARD] ' . $e->getMessage());
    header('Location: user_login.php');
    exit();
}
$clientRows = [];
try {
    $q = $pdo->prepare("
        SELECT client_id, user_id, first_name, middle_name, last_name, barangay, municipality, city
        FROM public.client
        ORDER BY (user_id = :uid) DESC, last_name ASC, first_name ASC
        LIMIT 500
    ");
    $q->execute([':uid' => $_SESSION['user_id']]);
    $clientRows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[SEED-CLIENTS] ' . $e->getMessage());
    $clientRows = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Seedlings</title>
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
            --as-primary: var(--primary-color, #2b6625);
            --as-primary-dark: var(--primary-dark, #1e4a1a);
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
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

        .form-group input[type="date"],
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
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: start;
            border: 1px solid black;
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

        /* Sample Letter Button */
        .sample-letter-btn {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .download-sample {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .download-sample:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }


        .name-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            MARGIN-TOP: -1%;
            margin-bottom: 10px;

            width: 100%;
        }

        .name-field {
            flex: 1;
            min-width: 200px;
        }

        .name-field input,
        input,
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #153415;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
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
        }

        /* === Seedling dropdown (native <select>) === */
        .seedling-select {
            /* reset native look */
            -webkit-appearance: none;
            appearance: none;

            /* sizing + shape */
            width: 100%;
            height: 44px;
            padding: 10px 42px 10px 14px;
            /* room for the arrow on the right */
            border: 2px solid var(--primary-color, #2b6625);
            border-radius: 10px;

            /* text + bg */
            background-color: #fff;

            /* custom arrow (inline SVG) */


            /* polish */
            font-size: 14px;
            font-weight: 600;
            transition: border-color .2s, box-shadow .2s, transform .02s ease-in-out;
        }

        .seedling-select:hover {
            transform: translateY(-1px);
        }

        .seedling-select:focus {
            outline: none;
            border-color: var(--primary-dark, #1e4a1a);
            box-shadow: 0 0 0 3px rgba(43, 102, 37, 0.18);
        }

        .seedling-select:disabled {
            color: #8a8a8a;
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        /* Firefox adds a dotted outline on focus-visible; keep it tidy */
        .seedling-select:-moz-focusring {
            color: transparent;
            text-shadow: 0 0 0 #000;
        }

        /* === Quantity number input === */
        .seedling-qty {
            width: 100%;
            min-width: 150px;
            height: 44px;
            padding: 10px 12px;
            border: 2px solid var(--primary-color, #2b6625);
            border-radius: 10px;
            background-color: #fff;
            font-size: 14px;
            font-weight: 600;
            transition: border-color .2s, box-shadow .2s, transform .02s ease-in-out;
        }

        .seedling-qty::placeholder {
            color: #6b6b6b;
            font-weight: 500;
            /* your “Stocks left: X” hint looks distinct */
        }

        .seedling-qty:hover {
            transform: translateY(-1px);
        }

        .seedling-qty:focus {
            outline: none;
            border-color: var(--primary-dark, #1e4a1a);
            box-shadow: 0 0 0 3px rgba(43, 102, 37, 0.18);
        }

        /* Optional: hide native spin buttons for a cleaner look */
        .seedling-qty::-webkit-outer-spin-button,
        .seedling-qty::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .seedling-qty[type="number"] {
            appearance: textfield;
            -webkit-appearance: textfield;
            -moz-appearance: textfield; /* Firefox */
        }

        /* Row layout stays tidy on small screens */
        .seedling-row {
            align-items: center;
        }

        .as-item {
            position: relative;
        }

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

        /* dropdown shell */
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

        /* notifications panel */
        .as-notifications {
            min-width: 350px;
            max-height: 500px;
        }

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

        /* 🔽 the scrolling list wrapper (this holds the records) */
        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

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
    </style>
</head>

<body>
    <!-- Global toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Loading overlay -->
    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
    <div id="loadingOverlay"
        style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.55);
            backdrop-filter:blur(2px); z-index:10000; align-items:center; justify-content:center;">
        <div style="background:#0b1220; color:#fff; padding:22px 26px; border-radius:12px;
              box-shadow:0 10px 30px rgba(0,0,0,.35); min-width:240px; text-align:center;">
            <div style="margin:0 auto 12px; width:42px; height:42px; border:4px solid rgba(255,255,255,.25);
                border-top-color:#fff; border-radius:50%; animation:spin 1s linear infinite;"></div>
            <div id="loadingText" style="font-weight:600;">Submitting…</div>
        </div>
    </div>

    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo"></a>
        </div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-exclamation-triangle"></i><span>Report Incident</span></a>
                    <a href="useraddseed.php" class="dropdown-item active-page"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                    <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                    <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                    <a href="useraddchainsaw.php" class="dropdown-item"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge"><?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <div class="notifcontainer">
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread = empty($n['is_read']);
                                $ts = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                // Determine title: if approval -> check if it's a seedlings approval
                                $title = 'Notification';
                                if (!empty($n['approval_id'])) {
                                    try {
                                        $stApprovalType->execute([':aid' => $n['approval_id']]);
                                        $aprRow = $stApprovalType->fetch(PDO::FETCH_ASSOC);
                                        if (!empty($aprRow) && !empty($aprRow['seedl_req_id'])) {
                                            $title = 'Seedlings Request Update';
                                        } else {
                                            $title = 'Permit Update';
                                        }
                                    } catch (Throwable $e) {
                                        // fallback
                                        $title = 'Permit Update';
                                    }
                                } elseif (!empty($n['incident_id'])) {
                                    $title = 'Incident Update';
                                }
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There’s an update.';
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
                                            <script>
                                            // Manila time + relative label for notifications
                                            (function() {
                                                function timeAgo(seconds) {
                                                    if (seconds < 60) return 'just now';
                                                    const m = Math.floor(seconds / 60);
                                                    if (m < 60) return `${m} minute${m > 1 ? 's' : ''} ago`;
                                                    const h = Math.floor(m / 60);
                                                    if (h < 24) return `${h} hour${h > 1 ? 's' : ''} ago`;
                                                    const d = Math.floor(h / 24);
                                                    if (d < 7) return `${d} day${d > 1 ? 's' : ''} ago`;
                                                    const w = Math.floor(d / 7);
                                                    if (w < 5) return `${w} week${w > 1 ? 's' : ''} ago`;
                                                    const mo = Math.floor(d / 30);
                                                    if (mo < 12) return `${mo} month${mo > 1 ? 's' : ''} ago`;
                                                    const y = Math.floor(d / 365);
                                                    return `${y} year${y > 1 ? 's' : ''} ago`;
                                                }
                                                document.querySelectorAll('.as-notif-time[data-ts]').forEach(el => {
                                                    const tsMs = Number(el.dataset.ts || 0) * 1000;
                                                    if (!tsMs) return;
                                                    const diffSec = Math.floor((Date.now() - tsMs) / 1000);
                                                    el.textContent = timeAgo(diffSec);
                                                    try {
                                                        const manilaFmt = new Intl.DateTimeFormat('en-PH', {
                                                            timeZone: 'Asia/Manila',
                                                            year: 'numeric', month: 'short', day: 'numeric',
                                                            hour: 'numeric', minute: '2-digit', second: '2-digit'
                                                        });
                                                        el.title = manilaFmt.format(new Date(tsMs));
                                                    } catch (err) {
                                                        el.title = new Date(tsMs).toLocaleString();
                                                    }
                                                });
                                            })();
                                            </script>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="as-notif-footer">
                        <a href="user_notification.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="requirements-form">
            <div class="form-header">
                <h2>Seedling Request - Requirement</h2>
            </div>

            <div class="form-body">
                <!-- Mode toggle + client picker + names -->
                <div style="display:flex;gap:8px;align-items: center; margin-bottom:10px; padding: 10px 0px;">
                    <button type="button" id="btnExisting" class="btn btn-outline">
                        <i class="fas fa-user-check"></i>&nbsp;Existing client
                    </button>
                    <button type="button" id="btnNew" class="btn btn-outline" style="display:none;">
                        <i class="fas fa-user-plus"></i>&nbsp;New client
                    </button>
                    <small style="opacity:.8;margin-left:6px; ">Choose <b>Existing client</b> if client already exists in records.</small>
                </div>

                <!-- Hidden field to track mode -->
                <input type="hidden" id="clientMode" value="new">

                <!-- Existing client selection (hidden by default) -->
                <div id="existingClientRow" class="name-fields" style="display:none;">
                    <div class="name-field" style="grid-column:1/-1;">
                        <label for="clientPick" style="font-weight:600;margin-bottom:6px;display:block;">Select client</label>
                        <select id="clientPick" class="seedling-select" style="height:40px;">
                            <option value="">— Select a client —</option>
                            <?php
                            $hasMine = false;
                            foreach ($clientRows as $c) {
                                $isMine = ((string)$c['user_id'] === (string)$_SESSION['user_id']);
                                if (!$isMine) continue;

                                $full = trim(($c['first_name'] ?? '') . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                $addr = trim(($c['barangay'] ? ('Brgy. ' . $c['barangay']) : '') . (($c['municipality'] || $c['city']) ? (', ' . ($c['municipality'] ?: $c['city'])) : ''));
                                $label = $full . ($addr ? " — $addr" : '');
                                echo '<option value="' . htmlspecialchars((string)$c['client_id'], ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
                                $hasMine = true;
                            }
                            if (!$hasMine) {
                                echo '<option disabled>No clients found</option>';
                            }
                            ?>
                        </select>
                        <div id="clientPickError" class="fv-error" style="display:none;"></div>
                    </div>
                </div>

                <!-- New client fields (default visible) -->
                <div id="newClientRow" class="name-fields" style="margin-bottom: 10px; ">
                    <div class="name-field"><input type="text" placeholder="First Name" id="firstName" required></div>
                    <div class="name-field"><input type="text" placeholder="Middle Name" id="middleName"></div>
                    <div class="name-field"><input type="text" placeholder="Last Name" id="lastName" required></div>
                </div>

                <!-- Contact -->
                <div class="name-fields" style="margin-top:8px;">
                    <div class="name-field" style="width:320px;">
                        <input type="text" placeholder="Contact Number (optional)" id="contactNumber">
                    </div>
                </div>

                <!-- Purpose -->
                <div style="width:100%;display:flex;flex-direction:column;">
                    <label style="font-weight:600">Purpose of the Request</label>
                    <textarea id="purpose" placeholder="e.g., community tree-planting along the barangay road..." style="width:100%;height:80px"></textarea>
                </div>

                <!-- Address + org + date -->
                <div style="width:100%;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:14px">
                    <input type="text" id="organization" placeholder="Organization (optional)" style="width:100%;">
                    <input list="barangayList" id="barangay" placeholder="Barangay">
                    <datalist id="barangayList">
                        <option value="Guadalupe">
                        <option value="Lahug">
                        <option value="Mabolo">
                        <option value="Labangon">
                        <option value="Talamban">
                    </datalist>
                    <input type="text" id="sitioStreet" placeholder="Sitio / Street">
                    <select id="municipality">
                        <option value="">Select Municipality (Cebu)</option>
                        <option>Alcantara</option>
                        <option>Alcoy</option>
                        <option>Alegria</option>
                        <option>Aloguinsan</option>
                        <option>Argao</option>
                        <option>Asturias</option>
                        <option>Badian</option>
                        <option>Balamban</option>
                        <option>Bantayan</option>
                        <option>Barili</option>
                        <option>Boljoon</option>
                        <option>Borbon</option>
                        <option>Carmen</option>
                        <option>Catmon</option>
                        <option>Compostela</option>
                        <option>Consolacion</option>
                        <option>Cordova</option>
                        <option>Daanbantayan</option>
                        <option>Dalaguete</option>
                        <option>Dumanjug</option>
                        <option>Ginatilan</option>
                        <option>Liloan</option>
                        <option>Madridejos</option>
                        <option>Malabuyoc</option>
                        <option>Medellin</option>
                        <option>Minglanilla</option>
                        <option>Moalboal</option>
                        <option>Oslob</option>
                        <option>Pilar</option>
                        <option>Pinamungajan</option>
                        <option>Poro</option>
                        <option>Ronda</option>
                        <option>Samboan</option>
                        <option>San Fernando</option>
                        <option>San Francisco</option>
                        <option>San Remigio</option>
                        <option>Santa Fe</option>
                        <option>Santander</option>
                        <option>Sibonga</option>
                        <option>Sogod</option>
                        <option>Tabogon</option>
                        <option>Tabuelan</option>
                        <option>Tuburan</option>
                        <option>Tudela</option>
                    </select>
                    <select id="city">
                        <option value="">Select City (Cebu)</option>
                        <option>Bogo City</option>
                        <option>Carcar City</option>
                        <option>Cebu City</option>
                        <option>Danao City</option>
                        <option>Lapu-Lapu City</option>
                        <option>Mandaue City</option>
                        <option>Naga City</option>
                        <option>Talisay City</option>
                        <option>Toledo City</option>
                    </select>
                    <input type="date" id="requestDate" required style="width:100%;height:40px;box-sizing:border-box;">
                </div>
                <small style="color:#666;display:block;margin-top:6px;">Tip: choose either a City <em>or</em> a Municipality. Province is assumed as Cebu.</small>

                <!-- Seedlings list -->
                <div style="margin-top:18px">
                    <label style="font-weight:600;display:block;margin-bottom:8px">Seedlings Requested (add more rows as needed)</label>
                    <div id="seedlingList" style="display:flex;flex-direction:column;gap:10px"></div>
                    <button type="button" id="addSeedlingBtn" class="btn btn-outline" style="margin-top:6px"><i class="fas fa-plus-circle"></i> Add another seedling</button>
                </div>

                <!-- Signature pad -->
                <div class="sig-wrap" style="margin-top:18px">
                    <label style="font-weight:600;display:block;margin-bottom:6px">Signature (draw inside the box)</label>
                    <canvas id="sigCanvas" style="width:400px;height:150px;border:2px dashed #9aa;border-radius:8px;background:#fff;touch-action:none"></canvas>
                    <div class="sig-actions" style="display:flex;gap:8px;margin-top:8px">
                        <button type="button" id="sigClear" class="btn btn-outline">Clear</button>
                        <button type="button" id="sigUndo" class="btn btn-outline">Undo</button>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </div>
    </div>

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
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this seedling request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <!-- Client decision modal -->
    <div id="clientDecisionModal" class="modal">
        <div class="modal-content" style="max-width:480px;text-align:center;">
            <span id="closeClientDecisionModal" class="close-modal">&times;</span>
            <h3>Existing Client Found</h3>
            <p id="clientDecisionText" style="margin:10px 0 16px 0;"></p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button id="useExistingBtn" class="btn btn-primary">Use existing</button>
                <button id="saveNewBtn" class="btn btn-outline">Save as new</button>
            </div>
        </div>
    </div>

    <!-- Keep the SignaturePad loader if you want (not one of the 2 inline scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* Toast */
            const noteEl = document.getElementById('profile-notification');

            function toast(message, opts = {}) {
                const {
                    type = 'info', timeout = 3000, html = false
                } = opts;
                if (!noteEl) return;
                noteEl.setAttribute('role', 'status');
                noteEl.setAttribute('aria-live', 'polite');
                noteEl.setAttribute('aria-atomic', 'true');
                noteEl.style.transition = 'opacity .2s ease-in-out';
                noteEl.style.background = type === 'error' ? '#c0392b' : (type === 'success' ? '#2d8a34' : '#323232');
                noteEl[html ? 'innerHTML' : 'textContent'] = message;
                noteEl.style.display = 'block';
                noteEl.style.opacity = '1';
                clearTimeout(noteEl._hideTimer);
                noteEl._hideTimer = setTimeout(() => {
                    noteEl.style.opacity = '0';
                    setTimeout(() => {
                        noteEl.style.display = 'none';
                        noteEl.style.opacity = '1';
                    }, 200);
                }, timeout);
            }

            /* Loading overlay */
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = document.getElementById('loadingText');

            function setLoading(show, text = 'Submitting…') {
                if (!overlay) return;
                loadingText.textContent = text || 'Submitting…';
                overlay.style.display = show ? 'flex' : 'none';
                document.body.style.pointerEvents = show ? 'none' : '';
            }

            /* Mobile menu */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) mobileToggle.addEventListener('click', () => {
                const isActive = navContainer.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            });

            /* Signature pad */
            const canvas = document.getElementById('sigCanvas');
            const sigPad = new SignaturePad(canvas, {
                backgroundColor: '#fff',
                penColor: 'black',
                minWidth: 0.8,
                maxWidth: 2.2
            });

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                canvas.width = Math.floor(rect.width * ratio);
                canvas.height = Math.floor(150 * ratio);
                canvas.getContext('2d').scale(ratio, ratio);
                sigPad.clear();
            }
            window.addEventListener('resize', resizeCanvas);
            setTimeout(resizeCanvas, 0);
            document.getElementById('sigClear').addEventListener('click', () => sigPad.clear());
            document.getElementById('sigUndo').addEventListener('click', () => {
                const d = sigPad.toData();
                if (d.length) {
                    d.pop();
                    sigPad.fromData(d);
                }
            });

            /* ==== Client mode toggle (NEW <-> EXISTING) ==== */
            const btnExisting = document.getElementById('btnExisting');
            const btnNew = document.getElementById('btnNew');
            const newClientRow = document.getElementById('newClientRow');
            const existingRow = document.getElementById('existingClientRow');
            const clientPick = document.getElementById('clientPick');
            const clientModeEl = document.getElementById('clientMode');
            const firstEl = document.getElementById('firstName');
            const middleEl = document.getElementById('middleName');
            const lastEl = document.getElementById('lastName');

            function setMode(mode) {
                const isExisting = (mode === 'existing');
                clientModeEl.value = isExisting ? 'existing' : 'new';

                // Show/hide
                existingRow.style.display = isExisting ? 'grid' : 'none';
                newClientRow.style.display = isExisting ? 'none' : 'grid';
                btnExisting.style.display = isExisting ? 'none' : 'inline-flex';
                btnNew.style.display = isExisting ? 'inline-flex' : 'none';

                // Disable/enable name inputs
                [firstEl, middleEl, lastEl].forEach(el => {
                    if (el) el.disabled = isExisting;
                });

                // Clear the opposite side
                if (isExisting) {
                    clientPick && (clientPick.value = clientPick.value || '');
                    [firstEl, middleEl, lastEl].forEach(el => {
                        if (el) el.value = '';
                    });
                } else {
                    clientPick && (clientPick.value = '');
                }
            }
            btnExisting?.addEventListener('click', () => setMode('existing'));
            btnNew?.addEventListener('click', () => setMode('new'));
            // default mode
            setMode('new');

            /* Seedlings catalog */
            const seedlingList = document.getElementById('seedlingList');
            const addSeedlingBtn = document.getElementById('addSeedlingBtn');
            let seedlingsCatalog = [];
            let submissionBatchKey = null;

            function ensureBatchKey() {
                if (submissionBatchKey) return submissionBatchKey;
                submissionBatchKey = `batch_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;
                return submissionBatchKey;
            }

            async function loadSeedlings() {
                try {
                    const res = await fetch('../backend/users/seedlings/list.php', {
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error('Failed to load seedlings');
                    seedlingsCatalog = data.seedlings || [];
                    seedlingList.innerHTML = '';
                    seedlingList.style.width = '100%';
                    addSeedlingRow();
                } catch (e) {
                    toast('Could not load seedlings list.', {
                        type: 'error'
                    });
                }
            }

            function buildSeedlingSelect() {
                const sel = document.createElement('select');
                sel.className = 'seedling-name seedling-select';
                sel.style.height = '40px';
                sel.style.width = '100%';
                sel.innerHTML = '<option value="">Select seedling</option>';
                seedlingsCatalog.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.seedlings_id;
                    opt.textContent = s.seedling_name + (Number(s.stock) <= 0 ? ' — out of stock' : '');
                    opt.dataset.name = s.seedling_name || '';
                    opt.dataset.stock = String(s.stock ?? 0);
                    if (Number(s.stock) <= 0) opt.disabled = true;
                    sel.appendChild(opt);
                });
                return sel;
            }

            function addSeedlingRow() {
                const row = document.createElement('div');
                row.className = 'seedling-row';
                row.style.display = 'grid';
                row.style.gridTemplateColumns = '2fr 1fr auto';
                row.style.gap = '8px';
                row.style.width = '700px';

                const sel = buildSeedlingSelect();
                const qty = document.createElement('input');
                qty.type = 'number';
                qty.className = 'seedling-qty';
                qty.placeholder = 'Qty';
                qty.min = '1';
                qty.step = '1';
                qty.style.height = '40px';
                qty.style.width = '100%';

                function syncQtyMeta(showMsg = false) {
                    const opt = sel.options[sel.selectedIndex];
                    const stock = Number(opt?.dataset.stock || 0);
                    const name = opt?.dataset.name || '';
                    qty.placeholder = stock ? `Available: ${stock}` : 'Available: 0';
                    qty.max = stock ? String(stock) : '';
                    qty.disabled = stock <= 0 || !sel.value;
                    if (qty.value && stock && Number(qty.value) > stock) {
                        qty.value = String(stock);
                        if (showMsg) toast(`Maximum available for "${name}" is ${stock}.`, {
                            type: 'error',
                            timeout: 2200
                        });
                    }
                }

                function enforceMaxOnType() {
                    const opt = sel.options[sel.selectedIndex];
                    const stock = Number(opt?.dataset.stock || 0);
                    const name = opt?.dataset.name || '';
                    if (!sel.value || !stock) {
                        qty.value = '';
                        return;
                    }
                    const v = parseInt(qty.value || '0', 10);
                    if (!Number.isFinite(v) || v < 1) return;
                    if (v > stock) {
                        qty.value = String(stock);
                        toast(`Maximum available for "${name}" is ${stock}.`, {
                            type: 'error',
                            timeout: 2200
                        });
                    }
                }
                qty.addEventListener('wheel', e => e.target.blur(), {
                    passive: true
                });
                sel.addEventListener('change', () => syncQtyMeta(true));
                qty.addEventListener('input', enforceMaxOnType);
                syncQtyMeta();

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-outline remove-row';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.addEventListener('click', () => {
                    row.remove();
                    if (!seedlingList.children.length) addSeedlingRow();
                });

                row.appendChild(sel);
                row.appendChild(qty);
                row.appendChild(removeBtn);
                seedlingList.appendChild(row);
            }
            addSeedlingBtn.addEventListener('click', addSeedlingRow);
            loadSeedlings();

            /* Confirm modal + decision modal */
            const confirmModal = document.getElementById('confirmModal');
            document.getElementById('closeConfirmModal').addEventListener('click', () => confirmModal.style.display = 'none');
            document.getElementById('cancelSubmitBtn').addEventListener('click', () => confirmModal.style.display = 'none');

            const clientDecisionModal = document.getElementById('clientDecisionModal');
            const clientDecisionText = document.getElementById('clientDecisionText');
            document.getElementById('closeClientDecisionModal').addEventListener('click', () => clientDecisionModal.style.display = 'none');

            /* Gather + POST */
            function gatherPayload() {
                return {
                    batch_key: ensureBatchKey(),
                    first_name: document.getElementById('firstName').value.trim(),
                    middle_name: document.getElementById('middleName').value.trim(),
                    last_name: document.getElementById('lastName').value.trim(),
                    contact_number: document.getElementById('contactNumber').value.trim(),
                    organization: document.getElementById('organization').value.trim(),
                    purpose: document.getElementById('purpose').value.trim(),
                    sitio_street: document.getElementById('sitioStreet').value.trim(),
                    barangay: document.getElementById('barangay').value.trim(),
                    municipality: document.getElementById('municipality').value.trim(),
                    city: document.getElementById('city').value.trim(),
                    request_date: document.getElementById('requestDate').value,
                    signature_b64: sigPad.toDataURL('image/png'),
                    seedlings: Array.from(document.querySelectorAll('.seedling-row')).map(row => {
                        const sel = row.querySelector('select.seedling-name');
                        const qtyEl = row.querySelector('.seedling-qty');
                        return {
                            seedlings_id: sel?.value || '',
                            qty: Number(qtyEl?.value || 0)
                        };
                    }).filter(s => s.seedlings_id && s.qty > 0)
                };
            }

            async function postRequest(mode, extra = {}, label = 'Submitting…') {
                setLoading(true, label);
                try {
                    const payload = {
                        ...gatherPayload(),
                        mode,
                        ...extra
                    };
                    const res = await fetch('../backend/users/seedlings/request_seedlings.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.error || 'Request failed');
                    return data;
                } finally {
                    setLoading(false);
                }
            }

            /* Open confirm (basic front-end checks; full validation is in 2nd script) */
            document.getElementById('submitApplication').addEventListener('click', (e) => {
                e.preventDefault();
                const mode = document.getElementById('clientMode')?.value || 'new';
                const purpose = document.getElementById('purpose').value.trim();
                const reqDate = document.getElementById('requestDate').value;

                if (sigPad.isEmpty()) return toast('Please provide your signature.', {
                    type: 'error'
                });

                const hasSeedling = Array.from(document.querySelectorAll('.seedling-row')).some(row => {
                    const sel = row.querySelector('select.seedling-name');
                    const qty = row.querySelector('.seedling-qty');
                    return sel?.value && Number(qty?.value || 0) > 0;
                });
                if (!hasSeedling) return toast('Add at least one seedling with a valid quantity.', {
                    type: 'error'
                });

                if (!purpose) return toast('Please enter the purpose of your request.', {
                    type: 'error'
                });
                if (!reqDate) return toast('Please choose the date of request.', {
                    type: 'error'
                });

                // Existing mode: require a pick before even opening confirm?
                if (mode === 'existing' && !(clientPick?.value)) {
                    return toast('Please select an existing client.', {
                        type: 'error'
                    });
                }
                confirmModal.style.display = 'block';
            });

            /* Final confirm → submit by mode */
            let decisionCtx = null;
            document.getElementById('confirmSubmitBtn').addEventListener('click', async () => {
                confirmModal.style.display = 'none';
                const mode = document.getElementById('clientMode')?.value || 'new';

                // EXISTING MODE: force reuse with selected client
                if (mode === 'existing') {
                    const cid = clientPick?.value || '';
                    if (!cid) {
                        toast('Please select an existing client.', {
                            type: 'error'
                        });
                        return;
                    }
                    try {
                        await postRequest('reuse', {
                            existing_client_id: cid
                        }, 'Submitting with existing client…');
                        toast('Request submitted (using existing client)', {
                            type: 'success',
                            timeout: 4000
                        });
                        clientDecisionModal.style.display = 'none';
                        resetForm();
                    } catch (err) {
                        console.error(err);
                        toast('Submission failed: ' + (err?.message || err), {
                            type: 'error',
                            timeout: 7000
                        });
                    }
                    return;
                }

                // NEW MODE: duplicate-check then decision
                try {
                    const data = await postRequest('auto', {}, 'Checking for existing client…'); // duplicate check
                    if (data.needs_decision) {
                        decisionCtx = {
                            existing: data.existing_client
                        };
                        const ex = data.existing_client;
                        const nm = [ex.first_name, ex.middle_name, ex.last_name].filter(Boolean).join(' ');
                        const addr = [ex.sitio_street, ex.barangay && `Brgy. ${ex.barangay}`, ex.municipality || ex.city].filter(Boolean).join(', ');
                        clientDecisionText.textContent = `A client record for "${nm}" already exists (${addr || 'no address saved'}). Do you want to use this existing record or save as a new client?`;
                        clientDecisionModal.style.display = 'block';
                    } else {
                        toast('Request submitted successfully', {
                            type: 'success',
                            timeout: 4000
                        });
                        resetForm();
                    }
                } catch (err) {
                    console.error(err);
                    toast('Submission failed: ' + (err?.message || err), {
                        type: 'error',
                        timeout: 7000
                    });
                }
            });

            // Decision modal buttons (used only when NEW mode found a duplicate)
            document.getElementById('useExistingBtn').addEventListener('click', async () => {
                try {
                    const cid = decisionCtx?.existing?.client_id;
                    if (!cid) throw new Error('Missing existing client id.');
                    await postRequest('reuse', {
                        existing_client_id: cid
                    }, 'Submitting with existing client…');
                    clientDecisionModal.style.display = 'none';
                    toast('Request submitted (using existing client)', {
                        type: 'success',
                        timeout: 4000
                    });
                    resetForm();
                } catch (err) {
                    console.error(err);
                    toast('Submission failed: ' + (err?.message || err), {
                        type: 'error',
                        timeout: 7000
                    });
                }
            });
            document.getElementById('saveNewBtn').addEventListener('click', async () => {
                try {
                    await postRequest('new', {}, 'Submitting as new client…');
                    clientDecisionModal.style.display = 'none';
                    toast('Request submitted (saved as new client)', {
                        type: 'success',
                        timeout: 4000
                    });
                    resetForm();
                } catch (err) {
                    console.error(err);
                    toast('Submission failed: ' + (err?.message || err), {
                        type: 'error',
                        timeout: 7000
                    });
                }
            });

            function resetForm() {
                try {
                    // reset mode first
                    setMode('new');

                    document.getElementById('firstName').value = '';
                    document.getElementById('middleName').value = '';
                    document.getElementById('lastName').value = '';
                    document.getElementById('contactNumber').value = '';
                    document.getElementById('organization').value = '';
                    document.getElementById('purpose').value = '';
                    document.getElementById('sitioStreet').value = '';
                    document.getElementById('barangay').value = '';
                    document.getElementById('municipality').value = '';
                    document.getElementById('city').value = '';
                    sigPad.clear();
                    seedlingList.innerHTML = '';
                    addSeedlingRow();
                    submissionBatchKey = null;

                    // default date again
                    const today = new Date();
                    const requestDate = document.getElementById('requestDate');
                    requestDate.value = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
                } catch (_) {}
            }

            // Default date = today (on initial load)
            const requestDate = document.getElementById('requestDate');
            if (requestDate && !requestDate.value) {
                const today = new Date();
                requestDate.value = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
            }
        });
    </script>
    <script>
        /*! seedling-validate.js — minimal red messages, no borders */
        (() => {
            "use strict";

            const $ = (id) => document.getElementById(id);
            const q = (sel, root = document) => root.querySelector(sel);
            const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

            // Inject tiny CSS for short errors
            (() => {
                const s = document.createElement("style");
                s.textContent = `.fv-error{color:#d93025;font-size:.9rem;line-height:1.3;margin-top:6px}`;
                document.head.appendChild(s);
            })();

            // Error helpers
            const clrErr = (el) => {
                if (!el) return;
                const sib = el.nextElementSibling;
                if (sib && sib.classList.contains("fv-error")) sib.remove();
            };
            const setErr = (el, msg) => {
                if (!el) return false;
                clrErr(el);
                const d = document.createElement("div");
                d.className = "fv-error";
                d.setAttribute("role", "alert");
                d.textContent = msg;
                el.insertAdjacentElement("afterend", d);
                return false;
            };
            const firstErrFocus = () => {
                const e = q(".fv-error");
                if (!e) return;
                const a = e.previousElementSibling || e;
                try {
                    a.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
                } catch {}
                if (a && a.focus) a.focus({
                    preventScroll: true
                });
            };

            // Utils
            const blank = (v) => !v || !String(v).trim();
            const rep4 = (v) => /(.)\1{3,}/.test(String(v));
            const letters = (v) => /^[A-Za-z][A-Za-z\s'’.()-]*[A-Za-z]$/.test(String(v).trim());
            const isPHMobile = (s) => {
                const v = String(s).replace(/[^\d+]/g, "");
                return !v || /^(\+639|639|09)\d{9}$/.test(v);
            };
            const isCanvasBlank = (c) => {
                try {
                    const ctx = c.getContext("2d");
                    const {
                        width: w,
                        height: h
                    } = c;
                    if (!w || !h) return true;
                    const data = ctx.getImageData(0, 0, w, h).data;
                    for (let i = 3; i < data.length; i += 4) {
                        if (data[i] !== 0) return false;
                    } // any non-transparent pixel
                    return true;
                } catch {
                    return false;
                }
            };

            // Fields
            const el = {
                first: $("firstName"),
                middle: $("middleName"),
                last: $("lastName"),
                contact: $("contactNumber"),
                purpose: $("purpose"),
                barangay: $("barangay"),
                municipality: $("municipality"),
                city: $("city"),
                reqDate: $("requestDate"),
                sigCanvas: $("sigCanvas"),
                seedList: $("seedlingList"),
                addRowBtn: $("addSeedlingBtn"),
                submitBtn: $("submitApplication"),
                confirmBtn: $("confirmSubmitBtn"),
                clientPick: $("clientPick"),
                clientMode: $("clientMode"),
            };

            // Single-field validators (short msgs)
            const vName = (input, required = true) => {
                const v = (input?.value || "").trim();
                clrErr(input);
                if (required && blank(v)) return setErr(input, "Required.");
                if (!required && blank(v)) return true;
                if (v.length < 2) return setErr(input, "Too short.");
                if (!letters(v)) return setErr(input, "Letters only.");
                if (rep4(v)) return setErr(input, "Looks invalid.");
                return true;
            };

            const vContact = (input) => {
                const v = (input?.value || "").trim();
                clrErr(input);
                if (!v) return true; // optional
                if (!isPHMobile(v)) return setErr(input, "Use 09/639 format.");
                return true;
            };

            const vPurpose = (ta) => {
                const v = (ta?.value || "").trim();
                clrErr(ta);
                if (blank(v)) return setErr(ta, "Required.");
                if (v.length < 20) return setErr(ta, "Min 20 chars.");
                return true;
            };

            const vDate = (d) => {
                clrErr(d);
                if (!d?.value) return setErr(d, "Required.");
                return true;
            };

            // Address: choose City XOR Municipality (at least one)
            const vAddress = () => {
                let ok = true;
                clrErr(el.city);
                clrErr(el.municipality);
                const city = (el.city?.value || "").trim();
                const muni = (el.municipality?.value || "").trim();
                if (!city && !muni) ok = setErr(el.city, "Pick City or Municipality.");
                // if (city && muni) ok = setErr(el.city, "Not both.");
                return !!ok;
            };

            const vClientPick = () => {
                const pick = el.clientPick;
                if (!pick) return true;
                const mode = (el.clientMode?.value || "new").toLowerCase();
                clrErr(pick);
                if (mode !== "existing") return true;
                if (blank(pick.value)) return setErr(pick, "Select a client.");
                return true;
            };

            // Seedling rows
            const rowParts = (row) => ({
                sel: row.querySelector("select.seedling-name"),
                qty: row.querySelector(".seedling-qty"),
            });

            const clrRowErrs = (row) => {
                const {
                    sel,
                    qty
                } = rowParts(row);
                clrErr(sel);
                clrErr(qty);
            };

            const setRowErr = (elRef, msg) => setErr(elRef, msg);

            const seedlingsAnyValid = () => {
                const rows = qa(".seedling-row", el.seedList);
                return rows.some(r => {
                    const {
                        sel,
                        qty
                    } = rowParts(r);
                    return sel?.value && Number(qty?.value || 0) > 0;
                });
            };

            const vSeedlings = () => {
                // Clear list-level error (under Add button)
                const anchor = el.addRowBtn || el.seedList;
                clrErr(anchor);

                const rows = qa(".seedling-row", el.seedList);
                let ok = true;

                // per-row checks
                rows.forEach(r => {
                    clrRowErrs(r);
                    const {
                        sel,
                        qty
                    } = rowParts(r);
                    if (!sel || !qty) return;

                    const id = sel.value || "";
                    const have = Number(qty.value || 0);
                    const opt = sel.options[sel.selectedIndex];
                    const stock = Number(opt?.dataset.stock || 0);

                    if (!id && have > 0) ok &= setRowErr(sel, "Select seedling.");
                    if (id && (!have || have < 1)) ok &= setRowErr(qty, "Enter qty.");
                    if (id && have > 0 && stock && have > stock)
                        ok &= setRowErr(qty, `Max ${stock}.`);
                });

                // duplicate selection check
                const seen = new Map();
                rows.forEach(r => {
                    const {
                        sel
                    } = rowParts(r);
                    const id = sel?.value || "";
                    if (!id) return;
                    if (seen.has(id)) {
                        ok &= setRowErr(sel, "Duplicate.");
                    } else {
                        seen.set(id, r);
                    }
                });

                // list-level check
                if (!seedlingsAnyValid()) ok &= setErr(anchor, "Add at least one seedling.");
                return !!ok;
            };

            // Signature
            const vSignature = () => {
                const c = el.sigCanvas;
                if (!c) return true;
                // error anchor is canvas
                clrErr(c);
                // treat empty canvas as no signature
                if (isCanvasBlank(c)) return setErr(c, "Signature needed.");
                return true;
            };

            // Validate-all
            function validateAll() {
                // wipe existing errors
                qa(".fv-error").forEach(n => n.remove());

                let ok = true;
                const mode = (el.clientMode?.value || "new").toLowerCase();
                const isExisting = mode === "existing";

                if (isExisting) {
                    ok &= vClientPick();
                } else {
                    ok &= vName(el.first, true);
                    ok &= vName(el.middle, false);
                    ok &= vName(el.last, true);
                }

                ok &= vContact(el.contact);
                ok &= vPurpose(el.purpose);
                ok &= vAddress();
                ok &= vDate(el.reqDate);
                ok &= vSeedlings();
                ok &= vSignature();

                return !!ok;
            }

            // Live bindings
            function bindLive() {
                // names
                el.first?.addEventListener("input", () => vName(el.first, true));
                el.middle?.addEventListener("input", () => vName(el.middle, false));
                el.last?.addEventListener("input", () => vName(el.last, true));
                // existing-client picker
                el.clientPick?.addEventListener("change", vClientPick);
                // contact
                el.contact?.addEventListener("input", () => vContact(el.contact));
                // purpose
                el.purpose?.addEventListener("input", () => vPurpose(el.purpose));
                // address
                el.barangay?.addEventListener("input", () => {
                    /* optional; no error */
                });
                el.city?.addEventListener("change", vAddress);
                el.municipality?.addEventListener("change", vAddress);
                // date
                el.reqDate?.addEventListener("change", () => vDate(el.reqDate));
                // signature: re-check after pen up / touch end
                if (el.sigCanvas) {
                    ["mouseup", "touchend"].forEach(ev =>
                        el.sigCanvas.addEventListener(ev, vSignature, {
                            passive: true
                        })
                    );
                }
                // seedlings: delegate to list
                if (el.seedList) {
                    el.seedList.addEventListener("change", (e) => {
                        const row = e.target.closest(".seedling-row");
                        if (!row) return;
                        const {
                            sel,
                            qty
                        } = rowParts(row);
                        if (e.target === sel) {
                            clrErr(qty);
                        }
                        vSeedlings();
                    });
                    el.seedList.addEventListener("input", (e) => {
                        if (!e.target.classList.contains("seedling-qty")) return;
                        vSeedlings();
                    });
                    // also when clicking remove buttons, clear list-level error next tick
                    el.seedList.addEventListener("click", (e) => {
                        if (e.target.closest(".remove-row")) {
                            setTimeout(() => vSeedlings(), 0);
                        }
                    });
                }
            }

            // Guard both buttons (open-confirm + final submit)
            function guardSubmits() {
                el.submitBtn?.addEventListener("click", (e) => {
                    if (!validateAll()) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        firstErrFocus();
                    }
                }, true); // capture to run before page handler

                el.confirmBtn?.addEventListener("click", (e) => {
                    if (!validateAll()) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        firstErrFocus();
                    }
                }, true);
            }

            // Boot
            const ready = (fn) => (document.readyState === "loading" ?
                document.addEventListener("DOMContentLoaded", fn) :
                fn());
            ready(() => {
                bindLive();
                guardSubmits();
            });
        })();
    </script>

</body>








</html>