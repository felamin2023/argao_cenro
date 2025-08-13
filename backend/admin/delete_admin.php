<?php

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
$stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "Admin"');
$stmt->bind_param('i', $id);
$success = $stmt->execute();
$stmt->close();
$conn->close();
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}
