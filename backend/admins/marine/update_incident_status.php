<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../backend/connection.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['status']) || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$id = $data['id'];
$status = $data['status'];
$user_id = $data['user_id'];

try {
    $query = "UPDATE incident_report SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $status, $user_id, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
