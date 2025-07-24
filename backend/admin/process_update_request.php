<?php
session_start();
include_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../superlogin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reviewed_by = isset($_POST['reviewed_by']) ? $_POST['reviewed_by'] : '';
$reason = isset($_POST['reason_for_rejection']) ? $_POST['reason_for_rejection'] : null;

if ($request_id <= 0 || !$action) {
    header('Location: ../supernotif.php');
    exit();
}

$reviewed_at = date('Y-m-d H:i:s');

if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE profile_update_requests SET status = 'approved', reviewed_at = ?, reviewed_by = ? WHERE id = ?");
    $stmt->bind_param("ssi", $reviewed_at, $reviewed_by, $request_id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'reject') {
    $stmt = $conn->prepare("UPDATE profile_update_requests SET status = 'rejected', reason_for_rejection = ?, reviewed_at = ?, reviewed_by = ? WHERE id = ?");
    $stmt->bind_param("sssi", $reason, $reviewed_at, $reviewed_by, $request_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header('Location: ../../supernotif.php');
exit();
