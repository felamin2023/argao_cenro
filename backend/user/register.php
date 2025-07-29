<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connection.php';

header('Content-Type: application/json');

// Debug setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'send_otp':
            handleSendOtp($conn);
            break;
        case 'verify_otp':
            handleVerifyOtp($conn);
            break;
        case 'register':
            handleRegister($conn);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}

function handleSendOtp($conn)
{
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));

    if (empty($email)) {
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    // Check email status
    $stmt = $conn->prepare("SELECT id, status FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (strtolower($user['status']) === 'verified') {
            echo json_encode(['error' => 'Email already registered']);
            return;
        }
    }

    // Generate and store OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_verification'] = [
        'email' => $email,
        'code' => $otp,
        'expires' => time() + 900, // 15 minutes
        'attempts' => 0
    ];

    // Send OTP email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'argaocenro@gmail.com';
        $mail->Password = 'rlqh eihc lyoa etbl';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('argaocenro@gmail.com', 'DENR System');
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your verification code is: $otp";

        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to send OTP']);
    }
}

function handleVerifyOtp($conn)
{
    $userOtp = trim($_POST['otp'] ?? '');

    if (!isset($_SESSION['otp_verification'])) {
        echo json_encode(['error' => 'OTP session expired']);
        return;
    }

    $otpData = $_SESSION['otp_verification'];

    // Check attempts
    if ($otpData['attempts'] >= 3) {
        echo json_encode(['error' => 'Too many attempts']);
        return;
    }

    // Check expiration
    if (time() > $otpData['expires']) {
        echo json_encode(['error' => 'OTP expired']);
        return;
    }

    // Verify OTP (as strings)
    if ((string)$userOtp === (string)$otpData['code']) {
        $_SESSION['email_verified'] = $otpData['email'];
        unset($_SESSION['otp_verification']);
        echo json_encode(['success' => true]);
    } else {
        $_SESSION['otp_verification']['attempts']++;
        $remaining = 3 - $_SESSION['otp_verification']['attempts'];
        echo json_encode(['error' => "Invalid OTP. $remaining attempts left"]);
    }
}

function handleRegister($conn)
{
    if (!isset($_SESSION['email_verified'])) {
        echo json_encode(['error' => 'Email not verified']);
        return;
    }

    $email = $conn->real_escape_string($_SESSION['email_verified']);
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($phone) || empty($password) || empty($confirm)) {
        echo json_encode(['error' => 'All fields required']);
        return;
    }

    if ($password !== $confirm) {
        echo json_encode(['error' => 'Passwords mismatch']);
        return;
    }

    if (!preg_match('/^09\d{9}$/', $phone)) {
        echo json_encode(['error' => 'Invalid phone format']);
        return;
    }

    // Create user
    try {
        $stmt = $conn->prepare("INSERT INTO Users 
            (email, phone, password, role, status, created_at) 
            VALUES (?, ?, ?, 'User', 'Verified', NOW())");

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt->bind_param("sss", $email, $phone, $hashed);
        $stmt->execute();

        // Notify CENRO
        notifyCenro($conn, $email);

        unset($_SESSION['email_verified']);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function notifyCenro($conn, $newUserEmail)
{
    $emails = [];
    $result = $conn->query("SELECT email FROM users WHERE LOWER(department) = 'cenro'");

    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }

    if (!empty($emails)) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            // ... same email config as before ...

            foreach ($emails as $email) {
                $mail->addAddress($email);
            }

            $mail->Subject = 'New User Registration';
            $mail->Body = "New user registered: $newUserEmail";
            $mail->send();
        } catch (Exception $e) {
            error_log("CENRO notify failed: " . $e->getMessage());
        }
    }
}
