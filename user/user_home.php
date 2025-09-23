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
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="/assets/css/notifications.css">
    <script src="/assets/js/notifications.js" defer></script>

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

        .logo::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--white);
            border-radius: 1px;
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

        /* Main Content Styles */
        .main-content {
            padding: 90px 40px 40px 40px;
            max-width: 1200px;
            margin: 0 auto;
            margin-top: -8%;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Content Header with Edit and Save Buttons */
        .content-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .content-header h1 {
            text-align: center;
            margin-top: -1px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 28px;
            margin-bottom: 30px;
            width: 100%;
        }

        .edit-actions {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            gap: 10px;
        }

        /* Cards */
        .program-highlight,
        .component-section {
            background: var(--white);
            margin-top: -2%;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .program-highlight:hover,
        .component-section:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-3px);
        }

        .program-highlight {
            background: var(--primary-light);
            border-left: 4px solid var(--primary-color);
        }

        h2,
        h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
        }

        h2 {
            font-size: 22px;
        }

        h3 {
            font-size: 18px;
        }

        p {
            color: var(--text-medium);
            margin-bottom: 20px;
        }

        /* Performance Metrics */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .metric-card {
            background: var(--white);
            padding: 20px;
            text-align: center;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            transition: var(--transition);
            border-top: 4px solid var(--accent-color);
            border: none;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .metric-card div:last-child {
            color: var(--text-light);
            font-size: 15px;
            font-weight: 500;
        }

        /* Enhanced Chart Container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin: 20px 0;
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .chart-legend-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-bottom: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 8px;
        }

        .chart-footer {
            text-align: center;
            font-size: 14px;
            color: #555;
            padding: 10px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }

        /* Enhanced Data Table */
        .data-table-container {
            overflow-x: auto;
            margin: 25px 0;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            min-width: 600px;
        }

        .data-table thead tr {
            background-color: var(--primary-color);
            color: #ffffff;
            text-align: left;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
        }

        .data-table th {
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }

        .data-table tbody tr:nth-of-type(even) {
            background-color: #f8f9fa;
        }

        .data-table tbody tr:last-of-type {
            border-bottom: 2px solid var(--primary-color);
        }

        .data-table tbody tr:hover {
            background-color: #f1f1f1;
        }

        /* Status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            min-width: 100px;
        }

        .status-active {
            background-color: #e6f7e6;
            color: #2b6625;
            border: 1px solid #c8e6c9;
        }

        .status-pending {
            background-color: #fff8e6;
            color: #cc8a00;
            border: 1px solid #ffe0b2;
        }

        .status-exceeded {
            background-color: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-edit {
            background-color: var(--primary-color);
            color: white;
            transition: var(--transition);
        }

        .btn-edit:hover {
            background-color: var(--primary-dark);
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        /* Two-column layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }

        /* Enhanced Habitat Assessment Section */
        .habitat-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .habitat-feature {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 1px solid darkgreen;
        }

        .habitat-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .habitat-feature h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
        }

        .habitat-feature h4 i {
            margin-right: 10px;
            color: var(--primary-dark);
        }

        .habitat-feature p {
            font-size: 14px;
            color: #555;
            margin-bottom: 0;
        }

        /* Enhanced Ways Forward Section */
        .roadmap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .roadmap-item {
            border: 1px solid #1e4a1a;
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .roadmap-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .roadmap-item h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
        }

        .roadmap-item h4 i {
            margin-right: 10px;
            color: var(--primary-dark);
        }

        /* Stat Blocks */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .stat-block {
            background: var(--light-gray);
            padding: 15px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-dark);
            transition: var(--transition);
        }

        .stat-block:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-block .stat-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .stat-block .stat-title i {
            margin-right: 8px;
        }

        .stat-block .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-block .stat-description {
            font-size: 14px;
            color: var(--text-light);
        }

        /* Filter Container Styles */
        .filter-container {
            display: flex;
            justify-content: flex-start;
            margin: 20px 0 30px;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            background-color: var(--white);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
            font-size: 14px;
        }

        .filter-btn:hover {
            background-color: rgba(43, 102, 37, 0.1);
        }

        .filter-btn i {
            font-size: 12px;
        }

        .filter-content {
            display: none;
            position: absolute;
            background-color: var(--white);
            min-width: 200px;
            box-shadow: var(--box-shadow);
            z-index: 1;
            border-radius: var(--border-radius);
            overflow: hidden;
            top: 100%;
            left: 0;
        }

        .filter-dropdown:hover .filter-content {
            display: block;
        }

        .filter-item {
            color: var(--primary-dark);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: var(--transition);
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .filter-item:hover {
            background-color: rgba(43, 102, 37, 0.1);
            padding-left: 20px;
        }

        .filter-item.active {
            background-color: rgba(43, 102, 37, 0.2);
            font-weight: 600;
        }

        .apply-filter-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
            font-size: 14px;
            white-space: nowrap;
        }

        .apply-filter-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .two-column {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .main-nav {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 90px 20px 20px 20px;
            }

            .stat-grid {
                grid-template-columns: 1fr;
            }

            .header-left .logo img {
                height: 32px;
            }

            .chart-container {
                height: 300px;
            }

            .edit-actions {
                position: static;
                justify-content: center;
                margin-bottom: 20px;
            }

            .filter-container {
                margin-top: -10%;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 10px;
            }

            .filter-group {
                width: auto;
                justify-content: flex-start;
            }

            .filter-btn,
            .apply-filter-btn {
                padding: 8px 12px;
                font-size: 13px;
            }

            .filter-btn i,
            .apply-filter-btn i {
                font-size: 11px;
            }

            .filter-content {
                min-width: 180px;
            }
        }

        /* Copy of inline styles for section titles */
        .inline-style-copy {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-weight: 700;
            font-size: 24px;
            color: var(--primary-dark);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .habitat-map {
            width: 100%;
            max-width: 600px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .habitat-section {
            border: 1px solid darkgreen;
            border-left: none !important;
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
                <div class="nav-icon">
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
                    <a href="useraddtreecut.php" class="dropdown-item">
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
                </div>
            </div>


            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-item unread">
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="user_notification.php" class="view-all">View All Notifications</a>
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
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <!-- Filter dropdown container above the title -->
        <div class="filter-container">
            <div class="filter-group">
                <div class="filter-dropdown">
                    <button class="filter-btn">
                        <i class="fas fa-chart-pie"></i> Filter by Category
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-content">
                        <a href="user_home.php" class="filter-item active">All Categories</a>
                        <a href="mpa-management.php" class="filter-item">MPA Management</a>
                        <a href="habitat.php" class="filter-item">Habitat Assessment</a>
                        <a href="species.php" class="filter-item">Species Monitoring</a>
                        <a href="reports.php" class="filter-item">Reports & Analytics</a>

                    </div>
                </div>

                <button class="apply-filter-btn">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </div>

        <!-- Modified content header section with Edit and Save buttons -->
        <div class="content-header">
            <div class="edit-actions">

            </div>
            <h1>Coastal and Marine Ecosystems Management Program</h1>
        </div>

        <div class="program-highlight">
            <div class="content-header">
                <h2>Program Overview</h2>
            </div>
            <p>Implemented since 2017 under DAO 2016-26, CMEMP focuses on restoring coastal ecosystems through science-based approaches. Key 2020 accomplishments include:</p>
            <div class="performance-grid">
                <div class="metric-card">
                    <div class="metric-value">125%</div>
                    <div>MPA Management Efficiency</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">103%</div>
                    <div>Habitat Monitoring Coverage</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">114</div>
                    <div>BDFEs Supported</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">37.7M</div>
                    <div>Financial Assistance (PHP)</div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <a href="#" class="btn">
                    <i class="fas fa-download"></i> Download Full Report
                </a>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">MPA Management & Networking</h3>
            </div>

            <div class="figure-container">
                <div class="figure-title">Regional Performance on MPA Management</div>

                <!-- Chart container -->
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                    <div class="chart-legend-container">
                        <div class="chart-legend" id="chartLegend"></div>

                    </div>
                </div>
            </div>

        </div>

        <div class="component-section habitat-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Habitat Assessment & Monitoring</h3>
            </div>
            <div class="two-column">
                <div>
                    <div class="habitat-features">
                        <div class="habitat-feature">
                            <h4><i class="fas fa-ruler-combined"></i> Coastal Habitats Assessed</h4>
                            <p>7,658.79 hectares of coastal habitats comprehensively assessed using advanced GIS mapping techniques.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-tint"></i> Water Quality Monitoring</h4>
                            <p>100% of protected areas now have regular water quality monitoring with quarterly reports.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-map-marked-alt"></i> Mangrove Mapping</h4>
                            <p>28-hectare mangrove area in Cavite digitally mapped with species distribution analysis.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-coral"></i> Coral Reef Monitoring</h4>
                            <p>15 new coral reef sites established with baseline data collection and health indicators.</p>
                        </div>
                    </div>
                </div>
                <div class="figure-container">
                    <div class="figure-title">Mangrove Area in Noveleta, Cavite</div>
                    <img src="mangrove-map.png" alt="Mangrove Map" class="habitat-map">
                    <div class="chart-footer">
                        Latest survey conducted March 2023 showing 92% healthy mangrove coverage
                    </div>
                </div>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Capacity Building & Partnerships</h3>
            </div>
            <div class="stat-grid">
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-users"></i> Personnel Trained
                    </div>
                    <div class="stat-value">70</div>
                    <div class="stat-description">DENR personnel via ODL</div>
                </div>
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-user-tie"></i> Extension Officers
                    </div>
                    <div class="stat-value">63</div>
                    <div class="stat-description">CMEMP Officers hired</div>
                </div>
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-hand-holding-usd"></i> Financial Assistance
                    </div>
                    <div class="stat-value">37.7M</div>
                    <div class="stat-description">PHP to POs</div>
                </div>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Strategic Roadmap: Ways Forward</h3>
            </div>
            <div class="roadmap">
                <div class="roadmap-item">
                    <h4><i class="fas fa-balance-scale"></i> Policy Development</h4>
                    <p>Strengthening MPAN institutionalization through comprehensive policy frameworks and legal instruments.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-flask"></i> Advanced Monitoring</h4>
                    <p>Enhancing water quality monitoring with IoT sensors and real-time data analytics platforms.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-chart-line"></i> Economic Valuation</h4>
                    <p>Developing models to quantify ecosystem services and their economic impact on coastal communities.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-map"></i> Verde Island Passage</h4>
                    <p>Expanding management initiatives in this biodiversity hotspot with international partnerships.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-users-cog"></i> Community Systems</h4>
                    <p>Implementing participatory monitoring systems with fisherfolk cooperatives and local governments.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-robot"></i> Technology Integration</h4>
                    <p>Deploying AI-powered monitoring tools and drone surveillance for illegal fishing detection.</p>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }

            // Improved dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');

            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');

                // Show menu on hover
                dropdown.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(0)' :
                        'translateY(0)';
                });

                // Hide menu when leaving both button and menu
                dropdown.addEventListener('mouseleave', (e) => {
                    // Check if we're leaving the entire dropdown area
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });

                // Additional check for menu mouseleave
                menu.addEventListener('mouseleave', (e) => {
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });
            });

            // Close dropdowns when clicking outside (for mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    });
                }
            });

            // Mobile dropdown toggle
            if (window.innerWidth <= 992) {
                dropdowns.forEach(dropdown => {
                    const toggle = dropdown.querySelector('.nav-icon');
                    const menu = dropdown.querySelector('.dropdown-menu');

                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        // Close other dropdowns
                        document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                            if (otherMenu !== menu) {
                                otherMenu.style.display = 'none';
                            }
                        });

                        // Toggle current dropdown
                        if (menu.style.display === 'block') {
                            menu.style.display = 'none';
                        } else {
                            menu.style.display = 'block';
                        }
                    });
                });
            }

            // Mark all notifications as read
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelector('.badge').style.display = 'none';
                });
            }

            // Create the performance chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['PAs assessed', 'PAs monitored', 'PAs water quality', 'MPA Network', 'Habitats monitored'],
                    datasets: [{
                            label: 'Target',
                            data: [100, 100, 100, 100, 100],
                            backgroundColor: 'rgba(169, 169, 169, 0.7)',
                            borderColor: 'rgba(169, 169, 169, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Actual',
                            data: [12, 100, 100, 100, 103],
                            backgroundColor: 'rgba(43, 102, 37, 0.7)',
                            borderColor: 'rgba(43, 102, 37, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 120,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    }
                }
            });

            // Custom legend
            const legendItems = performanceChart.data.datasets.map((dataset, i) => {
                return {
                    label: dataset.label,
                    backgroundColor: dataset.backgroundColor,
                    borderColor: dataset.borderColor
                };
            });

            const legendContainer = document.getElementById('chartLegend');
            legendItems.forEach(item => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';

                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = item.backgroundColor;
                colorBox.style.border = `1px solid ${item.borderColor}`;

                const text = document.createElement('span');
                text.textContent = item.label;

                legendItem.appendChild(colorBox);
                legendItem.appendChild(text);
                legendContainer.appendChild(legendItem);
            });

            // Filter button functionality
            const applyFilterBtn = document.querySelector('.apply-filter-btn');
            if (applyFilterBtn) {
                applyFilterBtn.addEventListener('click', function() {
                    // Here you would implement your filter functionality
                    alert('Filters applied. Implement your filter functionality here.');
                });
            }

            // Edit button functionality
            const editBtn = document.querySelector('.btn-edit');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    // Here you would implement your edit functionality
                    alert('Edit mode activated. Implement your edit functionality here.');
                    // Example: Enable form fields, show editable content, etc.
                });
            }
        });
    </script>
</body>

</html>