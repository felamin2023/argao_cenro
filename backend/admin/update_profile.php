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
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

$errors = [];
if (strlen($first_name) < 2) $errors['first_name'] = 'First name too short.';
if (strlen($last_name) < 2) $errors['last_name'] = 'Last name too short.';
if (!empty($age) && (!is_numeric($age) || $age < 0)) $errors['age'] = 'Invalid age.';

if (!empty($password) || !empty($confirm_password)) {
    if ($password !== $confirm_password) {
        $errors['password'] = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// Check if password is being changed
if (!empty($password)) {
    // Get current password hash
    $sql_check = "SELECT password FROM users WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('i', $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $user = $result->fetch_assoc();
    $stmt_check->close();

    // Verify if new password is different from current
    if (password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}

$profile_image = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['profile_image']['tmp_name'];
    $fileName = basename($_FILES['profile_image']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed)) {
        $newName = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $uploadDir = realpath(__DIR__ . '/../../upload/admin_profiles');

        if ($uploadDir === false) {
            $uploadDir = __DIR__ . '/../../upload/admin_profiles';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
        }

        $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
        if (move_uploaded_file($fileTmp, $dest)) {
            $profile_image = $newName;

            // Remove old profile image if it exists
            $sql_get_old = "SELECT image FROM users WHERE id = ?";
            $stmt_get_old = $conn->prepare($sql_get_old);
            $stmt_get_old->bind_param('i', $user_id);
            $stmt_get_old->execute();
            $result = $stmt_get_old->get_result();
            $old_image = $result->fetch_assoc()['image'];
            $stmt_get_old->close();

            if ($old_image && file_exists($uploadDir . DIRECTORY_SEPARATOR . $old_image)) {
                unlink($uploadDir . DIRECTORY_SEPARATOR . $old_image);
            }
        }
    }
}

try {
    if ($profile_image !== null && isset($hashed_password)) {
        $sql = "UPDATE users SET first_name=?, last_name=?, age=?, image=?, password=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssi', $first_name, $last_name, $age, $profile_image, $hashed_password, $user_id);
    } elseif ($profile_image !== null) {
        $sql = "UPDATE users SET first_name=?, last_name=?, age=?, image=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssi', $first_name, $last_name, $age, $profile_image, $user_id);
    } elseif (isset($hashed_password)) {
        $sql = "UPDATE users SET first_name=?, last_name=?, age=?, password=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssi', $first_name, $last_name, $age, $hashed_password, $user_id);
    } else {
        $sql = "UPDATE users SET first_name=?, last_name=?, age=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $first_name, $last_name, $age, $user_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update profile. Please try again.']);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
