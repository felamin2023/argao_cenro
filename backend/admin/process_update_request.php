<?php
// backend/admin/process_update_request.php (PDO / Supabase Postgres)
declare(strict_types=1);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /denr/superadmin/supernotif.php');
    exit();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /denr/superadmin/superlogin.php');
    exit();
}

require_once __DIR__ . '/../connection.php'; // exposes $pdo

$admin_uuid = (string)$_SESSION['user_id'];

// Verify admin & CENRO
try {
    $st = $pdo->prepare("SELECT department, role FROM public.users WHERE user_id = :uid LIMIT 1");
    $st->execute([':uid' => $admin_uuid]);
    $me = $st->fetch(PDO::FETCH_ASSOC);
    if (!$me || strtolower((string)$me['role']) !== 'admin' || strtolower((string)$me['department']) !== 'cenro') {
        header('Location: /denr/superadmin/superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[PROCESS/AUTH] ' . $e->getMessage());
    header('Location: /denr/superadmin/superlogin.php');
    exit();
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action     = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : '';
$reason     = isset($_POST['reason_for_rejection']) ? trim((string)$_POST['reason_for_rejection']) : null;

if ($request_id <= 0 || !in_array($action, ['approve', 'reject', 'delete'], true)) {
    header('Location: /denr/superadmin/supernotif.php');
    exit();
}

try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        // Delete the request (any status)
        $del = $pdo->prepare("DELETE FROM public.profile_update_requests WHERE id = :id");
        $del->execute([':id' => $request_id]);
        $pdo->commit();
        header('Location: /denr/superadmin/supernotif.php');
        exit();
    }

    if ($action === 'reject') {
        $rej = $pdo->prepare("
            UPDATE public.profile_update_requests
            SET status = 'rejected',
                reason_for_rejection = :reason,
                reviewed_at = now(),
                reviewed_by = :reviewer
            WHERE id = :id
        ");
        $rej->execute([
            ':reason'   => $reason,
            ':reviewer' => $admin_uuid,
            ':id'       => $request_id
        ]);
        $pdo->commit();
        header('Location: /denr/superadmin/supernotif.php');
        exit();
    }

    // APPROVE: mark request approved, then apply non-null fields to users(user_id)
    // Use UPDATE ... RETURNING to get the request data atomically
    $upd = $pdo->prepare("
        UPDATE public.profile_update_requests
        SET status = 'approved',
            reviewed_at = now(),
            reviewed_by = :reviewer
        WHERE id = :id
        RETURNING user_id, image, first_name, last_name, age, email, department, phone, password
    ");
    $upd->execute([':reviewer' => $admin_uuid, ':id' => $request_id]);
    $req = $upd->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        // nothing to update; approve changed 0 rows?
        $pdo->commit();
        header('Location: /denr/superadmin/supernotif.php');
        exit();
    }

    $user_uuid = (string)$req['user_id'];

    // Build dynamic update for users table (only overwrite with non-null values from request)
    $sets = [];
    $params = [':uid' => $user_uuid];

    $columns = ['image', 'first_name', 'last_name', 'age', 'email', 'department', 'phone', 'password'];
    foreach ($columns as $col) {
        if (array_key_exists($col, $req) && $req[$col] !== null && $req[$col] !== '') {
            $sets[] = $col . ' = :' . $col;
            // cast age to int
            if ($col === 'age') {
                $params[':age'] = (int)$req['age'];
            } else {
                $params[':' . $col] = $req[$col];
            }
        }
    }

    if (!empty($sets)) {
        $sql = "UPDATE public.users SET " . implode(', ', $sets) . " WHERE user_id = :uid";
        $doUser = $pdo->prepare($sql);
        $doUser->execute($params);
    }

    $pdo->commit();
    header('Location: /denr/superadmin/supernotif.php');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PROCESS/ERR] ' . $e->getMessage());
    header('Location: /denr/superadmin/supernotif.php');
    exit();
}
