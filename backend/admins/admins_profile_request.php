<?php
// backend/marine/update_marine_profile.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';
require_once __DIR__ . '/../../backend/admin/send_otp.php';

$user_id = $_SESSION['user_id'];

// First check for existing pending request for this user
$check_stmt = $conn->prepare('SELECT COUNT(*) FROM profile_update_requests WHERE user_id = ? AND status = "pending"');
$check_stmt->bind_param('i', $user_id);
$check_stmt->execute();
$check_stmt->bind_result($count);
$check_stmt->fetch();
$check_stmt->close();

// If pending request exists, block immediately
if ($count > 0) {
    echo json_encode(['success' => false, 'error' => 'You already have a pending profile update request.']);
    exit();
}

// Get current user data including profile image and email
$current_data = [];
$stmt = $conn->prepare('SELECT email, image FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($current_data['email'], $current_data['image']);
$stmt->fetch();
$stmt->close();

$original_email = $current_data['email'];
$current_image = $current_data['image'];

// Collect form fields
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$age = trim($_POST['age'] ?? '');
$department = trim($_POST['department'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validate passwords if provided
if ($password !== '' || $confirm_password !== '') {
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
        exit();
    }

    // Hash the password if it's valid
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}

// Validate required fields
if ($first_name === '' || $last_name === '' || $department === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit();
}

// Check if email is changed
$email_changed = (strtolower($email) !== strtolower($original_email));

// Only proceed with OTP if email was changed AND there's no pending request
if ($email_changed) {
    // First check if email already exists in users table
    $email_check = $conn->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
    $email_check->bind_param('si', $email, $user_id);
    $email_check->execute();
    $email_check->bind_result($email_exists);
    $email_check->fetch();
    $email_check->close();

    if ($email_exists > 0) {
        echo json_encode(['success' => false, 'error' => 'This email is already registered to another user.']);
        exit();
    }

    // Check if we need to send new OTP
    if (!isset($_SESSION['otp_sent']) || $_SESSION['otp_email'] !== $email) {
        $otp_code = random_int(100000, 999999);
        $_SESSION['otp_code'] = $otp_code;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_sent'] = time();

        if (!sendOTP($email, $otp_code)) {
            error_log("Failed to send OTP to $email");
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send verification email. Please try again later.'
            ]);
            exit();
        }

        echo json_encode([
            'success' => false,
            'otp_required' => true,
            'message' => 'Verification code sent to ' . obfuscateEmail($email)
        ]);
        exit();
    }

    // Verify OTP hasn't expired (5 minutes)
    if (time() - $_SESSION['otp_sent'] > 300) {
        echo json_encode([
            'success' => false,
            'otp_required' => true,
            'error' => 'Verification code has expired. A new one has been sent.'
        ]);
        unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email']);
        exit();
    }
}

function obfuscateEmail($email)
{
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;

    $username = $parts[0];
    $domain = $parts[1];

    $obfuscated = substr($username, 0, 2) . '***' . substr($username, -1);
    return $obfuscated . '@' . $domain;
}

// Handle image upload - use new image if provided, otherwise keep current image
$image_filename = $current_image;

if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../upload/admin_profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $new_filename = 'marine_' . $user_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
        $image_filename = $new_filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Image upload failed.']);
        exit();
    }
}

// Insert new request with conditional password inclusion
if (!empty($password)) {
    $sql = "INSERT INTO profile_update_requests (user_id, image, first_name, last_name, age, email, department, phone, password, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssissss', $user_id, $image_filename, $first_name, $last_name, $age, $email, $department, $phone, $hashed_password);
} else {
    $sql = "INSERT INTO profile_update_requests (user_id, image, first_name, last_name, age, email, department, phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssisss', $user_id, $image_filename, $first_name, $last_name, $age, $email, $department, $phone);
}

if ($stmt->execute()) {
    // Clear OTP session if any
    unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
