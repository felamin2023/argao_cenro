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
    <title>Wildlife permit</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

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
            display: flex;
            flex-wrap: wrap;
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

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input {
            width: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
            font-weight: 600;
        }

        /* Wider table columns */
        table th:nth-child(1),
        table td:nth-child(1) {
            width: 35%;
        }

        table th:nth-child(2),
        table td:nth-child(2) {
            width: 35%;
        }

        table th:nth-child(3),
        table td:nth-child(3) {
            width: 20%;
        }

        table th:nth-child(4),
        table td:nth-child(4) {
            width: 10%;
        }

        .table-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .add-row-btn {
            background-color: #2b6625;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .remove-row-btn {
            background-color: #ff4757;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
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

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }

            .signature-date {
                flex-direction: column;
                gap: 20px;
            }

            .declaration-input {
                width: 100%;
                margin: 5px 0;
            }

            /* Adjust table for mobile */
            table {
                display: block;
                overflow-x: auto;
            }

            table th:nth-child(1),
            table td:nth-child(1),
            table th:nth-child(2),
            table td:nth-child(2),
            table th:nth-child(3),
            table td:nth-child(3),
            table th:nth-child(4),
            table td:nth-child(4) {
                width: auto;
                min-width: 120px;
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
            .add-row-btn,
            .remove-row-btn,
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
                    <a href="useraddseed.php" class="dropdown-item ">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item active-page">
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
                                <div class="notification-title">Seedling Request Status</div>
                                <div class="notification-message">Your seedling request has been approved.</div>
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
        <div class="requirements-form">
            <div class="form-header">
                <h2>Wildlife Registration Permit - Requirements</h2>
            </div>

            <div class="form-body">

                <!-- Permit Type Selector -->
                <div class="permit-type-selector">
                    <button type="button" class="permit-type-btn active" data-type="new">New permit</button>
                    <button type="button" class="permit-type-btn" data-type="renewal">Renewal permit</button>
                </div>

                <!-- ================= NEW: Upper sections ================= -->
                <div id="new-upper-block">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="zoo">
                            <label for="zoo">Zoo</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="botanical-garden">
                            <label for="botanical-garden">Botanical Garden</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="private-collection">
                            <label for="private-collection">Private Collection</label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>APPLICANT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first-name" class="required">First Name:</label>
                                <input type="text" id="first-name" name="first_name" />
                            </div>
                            <div class="form-group">
                                <label for="middle-name">Middle Name:</label>
                                <input type="text" id="middle-name" name="middle_name" />
                            </div>
                            <div class="form-group">
                                <label for="last-name" class="required">Last Name:</label>
                                <input type="text" id="last-name" name="last_name" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="residence-address" class="required">Residence Address:</label>
                            <input type="text" id="residence-address">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telephone-number">Telephone Number:</label>
                                <input type="text" id="telephone-number">
                            </div>

                            <div class="form-group">
                                <label for="establishment-name" class="required">Name of Establishment:</label>
                                <input type="text" id="establishment-name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="establishment-address" class="required">Address of Establishment:</label>
                            <input type="text" id="establishment-address">
                        </div>

                        <div class="form-group">
                            <label for="establishment-telephone">Establishment Telephone Number:</label>
                            <input type="text" id="establishment-telephone">
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>ANIMALS/STOCKS INFORMATION</h2>

                        <table id="animals-table">
                            <thead>
                                <tr>
                                    <th>Common Name</th>
                                    <th>Scientific Name</th>
                                    <th>Quantity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="number" class="table-input" min="1"></td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row-btn" id="add-row-btn">
                            <i class="fas fa-plus"></i> Add Animal
                        </button>
                    </div>

                    <div class="form-section">
                        <h2>DECLARATION</h2>
                        <div class="declaration">
                            <p>I understand that the filling of this application conveys no right to possess any wild animals until Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.</p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <canvas id="signature-pad"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                        <button type="button" class="signature-btn save-signature" id="save-signature">Save Signature</button>
                                    </div>
                                    <div class="signature-preview">
                                        <img id="signature-image" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="postal-address">Postal Address:</label>
                                <input type="text" id="postal-address">
                            </div>
                        </div>
                    </div>

                    <!-- (Removed "Download Form as Word Document" button & per-section loading) -->
                </div>
                <!-- /NEW upper sections -->

                <!-- ================= RENEWAL (WILDLIFE) upper sections ================= -->
                <div id="renewal-upper-block" style="display:none;">

                    <!-- checkbox group -->
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-zoo">
                            <label for="renewal-zoo">Zoo</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-botanical-garden">
                            <label for="renewal-botanical-garden">Botanical Garden</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="renewal-private-collection">
                            <label for="renewal-private-collection">Private Collection</label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>APPLICANT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-first-name" class="required">First Name:</label>
                                <input type="text" id="renewal-first-name">
                            </div>

                            <div class="form-group">
                                <label for="renewal-middle-name">Middle Name:</label>
                                <input type="text" id="renewal-middle-name">
                            </div>

                            <div class="form-group">
                                <label for="renewal-last-name" class="required">Last Name:</label>
                                <input type="text" id="renewal-last-name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-residence-address" class="required">Residence Address:</label>
                            <input type="text" id="renewal-residence-address">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-telephone-number">Telephone Number:</label>
                                <input type="text" id="renewal-telephone-number">
                            </div>

                            <div class="form-group">
                                <label for="renewal-establishment-name" class="required">Name of Establishment:</label>
                                <input type="text" id="renewal-establishment-name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-establishment-address" class="required">Address of Establishment:</label>
                            <input type="text" id="renewal-establishment-address">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="renewal-establishment-telephone">Establishment Telephone Number:</label>
                                <input type="text" id="renewal-establishment-telephone">
                            </div>

                            <div class="form-group">
                                <label for="renewal-wfp-number" class="required">Original WFP No.:</label>
                                <input type="text" id="renewal-wfp-number">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="renewal-issue-date" class="required">Issued on:</label>
                            <input type="date" id="renewal-issue-date">
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>ANIMALS/STOCKS INFORMATION</h2>

                        <table id="renewal-animals-table">
                            <thead>
                                <tr>
                                    <th>Common Name</th>
                                    <th>Scientific Name</th>
                                    <th>Quantity</th>
                                    <th>Remarks (Alive/Deceased)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="number" class="table-input" min="1"></td>
                                    <td>
                                        <select class="table-input">
                                            <option value="Alive">Alive</option>
                                            <option value="Deceased">Deceased</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="button" class="add-row-btn" id="renewal-add-row-btn">
                            <i class="fas fa-plus"></i> Add Animal
                        </button>
                    </div>

                    <div class="form-section">
                        <h2>DECLARATION</h2>
                        <div class="declaration">
                            <p>I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.</p>

                            <div class="signature-date">
                                <div class="signature-box">
                                    <label>Signature of Applicant:</label>
                                    <div class="signature-pad-container">
                                        <canvas id="renewal-signature-pad" style="height: 180px;"></canvas>
                                    </div>
                                    <div class="signature-actions">
                                        <button type="button" class="signature-btn clear-signature" id="renewal-clear-signature">Clear</button>
                                        <button type="button" class="signature-btn save-signature" id="renewal-save-signature">Save Signature</button>
                                    </div>
                                    <div class="signature-preview">
                                        <img id="renewal-signature-image" class="hidden" alt="Signature">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="renewal-postal-address">Postal Address:</label>
                                <input type="text" id="renewal-postal-address">
                            </div>
                        </div>
                    </div>

                    <!-- (Removed Renewal "Download Form as Word Document" button & per-section loading) -->
                </div>
                <!-- /RENEWAL upper sections -->

                <!-- ============ NEW PERMIT REQUIREMENTS (default visible) ============ -->
                <div class="requirements-list" id="new-requirements" style="display: grid;">
                    <!-- 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Application Form filed-up with 2 copies of photo of the applicant/s
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Filled Form
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
                                SEC/CDA Registration (Security and Exchange Commission/Cooperative Development Authority) DTI, if for commercial purposes
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload SEC/CDA/DTI Registration
                                </label>
                                <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-2"></div>
                        </div>
                    </div>
                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Proof of Scientific Expertise (Veterinary Certificate)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Veterinary Certificate
                                </label>
                                <input type="file" id="file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-3"></div>
                        </div>
                    </div>
                    <!-- 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Financial Plan for Breeding (Financial/Bank Statement)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Financial/Bank Statement
                                </label>
                                <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-4"></div>
                        </div>
                    </div>
                    <!-- 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Proposed Facility Design (Photo of Facility)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Photo of Facility
                                </label>
                                <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-5"></div>
                        </div>
                    </div>
                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Prior Clearance of affected communities (Municipal or Barangay Clearance)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Municipal/Barangay Clearance
                                </label>
                                <input type="file" id="file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-6"></div>
                        </div>
                    </div>
                    <!-- 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Vicinity Map of the area/site (Ex. Google map Sketch map)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Vicinity Map
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-7"></div>
                        </div>
                    </div>
                    <!-- 8a/8b -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Legal Acquisition of Wildlife:

                            </div>

                        </div>
                        <div class="file-upload">
                            <h4>Proof of Purchase (Official Receipt/Deed of Sale or Captive Bred Certificate)</h4>
                            <div class="file-input-container">
                                <label for="file-8a" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Proof of Purchase
                                </label>
                                <input type="file" id="file-8a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8a"></div>
                            <h4>Deed of Donation with Notary</h4>
                            <div class="file-input-container" style="margin-top:8px;">

                                <label for="file-8b" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Deed of Donation
                                </label>
                                <input type="file" id="file-8b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-8b"></div>
                        </div>
                    </div>
                    <!-- 9 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">9</span>
                                Inspection Report conducted by concerned CENRO
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-9" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Inspection Report
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-9"></div>
                        </div>
                    </div>

                    <div class="fee-info">
                        <p><strong>Application and Processing Fee:</strong> ₱500.00</p>
                        <p><strong>Permit Fee:</strong> ₱2,500.00</p>
                        <p><strong>Total Fee:</strong> ₱3,000.00</p>
                    </div>
                </div>
                <!-- =============== /NEW REQUIREMENTS =============== -->

                <!-- ================= RENEWAL REQUIREMENTS ================= -->
                <div class="requirements-list" id="renewal-requirements" style="display: none;">
                    <!-- 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Duly accomplished application form with two recent 2'x2' photo of the applicant
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload Filled Form
                                </label>
                                <input type="file" id="renewal-file-1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-1"></div>
                        </div>
                    </div>
                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Copy of previous WFP (Original copy)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-2"></div>
                        </div>
                    </div>
                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Quarterly Breeding Report & Monthly Production report
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-3"></div>
                        </div>
                    </div>
                    <!-- 4 (a-d) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                For additional stocks (if any)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- WFP holders/ CITES/Non-CITES Import permit</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Proof of Purchase (Official Receipt/ Sales Invoice or Deed of Sale)</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4b"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Notarized Deed of Donation</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4c" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4c"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Local Transport Permit (if applicable)</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-4d" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-4d" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-4d"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 5 (a-c) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                For additional facility (if any)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Barangay Clearance/ Mayor Clearance</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5a"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Proposed facility design</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5b"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Sketch map of the location</p>
                                <div class="file-input-container">
                                    <label for="renewal-file-5c" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="renewal-file-5c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="renewal-uploaded-files-5c"></div>
                            </div>
                        </div>
                    </div>
                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Inspection Report conducted by concerned CENRO/Regional Office
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="renewal-file-6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="renewal-file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="renewal-uploaded-files-6"></div>
                        </div>
                    </div>
                </div>
                <!-- =============== /RENEWAL REQUIREMENTS =============== -->

            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- File Preview Modal (kept) -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span id="closeFilePreviewModal" class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <!-- Confirm Modal (Chainsaw-style) -->
    <div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Submit Application</div>
            <div style="padding:16px 20px;line-height:1.6">
                Please confirm you want to submit this Wildlife application. Files will be uploaded and your request will enter review.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="btnCancelConfirm" class="btn btn-outline" type="button">Cancel</button>
                <button id="btnOkConfirm" class="btn btn-primary" type="button">Yes, submit</button>
            </div>
        </div>
    </div>

    <!-- Pending NEW request modal -->
    <div id="pendingNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Pending Request</div>
            <div style="padding:16px 20px;line-height:1.6">
                You already have a pending wildlife <b>new</b> permit request. Please wait for updates before submitting another one.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="pendingNewOk" class="btn btn-primary" type="button">Okay</button>
            </div>
        </div>
    </div>

    <!-- Offer renewal modal -->
    <div id="offerRenewalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Renewal Available</div>
            <div style="padding:16px 20px;line-height:1.6">
                You can’t request a <b>new</b> wildlife permit because you already have an approved one. You’re allowed to request a <b>renewal</b> instead.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="offerRenewalOk" class="btn btn-outline" type="button">Okay</button>
                <button id="offerRenewalSwitch" class="btn btn-primary" type="button">Request renewal</button>
            </div>
        </div>
    </div>

    <!-- Need Approved NEW modal (when attempting renewal with no approved new) -->
    <div id="needApprovedNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Action Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                To request a renewal, you must have an approved <b>NEW</b> wildlife permit on record.<br><br>
                You can switch to a NEW permit request. We’ll copy over what you’ve already entered.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="needApprovedNewOk" class="btn btn-outline" type="button">Okay</button>
                <button id="needApprovedNewSwitch" class="btn btn-primary" type="button">Request new</button>
            </div>
        </div>
    </div>

    <!-- Global Loading Overlay -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px">Working…</div>
    </div>

    <script>
        (() => {
            // ====== CONFIG ======
            const SAVE_URL = new URL('../backend/users/wildlife/save_wildlife.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/wildlife/precheck_wildlife.php', window.location.href).toString();

            // ====== UTIL ======
            const byId = (id) => document.getElementById(id);
            const v = (id) => (byId(id)?.value || '').trim();
            const activePermitType = () =>
                (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');

            function toast(msg) {
                const n = byId('profile-notification');
                if (!n) return;
                n.textContent = msg;
                n.style.display = 'block';
                n.style.opacity = '1';
                setTimeout(() => {
                    n.style.opacity = '0';
                    setTimeout(() => {
                        n.style.display = 'none';
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

                const bodyParts = parts.map((p) => {
                    const wrapped = p.base64.replace(/.{1,76}/g, '$&\r\n');
                    return [
                        '',
                        `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        'Content-Transfer-Encoding: base64',
                        `Content-Type: ${p.contentType}`,
                        '',
                        wrapped
                    ].join('\r\n');
                }).join('');

                return header + bodyParts + `\r\n--${boundary}--`;
            }

            function resetForm() {
                document.querySelectorAll('input[type="text"], input[type="date"], input[type="number"]').forEach(inp => inp.value = '');
                document.querySelectorAll('input[type="checkbox"]').forEach(inp => inp.checked = false);
                document.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
                document.querySelectorAll('input[type="file"]').forEach(fi => {
                    fi.value = '';
                    const nameSpan = fi.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = 'No file chosen';
                });
                // tables: leave one row
                ['animals-table', 'renewal-animals-table'].forEach(tid => {
                    const tbody = document.querySelector(`#${tid} tbody`);
                    if (tbody) {
                        Array.from(tbody.querySelectorAll('tr')).slice(1).forEach(tr => tr.remove());
                        tbody.querySelectorAll('input').forEach(i => i.value = '');
                        tbody.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
                    }
                });
                // signatures
                clearSigPad(false);
                clearSigPad(true);
                setPermit('new');
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            // ====== MOBILE NAV ======
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => {
                const isActive = navContainer.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            });

            // ====== PERMIT TYPE TOGGLE ======
            const newBtn = document.querySelector('.permit-type-btn[data-type="new"]');
            const renewalBtn = document.querySelector('.permit-type-btn[data-type="renewal"]');
            const newUpper = byId('new-upper-block');
            const newReqs = byId('new-requirements');
            const renUpper = byId('renewal-upper-block');
            const renReqs = byId('renewal-requirements');

            function setPermit(type) {
                const isNew = type === 'new';
                newUpper.style.display = isNew ? '' : 'none';
                newReqs.style.display = isNew ? 'grid' : 'none';
                renUpper.style.display = isNew ? 'none' : '';
                renReqs.style.display = isNew ? 'none' : 'grid';
                newBtn.classList.toggle('active', isNew);
                renewalBtn.classList.toggle('active', !isNew);
            }
            newBtn?.addEventListener('click', () => setPermit('new'));
            renewalBtn?.addEventListener('click', () => setPermit('renewal'));
            setPermit('new');

            // ====== FILE INPUT LABEL SYNC ======
            document.addEventListener('change', (e) => {
                const input = e.target;
                if (input?.classList?.contains('file-input')) {
                    const nameSpan = input.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = input.files && input.files[0] ? input.files[0].name : 'No file chosen';
                }
            });

            // ====== ANIMALS TABLES ======
            function bindAnimalsTable(addBtnId, tableId, hasRemarks = false) {
                const tbody = document.querySelector(`#${tableId} tbody`);
                const addBtn = byId(addBtnId);

                function attachRemove(tr) {
                    tr.querySelector('.remove-row-btn')?.addEventListener('click', () => {
                        if (tbody.children.length > 1) tbody.removeChild(tr);
                        else alert('You must have at least one animal entry.');
                    });
                }
                // seed existing remove
                Array.from(tbody.querySelectorAll('tr')).forEach(attachRemove);

                addBtn?.addEventListener('click', () => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
        <td><input type="text" class="table-input"></td>
        <td><input type="text" class="table-input"></td>
        <td><input type="number" class="table-input" min="1"></td>
        ${hasRemarks ? `
          <td>
            <select class="table-input">
              <option value="Alive">Alive</option>
              <option value="Deceased">Deceased</option>
            </select>
          </td>` : ''
        }
        <td><button type="button" class="remove-row-btn">Remove</button></td>
      `;
                    tbody.appendChild(tr);
                    attachRemove(tr);
                });
            }
            bindAnimalsTable('add-row-btn', 'animals-table', false);
            bindAnimalsTable('renewal-add-row-btn', 'renewal-animals-table', true);

            // ====== SIGNATURE PADS (manual canvas draw; no external lib) ======
            let sigNew = {
                has: false,
                dataURL: ''
            };
            let sigRen = {
                has: false,
                dataURL: ''
            };

            function initCanvasPad(canvasId, clearBtnId, saveBtnId, imgId, stateObj) {
                const canvas = byId(canvasId);
                const clearBtn = byId(clearBtnId);
                const saveBtn = byId(saveBtnId);
                const img = byId(imgId);
                if (!canvas) return;

                let isDrawing = false,
                    lastX = 0,
                    lastY = 0;

                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const cssWidth = canvas.clientWidth || 400;
                    const cssHeight = canvas.clientHeight || 150;
                    canvas.width = Math.floor(cssWidth * ratio);
                    canvas.height = Math.floor(cssHeight * ratio);
                    const ctx = canvas.getContext('2d');
                    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, cssWidth, cssHeight);
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111';
                }

                function getPos(e) {
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches ? e.touches[0] : null;
                    const clientX = touch ? touch.clientX : e.clientX;
                    const clientY = touch ? touch.clientY : e.clientY;
                    return {
                        x: clientX - rect.left,
                        y: clientY - rect.top
                    };
                }

                function start(e) {
                    isDrawing = true;
                    const {
                        x,
                        y
                    } = getPos(e);
                    lastX = x;
                    lastY = y;
                    e.preventDefault();
                }

                function move(e) {
                    if (!isDrawing) return;
                    const {
                        x,
                        y
                    } = getPos(e);
                    const ctx = canvas.getContext('2d');
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(x, y);
                    ctx.stroke();
                    lastX = x;
                    lastY = y;
                    stateObj.has = true;
                    e.preventDefault();
                }

                function end() {
                    isDrawing = false;
                }

                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);
                canvas.addEventListener('touchstart', start, {
                    passive: false
                });
                canvas.addEventListener('touchmove', move, {
                    passive: false
                });
                window.addEventListener('touchend', end);

                clearBtn?.addEventListener('click', () => {
                    resizeCanvas();
                    stateObj.has = false;
                    stateObj.dataURL = '';
                    if (img) {
                        img.src = '';
                        img.classList.add('hidden');
                    }
                });
                saveBtn?.addEventListener('click', () => {
                    if (!stateObj.has) {
                        alert('Please draw your signature first.');
                        return;
                    }
                    stateObj.dataURL = canvas.toDataURL('image/png');
                    if (img) {
                        img.src = stateObj.dataURL;
                        img.classList.remove('hidden');
                    }
                });
            }

            function clearSigPad(isRenewal) {
                if (isRenewal) {
                    byId('renewal-clear-signature')?.click();
                } else {
                    byId('clear-signature')?.click();
                }
            }
            initCanvasPad('signature-pad', 'clear-signature', 'save-signature', 'signature-image', sigNew);
            initCanvasPad('renewal-signature-pad', 'renewal-clear-signature', 'renewal-save-signature', 'renewal-signature-image', sigRen);

            // ====== MODALS / LOADING ======
            const confirmModal = byId('confirmModal');
            const btnOkConfirm = byId('btnOkConfirm');
            const btnCancelConfirm = byId('btnCancelConfirm');

            const pendingNewModal = byId('pendingNewModal');
            const pendingNewOk = byId('pendingNewOk');

            const offerRenewalModal = byId('offerRenewalModal');
            const offerRenewalOk = byId('offerRenewalOk');
            const offerRenewalSwitch = byId('offerRenewalSwitch');

            const needApprovedNewModal = byId('needApprovedNewModal');
            const needApprovedNewOk = byId('needApprovedNewOk');
            const needApprovedNewSwitch = byId('needApprovedNewSwitch');

            const loading = byId('loadingIndicator');

            pendingNewOk?.addEventListener('click', () => pendingNewModal.style.display = 'none');
            offerRenewalOk?.addEventListener('click', () => offerRenewalModal.style.display = 'none');
            needApprovedNewOk?.addEventListener('click', () => needApprovedNewModal.style.display = 'none');
            btnCancelConfirm?.addEventListener('click', () => confirmModal.style.display = 'none');

            // Switchers (copy values like Chainsaw page)
            function autofillRenewalFromNew() {
                const map = [
                    ['first-name', 'renewal-first-name'],
                    ['middle-name', 'renewal-middle-name'],
                    ['last-name', 'renewal-last-name'],
                    ['residence-address', 'renewal-residence-address'],
                    ['telephone-number', 'renewal-telephone-number'],
                    ['establishment-name', 'renewal-establishment-name'],
                    ['establishment-address', 'renewal-establishment-address'],
                    ['establishment-telephone', 'renewal-establishment-telephone'],
                    ['postal-address', 'renewal-postal-address'],
                ];
                map.forEach(([src, dst]) => {
                    const s = byId(src),
                        d = byId(dst);
                    if (s && d && typeof s.value === 'string') d.value = s.value;
                });
                byId('renewal-zoo') && (byId('renewal-zoo').checked = !!byId('zoo')?.checked);
                byId('renewal-botanical-garden') && (byId('renewal-botanical-garden').checked = !!byId('botanical-garden')?.checked);
                byId('renewal-private-collection') && (byId('renewal-private-collection').checked = !!byId('private-collection')?.checked);
                // copy one row of animals if empty
                const srcRows = document.querySelectorAll('#animals-table tbody tr');
                const dstBody = document.querySelector('#renewal-animals-table tbody');
                if (dstBody && dstBody.children.length === 1) {
                    const inputs = srcRows[0]?.querySelectorAll('input') || [];
                    const dInputs = dstBody.querySelectorAll('input, select');
                    if (inputs.length >= 3 && dInputs.length >= 4) {
                        dInputs[0].value = inputs[0].value; // common
                        dInputs[1].value = inputs[1].value; // scientific
                        dInputs[2].value = inputs[2].value; // qty
                    }
                }
            }

            function autofillNewFromRenewal() {
                const map = [
                    ['renewal-first-name', 'first-name'],
                    ['renewal-middle-name', 'middle-name'],
                    ['renewal-last-name', 'last-name'],
                    ['renewal-residence-address', 'residence-address'],
                    ['renewal-telephone-number', 'telephone-number'],
                    ['renewal-establishment-name', 'establishment-name'],
                    ['renewal-establishment-address', 'establishment-address'],
                    ['renewal-establishment-telephone', 'establishment-telephone'],
                    ['renewal-postal-address', 'postal-address'],
                ];
                map.forEach(([src, dst]) => {
                    const s = byId(src),
                        d = byId(dst);
                    if (s && d && typeof s.value === 'string') d.value = s.value;
                });
                byId('zoo') && (byId('zoo').checked = !!byId('renewal-zoo')?.checked);
                byId('botanical-garden') && (byId('botanical-garden').checked = !!byId('renewal-botanical-garden')?.checked);
                byId('private-collection') && (byId('private-collection').checked = !!byId('renewal-private-collection')?.checked);
            }

            offerRenewalSwitch?.addEventListener('click', () => {
                offerRenewalModal.style.display = 'none';
                setPermit('renewal');
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            needApprovedNewSwitch?.addEventListener('click', () => {
                needApprovedNewModal.style.display = 'none';
                setPermit('new');
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // ====== PRECHECK & SUBMIT BUTTON ======
            const btnSubmit = byId('submitApplication');
            btnSubmit?.addEventListener('click', async () => {
                // Build minimal identity info for precheck
                const type = activePermitType();
                const first = type === 'renewal' ? v('renewal-first-name') : v('first-name');
                const middle = type === 'renewal' ? v('renewal-middle-name') : v('middle-name');
                const last = type === 'renewal' ? v('renewal-last-name') : v('last-name');

                try {
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

                    if (json.block === 'pending_new') {
                        pendingNewModal.style.display = 'flex';
                        return;
                    }
                    if (json.block === 'pending_renewal') {
                        toast('You already have a pending wildlife renewal. Please wait for the update first.');
                        return;
                    }
                    if (json.block === 'need_approved_new') {
                        needApprovedNewModal.style.display = 'flex';
                        return;
                    }
                    if (json.offer === 'renewal' && type === 'new') {
                        offerRenewalModal.style.display = 'flex';
                        return;
                    }
                    confirmModal.style.display = 'flex';
                } catch (e) {
                    console.error(e);
                    // Allow manual confirm even if precheck failed (same as chainsaw UX)
                    confirmModal.style.display = 'flex';
                }
            });

            // ====== FINAL SUBMIT HANDLER ======
            btnOkConfirm?.addEventListener('click', async () => {
                confirmModal.style.display = 'none';
                loading.style.display = 'flex';
                try {
                    await doSubmit();
                    toast("Application submitted. We'll notify you once reviewed.");
                    resetForm();
                } catch (e) {
                    console.error(e);
                    toast(e?.message || 'Submission failed. Please try again.');
                } finally {
                    loading.style.display = 'none';
                }
            });

            // ====== APP DOC GENERATION + SAVE ======
            async function doSubmit() {
                const type = activePermitType();
                // Collect NEW / RENEWAL specifics
                let firstName, middleName, lastName,
                    residenceAddress, telephoneNumber, establishmentName, establishmentAddress, establishmentTelephone, postalAddress;

                // Checkboxes (categories)
                let zoo = false,
                    botanical = false,
                    privateColl = false;

                // Animals
                let animals = [];

                // Renewal-only
                let wfpNumber = '',
                    issueDate = '';

                // Signatures
                let sigDataURL = '';

                if (type === 'new') {
                    firstName = v('first-name');
                    middleName = v('middle-name');
                    lastName = v('last-name');
                    residenceAddress = v('residence-address');
                    telephoneNumber = v('telephone-number');
                    establishmentName = v('establishment-name');
                    establishmentAddress = v('establishment-address');
                    establishmentTelephone = v('establishment-telephone');
                    postalAddress = v('postal-address');

                    zoo = !!byId('zoo')?.checked;
                    botanical = !!byId('botanical-garden')?.checked;
                    privateColl = !!byId('private-collection')?.checked;

                    sigDataURL = sigNew.dataURL || (sigNew.has ? byId('signature-pad')?.toDataURL?.('image/png') : '');

                    // animals rows
                    const rows = document.querySelectorAll('#animals-table tbody tr');
                    rows.forEach(row => {
                        const inputs = row.querySelectorAll('input');
                        const commonName = inputs[0]?.value || '';
                        const scientificName = inputs[1]?.value || '';
                        const quantity = inputs[2]?.value || '';
                        if (commonName || scientificName || quantity) {
                            animals.push({
                                commonName,
                                scientificName,
                                quantity
                            });
                        }
                    });
                } else {
                    firstName = v('renewal-first-name');
                    middleName = v('renewal-middle-name');
                    lastName = v('renewal-last-name');
                    residenceAddress = v('renewal-residence-address');
                    telephoneNumber = v('renewal-telephone-number');
                    establishmentName = v('renewal-establishment-name');
                    establishmentAddress = v('renewal-establishment-address');
                    establishmentTelephone = v('renewal-establishment-telephone');
                    postalAddress = v('renewal-postal-address');

                    wfpNumber = v('renewal-wfp-number');
                    issueDate = v('renewal-issue-date');

                    zoo = !!byId('renewal-zoo')?.checked;
                    botanical = !!byId('renewal-botanical-garden')?.checked;
                    privateColl = !!byId('renewal-private-collection')?.checked;

                    sigDataURL = sigRen.dataURL || (sigRen.has ? byId('renewal-signature-pad')?.toDataURL?.('image/png') : '');

                    const rows = document.querySelectorAll('#renewal-animals-table tbody tr');
                    rows.forEach(row => {
                        const inputs = row.querySelectorAll('input, select');
                        const commonName = inputs[0]?.value || '';
                        const scientificName = inputs[1]?.value || '';
                        const quantity = inputs[2]?.value || '';
                        const remarks = inputs[3]?.value || '';
                        if (commonName || scientificName || quantity || remarks) {
                            animals.push({
                                commonName,
                                scientificName,
                                quantity,
                                remarks
                            });
                        }
                    });
                }

                const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
                const check = (b) => b ? '☒' : '☐';

                // Build HTML content for the application (New vs Renewal)
                const isRenewal = type === 'renewal';
                const headerHtml = `
      <div style="text-align:center;margin-bottom:20px;">
        <p style="font-weight:bold;">Republic of the Philippines</p>
        <p style="font-weight:bold;">Department of Environment and Natural Resources</p>
        <p style="font-weight:bold;">REGION 7</p>
        <p>______</p>
        <p>Date</p>
      </div>
    `;

                const animalsTableRows = animals.length ?
                    animals.map(a => `
          <tr>
            <td>${a.commonName || ''}</td>
            <td>${a.scientificName || ''}</td>
            <td>${a.quantity || ''}</td>
            ${isRenewal ? `<td>${a.remarks || ''}</td>` : ''}
          </tr>`).join('') :
                    (isRenewal ?
                        `<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>` :
                        `<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>`);

                const sigLocation = 'signature.png';
                const hasSignature = !!sigDataURL;

                const docHtml = `
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>${isRenewal ? 'Wildlife Registration Renewal Application' : 'Wildlife Registration Application'}</title>
<style>
  body, div, p { line-height:1.6; font-family:Arial; font-size:11pt; margin:0; padding:0; }
  .bold{ font-weight:bold; }
  .checkbox{ font-family:"Wingdings 2"; font-size:14pt; vertical-align:middle; }
  .underline{ display:inline-block; border-bottom:1px solid #000; min-width:260px; padding:0 5px; margin:0 5px; }
  .underline-small{ display:inline-block; border-bottom:1px solid #000; min-width:150px; padding:0 5px; margin:0 5px; }
  .indent{ margin-left:40px; }
  .info-line{ margin:12pt 0; }
  table{ width:100%; border-collapse:collapse; margin:15pt 0; }
  table, th, td { border:1px solid #000; }
  th, td { padding:8px; text-align:left; }
</style>
</head>
<body>
${headerHtml}

<p style="text-align:center;margin-bottom:20px;" class="bold">
  ${isRenewal ? 'APPLICATION FOR: RENEWAL CERTIFICATE OF WILDLIFE REGISTRATION'
              : 'APPLICATION FOR: CERTIFICATE OF WILDLIFE REGISTRATION'}
</p>

<p style="margin-bottom:15px;">
  <span class="checkbox">${check(zoo)}</span> Zoo
  <span class="checkbox">${check(botanical)}</span> Botanical Garden
  <span class="checkbox">${check(privateColl)}</span> Private Collection
</p>

<p class="info-line">The Regional Executive Director</p>
<p class="info-line">DENR Region 7</p>
<p class="info-line">National Government Center,</p>
<p class="info-line">Sudion, Lahug, Cebu City</p>

<p class="info-line">(Submit in Duplicate)</p>
<p class="info-line">Sir/Madam:</p>

${
  isRenewal
  ? `
    <p class="info-line">I, <span class="underline">${fullName}</span> with address at <span class="underline">${residenceAddress}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">${telephoneNumber}</span>, have the honor to request for the</p>
    <p class="info-line indent">renewal of my Certificate of Wildlife Registration of <span class="underline">${establishmentName}</span></p>
    <p class="info-line indent">located at <span class="underline">${establishmentAddress}</span> with Tel. no. <span class="underline-small">${establishmentTelephone}</span></p>
    <p class="info-line indent">and Original WFP No. <span class="underline-small">${wfpNumber}</span> issued on <span class="underline-small">${issueDate}</span>, and</p>
    <p class="info-line">registration of animals/stocks maintained which are as follows:</p>
  `
  : `
    <p class="info-line">I <span class="underline">${fullName}</span> with address at <span class="underline">${residenceAddress}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">${telephoneNumber}</span> have the honor to apply for the registration of <span class="underline">${establishmentName}</span></p>
    <p class="info-line indent">located at <span class="underline">${establishmentAddress}</span> with Tel. no. <span class="underline-small">${establishmentTelephone}</span> and registration of animals/stocks maintained</p>
    <p class="info-line">there at which are as follows:</p>
  `
}

<table>
  <tr>
    <th>Common Name</th>
    <th>Scientific Name</th>
    <th>Quantity</th>
    ${isRenewal ? '<th>Remarks (Alive/Deceased)</th>' : ''}
  </tr>
  ${animalsTableRows}
</table>

<p class="info-line">
  ${
    isRenewal
      ? 'I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.'
      : 'I understand that the filling of this application conveys no right to possess any wild animals until Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.'
  }
</p>

<div style="margin-top:28px;">
  ${
    hasSignature
      ? `<img src="${sigLocation}" style="max-height:60px;display:block;margin-top:8pt;border:1px solid #000;" alt="Signature"/>`
      : `<div style="margin-top:40px;border-top:1px solid #000;width:50%;padding-top:3pt;"></div>`
  }
  <p>Signature of Applicant</p>
</div>

<p class="info-line">Postal Address: <span class="underline">${postalAddress}</span></p>

</body>
</html>
`.trim();

                const parts = hasSignature ? [{
                    location: sigLocation,
                    contentType: 'image/png',
                    base64: (sigDataURL.split(',')[1] || '')
                }] : [];

                const mhtml = makeMHTML(docHtml, parts);
                const docBlob = new Blob([mhtml], {
                    type: 'application/msword'
                });
                const docName = `${isRenewal ? 'Wildlife_Renewal' : 'Wildlife_New'}_${(fullName || 'Applicant').replace(/\s+/g, '_')}.doc`;
                const docFile = new File([docBlob], docName, {
                    type: 'application/msword'
                });

                // Build FormData for backend
                const fd = new FormData();
                fd.append('permit_type', isRenewal ? 'renewal' : 'new');

                // Identity / contact
                fd.append('first_name', firstName);
                fd.append('middle_name', middleName);
                fd.append('last_name', lastName);
                fd.append('residence_address', residenceAddress);
                fd.append('telephone_number', telephoneNumber);
                fd.append('establishment_name', establishmentName);
                fd.append('establishment_address', establishmentAddress);
                fd.append('establishment_telephone', establishmentTelephone);
                fd.append('postal_address', postalAddress);

                // Categories
                fd.append('zoo', String(zoo ? 1 : 0));
                fd.append('botanical_garden', String(botanical ? 1 : 0));
                fd.append('private_collection', String(privateColl ? 1 : 0));

                // Renewal-only fields
                if (isRenewal) {
                    fd.append('wfp_number', wfpNumber);
                    fd.append('issue_date', issueDate);
                }

                // Animals JSON
                fd.append(isRenewal ? 'renewal_animals_json' : 'animals_json', JSON.stringify(animals || []));

                // Generated application document + signature
                fd.append('application_doc', docFile);
                if (hasSignature) {
                    const sigBlob = dataURLToBlob(sigDataURL);
                    fd.append('signature_file', new File([sigBlob], 'signature.png', {
                        type: 'image/png'
                    }));
                }

                // ---- Attach FILES (only if present) ----
                // NEW files
                if (!isRenewal) {
                    [
                        'file-1', 'file-2', 'file-3', 'file-4', 'file-5', 'file-6', 'file-7', 'file-8a', 'file-8b', 'file-9'
                    ].forEach((id) => {
                        const f = byId(id)?.files?.[0];
                        if (f) fd.append(id.replace(/-/g, '_'), f); // e.g., file_1, file_8a
                    });
                } else {
                    [
                        'renewal-file-1', 'renewal-file-2', 'renewal-file-3',
                        'renewal-file-4a', 'renewal-file-4b', 'renewal-file-4c', 'renewal-file-4d',
                        'renewal-file-5a', 'renewal-file-5b', 'renewal-file-5c',
                        'renewal-file-6'
                    ].forEach((id) => {
                        const f = byId(id)?.files?.[0];
                        if (f) fd.append(id.replace(/-/g, '_'), f); // e.g., renewal_file_1, renewal_file_4a
                    });
                }

                // ---- SEND ----
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
                if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);
            }

            // ====== FILE PREVIEW (optional; kept) ======
            const previewModal = byId('filePreviewModal');
            const modalFrame = byId('filePreviewFrame');
            const closePreview = byId('closeFilePreviewModal');

            function previewFile(file) {
                if (!modalFrame || !previewModal) return;
                modalFrame.removeAttribute('src');
                modalFrame.removeAttribute('srcdoc');
                const reader = new FileReader();
                reader.onload = function(e) {
                    const dataUrl = e.target?.result;
                    if (file.type.startsWith('image/')) {
                        modalFrame.srcdoc = `<img src='${dataUrl}' style='max-width:100%;max-height:80vh;'>`;
                    } else if (file.type === 'application/pdf') {
                        modalFrame.src = String(dataUrl);
                    } else {
                        const url = URL.createObjectURL(file);
                        modalFrame.srcdoc = `<div style='padding:20px;text-align:center;'>
          Cannot preview this file type.<br>
          <a href='${url}' download='${file.name}' style='color:#2b6625;font-weight:bold;'>Download ${file.name}</a>
        </div>`;
                    }
                    previewModal.style.display = 'block';
                };
                if (file.type.startsWith('image/') || file.type === 'application/pdf') reader.readAsDataURL(file);
                else reader.onload();
            }
            closePreview?.addEventListener('click', () => previewModal.style.display = 'none');
            window.addEventListener('click', (e) => {
                if (e.target === previewModal) previewModal.style.display = 'none';
            });

        })();
    </script>


</body>






</html>