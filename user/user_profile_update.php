<?php
// user_profile_update.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Include your connection file
if (is_file(__DIR__ . '/../backend/connection.php')) {
    include_once __DIR__ . '/../backend/connection.php';
}

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($age) || empty($email) || empty($phone)) {
        throw new Exception("All required fields must be filled");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate age
    if ($age < 1 || $age > 150) {
        throw new Exception("Age must be between 1 and 150");
    }

    // Handle profile image upload
    $image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../upload/user_profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;

        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_image']['type'];
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid image format. Allowed: JPEG, PNG, GIF, WebP");
        }

        // Check file size (5MB max)
        if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
            throw new Exception("Image size too large. Maximum 5MB allowed.");
        }

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
            $image_path = $filename;
        }
    }

    // Prepare the update query
    $update_fields = [];
    $params = [];

    // Add basic fields
    $update_fields[] = 'first_name = ?';
    $params[] = $first_name;
    
    $update_fields[] = 'last_name = ?';
    $params[] = $last_name;
    
    $update_fields[] = 'age = ?';
    $params[] = $age;
    
    $update_fields[] = 'email = ?';
    $params[] = $email;
    
    $update_fields[] = 'phone = ?';
    $params[] = $phone;

    // Add password if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields[] = 'password = ?';
        $params[] = $hashed_password;
    }

    // Add image if uploaded
    if ($image_path) {
        $update_fields[] = 'image = ?';
        $params[] = $image_path;
    }

    // Determine which column to use for WHERE clause
    $isUuid = is_string($user_id) && preg_match(
        '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
        $user_id
    );

    if ($isUuid) {
        $where_column = 'user_id';
    } else {
        $where_column = 'id';
        $user_id = (int)$user_id;
    }

    $params[] = $user_id;

    // Build the final query
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE {$where_column} = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        throw new Exception("Failed to update profile in database");
    }

} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>