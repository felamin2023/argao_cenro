<?php
// backend/admins/seedlingsRequest/update_status.php
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
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$approval_id      = isset($data['approval_id']) ? (string)$data['approval_id'] : '';
$status           = isset($data['status']) ? strtolower(trim((string)$data['status'])) : '';
$rejection_reason = array_key_exists('rejection_reason', $data) ? trim((string)$data['rejection_reason']) : null;

if ($approval_id === '' || $status === '') {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}
$allowed = ['pending', 'approved', 'rejected'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    if ($status === 'rejected') {
        $sql = "
            UPDATE public.approval
               SET approval_status = 'rejected',
                   rejection_reason = :reason,
                   approved_by = :by,
                   approved_at = now()
             WHERE approval_id::text = :id
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':reason' => $rejection_reason,
            ':by'     => (string)$_SESSION['user_id'],
            ':id'     => $approval_id,
        ]);
    } elseif ($status === 'approved') {
        $sql = "
            UPDATE public.approval
               SET approval_status = 'approved',
                   rejection_reason = NULL,
                   approved_by = :by,
                   approved_at = now()
             WHERE approval_id::text = :id
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':by' => (string)$_SESSION['user_id'],
            ':id' => $approval_id,
        ]);
    } else {
        $sql = "
            UPDATE public.approval
               SET approval_status = 'pending',
                   rejection_reason = NULL,
                   approved_by = NULL,
                   approved_at = NULL
             WHERE approval_id::text = :id
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $approval_id]);
    }

    echo json_encode(['success' => $st->rowCount() > 0]);
} catch (Throwable $e) {
    error_log('[seedlingsRequest/update_status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed']);
}
