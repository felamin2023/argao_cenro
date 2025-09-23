<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarterly Breeding Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
   
       :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --primary-light: #e9fff2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-color: #e0e0e0;
            --text-dark: #333333;
            --text-light: #666666;
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
            padding-top: 100px;
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
        .content {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Form Header */
        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            padding: 25px 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .form-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        /* Form Body */
        .form-body {
            padding: 20px;
        }

        /* Form Sections */
        .form-section {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #000000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .section-title {
            font-size: 18px;
            color: var(--primary-dark);
            margin-bottom: 15px;
            padding-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 20px;
            background-color: #000000;
            border-radius: 2px;
        }

        /* Form Layout */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1;
            min-width: 280px;
            margin-bottom: 10px;
        }

        /* Form Elements */
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        input[type="text"],
        input[type="date"],
        input[type="number"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #000000;
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
            background-color: var(--white);
            color: var(--text-dark);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(43, 102, 37, 0.1);
        }

        /* Radio Buttons */
        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 6px;
            transition: var(--transition);
        }

        .radio-option:hover {
            background-color: var(--primary-light);
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--primary-color);
        }

        /* Image Upload */
        .image-upload-container {
            border: 1px dashed #000000;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background-color: var(--light-gray);
            transition: var(--transition);
            cursor: pointer;
            margin-bottom: 15px;
        }

        .image-upload-container:hover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }

        .image-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-light);
        }

        .image-upload-label i {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .image-upload-input {
            display: none;
        }

        .image-preview {
            margin-top: 20px;
            max-width: 100%;
            display: none;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            gap: 8px;
            font-size: 14px;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            min-width: 200px;
            padding: 12px 30px;
            margin-top: 5px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 102, 37, 0.2);
        }

        .btn-outline {
            background-color: var(--white);
            border: 1px solid #000000;
            color: var(--text-dark);
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 5px;
            padding-top: 0;
        }

        /* Success Message */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #c3e6cb;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .success-message i {
            color: #155724;
            font-size: 20px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            header {
                padding: 0 15px;
            }
            
            .form-body {
                padding: 20px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-row {
                gap: 20px;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 25px;
            border-radius: 4px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 18px;
        }

        .close {
            color: var(--text-light);
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
        }

        .close:hover {
            color: var(--text-dark);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <a href="wildhome.php">
            <img src="seal.png" alt="Site Logo">
        </a>
    </div>
    
    <!-- Navigation on the right -->
    <div class="nav-container">
        <!-- Dashboard Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon active">
                <i class="fas fa-bars"></i>
            </div>
            <div class="dropdown-menu center">
                <a href="breedingreport.php" class="dropdown-item active-page">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Record</span>
                </a> 
                
                    <a href="wildpermit.php" class="dropdown-item">
                    <i class="fas fa-paw"></i>
                    <span>Wildlife Permit</span>
                </a>
                <a href="reportaccident.php" class="dropdown-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Incident Reports</span>
                </a>
            </div>
        </div>

        <!-- Messages Icon -->
        <div class="nav-item">
            <div class="nav-icon">
                <a href="wildmessage.php" aria-label="Messages">
                    <i class="fas fa-envelope" style="color: black;"></i>
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
                    <a href="wildeach.php?id=1" class="notification-link">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                        <div class="notification-title">Wildlife Incident Reported</div>
                            <div class="notification-message">A large monitor lizard approximately 1.2 meters in length was spotted near a residential backyard early in the morning.</div>
                            <div class="notification-time">15 minutes ago</div>
                        </div>
                    </a>
                </div>
                <div class="notification-footer">
                    <a href="wildnotification.php" class="view-all">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="dropdown-menu">
                <a href="wildprofile.php" class="dropdown-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Edit Profile</span>
                </a>
                <a href="../superlogin.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<div class="content">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
        <a href="wildrecord.php" class="btn btn-outline">
            <i class="fas fa-list"></i> View Records
        </a>
    </div>
    
    <form class="form-container" method="POST" action="">
        <div class="form-header">
            <h1>WILDLIFE BREEDING REPORT</h1>
        </div>
        
        <div class="form-body">
            <?php if (!empty($successMessage)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $successMessage; ?></span>
                </div>
            <?php endif; ?>

            <!-- Record Period -->
            <div class="form-section">
                <h2 class="section-title">RECORD PERIOD</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>
                </div>
            </div>

            <!-- Owner Information -->
            <div class="form-section">
                <h2 class="section-title">OWNER INFORMATION</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter owner's full name" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" placeholder="Enter complete address" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wfp_no">WFP Number</label>
                        <input type="text" id="wfp_no" name="wfp_no" placeholder="Enter WFP number" required>
                    </div>
                    <div class="form-group">
                        <label for="farm_location">Farm Location</label>
                        <input type="text" id="farm_location" name="farm_location" placeholder="Enter farm location" required>
                    </div>
                </div>
            </div>

            <!-- Wildlife Stock Details -->
            <div class="form-section">
                <h2 class="section-title">WILDLIFE STOCK DETAILS</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label>Species Image (optional)</label>
                        <div class="image-upload-container" onclick="document.getElementById('species_image').click()">
                            <input type="file" name="species_image" id="species_image" accept="image/*" class="image-upload-input" required>
                            <div class="image-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload species image</span>
                                <small style="display: block; margin-top: 5px; color: var(--text-light);">(JPEG, PNG, max 5MB)</small>
                            </div>
                            <div class="image-preview" id="image-preview"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="species_name">Species Name</label>
                        <select id="species_name" name="species_name" required>
                            <option value="">Select Species</option>
                            <option value="Agapornis">Agapornis</option>
                            <option value="Cockatiel">Cockatiel</option>
                            <option value="Lovebird">Lovebird</option>
                            <option value="Parakeet">Parakeet</option>
                            <option value="Macaw">Macaw</option>
                            <option value="African Grey">African Grey</option>
                            <option value="Eclectus">Eclectus</option>
                            <option value="Amazon">Amazon</option>
                            <option value="Conure">Conure</option>
                            <option value="Finch">Finch</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock_number">Breeding Stock Number</label>
                        <input type="number" id="stock_number" name="stock_number" placeholder="Enter stock number" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="previous_balance">Previous Quarter Balance</label>
                        <input type="number" id="previous_balance" name="previous_balance" placeholder="Enter previous balance" required>
                    </div>
                    <div class="form-group">
                        <label>Current Status</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="status" value="alive" required>
                                <span>Alive</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="status" value="dead">
                                <span>Dead</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-top: 20px; text-align: center; width: 100%;">
                        <button type="button" class="btn btn-outline" onclick="openModal()" style="width: auto;">
                            <i class="fas fa-plus"></i> Add Another Species
                        </button>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Report
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal -->
<div id="speciesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Another Species</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Upload Image</label>
                <div class="image-upload-container" onclick="document.getElementById('modal_species_image').click()">
                    <input type="file" name="modal_species_image" id="modal_species_image" accept="image/*" class="image-upload-input" required>
                    <div class="image-upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to upload species image</span>
                    </div>
                    <div class="image-preview" id="modal-image-preview"></div>
                </div>
            </div>
            <div class="form-group">
                <label for="modal_species_name">Species Name</label>
                <select id="modal_species_name" name="modal_species_name" required>
                    <option value="">Select Species</option>
                    <option value="Agapornis">Agapornis</option>
                    <option value="Cockatiel">Cockatiel</option>
                    <option value="Lovebird">Lovebird</option>
                    <option value="Parakeet">Parakeet</option>
                    <option value="Macaw">Macaw</option>
                    <option value="African Grey">African Grey</option>
                    <option value="Eclectus">Eclectus</option>
                    <option value="Amazon">Amazon</option>
                    <option value="Conure">Conure</option>
                    <option value="Finch">Finch</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="modal_status" value="alive" required>
                        <span>Alive</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="modal_status" value="dead">
                        <span>Dead</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal()">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="saveModalData()">
                <i class="fas fa-plus"></i> Add Species
            </button>
        </div>
    </div>
</div>

<script>
    // Image preview functionality
    document.getElementById('species_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('image-preview');
                preview.style.display = 'block';
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            reader.readAsDataURL(file);
        }
    });

    // Modal functionality
    function openModal() {
        document.getElementById('speciesModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('speciesModal').style.display = 'none';
    }

    function saveModalData() {
        // Add your save logic here
        alert('Species added successfully!');
        closeModal();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('speciesModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Image preview for modal
    document.getElementById('modal_species_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('modal-image-preview');
                preview.style.display = 'block';
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            reader.readAsDataURL(file);
        }
    });

    // Dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.nav-icon');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            dropdown.addEventListener('mouseenter', () => {
                menu.style.opacity = '1';
                menu.style.visibility = 'visible';
                menu.style.transform = menu.classList.contains('center') 
                    ? 'translateX(-50%) translateY(0)' 
                    : 'translateY(0)';
            });
            
            dropdown.addEventListener('mouseleave', (e) => {
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
        });
    });
</script>
</body>
</html>