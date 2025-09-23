<?php
session_start();
include_once __DIR__ . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? 0;

    if (isset($_FILES['profile_image']) && $user_id > 0) {
        $upload_dir = __DIR__ . '/../upload/use_profiles/';


        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }


        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;


        $check = getimagesize($_FILES['profile_image']['tmp_name']);
        if ($check !== false) {

            if ($_FILES['profile_image']['size'] > 2000000) {
                echo json_encode(['success' => false, 'error' => 'File is too large (max 2MB)']);
                exit();
            }


            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_ext), $allowed_extensions)) {
                echo json_encode(['success' => false, 'error' => 'Only JPG, JPEG, PNG & GIF files are allowed']);
                exit();
            }


            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {

                $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->bind_param("si", $file_name, $user_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'filename' => $file_name]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Database update failed']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'File upload failed']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'File is not an image']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
}
