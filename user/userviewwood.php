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
    min-width: 320px; /* Increased from 300px */
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
    white-space: nowrap; /* Prevent text wrapping */
}

.dropdown-item i {
    width: 30px;
    font-size: 1.5rem;
    color: var(--primary-color) !important;
    margin-right: 15px;
    flex-shrink: 0; /* Prevent icon from affecting layout */
}

.dropdown-item.active-page {
    background-color: rgb(225, 255, 220);
    color: var(--primary-dark);
    font-weight: bold;
    border-left: 4px solid var(--primary-color);
    padding-left: 21px; /* Adjusted for border */
}

.dropdown-item span {
    overflow: hidden;
    text-overflow: ellipsis; /* Handle overflow with ellipsis */
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
            margin-top:-0.5%;
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
        }

        .requirement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .requirement-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1rem;
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

        /* Uploaded Files Section - Enhanced for Viewing */
        .uploaded-files-container {
            margin-top: 15px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .uploaded-files-title {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .uploaded-files-title i {
            color: var(--primary-color);
        }

        .uploaded-files {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-item {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid #ddd;
            padding: 10px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .file-name {
            font-weight: 500;
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-actions {
            display: flex;
            gap: 10px;
        }

        .file-action-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
        }

        .file-action-btn:hover {
            color: var(--primary-color);
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .status-approved {
            background-color: #e3f7e8;
            color: #2b6625;
        }

        .status-pending {
            background-color: #fff8e6;
            color: #b38b00;
        }

        .status-rejected {
            background-color: #ffebee;
            color: #c62828;
        }

        /* Summary Section */
        .summary-section {
            background: rgba(43, 102, 37, 0.05);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--primary-color);
            grid-column: 1 / -1;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .summary-title {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.2rem;
        }

        .application-status {
            font-weight: 600;
            font-size: 1rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .summary-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .summary-item-title {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .summary-item-value {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.1rem;
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

        .file-preview-frame {
            width: 100%;
            height: 70vh;
            border: none;
            margin-top: 20px;
        }


        
        /* Add new styles for applicant info fields */
.applicant-info {
    background: rgba(43, 102, 37, 0.05);
    border-radius: var(--border-radius);
    padding: 20px;
    margin-bottom: 30px;
    border-left: 4px solid var(--primary-color);
}

.applicant-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.applicant-info-item {
    background: white;
    border-radius: var(--border-radius);
    padding: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.applicant-info-title {
    font-size: 0.9rem;
    color: var(--dark-gray);
    margin-bottom: 5px;
}

.applicant-info-value {
    font-weight: 600;
    color: var(--primary-dark);
    font-size: 1.1rem;
}

        /* Responsive Design */
        @media (max-width: 992px) {
            .mobile-toggle {
                display: block;
            }
            
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
            <a href="useraddwood.php" class="btn btn-outline">
                <i class="fas fa-plus-circle"></i> Add
            </a>
            <a href="usereditwood.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button class="btn btn-primary">
                <i class="fas fa-eye"></i> View
            </button>
        </div>

        <div class="requirements-form">
            <div class="form-header">
                <h2>Wood Processing Plant Permit - View Requirements</h2>
            </div>
            
            <div class="form-body">
                <div class="summary-section">
                    <div class="summary-header">
                        <h3 class="summary-title">Application Summary</h3>
                        <span class="application-status status-badge status-approved">Approved</span>
                    </div>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-item-title">Application Type:</div>
                            <div class="summary-item-value">New Permit</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-title">Date Submitted</div>
                            <div class="summary-item-value">June 15, 2023</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-title">Date Approved</div>
                            <div class="summary-item-value">June 22, 2023</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-title">Permit Number</div>
                            <div class="summary-item-value">WPP-2023-0456</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-title">Valid Until</div>
                            <div class="summary-item-value">June 22, 2024</div>
                        </div>
                    </div>
                </div>

                <div class="applicant-info">
    <div class="applicant-info-grid">
        <div class="applicant-info-item">
            <div class="applicant-info-title">First Name</div>
            <div class="applicant-info-value">Juan</div>
        </div>
        <div class="applicant-info-item">
            <div class="applicant-info-title">Middle Name</div>
            <div class="applicant-info-value">Dela</div>
        </div>
        <div class="applicant-info-item">
            <div class="applicant-info-title">Last Name</div>
            <div class="applicant-info-value">Cruz</div>
        </div>

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
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Application_Form.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Application_Form.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">b</span>
                                <span class="requirement-number renewal-number" style="display:none">b</span>
                                Application fee/permit fee (OR as proof of payment)
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Payment_Receipt.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Payment_Receipt.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">c</span>
                                <span class="requirement-number renewal-number" style="display:none">c</span>
                                Copy of Certificate of Registration, Articles of Incorporation, Partnership or Cooperation
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Certificate_of_Registration.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Certificate_of_Registration.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">d</span>
                                <span class="requirement-number renewal-number" style="display:none">d</span>
                                Authorization issued by the Corporation, Partnership or Association in favor of the person signing the application
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Authorization_Letter.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Authorization_Letter.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">e</span>
                                <span class="requirement-number renewal-number" style="display:none">e</span>
                                Feasibility Study/Business Plan
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Business_Plan.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Business_Plan.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">f</span>
                                <span class="requirement-number renewal-number" style="display:none">f</span>
                                Business Permit
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Business_Permit.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Business_Permit.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">g</span>
                                <span class="requirement-number renewal-number" style="display:none">g</span>
                                Environmental Compliance Certificate (ECC)
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">ECC_Certificate.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="ECC_Certificate.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">h</span>
                                <span class="requirement-number renewal-number" style="display:none">h</span>
                                For individual persons, document reflecting proof of Filipino citizenship such as Birth Certificate or Certificate of Naturalization
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Birth_Certificate.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Birth_Certificate.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">i</span>
                                <span class="requirement-number renewal-number" style="display:none">i</span>
                                Evidence of ownership of machines
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Machine_Ownership.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Machine_Ownership.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">j</span>
                                <span class="requirement-number renewal-number" style="display:none">j</span>
                                GIS generated map with corresponding geo-tagged photos showing the location of WPP
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">GIS_Map.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="GIS_Map.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-image file-icon"></i>
                                        <div class="file-name">Location_Photos.zip</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Location_Photos.zip">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">k</span>
                                <span class="requirement-number renewal-number" style="display:none">k</span>
                                Certification from the Regional Office that the WPP is not within the illegal logging hotspot area
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Certification_Letter.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Certification_Letter.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">l</span>
                                <span class="requirement-number renewal-number" style="display:none">l</span>
                                Proof of sustainable sources of legally cut logs for a period of at least 5 years
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> Uploaded Documents</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Sustainable_Sources.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Sustainable_Sources.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number">m</span>
                                <span class="requirement-number renewal-number" style="display:none">m</span>
                                Supporting Documents
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> 1. Original copy of Log/Veneer/Lumber Supply Contracts</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Supply_Contracts.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Supply_Contracts.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 2. 5% Tree Inventory</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Tree_Inventory.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Tree_Inventory.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 3. Electronic Copy of Inventory Data</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-excel file-icon"></i>
                                        <div class="file-name">Inventory_Data.xlsx</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Inventory_Data.xlsx">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 4. Validation Report</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Validation_Report.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Validation_Report.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 5. Copy of Tenure Instrument and Harvesting Permit</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Harvesting_Permit.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Harvesting_Permit.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 6. Copy of CTPO/PTPR and map</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">CTPO_Documents.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="CTPO_Documents.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 7. Monthly Production and Disposition Report</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Production_Report.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Production_Report.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requirement-item renewal-only" style="display: none;">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number new-number" style="display:none">q</span>
                                <span class="requirement-number renewal-number">q</span>
                                For Importers:
                                <span class="status-badge status-approved">Approved</span>
                            </div>
                        </div>
                        <div class="uploaded-files-container">
                            <h4 class="uploaded-files-title"><i class="fas fa-paperclip"></i> 1. Certificate of Registration as Log/Veneer/Lumber Importer</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Importer_Registration.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Importer_Registration.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 2. Original Copy of Log/Veneer/Lumber Supply Contracts</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Import_Contracts.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Import_Contracts.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 3. Proof of importation</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Import_Documents.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Import_Documents.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <h4 class="uploaded-files-title" style="margin-top: 15px;"><i class="fas fa-paperclip"></i> 4. Monthly Production and Disposition Report</h4>
                            <div class="uploaded-files">
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file-pdf file-icon"></i>
                                        <div class="file-name">Import_Production_Report.pdf</div>
                                    </div>
                                    <div class="file-actions">
                                        <button class="file-action-btn view-file" data-file="Import_Production_Report.pdf">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="file-action-btn">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fee Information -->
                    <div class="fee-info">
                        <p><strong>Application fee for WPP:</strong> ₱600.00</p>
                        <p><strong>Annual/Permit fee:</strong> ₱900.00</p>
                        <p><strong>Performance bond:</strong> ₱6,000.00</p>
                        <p><strong>Total Fee:</strong> ₱7,500.00</p>
                        <p><strong>Payment Status:</strong> <span class="status-badge status-approved">Paid</span></p>
                        <p><strong>Payment Date:</strong> June 15, 2023</p>
                        <p><strong>Reference Number:</strong> PAY-2023-0456</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview-frame" src="about:blank"></iframe>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            // File preview functionality
            const modal = document.getElementById('filePreviewModal');
            const modalFrame = document.getElementById('filePreviewFrame');
            const closeModal = document.querySelector('.close-modal');
            
            // Add click event to all view buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-file')) {
                    const fileName = e.target.closest('.view-file').getAttribute('data-file');
                    document.getElementById('modal-title').textContent = `Preview: ${fileName}`;
                    
                    // For demo, we'll show a placeholder
                    modalFrame.src = "about:blank";
                    modalFrame.srcdoc = `
                        <html>
                            <head>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        display: flex; 
                                        justify-content: center; 
                                        align-items: center; 
                                        height: 100vh; 
                                        margin: 0; 
                                        background-color: #f5f5f5;
                                    }
                                    .preview-content {
                                        text-align: center;
                                        padding: 20px;
                                    }
                                    .file-icon {
                                        font-size: 48px;
                                        color: #2b6625;
                                        margin-bottom: 20px;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="preview-content">
                                    <div class="file-icon">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <h2>${fileName}</h2>
                                    <p>This is a preview of the uploaded file.</p>
                                    <p>In a real application, the actual file content would be displayed here.</p>
                                </div>
                            </body>
                        </html>
                    `;
                    
                    modal.style.display = "block";
                }
            });
            
            // Close modal when clicking X
            closeModal.addEventListener('click', function() {
                modal.style.display = "none";
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            });

            // Initialize existing file items with download functionality
            document.querySelectorAll('.file-action-btn .fa-download').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileName = this.closest('.file-item').querySelector('.file-name').textContent;
                    alert(`In a real application, this would download: ${fileName}`);
                });
            });
        });
    </script>
</body>
</html>