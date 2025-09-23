<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            padding-top: 80px;
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

        /* Navigation Items - Larger Icons */
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
            max-width: 90vw;
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
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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

        /* Notifications Container */
        .notifications-container {
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        /* Notifications Header */
        .notifications-header {
            background-color: white;
            color: black;
            padding: 20px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #000;
        }

        /* Notification Tabs - Modified to stay side by side */
        .notification-tabs {
            display: flex;
            background-color: #f5f5f5;
            border-bottom: 1px solid #ddd;
            flex-wrap: nowrap; /* Prevent wrapping */
        }

        .tab {
            flex: 1; /* Equal width */
            min-width: 0; /* Allow flex items to shrink */
            padding: 15px 0;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            color: #333;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden; /* Hide overflow */
            text-overflow: ellipsis; /* Add ellipsis if text is too long */
        }

        .tab:hover {
            background-color: #e9e9e9;
        }

        .tab.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
        }

        /* Notification count as icon */
        .tab-badge {
            margin-top: -5%;
            display: inline-block;
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-left: 1px;
            vertical-align: middle;
        }

        /* Notification List */
        .notification-list {
            background-color: white;
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.unread {
            background-color: #f0fff0;
            border-left: 4px solid var(--primary-color);
        }

        .notification-title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }

        .notification-icon {
            margin-right: 10px;
            color: var(--primary-color);
            font-size: 20px;
        }

        .notification-content {
            color: #555;
            margin-bottom: 10px;
            font-size: 16px;
            line-height: 1.5;
            padding-left: 30px;
        }

        .notification-time {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
            padding-left: 30px;
        }

        .notification-actions {
            display: flex;
            gap: 15px;
            padding-left: 30px;
        }

        .action-button {
            padding: 8px 15px;
            border: 1px solid var(--primary-color);
            background-color: white;
            color: var(--primary-color);
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Mark all as read button */
        .mark-all-button {
            text-align: right;
            padding: 15px 20px;
            background-color: #f5f5f5;
            border-top: 1px solid #ddd;
        }

        .mark-all-button button {
            padding: 8px 15px;
            background-color: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .mark-all-button button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 10px;
            box-sizing: border-box;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 24px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .modal-body strong {
            color: var(--primary-color);
        }

        .modal-footer {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .close-modal:hover {
            color: #333;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .notifications-container {
                width: 95%;
                margin: 20px auto;
            }

            .notifications-header {
                font-size: 20px;
                padding: 10px;
            }

            .notification-tabs {
                flex-direction: row; /* Keep tabs in a row */
                overflow-x: auto; /* Add horizontal scrolling if needed */
                -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            }

            .tab {
                flex: none; /* Don't grow or shrink */
                width: 50%; /* Each tab takes half width */
                font-size: 16px;
                padding: 10px;
                white-space: nowrap;
            }

            .tab-badge {
                width: 20px;
                height: 20px;
                line-height: 20px;
                font-size: 11px;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-title {
                font-size: 16px;
            }

            .notification-content {
                font-size: 14px;
                padding-left: 20px;
            }

            .notification-time {
                font-size: 12px;
                padding-left: 20px;
            }

            .notification-actions {
                flex-direction: row;
                gap: 10px;
                padding-left: 20px;
            }

            .action-button {
                width: auto;
            }

            .mark-all-button {
                text-align: right;
            }

            .mark-all-button button {
                width: auto;
            }

            /* Modal */
            .modal-content {
                width: 90%;
                padding: 20px;
            }

            .modal-header h2 {
                font-size: 20px;
            }

            .modal-body p {
                font-size: 14px;
            }

            .close-modal {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .tab {
                font-size: 14px;
                padding: 8px 0;
            }

            .notification-title {
                font-size: 14px;
            }

            .notification-content {
                font-size: 12px;
                padding-left: 15px;
            }

            .notification-time {
                font-size: 10px;
                padding-left: 15px;
            }

            .action-button {
                font-size: 12px;
                padding: 6px 10px;
            }

            .notification-actions {
                gap: 8px;
                padding-left: 15px;
            }
        }

        /* For very small screens where tabs might still not fit */
        @media (max-width: 360px) {
            .tab {
                font-size: 12px;
                padding: 8px 0;
            }
            
            .tab-badge {
                width: 18px;
                height: 18px;
                line-height: 18px;
                font-size: 10px;
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
            <div class="nav-icon active">
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

<!-- Notifications Content -->
<div class="notifications-container">
    <div class="notifications-header">NOTIFICATIONS</div>

    <div class="notification-tabs">
        <div id="all-tab" class="tab active">All Notifications</div>
        <div id="unread-tab" class="tab">Unread <span class="tab-badge">1</span></div>
    </div>

    <div id="all-notifications" class="notification-list">
        <!-- Single Tree Cutting Notification -->
        <div class="notification-item unread" id="tree-cutting-notification">
            <div class="notification-title">
                <div class="notification-icon"><i class="fas fa-tree"></i></div>
               Chainsaw Renewal Status
            </div>
            <div class="notification-content">
            Chainsaw Renewal has been approved.
            </div>
            <div class="notification-time">Today, 10:30 AM</div>
            <div class="notification-actions">
                <button class="action-button view-details-btn">View Details</button>
                <button class="action-button mark-read-btn">Mark as Read</button>
            </div>
        </div>
    </div>

    <div id="unread-notifications" class="notification-list" style="display: none;">
        <!-- Same notification appears in unread tab -->
        <div class="notification-item unread" id="tree-cutting-notification-unread">
            <div class="notification-title">
                <div class="notification-icon"><i class="fas fa-tree"></i></div>
                Chainsaw Renewal Status
            </div>
            <div class="notification-content">
            Chainsaw Renewal has been approved.
            </div>
            <div class="notification-time">Today, 10:30 AM</div>
            <div class="notification-actions">
                <button class="action-button view-details-btn">View Details</button>
                <button class="action-button mark-read-btn">Mark as Read</button>
            </div>
        </div>
    </div>
    
    <div class="mark-all-button">
        <button id="mark-all-read">✓ Mark all as read</button>
    </div>
</div>

<!-- Modal for Notification Details -->
<div class="modal" id="notification-modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="modal-header">
            <h2>Chainsaw Renewal Approval</h2>
        </div>
        <div class="modal-body">
            <p><strong>Category:</strong> Chainsaw Permit</p>
            <p><strong>Received:</strong> 10 minutes ago</p>
            
            <h3>Chainsaw Registration Renewal Approved</h3>
            
            <p>Your chainsaw renewal application has been approved by the DENR.</p>
            
            <p><strong>Chainsaw Model:</strong> STIHL MS 660</p>
            <p><strong>Serial Number:</strong> ST660123456</p>
            <p><strong>Date Approved:</strong> June 15, 2023</p>
            <p><strong>Valid Until:</strong> June 15, 2024</p>
            <p><strong>Approved By:</strong> DENR Regional Office</p>
            <p><strong>Next Steps:</strong> You may now claim your renewed chainsaw permit at the DENR office.</p>
        </div>
       
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

    // Tab switching
    const allTab = document.getElementById('all-tab');
    const unreadTab = document.getElementById('unread-tab');
    const allContent = document.getElementById('all-notifications');
    const unreadContent = document.getElementById('unread-notifications');

    allTab.addEventListener('click', function () {
        allTab.classList.add('active');
        unreadTab.classList.remove('active');
        allContent.style.display = 'block';
        unreadContent.style.display = 'none';
    });

    unreadTab.addEventListener('click', function () {
        unreadTab.classList.add('active');
        allTab.classList.remove('active');
        unreadContent.style.display = 'block';
        allContent.style.display = 'none';
    });

    // Modal functionality
    const modal = document.getElementById('notification-modal');
    const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
    const closeModal = document.querySelector('.close-modal');

    viewDetailsBtns.forEach((btn) => {
        btn.addEventListener('click', function () {
            modal.style.display = 'flex';
        });
    });

    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Mark all as read functionality
    const markAllRead = document.getElementById('mark-all-read');
    markAllRead.addEventListener('click', function () {
        document.querySelectorAll('.notification-item.unread').forEach((item) => {
            item.classList.remove('unread');
        });
        updateUnreadCount();
    });

    // Function to update unread count
    function updateUnreadCount() {
        const unreadCount = document.querySelectorAll('.notification-item.unread').length;
        const badge = document.querySelector('.tab-badge');
        const navBadge = document.querySelector('.badge');

        badge.textContent = unreadCount;
        navBadge.textContent = unreadCount;

        if (unreadCount === 0) {
            badge.style.display = 'none';
            navBadge.style.display = 'none';
        } else {
            badge.style.display = 'inline-block';
            navBadge.style.display = 'flex';
        }
    }
});
</script>
</body>
</html>