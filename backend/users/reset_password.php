<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

/* ─────────────────────────────────────────────────────────────────────────────
   Autoload
   ───────────────────────────────────────────────────────────────────────────── */
try {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Autoload failed']);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Load .env from backend/
   ───────────────────────────────────────────────────────────────────────────── */
$ENV_ROOT = dirname(__DIR__); // -> backend/
try {
    if (class_exists(Dotenv\Dotenv::class) && is_file($ENV_ROOT . '/.env')) {
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[USER-RESET] Dotenv load error: ' . $e->getMessage());
}

/* Minimal fallback */
if (!getenv('SUPABASE_URL') && is_readable($ENV_ROOT . '/.env')) {
    foreach (file($ENV_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if ($k !== '') {
            if (getenv($k) === false) putenv("$k=$v");
            $_ENV[$k]    = $_ENV[$k]    ?? $v;
            $_SERVER[$k] = $_SERVER[$k] ?? $v;
        }
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   Helpers
   ───────────────────────────────────────────────────────────────────────────── */
function debug_log($msg)
{
    error_log('[USER-RESET] ' . $msg);
}

function json_out($ok, $err = '', $extra = [])
{
    $out = ['success' => (bool)$ok];
    if ($err !== '') $out['error'] = $err;
    echo json_encode($out + $extra);
    exit;
}

function env_required(string $k): string
{
    $v = getenv($k);
    if ($v === false || $v === null || $v === '') {
        $v = $_ENV[$k] ?? $_SERVER[$k] ?? null;
    }
    if (!$v) json_out(false, "Missing required env: {$k}");
    return (string)$v;
}

function is_uuid_v4(string $s): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s);
}

/* ─────────────────────────────────────────────────────────────────────────────
   Includes
   ───────────────────────────────────────────────────────────────────────────── */
require_once dirname(__DIR__) . '/connection.php';      // must expose $pdo (PDO)
require_once dirname(__DIR__) . '/admin/send_otp.php';  // must expose sendOTP($email, $otp)

/* ─────────────────────────────────────────────────────────────────────────────
   Constants
   ───────────────────────────────────────────────────────────────────────────── */
const APP_TABLE = 'public.users';

/* ─────────────────────────────────────────────────────────────────────────────
   Supabase Auth Admin API (service_role)
   ───────────────────────────────────────────────────────────────────────────── */
function supabase_admin_update_password(string $user_id, string $password): array
{
    $base = rtrim(env_required('SUPABASE_URL'), '/');
    $srv  = env_required('SUPABASE_SERVICE_ROLE_KEY');

    $url  = $base . '/auth/v1/admin/users/' . urlencode($user_id);
    $body = [
        'password' => $password,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $srv,
            'apikey: ' . $srv,
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return [0, null, ['error' => $err ?: 'cURL error']];

    $json = json_decode($resp, true);
    $user = null;
    if (is_array($json)) $user = $json['user'] ?? $json;
    return [$code, $user, $json];
}

/* ─────────────────────────────────────────────────────────────────────────────
   Actions
   ───────────────────────────────────────────────────────────────────────────── */
try {
    $action = $_POST['action'] ?? '';

    /* send_otp - send OTP to email for password reset */
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(false, 'Invalid email address.');
        }

        // Check if email exists in app table and verify role is "User"
        $stmt = $pdo->prepare("select user_id, role from " . APP_TABLE . " where lower(email)=lower(:e) limit 1");
        $stmt->execute([':e' => $email]);
        $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Email not in database
        if (!$user_row) {
            json_out(false, 'This email is not registered.');
        }

        // Email exists but role is not "User"
        if (strtolower((string)$user_row['role']) !== 'user') {
            json_out(false, 'This email is not registered.');
        }

        // Generate OTP
        $otp = random_int(100000, 999999);
        $_SESSION['reset_otp']         = $otp;
        $_SESSION['reset_email']       = $email;
        $_SESSION['reset_user_id']     = $user_row['user_id'];
        $_SESSION['reset_otp_expires'] = time() + 300; // 5 minutes

        // Send OTP email
        if (!sendOTP($email, $otp)) {
            json_out(false, 'Failed to send OTP email. Please try again later.');
        }

        // DEV ONLY: expose OTP in response for testing (remove in production)
        json_out(true, '', ['otp' => $otp, 'message' => 'OTP sent to email.']);
    }

    /* verify_otp - verify OTP for password reset */
    if ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_otp_expires'])) {
            json_out(false, 'No OTP in session.');
        }
        if (time() > (int)$_SESSION['reset_otp_expires']) {
            json_out(false, 'OTP expired. Please resend.');
        }
        if ($otp !== (string)$_SESSION['reset_otp']) {
            json_out(false, 'Incorrect OTP.');
        }
        $_SESSION['reset_verified'] = true;
        json_out(true);
    }

    /* reset_password - update password after OTP verification */
    if ($action === 'reset_password') {
        $password         = (string)($_POST['password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        // Check if OTP was verified
        if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']) {
            json_out(false, 'Please verify your email first.');
        }

        // Validate passwords
        if (strlen($password) < 8) {
            json_out(false, 'Password must be at least 8 characters.');
        }
        if ($password !== $confirm_password) {
            json_out(false, 'Passwords do not match.');
        }

        $email   = $_SESSION['reset_email'] ?? '';
        $user_id = $_SESSION['reset_user_id'] ?? '';

        if (!$email || !$user_id || !is_uuid_v4($user_id)) {
            json_out(false, 'Session expired. Please try again.');
        }

        // Update Auth password via Admin API
        [$code, $user, $raw] = supabase_admin_update_password($user_id, $password);

        if ($code < 200 || $code >= 300) {
            debug_log("Admin update password failed: HTTP {$code} " . json_encode($raw));
            json_out(false, 'Password reset failed.');
        }

        // Update app table password hash
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("update " . APP_TABLE . " set password = :password where user_id = :user_id");
            $stmt->execute([':password' => $hash, ':user_id' => $user_id]);
            $pdo->commit();

            // Clear reset session
            unset($_SESSION['reset_verified'], $_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_expires']);

            json_out(true, '', ['message' => 'Password reset successful. Please login with your new password.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            debug_log("Password update failed: " . $e->getMessage());
            json_out(false, 'Password reset failed.');
        }
    }

    json_out(false, 'Invalid request.');
} catch (Throwable $e) {
    debug_log("Fatal: " . $e->getMessage());
    json_out(false, 'System error.');
}
