<?php
// backend/admin/register.php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

/* ─────────────────────────────────────────────────────────────────────────────
   Composer autoload
   ───────────────────────────────────────────────────────────────────────────── */
try {
    // vendor/ is at project root (two levels up from /backend/admin)
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Autoload failed']);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Load .env from backend/ (single source of truth)
   ───────────────────────────────────────────────────────────────────────────── */
$ENV_ROOT = dirname(__DIR__); // -> backend/
try {
    if (class_exists(Dotenv\Dotenv::class) && is_file($ENV_ROOT . '/.env')) {
        // don't crash if missing; don't overwrite real server env
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[ADMIN-REG] Dotenv load error: ' . $e->getMessage());
}

/* Minimal fallback: populate getenv()/$_ENV/$_SERVER if host didn’t */
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
    error_log('[ADMIN-REG] ' . $msg);
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

/** Parse friendly message from a PostgreSQL 23505 unique violation. */
function duplicate_violation_message(PDOException $e): string
{
    $detail = '';
    if (property_exists($e, 'errorInfo') && is_array($e->errorInfo) && isset($e->errorInfo[2])) {
        $detail = strtolower((string)$e->errorInfo[2]);
    } else {
        $detail = strtolower($e->getMessage());
    }
    if (strpos($detail, '(email)') !== false || strpos($detail, 'email') !== false || strpos($detail, 'users_email') !== false) {
        return 'Email already exist';
    }
    if (strpos($detail, '(phone)') !== false || strpos($detail, 'phone') !== false || strpos($detail, 'users_phone') !== false) {
        return 'Phone number already exists.';
    }
    return 'Duplicate entry found. Please try again.';
}

function is_uuid_v4(string $s): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s);
}

/* ─────────────────────────────────────────────────────────────────────────────
   Includes
   ───────────────────────────────────────────────────────────────────────────── */
require_once dirname(__DIR__) . '/connection.php'; // must expose $pdo (PDO to Supabase Postgres)
require_once __DIR__ . '/send_otp.php';            // must expose sendOTP($email, $otp)

/* ─────────────────────────────────────────────────────────────────────────────
   Constants
   ───────────────────────────────────────────────────────────────────────────── */
const APP_TABLE = 'public.users';

/* ─────────────────────────────────────────────────────────────────────────────
   Duplicate checks
   ───────────────────────────────────────────────────────────────────────────── */
