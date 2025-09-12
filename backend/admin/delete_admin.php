<?php

// session_start();
// header('Content-Type: application/json');
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit();
// }
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(['success' => false, 'error' => 'Invalid request']);
//     exit();
// }
// if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
//     echo json_encode(['success' => false, 'error' => 'Invalid ID']);
//     exit();
// }
// $id = intval($_POST['id']);
// include_once __DIR__ . '/../connection.php';
// $stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "Admin"');
// $stmt->bind_param('i', $id);
// $success = $stmt->execute();
// $stmt->close();
// $conn->close();
// if ($success) {
//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false, 'error' => 'Delete failed']);
// }

declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

session_start();
require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

try {
    $st = $pdo->prepare("DELETE FROM public.users WHERE id = :id");
    $st->execute([':id' => $id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[DELETE ADMIN] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
