<?php
// backend/admin/update_admin.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}
$fields = ['id', 'first_name', 'last_name', 'age', 'email', 'department', 'phone', 'status'];
foreach ($fields as $f) {
    if (!isset($_POST[$f])) {
        echo json_encode(['success' => false, 'error' => 'Missing field: ' . $f]);
        exit();
    }
}
$id = intval($_POST['id']);
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$age = $_POST['age'];
$email = $_POST['email'];
$department = $_POST['department'];
$phone = $_POST['phone'];
$status = $_POST['status'];
include_once __DIR__ . '/../connection.php';
$stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, age=?, email=?, department=?, phone=?, status=? WHERE id=? AND role="Admin"');
$stmt->bind_param('sssssssi', $first_name, $last_name, $age, $email, $department, $phone, $status, $id);
$success = $stmt->execute();
$stmt->close();
$conn->close();
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Update failed']);
}
