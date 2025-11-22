
<?php
// reset_password_process.php
// Handles: email existence check, OTP generation, sending, OTP validation, and password reset
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/reset_password_error.log');
session_start();
require_once __DIR__ . '/backend/connection.php'; // provides $pdo (PDO)
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$email = trim($_POST['email'] ?? '');

if ($action === 'request_otp') {
    // 1. Check if email exists and has admin role (use PDO)
    try {
        $st = $pdo->prepare('SELECT user_id AS id, role, email FROM public.users WHERE lower(email) = lower(:e) LIMIT 1');
        $st->execute([':e' => $email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[RESET-OTP] DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error.']);
        exit;
    }

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'This account is not registered.']);
        exit;
    }

    if (strtolower((string)$user['role']) !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'This account is not registered as an admin.']);
        exit;
    }

    // 2. Generate OTP
    $otp = random_int(100000, 999999);
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    // 3. Send OTP via Gmail SMTP
    require_once __DIR__ . '/send_mail_smtp.php';
    $subject = 'Reset Password';
    $message = "Your verification code is: $otp\nThis code will expire in 5 minutes.";
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
    if ((string)$otpInput === (string)($_SESSION['reset_otp'] ?? '')) {
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
    $email = $_SESSION['reset_email'] ?? '';
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'No email in session.']);
        exit;
    }
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    try {
        $u = $pdo->prepare('UPDATE public.users SET password = :pw WHERE lower(email) = lower(:e)');
        $u->execute([':pw' => $hashed, ':e' => $email]);
    } catch (Throwable $e) {
        error_log('[RESET-PASSWORD] DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error.']);
        exit;
    }
    // Optionally send confirmation email about password reset
    require_once __DIR__ . '/send_mail_smtp.php';
    $confirmSubj = 'Reset Password';
    $confirmMsg = "Your password was successfully reset. If you did not request this, contact support immediately.";
    // Try to send but ignore failure
    @send_smtp_mail($email, $confirmSubj, $confirmMsg);

    // Clear session
    unset($_SESSION['reset_otp'], $_SESSION['otp_expiry'], $_SESSION['otp_validated'], $_SESSION['reset_email']);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
