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
        .main-content {
            padding: 90px 40px 40px 40px;
            max-width: 1200px;
            margin: 0 auto;
            margin-top: -8%;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Page Header */
        .page-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            border-left: 4px solid var(--primary-color);
        }

        .page-title {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            font-size: 32px;
        }

        .page-description {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            border-top: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: var(--primary-color);
            font-size: 16px;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .stat-card .stat-description {
            font-size: 14px;
            color: #666;
        }

        /* Collapsible Sections */
        .collapsible-section {
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            background: white;
            border: 1px solid #e0e0e0;
        }

        .section-header {
            padding: 18px 25px;
            background-color: white;
            color: var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border-bottom: 1px solid #e0e0e0;
        }

        .section-header:hover {
            background-color: #f9f9f9;
        }

        .section-header h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
        }

        .section-header h2 i {
            margin-right: 12px;
            font-size: 1.1em;
            color: var(--primary-color);
        }

        .toggle-icon {
            transition: transform 0.3s ease;
            font-size: 1.2em;
            color: var(--primary-color);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }

        .section-content-inner {
            padding: 25px;
        }

        .collapsible-section.active .section-content {
            max-height: 2000px;
        }

        .collapsible-section.active .toggle-icon {
            transform: rotate(180deg);
        }

        /* Content Styles */
        p {
            margin-bottom: 20px;
            color: #555;
            line-height: 1.6;
        }

        /* Threat List */
        .threat-list {
            list-style-type: none;
            padding-left: 0;
        }

        .threat-item {
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: rgba(30, 74, 26, 0.05);
            border-left: 4px solid var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 4px;
            color: var(--primary-dark);
        }

        .threat-item i {
            color: var(--primary-dark);
        }

        /* Species Grid Styles */
        .species-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin: 20px 0;
        }

        .species-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 1px solid #e0e0e0;
        }

        .species-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .species-header {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .species-name {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
        }

        .species-name span {
            font-weight: normal;
            font-style: italic;
            font-size: 14px;
            opacity: 0.9;
        }

        .species-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background: #ffeeba;
            color: #856404;
        }

        .species-status.endangered {
            background: #f8d7da;
            color: #721c24;
        }

        .species-status.threat {
            background: #fff3cd;
            color: #856404;
        }

        .species-body {
            padding: 15px;
        }

        .species-body p {
            margin-bottom: 10px;
        }

        .species-body strong {
            color: var(--primary-dark);
        }

        /* Image Styles */
        .species-image {
            width: 100%;
            max-width: 800px;
            height: auto;
            border-radius: var(--border-radius);
            margin: 20px auto;
            box-shadow: var(--box-shadow);
            display: block;
        }

        .image-caption {
            text-align: center;
            font-style: italic;
            color: #666;
            margin-top: -15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Button Styles */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

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

            .filter-container {
                margin-top: -11%;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 10px;
            }

            .filter-group {
                width: auto;
                flex-direction: row;
                gap: 10px;
            }

            .filter-dropdown {
                width: auto;
            }

            .filter-content {
                width: auto;
            }

            .apply-filter-btn {
                width: auto;
                justify-content: center;
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
                    <a href="user_login.php" class="dropdown-item">
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
                        <a href="user_home.php" class="filter-item">All Categories</a>
                        <a href="mpa-management.php" class="filter-item">MPA Management</a>
                        <a href="habitat.php" class="filter-item">Habitat Assessment</a>
                        <a href="species.php" class="filter-item active">Species Monitoring</a>
                        <a href="reports.php" class="filter-item">Reports & Analytics</a>
                        
                    </div>
                </div>
                
                <button class="apply-filter-btn">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </div>


        
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-fish"></i> Coastal and Marine Species Monitoring
            </h1>
            <p class="page-description">
                Tracking and conservation of key marine species across protected areas in the Philippines. 
                Data collected supports the Coastal and Marine Ecosystems Management Program (CMEMP) for biodiversity conservation.
            </p>
        </div>

        <!-- Species Monitoring Overview Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-clipboard-check"></i> Species Monitoring Overview</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>
                        The CMEMP includes regular monitoring of marine species to assess population health, identify threats, 
                        and implement conservation measures. Monitoring activities are conducted through scientific surveys, 
                        community reporting, and technological tools.
                    </p>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Protected Areas Monitored</h3>
                            <div class="stat-value">18/18</div>
                            <p class="stat-description">Target achievement for 2020</p>
                        </div>
                        <div class="stat-card">
                            <h3>Water Quality Sites</h3>
                            <div class="stat-value">15</div>
                            <p class="stat-description">Monitoring stations assessed</p>
                        </div>
                        <div class="stat-card">
                            <h3>MPA Networks</h3>
                            <div class="stat-value">47</div>
                            <p class="stat-description">Identified for species protection</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Species Monitoring Activities Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-water"></i> Key Species Monitoring Activities</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="species-grid">
                        <!-- Giant Clams Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Giant Clams <span>(Tridacna gigas)</span></h3>
                                <span class="species-status">Vulnerable</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Twin Rocks Sanctuary, Mabini, Batangas (Region 4A)</p>
                                <p><strong>Activities:</strong> Restocking program with survival rate and growth pattern monitoring</p>
                                <p><strong>Threats:</strong> Poaching observed in Northern Sierra Madre Natural Park (Region 2)</p>
                            </div>
                        </div>
                        
                        <!-- Marine Turtles Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Marine Turtles <span>(Cheloniidae/Dermochelyidae)</span></h3>
                                <span class="species-status endangered">Endangered</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Cuatro Islas Protected Landscape and Seascape (Region 8)</p>
                                <p><strong>Activities:</strong> Voluntary turnover and release by Land of Paradise Farmers Association</p>
                                <p><strong>Conservation:</strong> Community-based protection initiatives</p>
                            </div>
                        </div>
                        
                        <!-- COTS Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Crown-of-Thorns Starfish <span>(Acanthaster planci)</span></h3>
                                <span class="species-status threat">Outbreak Control</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Olango Island Wildlife Sanctuary (Region 7)</p>
                                <p><strong>Activities:</strong> Removal program with boundary delineation and marker installation</p>
                                <p><strong>Impact:</strong> Prevents rapid destruction of coral reefs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Threats and Conservation Challenges Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Threats and Conservation Challenges</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <ul class="threat-list">
                        <li class="threat-item">
                            <i class="fas fa-trash"></i>
                            <strong>Poaching:</strong> Empty shells of giant clams found scattered in Northern Sierra Madre Natural Park waters (Region 2)
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-thermometer-full"></i>
                            <strong>Coral Bleaching:</strong> Completely bleached bubble-tip sea anemone found during validation in BBBIDA MPAN waters (Region 1)
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-ship"></i>
                            <strong>Tourism Pressure:</strong> Activities like bathing, boating, and snorkeling impacting sensitive species habitats
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-water"></i>
                            <strong>Sedimentation:</strong> Influx of riverine sediments affecting coral areas in Bacolod-Kauswagan PLS
                        </li>
                    </ul>
                    
                    <img src="coral.png" alt="Coral reef monitoring" class="species-image">
                    <p class="image-caption">Figure 11.9: Coral reef monitoring using photo transect method in BBBIDA MPAN, Region 1</p>
                </div>
            </div>
        </div>

        <!-- Protection and Conservation Measures Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-shield-alt"></i> Protection and Conservation Measures</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>MPAs Maintained</h3>
                            <div class="stat-value">38</div>
                            <p class="stat-description">NIPAS MPAs with protection activities</p>
                        </div>
                        <div class="stat-card">
                            <h3>Patrolling</h3>
                            <div class="stat-value">100%</div>
                            <p class="stat-description">Regular surveillance conducted</p>
                        </div>
                        <div class="stat-card">
                            <h3>Community Engagement</h3>
                            <div class="stat-value">114 POs</div>
                            <p class="stat-description">People's Organizations involved</p>
                        </div>
                    </div>
                    
                    <p>
                        Maintenance and protection activities in 2020 included patrolling, habitat surveillance, 
                        direct conservation activities, and repair of signages/equipment across 38 NIPAS MPAs 
                        (32 Legislated, 4 Proclaimed and 2 Initial Components).
                    </p>
                    
                  
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

            // Collapsible sections functionality
            const sectionHeaders = document.querySelectorAll('.section-header');
            sectionHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const section = header.parentElement;
                    section.classList.toggle('active');
                });
            });

            // Filter functionality
            const applyFilterBtn = document.querySelector('.apply-filter-btn');
            if (applyFilterBtn) {
                applyFilterBtn.addEventListener('click', function() {
                    const activeCategory = document.querySelector('.filter-content:first-child .filter-item.active')?.textContent || 'All Species';
                    const activeRegion = document.querySelector('.filter-content:last-child .filter-item.active')?.textContent || 'All Regions';
                    
                    alert(`Applying filters:\nCategory: ${activeCategory}\nRegion: ${activeRegion}`);
                    // Here you would implement your actual filter logic
                    // For example: filterSpecies(activeCategory, activeRegion);
                });
            }

            // Make filter items clickable
            const filterItems = document.querySelectorAll('.filter-item');
            filterItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // e.preventDefault();
                    const filterMenu = this.parentElement;
                    
                    // Remove active class from all items in this menu
                    filterMenu.querySelectorAll('.filter-item').forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Update the filter button text
                    const filterBtn = filterMenu.parentElement.querySelector('.filter-btn');
                    if (filterBtn) {
                        const icon = filterBtn.querySelector('i:first-child');
                        filterBtn.innerHTML = '';
                        filterBtn.appendChild(icon);
                        filterBtn.appendChild(document.createTextNode(this.textContent));
                        const chevron = document.createElement('i');
                        chevron.className = 'fas fa-chevron-down';
                        filterBtn.appendChild(chevron);
                    }
                });
            });
        });
    </script>
</body>
</html>