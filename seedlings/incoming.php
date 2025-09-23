<?php
// Handle logout if requested
if (isset($_GET['logout'])) {
    session_start();
    session_destroy();
    header("Location: superadmin/superlogin.php");
    exit();
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Sample quantities (replace with your database values)
$quantities = [
    'total_received' => 1250,
    'plantable_seedlings' => 980,
    'total_released' => 720,
    'total_discarded' => 150,
    'total_balance' => 380,
    'all_records' => 2150
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEEDLINGS RECEIVED</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/incoming.css">
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
            color: #000; /* Added to make all text black by default */
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

        /* Updated Dropdown Items with Hover Effects */
        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1rem;
            gap: 10px;
            position: relative;
            line-height: 1.3;
        }

        /* Hover effect matching nav icons */
        .dropdown-item:hover {
            background: rgba(43, 102, 37, 0.1);
            transform: scale(1.02);
        }

        .dropdown-item:hover i {
            color: var(--primary-dark);
        }

        /* Align icon and text to left */
        .dropdown-item i {
            width: 20px;
            text-align: left;
            font-size: 1.2rem;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .dropdown-item .item-text {
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Quantity Badge - aligned to the right */
        .quantity-badge {
            color: var(--primary-color);
            font-size: 1rem;
            font-weight: bold;
            margin-left: auto;
            padding-left: 10px
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        /* Main Content Styles for the Form */
        .main-content {
            margin-top:-3%;
            padding: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Form Styles */
        .data-entry-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            width: 100%;
            margin: 0 auto;
            padding: 30px;
            align-content: start;
            border-radius: 10px;
            background-color: #ffffff;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.78);
            position: relative;
        }

        .form-header {
            grid-column: span 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-title {
            font-size: 24px;
            font-family: Tahoma, sans-serif;
            color: #000; /* Changed to black */
            text-align: center;
            margin: 0 auto;
            padding: 10px 0;
        }

        /* Input, Select & Date Picker Styling */
        .form-group {
            display: flex;
            flex-direction: column;
            margin: auto 5px;
            width: 100%;
        }

        .form-group label {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
            font-family: Tahoma, sans-serif;
            color: #000; /* Changed to black */
        }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 45px;
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            box-sizing: border-box;
            transition: var(--transition);
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
            background-color: #fff;
        }

        /* Custom number input styling */
        .number-input-container {
            position: relative;
            width: 100%;
        }
        
        .number-input {
            width: 100%;
            height: 45px;
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            box-sizing: border-box;
            appearance: none;
            -moz-appearance: textfield;
            -webkit-appearance: none;
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }

        .number-input::-webkit-inner-spin-button,
        .number-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .number-input-buttons {
            position: absolute;
            right: 1px;
            top: 1px;
            bottom: 1px;
            width: 30px;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #000; /* Changed to black */
            background: #f5f5f5;
            border-radius: 0 4px 4px 0;
        }
        
        .number-input-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            padding: 0;
            font-size: 12px;
            color: #000; /* Changed to black */
            transition: var(--transition);
        }
        
        .number-input-button:hover {
            background: #e0e0e0;
            color: #000;
        }
        
        .number-input-button:first-child {
            border-bottom: 1px solid #000; /* Changed to black */
        }

        input[type="file"] {
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            box-sizing: border-box;
            cursor: pointer;
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }

        /* Date Input Styling */
        input[type="date"] {
            width: 100%;
            height: 45px;
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            padding-right: 35px;
            box-sizing: border-box;
            cursor: pointer;
            position: relative;
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            width: 25px;
            height: 30px;
            cursor: pointer;
            padding: 5px;
            position: absolute;
            right: 0;
            margin-right: 5px;
            background-position: right center;
            opacity: 0.7;
            transition: var(--transition);
            filter: invert(0); /* Make the icon black */
        }

        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        /* Button Styles */
        .button-container {
            grid-column: span 2;
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 0; /* Removed padding-top to eliminate space */
            border-top: none; /* Removed border-top */
        }

        .submit-button,
        .view-records-button {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            min-width: 190px;
            font-family: Tahoma, sans-serif;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .view-records-button {
            background-color: #00796b;
        }

        .submit-button:hover,
        .view-records-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .submit-button:active,
        .view-records-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }

        .view-records-top {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-family: Tahoma, sans-serif;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: auto;
            float: none;
            margin-bottom: 20px;
        }

        .view-records-container {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            justify-content: flex-end;
            padding: 0 20px 10px 20px;
        }

        .view-records-top:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            text-decoration: none;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: none;
            width: 60%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-title {
            text-align: center;
            margin-bottom: 25px;
            color: #000; /* Changed to black */
            font-size: 24px;
            font-family: Tahoma, sans-serif;
            font-weight: bold;
            padding-bottom: 10px;
            border-bottom: 1px solid #000; /* Changed to black */
        }
        
        .modal-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .modal-form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .modal-form-group label {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 16px;
            font-family: Tahoma, sans-serif;
            color: #000; /* Changed to black */
        }
        
        .modal-form-group select {
            width: 100%;
            height: 45px;
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            box-sizing: border-box;
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }
        
        .modal-number-input-container {
            position: relative;
            width: 100%;
        }
        
        .modal-number-input {
            width: 100%;
            height: 45px;
            border: 1px solid #000; /* Changed to black */
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            box-sizing: border-box;
            appearance: none;
            -moz-appearance: textfield;
            -webkit-appearance: none;
            background-color: #f9f9f9;
            color: #000; /* Changed to black */
        }
        
        .modal-number-input::-webkit-outer-spin-button,
        .modal-number-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .modal-number-input-buttons {
            position: absolute;
            right: 1px;
            top: 1px;
            bottom: 1px;
            width: 30px;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #000; /* Changed to black */
            background: #f5f5f5;
            border-radius: 0 4px 4px 0;
        }
        
        .modal-number-input-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            cursor: pointer;
            border: none;
            padding: 0;
            font-size: 12px;
            color: #000; /* Changed to black */
            transition: var(--transition);
        }
        
        .modal-number-input-button:hover {
            background: #e0e0e0;
            color: #000;
        }
        
        .modal-number-input-button:first-child {
            border-bottom: 1px solid #000; /* Changed to black */
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .modal-button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .modal-save {
            background-color: var(--primary-color);
            color: white;
        }
        
        .modal-save:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .modal-cancel {
            background-color: #f44336;
            color: white;
        }
        
        .modal-cancel:hover {
            background-color: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        /* Species list styles */
        .species-list {
            margin-top: 30px;
            border-top: 2px solid #000; /* Changed to black */
            padding-top: 15px;
        }
        
        .species-list-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: #000; /* Changed to black */
            font-size: 18px;
            font-family: Tahoma, sans-serif;
        }
        
        .species-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 10px;
            border: 1px solid #000; /* Changed to black */
            transition: var(--transition);
        }
        
        .species-item:hover {
            background-color: #f0f0f0;
        }
        
        .species-info {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .species-name {
            font-weight: bold;
            color: #000; /* Changed to black */
        }
        
        .species-quantity {
            color: #000; /* Changed to black */
            font-weight: bold;
        }
        
        .remove-species {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            margin-left: 15px;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        
        .remove-species:hover {
            background-color: #d32f2f;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .data-entry-form {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .form-title,
            .button-container {
                grid-column: span 1;
            }
            
            .modal-content {
                width: 90%;
                padding: 20px;
            }
            
            .modal-form-row {
                flex-direction: column;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .button-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .submit-button,
            .view-records-button {
                width: 100%;
            }
            
            .form-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .form-title {
                text-align: left;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    
<header>
        <div class="logo">
            <a href="seedlingshome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>
        
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-container">
            <!-- Main Dropdown Menu -->
            <div class="nav-item dropdown">
            <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <!-- New Add Seedlings option -->
                  
                      <a href="incoming.php" class="dropdown-item active-page">
                        <i class="fas fa-seedling"></i>
                        <span class="item-text">Seedlings Received</span>
                        <span class="quantity-badge"><?php echo $quantities['total_received']; ?></span>
                    </a>
                    
                    <a href="releasedrecords.php" class="dropdown-item">
                        <i class="fas fa-truck"></i>
                        <span class="item-text">Seedlings Released</span>
                        <span class="quantity-badge"><?php echo $quantities['total_released']; ?></span>
                    </a>
                    <a href="discardedrecords.php" class="dropdown-item">
                        <i class="fas fa-trash-alt"></i>
                        <span class="item-text">Seedlings Discarded</span>
                        <span class="quantity-badge"><?php echo $quantities['total_discarded']; ?></span>
                    </a>
                    <a href="balancerecords.php" class="dropdown-item">
                        <i class="fas fa-calculator"></i>
                        <span class="item-text">Seedlings Left</span>
                        <span class="quantity-badge"><?php echo $quantities['total_balance']; ?></span>
                    </a>
                    
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>

                    <a href="user_requestseedlings.php" class="dropdown-item">
                        <i class="fas fa-paper-plane"></i>
                        <span>Seedlings Request</span>
                    </a>
                </div>
            </div>
            
            <!-- Messages Icon -->
            <div class="nav-item">
                <div class="nav-icon">
                    <a href="seedlingsmessage.php">
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
                        <a href="seedlingseach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                            <div class="notification-title">Seedlings Disposal Alert</div>
                            <div class="notification-message">Report of seedlings being improperly discarded in the protected area.</div>
                            <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="notification-footer">
                        <a href="seedlingsnotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span class="item-text">Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="item-text">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="view-records-container">
            <a href="receivedrecords.php" class="view-records-top">VIEW RECORDS</a>
        </div>
        <form class="data-entry-form" id="seedlings-form">
            <div class="form-header">
                <h1 class="form-title">SEEDLINGS RECEIVED FORM</h1>
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label for="name">NAME OF AGENCY/COMPANY:</label>
                <input type="text" id="name" name="name" placeholder="Enter agency/company name">
            </div>

            <!-- Original species fields -->
            <div class="form-group">
                <label for="species">SEEDLING NAME:</label>
                <select id="species" name="species[]" class="species-select">
                    <option value="">Select Seedling</option>
                    <option value="Mahogany">Mahogany</option>
                    <option value="Molave">Molave</option>
                    <option value="Narra">Narra</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <div class="form-group">
                <label for="seedlings-delivered">QUANTITY:</label>
                <div class="number-input-container">
                    <input type="number" id="seedlings-delivered" name="seedlings_delivered[]" min="0" value="0" class="number-input">
                    <div class="number-input-buttons">
                        <button type="button" class="number-input-button" onclick="seedlingsIncrementValue()">▲</button>
                        <button type="button" class="number-input-button" onclick="seedlingsDecrementValue()">▼</button>
                    </div>
                </div>
            </div>

            <!-- Hidden container to store additional species -->
            <div id="species-data-container" style="display: none;"></div>

            <div class="form-group">
                <label for="expiry-date">DATE RECEIVED:</label>
                <input type="date" id="expiry-date" name="expiry-date">
            </div>

            <div class="form-group">
                <label for="name">NAME OF RECEIVER:</label>
                <input type="text" id="name" name="name" placeholder="Enter receiver's name">
            </div>

            <div class="button-container">
                <button type="button" class="view-records-button" id="add-species-btn">ADD SEEDLINGS</button>
                <button type="submit" class="submit-button">SUBMIT</button>
            </div>
        </form>
    </div>
    
    <!-- Modal for adding species -->
    <div id="species-modal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Add Additional Seedlings</h2>
            <div class="modal-form-row">
                <div class="modal-form-group">
                    <label for="modal-species">SEEDLINGS NAME:</label>
                    <select id="modal-species" name="modal-species" class="species-select">
                        <option value="">Select Seedling</option>
                        <option value="Mahogany">Mahogany</option>
                        <option value="Molave">Molave</option>
                        <option value="Narra">Narra</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label for="modal-seedlings">QUANTITY:</label>
                    <div class="modal-number-input-container">
                        <input type="number" id="modal-seedlings" name="modal-seedlings" min="0" value="0" class="modal-number-input">
                        <div class="modal-number-input-buttons">
                            <button type="button" class="modal-number-input-button" onclick="modalIncrementValue()">▲</button>
                            <button type="button" class="modal-number-input-button" onclick="modalDecrementValue()">▼</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="modal-button modal-cancel" id="cancel-species">Cancel</button>
                <button type="button" class="modal-button modal-save" id="save-species">Save Seedlings</button>
            </div>
            
            <div class="species-list">
                <div class="species-list-title">Added Seedlings:</div>
                <div id="species-list-items">
                    <!-- Added species will appear here -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Function to handle number input increment/decrement
        function seedlingsIncrementValue() {
            const input = document.getElementById('seedlings-delivered');
            if (input) {
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
            }
        }
        
        function seedlingsDecrementValue() {
            const input = document.getElementById('seedlings-delivered');
            if (input) {
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                }
            }
        }
        
        // Modal number input functions
        function modalIncrementValue() {
            const input = document.getElementById('modal-seedlings');
            if (input) {
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
            }
        }
        
        function modalDecrementValue() {
            const input = document.getElementById('modal-seedlings');
            if (input) {
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                }
            }
        }
        
        // Function to remove species
        function removeSpecies(button) {
            const item = button.parentElement;
            const speciesList = document.getElementById('species-list-items');
            const speciesDataContainer = document.getElementById('species-data-container');
            
            if (speciesList && speciesDataContainer) {
                speciesList.removeChild(item);
                
                // Remove from hidden container
                const species = item.querySelector('input[name="species[]"]')?.value;
                const seedlings = item.querySelector('input[name="seedlings_delivered[]"]')?.value;
                
                if (species && seedlings) {
                    const hiddenInputs = speciesDataContainer.querySelectorAll('input');
                    hiddenInputs.forEach(input => {
                        if ((input.name === "species[]" && input.value === species) || 
                            (input.name === "seedlings_delivered[]" && input.value === seedlings)) {
                            speciesDataContainer.removeChild(input);
                        }
                    });
                }
            }
        }

        // Initialize when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const modal = document.getElementById('species-modal');
            const addBtn = document.getElementById('add-species-btn');
            const cancelBtn = document.getElementById('cancel-species');
            const saveBtn = document.getElementById('save-species');
            const speciesList = document.getElementById('species-list-items');
            const speciesDataContainer = document.getElementById('species-data-container');

            // Open modal when Add Another Seedlings is clicked
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    if (modal) {
                        // Reset form values
                        const speciesSelect = document.getElementById('modal-species');
                        const seedlingsInput = document.getElementById('modal-seedlings');
                        
                        if (speciesSelect) speciesSelect.value = '';
                        if (seedlingsInput) seedlingsInput.value = '0';
                        
                        // Show modal
                        modal.style.display = 'block';
                    }
                });
            }

            // Close modal when Cancel is clicked
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Save species data
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    const species = document.getElementById('modal-species')?.value;
                    const seedlings = document.getElementById('modal-seedlings')?.value;
                    
                    if (species && seedlings > 0) {
                        // Create a unique ID for this entry
                        const entryId = 'species-' + Date.now();
                        
                        // Add to the visible list
                        const speciesItem = document.createElement('div');
                        speciesItem.className = 'species-item';
                        speciesItem.id = entryId;
                        speciesItem.innerHTML = `
                            <div class="species-info">
                                <span class="species-name">${species}</span>
                                <span class="species-quantity">${seedlings} seedlings</span>
                            </div>
                            <button type="button" class="remove-species" onclick="removeSpecies(this)">Remove</button>
                            <input type="hidden" name="species[]" value="${species}">
                            <input type="hidden" name="seedlings_delivered[]" value="${seedlings}">
                        `;
                        
                        if (speciesList) {
                            speciesList.appendChild(speciesItem);
                        }
                        
                        // Add to hidden container for form submission
                        if (speciesDataContainer) {
                            const hiddenInput1 = document.createElement('input');
                            hiddenInput1.type = 'hidden';
                            hiddenInput1.name = 'species[]';
                            hiddenInput1.value = species;
                            
                            const hiddenInput2 = document.createElement('input');
                            hiddenInput2.type = 'hidden';
                            hiddenInput2.name = 'seedlings_delivered[]';
                            hiddenInput2.value = seedlings;
                            
                            speciesDataContainer.appendChild(hiddenInput1);
                            speciesDataContainer.appendChild(hiddenInput2);
                        }
                        
                        // Clear form and close modal
                        const speciesSelect = document.getElementById('modal-species');
                        const seedlingsInput = document.getElementById('modal-seedlings');
                        
                        if (speciesSelect) speciesSelect.value = '';
                        if (seedlingsInput) seedlingsInput.value = '0';
                        if (modal) modal.style.display = 'none';
                        
                    } else {
                        alert('Please select a species and enter a valid number of seedlings delivered');
                    }
                });
            }

            // Mobile menu toggle functionality
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }
            
            // Dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');
            
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                // Show menu on hover (desktop)
                dropdown.addEventListener('mouseenter', () => {
                    if (window.innerWidth > 992) {
                        menu.style.opacity = '1';
                        menu.style.visibility = 'visible';
                        menu.style.transform = menu.classList.contains('center') 
                            ? 'translateX(-50%) translateY(0)' 
                            : 'translateY(0)';
                    }
                });
                
                // Hide menu when leaving (desktop)
                dropdown.addEventListener('mouseleave', (e) => {
                    if (window.innerWidth > 992 && !dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') 
                            ? 'translateX(-50%) translateY(10px)' 
                            : 'translateY(10px)';
                    }
                });
                
                // Toggle menu on click (mobile)
                if (window.innerWidth <= 992) {
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
                }
            });
            
            // Close dropdowns when clicking outside (mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown') && window.innerWidth <= 992) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.display = 'none';
                    });
                }
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
                });
            }

            // Form submission
            const form = document.getElementById('seedlings-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Here you would normally submit the form data to the server
                    alert('Form submitted successfully!');
                    // form.submit(); // Uncomment this to actually submit the form
                });
            }
        });
    </script>
</body>
</html>