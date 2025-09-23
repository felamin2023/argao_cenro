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
    <title>Wood Processing Plant Permit Application</title>
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
            min-width: 320px;
            /* Increased from 300px */
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem;
            white-space: nowrap;
            /* Prevent text wrapping */
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        .dropdown-item.active-page {
            background-color: rgb(225, 255, 220);
            color: var(--primary-dark);
            font-weight: bold;
            border-left: 4px solid var(--primary-color);
            padding-left: 21px;
            /* Adjusted for border */
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .dropdown-item span {
            overflow: hidden;
            text-overflow: ellipsis;
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
            grid-template-columns: 1fr 1fr;
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
            margin-bottom: 20px;
        }

        .requirement-item:last-child {
            margin-bottom: 0;
        }

        .requirement-item.supporting-docs {
            margin-bottom: 0;
        }

        .requirement-item.renewal-only {
            display: none;
        }

        .requirement-item.renewal-only.active {
            display: block;
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
            line-height: 25px;
            text-align: center;
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
            grid-column: 1 / -1;
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
            .requirements-list {
                grid-template-columns: 1fr;
            }

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

        .sub-requirement {
            margin-top: 15px;
        }

        .sub-requirement:first-child {
            margin-top: 0;
        }

        .sub-requirement:last-child {
            margin-bottom: 0;
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
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item active-page">
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
            <button class="btn btn-primary" id="addFilesBtn">
                <i class="fas fa-plus-circle"></i> Add
            </button>
            <a href="usereditwood.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewwood.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form">
            <div class="form-header">
                <h2>Wood Processing Plant Permit - Requirements</h2>
            </div>

            <div class="form-body">
                <!-- Permit Type Selector -->
                <div class="permit-type-selector">
                    <button class="permit-type-btn active" data-type="new">New Permit</button>
                    <button class="permit-type-btn" data-type="renewal">Renewal</button>
                </div>

                <!-- Name fields -->
                <div class="name-fields">
                    <div class="name-field">
                        <input type="text" placeholder="First Name" required>
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Middle Name">
                    </div>
                    <div class="name-field">
                        <input type="text" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="requirements-list">
                    <!-- Column 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">a</span>
                                <span class="requirement-number renewal-number" style="display:none">a</span>
                                Duly accomplished application form
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-a" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-a"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">b</span>
                                <span class="requirement-number renewal-number" style="display:none">b</span>
                                Application fee/permit fee (OR as proof of payment)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-b" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-b"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">c</span>
                                <span class="requirement-number renewal-number" style="display:none">c</span>
                                Copy of Certificate of Registration, Articles of Incorporation, Partnership or Cooperation
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-c" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-c" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-c"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">d</span>
                                <span class="requirement-number renewal-number" style="display:none">d</span>
                                Authorization issued by the Corporation, Partnership or Association in favor of the person signing the application
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-d" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-d" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-d"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">e</span>
                                <span class="requirement-number renewal-number" style="display:none">e</span>
                                Feasibility Study/Business Plan
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-e" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-e" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-e"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">f</span>
                                <span class="requirement-number renewal-number" style="display:none">f</span>
                                Business Permit
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-f" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-f" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-f"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">g</span>
                                <span class="requirement-number renewal-number" style="display:none">g</span>
                                Environmental Compliance Certificate (ECC)
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-g" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-g" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-g"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">h</span>
                                <span class="requirement-number renewal-number" style="display:none">h</span>
                                For individual persons, document reflecting proof of Filipino citizenship such as Birth Certificate or Certificate of Naturalization
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-h" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-h" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-h"></div>
                        </div>
                    </div>

                    <!-- Column 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">i</span>
                                <span class="requirement-number renewal-number" style="display:none">i</span>
                                Evidence of ownership of machines
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-i" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-i" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-i"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">j</span>
                                <span class="requirement-number renewal-number" style="display:none">j</span>
                                GIS generated map with corresponding geo-tagged photos showing the location of WPP
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-j" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-j" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-j"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">k</span>
                                <span class="requirement-number renewal-number" style="display:none">k</span>
                                Certification from the Regional Office that the WPP is not within the illegal logging hotspot area
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-k" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-k" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-k"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">l</span>
                                <span class="requirement-number renewal-number" style="display:none">l</span>
                                Proof of sustainable sources of legally cut logs for a period of at least 5 years
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-l" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-l" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-l"></div>
                        </div>
                    </div>

                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">m</span>
                                <span class="requirement-number renewal-number" style="display:none">m</span>
                                Supporting Documents
                            </div>
                        </div>
                        <div class="sub-requirement">
                            <p style="margin-bottom: 10px; font-weight: 500;">1. Original copy of Log/Veneer/Lumber Supply Contracts</p>
                            <div class="file-input-container">
                                <label for="file-o2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o2"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom: 10px; font-weight: 500;">2. 5% Tree Inventory</p>
                            <div class="file-input-container">
                                <label for="file-o3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o3"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom: 10px; font-weight: 500;">3. Electronic Copy of Inventory Data</p>
                            <div class="file-input-container">
                                <label for="file-o4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o4"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom: 10px; font-weight: 500;">4. Validation Report</p>
                            <div class="file-input-container">
                                <label for="file-o5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o5"></div>
                        </div>

                        <div class="sub-requirement renewal-only" style="margin-top: 15px; display: none;">
                            <p style="margin-bottom: 10px; font-weight: 500;">5. Copy of Tenure Instrument and Harvesting Permit (if source of raw materials is forest plantations)</p>
                            <div class="file-input-container">
                                <label for="file-o6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o6"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom: 10px; font-weight: 500;">6. Copy of CTPO/PTPR and map (if source of raw materials is private tree plantations)</p>
                            <div class="file-input-container">
                                <label for="file-o7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o7"></div>
                        </div>

                        <div class="sub-requirement renewal-only" style="margin-top: 15px; display: none;">
                            <p style="margin-bottom: 10px; font-weight: 500;">7. Monthly Production and Disposition Report</p>
                            <div class="file-input-container">
                                <label for="file-o8" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o8" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o8"></div>
                        </div>
                    </div>

                    <div class="requirement-item renewal-only" style="display: none;">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number" style="display:none">q</span>
                                <span class="requirement-number renewal-number">q</span>
                                For Importers:
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">1. Certificate of Registration as Log/Veneer/Lumber Importer</p>
                                <div class="file-input-container">
                                    <label for="file-q1" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-q1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-q1"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">2. Original Copy of Log/Veneer/Lumber Supply Contracts</p>
                                <div class="file-input-container">
                                    <label for="file-q2" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-q2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-q2"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">3. Proof of importation</p>
                                <div class="file-input-container">
                                    <label for="file-q3" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-q3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-q3"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">4. Monthly Production and Disposition Report</p>
                                <div class="file-input-container">
                                    <label for="file-q4" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-q4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files" id="uploaded-files-q4"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Information -->
                    <div class="fee-info">
                        <p><strong>Application fee for WPP:</strong> ₱600.00</p>
                        <p><strong>Annual/Permit fee:</strong> ₱900.00</p>
                        <p><strong>Performance bond:</strong> ₱6,000.00</p>
                        <p><strong>Total Fee:</strong> ₱7,500.00</p>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this wood processing plant permit request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <!-- Notification -->
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

            // Permit type selector functionality
            const permitTypeBtns = document.querySelectorAll('.permit-type-btn');
            const renewalOnlyItems = document.querySelectorAll('.renewal-only');

            permitTypeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    permitTypeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    if (this.dataset.type === 'new') {
                        document.querySelectorAll('.new-number').forEach(el => el.style.display = 'inline');
                        document.querySelectorAll('.renewal-number').forEach(el => el.style.display = 'none');
                        renewalOnlyItems.forEach(el => el.style.display = 'none');
                    } else {
                        document.querySelectorAll('.new-number').forEach(el => el.style.display = 'none');
                        document.querySelectorAll('.renewal-number').forEach(el => el.style.display = 'inline');
                        renewalOnlyItems.forEach(el => el.style.display = 'block');
                    }
                });
            });

            // Initialize with New Permit selected
            document.querySelector('.permit-type-btn[data-type="new"]').click();

            // File input handling
            const fileInputs = [{
                    id: 'file-a',
                    field: 'a'
                },
                {
                    id: 'file-b',
                    field: 'b'
                },
                {
                    id: 'file-c',
                    field: 'c'
                },
                {
                    id: 'file-d',
                    field: 'd'
                },
                {
                    id: 'file-e',
                    field: 'e'
                },
                {
                    id: 'file-f',
                    field: 'f'
                },
                {
                    id: 'file-g',
                    field: 'g'
                },
                {
                    id: 'file-h',
                    field: 'h'
                },
                {
                    id: 'file-i',
                    field: 'i'
                },
                {
                    id: 'file-j',
                    field: 'j'
                },
                {
                    id: 'file-k',
                    field: 'k'
                },
                {
                    id: 'file-l',
                    field: 'l'
                },
                {
                    id: 'file-o2',
                    field: 'o2'
                },
                {
                    id: 'file-o3',
                    field: 'o3'
                },
                {
                    id: 'file-o4',
                    field: 'o4'
                },
                {
                    id: 'file-o5',
                    field: 'o5'
                },
                {
                    id: 'file-o6',
                    field: 'o6'
                },
                {
                    id: 'file-o7',
                    field: 'o7'
                },
                {
                    id: 'file-o8',
                    field: 'o8'
                },
                {
                    id: 'file-q1',
                    field: 'q1'
                },
                {
                    id: 'file-q2',
                    field: 'q2'
                },
                {
                    id: 'file-q3',
                    field: 'q3'
                },
                {
                    id: 'file-q4',
                    field: 'q4'
                }
            ];

            let selectedFiles = {};

            fileInputs.forEach(input => {
                const fileInput = document.getElementById(input.id);
                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        const file = this.files[0];
                        this.parentElement.querySelector('.file-name').textContent = file ? file.name : 'No file chosen';
                        if (file) {
                            selectedFiles[input.field] = file;
                        } else {
                            selectedFiles[input.field] = null;
                        }
                    });
                }
            });

            // Confirmation modal logic
            const confirmModal = document.getElementById('confirmModal');
            const closeConfirmModal = document.getElementById('closeConfirmModal');
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');

            const submitApplicationBtn = document.getElementById('submitApplication');
            if (submitApplicationBtn) {
                submitApplicationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Validate fields
                    const firstName = document.querySelector('.name-fields input[placeholder="First Name"]').value.trim();
                    const lastName = document.querySelector('.name-fields input[placeholder="Last Name"]').value.trim();
                    if (!firstName || !lastName) {
                        alert('First name and last name are required.');
                        return;
                    }

                    // Check if required files are uploaded based on permit type
                    const isNewPermit = document.querySelector('.permit-type-btn[data-type="new"]').classList.contains('active');
                    const requiredFiles = isNewPermit ? ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'o2', 'o3', 'o4', 'o5', 'o7'] : ['a', 'b', 'c', 'd', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'o2', 'o3', 'o4', 'o5', 'o6', 'o7', 'o8'];

                    let allRequiredFilesUploaded = true;
                    requiredFiles.forEach(field => {
                        if (!selectedFiles[field]) {
                            allRequiredFilesUploaded = false;
                            const fileId = 'file-' + field.replace('_', '-');
                            const requirementItem = document.getElementById(fileId)?.closest('.requirement-item');
                            if (requirementItem) {
                                requirementItem.style.borderLeft = '4px solid #ff4757';
                                setTimeout(() => {
                                    requirementItem.style.borderLeft = '4px solid var(--primary-color)';
                                }, 2000);
                            }
                        }
                    });

                    if (!allRequiredFilesUploaded) {
                        alert('Please upload all required files before submitting.');
                        return;
                    }

                    if (confirmModal) confirmModal.style.display = 'block';
                });
            }

            if (closeConfirmModal) {
                closeConfirmModal.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';
                });
            }
            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';
                });
            }

            if (confirmSubmitBtn) {
                confirmSubmitBtn.addEventListener('click', function() {
                    if (confirmModal) confirmModal.style.display = 'none';

                    // Prepare form data
                    const firstName = document.querySelector('.name-fields input[placeholder="First Name"]').value.trim();
                    const middleName = document.querySelector('.name-fields input[placeholder="Middle Name"]').value.trim();
                    const lastName = document.querySelector('.name-fields input[placeholder="Last Name"]').value.trim();
                    const permitType = document.querySelector('.permit-type-btn.active').dataset.type;

                    const formData = new FormData();
                    formData.append('first_name', firstName);
                    formData.append('middle_name', middleName);
                    formData.append('last_name', lastName);
                    formData.append('permit_type', permitType);

                    // Append all files
                    Object.entries(selectedFiles).forEach(([field, file]) => {
                        if (file) {
                            formData.append(field, file);
                        }
                    });

                    // Show loading state
                    submitApplicationBtn.disabled = true;
                    submitApplicationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

                    // Update the fetch call in your frontend code:
                    fetch('../backend/users/addwood.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(async response => {
                            const text = await response.text();
                            try {
                                const data = JSON.parse(text);
                                if (!response.ok) {
                                    throw new Error(data.errors ? data.errors.join('\n') : 'Server error');
                                }
                                return data;
                            } catch (e) {
                                // Handle cases where the response isn't valid JSON
                                if (text.includes('Maximum number of allowable file uploads')) {
                                    throw new Error('Server configuration error: Too many files uploaded at once. Please contact support.');
                                }
                                throw new Error(text || 'Server error');
                            }
                        })
                        .then(data => {
                            if (data.success) {
                                // Clear form and show success
                                document.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
                                document.querySelectorAll('input[type="file"]').forEach(input => {
                                    input.value = '';
                                    input.parentElement.querySelector('.file-name').textContent = 'No file chosen';
                                });
                                selectedFiles = {};
                                showProfileNotification(data.message || 'Application submitted successfully!');
                            } else {
                                throw new Error(data.errors ? data.errors.join('\n') : 'Submission failed');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert(error.message || 'Network error. Please try again.');
                        })
                        .finally(() => {
                            submitApplicationBtn.disabled = false;
                            submitApplicationBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
                        });
                });
            }

            function showProfileNotification(message) {
                const notif = document.getElementById('profile-notification');
                if (!notif) return;
                notif.textContent = message;
                notif.style.display = 'block';
                notif.style.opacity = '1';
                setTimeout(() => {
                    notif.style.opacity = '0';
                    setTimeout(() => {
                        notif.style.display = 'none';
                        notif.style.opacity = '1';
                    }, 400);
                }, 2200);
            }
        });
    </script>
</body>

</html>