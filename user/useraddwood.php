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
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

</head>
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

    .form-section {
        margin-bottom: 25px;
    }

    .general-section h3 {
        background-color: #2b6625;
        color: white;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 18px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #2b6625;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 15px;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        border-color: #2b6625;
        outline: none;
        box-shadow: 0 0 0 2px rgba(43, 102, 37, 0.2);
    }

    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
    }

    .form-row .form-group {
        flex: 1;
    }

    .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    table,
    th,
    td {
        border: 1px solid #ddd;
    }

    th,
    td {
        padding: 10px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    .add-row-btn {
        background-color: #2b6625;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 15px;
    }

    .declaration {
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 4px;
        border-left: 4px solid #2b6625;
        margin-bottom: 25px;
    }

    .declaration p {
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .signature-date {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .signature-box {
        width: 100%;
        margin-top: 20px;
    }

    .signature-pad-container {
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 10px;
        background: white;
    }

    #signature-pad {
        width: 100%;
        height: 150px;
        cursor: crosshair;
    }

    .signature-actions {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .signature-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .clear-signature {
        background-color: #ff4757;
        color: white;
    }

    .save-signature {
        background-color: #2b6625;
        color: white;
    }

    .signature-preview {
        margin-top: 15px;
        text-align: center;
    }

    #signature-image {
        max-width: 300px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .download-btn {
        background-color: #2b6625;
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 30px auto 0;
        transition: background-color 0.3s;
    }

    .download-btn:hover {
        background-color: #1e4a1a;
    }

    .hidden {
        display: none;
    }

    .declaration-input {
        border: none;
        border-bottom: 1px solid #999;
        border-radius: 0;
        padding: 0 5px;
        width: 300px;
        display: inline-block;
        background: transparent;
    }

    .declaration-input:focus {
        border-bottom: 2px solid #2b6625;
        outline: none;
        box-shadow: none;
    }

    .required::after {
        content: " *";
        color: #ff4757;
    }

    .address-same-notice {
        background-color: #e8f5e9;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        font-size: 14px;
        color: #2b6625;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .signature-date {
            flex-direction: column;
            gap: 20px;
        }

        .declaration-input {
            width: 100%;
            margin: 5px 0;
        }
    }

    /* Loading indicator */
    .loading {
        display: none;
        text-align: center;
        margin-top: 20px;
        color: #2b6625;
    }

    .loading i {
        margin-right: 10px;
    }

    /* Print-specific styles */
    @media print {

        .download-btn,
        .add-row-btn,
        .signature-actions,
        .signature-pad-container {
            display: none !important;
        }

        body {
            background-color: white;
            padding: 0;
        }

        .container {
            box-shadow: none;
            border: none;
            padding: 15px;
        }
    }

    .form-section h2,
    .plant-section h3,
    .supply-section h3,
    .declaration-section h3 {
        background-color: #2b6625;
        color: white;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 18px;
    }

    /* Make address field same height as name field */
    .same-height-input {
        height: 45px;
        /* Same height as text input */
        min-height: 45px;
        resize: vertical;
        /* Allow vertical resizing only */
    }
</style>

<body>
    <header>
        <div class="logo"> <a href="user_home.php"> <img src="seal.png" alt="Site Logo"> </a> </div>
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
        <!-- <div class="action-buttons">
            <button class="btn btn-primary" id="addFilesBtn">
                <i class="fas fa-plus-circle"></i> Add
            </button>
            <a href="usereditwood.php" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="userviewwood.php" class="btn btn-outline">
                <i class="fas fa-eye"></i> View
            </a>
        </div> -->

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

                <!-- ======= I. GENERAL INFORMATION ======= -->
                <!-- New Permit -->
                <div id="general-new" class="general-section" style="display:block;">
                    <h3 style="margin:18px 0 10px;">I. GENERAL INFORMATION (New)</h3>

                    <div class="name-fields">
                        <div class="name-field">
                            <input type="text" id="new-first-name" placeholder="First Name" required>
                        </div>
                        <div class="name-field">
                            <input type="text" id="new-middle-name" placeholder="Middle Name">
                        </div>
                        <div class="name-field">
                            <input type="text" id="new-last-name" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new-business-address" class="required">Complete Business Address:</label>
                        <textarea id="new-business-address" rows="1" class="same-height-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="new-plant-location" class="required">Plant Location (Barangay/Municipality/Province):</label>
                        <input type="text" id="new-plant-location">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new-contact-number" class="required">Contact Number(s):</label>
                            <input type="text" id="new-contact-number">
                        </div>

                        <div class="form-group">
                            <label for="new-email-address">Email Address:</label>
                            <input type="email" id="new-email-address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Type of Ownership:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="new-single-proprietorship" name="new-ownership-type" value="Single Proprietorship">
                                <label for="new-single-proprietorship">Single Proprietorship</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-partnership" name="new-ownership-type" value="Partnership">
                                <label for="new-partnership">Partnership</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-corporation" name="new-ownership-type" value="Corporation">
                                <label for="new-corporation">Corporation</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="new-cooperative" name="new-ownership-type" value="Cooperative">
                                <label for="new-cooperative">Cooperative</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Renewal -->
                <div id="general-renewal" class="general-section" style="display:none;">
                    <h3 style="margin:18px 0 10px;">I. GENERAL INFORMATION (Renewal)</h3>

                    <div class="name-fields">
                        <div class="name-field">
                            <input type="text" id="r-first-name" placeholder="First Name" required>
                        </div>
                        <div class="name-field">
                            <input type="text" id="r-middle-name" placeholder="Middle Name">
                        </div>
                        <div class="name-field">
                            <input type="text" id="r-last-name" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="r-address" class="required">Address:</label>
                        <textarea id="r-address" rows="1" class="same-height-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="r-plant-location" class="required">Plant Location:</label>
                        <input type="text" id="r-plant-location">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="r-contact-number" class="required">Contact Number:</label>
                            <input type="text" id="r-contact-number">
                        </div>

                        <div class="form-group">
                            <label for="r-email-address">Email:</label>
                            <input type="email" id="r-email-address">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Type of Ownership:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="r-single-proprietorship" name="r-ownership-type" value="Single Proprietorship">
                                <label for="r-single-proprietorship">Single Proprietorship</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-partnership" name="r-ownership-type" value="Partnership">
                                <label for="r-partnership">Partnership</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-corporation" name="r-ownership-type" value="Corporation">
                                <label for="r-corporation">Corporation</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="r-cooperative" name="r-ownership-type" value="Cooperative">
                                <label for="r-cooperative">Cooperative</label>
                            </div>
                        </div>
                    </div>

                    <div class="permit-info" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label for="r-previous-permit">Previous Permit No.:</label>
                            <input type="text" id="r-previous-permit" placeholder="e.g., WPP-XXXX-2024">
                        </div>

                        <div class="form-group">
                            <label for="r-expiry-date">Expiry Date:</label>
                            <input type="date" id="r-expiry-date">
                        </div>
                    </div>
                </div>
                <!-- ======= /I. GENERAL INFORMATION ======= -->

                <!-- ======= II. PLANT DESCRIPTION AND OPERATION (shared UI) ======= -->
                <div class="plant-section">
                    <h3 style="margin:18px 0 10px;">II. PLANT DESCRIPTION AND OPERATION</h3>

                    <div class="form-group">
                        <label class="required">Kind of Wood Processing Plant:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="resawmill" name="plant-type" value="Resawmill">
                                <label for="resawmill">Resawmill</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="saw-mill" name="plant-type" value="Saw Mill">
                                <label for="saw-mill">Saw Mill</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="veneer-plant" name="plant-type" value="Veneer Plant">
                                <label for="veneer-plant">Veneer Plant</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="plywood-plant" name="plant-type" value="Plywood Plant">
                                <label for="plywood-plant">Plywood Plant</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="other-plant" name="plant-type" value="Other">
                                <label for="other-plant">Others (Specify):</label>
                                <input type="text" id="other-plant-specify" style="width: 200px;" placeholder="Specify">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="daily-capacity" class="required">Daily Rated Capacity (per 8-hour shift):</label>
                        <input type="text" id="daily-capacity" placeholder="e.g., 20 m³">
                    </div>

                    <div class="form-group">
                        <label class="required">Machineries and Equipment to be Used (with specifications):</label>
                        <table id="machinery-table">
                            <thead>
                                <tr>
                                    <th>Type of Equipment/Machinery</th>
                                    <th>Brand/Model</th>
                                    <th>Horsepower/Capacity</th>
                                    <th>Quantity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="number" class="table-input" min="1"></td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-row-btn" id="add-machinery-row">Add Row</button>
                    </div>

                    <div class="form-group">
                        <label class="required">Source of Power Supply:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="electricity" name="power-source" value="Electricity">
                                <label for="electricity">Electricity</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="generator" name="power-source" value="Generator">
                                <label for="generator">Generator</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="other-power" name="power-source" value="Other">
                                <label for="other-power">Others (Specify):</label>
                                <input type="text" id="other-power-specify" style="width: 200px;" placeholder="Specify">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======= III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS (shared UI) ======= -->
                <div class="supply-section">
                    <h3 style="margin:18px 0 10px;">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</h3>

                    <div class="form-group">
                        <p>The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
                        <table id="supply-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Species</th>
                                    <th>Contracted Vol.</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><input type="text" class="table-input"></td>
                                    <td><button type="button" class="remove-row-btn">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="add-row-btn" id="add-supply-row">Add Row</button>
                    </div>
                </div>

                <!-- ======= IV. DECLARATION (DIFFERS NEW vs RENEWAL) + SIGNATURE (shared) ======= -->
                <div class="declaration-section">
                    <h3 style="margin:18px 0 10px;">IV. DECLARATION</h3>

                    <!-- New declaration -->
                    <div id="declaration-new" data-permit-for="new" style="display:block;">
                        <p>
                            I,
                            <input type="text" id="declaration-name-new" class="declaration-input" placeholder="Enter your full name">,
                            of legal age, a citizen of the Philippines, with residence at
                            <input type="text" id="declaration-address" class="declaration-input" placeholder="Enter your address">,
                            do hereby certify that the foregoing information and documents are true and correct to the best of my knowledge.
                        </p>
                        <p>
                            I further understand that any false statement or misrepresentation shall be ground for denial, cancellation, or revocation of the permit, without prejudice to legal actions that may be filed against me.
                        </p>
                    </div>

                    <!-- Renewal declaration -->
                    <div id="declaration-renewal" data-permit-for="renewal" style="display:none;">
                        <p>
                            I,
                            <input type="text" id="declaration-name-renewal" class="declaration-input" placeholder="Enter your full name">,
                            hereby certify that the above information is true and correct, and all requirements for renewal are submitted.
                        </p>
                    </div>

                    <!-- Shared signature pad -->
                    <div class="signature-date">
                        <div class="signature-box">
                            <label>Signature of Applicant:</label>
                            <div class="signature-pad-container">
                                <canvas id="signature-pad"></canvas>
                            </div>
                            <div class="signature-actions">
                                <button type="button" class="signature-btn clear-signature" id="clear-signature">Clear</button>
                                <button type="button" class="signature-btn undo-signature" id="undo-signature">Undo</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Download buttons REMOVED (generation moved to submit flow) -->

                <!-- Loading overlay (reused for submit progress) -->
                <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
                    <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px">Working…</div>
                </div>

                <!-- ================= NEW PERMIT REQUIREMENTS ================= -->
                <div id="new-requirements" class="requirements-list" style="display:block;">
                    <!-- a -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">a</span>
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

                    <!-- b -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">b</span>
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

                    <!-- c -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">c</span>
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

                    <!-- d -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">d</span>
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

                    <!-- e -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">e</span>
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

                    <!-- f -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">f</span>
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

                    <!-- g -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">g</span>
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

                    <!-- h -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">h</span>
                                For individual persons, proof of Filipino citizenship (Birth Certificate/Certificate of Naturalization)
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

                    <!-- i -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">i</span>
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

                    <!-- j -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">j</span>
                                GIS generated map with geo-tagged photos showing the location of WPP
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

                    <!-- k -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">k</span>
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

                    <!-- l -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">l</span>
                                Proof of sustainable sources of legally cut logs for at least 5 years
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

                    <!-- m (supporting docs for NEW) -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">m</span>
                                Supporting Documents
                            </div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">1. Original copy of Log/Veneer/Lumber Supply Contracts</p>
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
                            <p style="margin-bottom:10px;font-weight:500;">2. 5% Tree Inventory</p>
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
                            <p style="margin-bottom:10px;font-weight:500;">3. Electronic Copy of Inventory Data</p>
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
                            <p style="margin-bottom:10px;font-weight:500;">4. Validation Report</p>
                            <div class="file-input-container">
                                <label for="file-o5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o5"></div>
                        </div>

                        <div class="sub-requirement">
                            <p style="margin-bottom:10px;font-weight:500;">5. Copy of CTPO/PTPR and map (if source is private tree plantations)</p>
                            <div class="file-input-container">
                                <label for="file-o7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-o7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-o7"></div>
                        </div>
                    </div>
                    <!-- =============== /NEW REQUIREMENTS =============== -->

                </div>

                <!-- ================= RENEWAL REQUIREMENTS ================= -->
                <div id="renewal-requirements" class="requirements-list" style="display:none;">
                    <!-- 1 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">1</span>
                                Previously Approved WPP Permit
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r1" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r1" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r1"></div>
                        </div>
                    </div>

                    <!-- 2 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">2</span>
                                Certificate of Good Standing
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r2" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r2" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r2"></div>
                        </div>
                    </div>

                    <!-- 3 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">3</span>
                                Certificate that the WPP Holder has already installed CCTV Camera
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r3" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r3" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r3"></div>
                        </div>
                    </div>

                    <!-- 4 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">4</span>
                                Monthly Production and Disposition Report
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r4" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r4" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r4"></div>
                        </div>
                    </div>

                    <!-- 5 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">5</span>
                                Certificate of Registration as Log/Veneer/Lumber Importer
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r5" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r5" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r5"></div>
                        </div>
                    </div>

                    <!-- 6 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">6</span>
                                Original Copy of Log/Veneer/Lumber Supply Contracts
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r6" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r6" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r6"></div>
                        </div>
                    </div>

                    <!-- 7 -->
                    <div class="requirement-item">
                        <div class="requirement-header">
                            <div class="requirement-title">
                                <span class="requirement-number">7</span>
                                Proof of importation
                            </div>
                        </div>
                        <div class="file-upload">
                            <div class="file-input-container">
                                <label for="file-r7" class="file-input-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <input type="file" id="file-r7" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <span class="file-name">No file chosen</span>
                            </div>
                            <div class="uploaded-files" id="uploaded-files-r7"></div>
                        </div>
                    </div>
                </div>
                <!-- =============== /RENEWAL REQUIREMENTS =============== -->
            </div>

            <div class="form-footer">
                <button class="btn btn-primary" id="submitApplication">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal (Submit Application) -->
    <div id="confirmModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmModal" class="close-modal">&times;</span>
            <h3>Confirm Submission</h3>
            <p>Are you sure you want to submit this wood processing plant permit request?</p>
            <button id="confirmSubmitBtn" class="btn btn-primary" style="margin:10px 10px 0 0;">Yes, Submit</button>
            <button id="cancelSubmitBtn" class="btn btn-outline">Cancel</button>
        </div>
    </div>

    <!-- Modal: Pending NEW request (WPP) -->
    <div id="pendingNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:520px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Pending Request</div>
            <div style="padding:16px 20px;line-height:1.6">
                You already have a pending <b>new</b> Wood Processing Plant permit request. Please wait for updates before submitting another one.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="pendingNewOk" class="btn btn-primary" type="button">Okay</button>
            </div>
        </div>
    </div>

    <!-- Modal: Offer Renewal (user tried NEW but eligible for renewal) -->
    <div id="offerRenewalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Renewal Available</div>
            <div style="padding:16px 20px;line-height:1.6">
                You can’t request a <b>new</b> WPP permit because you already have an approved one. You’re allowed to request a <b>renewal</b> instead.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="offerRenewalOk" class="btn btn-outline" type="button">Okay</button>
                <button id="offerRenewalSwitch" class="btn btn-primary" type="button">Request renewal</button>
            </div>
        </div>
    </div>

    <!-- Modal: Need Approved NEW (user tried RENEWAL without prior approved NEW) -->
    <div id="needApprovedNewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:10000;align-items:center;justify-content:center;">
        <div style="background:#fff;max-width:560px;width:92%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
            <div style="padding:18px 20px;border-bottom:1px solid #eee;font-weight:600">Action Required</div>
            <div style="padding:16px 20px;line-height:1.6">
                To request a renewal, you must have an approved <b>NEW</b> WPP permit on record.<br><br>
                You can switch to a NEW permit request. We’ll copy over what you’ve already entered.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;background:#fafafa;border-top:1px solid #eee">
                <button id="needApprovedNewOk" class="btn btn-outline" type="button">Okay</button>
                <button id="needApprovedNewSwitch" class="btn btn-primary" type="button">Request new</button>
            </div>
        </div>
    </div>

    <!-- Notification toast -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>
    <script>
        (function() {
            /* ===================== CONFIG ===================== */
            const SIG_WIDTH = 220; // for doc image tag (px)
            const SIG_HEIGHT = 80; // for doc image tag (px)

            const SAVE_URL = new URL('../backend/users/wood/save_wood.php', window.location.href).toString();
            const PRECHECK_URL = new URL('../backend/users/wood/precheck_wood.php', window.location.href).toString();

            /* ===================== Helpers ===================== */
            const $ = (sel) => document.querySelector(sel);
            const $$ = (sel) => Array.from(document.querySelectorAll(sel));
            const v = (id) => (document.getElementById(id)?.value || '').trim();
            const show = (el, on = true) => el && (el.style.display = on ? 'block' : 'none');

            function toast(msg) {
                const n = document.getElementById('profile-notification');
                if (!n) return;
                n.textContent = msg;
                n.style.display = 'block';
                n.style.opacity = '1';
                setTimeout(() => {
                    n.style.opacity = '0';
                    setTimeout(() => {
                        n.style.display = 'none';
                        n.style.opacity = '1';
                    }, 350);
                }, 2400);
            }

            const activePermitType = () =>
                (document.querySelector('.permit-type-btn.active')?.getAttribute('data-type') || 'new');

            /* ===================== Permit type toggle ===================== */
            const btnNew = document.querySelector('.permit-type-btn[data-type="new"]');
            const btnRenewal = document.querySelector('.permit-type-btn[data-type="renewal"]');

            function setType(type) {
                const isNew = type === 'new';
                btnNew?.classList.toggle('active', isNew);
                btnRenewal?.classList.toggle('active', !isNew);

                show(document.getElementById('general-new'), isNew);
                show(document.getElementById('general-renewal'), !isNew);
                show(document.getElementById('declaration-new'), isNew);
                show(document.getElementById('declaration-renewal'), !isNew);
                show(document.getElementById('new-requirements'), isNew);
                show(document.getElementById('renewal-requirements'), !isNew);
            }
            btnNew?.addEventListener('click', () => setType('new'));
            btnRenewal?.addEventListener('click', () => setType('renewal'));
            setType('new');

            /* ===================== Mobile menu toggle ===================== */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => {
                const isActive = navContainer.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
            });

            /* ===================== Dynamic tables ===================== */
            const machTbody = document.querySelector('#machinery-table tbody');
            document.getElementById('add-machinery-row')?.addEventListener('click', () => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td><input type="text" class="table-input"></td>
      <td><input type="text" class="table-input"></td>
      <td><input type="text" class="table-input"></td>
      <td><input type="number" class="table-input" min="1"></td>
      <td><button type="button" class="remove-row-btn">Remove</button></td>`;
                machTbody.appendChild(tr);
                tr.querySelector('.remove-row-btn')?.addEventListener('click', () => tr.remove());
            });
            machTbody?.querySelectorAll('.remove-row-btn').forEach((b) =>
                b.addEventListener('click', () => b.closest('tr')?.remove())
            );

            const supplyTbody = document.querySelector('#supply-table tbody');
            document.getElementById('add-supply-row')?.addEventListener('click', () => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td><input type="text" class="table-input"></td>
      <td><input type="text" class="table-input"></td>
      <td><input type="text" class="table-input"></td>
      <td><button type="button" class="remove-row-btn">Remove</button></td>`;
                supplyTbody.appendChild(tr);
                tr.querySelector('.remove-row-btn')?.addEventListener('click', () => tr.remove());
            });
            supplyTbody?.querySelectorAll('.remove-row-btn').forEach((b) =>
                b.addEventListener('click', () => b.closest('tr')?.remove())
            );

            /* ===================== File input name preview ===================== */
            document.addEventListener('change', (e) => {
                const input = e.target;
                if (input?.classList?.contains('file-input')) {
                    const nameSpan = input.parentElement?.querySelector('.file-name');
                    if (nameSpan) nameSpan.textContent = input.files?.[0]?.name || 'No file chosen';
                }
            });

            /* ===================== Signature pad (with undo) ===================== */
            const canvas = document.getElementById('signature-pad');
            const clearBtn = document.getElementById('clear-signature');
            const undoBtn = document.getElementById('undo-signature');
            let isDrawing = false,
                lastX = 0,
                lastY = 0;
            let strokes = [],
                currentStroke = [];

            function ctxStyle(ctx) {
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111';
            }

            function repaint(bg = true) {
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const cssW = canvas.clientWidth || 300;
                const cssH = canvas.clientHeight || 200;
                if (bg) {
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, cssW, cssH);
                }
                ctxStyle(ctx);
                for (const s of strokes) {
                    if (s.length < 2) continue;
                    ctx.beginPath();
                    ctx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) ctx.lineTo(s[i].x, s[i].y);
                    ctx.stroke();
                }
            }

            function resizeCanvas() {
                if (!canvas) return;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssW = canvas.clientWidth || 300;
                const cssH = 200;
                canvas.width = Math.floor(cssW * ratio);
                canvas.height = Math.floor(cssH * ratio);
                const ctx = canvas.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                repaint(true);
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : null;
                const cx = t ? t.clientX : e.clientX;
                const cy = t ? t.clientY : e.clientY;
                return {
                    x: cx - rect.left,
                    y: cy - rect.top
                };
            }

            function startDraw(e) {
                if (!canvas) return;
                isDrawing = true;
                currentStroke = [];
                const {
                    x,
                    y
                } = getPos(e);
                lastX = x;
                lastY = y;
                currentStroke.push({
                    x,
                    y
                });
                e.preventDefault?.();
            }

            function draw(e) {
                if (!isDrawing || !canvas) return;
                const ctx = canvas.getContext('2d');
                const {
                    x,
                    y
                } = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(x, y);
                ctx.stroke();
                lastX = x;
                lastY = y;
                currentStroke.push({
                    x,
                    y
                });
                e.preventDefault?.();
            }

            function endDraw() {
                if (!isDrawing) return;
                isDrawing = false;
                if (currentStroke.length > 1) strokes.push(currentStroke);
                currentStroke = [];
            }
            if (canvas) {
                // mouse
                canvas.addEventListener('mousedown', startDraw);
                canvas.addEventListener('mousemove', draw);
                window.addEventListener('mouseup', endDraw);
                // touch
                canvas.addEventListener('touchstart', startDraw, {
                    passive: false
                });
                canvas.addEventListener('touchmove', draw, {
                    passive: false
                });
                window.addEventListener('touchend', endDraw);
                // buttons
                clearBtn?.addEventListener('click', () => {
                    strokes = [];
                    repaint(true);
                });
                undoBtn?.addEventListener('click', () => {
                    if (strokes.length) {
                        strokes.pop();
                        repaint(true);
                    }
                });
            }

            function hasSignature() {
                return strokes && strokes.length > 0 && strokes.some(s => s.length > 1);
            }

            function getSignatureDataURL() {
                if (!canvas || !hasSignature()) return '';
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const cssW = canvas.width / ratio;
                const cssH = canvas.height / ratio;
                const off = document.createElement('canvas');
                off.width = Math.floor(cssW * ratio);
                off.height = Math.floor(cssH * ratio);
                const octx = off.getContext('2d');
                octx.setTransform(ratio, 0, 0, ratio, 0, 0);
                octx.fillStyle = '#fff';
                octx.fillRect(0, 0, cssW, cssH);
                ctxStyle(octx);
                for (const s of strokes) {
                    if (s.length < 2) continue;
                    octx.beginPath();
                    octx.moveTo(s[0].x, s[0].y);
                    for (let i = 1; i < s.length; i++) octx.lineTo(s[i].x, s[i].y);
                    octx.stroke();
                }
                return off.toDataURL('image/png');
            }

            /* ===================== Value helpers ===================== */
            function buildRows(tbody, colsExpected) {
                const trs = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
                if (!trs.length) return '';
                return trs.map(row => {
                    const ins = Array.from(row.querySelectorAll('input'));
                    const tds = [];
                    for (let i = 0; i < colsExpected; i++) tds.push(`<td>${(ins[i]?.value || '').toString()}</td>`);
                    return `<tr>${tds.join('')}</tr>`;
                }).join('');
            }

            function plantTypeValue() {
                let val = '';
                document.querySelectorAll('input[name="plant-type"]').forEach((r) => {
                    if (r.checked) {
                        val = r.value;
                        if (val === 'Other') val += ' - ' + (v('other-plant-specify') || '');
                    }
                });
                return val;
            }

            function powerSourceValue() {
                let val = '';
                document.querySelectorAll('input[name="power-source"]').forEach((r) => {
                    if (r.checked) {
                        val = r.value;
                        if (val === 'Other') val += ' - ' + (v('other-power-specify') || '');
                    }
                });
                return val;
            }

            /* ===================== MHTML (Word-friendly) ===================== */
            function makeMHTML(html, parts = []) {
                const boundary = '----=_NextPart_' + Date.now().toString(16);
                const header = [
                    'MIME-Version: 1.0',
                    `Content-Type: multipart/related; type="text/html"; boundary="${boundary}"`,
                    'X-MimeOLE: Produced By Microsoft MimeOLE',
                    '',
                    `--${boundary}`,
                    'Content-Type: text/html; charset="utf-8"',
                    'Content-Transfer-Encoding: 8bit',
                    '',
                    html
                ].join('\r\n');

                const bodyParts = parts.map(p => {
                    const wrapped = p.base64.replace(/.{1,76}/g, '$&\r\n');
                    return [
                        '',
                        `--${boundary}`,
                        `Content-Location: ${p.location}`,
                        'Content-Transfer-Encoding: base64',
                        `Content-Type: ${p.contentType}`,
                        '',
                        wrapped
                    ].join('\r\n');
                }).join('');

                return header + bodyParts + `\r\n--${boundary}--`;
            }

            function dataURLToBlob(dataURL) {
                if (!dataURL) return null;
                const [meta, b64] = dataURL.split(',');
                const mime = (meta.match(/data:(.*?);base64/) || [])[1] || 'application/octet-stream';
                const bin = atob(b64 || '');
                const u8 = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                return new Blob([u8], {
                    type: mime
                });
            }

            /* ===================== Document builders ===================== */
            function buildNewDocHTML(sigLocation, includeSignature) {
                const first = v('new-first-name');
                const middle = v('new-middle-name');
                const last = v('new-last-name');
                const applicantName = [first, middle, last].filter(Boolean).join(' ');

                const businessAddress = v('new-business-address');
                const plantLocation = v('new-plant-location');
                const contactNumber = v('new-contact-number');
                const emailAddress = v('new-email-address');
                const ownershipType = document.querySelector('input[name="new-ownership-type"]:checked')?.value || '';

                const plantType = plantTypeValue();
                const dailyCapacity = v('daily-capacity');
                const machineryRowsHTML = buildRows(machTbody, 4) || `<tr><td colspan="4"></td></tr>`;
                const powerSource = powerSourceValue();
                const supplyRowsHTML = buildRows(supplyTbody, 3) || `<tr><td colspan="3"></td></tr>`;

                const declarationName = v('declaration-name-new') || applicantName;
                const declarationAddress = v('declaration-address');

                const sigBlock = includeSignature ?
                    `<img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}" style="display:block;margin:8px 0 6px 0;border:1px solid #000;" alt="Signature">` :
                    '';

                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align:center; margin-bottom: 20px;">Application for Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">${applicantName}</span></p>
  <p class="info-line">Complete Business Address: <span class="underline">${businessAddress}</span></p>
  <p class="info-line">Plant Location (Barangay/Municipality/Province): <span class="underline">${plantLocation}</span></p>
  <p class="info-line">Contact Number(s): <span class="underline">${contactNumber}</span> Email Address: <span class="underline">${emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">${ownershipType}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">${plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">${dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      ${machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">${powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      ${supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION AND SIGNATURE</p>
  <div class="declaration">
    <p>I, <span class="underline">${declarationName}</span>, of legal age, a citizen of the Philippines, with residence at <span class="underline">${declarationAddress}</span>, do hereby certify that the foregoing information and documents are true and correct to the best of my knowledge.</p>
    <p>I further understand that any false statement or misrepresentation shall be ground for denial, cancellation, or revocation of the permit, without prejudice to legal actions that may be filed against me.</p>
    <div style="margin-top: 16px;">
      ${sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>`.trim();
            }

            function buildRenewalDocHTML(sigLocation, includeSignature) {
                const first = v('r-first-name');
                const middle = v('r-middle-name');
                const last = v('r-last-name');
                const applicantName = [first, middle, last].filter(Boolean).join(' ');

                const address = v('r-address');
                const plantLocation = v('r-plant-location');
                const contactNumber = v('r-contact-number');
                const emailAddress = v('r-email-address');
                const ownershipType = document.querySelector('input[name="r-ownership-type"]:checked')?.value || '';

                const previousPermit = v('r-previous-permit');
                const theExpiryDate = v('r-expiry-date');

                const plantType = plantTypeValue();
                const dailyCapacity = v('daily-capacity');
                const machineryRowsHTML = buildRows(machTbody, 4) || `<tr><td colspan="4"></td></tr>`;
                const powerSource = powerSourceValue();
                const supplyRowsHTML = buildRows(supplyTbody, 3) || `<tr><td colspan="3"></td></tr>`;

                const declarationName = v('declaration-name-renewal') || applicantName;

                const sigBlock = includeSignature ?
                    `<img src="${sigLocation}" width="${SIG_WIDTH}" height="${SIG_HEIGHT}" style="display:block;margin:8px 0 6px 0;border:1px solid #000;" alt="Signature">` :
                    '';

                return `
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Renewal of Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align: center; margin-bottom: 20px;">Application for Renewal of Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">${applicantName}</span></p>
  <p class="info-line">Address: <span class="underline">${address}</span></p>
  <p class="info-line">Plant Location: <span class="underline">${plantLocation}</span></p>
  <p class="info-line">Contact Number: <span class="underline">${contactNumber}</span> Email: <span class="underline">${emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">${ownershipType}</span></p>
  <p class="info-line">Previous Permit No.: <span class="underline">${previousPermit}</span> Expiry Date: <span class="underline">${theExpiryDate}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">${plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">${dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      ${machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">${powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      ${supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION</p>
  <div class="declaration">
    <p>I, <span class="underline">${declarationName}</span>, hereby certify that the above information is true and correct, and all requirements for renewal are submitted.</p>
    <div style="margin-top: 16px;">
      ${sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>`.trim();
            }

            /* ===================== Loading overlay ===================== */
            const loading = document.getElementById('loadingIndicator');

            /* ===================== Modals ===================== */
            const confirmModal = document.getElementById('confirmModal');
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const closeConfirmModal = document.getElementById('closeConfirmModal');

            const pendingNewModal = document.getElementById('pendingNewModal');
            const pendingNewOk = document.getElementById('pendingNewOk');
            const offerRenewalModal = document.getElementById('offerRenewalModal');
            const offerRenewalOk = document.getElementById('offerRenewalOk');
            const offerRenewalSwitch = document.getElementById('offerRenewalSwitch');
            const needApprovedNewModal = document.getElementById('needApprovedNewModal');
            const needApprovedNewOk = document.getElementById('needApprovedNewOk');
            const needApprovedNewSwitch = document.getElementById('needApprovedNewSwitch');

            pendingNewOk?.addEventListener('click', () => (pendingNewModal.style.display = 'none'));
            offerRenewalOk?.addEventListener('click', () => (offerRenewalModal.style.display = 'none'));
            needApprovedNewOk?.addEventListener('click', () => (needApprovedNewModal.style.display = 'none'));
            closeConfirmModal?.addEventListener('click', () => (confirmModal.style.display = 'none'));
            cancelSubmitBtn?.addEventListener('click', () => (confirmModal.style.display = 'none'));

            // Autofill helper when switching between types via modals
            function autofillRenewalFromNew() {
                const map = [
                    ['new-first-name', 'r-first-name'],
                    ['new-middle-name', 'r-middle-name'],
                    ['new-last-name', 'r-last-name'],
                    ['new-plant-location', 'r-plant-location'],
                    ['new-contact-number', 'r-contact-number'],
                    ['new-email-address', 'r-email-address'],
                ];
                map.forEach(([src, dst]) => {
                    const s = document.getElementById(src);
                    const d = document.getElementById(dst);
                    if (s && d && typeof s.value === 'string') d.value = s.value;
                });
                // Address and declaration
                if (!v('declaration-name-renewal')) {
                    const nm = [v('new-first-name'), v('new-middle-name'), v('new-last-name')].filter(Boolean).join(' ');
                    const dn = document.getElementById('declaration-name-renewal');
                    if (dn) dn.value = nm;
                }
                const addr = v('new-business-address');
                const rn = document.getElementById('r-address');
                if (rn && !rn.value) rn.value = addr;
            }

            function autofillNewFromRenewal() {
                const map = [
                    ['r-first-name', 'new-first-name'],
                    ['r-middle-name', 'new-middle-name'],
                    ['r-last-name', 'new-last-name'],
                    ['r-plant-location', 'new-plant-location'],
                    ['r-contact-number', 'new-contact-number'],
                    ['r-email-address', 'new-email-address'],
                ];
                map.forEach(([src, dst]) => {
                    const s = document.getElementById(src);
                    const d = document.getElementById(dst);
                    if (s && d && typeof s.value === 'string') d.value = s.value;
                });
                // Declaration
                if (!v('declaration-name-new')) {
                    const nm = [v('r-first-name'), v('r-middle-name'), v('r-last-name')].filter(Boolean).join(' ');
                    const dn = document.getElementById('declaration-name-new');
                    if (dn) dn.value = nm;
                }
                // Business address from renewal address if not set
                if (!v('new-business-address')) {
                    const a = v('r-address');
                    const nb = document.getElementById('new-business-address');
                    if (nb) nb.value = a;
                }
            }

            offerRenewalSwitch?.addEventListener('click', () => {
                offerRenewalModal.style.display = 'none';
                setType('renewal');
                autofillRenewalFromNew();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            needApprovedNewSwitch?.addEventListener('click', () => {
                needApprovedNewModal.style.display = 'none';
                setType('new');
                autofillNewFromRenewal();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            /* ===================== PRECHECK before confirm ===================== */
            const submitApplicationBtn = document.getElementById('submitApplication');

            function validateTopInputs() {
                if (activePermitType() === 'new') {
                    if (!v('new-first-name') || !v('new-last-name')) {
                        toast('First and Last name are required for New applications.');
                        return false;
                    }
                } else {
                    if (!v('r-first-name') || !v('r-last-name')) {
                        toast('First and Last name are required for Renewal.');
                        return false;
                    }
                }
                return true;
            }

            submitApplicationBtn?.addEventListener('click', async () => {
                if (!validateTopInputs()) return;

                try {
                    const type = activePermitType();
                    const first = type === 'renewal' ? v('r-first-name') : v('new-first-name');
                    const middle = type === 'renewal' ? v('r-middle-name') : v('new-middle-name');
                    const last = type === 'renewal' ? v('r-last-name') : v('new-last-name');

                    const fd = new FormData();
                    fd.append('first_name', first);
                    fd.append('middle_name', middle);
                    fd.append('last_name', last);
                    fd.append('desired_permit_type', type);

                    const res = await fetch(PRECHECK_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                    const json = await res.json();
                    if (!res.ok) throw new Error(json.message || 'Precheck failed');

                    if (json.block === 'pending_new') {
                        pendingNewModal.style.display = 'flex';
                        return;
                    }
                    if (json.block === 'pending_renewal') {
                        toast('You already have a pending wood processing renewal. Please wait for the update first.');
                        return;
                    }
                    if (json.block === 'need_approved_new') {
                        needApprovedNewModal.style.display = 'flex';
                        return;
                    }
                    if (json.offer === 'renewal' && type === 'new') {
                        offerRenewalModal.style.display = 'flex';
                        return;
                    }
                    confirmModal.style.display = 'block';
                } catch (e) {
                    console.error(e);
                    // If precheck fails unexpectedly, still allow manual confirm so user gets feedback.
                    confirmModal.style.display = 'block';
                }
            });

            /* ===================== FINAL SUBMIT (generate doc + upload + save) ===================== */
            confirmSubmitBtn?.addEventListener('click', async () => {
                confirmModal.style.display = 'none';
                // block UI
                loading.style.display = 'flex';
                submitApplicationBtn.disabled = true;

                try {
                    // 1) Build Word (MHTML) with embedded signature
                    const sigData = getSignatureDataURL();
                    const hasSig = !!sigData;
                    const sigLocation = 'signature.png';
                    const html =
                        activePermitType() === 'renewal' ?
                        buildRenewalDocHTML(sigLocation, hasSig) :
                        buildNewDocHTML(sigLocation, hasSig);
                    const parts = hasSig ? [{
                        location: sigLocation,
                        contentType: 'image/png',
                        base64: (sigData.split(',')[1] || '')
                    }] : [];
                    const mhtml = makeMHTML(html, parts);
                    const docBlob = new Blob([mhtml], {
                        type: 'application/msword'
                    });

                    const fullApplicantName =
                        activePermitType() === 'renewal' ? [v('r-first-name'), v('r-middle-name'), v('r-last-name')].filter(Boolean).join(' ') : [v('new-first-name'), v('new-middle-name'), v('new-last-name')].filter(Boolean).join(' ');

                    const docFilename =
                        (activePermitType() === 'renewal' ? 'WPP_Renewal_' : 'WPP_New_') +
                        (fullApplicantName || 'Applicant').replace(/\s+/g, '_') + '.doc';

                    // 2) Collect fields & files for backend save
                    const fd = new FormData();
                    const type = activePermitType();
                    fd.append('permit_type', type);

                    if (type === 'renewal') {
                        fd.append('first_name', v('r-first-name'));
                        fd.append('middle_name', v('r-middle-name'));
                        fd.append('last_name', v('r-last-name'));
                        fd.append('address', v('r-address'));
                        fd.append('plant_location', v('r-plant-location'));
                        fd.append('contact_number', v('r-contact-number'));
                        fd.append('email_address', v('r-email-address'));
                        fd.append('ownership_type', document.querySelector('input[name="r-ownership-type"]:checked')?.value || '');
                        fd.append('previous_permit_no', v('r-previous-permit'));
                        fd.append('expiry_date', v('r-expiry-date'));
                    } else {
                        fd.append('first_name', v('new-first-name'));
                        fd.append('middle_name', v('new-middle-name'));
                        fd.append('last_name', v('new-last-name'));
                        fd.append('business_address', v('new-business-address'));
                        fd.append('plant_location', v('new-plant-location'));
                        fd.append('contact_number', v('new-contact-number'));
                        fd.append('email_address', v('new-email-address'));
                        fd.append('ownership_type', document.querySelector('input[name="new-ownership-type"]:checked')?.value || '');
                    }

                    // Shared plant details
                    fd.append('plant_type', plantTypeValue());
                    fd.append('daily_capacity', v('daily-capacity'));
                    fd.append('power_source', powerSourceValue());

                    // Tables as minimal HTML (backend may store text or ignore)
                    fd.append('machinery_table_html', buildRows(machTbody, 4));
                    fd.append('supply_table_html', buildRows(supplyTbody, 3));

                    // Generated application document
                    fd.append('application_doc', new File([docBlob], docFilename, {
                        type: 'application/msword'
                    }));

                    // Signature file (optional)
                    if (hasSig) {
                        const sigBlob = dataURLToBlob(sigData);
                        fd.append('signature_file', new File([sigBlob], 'signature.png', {
                            type: 'image/png'
                        }));
                    }

                    // Attach ALL chosen files present in UI (IDs unchanged)
                    $$('input[type="file"]').forEach((fi) => {
                        if (fi.files?.[0]) fd.append(fi.id, fi.files[0]);
                    });

                    // 3) Submit to backend
                    const res = await fetch(SAVE_URL, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                    let json;
                    try {
                        json = await res.json();
                    } catch {
                        const text = await res.text();
                        throw new Error(`HTTP ${res.status} – ${text.slice(0, 200)}`);
                    }
                    if (!res.ok || !json.ok) throw new Error(json.error || `HTTP ${res.status}`);

                    toast("Application submitted. We'll notify you once reviewed.");
                    resetAllFields();
                } catch (e) {
                    console.error(e);
                    toast(e?.message || 'Submission failed. Please try again.');
                } finally {
                    loading.style.display = 'none';
                    submitApplicationBtn.disabled = false;
                }
            });

            /* ===================== Reset ===================== */
            function resetAllFields() {
                // New
                ['new-first-name', 'new-middle-name', 'new-last-name', 'new-business-address', 'new-plant-location', 'new-contact-number', 'new-email-address']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Renewal
                ['r-first-name', 'r-middle-name', 'r-last-name', 'r-address', 'r-plant-location', 'r-contact-number', 'r-email-address', 'r-previous-permit', 'r-expiry-date']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Shared
                ['daily-capacity', 'other-plant-specify', 'other-power-specify', 'declaration-name-new', 'declaration-address', 'declaration-name-renewal']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                // Radios
                $$('input[type="radio"]').forEach(r => (r.checked = false));
                // Files
                $$('input[type="file"]').forEach(i => {
                    i.value = '';
                    const n = i.parentElement?.querySelector('.file-name');
                    if (n) n.textContent = 'No file chosen';
                });
                // Tables: keep first row, clear inputs, remove extras
                [machTbody, supplyTbody].forEach(tbody => {
                    if (!tbody) return;
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.forEach((tr, idx) => {
                        if (idx === 0) tr.querySelectorAll('input').forEach(inp => (inp.value = ''));
                        else tr.remove();
                    });
                });
                // Signature
                strokes = [];
                repaint(true);
                // Back to NEW view
                setType('new');
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        })();
    </script>

</body>







</html>