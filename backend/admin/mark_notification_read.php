<?php
// /denr/superadmin/backend/admin/mark_notification_read.php
declare(strict_types=1);
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once __DIR__ . '/../connection.php'; // exposes $pdo (PDO -> Supabase Postgres)

$admin_uuid = (string)$_SESSION['user_id'];

// Verify admin + CENRO
try {
    $st = $pdo->prepare("
        SELECT role, department
        FROM public.users
        WHERE user_id = :uid
        LIMIT 1
    ");
    $st->execute([':uid' => $admin_uuid]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    if (!$me || strtolower((string)$me['role']) !== 'admin' || strtolower((string)$me['department']) !== 'cenro') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit();
    }
} catch (Throwable $e) {
    error_log('[MARK_READ AUTH] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Auth check failed']);
    exit();
}

$id       = isset($_POST['id']) ? (string)$_POST['id'] : null;   // keep as string (works for uuid or bigint)
$mark_all = isset($_POST['mark_all']) ? (string)$_POST['mark_all'] : null;

try {
    if ($id !== null && $id !== '') {
        // Try boolean column first
        try {
            $st = $pdo->prepare("
                UPDATE public.profile_update_requests
                   SET is_read = TRUE
                 WHERE id = :id AND is_read IS DISTINCT FROM TRUE
            ");
            $st->execute([':id' => $id]);
            $affected = $st->rowCount();
        } catch (Throwable $e) {
            // Fallback if column is integer 0/1
            $st = $pdo->prepare("
                UPDATE public.profile_update_requests
                   SET is_read = 1
                 WHERE id = :id AND (is_read IS NULL OR is_read = 0)
            ");
            $st->execute([':id' => $id]);
            $affected = $st->rowCount();
        }

        echo json_encode(['success' => true, 'updated' => $affected, 'id' => $id]);
        exit();
    }

    if ($mark_all !== null) {
        // Try boolean column first
        try {
            $st = $pdo->prepare("
                UPDATE public.profile_update_requests
                   SET is_read = TRUE
                 WHERE is_read IS DISTINCT FROM TRUE
            ");
            $st->execute();
            $affected = $st->rowCount();
        } catch (Throwable $e) {
            // Fallback if column is integer 0/1
            $st = $pdo->prepare("
                UPDATE public.profile_update_requests
                   SET is_read = 1
                 WHERE is_read IS NULL OR is_read = 0
            ");
            $st->execute();
            $affected = $st->rowCount();
        }

        echo json_encode(['success' => true, 'updated' => $affected]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'No action']);
} catch (Throwable $e) {
    error_log('[MARK_READ FAIL] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
