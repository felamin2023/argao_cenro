<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../backend/connection.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID parameter is missing']);
    exit;
}

$id = $_GET['id'];
$query = "SELECT * FROM incident_report WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $report = $result->fetch_assoc();
    echo json_encode($report);
} else {
    echo json_encode(['error' => 'Report not found']);
}
