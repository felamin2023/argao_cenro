<?php
// backend/admins/seedlingsRequest/list.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../connection.php'; // -> $pdo
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection error']);
    exit;
}

/* Guard: admin + seedling department */
if (
    empty($_SESSION['user_id']) || empty($_SESSION['role']) ||
    strtolower((string)$_SESSION['role']) !== 'admin'
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}
try {
    $st = $pdo->prepare("
        SELECT department FROM public.users WHERE user_id = :id LIMIT 1
    ");
    $st->execute([':id' => (string)$_SESSION['user_id']]);
    $dept = strtolower((string)($st->fetchColumn() ?? ''));
    if ($dept !== 'seedling') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Auth check failed']);
    exit;
}

/* Input */
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

/* Query: approvals (seedling) + client name */
try {
    $sql = "
        SELECT
            a.approval_id::text,
            c.first_name,
            c.last_name,
            a.request_type,
            a.submitted_at,
            a.approval_status
        FROM public.approval a
        JOIN public.client c ON c.client_id = a.client_id
        WHERE a.request_type = 'seedling'
    ";

    $params = [];
    if ($q !== '') {
        $sql .= " AND (
            a.approval_id::text ILIKE :q
            OR c.first_name ILIKE :q
            OR c.last_name ILIKE :q
            OR a.approval_status ILIKE :q
        )";
        $params[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY a.submitted_at DESC NULLS LAST, a.approval_id DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    error_log('[seedlingsRequest/list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed']);
}
