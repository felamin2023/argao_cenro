<?php
session_start();
include_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (isset($_POST['mark_all'])) {
    // Mark all as read for all notifications
    $conn->query("UPDATE profile_update_requests SET is_read = 1 WHERE is_read = 0");
    echo json_encode(['success' => true]);
    exit();
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE profile_update_requests SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false]);
