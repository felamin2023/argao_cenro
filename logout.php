<?php
// logout.php
session_start();
// mark user inactive if logged in
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/backend/connection.php';
        $pdo->prepare('UPDATE public.users SET is_active = false WHERE user_id = :uid')->execute([':uid' => (string)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        error_log('[LOGOUT] failed to set is_active=false: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();
header('Location: superlogin.php');
exit();
