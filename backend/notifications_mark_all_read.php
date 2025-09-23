<?php
declare(strict_types=1);
header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/notifications_repo.php';

try {
    $affected = mark_all_read($pdo, (string)$_SESSION['user_id']);
    echo json_encode(['ok' => true, 'affected' => $affected]);
} catch (Throwable $e) {
    error_log('[notifications_mark_all_read] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
