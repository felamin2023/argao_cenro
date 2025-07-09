<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
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
            margin-top:-2%;
            padding: 30px;
           
        }

        /* Notification Detail Page */
        .notification-detail-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 2px solid darkgreen;
        }

      
        .notification-detail-header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 25px 30px;
            position: relative;
            border-bottom: 1px solid darkgreen;
        }

            .notification-detail-title {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 5px;
                text-align: left;
                width: 100%;
            }

        .notification-detail-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }

            .notification-status {
                position: absolute;
                top: 25px;
                right: 10px;
                background: var(--primary-dark);
                color: var(--white);
                padding: 5px 15px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.9rem;
            }

        .notification-detail-body {
            padding: 30px;
        }

        .notification-detail-message {
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 30px;
            color: #444;
        }

        .notification-detail-meta {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .meta-item {
            display: flex;
            margin-bottom: 10px;
        }

        .meta-item:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            font-weight: 600;
            min-width: 150px;
            color: var(--primary-dark);
        }

        .meta-value {
            color: #555;
        }

        .notification-actions {
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            border-top: 1px solid var(--medium-gray);
        }

        .btn {
            padding: 12px 25px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            background: var(--primary-light);
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
            
            .notification-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
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

    <div class="main-container">
        <!-- Notification Detail Content -->
        <div class="notification-detail-container">
            <div class="notification-detail-header" style="background-color: white; color: var(--primary-color);">
                <div>
                    <h1 class="notification-detail-title" style="color: var(--primary-color);">Chainsaw Renewal Approved</h1>
                    
                </div>
                <span class="notification-status">Approved</span>
            </div>
            
            <div class="notification-detail-body">
                <div class="notification-detail-message">
                    <p>Your chainsaw renewal application has been approved by the DENR CENRO Office. You may now proceed to claim your permit at our office during regular business hours (Monday to Friday, 8:00 AM to 5:00 PM). Please bring your valid ID and this notification for verification.</p>
                    
                    <p>Your new permit will be valid for one year from the date of issuance. Thank you for complying with DENR regulations.</p>
                </div>
                
                <div class="notification-detail-meta">
                    <div class="meta-item">
                        <span class="meta-label">Application Date:</span>
                        <span class="meta-value">May 1, 2025</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Processing Officer: </span>
                        <span class="meta-value">Juan Dela Cruz (CENRO Officer)</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Contact Information: </span>
                        <span class="meta-value">cenroargao@denr.gov.ph</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Office Location: </span>
                        <span class="meta-value">Lamacan, Argao, Cebu</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Valid Until: </span>
                        <span class="meta-value">May 1, 2026</span>
                    </div>
                </div>
                
                
            </div>
        </div>
    </div>

    <script>

document.getElementById('datetime').value = new Date().toISOString().slice(0, 16);
        
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
        document.addEventListener('DOMContentLoaded', function() {
            // Dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');
            
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                // Show menu on hover
                dropdown.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = 'translateY(0)';
                });
                
                // Hide menu when leaving
                dropdown.addEventListener('mouseleave', () => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = 'translateY(10px)';
                });
            });
            
            // Mark all notifications as read
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelector('.badge').style.display = 'none';
                    
                    // Show confirmation
                    const confirmation = document.createElement('div');
                    confirmation.textContent = 'All notifications marked as read';
                    confirmation.style.position = 'fixed';
                    confirmation.style.bottom = '20px';
                    confirmation.style.right = '20px';
                    confirmation.style.backgroundColor = 'var(--primary-color)';
                    confirmation.style.color = 'white';
                    confirmation.style.padding = '10px 20px';
                    confirmation.style.borderRadius = 'var(--border-radius)';
                    confirmation.style.boxShadow = 'var(--box-shadow)';
                    confirmation.style.zIndex = '2000';
                    document.body.appendChild(confirmation);
                    
                    setTimeout(() => {
                        confirmation.style.opacity = '0';
                        confirmation.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            document.body.removeChild(confirmation);
                        }, 300);
                    }, 3000);
                });
            }
            
            // Mobile menu toggle (would be implemented for mobile views)
            const mobileToggle = document.createElement('button');
            mobileToggle.className = 'mobile-toggle';
            mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.querySelector('header').prepend(mobileToggle);
            
            mobileToggle.addEventListener('click', () => {
                document.querySelector('.nav-container').classList.toggle('active');
            });
        });
    });
    </script>
</body>
</html>