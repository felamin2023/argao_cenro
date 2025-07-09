<?php
// backend/admin/add_admin.php
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
$fields = ['first_name', 'last_name', 'age', 'email', 'department', 'password', 'phone', 'status'];
foreach ($fields as $f) {
    if (!isset($_POST[$f]) || $_POST[$f] === '') {
        echo json_encode(['success' => false, 'error' => 'Missing field: ' . $f]);
        exit();
    }
}
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$age = $_POST['age'];
$email = $_POST['email'];
$department = $_POST['department'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$phone = $_POST['phone'];
$status = $_POST['status'];
$role = 'Admin';
include_once __DIR__ . '/../connection.php';
// Check if email already exists
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Email already exists.']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();
$stmt = $conn->prepare('INSERT INTO users (first_name, last_name, age, email, department, password, phone, status, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssissssss', $first_name, $last_name, $age, $email, $department, $password, $phone, $status, $role);
$success = $stmt->execute();
$stmt->close();
$conn->close();
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Add failed']);
}
