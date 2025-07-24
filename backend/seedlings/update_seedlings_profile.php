<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../admin/send_otp.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check for pending request
$check_stmt = $conn->prepare('SELECT COUNT(*) FROM profile_update_requests WHERE user_id = ? AND status = "pending"');
$check_stmt->bind_param('i', $user_id);
$check_stmt->execute();
$check_stmt->bind_result($count);
$check_stmt->fetch();
$check_stmt->close();

if ($count > 0) {
    echo json_encode(['success' => false, 'error' => 'You already have a pending profile update request.']);
    exit();
}

// Get current user data
$stmt = $conn->prepare('SELECT first_name, last_name, age, email, department, phone, image FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result(
    $current_first_name,
    $current_last_name,
    $current_age,
    $current_email,
    $current_department,
    $current_phone,
    $current_image
);
$stmt->fetch();
$stmt->close();

// Collect form fields, fallback to current value if empty
$first_name = isset($_POST['first_name']) && $_POST['first_name'] !== '' ? trim($_POST['first_name']) : $current_first_name;
$last_name = isset($_POST['last_name']) && $_POST['last_name'] !== '' ? trim($_POST['last_name']) : $current_last_name;
$age = isset($_POST['age']) && $_POST['age'] !== '' ? trim($_POST['age']) : $current_age;
$department = isset($_POST['department']) && $_POST['department'] !== '' ? trim($_POST['department']) : $current_department;
$phone = isset($_POST['phone']) && $_POST['phone'] !== '' ? trim($_POST['phone']) : $current_phone;
$email = isset($_POST['email']) && $_POST['email'] !== '' ? trim($_POST['email']) : $current_email;

// Check if email is changed
$email_changed = (strtolower($email) !== strtolower($current_email));

// --- OTP resend logic (like superregister) ---
if ($email_changed && isset($_POST['request_otp'])) {
    $otp_code = random_int(100000, 999999);
    $_SESSION['tree_otp_code'] = $otp_code;
    $_SESSION['tree_otp_email'] = $email;
    $_SESSION['tree_otp_sent'] = time();

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

// --- Normal OTP logic ---
if ($email_changed) {
    // If no OTP sent yet, send one
    if (!isset($_SESSION['tree_otp_sent']) || $_SESSION['tree_otp_email'] !== $email) {
        $otp_code = random_int(100000, 999999);
        $_SESSION['tree_otp_code'] = $otp_code;
        $_SESSION['tree_otp_email'] = $email;
        $_SESSION['tree_otp_sent'] = time();

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
    if (time() - $_SESSION['tree_otp_sent'] > 300) {
        echo json_encode([
            'success' => false,
            'otp_required' => true,
            'error' => 'Verification code has expired. Please resend.'
        ]);
        unset($_SESSION['tree_otp_code'], $_SESSION['tree_otp_sent'], $_SESSION['tree_otp_email']);
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
    $new_filename = 'tree_' . $user_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
        $image_filename = $new_filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Image upload failed.']);
        exit();
    }
}

// OTP verification
if ($email_changed) {
    if (isset($_POST['otp_code'])) {
        // Always check against the latest OTP and email
        if (
            !isset($_SESSION['tree_otp_code']) ||
            !isset($_SESSION['tree_otp_email']) ||
            $_SESSION['tree_otp_email'] !== $email ||
            $_POST['otp_code'] != $_SESSION['tree_otp_code']
        ) {
            echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
            exit();
        }
        unset($_SESSION['tree_otp_code'], $_SESSION['tree_otp_sent'], $_SESSION['tree_otp_email']);
    } else {
        // Block insert until OTP is verified
        echo json_encode(['success' => false, 'otp_required' => true, 'message' => 'Verification required']);
        exit();
    }
}

// Insert new request
$sql = "INSERT INTO profile_update_requests (user_id, image, first_name, last_name, age, email, department, phone, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($sql);
$stmt->bind_param('isssisss', $user_id, $image_filename, $first_name, $last_name, $age, $email, $department, $phone);

if ($stmt->execute()) {
    unset($_SESSION['tree_otp_code'], $_SESSION['tree_otp_sent'], $_SESSION['tree_otp_email']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
