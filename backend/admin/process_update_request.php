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
        // Activity log: record deletion of the request by CENRO admin
        try {
            $ilog = $pdo->prepare('INSERT INTO public.admin_activity_logs (admin_user_id, admin_department, action, details) VALUES (:uid, :dept, :action, :details)');
            $ilog->execute([
                ':uid' => $admin_uuid,
                ':dept' => $me['department'] ?? 'cenro',
                ':action' => 'delete_profile_request',
                ':details' => sprintf('Deleted profile update request id=%d', $request_id),
            ]);
        } catch (Throwable $le) {
            error_log('[PROCESS/ACTIVITY LOG DELETE] ' . $le->getMessage());
        }
        $pdo->commit();
        header('Location: /denr/superadmin/supernotif.php');
        exit();
    }

    if ($action === 'reject') {
        // Update request and RETURN key fields to create a notification
        $rej = $pdo->prepare("
                    UPDATE public.profile_update_requests
                    SET status = 'rejected',
                        reason_for_rejection = :reason,
                        reviewed_at = now(),
                        reviewed_by = :reviewer
                    WHERE id = :id
                    RETURNING reqpro_id, user_id, first_name, department
                ");
        $rej->execute([
            ':reason'   => $reason,
            ':reviewer' => $admin_uuid,
            ':id'       => $request_id
        ]);
        $rejRow = $rej->fetch(PDO::FETCH_ASSOC) ?: null;

        // Insert notification to the requester about rejection
        if ($rejRow) {
            try {
                $notifMsg = sprintf('%s, your profile request update is rejected.', trim((string)$rejRow['first_name']));
                $nst = $pdo->prepare('INSERT INTO public.notifications (message, "from", "to", reqpro_id) VALUES (:message, :from, :to, :reqpro_id)');
                $deptTo = trim((string)($rejRow['department'] ?? '')) ?: 'Unknown';
                $nst->execute([
                    ':message' => $notifMsg,
                    ':from' => "Cenro",
                    ':to' => $deptTo,
                    ':reqpro_id' => $rejRow['reqpro_id']
                ]);
                // Activity log: record rejection
                try {
                    $ilog = $pdo->prepare('INSERT INTO public.admin_activity_logs (admin_user_id, admin_department, action, details) VALUES (:uid, :dept, :action, :details)');
                    $ilog->execute([
                        ':uid' => $admin_uuid,
                        ':dept' => $me['department'] ?? 'cenro',
                        ':action' => 'reject_profile_request',
                        ':details' => sprintf('Rejected profile update req %s. Reason: %s', substr((string)($rejRow['reqpro_id'] ?? ''), 0, 8), $reason ?? ''),
                    ]);
                } catch (Throwable $le) {
                    error_log('[PROCESS/ACTIVITY LOG REJECT] ' . $le->getMessage());
                }
            } catch (Throwable $ne) {
                error_log('[PROCESS/NOTIF REJECT] ' . $ne->getMessage());
                // do not fail the main flow
            }
        }

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
        RETURNING reqpro_id, user_id, image, first_name, last_name, age, email, department, phone, password
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

    // Notify requester about approval
    try {
        $notifMsg = sprintf('%s, your profile request update is approved.', trim((string)($req['first_name'] ?? '')));
        $nst = $pdo->prepare('INSERT INTO public.notifications (message, "from", "to", reqpro_id) VALUES (:message, :from, :to, :reqpro_id)');
        $deptTo = trim((string)($req['department'] ?? '')) ?: 'Unknown';
        $nst->execute([
            ':message' => $notifMsg,
            ':from' => 'Cenro',
            ':to' => $deptTo,
            ':reqpro_id' => $req['reqpro_id'] ?? null,
        ]);
        // Activity log: record approval
        try {
            $ilog = $pdo->prepare('INSERT INTO public.admin_activity_logs (admin_user_id, admin_department, action, details) VALUES (:uid, :dept, :action, :details)');
            $ilog->execute([
                ':uid' => $admin_uuid,
                ':dept' => $me['department'] ?? 'cenro',
                ':action' => 'approve_profile_request',
                ':details' => sprintf('Approved profile update req %s', substr((string)($req['reqpro_id'] ?? ''), 0, 8)),
            ]);
        } catch (Throwable $le) {
            error_log('[PROCESS/ACTIVITY LOG APPROVE] ' . $le->getMessage());
        }
    } catch (Throwable $ne) {
        error_log('[PROCESS/NOTIF APPROVE] ' . $ne->getMessage());
        // ignore notification failure
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
