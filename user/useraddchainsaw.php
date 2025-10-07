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
            --dark-gray: #555;
            --medium-gray: #ddd;
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
            background: rgba(224, 204, 204, 0.3);
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

        /* fixed visible height */

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
        #loadingIndicator {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        #loadingIndicator .card {
            background: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
            color: #333;
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
    </style>
</head>

<body>
    <!-- Header #1 (kept) -->
    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                    <a href="user_requestseedlings.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="user_chainsaw_renewal.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Renewal</span></a>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer"><a href="user_notification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas a-user-edit"></i><span>Edit Profile</span></a>
                    <a href="user_login.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <!-- Header #2 (kept) -->
    <header>
        <div class="logo">
            <a href="user_home.php"><img src="seal.png" alt="Site Logo" /></a>
        </div>
        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
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
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-as-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer"><a href="user_notification.php" class="view-all">View All Notifications</a></div>
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
        <!-- <div class="action-buttons">
            <button class="btn btn-primary" id="addFilesBtn"><i class="fas fa-plus-circle"></i> Add</button>
            <a href="usereditchainsaw.php" class="btn btn-outline"><i class="fas fa-edit"></i> Edit</a>
            <a href="userviewchainsaw.php" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
        </div> -->

        <div class="requirements-form">
            <div class="form-header">
                <h2>Chainsaw Registration (New/ Renewal) Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <div class="permit-type-selector">
                    <button class="permit-type-btn active" data-type="new" type="button">New Chainsaw Permit</button>
                    <button class="permit-type-btn" data-type="renewal" type="button">Chainsaw Renewal</button>
                </div>

                <!-- NEW: APPLICANT INFO -->
                <div class="form-section-group" data-permit-for="new">
                    <div class="form-section">
                        <h2>I. APPLICANT INFORMATION</h2>

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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="street" class="required">Street Name/Sitio:</label>
                                <input type="text" id="street" name="sitio_street" />
                            </div>
                            <div class="form-group">
                                <label for="barangay" class="required">Barangay:</label>
                                <input list="barangayList" id="barangay" name="barangay" />
                                <datalist id="barangayList">
                                    <option value="Guadalupe" />
                                    <option value="Lahug" />
                                    <option value="Mabolo" />
                                    <option value="Labangon" />
                                    <option value="Talamban" />
                                </datalist>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="municipality" class="required">Municipality:</label>
                                <select id="municipality" name="municipality">
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
                            </div>
                            <div class="form-group">
                                <label for="province" class="required">Province:</label>
                                <input type="text" id="province" name="province" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact-number" class="required">Contact Number:</label>
                            <input type="text" id="contact-number" name="contact_number" />
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>II. CHAINSAW INFORMATION AND DESCRIPTION</h2>

                        <div class="form-group">
                            <label for="purpose" class="required">Purpose of Use:</label>
                            <input type="text" id="purpose" name="purpose" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="brand" class="required">Brand:</label>
                                <input type="text" id="brand" name="brand" />
                            </div>
                            <div class="form-group">
                                <label for="model" class="required">Model:</label>
                                <input type="text" id="model" name="model" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="acquisition-date" class="required">Date of Acquisition:</label>
                                <input type="date" id="acquisition-date" name="date_of_acquisition" />
                            </div>
                            <div class="form-group">
                                <label for="serial-number" class="required">Serial Number:</label>
                                <input type="text" id="serial-number" name="serial_number" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="horsepower">Horsepower:</label>
                                <input type="text" id="horsepower" name="horsepower" />
                            </div>
                            <div class="form-group">
                                <label for="guide-bar-length" class="required">Maximum Length of Guide Bar:</label>
                                <input type="text" id="guide-bar-length" name="maximum_length_of_guide_bar" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RENEWAL -->
                <div class="form-section-group" data-permit-for="renewal" style="display:none">
                    <div class="form-section">
                        <h2>I. APPLICANT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first-name-r" class="required">First Name:</label>
                                <input type="text" id="first-name-r" />
                            </div>
                            <div class="form-group">
                                <label for="middle-name-r">Middle Name:</label>
                                <input type="text" id="middle-name-r" />
                            </div>
                            <div class="form-group">
                                <label for="last-name-r" class="required">Last Name:</label>
                                <input type="text" id="last-name-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address-r" class="required">Address:</label>
                            <input type="text" id="address-r" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="street-r" class="required">Street Name/Sitio:</label>
                                <input type="text" id="street-r" />
                            </div>
                            <div class="form-group">
                                <label for="barangay-r" class="required">Barangay:</label>
                                <input type="text" id="barangay-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="municipality-r" class="required">Municipality:</label>
                                <input type="text" id="municipality-r" />
                            </div>
                            <div class="form-group">
                                <label for="province-r" class="required">Province:</label>
                                <input type="text" id="province-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact-number-r" class="required">Contact Number:</label>
                            <input type="text" id="contact-number-r" />
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>II. EXISTING CHAINSAW PERMIT INFORMATION</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="permit-number-r" class="required">Permit Number:</label>
                                <input type="text" id="permit-number-r" />
                            </div>

                            <div class="form-group">
                                <label for="issuance-date-r" class="required">Date of Original Issuance:</label>
                                <input type="date" id="issuance-date-r" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="expiry-date-r" class="required">Expiry Date:</label>
                            <input type="date" id="expiry-date-r" />
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>III. CHAINSAW INFORMATION AND DESCRIPTION</h2>

                        <div class="form-group">
                            <label for="purpose-r" class="required">Purpose of Use:</label>
                            <input type="text" id="purpose-r" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="brand-r" class="required">Brand:</label>
                                <input type="text" id="brand-r" />
                            </div>

                            <div class="form-group">
                                <label for="model-r" class="required">Model:</label>
                                <input type="text" id="model-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="acquisition-date-r" class="required">Date of Acquisition:</label>
                                <input type="date" id="acquisition-date-r" />
                            </div>

                            <div class="form-group">
                                <label for="serial-number-r" class="required">Serial Number:</label>
                                <input type="text" id="serial-number-r" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="horsepower-r">Horsepower:</label>
                                <input type="text" id="horsepower-r" />
                            </div>

                            <div class="form-group">
                                <label for="guide-bar-length-r" class="required">Maximum Length of Guide Bar:</label>
                                <input type="text" id="guide-bar-length-r" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DECLARATION -->
                <div class="form-section">
                    <h2 class="declaration-title">DECLARATION AND SUBMISSION</h2>
                    <div class="declaration">
                        <p>
                            I,
                            <input type="text" id="declaration-name" class="declaration-input" placeholder="Enter your full name" />,
                            hereby declare that the information provided above is true and correct to the best of my knowledge. I understand that any false statement or misrepresentation may be a ground for the denial or revocation of this application and will subject me to appropriate legal action.
                        </p>

                        <div class="signature-date">
                            <div class="signature-box">
                                <label>Signature of Applicant:</label>
                                <div class="signature-pad-container">
                                    <canvas id="signature-pad"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" class="signature-btn clear-signature" id="clear-signature"><i class="fa-solid fa-eraser"></i> Clear</button>
                                </div>
                                <div class="signature-preview">
                                    <img id="signature-image" class="hidden" alt="Signature Preview" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requirements (common + filtered per type) -->
                <div class="requirements-list" id="requirementsList">
                    <!-- 1 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Certificate of Chainsaw Registration (3 copies for CENRO signature)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement" data-show-for="new,renewal">
                                <p style="margin-bottom:10px;font-weight:500;">- Terms and Conditions</p>
                                <div class="file-input-container">
                                    <label for="file-cert-terms" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                    <input type="file" id="file-cert-terms" name="chainsaw_cert_terms" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-cert-terms"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top:15px;" data-show-for="new,renewal">
                                <p style="margin-bottom:10px;font-weight:500;">- Chainsaw Registration Sticker</p>
                                <div class="file-input-container">
                                    <label for="file-cert-sticker" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                    <input type="file" id="file-cert-sticker" name="chainsaw_cert_sticker" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-cert-sticker"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Complete Staff Work (Memorandum Report) – 2 pages with Station Supervisor’s signature (1 file)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-memo" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-memo" name="chainsaw_staff_work" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-memo"></div>
                        </div>
                    </div>

                    <!-- 3 -->
                    <div class="requirement-item" data-show-for="new,renewal">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Geo-tagged Photos of the Chainsaw (2 copies from the client)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-geo" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-geo" name="geo_photos" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-geo"></div>
                            <!-- NOTE: Application letter REMOVED from UI -->
                        </div>
                    </div>

                    <!-- New-only items -->
                    <div class="requirement-item" data-show-for="new">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Permit to Sell (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-sell-permit" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-sell-permit" name="chainsaw_permit_to_sell" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-sell-permit"></div>
                        </div>
                    </div>

                    <div class="requirement-item" data-show-for="new">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Photocopy of Business Permit – new recent issued (2 copies)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-business-permit" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-business-permit" name="chainsaw_business_permit" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-business-permit"></div>
                        </div>
                    </div>

                    <div class="requirement-item" data-show-for="new">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Photocopy of old chainsaw Registration – 2 copies
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-old-reg" class="file-input-label"><i class="fas fa-upload"></i> Upload File</label>
                                <input type="file" id="file-old-reg" name="chainsaw_old_registration" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-old-reg"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication" type="button">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Loading -->
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px">Working…</div>
    </div>
    <!-- Need Approved NEW modal -->
    <div id="needApprovedNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Action Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                To request a renewal, you must have an approved <b>NEW</b> chainsaw permit on record.<br><br>
                You can switch to a NEW permit request. We’ll copy over what you’ve already entered.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="needApprovedNewOk" class="btn btn-outline" type="button">Okay</button>
                <button id="needApprovedNewSwitch" class="btn btn-primary" type="button">Request new</button>
            </div>
        </div>
    </div>


    <!-- Confirm Modal -->
    <div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Submit Application</div>
            <div style="padding:16px 20px;line-height:1.6">
                Please confirm you want to submit this Chainsaw application. Files will be uploaded and your request will enter review.
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
                You already have a pending chainsaw <b>new</b> permit request. Please wait for updates before submitting another one.
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
                You can’t request a <b>new</b> chainsaw permit because you already have an approved one. You’re allowed to request a <b>renewal</b> instead.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="offerRenewalOk" class="btn btn-outline" type="button">Okay</button>
                <button id="offerRenewalSwitch" class="btn btn-primary" type="button">Request renewal</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const SIG_WIDTH = 300,
                SIG_HEIGHT = 110;
            const SAVE_URL = new URL('../backend/users/chainsaw/save_chainsaw.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/chainsaw/precheck_chainsaw.php', window.location.href).toString();

            const btns = document.querySelectorAll(".permit-type-btn");
            const list = document.getElementById("requirementsList");

            function renumberVisible() {
                if (!list) return;
                const items = Array.from(list.querySelectorAll(".requirement-item")).filter(el => el.style.display !== "none");
                items.forEach((el, idx) => {
                    const num = el.querySelector(".requirement-number");
                    if (num) num.textContent = (idx + 1).toString();
                });
            }

            function showPermitGroup(type) {
                document.querySelectorAll(".form-section-group").forEach((g) => {
                    g.style.display = g.getAttribute("data-permit-for") === type ? "" : "none";
                });
            }

            function applyFilter(type) {
                btns.forEach((b) => b.classList.toggle("active", b.dataset.type === type));
                showPermitGroup(type);

                // filter items and sub-requirements
                if (list) {
                    list.style.display = "";
                    list.querySelectorAll(".requirement-item").forEach((el) => {
                        const show = (el.getAttribute("data-show-for") || "").split(",").map((s) => s.trim());
                        el.style.display = show.includes(type) ? "" : "none";
                    });
                    list.querySelectorAll(".sub-requirement[data-show-for]").forEach((sub) => {
                        const show = (sub.getAttribute("data-show-for") || "").split(",").map((s) => s.trim());
                        sub.style.display = show.includes(type) ? "" : "none";
                    });
                }
                renumberVisible();
            }
            btns.forEach((b) => b.addEventListener("click", () => applyFilter(b.dataset.type)));
            applyFilter("new");

            // file input name preview
            document.addEventListener("change", (e) => {
                const input = e.target;
                if (input.classList && input.classList.contains("file-input")) {
                    const nameSpan = input.parentElement.querySelector(".file-name");
                    nameSpan.textContent = input.files && input.files[0] ? input.files[0].name : "No file chosen";
                }
            });

            // signature pad
            const canvas = document.getElementById("signature-pad");
            const clearBtn = document.getElementById("clear-signature");
            const sigImg = document.getElementById("signature-image");
            let isDrawing = false,
                lastX = 0,
                lastY = 0,
                hasDrawn = false;

            function resizeCanvas() {
                if (!canvas) return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssWidth = canvas.clientWidth || 300;
                const cssHeight = canvas.clientHeight || 150;
                canvas.width = Math.floor(cssWidth * ratio);
                canvas.height = Math.floor(cssHeight * ratio);
                const ctx = canvas.getContext("2d");
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, cssWidth, cssHeight);
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.strokeStyle = "#111";
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

            function startDraw(e) {
                isDrawing = true;
                const {
                    x,
                    y
                } = getPos(e);
                lastX = x;
                lastY = y;
                e.preventDefault();
            }

            function draw(e) {
                if (!isDrawing) return;
                const ctx = canvas.getContext("2d");
                const {
                    x,
                    y
                } = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();
                lastX = x;
                lastY = y;
                hasDrawn = true;
                e.preventDefault();
            }

            function endDraw() {
                isDrawing = false;
            }

            if (canvas) {
                resizeCanvas();
                window.addEventListener("resize", resizeCanvas);
                canvas.addEventListener("mousedown", startDraw);
                canvas.addEventListener("mousemove", draw);
                window.addEventListener("mouseup", endDraw);
                canvas.addEventListener("touchstart", startDraw, {
                    passive: false
                });
                canvas.addEventListener("touchmove", draw, {
                    passive: false
                });
                window.addEventListener("touchend", endDraw);
                clearBtn?.addEventListener("click", () => {
                    resizeCanvas();
                    hasDrawn = false;
                    if (sigImg) {
                        sigImg.src = "";
                        sigImg.classList.add("hidden");
                    }
                });
            }

            // helpers
            function dataURLToBlob(dataURL) {
                if (!dataURL) return null;
                const [meta, b64] = dataURL.split(",");
                const mime = (meta.match(/data:(.*?);base64/) || [])[1] || "application/octet-stream";
                const bin = atob(b64 || "");
                const u8 = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                return new Blob([u8], {
                    type: mime
                });
            }

            function makeMHTML(html, parts = []) {
                const boundary = "----=_NextPart_" + Date.now().toString(16);
                const header = [
                    "MIME-Version: 1.0",
                    `Content-Type: multipart/related; type="text/html"; boundary="${boundary}"`,
                    "X-MimeOLE: Produced By Microsoft MimeOLE",
                    "",
                    `--${boundary}`,
                    'Content-Type: text/html; charset="utf-8"',
                    "Content-Transfer-Encoding: 8bit",
                    "",
                    html
                ].join("\r\n");
                const bodyParts = parts.map(p => {
                    const wrapped = p.base64.replace(/.{1,76}/g, "$&\r\n");
                    return [
                        "", `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        "Content-Transfer-Encoding: base64",
                        `Content-Type: ${p.contentType}`, "", wrapped
                    ].join("\r\n");
                }).join("");
                return header + bodyParts + `\r\n--${boundary}--`;
            }

            function toast(msg) {
                const n = document.getElementById("profile-notification");
                n.textContent = msg;
                n.style.display = "block";
                n.style.opacity = "1";
                setTimeout(() => {
                    n.style.opacity = "0";
                    setTimeout(() => {
                        n.style.display = "none";
                        n.style.opacity = "1";
                    }, 350);
                }, 2400);
            }

            function resetForm() {
                document.querySelectorAll("input[type='text'], input[type='date']").forEach(inp => inp.value = "");
                document.querySelectorAll("select").forEach(sel => sel.selectedIndex = 0);
                document.querySelectorAll("input[type='file']").forEach(fi => {
                    fi.value = "";
                    const nameSpan = fi.parentElement?.querySelector(".file-name");
                    if (nameSpan) nameSpan.textContent = "No file chosen";
                });
                hasDrawn = false;
                const sigImg = document.getElementById("signature-image");
                if (sigImg) {
                    sigImg.src = "";
                    sigImg.classList.add("hidden");
                }
                if (canvas) resizeCanvas();
                applyFilter("new");
            }

            // elements
            const confirmModal = document.getElementById("confirmModal");
            const btnSubmit = document.getElementById("submitApplication");
            const btnOk = document.getElementById("btnOkConfirm");
            const btnCancel = document.getElementById("btnCancelConfirm");
            const loading = document.getElementById("loadingIndicator");

            const pendingNewModal = document.getElementById("pendingNewModal");
            const pendingNewOk = document.getElementById("pendingNewOk");
            const offerRenewalModal = document.getElementById("offerRenewalModal");
            const offerRenewalOk = document.getElementById("offerRenewalOk");
            const offerRenewalSwitch = document.getElementById("offerRenewalSwitch");

            // NEW: Need Approved NEW modal refs
            const needApprovedNewModal = document.getElementById("needApprovedNewModal");
            const needApprovedNewOk = document.getElementById("needApprovedNewOk");
            const needApprovedNewSwitch = document.getElementById("needApprovedNewSwitch");

            pendingNewOk?.addEventListener("click", () => {
                pendingNewModal.style.display = "none";
            });
            offerRenewalOk?.addEventListener("click", () => {
                offerRenewalModal.style.display = "none";
            });
            needApprovedNewOk?.addEventListener("click", () => {
                needApprovedNewModal.style.display = "none";
            });

            // value helper
            function v(id) {
                return (document.getElementById(id)?.value || "").trim();
            }

            // Autofill RENEWAL from NEW (used when offering renewal)
            function autofillRenewalFromNew() {
                const map = [
                    ["first-name", "first-name-r"],
                    ["middle-name", "middle-name-r"],
                    ["last-name", "last-name-r"],
                    ["street", "street-r"],
                    ["barangay", "barangay-r"],
                    ["municipality", "municipality-r"],
                    ["province", "province-r"],
                    ["contact-number", "contact-number-r"],
                    ["purpose", "purpose-r"],
                    ["brand", "brand-r"],
                    ["model", "model-r"],
                    ["acquisition-date", "acquisition-date-r"],
                    ["serial-number", "serial-number-r"],
                    ["horsepower", "horsepower-r"],
                    ["guide-bar-length", "guide-bar-length-r"],
                ];
                map.forEach(([srcId, dstId]) => {
                    const src = document.getElementById(srcId);
                    const dst = document.getElementById(dstId);
                    if (src && dst && typeof src.value === "string") dst.value = src.value;
                });
                const addr = [v("street"), v("barangay"), v("municipality"), v("province")].filter(Boolean).join(", ");
                const addrR = document.getElementById("address-r");
                if (addrR) addrR.value = addr;
                const decl = document.getElementById("declaration-name");
                const fullName = [v("first-name"), v("middle-name"), v("last-name")].filter(Boolean).join(" ");
                if (decl && !decl.value) decl.value = fullName;
            }

            // NEW: Autofill NEW from RENEWAL (used when renewal is blocked; switch to NEW)
            function autofillNewFromRenewal() {
                const map = [
                    ["first-name-r", "first-name"],
                    ["middle-name-r", "middle-name"],
                    ["last-name-r", "last-name"],
                    ["street-r", "street"],
                    ["barangay-r", "barangay"],
                    ["municipality-r", "municipality"],
                    ["province-r", "province"],
                    ["contact-number-r", "contact-number"],
                    ["purpose-r", "purpose"],
                    ["brand-r", "brand"],
                    ["model-r", "model"],
                    ["acquisition-date-r", "acquisition-date"],
                    ["serial-number-r", "serial-number"],
                    ["horsepower-r", "horsepower"],
                    ["guide-bar-length-r", "guide-bar-length"],
                ];
                map.forEach(([srcId, dstId]) => {
                    const src = document.getElementById(srcId);
                    const dst = document.getElementById(dstId);
                    if (src && dst && typeof src.value === "string") dst.value = src.value;
                });
                // declaration name if blank
                const decl = document.getElementById("declaration-name");
                if (decl && !decl.value) {
                    const fullName = [v("first-name-r"), v("middle-name-r"), v("last-name-r")].filter(Boolean).join(" ");
                    decl.value = fullName;
                }
            }

            // Switch to renewal via offer
            offerRenewalSwitch?.addEventListener("click", () => {
                offerRenewalModal.style.display = "none";
                applyFilter("renewal");
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });

            // NEW: Switch to NEW from the "Need Approved NEW" modal
            needApprovedNewSwitch?.addEventListener("click", () => {
                needApprovedNewModal.style.display = "none";
                applyFilter("new");
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            });

            btnCancel?.addEventListener("click", () => {
                confirmModal.style.display = "none";
            });

            const activePermitType = () =>
                (document.querySelector(".permit-type-btn.active")?.getAttribute("data-type") || "new");

            // PRECHECK before confirm (sync with backend decisions)
            btnSubmit?.addEventListener("click", async () => {
                try {
                    const type = activePermitType();
                    const first = type === "renewal" ? v("first-name-r") : v("first-name");
                    const middle = type === "renewal" ? v("middle-name-r") : v("middle-name");
                    const last = type === "renewal" ? v("last-name-r") : v("last-name");

                    const fd = new FormData();
                    fd.append("first_name", first);
                    fd.append("middle_name", middle);
                    fd.append("last_name", last);
                    fd.append("desired_permit_type", type);

                    const res = await fetch(PRECHECK_URL, {
                        method: "POST",
                        body: fd,
                        credentials: "include"
                    });
                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message || "Precheck failed");

                    if (json.block === "pending_new") {
                        pendingNewModal.style.display = "flex";
                        return;
                    }
                    if (json.block === "pending_renewal") {
                        toast("You already have a pending chainsaw renewal. Please wait for the update first.");
                        return;
                    }
                    if (json.block === "need_approved_new") {
                        // Show the new modal (instead of toast). User can switch to NEW and keep their values.
                        needApprovedNewModal.style.display = "flex";
                        return;
                    }
                    if (json.offer === "renewal" && type === "new") {
                        offerRenewalModal.style.display = "flex";
                        return;
                    }
                    confirmModal.style.display = "flex";
                } catch (e) {
                    console.error(e);
                    // still allow manual confirm so user gets feedback
                    confirmModal.style.display = "flex";
                }
            });

            // FINAL submit
            document.getElementById("btnOkConfirm")?.addEventListener("click", async () => {
                confirmModal.style.display = "none";
                loading.style.display = "flex";
                try {
                    await doSubmit();
                    toast("Application submitted. We'll notify you once reviewed.");
                    resetForm();
                } catch (e) {
                    console.error(e);
                    toast(e?.message || "Submission failed. Please try again.");
                } finally {
                    loading.style.display = "none";
                }
            });

            async function doSubmit() {
                // Signature capture
                let signatureDataURL = "";
                if (canvas && hasDrawn) signatureDataURL = canvas.toDataURL("image/png");

                const type = activePermitType();
                let firstName, middleName, lastName, sitio_street, barangay, municipality, province, contact_number;
                let permit_number = "",
                    issuance_date = "",
                    expiry_date = "";
                let purpose, brand, model, date_of_acquisition, serial_number, horsepower, maximum_length_of_guide_bar;
                let address_line = "";

                if (type === "renewal") {
                    firstName = v("first-name-r");
                    middleName = v("middle-name-r");
                    lastName = v("last-name-r");
                    address_line = v("address-r");
                    sitio_street = v("street-r");
                    barangay = v("barangay-r");
                    municipality = v("municipality-r");
                    province = v("province-r");
                    contact_number = v("contact-number-r");
                    permit_number = v("permit-number-r");
                    issuance_date = v("issuance-date-r");
                    expiry_date = v("expiry-date-r");
                    purpose = v("purpose-r");
                    brand = v("brand-r");
                    model = v("model-r");
                    date_of_acquisition = v("acquisition-date-r");
                    serial_number = v("serial-number-r");
                    horsepower = v("horsepower-r");
                    maximum_length_of_guide_bar = v("guide-bar-length-r");
                } else {
                    firstName = v("first-name");
                    middleName = v("middle-name");
                    lastName = v("last-name");
                    sitio_street = v("street");
                    barangay = v("barangay");
                    municipality = v("municipality");
                    province = v("province");
                    contact_number = v("contact-number");
                    purpose = v("purpose");
                    brand = v("brand");
                    model = v("model");
                    date_of_acquisition = v("acquisition-date");
                    serial_number = v("serial-number");
                    horsepower = v("horsepower");
                    maximum_length_of_guide_bar = v("guide-bar-length");
                }

                const fullName = `${firstName} ${middleName} ${lastName}`.replace(/\s+/g, " ").trim();

                // Build MHTML doc (Word-opening)
                const sigLocation = "signature.png";
                const hasSignature = !!signatureDataURL;
                const titleLine = type === "renewal" ? "Application for Renewal of Chainsaw Permit" : "Application for New Chainsaw Permit";
                const signatureBlock = hasSignature ?
                    `<div style="margin-top:28px;">
             <img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}"
                  style="display:block;border:1px solid #ddd;padding:4px;border-radius:4px;width:${SIG_WIDTH}px;height:${SIG_HEIGHT}px;" alt="Signature"/>
             <p style="margin-top:6px;">Signature of Applicant</p>
           </div>` :
                    `<div style="margin-top:40px;">
             <div style="border-top:1px solid #000;width:50%;padding-top:3pt;"></div>
             <p>Signature of Applicant</p>
           </div>`;

                const addrJoined = [address_line, sitio_street, barangay, municipality, province].filter(Boolean).join(", ");
                const bodyHtml = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:w="urn:schemas-microsoft-com:office:word"
              xmlns="http://www.w3.org/TR/REC-html40">
          <head><meta charset="UTF-8"><title>Chainsaw Registration Form</title></head>
          <body>
            <div style="text-align:center">
              <p><b>Republic of the Philippines</b></p>
              <p><b>Department of Environment and Natural Resources</b></p>
              <p>Community Environment and Natural Resources Office (CENRO)</p>
              <p>Argao, Cebu</p>
            </div>
            <h3 style="text-align:center;">${titleLine}</h3>

            <p><b>I. APPLICANT INFORMATION</b></p>
            <p>Name: <u>${fullName}</u></p>
            <p>Address: <u>${addrJoined}</u></p>
            <p>Contact Number: <u>${contact_number}</u></p>

            ${type === "renewal" ? `
            <p><b>II. EXISTING CHAINSAW PERMIT INFORMATION</b></p>
            <p>Permit Number: <u>${permit_number}</u></p>
            <p>Date of Original Issuance: <u>${issuance_date}</u></p>
            <p>Expiry Date: <u>${expiry_date}</u></p>
            ` : ""}

            <p><b>${type === "renewal" ? "III" : "II"}. CHAINSAW INFORMATION AND DESCRIPTION</b></p>
            <p>Purpose of Use: <u>${purpose}</u></p>
            <p>Brand: <u>${brand}</u></p>
            <p>Model: <u>${model}</u></p>
            <p>Date of Acquisition: <u>${date_of_acquisition}</u></p>
            <p>Serial Number: <u>${serial_number}</u></p>
            <p>Horsepower: <u>${horsepower}</u></p>
            <p>Maximum Length of Guide Bar: <u>${maximum_length_of_guide_bar}</u></p>

            <p><b>${type === "renewal" ? "IV" : "III"}. DECLARATION AND SUBMISSION</b></p>
            ${signatureBlock}
          </body>
        </html>`.trim();

                const parts = hasSignature ? [{
                    location: sigLocation,
                    contentType: "image/png",
                    base64: (signatureDataURL.split(",")[1] || "")
                }] : [];
                const mhtml = makeMHTML(bodyHtml, parts);
                const docBlob = new Blob([mhtml], {
                    type: "application/msword"
                });
                const docFileName = `${type === "renewal" ? "Chainsaw_Renewal" : "Chainsaw_New"}_${(fullName || "Applicant").replace(/\s+/g, "_")}.doc`;
                const docFile = new File([docBlob], docFileName, {
                    type: "application/msword"
                });

                const fd = new FormData();
                fd.append("permit_type", type);
                fd.append("first_name", firstName);
                fd.append("middle_name", middleName);
                fd.append("last_name", lastName);
                fd.append("sitio_street", sitio_street);
                fd.append("barangay", barangay);
                fd.append("municipality", municipality);
                fd.append("province", province);
                fd.append("contact_number", contact_number);
                if (type === "renewal") {
                    fd.append("permit_number", permit_number);
                    fd.append("issuance_date", issuance_date);
                    fd.append("expiry_date", expiry_date);
                }
                fd.append("purpose", purpose);
                fd.append("brand", brand);
                fd.append("model", model);
                fd.append("date_of_acquisition", date_of_acquisition);
                fd.append("serial_number", serial_number);
                fd.append("horsepower", horsepower);
                fd.append("maximum_length_of_guide_bar", maximum_length_of_guide_bar);

                // generated application document
                fd.append("application_doc", docFile);

                // signature file (optional)
                if (hasSignature) {
                    const sigBlob = dataURLToBlob(signatureDataURL);
                    fd.append("signature_file", new File([sigBlob], "signature.png", {
                        type: "image/png"
                    }));
                }

                // Attach files (Application letter intentionally NOT present)
                const pick = (id) => document.getElementById(id)?.files?.[0] || null;

                if (type === "new") {
                    const files = {
                        chainsaw_cert_terms: pick("file-cert-terms"),
                        chainsaw_cert_sticker: pick("file-cert-sticker"),
                        chainsaw_staff_work: pick("file-memo"),
                        geo_photos: pick("file-geo"),
                        chainsaw_permit_to_sell: pick("file-sell-permit"),
                        chainsaw_business_permit: pick("file-business-permit"),
                        chainsaw_old_registration: pick("file-old-reg"),
                    };
                    Object.entries(files).forEach(([name, file]) => {
                        if (file) fd.append(name, file);
                    });
                } else {
                    const filesRenewal = {
                        chainsaw_cert_terms: pick("file-cert-terms"), // 1.1
                        chainsaw_cert_sticker: pick("file-cert-sticker"), // 1.2
                        chainsaw_staff_work: pick("file-memo"), // 2
                        geo_photos: pick("file-geo"), // 3
                        // (no application letter)
                    };
                    Object.entries(filesRenewal).forEach(([name, file]) => {
                        if (file) fd.append(name, file);
                    });
                }

                const res = await fetch(SAVE_URL, {
                    method: "POST",
                    body: fd,
                    credentials: "include"
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

            // mobile menu toggle
            const mobileToggle = document.querySelector(".mobile-toggle");
            const navContainer = document.querySelector(".nav-container");
            mobileToggle?.addEventListener("click", () => {
                const isActive = navContainer.classList.toggle("active");
                document.body.style.overflow = isActive ? "hidden" : "";
            });
        })();
    </script>

</body>










</html>