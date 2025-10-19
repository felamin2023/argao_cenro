<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../backend/connection.php'; // exposes $pdo (PDO)
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

$user_id = (string)$_SESSION['user_id'];

$first_name       = trim($_POST['first_name'] ?? '');
$last_name        = trim($_POST['last_name'] ?? '');
$age              = trim($_POST['age'] ?? '');
$password         = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

$errors = [];
if (mb_strlen($first_name) < 2) $errors['first_name'] = 'First name too short.';
if (mb_strlen($last_name)  < 2) $errors['last_name']  = 'Last name too short.';
if ($age !== '' && (!ctype_digit($age) || (int)$age < 0)) $errors['age'] = 'Invalid age.';

if ($password !== '' || $confirm_password !== '') {
    if ($password !== $confirm_password) {
        $errors['password'] = 'Passwords do not match.';
    } elseif (mb_strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

$hashed_password = null;

// If changing password, ensure it differs from current
if ($password !== '') {
    try {
        $st = $pdo->prepare("SELECT password FROM public.users WHERE user_id = :id LIMIT 1");
        $st->execute([':id' => $user_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit();
        }

        if (!empty($row['password']) && password_verify($password, (string)$row['password'])) {
            echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    } catch (Throwable $e) {
        error_log('[UPDATE PROFILE password check] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Password check failed.']);
        exit();
    }
}

// Handle profile image upload (optional)
$profile_image = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['profile_image']['tmp_name'];
    $fileName = basename((string)$_FILES['profile_image']['name']);
    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed  = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed, true)) {
        $newName  = 'profile_' . $user_id . '_' . time() . '.' . $ext;

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

            // Delete old image if present
            try {
                $st = $pdo->prepare("SELECT image FROM public.users WHERE user_id = :id LIMIT 1");
                $st->execute([':id' => $user_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                $old_image = $row['image'] ?? null;

                if ($old_image) {
                    $oldPath = $uploadDir . DIRECTORY_SEPARATOR . $old_image;
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            } catch (Throwable $e) {
                error_log('[UPDATE PROFILE old image fetch] ' . $e->getMessage());
            }
        }
    }
}

// Build dynamic UPDATE
$fields = [
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'age'        => $age
];

if ($profile_image !== null) {
    $fields['image'] = $profile_image;
}
if ($hashed_password !== null) {
    $fields['password'] = $hashed_password;
}

$setParts = [];
$params   = [':id' => $user_id];
foreach ($fields as $col => $val) {
    $setParts[] = "$col = :$col";
    $params[":$col"] = $val;
}

if (empty($setParts)) {
    echo json_encode(['success' => true]); // nothing to update
    exit();
}

$sql = "UPDATE public.users SET " . implode(', ', $setParts) . " WHERE user_id = :id";

try {
    $st = $pdo->prepare($sql);
    $ok = $st->execute($params);

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update profile.']);
    }
} catch (Throwable $e) {
    error_log('[UPDATE PROFILE execute] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update profile. Please try again.']);
}