function auth_email_exists(PDO $pdo, string $email): bool
{
    try {
        $stmt = $pdo->prepare("select id from auth.users where lower(email)=lower(:e) limit 1");
        $stmt->execute([':e' => $email]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        debug_log("auth_email_exists() cannot query auth.users: " . $e->getMessage());
        return false; // let Admin API report conflicts if we can't check
    }
}

function app_email_or_phone_exists(PDO $pdo, string $email, string $phone): bool
{
    $stmt = $pdo->prepare("select user_id from " . APP_TABLE . " where lower(email)=lower(:e) or phone=:p limit 1");
    $stmt->execute([':e' => $email, ':p' => $phone]);
    return (bool)$stmt->fetchColumn();
}

/* Extra safety: confirm an auth user id exists server-side */
function auth_user_exists_by_id(PDO $pdo, string $user_id): bool
{
    try {
        $stmt = $pdo->prepare("select 1 from auth.users where id = :id limit 1");
        $stmt->execute([':id' => $user_id]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        debug_log("auth_user_exists_by_id() failed: " . $e->getMessage());
        return false;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   Supabase Auth Admin API (service_role)
   ───────────────────────────────────────────────────────────────────────────── */
function supabase_admin_create_user(string $email, string $password, array $metadata = []): array
{
    $base = rtrim(env_required('SUPABASE_URL'), '/');
    $srv  = env_required('SUPABASE_SERVICE_ROLE_KEY'); // server-only

    $url  = $base . '/auth/v1/admin/users';
    $body = [
        'email'         => $email,
        'password'      => $password,
        'email_confirm' => true,              // Auth email becomes "Verified" (Auth-side) by design
        'user_metadata' => $metadata,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
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
    // Accept either raw user object or { user: {...} }
    $user = null;
    if (is_array($json)) {
        $user = $json['user'] ?? $json;
    }
    return [$code, $user, $json];
}

function supabase_admin_delete_user(string $user_id): bool
{
    $base = rtrim(env_required('SUPABASE_URL'), '/');
    $srv  = env_required('SUPABASE_SERVICE_ROLE_KEY');

    $ch = curl_init($base . '/auth/v1/admin/users/' . urlencode($user_id));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $srv,
            'apikey: ' . $srv,
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Actions
   ───────────────────────────────────────────────────────────────────────────── */
try {
    $action = $_POST['action'] ?? '';

    // Diagnostic endpoint: checks presence (booleans only, no secrets)
    if ($action === 'diag_env') {
        json_out(true, '', [
            'SUPABASE_URL_present' => (bool) (getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? $_SERVER['SUPABASE_URL'] ?? null)),
            'SERVICE_ROLE_present' => (bool) (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ($_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] ?? null)),
        ]);
    }

    /* send_otp */
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(false, 'Invalid email address.');

        // Block immediately if email already exists
        if (auth_email_exists($pdo, $email)) json_out(false, 'Email already exist');

        $stmt = $pdo->prepare("select status, department from " . APP_TABLE . " where lower(email)=lower(:e) limit 1");
        $stmt->execute([':e' => $email]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strtolower((string)$row['status']) === 'pending') {
                json_out(false, 'Your registration is pending approval.', [
                    'pending'    => true,
                    'department' => $row['department'] ?? null
                ]);
            }
            json_out(false, 'Email already exist');
        }

        $otp = random_int(100000, 999999);
        $_SESSION['email_otp']         = $otp;
        $_SESSION['email_otp_to']      = $email;
        $_SESSION['email_otp_expires'] = time() + 300; // 5 minutes

        if (!sendOTP($email, $otp)) json_out(false, 'Failed to send OTP email. Please try again later.');
        // (you were returning OTP for testing; keep or remove as you like)
        json_out(true, '', ['otp' => $otp, 'message' => 'OTP sent to email.']);
    }

    /* verify_otp */
    if ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if (!isset($_SESSION['email_otp'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires'])) {
            json_out(false, 'No OTP in session.');
        }
        if (time() > (int)$_SESSION['email_otp_expires']) json_out(false, 'OTP expired. Please resend.');
        if ($otp !== (string)$_SESSION['email_otp'])      json_out(false, 'Incorrect OTP.');
        $_SESSION['email_verified'] = true;
        json_out(true);
    }

    /* register */
    if ($action === 'register') {
        $email            = strtolower(trim($_POST['email'] ?? ''));
        $phone            = trim($_POST['phone'] ?? '');
        $department       = trim($_POST['department'] ?? '');
        $password         = (string)($_POST['password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        $role             = 'Admin';   // app-side role for this flow
        $status           = 'Pending'; // app-side status

        if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || ($email !== ($_SESSION['email_otp_to'] ?? ''))) {
            json_out(false, 'Email not verified.');
        }

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors['email'] = 'Invalid email address.';
        if (!preg_match('/^09\d{9}$/', $phone))          $errors['phone'] = 'Invalid phone number. Must be 11 digits starting with 09.';
        if ($department === '')                          $errors['department'] = 'Department is required.';
        if (strlen($password) < 8)                       $errors['password'] = 'Password must be at least 8 characters.';
        if ($password !== $confirm_password)             $errors['confirm_password'] = 'Passwords do not match.';
        if ($errors) json_out(false, 'Validation failed.', ['errors' => $errors]);

        // Double-check duplicates to fail fast before admin-create
        if (auth_email_exists($pdo, $email) || app_email_or_phone_exists($pdo, $email, $phone)) {
            json_out(false, 'Email already exist');
        }

        $auth_id = null; // for cleanup on failure

        // 1) Create Auth user via Admin API (requires service_role)
        [$code, $user, $raw] = supabase_admin_create_user($email, $password, [
            'department' => $department,
            'app_role'   => $role,
            'app_status' => $status,
        ]);

        if ($code === 409 || $code === 422) json_out(false, 'Email already exist');

        $auth_id = $user['id'] ?? null;

        // HARD GATE: do not proceed unless we truly have an Auth user
        if ($code < 200 || $code >= 300 || !$auth_id || !is_uuid_v4($auth_id) || !auth_user_exists_by_id($pdo, $auth_id)) {
            debug_log("Admin create user failed or not persisted: HTTP {$code} " . json_encode($raw));
            json_out(false, 'Email verification failed.');
        }

        // 2) Insert app profile (server can bypass RLS). If this fails,
        //    delete the Auth user we just created to avoid leftovers.
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "insert into " . APP_TABLE . "
              (user_id, email, phone, department, role, password, status, created_at)
              values (:user_id, :email, :phone, :department, :role, :password, :status, now())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'    => $auth_id,
                ':email'      => $email,
                ':phone'      => $phone,
                ':department' => $department,
                ':role'       => $role,
                ':password'   => $hash,
                ':status'     => $status,
            ]);
            $pdo->commit();

            // Clear OTP session
            unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires']);

            json_out(true, '', [
                'user_id' => $auth_id,
                'message' => 'Registration successful. Waiting for admin approval.'
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();

            // Cleanup: remove the Auth user we just created to avoid leftovers
            if (!empty($auth_id)) {
                $deleted = supabase_admin_delete_user($auth_id); // best-effort
                if (!$deleted) debug_log("Cleanup failed: could not delete auth user {$auth_id}");
            }

            $msg = 'Registration failed.';
            if ($e instanceof PDOException && $e->getCode() === '23505') {
                $msg = duplicate_violation_message($e); // email / phone specific
            }
            debug_log("App insert failed: " . $e->getMessage());
            json_out(false, $msg);
        }
    }

    json_out(false, 'Invalid request.');
} catch (Throwable $e) {
    debug_log("Fatal: " . $e->getMessage());
    json_out(false, 'System error.');
}
