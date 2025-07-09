<?php
// backend/admin/update_marine_profile.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

require_once __DIR__ . '/../../backend/connection.php';

$user_id = $_SESSION['user_id'];

// Collect fields
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$age = trim($_POST['age'] ?? '');
$department = trim($_POST['department'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Validate required fields
if ($first_name === '' || $last_name === '' || $department === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit();
}

// Handle image upload if present
$image_filename = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../../upload/admin_profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $new_filename = 'marine_' . $user_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $new_filename;
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
        $image_filename = $new_filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload image.']);
        exit();
    }
}

// Fetch email for the request
$email = '';
$stmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

// Insert request into profile_update_requests
$sql = "INSERT INTO profile_update_requests (user_id, image, first_name, last_name, age, email, department, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'isssisss',
    $user_id,
    $image_filename,
    $first_name,
    $last_name,
    $age,
    $email,
    $department,
    $phone
);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database insert failed.']);
}
$stmt->close();
$conn->close();
