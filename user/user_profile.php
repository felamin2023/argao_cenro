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

           /* Updated active styles for nav-icon */
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

        /* Main Content Styles */
    
        
       
      


        /* Responsive adjustments */
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
            
            .stats-grid, .species-grid {
                grid-template-columns: 1fr;
            }
            
            .header-left .logo img {
                height: 32px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .section-header h2 {
                font-size: 18px;
            }

          

      
        }
        /* IMPROVED Profile Section - MODIFIED for white background */
        .profile-container {
            max-width: 1000px;
            margin: 1px auto 30px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            border: 2px solid #1e4a1a;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

      
        .profile-container:hover {
            transform: translateY(-5px);
        }

        .profile-header {
            background: rgb(201, 255, 196);  
            backdrop-filter: blur(1px);
            color: var(--primary-color);
            padding: 30px 30px 70px;
            text-align: center;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #eee;
        }

        .profile-title {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            color: var(--primary-color);
        }

        .profile-subtitle {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 500;
            margin-top: 5px;
            color: black;
        }

        .profile-body {
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: white;
        }

        .profile-picture-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin-bottom: 40px;
            margin-top: -90px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--primary-dark);
            z-index: 10;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            display: none;
        }

        .profile-picture-placeholder {
            font-size: 60px;
            color: #9e9e9e;
        }

        .profile-upload-icon {
            position: absolute;
            bottom: 0;
            right: 10px;
            background: var(--primary-dark);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            border: 3px solid white;
            display: none;
        }

        .profile-upload-icon:hover {
            background: var(--primary-color);
            transform: scale(1.15);
        }

        #profile-upload-input {
            display: none;
        }

        .profile-info {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Improved two-column layout for profile info */
        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px 40px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .profile-info-grid {
                grid-template-columns: 1fr;
                gap: 15px 0;
            }
        }

        .profile-info-item {
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }

        .profile-info-item:hover {
            transform: translateY(-3px);
        }

        .profile-info-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: block;
            text-align: left;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-info-value {
            color: black;
            font-size: 16px;
            font-weight: 500;
            padding: 12px 16px;
            border: 1px solid rgb(158, 168, 158);
            border-radius: 8px;
            display: flex;
            align-items: center;
            min-height: 50px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .profile-info-value input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 16px;
            font-weight: 500;
            color: black;
            padding: 0;
            pointer-events: none;
            cursor: default;
        }

        @media (max-width: 768px) {
            .profile-info-value input {
                width: 30vw;
                max-width: 100%;
            }

            .profile-container {
                margin-left: 0.2in;
                margin-right: 0.2in;
            } 
        }

        .profile-info-value input.editable {
            pointer-events: auto;
            cursor: text;
            background-color: #f8f8f8;
            padding: 8px;
            border-radius: 4px;
            
        }

        .edit-mode .profile-info-value {
            background-color: #f8f8f8;
        }

        .profile-actions {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
            width: 100%;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            display: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--primary-dark);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
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
                <div class="nav-icon active">
                        <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item active-page">
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

    <!-- Profile Content -->
    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">User Profile</h1>
            <p class="profile-subtitle">Manage your Account Information</p>
        </div>
        
        <div class="profile-body">
            <div class="profile-picture-container">
                <img src="default-profile.jpg" alt="Profile Picture" class="profile-picture" id="profile-picture">
                <div class="profile-picture-placeholder" id="profile-placeholder">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-upload-icon" id="profile-upload-btn">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            
            <input type="file" id="profile-upload-input" accept="image/*">
            
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <div class="profile-info-label">First Name:</div>
                    <div class="profile-info-value">
                        <input type="text" id="first-name" value="Juan" readonly>
                    </div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Last Name:</div>
                    <div class="profile-info-value">
                        <input type="text" id="last-name" value="Dela Cruz" readonly>
                    </div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Username:</div>
                    <div class="profile-info-value">
                        <input type="text" id="username" value="admin.juan" readonly>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">Age:</div>
                    <div class="profile-info-value">
                        <input type="number" id="age" value="35" min="18" max="100" readonly>
                    </div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Email:</div>
                    <div class="profile-info-value">
                        <input type="email" id="email" value="admin.juan@example.com" readonly>
                    </div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Phone Number:</div>
                    <div class="profile-info-value">
                        <input type="tel" id="phone" value="+639123456789" readonly>
                    </div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Old Password:</div>
                    <div class="profile-info-value">
                        <input type="password" id="old-password">
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">New Password:</div>
                    <div class="profile-info-value">
                        <input type="password" id="new-password">
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">Confirm Password:</div>
                    <div class="profile-info-value">
                        <input type="password" id="confirm-password">
                    </div>
                </div>
            </div>
            
            <div class="profile-actions">
                <button class="btn btn-secondary" id="edit-btn">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-primary" id="update-profile-btn">
                    <i class="fas fa-save"></i> Update
                </button>
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
                menu.style.transform = menu.classList.contains('center') 
                    ? 'translateX(-50%) translateY(0)' 
                    : 'translateY(0)';
            });
            
            // Hide menu when leaving both button and menu
            dropdown.addEventListener('mouseleave', (e) => {
                // Check if we're leaving the entire dropdown area
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
            
            // Additional check for menu mouseleave
            menu.addEventListener('mouseleave', (e) => {
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
        });
        
        // Close dropdowns when clicking outside (for mobile)
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
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

        // Profile picture upload functionality
        const profileUploadBtn = document.getElementById('profile-upload-btn');
        document.getElementById('profile-upload-input').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const profilePic = document.getElementById('profile-picture');
                    const placeholder = document.getElementById('profile-placeholder');
                    
                    profilePic.src = event.target.result;
                    profilePic.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                
                reader.readAsDataURL(file);
            }
        });

        // Edit button functionality
        const editBtn = document.getElementById('edit-btn');
        const updateBtn = document.getElementById('update-profile-btn');
        const profileBody = document.querySelector('.profile-body');
        const inputs = document.querySelectorAll('.profile-info-value input');
        const profileUploadIcon = document.getElementById('profile-upload-btn');
        
        // Initially hide the update button and upload icon
        updateBtn.style.display = 'none';
        profileUploadIcon.style.display = 'none';

        editBtn.addEventListener('click', function() {
            // Toggle edit mode
            profileBody.classList.add('edit-mode');
            
            // Make all inputs editable
            inputs.forEach(input => {
                input.readOnly = false;
                input.classList.add('editable');
            });
            
            // Show the update button and profile upload icon
            updateBtn.style.display = 'inline-flex';
            profileUploadIcon.style.display = 'flex';
            
            // Hide the edit button
            editBtn.style.display = 'none';
            
            // Show the profile upload button
            document.getElementById('profile-upload-btn').style.display = 'flex';
        });

        // Update profile button functionality
        updateBtn.addEventListener('click', function() {
            // Get all input values
            const firstName = document.getElementById('first-name').value;
            const lastName = document.getElementById('last-name').value;
            const username = document.getElementById('username').value;
            const age = document.getElementById('age').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const oldPassword = document.getElementById('old-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            // Simple validation
            if (!firstName || !lastName || !username || !email) {
                alert('Please fill in all required fields!');
                return;
            }

            if (newPassword && newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }

            // Here you would typically send this data to the server
            // For this example, we'll just show a success message
            alert('Profile updated successfully!');
            
            // Exit edit mode
            profileBody.classList.remove('edit-mode');
            inputs.forEach(input => {
                input.readOnly = true;
                input.classList.remove('editable');
            });
            
            // Hide the update button and profile upload icon
            updateBtn.style.display = 'none';
            profileUploadIcon.style.display = 'none';
            
            // Show the edit button again
            editBtn.style.display = 'inline-flex';
            
            // You might want to redirect or reload the page after successful update
            // window.location.reload();
        });

        // Profile upload button click handler
        profileUploadIcon.addEventListener('click', function() {
            document.getElementById('profile-upload-input').click();
        });
    });
    </script>
</body>
</html>