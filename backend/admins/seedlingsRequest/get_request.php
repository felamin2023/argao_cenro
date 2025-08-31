<?php
// backend/admins/seedlingsRequest/get_request.php
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
$approval_id = isset($_GET['approval_id']) ? (string)$_GET['approval_id'] : '';
if ($approval_id === '') {
    echo json_encode(['success' => false, 'error' => 'Missing approval_id']);
    exit;
}

try {
    // Main approval + client + requirements (application_form URL)
    $sql = "
        SELECT
            a.approval_id::text,
            a.client_id,
            a.requirement_id,
            a.request_type,
            a.submitted_at,
            a.approval_status,
            a.rejection_reason,
            a.seedl_req_id,
            c.first_name,
            c.last_name,
            r.application_form
        FROM public.approval a
        JOIN public.client c ON c.client_id = a.client_id
        LEFT JOIN public.requirements r ON r.requirement_id = a.requirement_id
        WHERE a.approval_id::text = :id AND a.request_type = 'seedling'
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $approval_id]);
    $a = $st->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
        echo json_encode(['success' => false, 'error' => 'Record not found']);
        exit;
    }

    // Seedling name + qty from seedling_requests referenced by this approval
    $seedling = null;
    if (!empty($a['seedl_req_id'])) {
        $st2 = $pdo->prepare("
            SELECT sr.seedl_req_id,
                   sr.seedlings_id,
                   sr.quantity,
                   s.seedling_name
            FROM public.seedling_requests sr
            LEFT JOIN public.seedlings s ON s.seedlings_id = sr.seedlings_id
            WHERE sr.seedl_req_id = :sid
            LIMIT 1
        ");
        $st2->execute([':sid' => $a['seedl_req_id']]);
        $seedling = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success'  => true,
        'approval' => [
            'approval_id'      => $a['approval_id'],
            'request_type'     => $a['request_type'],
            'submitted_at'     => $a['submitted_at'],
            'approval_status'  => $a['approval_status'],
            'rejection_reason' => $a['rejection_reason'],
            'first_name'       => $a['first_name'],
            'last_name'        => $a['last_name'],
            'application_form' => $a['application_form'], // public URL you saved
        ],
        'seedling' => $seedling,
    ]);
} catch (Throwable $e) {
    error_log('[seedlingsRequest/get_request] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed']);
}
