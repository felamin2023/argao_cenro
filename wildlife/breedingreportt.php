<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cenro_argao";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$successMessage = "";
$current_page = basename($_SERVER['PHP_SELF']);
$showPopup = false;
$report_id = "";
$validationErrors = [];

// Function to generate the next report ID (BR-001, BR-002, etc.)
function generateReportID($conn) {
    $sql = "SELECT MAX(CAST(SUBSTRING(report_id, 4) AS UNSIGNED)) as max_id FROM breeding_reports";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $next_id = $row['max_id'] + 1;
    } else {
        $next_id = 1;
    }
    
    return 'BR-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate fields
    $requiredFields = [
        'start_date', 'end_date', 'name', 'address', 'wfp_no', 'farm_location',
        'species_name', 'stock_number', 'previous_balance', 'dead_count'
    ];
    
    // Check required fields
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $validationErrors[$field] = "This field is required";
        }
    }
    
    // Validate name (letters only)
    if (!empty($_POST['name']) && !preg_match("/^[a-zA-Z ]*$/", $_POST['name'])) {
        $validationErrors['name'] = "Only letters and spaces are allowed";
    }
    
    // Validate WFP number (numeric only)
    if (!empty($_POST['wfp_no']) && !is_numeric($_POST['wfp_no'])) {
        $validationErrors['wfp_no'] = "WFP number must be numeric";
    }
    
    // Validate stock numbers (must be positive)
    $numericFields = ['stock_number', 'previous_balance', 'dead_count'];
    foreach ($numericFields as $field) {
        if (!empty($_POST[$field]) && (!is_numeric($_POST[$field]) || $_POST[$field] < 0)) {
            $validationErrors[$field] = "Must be a positive number";
        }
    }
    
    // Validate dates
    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        $start_date = strtotime($_POST['start_date']);
        $end_date = strtotime($_POST['end_date']);
        
        if ($start_date > $end_date) {
            $validationErrors['end_date'] = "End date must be after start date";
        }
    }
    
    if (empty($validationErrors)) {
        // Generate report ID
        $report_id = generateReportID($conn);
        
        // Get form data
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $owner_name = $_POST['name'];
        $owner_address = $_POST['address'];
        $wfp_number = $_POST['wfp_no'];
        $farm_location = $_POST['farm_location'];
        $species_name = $_POST['species_name'];
        $stock_number = $_POST['stock_number'];
        $previous_balance = $_POST['previous_balance'];
        $dead_count = $_POST['dead_count'];
        $total_stocks = $previous_balance - $dead_count;
        
        // Handle file upload (optional)
        $species_image = '';
        if (isset($_FILES['species_image']) && $_FILES['species_image']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = uniqid() . '_' . basename($_FILES['species_image']['name']);
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['species_image']['tmp_name'], $targetPath)) {
                $species_image = $targetPath;
            }
        }
        
        // Handle additional species
        $additional_species = [];
        if (!empty($_POST['additional_species'])) {
            foreach ($_POST['additional_species'] as $additional) {
                $additional_total = $additional['previous_balance'] - $additional['dead_count'];
                $additional_species[] = [
                    'species_name' => $additional['species_name'],
                    'stock_number' => $additional['stock_number'],
                    'previous_balance' => $additional['previous_balance'],
                    'dead_count' => $additional['dead_count'],
                    'total_stocks' => $additional_total
                ];
            }
        }
        
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO breeding_reports (
            report_id, start_date, end_date, owner_name, owner_address, wfp_number, 
            farm_location, species_name, species_image, stock_number, 
            previous_balance, dead_count, total_stocks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssiiii", 
            $report_id, $start_date, $end_date, $owner_name, $owner_address, 
            $wfp_number, $farm_location, $species_name, $species_image, 
            $stock_number, $previous_balance, $dead_count, $total_stocks
        );
        
        if ($stmt->execute()) {
            $successMessage = "Breeding report submitted successfully!";
            $showPopup = true;
        } else {
            $successMessage = "Error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Close connection
$conn->close();
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
            --error-color: #ff4757;
            --success-color: #2b6625;
            --section-border: #cccccc;
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            0%, 20%, 50%, 80%, 100% { transform: scale(1); }
            40% { transform: scale(1.1); }
            60% { transform: scale(1); }
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
            width: 100%;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 40px;
            width: 100%;
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
            padding: 30px;
            margin-top: 0;
        }

        /* Form Sections */
        .form-section {
            background-color: var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--section-border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .form-section.record-period {
            border: 1px solid var(--section-border);
        }

        .form-section.owner-information {
            border: 1px solid var(--section-border);
        }

        .form-section.wildlife-stock-details {
            border: 1px solid var(--section-border);
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
            background-color: var(--primary-color);
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
            color: var(--black);
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

        /* Total Stocks Display */
        .total-stocks-display {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #000000;
            border-radius: 6px;
            font-size: 14px;
            background-color: var(--white);
            color: var(--text-dark);
        }

        /* Error state for inputs */
        input.error,
        select.error,
        textarea.error {
            border-color: var(--error-color);
            animation: shake 0.5s;
        }

        .error-message {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
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
            position: relative;
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
            position: relative;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .remove-image-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: var(--error-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .remove-image-btn:hover {
            background-color: #ff0000;
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
            width: 200px;
        }

        .btn-outline {
            background-color: var(--white);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-dark);
            color: var(--primary-dark);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 102, 37, 0.2);
        }

        /* Add Species Button */
        .add-species-btn {
            background-color: var(--white);
            border: 1px solid #000000;
            color: var(--text-dark);
        }

        .add-species-btn:hover {
            background-color: var(--light-gray);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Submit Report Button */
        .submit-report-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
        }

        .submit-report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 102, 37, 0.2);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 20px;
            background-color: var(--light-gray);
            border-top: 1px solid var(--border-color);
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: -3%;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        /* Modal Content */
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Modal Header */
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

        /* Modal Body */
        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            justify-content: center;
            gap: 15px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        /* Success Popup */
        .success-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .success-popup-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: popIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform-origin: center;
        }

        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-icon {
            font-size: 60px;
            color: #2b6625;
            margin-bottom: 20px;
            animation: bounce 0.6s;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }

        .success-title {
            font-size: 24px;
            font-weight: 700;
            color: #2b6625;
            margin-bottom: 10px;
        }

        .success-message-text {
            font-size: 16px;
            color: #333;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .success-ok-btn {
            background: linear-gradient(135deg, #2b6625, #1e4a1a);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(43, 102, 37, 0.2);
        }

        .success-ok-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(43, 102, 37, 0.3);
        }

        /* Additional Species Section */
        .additional-species-item {
            background-color: var(--light-gray);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--section-border);
            position: relative;
        }

        .species-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .species-item-title {
            font-weight: 600;
            color: var(--primary-color);
        }

        .remove-species-btn {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 16px;
        }

        /* Species Image Preview */
        .species-image-container {
            border: 1px dashed var(--section-border);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background-color: var(--light-gray);
            margin-bottom: 15px;
            position: relative;
        }

        .species-image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Success Modal */
        .success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .success-modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .success-modal-icon {
            font-size: 50px;
            color: var(--success-color);
            margin-bottom: 15px;
        }

        .success-modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 10px;
        }

        .success-modal-message {
            font-size: 16px;
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .success-modal-ok-btn {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .success-modal-ok-btn:hover {
            background: var(--primary-dark);
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

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 15px;
            }

            .modal-body .form-group,
            .modal-body .form-row:nth-child(2) .form-group {
                min-width: 100%;
            }

            .success-popup-content {
                width: 95%;
                padding: 20px;
            }

            .success-icon {
                font-size: 50px;
            }

            .success-title {
                font-size: 20px;
            }

            .success-message-text {
                font-size: 14px;
            }
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
    
    <?php if (!empty($validationErrors)): ?>
        <div class="success-message" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i>
            <span>Please correct the following errors in the form:</span>
            <ul style="margin-left: 20px; margin-top: 5px;">
                <?php foreach ($validationErrors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form class="form-container" method="POST" action="" enctype="multipart/form-data" id="breedingForm">
        <div class="form-header">
            <h1>WILDLIFE BREEDING REPORT</h1>
        </div>
        
        <div class="form-body">
            <!-- Record Period -->
            <div class="form-section record-period">
                <h2 class="section-title">RECORD PERIOD</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" required>
                        <div class="error-message" id="start_date_error"><?php echo isset($validationErrors['start_date']) ? $validationErrors['start_date'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" required>
                        <div class="error-message" id="end_date_error"><?php echo isset($validationErrors['end_date']) ? $validationErrors['end_date'] : ''; ?></div>
                    </div>
                </div>
            </div>

            <!-- Owner Information -->
            <div class="form-section owner-information">
                <h2 class="section-title">OWNER INFORMATION</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" placeholder="Enter owner's full name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        <div class="error-message" id="name_error"><?php echo isset($validationErrors['name']) ? $validationErrors['name'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" placeholder="Enter complete address" 
                               value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
                        <div class="error-message" id="address_error"><?php echo isset($validationErrors['address']) ? $validationErrors['address'] : ''; ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="wfp_no">WFP Number</label>
                        <input type="text" id="wfp_no" name="wfp_no" placeholder="Enter WFP number" 
                               value="<?php echo isset($_POST['wfp_no']) ? htmlspecialchars($_POST['wfp_no']) : ''; ?>" required>
                        <div class="error-message" id="wfp_no_error"><?php echo isset($validationErrors['wfp_no']) ? $validationErrors['wfp_no'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="farm_location">Farm Location</label>
                        <input type="text" id="farm_location" name="farm_location" placeholder="Enter farm location" 
                               value="<?php echo isset($_POST['farm_location']) ? htmlspecialchars($_POST['farm_location']) : ''; ?>" required>
                        <div class="error-message" id="farm_location_error"><?php echo isset($validationErrors['farm_location']) ? $validationErrors['farm_location'] : ''; ?></div>
                    </div>
                </div>
            </div>

            <!-- Wildlife Stock Details -->
            <div class="form-section wildlife-stock-details">
                <h2 class="section-title">WILDLIFE STOCK DETAILS</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label>Species Image (optional)</label>
                        <div class="image-upload-container" id="main-image-container" onclick="document.getElementById('species_image').click()">
                            <input type="file" name="species_image" id="species_image" accept="image/*" class="image-upload-input">
                            <div class="image-upload-label" id="main-image-upload-label">
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
                        <input type="text" id="species_name" name="species_name" placeholder="Enter or select species name" 
                               value="<?php echo isset($_POST['species_name']) ? htmlspecialchars($_POST['species_name']) : ''; ?>" list="main_species_list" required>
                        <datalist id="main_species_list">
                            <option value="Agapornis">
                            <option value="Cockatiel">
                            <option value="Lovebird">
                            <option value="Parakeet">
                            <option value="Macaw">
                            <option value="African Grey">
                            <option value="Eclectus">
                            <option value="Amazon">
                            <option value="Conure">
                            <option value="Finch">
                        </datalist>
                        <div class="error-message" id="species_name_error"><?php echo isset($validationErrors['species_name']) ? $validationErrors['species_name'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="stock_number">Accredited Breeding Stock Number</label>
                        <input type="number" id="stock_number" name="stock_number" placeholder="Enter stock number" 
                               value="<?php echo isset($_POST['stock_number']) ? htmlspecialchars($_POST['stock_number']) : ''; ?>" min="0" required>
                        <div class="error-message" id="stock_number_error"><?php echo isset($validationErrors['stock_number']) ? $validationErrors['stock_number'] : ''; ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="previous_balance">Previous Quarter Balance (Alive)</label>
                        <input type="number" id="previous_balance" name="previous_balance" placeholder="Enter previous balance" 
                               value="<?php echo isset($_POST['previous_balance']) ? htmlspecialchars($_POST['previous_balance']) : ''; ?>" min="0" required>
                        <div class="error-message" id="previous_balance_error"><?php echo isset($validationErrors['previous_balance']) ? $validationErrors['previous_balance'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="dead_count">Dead Count</label>
                        <input type="number" id="dead_count" name="dead_count" placeholder="Enter dead count" 
                               value="<?php echo isset($_POST['dead_count']) ? htmlspecialchars($_POST['dead_count']) : ''; ?>" min="0" required>
                        <div class="error-message" id="dead_count_error"><?php echo isset($validationErrors['dead_count']) ? $validationErrors['dead_count'] : ''; ?></div>
                    </div>
                    <div class="form-group">
                        <label for="total_stocks">Total Stocks</label>
                        <div class="total-stocks-display" id="total_stocks_display">
                            <?php echo isset($_POST['previous_balance'], $_POST['dead_count']) ? ($_POST['previous_balance'] - $_POST['dead_count']) : '0'; ?>
                        </div>
                        <input type="hidden" id="total_stocks" name="total_stocks" value="<?php echo isset($_POST['previous_balance'], $_POST['dead_count']) ? ($_POST['previous_balance'] - $_POST['dead_count']) : '0'; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Additional Species Section (will be populated by JavaScript) -->
            <div id="additionalSpeciesContainer"></div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-outline add-species-btn" onclick="openSpeciesModal()">
                <i class="fas fa-plus"></i> Add More Species
            </button>
            <button type="submit" class="btn btn-primary submit-report-btn">
                <i class="fas fa-save"></i> Submit Report
            </button>
        </div>
    </form>
</div>

<!-- Success Popup -->
<div class="success-popup" id="successPopup">
    <div class="success-popup-content">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2 class="success-title">Success!</h2>
        <p class="success-message-text">Breeding report submitted successfully!</p>
        <button class="success-ok-btn" onclick="closeSuccessPopup()">OK</button>
    </div>
</div>

<!-- Success Modal (for species addition) -->
<div class="success-modal" id="successModal">
    <div class="success-modal-content">
        <div class="success-modal-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="success-modal-title">Successful</h3>
        <p class="success-modal-message" id="successModalMessage"></p>
        <button class="success-modal-ok-btn" onclick="closeSuccessModal()">OK</button>
    </div>
</div>

<!-- Species Modal -->
<div id="speciesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Additional Species</h2>
            <span class="close" onclick="closeSpeciesModal()">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Image Row (full width) -->
            <div class="form-row">
                <div class="form-group" style="width: 100%;">
                    <label>Species Image (optional)</label>
                    <div class="image-upload-container" id="modal-image-container" onclick="document.getElementById('modal_species_image').click()">
                        <input type="file" name="modal_species_image" id="modal_species_image" accept="image/*" class="image-upload-input">
                        <div class="image-upload-label" id="modal-image-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload species image</span>
                            <small style="display: block; margin-top: 5px; color: var(--text-light);">(JPEG, PNG, max 5MB)</small>
                        </div>
                        <div class="image-preview" id="modal-image-preview"></div>
                    </div>
                </div>
            </div>

            <!-- Species Name and Stock Number Row -->
            <div class="form-row">
                <div class="form-group">
                    <label for="modal_species_name">Species Name</label>
                    <input type="text" id="modal_species_name" name="modal_species_name" placeholder="Enter or select species name" list="modal_species_list" required>
                    <datalist id="modal_species_list">
                        <option value="Agapornis">
                        <option value="Cockatiel">
                        <option value="Lovebird">
                        <option value="Parakeet">
                        <option value="Macaw">
                        <option value="African Grey">
                        <option value="Eclectus">
                        <option value="Amazon">
                        <option value="Conure">
                        <option value="Finch">
                    </datalist>
                    <div class="error-message" id="modal_species_name_error"></div>
                </div>
                <div class="form-group">
                    <label for="modal_stock_number">Accredited Breeding Stock Number</label>
                    <input type="number" id="modal_stock_number" name="modal_stock_number" placeholder="Enter stock number" min="0" required>
                    <div class="error-message" id="modal_stock_number_error"></div>
                </div>
            </div>

            <!-- Stock Details Row -->
            <div class="form-row">
                <div class="form-group">
                    <label for="modal_previous_balance">Previous Quarter Balance (Alive)</label>
                    <input type="number" id="modal_previous_balance" name="modal_previous_balance" placeholder="Previous balance" min="0" required>
                    <div class="error-message" id="modal_previous_balance_error"></div>
                </div>
                <div class="form-group">
                    <label for="modal_dead_count">Dead Count</label>
                    <input type="number" id="modal_dead_count" name="modal_dead_count" placeholder="Enter dead count" min="0" required>
                    <div class="error-message" id="modal_dead_count_error"></div>
                </div>
                <div class="form-group">
                    <label for="modal_total_stocks">Total Stocks</label>
                    <div class="total-stocks-display" id="modal_total_stocks_display">0</div>
                    <input type="hidden" id="modal_total_stocks" name="modal_total_stocks" value="0">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeSpeciesModal()">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="saveAndCloseSpeciesModal()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<script>
    // Store additional species data
    let additionalSpecies = [];
    
    // Image preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Main form image preview
        const speciesImageInput = document.getElementById('species_image');
        if (speciesImageInput) {
            speciesImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('image-preview');
                        const uploadLabel = document.getElementById('main-image-upload-label');
                        if (preview) {
                            preview.innerHTML = `
                                <button class="remove-image-btn" onclick="removeImage('species_image', 'image-preview', 'main-image-upload-label')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <img src="${e.target.result}" alt="Preview">
                            `;
                            preview.style.display = 'block';
                            if (uploadLabel) {
                                uploadLabel.style.display = 'none';
                            }
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Modal image preview
        const modalSpeciesImageInput = document.getElementById('modal_species_image');
        if (modalSpeciesImageInput) {
            modalSpeciesImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('modal-image-preview');
                        const uploadLabel = document.getElementById('modal-image-upload-label');
                        if (preview) {
                            preview.innerHTML = `
                                <button class="remove-image-btn" onclick="removeImage('modal_species_image', 'modal-image-preview', 'modal-image-upload-label')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <img src="${e.target.result}" alt="Preview">
                            `;
                            preview.style.display = 'block';
                            if (uploadLabel) {
                                uploadLabel.style.display = 'none';
                            }
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Auto-calculate Total Stocks (Alive - Dead)
        function updateTotalStocks() {
            const alive = parseInt(document.getElementById('previous_balance').value) || 0;
            const dead = parseInt(document.getElementById('dead_count').value) || 0;
            const total = alive - dead;
            document.getElementById('total_stocks_display').textContent = total;
            document.getElementById('total_stocks').value = total;
        }

        const previousBalance = document.getElementById('previous_balance');
        const deadCount = document.getElementById('dead_count');
        
        if (previousBalance && deadCount) {
            previousBalance.addEventListener('input', updateTotalStocks);
            deadCount.addEventListener('input', updateTotalStocks);
        }

        // Auto-calculate Total Stocks in the modal (Alive - Dead)
        function updateModalTotalStocks() {
            const alive = parseInt(document.getElementById('modal_previous_balance').value) || 0;
            const dead = parseInt(document.getElementById('modal_dead_count').value) || 0;
            const total = alive - dead;
            document.getElementById('modal_total_stocks_display').textContent = total;
            document.getElementById('modal_total_stocks').value = total;
        }

        const modalPreviousBalance = document.getElementById('modal_previous_balance');
        const modalDeadCount = document.getElementById('modal_dead_count');
        
        if (modalPreviousBalance && modalDeadCount) {
            modalPreviousBalance.addEventListener('input', updateModalTotalStocks);
            modalDeadCount.addEventListener('input', updateModalTotalStocks);
        }

        // Show error messages for fields with validation errors
        <?php foreach ($validationErrors as $field => $error): ?>
            const <?php echo $field; ?>Input = document.getElementById('<?php echo $field; ?>');
            const <?php echo $field; ?>Error = document.getElementById('<?php echo $field; ?>_error');
            if (<?php echo $field; ?>Input && <?php echo $field; ?>Error) {
                <?php echo $field; ?>Input.classList.add('error');
                <?php echo $field; ?>Error.style.display = 'block';
                <?php echo $field; ?>Error.textContent = '<?php echo addslashes($error); ?>';
            }
        <?php endforeach; ?>

        // Show success popup if form was submitted successfully
        <?php if ($showPopup): ?>
            document.getElementById('successPopup').style.display = 'flex';
        <?php endif; ?>
    });

    // Function to remove an uploaded image
    function removeImage(inputId, previewId, labelId) {
        // Clear the file input
        const input = document.getElementById(inputId);
        if (input) {
            input.value = '';
        }
        
        // Hide the preview
        const preview = document.getElementById(previewId);
        if (preview) {
            preview.innerHTML = '';
            preview.style.display = 'none';
        }
        
        // Show the upload label again
        const label = document.getElementById(labelId);
        if (label) {
            label.style.display = 'flex';
        }
    }

    // Function to clear all form fields
    function clearFormFields() {
        const form = document.getElementById('breedingForm');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            if (input.type !== 'submit' && input.type !== 'button') {
                input.value = '';
            }
        });
        
        // Clear image preview
        const imagePreview = document.getElementById('image-preview');
        if (imagePreview) {
            imagePreview.style.display = 'none';
            imagePreview.innerHTML = '';
        }
        
        // Show upload label again
        const uploadLabel = document.getElementById('main-image-upload-label');
        if (uploadLabel) {
            uploadLabel.style.display = 'flex';
        }
        
        // Reset file input
        const fileInput = document.getElementById('species_image');
        if (fileInput) {
            fileInput.value = '';
        }
        
        // Clear additional species
        additionalSpecies = [];
        renderAdditionalSpecies();
    }

    // Close success popup and clear form
    function closeSuccessPopup() {
        document.getElementById('successPopup').style.display = 'none';
        clearFormFields();
    }

    // Show success modal with message
    function showSuccessModal(message) {
        document.getElementById('successModalMessage').textContent = message;
        document.getElementById('successModal').style.display = 'flex';
    }

    // Close success modal
    function closeSuccessModal() {
        document.getElementById('successModal').style.display = 'none';
    }

    // Modal functionality for adding species
    function openSpeciesModal() {
        document.getElementById('speciesModal').style.display = 'block';
        // Clear previous form data
        document.getElementById('modal_species_image').value = '';
        document.getElementById('modal_species_name').value = '';
        document.getElementById('modal_stock_number').value = '';
        document.getElementById('modal_previous_balance').value = '';
        document.getElementById('modal_dead_count').value = '';
        document.getElementById('modal_total_stocks_display').textContent = '0';
        document.getElementById('modal_total_stocks').value = '0';
        
        // Clear image preview and show upload label
        document.getElementById('modal-image-preview').style.display = 'none';
        document.getElementById('modal-image-preview').innerHTML = '';
        document.getElementById('modal-image-upload-label').style.display = 'flex';
        
        // Clear error messages
        document.getElementById('modal_species_name_error').style.display = 'none';
        document.getElementById('modal_stock_number_error').style.display = 'none';
        document.getElementById('modal_previous_balance_error').style.display = 'none';
        document.getElementById('modal_dead_count_error').style.display = 'none';
    }

    function closeSpeciesModal() {
        document.getElementById('speciesModal').style.display = 'none';
    }

    // Validate modal form
    function validateModalForm() {
        let isValid = true;
        
        // Species name validation
        const speciesName = document.getElementById('modal_species_name').value.trim();
        if (!speciesName) {
            document.getElementById('modal_species_name_error').textContent = 'Species name is required';
            document.getElementById('modal_species_name_error').style.display = 'block';
            document.getElementById('modal_species_name').classList.add('error');
            isValid = false;
        } else {
            document.getElementById('modal_species_name_error').style.display = 'none';
            document.getElementById('modal_species_name').classList.remove('error');
        }
        
        // Stock number validation
        const stockNumber = document.getElementById('modal_stock_number').value;
        if (!stockNumber || isNaN(stockNumber) || stockNumber < 0) {
            document.getElementById('modal_stock_number_error').textContent = 'Valid stock number is required';
            document.getElementById('modal_stock_number_error').style.display = 'block';
            document.getElementById('modal_stock_number').classList.add('error');
            isValid = false;
        } else {
            document.getElementById('modal_stock_number_error').style.display = 'none';
            document.getElementById('modal_stock_number').classList.remove('error');
        }
        
        // Previous balance validation
        const previousBalance = document.getElementById('modal_previous_balance').value;
        if (!previousBalance || isNaN(previousBalance) || previousBalance < 0) {
            document.getElementById('modal_previous_balance_error').textContent = 'Valid previous balance is required';
            document.getElementById('modal_previous_balance_error').style.display = 'block';
            document.getElementById('modal_previous_balance').classList.add('error');
            isValid = false;
        } else {
            document.getElementById('modal_previous_balance_error').style.display = 'none';
            document.getElementById('modal_previous_balance').classList.remove('error');
        }
        
        // Dead count validation
        const deadCount = document.getElementById('modal_dead_count').value;
        if (!deadCount || isNaN(deadCount) || deadCount < 0) {
            document.getElementById('modal_dead_count_error').textContent = 'Valid dead count is required';
            document.getElementById('modal_dead_count_error').style.display = 'block';
            document.getElementById('modal_dead_count').classList.add('error');
            isValid = false;
        } else {
            document.getElementById('modal_dead_count_error').style.display = 'none';
            document.getElementById('modal_dead_count').classList.remove('error');
        }
        
        // Check if dead count exceeds previous balance
        if (parseInt(deadCount) > parseInt(previousBalance)) {
            document.getElementById('modal_dead_count_error').textContent = 'Dead count cannot exceed previous balance';
            document.getElementById('modal_dead_count_error').style.display = 'block';
            document.getElementById('modal_dead_count').classList.add('error');
            isValid = false;
        }
        
        return isValid;
    }

    // Save species data and close modal
    function saveAndCloseSpeciesModal() {
        if (!validateModalForm()) {
            return;
        }
        
        // Get image preview if exists
        let imagePreview = '';
        const modalImagePreview = document.getElementById('modal-image-preview');
        if (modalImagePreview.style.display === 'block') {
            imagePreview = modalImagePreview.innerHTML;
        }
        
        // Create species object
        const speciesData = {
            species_name: document.getElementById('modal_species_name').value,
            stock_number: document.getElementById('modal_stock_number').value,
            previous_balance: document.getElementById('modal_previous_balance').value,
            dead_count: document.getElementById('modal_dead_count').value,
            total_stocks: document.getElementById('modal_total_stocks').value,
            image_preview: imagePreview
        };
        
        // Add to array
        additionalSpecies.push(speciesData);
        
        // Render additional species
        renderAdditionalSpecies();
        
        // Close modal
        closeSpeciesModal();
        
        // Show success message
        showSuccessModal('Additional species added successfully!');
    }

    // Render additional species in the form
    function renderAdditionalSpecies() {
        const container = document.getElementById('additionalSpeciesContainer');
        if (!container) return;
        
        if (additionalSpecies.length === 0) {
            container.innerHTML = '';
            return;
        }
        
        let html = '';
        
        additionalSpecies.forEach((species, index) => {
            html += `
                <div class="form-section additional-species-item" id="species-${index}">
                    <div class="species-item-header">
                        <h2 class="section-title">ADDITIONAL SPECIES DETAILS</h2>
                        <button class="remove-species-btn" onclick="removeSpecies(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Species Image</label>
                            <div class="species-image-container">
                                ${species.image_preview ? species.image_preview : '<div class="image-upload-label"><i class="fas fa-image"></i><span>No image uploaded</span></div>'}
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Species Name</label>
                            <input type="text" value="${species.species_name}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Accredited Breeding Stock Number</label>
                            <input type="text" value="${species.stock_number}" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Previous Quarter Balance (Alive)</label>
                            <input type="text" value="${species.previous_balance}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Dead Count</label>
                            <input type="text" value="${species.dead_count}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Total Stocks</label>
                            <div class="total-stocks-display">${species.total_stocks}</div>
                        </div>
                    </div>
                    <input type="hidden" name="additional_species[${index}][species_name]" value="${species.species_name}">
                    <input type="hidden" name="additional_species[${index}][stock_number]" value="${species.stock_number}">
                    <input type="hidden" name="additional_species[${index}][previous_balance]" value="${species.previous_balance}">
                    <input type="hidden" name="additional_species[${index}][dead_count]" value="${species.dead_count}">
                    <input type="hidden" name="additional_species[${index}][total_stocks]" value="${species.total_stocks}">
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Add event listeners to remove image buttons in additional species
        additionalSpecies.forEach((species, index) => {
            const removeBtn = document.querySelector(`#species-${index} .remove-image-btn`);
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    removeAdditionalSpeciesImage(index);
                });
            }
        });
    }

    // Remove image from additional species
    function removeAdditionalSpeciesImage(index) {
        if (index >= 0 && index < additionalSpecies.length) {
            additionalSpecies[index].image_preview = '';
            renderAdditionalSpecies();
        }
    }

    // Remove species from array
    function removeSpecies(index) {
        if (index >= 0 && index < additionalSpecies.length) {
            additionalSpecies.splice(index, 1);
            renderAdditionalSpecies();
        }
    }

    // Form validation before submission
    document.getElementById('breedingForm').addEventListener('submit', function(e) {
        const requiredFields = [
            'start_date', 'end_date', 'name', 'address', 'wfp_no', 'farm_location',
            'species_name', 'stock_number', 'previous_balance', 'dead_count'
        ];
        
        let isValid = true;
        let firstMissingField = null;
        
        // Validate required fields
        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field}"]`);
            const errorElement = document.getElementById(`${field}_error`);
            
            if (!input || !input.value.trim()) {
                isValid = false;
                if (!firstMissingField) {
                    firstMissingField = input;
                }
                // Add error class to missing field
                input.classList.add('error');
                if (errorElement) {
                    errorElement.textContent = 'This field is required';
                    errorElement.style.display = 'block';
                }
                input.addEventListener('input', function() {
                    this.classList.remove('error');
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                });
            }
        });
        
        // Validate name (letters only)
        const nameInput = document.getElementById('name');
        const nameError = document.getElementById('name_error');
        if (nameInput && nameInput.value.trim() && !/^[a-zA-Z ]*$/.test(nameInput.value.trim())) {
            isValid = false;
            nameInput.classList.add('error');
            if (nameError) {
                nameError.textContent = 'Only letters and spaces are allowed';
                nameError.style.display = 'block';
            }
            nameInput.addEventListener('input', function() {
                if (/^[a-zA-Z ]*$/.test(this.value.trim())) {
                    this.classList.remove('error');
                    if (nameError) {
                        nameError.style.display = 'none';
                    }
                }
            });
        }
        
        // Validate WFP number (numeric only)
        const wfpNoInput = document.getElementById('wfp_no');
        const wfpNoError = document.getElementById('wfp_no_error');
        if (wfpNoInput && wfpNoInput.value.trim() && !/^\d+$/.test(wfpNoInput.value.trim())) {
            isValid = false;
            wfpNoInput.classList.add('error');
            if (wfpNoError) {
                wfpNoError.textContent = 'WFP number must be numeric';
                wfpNoError.style.display = 'block';
            }
            wfpNoInput.addEventListener('input', function() {
                if (/^\d+$/.test(this.value.trim())) {
                    this.classList.remove('error');
                    if (wfpNoError) {
                        wfpNoError.style.display = 'none';
                    }
                }
            });
        }
        
        // Validate numeric fields (positive numbers)
        const numericFields = ['stock_number', 'previous_balance', 'dead_count'];
        numericFields.forEach(field => {
            const input = document.getElementById(field);
            const errorElement = document.getElementById(`${field}_error`);
            
            if (input && input.value.trim() && (isNaN(input.value) || parseInt(input.value) < 0)) {
                isValid = false;
                input.classList.add('error');
                if (errorElement) {
                    errorElement.textContent = 'Must be a positive number';
                    errorElement.style.display = 'block';
                }
                input.addEventListener('input', function() {
                    if (!isNaN(this.value) && parseInt(this.value) >= 0) {
                        this.classList.remove('error');
                        if (errorElement) {
                            errorElement.style.display = 'none';
                        }
                    }
                });
            }
        });
        
        // Validate dead count doesn't exceed previous balance
        const previousBalance = parseInt(document.getElementById('previous_balance').value) || 0;
        const deadCount = parseInt(document.getElementById('dead_count').value) || 0;
        const deadCountError = document.getElementById('dead_count_error');
        if (deadCount > previousBalance) {
            isValid = false;
            document.getElementById('dead_count').classList.add('error');
            if (deadCountError) {
                deadCountError.textContent = 'Dead count cannot exceed previous balance';
                deadCountError.style.display = 'block';
            }
            document.getElementById('dead_count').addEventListener('input', function() {
                const newDeadCount = parseInt(this.value) || 0;
                if (newDeadCount <= previousBalance) {
                    this.classList.remove('error');
                    if (deadCountError) {
                        deadCountError.style.display = 'none';
                    }
                }
            });
        }
        
        // Validate dates
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const endDateError = document.getElementById('end_date_error');
        if (startDate && endDate && startDate.value && endDate.value) {
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);
            
            if (start > end) {
                isValid = false;
                endDate.classList.add('error');
                if (endDateError) {
                    endDateError.textContent = 'End date must be after start date';
                    endDateError.style.display = 'block';
                }
                endDate.addEventListener('change', function() {
                    const newEnd = new Date(this.value);
                    if (newEnd >= start) {
                        this.classList.remove('error');
                        if (endDateError) {
                            endDateError.style.display = 'none';
                        }
                    }
                });
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // Scroll to first error field
            if (firstMissingField) {
                firstMissingField.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('speciesModal');
        if (event.target == modal) {
            closeSpeciesModal();
        }
        
        const successPopup = document.getElementById('successPopup');
        if (event.target == successPopup) {
            closeSuccessPopup();
        }
        
        const successModal = document.getElementById('successModal');
        if (event.target == successModal) {
            closeSuccessModal();
        }
    }
</script>
</body>
</html>