<?php
declare(strict_types=1);
header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$notifId = isset($_POST['notif_id']) ? trim((string)$_POST['notif_id']) : '';
if ($notifId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing notif_id']);
    exit;
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/notifications_repo.php';

try {
    $ok = mark_read($pdo, (string)$_SESSION['user_id'], $notifId);
    echo json_encode(['ok' => $ok]);
} catch (Throwable $e) {
    error_log('[notifications_mark_read] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
