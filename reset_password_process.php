
<?php
// reset_password_process.php
// Handles: email existence check, OTP generation, sending, OTP validation, and password reset
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/reset_password_error.log');
session_start();
include 'backend/connection.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$email = trim($_POST['email'] ?? '');

if ($action === 'request_otp') {
    // 1. Check if email exists and has admin role
    $stmt = $conn->prepare('SELECT id, role FROM users WHERE lower(email) = lower(?)');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'This account is not registered.']);
        exit;
    }

    $user = $result->fetch_assoc();
    if (strtolower($user['role']) !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'This account is not registered as an admin.']);
        exit;
    }

    // 2. Generate OTP
    $otp = rand(100000, 999999);
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    // 3. Send OTP via Gmail SMTP
    require_once __DIR__ . '/send_mail_smtp.php';
    $subject = 'Your Password Reset OTP';
    $message = "Your OTP code is: $otp\nThis code will expire in 5 minutes.";
    $mailSent = send_smtp_mail($email, $subject, $message);
    if ($mailSent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP.']);
    }
    exit;
}

if ($action === 'validate_otp') {
    $otpInput = $_POST['otp'] ?? '';
    if (!isset($_SESSION['reset_otp'], $_SESSION['otp_expiry']) || time() > $_SESSION['otp_expiry']) {
        echo json_encode(['success' => false, 'message' => 'OTP expired.']);
        exit;
    }
    if ($otpInput == $_SESSION['reset_otp']) {
        $_SESSION['otp_validated'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    }
    exit;
}

if ($action === 'reset_password') {
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    if (!isset($_SESSION['otp_validated']) || !$_SESSION['otp_validated']) {
        echo json_encode(['success' => false, 'message' => 'OTP not validated.']);
        exit;
    }
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    $email = $_SESSION['reset_email'];
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
    $stmt->bind_param('ss', $hashed, $email);
    $stmt->execute();
    // Clear session
    unset($_SESSION['reset_otp'], $_SESSION['otp_expiry'], $_SESSION['otp_validated'], $_SESSION['reset_email']);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
