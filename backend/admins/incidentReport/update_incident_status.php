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
    $pdo->beginTransaction();

    $fetch = $pdo->prepare('
        SELECT status, incident_id, user_id, who, rejection_reason
          FROM public.incident_report
         WHERE id = :id
         FOR UPDATE
    ');
    $fetch->execute([':id' => $id]);
    $incident = $fetch->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit;
    }

    $currentStatus = strtolower((string)($incident['status'] ?? ''));
    if ($currentStatus === 'resolved') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Incident already resolved']);
        exit;
    }

    if ($status === 'rejected') {
        if ($reason === null || trim($reason) === '') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
            exit;
        }
        $rejectionReason = $reason;
    } else {
        $rejectionReason = null;
    }

    $update = $pdo->prepare('
        UPDATE public.incident_report
           SET status = :status,
               approved_by = :approved_by,
               approved_at = NOW(),
               rejection_reason = :reason
         WHERE id = :id
    ');
    $update->execute([
        ':status'      => $status,
        ':approved_by' => $user_id,
        ':reason'      => $rejectionReason,
        ':id'          => $id,
    ]);

    $reporterId = trim((string)($incident['user_id'] ?? ''));
    $firstName = '';
    if ($reporterId !== '') {
        $first = $pdo->prepare("
            SELECT COALESCE(NULLIF(btrim(first_name), ''), 'User')
              FROM public.users
             WHERE user_id = :id
             LIMIT 1
        ");
        $first->execute([':id' => $reporterId]);
        $firstName = (string)$first->fetchColumn();
    }
    if ($firstName === '' && !empty($incident['who'])) {
        $parts = preg_split('/\s+/', trim((string)$incident['who']));
        $firstName = $parts[0] ?? '';
    }
    if ($firstName === '') {
        $firstName = 'User';
    }

    $department = '';
    $deptStmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(btrim(department), ''), 'Wildlife')
          FROM public.users
         WHERE user_id = :id
         LIMIT 1
    ");
    $deptStmt->execute([':id' => $user_id]);
    $department = trim((string)$deptStmt->fetchColumn());
    if ($department === '') {
        $department = 'Wildlife';
    }

    $statusLabel = strtolower($status);
    $notifMessage = sprintf('%s, admin %s your incident report', $firstName, $statusLabel);

    // Notifications should show they came from the Wildlife department
    $notifStmt = $pdo->prepare('
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, "from", "to")
        VALUES (NULL, :incident_id, :message, false, :from, :to)
    ');
    $notifStmt->execute([
        ':incident_id' => $incident['incident_id'] ?? null,
        ':message'     => $notifMessage,
        ':from'        => 'wildlife',
        ':to'          => $reporterId !== '' ? $reporterId : null,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated',
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
