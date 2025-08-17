<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../backend/connection.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$id = $data['id'];
$user_id = $data['user_id'];

try {
    // First, get photos to delete them from server
    $query = "SELECT photos FROM incident_report WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        $photos = json_decode($report['photos'], true);

        // Delete photos from server
        foreach ($photos as $photo) {
            $filepath = __DIR__ . '/../uploads/' . $photo;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }

    // Now delete the record
    $query = "DELETE FROM incident_report WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
