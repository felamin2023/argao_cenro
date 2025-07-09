<?php
session_start();
include '../../backend/connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$age = trim($_POST['age'] ?? '');
// Email, role, department are not updatable by user

// Validate
$errors = [];
if (strlen($first_name) < 2) $errors['first_name'] = 'First name too short.';
if (strlen($last_name) < 2) $errors['last_name'] = 'Last name too short.';
if (!empty($age) && (!is_numeric($age) || $age < 0)) $errors['age'] = 'Invalid age.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit();
}

// Handle profile image upload
$profile_image = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['profile_image']['tmp_name'];
    $fileName = basename($_FILES['profile_image']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($ext, $allowed)) {
        $newName = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        // Use absolute path for upload directory
        $uploadDir = realpath(__DIR__ . '/../../upload/admin_profiles');
        if ($uploadDir === false) {
            // Fallback: try to create the directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../upload/admin_profiles';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
        }
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
        if (move_uploaded_file($fileTmp, $dest)) {
            $profile_image = $newName;
        }
    }
}

// Update user
if ($profile_image !== null) {
    // Always update the image field, even if it's the same as before
    $sql = "UPDATE users SET first_name=?, last_name=?, age=?, image=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssi', $first_name, $last_name, $age, $profile_image, $user_id);
} else {
    $sql = "UPDATE users SET first_name=?, last_name=?, age=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $first_name, $last_name, $age, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update profile.']);
}
$stmt->close();
$conn->close();
