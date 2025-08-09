<?php
session_start();
require_once __DIR__ . '/../../backend/connection.php';

header('Content-Type: application/json');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Remove the isset($_POST['submit']) check since we're using fetch()
$user_id = $_SESSION['user_id'];

// Collect form data
$location = $_POST['location'] ?? '';
$datetime = $_POST['datetime'] ?? '';
$category = $_POST['categories'] ?? '';
$description = $_POST['description'] ?? '';

// Validate required fields
if (empty($location) || empty($datetime) || empty($category) || empty($description) || empty($_FILES['photo']['name'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Handle file upload
$upload_dir = __DIR__ . '/../../../../upload/users/reportincidents/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$photo_name = $_FILES['photo']['name'];
$photo_tmp = $_FILES['photo']['tmp_name'];
$photo_new_name = time() . "_" . basename($photo_name);
$photo_path = $upload_dir . $photo_new_name;

if (move_uploaded_file($photo_tmp, $photo_path)) {
    // Insert into incident_reports table
    $stmt = $conn->prepare("INSERT INTO incident_reports 
        (user_id, location, photo, date_time, category, description, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isssss", $user_id, $location, $photo_new_name, $datetime, $category, $description);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Incident report submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
}
