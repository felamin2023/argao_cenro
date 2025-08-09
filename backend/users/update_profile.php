<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';
require_once __DIR__ . '/../../backend/admin/send_otp.php';

$user_id = $_SESSION['user_id'];

// Get current user data
$stmt = $conn->prepare('SELECT email, image FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($current_email, $current_image);
$stmt->fetch();
$stmt->close();

// Handle OTP resend request
if (isset($_POST['resend']) && $_POST['resend'] === 'true') {
    if (!isset($_SESSION['otp_email'])) {
        echo json_encode(['success' => false, 'error' => 'No email to resend to.']);
        exit();
    }

    $email = $_SESSION['otp_email'];
    $new_otp = random_int(100000, 999999);
    $_SESSION['otp_code'] = $new_otp;
    $_SESSION['otp_sent'] = time();
    $_SESSION['otp_attempts'] = 0; // Reset attempts on resend

    if (!sendOTP($email, $new_otp)) {
        echo json_encode(['success' => false, 'error' => 'Failed to resend verification email.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'otp_required' => true,
        'message' => 'New verification code sent!'
    ]);
    exit();
}

// Handle OTP verification
if (isset($_POST['otp_code'])) {
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 1;
    } else {
        $_SESSION['otp_attempts']++;
    }

    if ($_SESSION['otp_attempts'] > 5) {
        echo json_encode(['success' => false, 'error' => 'Too many attempts. Please try again later.']);
        exit();
    }

    $submitted_otp = trim($_POST['otp_code']);

    if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_sent'])) {
        echo json_encode(['success' => false, 'error' => 'Verification session expired.']);
        exit();
    }

    if (time() - $_SESSION['otp_sent'] > 300) {
        unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email']);
        echo json_encode(['success' => false, 'error' => 'Verification code has expired.']);
        exit();
    }

    if ($submitted_otp != $_SESSION['otp_code']) {
        echo json_encode(['success' => false, 'error' => 'Invalid verification code.']);
        exit();
    }

    // OTP is valid - clear session data
    unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email'], $_SESSION['otp_attempts']);
}

// Collect form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$age = trim($_POST['age'] ?? '');
$department = trim($_POST['department'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validate passwords
if ($password && $password !== $confirm_password) {
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit();
}

// Validate required fields
if (!$first_name || !$last_name || !$department) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit();
}

// Check if email changed
$email_changed = strtolower($email) !== strtolower($current_email);

// Handle email change with OTP
if ($email_changed && !isset($_POST['otp_code'])) {
    // Check if email exists
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $stmt->bind_result($email_exists);
    $stmt->fetch();
    $stmt->close();

    if ($email_exists) {
        echo json_encode(['success' => false, 'error' => 'Email already registered.']);
        exit();
    }

    // Generate and send OTP
    $otp_code = random_int(100000, 999999);
    $_SESSION['otp_code'] = $otp_code;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_sent'] = time();
    $_SESSION['otp_attempts'] = 0;

    if (!sendOTP($email, $otp_code)) {
        echo json_encode(['success' => false, 'error' => 'Failed to send verification email.']);
        exit();
    }

    echo json_encode([
        'success' => false,
        'otp_required' => true,
        'message' => 'Verification code sent to your email'
    ]);
    exit();
}

// Handle image upload
$image_filename = $current_image;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../upload/user_profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
        $image_filename = $new_filename;

        // Delete old image if it's not the default
        if ($current_image && $current_image !== 'default-profile.jpg') {
            $old_image_path = $upload_dir . $current_image;
            if (file_exists($old_image_path)) {
                @unlink($old_image_path);
            }
        }
    }
}

// Update user
$sql = "UPDATE users SET 
        image = ?, 
        first_name = ?, 
        last_name = ?, 
        age = ?, 
        email = ?, 
        department = ?, 
        phone = ?" .
    ($password ? ", password = ?" : "") .
    " WHERE id = ?";

$stmt = $conn->prepare($sql);
if ($password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt->bind_param('ssssssssi', $image_filename, $first_name, $last_name, $age, $email, $department, $phone, $hashed_password, $user_id);
} else {
    $stmt->bind_param('sssssssi', $image_filename, $first_name, $last_name, $age, $email, $department, $phone, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
