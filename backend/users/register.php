<?php
session_start();
require_once __DIR__ . '/../../backend/connection.php';
require_once __DIR__ . '/../admin/send_otp.php';
header('Content-Type: application/json');

function jsonResponse($success, $error = '', $extra = [])
{
    $resp = ['success' => $success];
    if ($error) $resp['error'] = $error;
    return die(json_encode(array_merge($resp, $extra)));
}


if (isset($_POST['action']) && $_POST['action'] === 'send_otp' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email address.');
    }
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        jsonResponse(false, 'Email already exists.');
    }
    $stmt->close();
    $otp = rand(100000, 999999);
    $_SESSION['email_otp'] = $otp;
    $_SESSION['email_otp_to'] = $email;

    if (!sendOTP($email, $otp)) {
        jsonResponse(false, 'Failed to send OTP email. Please try again later.');
    }
    jsonResponse(true, '');
}


if (isset($_POST['action']) && $_POST['action'] === 'verify_otp' && isset($_POST['otp'])) {
    $otp = trim($_POST['otp']);
    if ($otp == ($_SESSION['email_otp'] ?? '') && isset($_SESSION['email_otp_to'])) {
        $_SESSION['email_verified'] = true;
        jsonResponse(true);
    } else {
        jsonResponse(false, 'Incorrect OTP.');
    }
}


if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'User';
    $status = 'Verified';


    if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || ($_SESSION['email_otp_to'] ?? '') !== $email) {
        jsonResponse(false, 'Email not verified.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email address.');
    }
    if (!preg_match('/^09\d{9}$/', $phone)) {
        jsonResponse(false, 'Invalid phone number.');
    }
    if (strlen($password) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters.');
    }
    if ($password !== $confirm_password) {
        jsonResponse(false, 'Passwords do not match.');
    }
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        jsonResponse(false, 'Email already exists.');
    }
    $stmt->close();
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (email, phone, role, password, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $email, $phone, $role, $password_hash, $status);
    if ($stmt->execute()) {
        unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to']);
        jsonResponse(true);
    } else {
        jsonResponse(false, 'Registration failed: ' . $stmt->error);
    }
}

jsonResponse(false, 'Invalid request.');
