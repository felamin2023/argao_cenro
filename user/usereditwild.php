<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Farm Permit</title>
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
            --dark-green: #005117;
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
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
            border: 2px solid var(--dark-green);
            color: var(--dark-green);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--light-gray);
        }

        .btn-primary {
            background: var(--dark-green);
            color: var(--white);
            border: 2px solid var(--dark-green);
        }

        .btn-primary:hover {
            background: #006622;
            border-color: #006622;
        }

        .edit-mode .file-input-label {
    background: #0a192f;
}
        /* Requirements Form */
        .requirements-form {
            margin-top: -1%;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 2px solid var(--dark-green);
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
            border-left: 4px solid var(--dark-green);
        }

        .requirement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-title {
            font-weight: 600;
            color: #0a192f;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirement-number {
            background: var(--dark-green);
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
            background: var(--dark-green);
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
            background: #006622;
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
            color: var(--dark-green);
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
            color: var(--dark-green);
        }

        .form-footer {
            padding: 20px 30px;
            background: var(--light-gray);
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        /* Fee Information */
        .fee-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 81, 23, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--dark-green);
        }

        .fee-info p {
            margin: 5px 0;
            color: var(--dark-green);
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
            background-color: var(--dark-green);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .download-btn:hover {
            background-color: #006622;
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
            border: 2px solid var(--primary-color);
            background-color: white;
            color: var(--dark-green);
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .permit-type-btn.active {
            background-color: var(--primary-color);
            color: white;
        }

        .permit-type-btn:hover {
            background-color: var(--dark-green);
            color: white;
        }

        /* Name fields styling */
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
                   
                    <a href="useraddseed.php" class="dropdown-item">
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
            <a href="useraddwild.php" class="btn btn-outline">
                <i class="fas fa-plus-circle"></i> Add
            </a>
            <a href="usereditwild.php" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewwild.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div>

        <div class="requirements-form edit-mode" id="requirementsForm">
            <div class="form-header">
                <h2>Wildlife Farm Permit - Edit Requirements</h2>
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
                <div class="requirements-list" id="new-requirements">
                    <!-- New Requirements -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 2 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 3 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 4 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 5 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 6 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 7 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                    
                    <!-- Requirement 8 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">8</span>
                                Legal Acquisition of Wildlife:
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="sub-requirement">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Proof of Purchase (Official Receipt/Deed of Sale or Captive Bred Certificate)</p>
                                <div class="file-input-container">
                                    <label for="file-8a" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-8a" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files"></div>
                            </div>
                            <div class="sub-requirement" style="margin-top: 15px;">
                                <p style="margin-bottom: 10px; font-weight: 500;">- Deed of Donation with Notary</p>
                                <div class="file-input-container">
                                    <label for="file-8b" class="file-input-label">
                                        <i class="fas fa-upload"></i> Upload File
                                    </label>
                                    <input type="file" id="file-8b" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <div class="uploaded-files"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Requirement 9 -->
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
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-9" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files"></div>
                        </div>
                    </div>
                </div>

                <!-- Renewal Requirements -->
                <div class="requirements-list" id="renewal-requirements" style="display: none;">
                    <!-- Requirement 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Duly accomplished application form with two recent 2'x2' photo of the applicant
                            </div>
                        </div>
                        <div class="file-upload">
                            <div style="margin-bottom: 15px;">
                                <a href="http://localhost/denr/superadmin/user/form_wild.docx" class="download-btn" id="downloadRenewalForm" download="Wildlife_Renewal_Application_Form.docx">
                                    <i class="fas fa-file-word"></i> Download Application Form (DOCX)
                                </a>
                            </div>
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
                    
                    <!-- Requirement 2 -->
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
                    
                    <!-- Requirement 3 -->
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
                    
                    <!-- Requirement 4 -->
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
                    
                    <!-- Requirement 5 -->
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
                    
                    <!-- Requirement 6 -->
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

                <!-- Fee Information -->
                <div class="fee-info">
                    <p><strong>Application and Processing Fee:</strong> ₱500.00</p>
                    <p><strong>Permit Fee:</strong> ₱2,500.00</p>
                    <p><strong>Total Fee:</strong> ₱3,000.00</p>
                </div>
            </div>
            
            <div class="form-footer">
                <button id="saveBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="wfp_view.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuToggle = document.querySelector('.mobile-toggle');
            const mobileMenu = document.querySelector('.nav-container');
            
            if (menuToggle && mobileMenu) {
                menuToggle.addEventListener('click', () => {
                    mobileMenu.classList.toggle('active');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!mobileMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                        mobileMenu.classList.remove('active');
                    }
                });
            }

            // Permit type selection
            const newBtn = document.querySelector('.permit-type-btn[data-type="new"]');
            const renewalBtn = document.querySelector('.permit-type-btn[data-type="renewal"]');
            const newRequirements = document.getElementById('new-requirements');
            const renewalRequirements = document.getElementById('renewal-requirements');

            if (newBtn && renewalBtn && newRequirements && renewalRequirements) {
                newBtn.addEventListener('click', () => {
                    newBtn.classList.add('active');
                    renewalBtn.classList.remove('active');
                    newRequirements.style.display = 'grid';
                    renewalRequirements.style.display = 'none';
                });

                renewalBtn.addEventListener('click', () => {
                    renewalBtn.classList.add('active');
                    newBtn.classList.remove('active');
                    renewalRequirements.style.display = 'grid';
                    newRequirements.style.display = 'none';
                });
            }

            // File input change handler
            const fileInputs = document.querySelectorAll('.file-input');
            fileInputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'No file chosen';
                    const fileNameSpan = this.parentElement.querySelector('.file-name');
                    if (fileNameSpan) {
                        fileNameSpan.textContent = fileName;
                    }

                    // Create uploaded file element
                    if (e.target.files[0]) {
                        const uploadedFiles = this.parentElement.nextElementSibling;
                        const fileElement = document.createElement('div');
                        fileElement.className = 'file-item';
                        fileElement.innerHTML = `
                            <div class="file-info">
                                <i class="fas fa-file"></i>
                                <span>${e.target.files[0].name}</span>
                            </div>
                            <div class="file-actions">
                                <button class="preview-btn" title="Preview">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        uploadedFiles.appendChild(fileElement);

                        // Add event listeners for preview and delete
                        const previewBtn = fileElement.querySelector('.preview-btn');
                        const deleteBtn = fileElement.querySelector('.delete-btn');

                        previewBtn.addEventListener('click', () => {
                            const file = e.target.files[0];
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const modal = document.getElementById('previewModal');
                                const previewContent = document.getElementById('previewContent');
                                if (modal && previewContent) {
                                    previewContent.src = e.target.result;
                                    modal.style.display = 'flex';
                                }
                            };
                            reader.readAsDataURL(file);
                        });

                        deleteBtn.addEventListener('click', () => {
                            fileElement.remove();
                            this.value = '';
                            if (fileNameSpan) {
                                fileNameSpan.textContent = 'No file chosen';
                            }
                        });
                    }
                });
            });

            // Close modal when clicking outside
            const modal = document.getElementById('previewModal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // Save changes button
            const saveBtn = document.querySelector('.save-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    alert('Changes saved successfully!');
                    window.location.href = 'wfp_view.php';
                });
            }
        });
    </script>
</body>
</html>