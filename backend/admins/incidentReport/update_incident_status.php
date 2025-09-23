<?php
// backend/admins/marine/update_incident_status.php
declare(strict_types=1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../backend/connection.php'; // must set $pdo (PDO)
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$id        = isset($data['id']) ? (int)$data['id'] : 0;
$status    = isset($data['status']) ? strtolower(trim((string)$data['status'])) : '';
$user_id   = isset($data['user_id']) ? trim((string)$data['user_id']) : '';
$reason    = array_key_exists('rejection_reason', $data) ? trim((string)$data['rejection_reason']) : null;

if ($id <= 0 || $status === '' || $user_id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$allowed = ['pending', 'approved', 'rejected', 'resolved'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    if ($status === 'rejected') {
        $sql = '
            UPDATE public.incident_report
               SET status = :status,
                   approved_by = :approved_by,
                   approved_at = NOW(),
                   rejection_reason = :reason
             WHERE id = :id
        ';
        $st = $pdo->prepare($sql);
        $st->execute([
            ':status'      => $status,
            ':approved_by' => $user_id,
            ':reason'      => $reason,
            ':id'          => $id,
        ]);
    } else {
        $sql = '
            UPDATE public.incident_report
               SET status = :status,
                   approved_by = :approved_by,
                   approved_at = NOW(),
                   rejection_reason = NULL
             WHERE id = :id
        ';
        $st = $pdo->prepare($sql);
        $st->execute([
            ':status'      => $status,
            ':approved_by' => $user_id,
            ':id'          => $id,
        ]);
    }

    $changed = $st->rowCount();
    echo json_encode([
        'success' => $changed > 0,
        'message' => $changed > 0 ? 'Updated' : 'No changes made',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
