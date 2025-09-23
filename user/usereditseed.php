<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';

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
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/jszip/dist/jszip.min.js"></script>
    <script src="https://unpkg.com/docx-preview/dist/docx-preview.js"></script>

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
        }

        .file-action-btn:hover {
            color: var(--primary-color);
        }

        .form-footer {
            padding: 20px 30px;
            background: var(white);

            display: flex;
            justify-content: flex-end;
            gap: 15px;
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

        /* Edit Mode Styles */
        .edit-mode .requirement-title {
            color: #0a192f;
        }

        .edit-mode .file-input-label {
            background: #0a192f;
        }

        .edit-mode .file-input-label:hover {
            background: #020c1b;
        }

        .name-fields {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            MARGIN-TOP: -1%;
            margin-bottom: 10px;
            padding: 15px;
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

            .notification-detail-container {
                margin-left: 0.1in;
                margin-right: 0.1in;
                margin-top: -27px;
                width: auto;
                padding: 10px;
            }

            .notification-detail-title {
                margin-top: 20px;
            }

            .notification-detail-header {
                padding: 20px;
                font-size: 1.2rem;
                text-align: center;
                display: flex;
                justify-content: flex-end;
                align-items: center;
                flex-direction: column;
            }

            .notification-status {
                margin-top: -20px;
                right: 2px;
            }

            .notification-detail-body {
                padding: 20px;
            }

            .notification-detail-message p {
                font-size: 1rem;
            }

            .meta-item {
                flex-direction: column;
                margin-bottom: 15px;
            }

            .meta-label {
                margin-bottom: 5px;
                font-size: 0.9rem;
            }

            .meta-value {
                font-size: 0.9rem;
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

        .name-field {
            padding: 5px;
            border-radius: 4px;
            background-color: #f9f9f9;
        }

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
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Report Incident</span>
                    </a>

                    <a href="useraddseed.php" class="dropdown-item active-page">
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
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <div class="action-buttons">
            <a href="useraddseed.php" class="btn btn-outline">
                <i class="fas fa-plus-circle"></i> Add
            </a>
            <a href="usereditseed.php" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewseed.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form edit-mode" id="requirementsForm">
            <div class="form-header">
                <h2>Seedlings Request - Edit Requirement</h2>
            </div>

            <div class="form-body">
                <!-- Display Pending Records -->
                <div class="pending-records" style="display: flex; flex-direction: column; gap: 10px;">
                    <?php
                    // Fetch pending records for the current user
                    $userId = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT * FROM seedling_requests WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                    ?>
                            <div class="record-container" style="padding: 3px; background-color: rgba(0, 0, 0, 0.3); border-radius: 5px;" data-id="<?php echo $row['id']; ?>">
                                <div class="name-fields">
                                    <div class="name-field">
                                        <label>First Name:</label>
                                        <span class="view-mode"><?php echo htmlspecialchars($row['first_name']); ?></span>
                                        <input type="text" class="edit-mode" value="<?php echo htmlspecialchars($row['first_name']); ?>" placeholder="First Name" style="display: none;">
                                    </div>
                                    <div class="name-field">
                                        <label>Middle Name:</label>
                                        <span class="view-mode"><?php echo htmlspecialchars($row['middle_name']); ?></span>
                                        <input type="text" class="edit-mode" value="<?php echo htmlspecialchars($row['middle_name']); ?>" placeholder="Middle Name" style="display: none;">
                                    </div>
                                    <div class="name-field">
                                        <label>Last Name:</label>
                                        <span class="view-mode"><?php echo htmlspecialchars($row['last_name']); ?></span>
                                        <input type="text" class="edit-mode" value="<?php echo htmlspecialchars($row['last_name']); ?>" placeholder="Last Name" style="display: none;">
                                    </div>
                                    <div class="name-field">
                                        <label>Seedling Name:</label>
                                        <span class="view-mode"><?php echo htmlspecialchars($row['seedling_name']); ?></span>
                                        <input type="text" class="edit-mode" value="<?php echo htmlspecialchars($row['seedling_name']); ?>" placeholder="Seedling Name" style="display: none;">
                                    </div>
                                    <div class="name-field">
                                        <label>Quantity:</label>
                                        <span class="view-mode"><?php echo htmlspecialchars($row['quantity']); ?></span>
                                        <input type="number" class="edit-mode" value="<?php echo htmlspecialchars($row['quantity']); ?>" placeholder="Quantity" style="display: none;">
                                    </div>
                                </div>
                                <div class="file-display" style="padding: 0px 20px;">
                                    <label>Request Letter</label>
                                    <div class="file-item">
                                        <div class="file-info" style="display: flex; justify-content: space-between; width: 100%;">
                                            <?php
                                            $fileExt = pathinfo($row['request_letter'], PATHINFO_EXTENSION);
                                            $iconClass = 'fa-file';
                                            if ($fileExt === 'pdf') {
                                                $iconClass = 'fa-file-pdf';
                                            } elseif (in_array($fileExt, ['doc', 'docx'])) {
                                                $iconClass = 'fa-file-word';
                                            } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                                                $iconClass = 'fa-file-image';
                                            }
                                            ?>
                                            <div class="view-mode">
                                                <i class="fas <?php echo $iconClass; ?> file-icon"></i>
                                                <a href="#" onclick="previewFile('<?php echo htmlspecialchars($row['request_letter']); ?>', '<?php echo $fileExt; ?>'); return false;" class="file-link" style="text-decoration: none; color: black;">
                                                    <?php echo htmlspecialchars($row['request_letter']); ?>
                                                </a>
                                            </div>
                                            <div class="edit-mode" style="display: none; align-items: center; gap: 10px;">
                                                <input type="file" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                <span class="new-file-name" style="display: none;"></span>
                                            </div>
                                            <div class="record-header view-mode">
                                                <button class="edit-btn" onclick="toggleEditMode(this)" style="padding: 5px 15px; background-color: #006622; border: none; border-radius: 7px; color: white;">Edit</button>
                                            </div>
                                            <div class="record-actions edit-mode" style="display: none; gap: 3px;">
                                                <button class="btn-primary save-btn" onclick="handleUpdateClick(this)" style="padding: 5px 11px; font-weight: 100; background-color: #006622; border: none; border-radius: 7px; color: white;">Update</button>
                                                <button class="btn-outline cancel-btn" style="padding: 5px 11px; font-weight: 100; background-color: #888; border: none; border-radius: 7px; color: white;" onclick="cancelEdit(this)">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        echo '<p>No pending records found.</p>';
                    }
                    $stmt->close();
                    ?>
                </div>
            </div>
        </div>

        <!-- File Preview Modal -->
        <div id="filePreviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:9999;">
            <div style="background:#fff; padding:20px; border-radius:10px; width:80%; height:90%; position:relative;">
                <button onclick="closeModal()" style="position:absolute; top:10px; right:10px;">❌</button>
                <div id="filePreviewContent" style="width:100%; height:100%; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmModal" class="modal">
            <div class="modal-content" style="max-width:400px;text-align:center;">
                <span id="closeConfirmModal" class="close-modal">&times;</span>
                <h3>Confirm Update</h3>
                <p>Are you sure you want to update this seedling request?</p>
                <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Update</button>
                <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
            </div>
        </div>
        <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Mobile menu toggle
                const mobileToggle = document.querySelector('.mobile-toggle');
                const navContainer = document.querySelector('.nav-container');

                if (mobileToggle) {
                    mobileToggle.addEventListener('click', () => {
                        const isActive = navContainer.classList.toggle('active');
                        document.body.style.overflow = isActive ? 'hidden' : '';
                    });
                }

                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.nav-container') && !e.target.closest('.mobile-toggle')) {
                        navContainer.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });

                // File input change handler
                document.querySelectorAll('.file-input').forEach(input => {
                    input.addEventListener('change', function() {
                        const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                        this.parentElement.querySelector('.file-name').textContent = fileName;
                    });
                });

                // File delete handler
                document.querySelectorAll('.file-action-btn .fa-trash').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const fileItem = this.closest('.file-item');
                        fileItem.remove();
                    });
                });

                // Save changes
                var saveBtn = document.getElementById('saveBtn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', () => {
                        // Here you would typically send the updated files to the server
                        alert('Changes saved successfully!');
                        window.location.href = 'wfp_view.php';
                    });
                }
            });

            function toggleEditMode(btn) {
                const recordContainer = btn.closest('.record-container');

                // Hide all "view-mode" elements
                recordContainer.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');

                // Show all "edit-mode" elements
                recordContainer.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'flex');

                // Make sure file input is visible in edit mode
                recordContainer.querySelectorAll('.edit-mode .file-input').forEach(input => {
                    input.style.display = 'block';
                });
            }

            function triggerFileUpload(btn) {
                // Find the closest .edit-mode container
                const editModeContainer = btn.closest('.edit-mode');
                if (!editModeContainer) return;
                // Find the file input inside this container
                const fileInput = editModeContainer.querySelector('.file-input');
                if (fileInput) {
                    fileInput.click();
                }
            }

            // When a new file is chosen, show its name
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('file-input')) {
                    const fileNameSpan = e.target.closest('.record-container').querySelector('.new-file-name');
                    if (e.target.files.length > 0) {
                        fileNameSpan.textContent = e.target.files[0].name;
                        fileNameSpan.style.display = 'inline';
                    } else {
                        fileNameSpan.style.display = 'none';
                    }
                }
            });


            function previewFile(fileName, ext) {
                const filePath = `../upload/user/requestseed/${fileName}`;
                const modal = document.getElementById('filePreviewModal');
                const content = document.getElementById('filePreviewContent');

                fetch(filePath, {
                        method: 'HEAD'
                    })
                    .then(res => {
                        if (!res.ok) {
                            alert('File not found!');
                            return;
                        }

                        content.innerHTML = ''; // Clear previous preview
                        ext = ext.toLowerCase();

                        if (ext === 'pdf') {
                            content.innerHTML = `<embed src="${filePath}" type="application/pdf" width="100%" height="100%">`;
                        } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                            content.innerHTML = `<img src="${filePath}" alt="Preview" style="max-width:100%; max-height:100%;">`;
                        } else if (ext === 'docx') {
                            const container = document.createElement('div');
                            content.appendChild(container);
                            fetch(filePath)
                                .then(r => r.arrayBuffer())
                                .then(buffer => {
                                    window.docx.renderAsync(buffer, container)
                                        .catch(err => {
                                            console.error(err);
                                            container.innerHTML = '<p>Error loading document.</p>';
                                        });
                                })
                                .catch(err => {
                                    console.error(err);
                                    alert('Error loading DOCX file.');
                                });
                        } else {
                            content.innerHTML = `<p>Preview not supported. <a href="${filePath}" target="_blank">Download File</a></p>`;
                        }

                        modal.style.display = 'flex';
                    })
                    .catch(() => alert('Error loading file.'));
            }

            function cancelEdit(btn) {
                const recordContainer = btn.closest('.record-container');

                // Show all "view-mode" elements
                recordContainer.querySelectorAll('.view-mode').forEach(el => el.style.display = '');

                // Hide all "edit-mode" elements
                recordContainer.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'none');

                // Reset any file input changes
                const fileInput = recordContainer.querySelector('.file-input');
                if (fileInput) {
                    fileInput.value = ''; // Clear the file selection
                }

                // Hide any displayed new file name
                const fileNameSpan = recordContainer.querySelector('.new-file-name');
                if (fileNameSpan) {
                    fileNameSpan.style.display = 'none';
                    fileNameSpan.textContent = '';
                }
            }


            function closeModal() {
                document.getElementById('filePreviewModal').style.display = 'none';
                document.getElementById('filePreviewContent').innerHTML = '';
            }

            let currentUpdateBtn = null;

            function handleUpdateClick(btn) {
                currentUpdateBtn = btn;
                document.getElementById('confirmModal').style.display = 'block';
            }

            document.getElementById('closeConfirmModal').addEventListener('click', () => {
                document.getElementById('confirmModal').style.display = 'none';
            });
            document.getElementById('cancelSubmitBtn').addEventListener('click', () => {
                document.getElementById('confirmModal').style.display = 'none';
            });

            document.getElementById('confirmSubmitBtn').addEventListener('click', () => {
                if (!currentUpdateBtn) return;

                const recordContainer = currentUpdateBtn.closest('.record-container');
                const formData = new FormData();

                // Add record ID
                formData.append('id', recordContainer.dataset.id);

                // Get all input values
                const firstNameInput = recordContainer.querySelector('.name-field:nth-child(1) input.edit-mode');
                const middleNameInput = recordContainer.querySelector('.name-field:nth-child(2) input.edit-mode');
                const lastNameInput = recordContainer.querySelector('.name-field:nth-child(3) input.edit-mode');
                const seedlingNameInput = recordContainer.querySelector('.name-field:nth-child(4) input.edit-mode');
                const quantityInput = recordContainer.querySelector('.name-field:nth-child(5) input.edit-mode');

                // Add to formData if changed
                if (firstNameInput) formData.append('first_name', firstNameInput.value);
                if (middleNameInput) formData.append('middle_name', middleNameInput.value);
                if (lastNameInput) formData.append('last_name', lastNameInput.value);
                if (seedlingNameInput) formData.append('seedling_name', seedlingNameInput.value);
                if (quantityInput) formData.append('quantity', quantityInput.value);

                // Handle file upload
                const fileInput = recordContainer.querySelector('.file-input');
                if (fileInput && fileInput.files.length > 0) {
                    formData.append('request_letter', fileInput.files[0]);
                }

                fetch('../backend/users/editseed.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('confirmModal').style.display = 'none';

                        if (data.success) {
                            showNotification('✅ ' + data.message);
                            // Refresh the page to show updated data
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification('❌ ' + (data.errors ? data.errors.join(', ') : 'Update failed.'));
                        }
                    })
                    .catch(err => {
                        document.getElementById('confirmModal').style.display = 'none';
                        showNotification('❌ An error occurred while updating.');
                        console.error(err);
                    });
            });

            function showNotification(message) {
                const notification = document.getElementById('profile-notification');
                notification.textContent = message;
                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 3000);
            }
        </script>
</body>

</html>