<?php
// backend/admin/get_admin.php
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
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit();
}
$id = intval($_POST['id']);
include_once __DIR__ . '/../connection.php';
$stmt = $conn->prepare('SELECT first_name, last_name, age, email, department, phone, status FROM users WHERE id = ? AND role = "Admin"');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($first_name, $last_name, $age, $email, $department, $phone, $status);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => true,
        'data' => [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'age' => $age,
            'email' => $email,
            'department' => $department,
            'phone' => $phone,
            'status' => $status
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'User not found']);
}
$stmt->close();
$conn->close();
