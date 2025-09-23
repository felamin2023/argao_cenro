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
        // Invalidate session if it no longer matches a real verified User
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9 url('images/seedlings.jpg')center / cover no-repeat fixed;
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
            -moz-appearance: textfield;
            /* Firefox */
        }

        /* Row layout stays tidy on small screens */
        .seedling-row {
            align-items: center;
        }
    </style>
</head>

<body>
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
            <div class="nav-icon active"><i class="fas fa-bars"></i></div>
            <div class="dropdown-menu center">
                <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                <a href="useraddchainsaw.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
            </div>
            </div>

            <!-- Notifications (from partial) -->
            <?php include dirname(__DIR__) . '/backend/partials/notifications.php'; ?>

            <!-- Profile Dropdown -->
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
                <!-- Basic info -->
                <div class="name-fields">
                    <div class="name-field">
                        <input type="text" placeholder="First Name" id="firstName" required>
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Middle Name" id="middleName">
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Last Name" id="lastName" required>
                    </div>
                </div>

                <!-- Purpose -->
                <div style="width: 100%; display: flex; flex-direction: column;">
                    <label style="font-weight:600">Purpose of the Request</label>
                    <textarea id="purpose" placeholder="e.g., community tree-planting along the barangay road..." style="width:100%;height:80px"></textarea>
                </div>

                <!-- Address + org + date -->
                <div style=" width: 100%; display:grid;grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:14px">
                    <input type="text" id="organization" placeholder="Organization (optional)" style="width:100%;">
                    <input type="text" id="sitioStreet" placeholder="Sitio / Street">

                    <input list="barangayList" id="barangay" placeholder="Barangay">
                    <datalist id="barangayList">
                        <option value="Guadalupe">
                        <option value="Lahug">
                        <option value="Mabolo">
                        <option value="Labangon">
                        <option value="Talamban">
                    </datalist>

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

                    <input type="date" id="requestDate" required style="width:100%; height:40px; box-sizing:border-box;">
                </div>
                <small style="color:#666;display:block;margin-top:6px;">
                    Tip: choose either a City <em>or</em> a Municipality. Province is assumed as Cebu.
                </small>

                <!-- Seedlings dynamic list -->
                <div style="margin-top:18px">
                    <label style="font-weight:600;display:block;margin-bottom:8px">Seedlings Requested (add more rows as needed)</label>
                    <div id="seedlingList" style="display:flex;flex-direction:column;gap:10px"></div>
                    <button type="button" id="addSeedlingBtn" class="btn btn-outline" style="margin-top:6px">
                        <i class="fas fa-plus-circle"></i> Add another seedling
                    </button>
                </div>

                <!-- Generate letter -->
                <!-- <div class="sample-letter-btn" style="margin-top:18px">
                    <button class="btn btn-primary" id="generateLetter">
                        <i class="fas fa-file-word"></i> Generate Letter (DOC)
                    </button>
                </div> -->

                <!-- Signature pad -->
                <div class="sig-wrap" style="margin-top:18px">
                    <label style="font-weight:600;display:block;margin-bottom:6px">Signature (draw inside the box)</label>
                    <canvas id="sigCanvas" style="width:400px;height:150px;border:2px dashed #9aa;border-radius:8px;background:#fff;touch-action:none"></canvas>
                    <div class="sig-actions" style="display:flex;gap:8px;margin-top:8px">
                        <button type="button" id="sigClear" class="btn btn-outline">Clear</button>
                        <button type="button" id="sigUndo" class="btn btn-outline">Undo</button>
                    </div>
                </div>

                <!-- (Optional) Requirements box removed for brevity -->
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
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

    <!-- Sample Letter Content (EXACT FORMAT USED) -->
    <div id="sampleLetterContent" style="display:none;">
        <p style="text-align: right;">[Your Address]<br>[City, Province]<br>[Date]</p>

        <p style="text-align: left; margin-top: 30px;">
            <strong>CENRO Argao</strong><br>
        </p>

        <p style="margin-top: 30px;"><strong>Subject: Request for Seedlings</strong></p>

        <p style="margin-top: 20px; text-align: justify;">
            Dear Sir/Madam,
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            I am writing to formally request [number] seedlings of [seedling name/species] for [purpose - e.g., reforestation project, backyard planting, etc.]. The seedlings will be planted at [location/address where seedlings will be planted].
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            The purpose of this request is [explain purpose in more detail]. This initiative is part of [explain any project or personal initiative if applicable].
        </p>

        <p style="text-align: justify; text-indent: 50px;">
            I would be grateful if you could approve this request at your earliest convenience. Please let me know if you require any additional information or documentation to process this request.
        </p>

        <p style="margin-top: 30px;">
            Thank you for your time and consideration.
        </p>

        <p style="margin-top: 50px;">
            Sincerely,<br><br>
            _________________________<br>
            [Your Full Name]<br>
            [Your Contact Information]<br>
            [Your Organization, if applicable]
        </p>
    </div>

    <!-- SignaturePad CDN -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* ───────── Toast (replaces all alert()) ───────── */
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

                // Slight dark green for success
                noteEl.style.background =
                    type === 'error' ? '#c0392b' :
                    type === 'success' ? '#2d8a34' : '#323232';

                if (html) noteEl.innerHTML = message;
                else noteEl.textContent = message;

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

            /* ───────── Mobile menu ───────── */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });
            }

            /* ───────── Helpers ───────── */
            function escapeHTML(str) {
                return String(str ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function dataURLtoBase64(dataURL) {
                return (dataURL.split(',')[1] || '').replace(/\s/g, '');
            }

            /* ───────── Signature Pad ───────── */
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
                const data = sigPad.toData();
                if (data.length) {
                    data.pop();
                    sigPad.fromData(data);
                }
            });

            /* ───────── Seedlings catalog (from backend) ───────── */
            const seedlingList = document.getElementById('seedlingList');
            const addSeedlingBtn = document.getElementById('addSeedlingBtn');
            let seedlingsCatalog = []; // [{seedlings_id, seedling_name, stock}, ...]

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
                sel.className = 'seedling-name';
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

            // 🔒 Enforced quantity limiter with per-keystroke feedback
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
                    const name = opt?.dataset.name || '';
                    const stock = Number(opt?.dataset.stock || 0);

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
                    const name = opt?.dataset.name || '';
                    const stock = Number(opt?.dataset.stock || 0);

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

                // Optional: prevent accidental scroll changing the number
                qty.addEventListener('wheel', (e) => e.target.blur(), {
                    passive: true
                });

                sel.addEventListener('change', () => syncQtyMeta(true));
                qty.addEventListener('input', enforceMaxOnType);
                syncQtyMeta(); // initialize

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

            // Load catalog now
            loadSeedlings();

            /* ───────── Submit flow (CONFIRM → POST) ───────── */
            const confirmModal = document.getElementById('confirmModal');
            document.getElementById('closeConfirmModal').addEventListener('click', () => confirmModal.style.display = 'none');
            document.getElementById('cancelSubmitBtn').addEventListener('click', () => confirmModal.style.display = 'none');

            document.getElementById('submitApplication').addEventListener('click', (e) => {
                e.preventDefault();
                const firstName = document.getElementById('firstName').value.trim();
                const lastName = document.getElementById('lastName').value.trim();
                const purpose = document.getElementById('purpose').value.trim();
                const reqDate = document.getElementById('requestDate').value;

                if (!firstName || !lastName) {
                    toast('First name and last name are required.', {
                        type: 'error'
                    });
                    return;
                }
                if (!purpose) {
                    toast('Please enter the purpose of your request.', {
                        type: 'error'
                    });
                    return;
                }
                if (!reqDate) {
                    toast('Please choose the date of request.', {
                        type: 'error'
                    });
                    return;
                }
                if (sigPad.isEmpty()) {
                    toast('Please provide your signature.', {
                        type: 'error'
                    });
                    return;
                }

                const hasSeedling = Array.from(document.querySelectorAll('.seedling-row')).some(row => {
                    const sel = row.querySelector('select.seedling-name');
                    const qty = row.querySelector('.seedling-qty');
                    return sel?.value && Number(qty?.value || 0) > 0;
                });
                if (!hasSeedling) {
                    toast('Add at least one seedling with a valid quantity.', {
                        type: 'error'
                    });
                    return;
                }

                confirmModal.style.display = 'block';
            });

            document.getElementById('confirmSubmitBtn').addEventListener('click', async () => {
            confirmModal.style.display = 'none';

            const submitBtn = document.getElementById('submitApplication');
            submitBtn.disabled = true;

            const payload = {
                first_name: document.getElementById('firstName').value.trim(),
                middle_name: document.getElementById('middleName').value.trim(),
                last_name: document.getElementById('lastName').value.trim(),
                organization: document.getElementById('organization').value.trim(),
                purpose: document.getElementById('purpose').value.trim(),
                sitio_street: document.getElementById('sitioStreet').value.trim(),
                barangay: document.getElementById('barangay').value.trim(),
                municipality: document.getElementById('municipality').value.trim(),
                city: document.getElementById('city').value.trim(),
                request_date: document.getElementById('requestDate').value,
                signature_b64: sigPad.toDataURL('image/png'),
                seedlings: Array.from(document.querySelectorAll('.seedling-row'))
                .map(row => {
                    const sel = row.querySelector('select.seedling-name');
                    const qtyEl = row.querySelector('.seedling-qty');
                    return { seedlings_id: sel?.value || '', qty: Number(qtyEl?.value || 0) };
                })
                .filter(s => s.seedlings_id && s.qty > 0),
            };

            try {
                const res = await fetch('../backend/users/seedlings/request_seedlings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
                });

                const text = await res.text();
                let data;
                try {
                data = JSON.parse(text);
                } catch {
                // Backend returned HTML (PHP error). Surface it.
                throw new Error(text.replace(/<[^>]*>/g, ' ').trim().slice(0, 500));
                }

                console.log('Seedlings submit →', res.status, data);

                if (!res.ok || !data?.success) {
                // Show backend error and DO NOT clear form
                throw new Error(data?.error || `HTTP ${res.status}`);
                }

                // ✅ Success — now we reset
                toast('Request submitted successfully.', { type: 'success', timeout: 4000 });

                document.getElementById('firstName').value = '';
                document.getElementById('middleName').value = '';
                document.getElementById('lastName').value = '';
                document.getElementById('organization').value = '';
                document.getElementById('purpose').value = '';
                document.getElementById('sitioStreet').value = '';
                document.getElementById('barangay').value = '';
                document.getElementById('municipality').value = '';
                document.getElementById('city').value = '';
                sigPad.clear();
                seedlingList.innerHTML = '';
                addSeedlingRow();

                // (Optional) Open or preview the generated file
                // if (data.signed_url_preview || data.application_form_url) {
                //   window.open(data.signed_url_preview || data.application_form_url, '_blank');
                // }

            } catch (err) {
                console.error(err);
                toast('Submission failed: ' + (err?.message || err), { type: 'error', timeout: 7000 });
            } finally {
                submitBtn.disabled = false;
            }
            });


            /* ───────── Default date = today ───────── */
            const requestDate = document.getElementById('requestDate');
            if (requestDate && !requestDate.value) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                requestDate.value = `${yyyy}-${mm}-${dd}`;
            }

            /* ───────── Demo: Add button (optional) ───────── */
            const addFilesBtn = document.getElementById('addFilesBtn');
            if (addFilesBtn) addFilesBtn.addEventListener('click', () => {
                toast('In a real app, this opens a multi-file picker.');
            });
        });
    </script>




</body>




</html>