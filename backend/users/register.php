<?php
// session_start();
// require_once __DIR__ . '/../../backend/connection.php';
// require_once __DIR__ . '/../admin/send_otp.php';
// header('Content-Type: application/json');

// function jsonResponse($success, $error = '', $extra = [])
// {
//     $resp = ['success' => $success];
//     if ($error) $resp['error'] = $error;
//     return die(json_encode(array_merge($resp, $extra)));
// }


// if (isset($_POST['action']) && $_POST['action'] === 'send_otp' && isset($_POST['email'])) {
//     $email = trim($_POST['email']);
//     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//         jsonResponse(false, 'Invalid email address.');
//     }
//     $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
//     $stmt->bind_param('s', $email);
//     $stmt->execute();
//     $stmt->store_result();
//     if ($stmt->num_rows > 0) {
//         jsonResponse(false, 'Email already exists.');
//     }
//     $stmt->close();
//     $otp = rand(100000, 999999);
//     $_SESSION['email_otp'] = $otp;
//     $_SESSION['email_otp_to'] = $email;

//     if (!sendOTP($email, $otp)) {
//         jsonResponse(false, 'Failed to send OTP email. Please try again later.');
//     }
//     jsonResponse(true, '');
// }


// if (isset($_POST['action']) && $_POST['action'] === 'verify_otp' && isset($_POST['otp'])) {
//     $otp = trim($_POST['otp']);
//     if ($otp == ($_SESSION['email_otp'] ?? '') && isset($_SESSION['email_otp_to'])) {
//         $_SESSION['email_verified'] = true;
//         jsonResponse(true);
//     } else {
//         jsonResponse(false, 'Incorrect OTP.');
//     }
// }


// if (isset($_POST['action']) && $_POST['action'] === 'register') {
//     $email = trim($_POST['email'] ?? '');
//     $phone = trim($_POST['phone'] ?? '');
//     $password = $_POST['password'] ?? '';
//     $confirm_password = $_POST['confirm_password'] ?? '';
//     $role = 'User';
//     $status = 'Verified';


//     if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || ($_SESSION['email_otp_to'] ?? '') !== $email) {
//         jsonResponse(false, 'Email not verified.');
//     }
//     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//         jsonResponse(false, 'Invalid email address.');
//     }
//     if (!preg_match('/^09\d{9}$/', $phone)) {
//         jsonResponse(false, 'Invalid phone number.');
//     }
//     if (strlen($password) < 8) {
//         jsonResponse(false, 'Password must be at least 8 characters.');
//     }
//     if ($password !== $confirm_password) {
//         jsonResponse(false, 'Passwords do not match.');
//     }
//     $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
//     $stmt->bind_param('s', $email);
//     $stmt->execute();
//     $stmt->store_result();
//     if ($stmt->num_rows > 0) {
//         jsonResponse(false, 'Email already exists.');
//     }
//     $stmt->close();
//     $password_hash = password_hash($password, PASSWORD_DEFAULT);
//     $stmt = $conn->prepare('INSERT INTO users (email, phone, role, password, status) VALUES (?, ?, ?, ?, ?)');
//     $stmt->bind_param('sssss', $email, $phone, $role, $password_hash, $status);
//     if ($stmt->execute()) {
//         unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to']);
//         jsonResponse(true);
//     } else {
//         jsonResponse(false, 'Registration failed: ' . $stmt->error);
//     }
// }

// jsonResponse(false, 'Invalid request.');


// backend/services/users/register.php


declare(strict_types=1);

session_start();
header('Content-Type: application/json');

/* ─────────────────────────────────────────────────────────────────────────────
   Autoload (vendor/ is at project root, two levels up from /backend/users)
   ───────────────────────────────────────────────────────────────────────────── */
try {
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
        // Safe for mixed hosts; does not nuke real server env
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[USER-REG] Dotenv load error: ' . $e->getMessage());
}

/* Minimal fallback: hydrate getenv/$_ENV/$_SERVER if host didn’t */
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
    error_log('[USER-REG] ' . $msg);
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
    if (strpos($detail, 'email') !== false) return 'Email already exist';
    if (strpos($detail, 'phone') !== false) return 'Phone number already exists.';
    return 'Duplicate entry found. Please try again.';
}

function is_uuid_v4(string $s): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s);
}

/* ─────────────────────────────────────────────────────────────────────────────
   Includes
   ───────────────────────────────────────────────────────────────────────────── */
require_once dirname(__DIR__) . '/connection.php';      // must expose $pdo (PDO to Supabase Postgres)
require_once dirname(__DIR__) . '/admin/send_otp.php';  // must expose sendOTP($email, $otp)

/* ─────────────────────────────────────────────────────────────────────────────
   Constants
   ───────────────────────────────────────────────────────────────────────────── */
const APP_TABLE = 'public.users';

/* ─────────────────────────────────────────────────────────────────────────────
   Introspection helpers (optional but resilient)
   ───────────────────────────────────────────────────────────────────────────── */
function get_table_columns(PDO $pdo, string $schema, string $table): array
{
    $sql = "select column_name, is_nullable
            from information_schema.columns
            where table_schema = :s and table_name = :t";
    $st = $pdo->prepare($sql);
    $st->execute([':s' => $schema, ':t' => $table]);
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[strtolower($r['column_name'])] = strtolower($r['is_nullable'] ?? 'yes');
    }
    return $out; // ['department' => 'yes'/'no', ...]
}

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

function app_email_or_phone_exists(PDO $pdo, string $email, ?string $phone = null): bool
{
    $sql = "select user_id from " . APP_TABLE . " where lower(email)=lower(:e)";
    $args = [':e' => $email];
    if ($phone !== null && $phone !== '') {
        $sql .= " or phone = :p";
        $args[':p'] = $phone;
    }
    $sql .= " limit 1";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return (bool)$st->fetchColumn();
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
        'email_confirm' => true,   // mark verified on Auth side (OTP already done app-side)
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
    $user = null;
    if (is_array($json)) $user = $json['user'] ?? $json;
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

    // quick env diagnostic (booleans only)
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

        // Block immediately if email already exists anywhere
        if (auth_email_exists($pdo, $email) || app_email_or_phone_exists($pdo, $email)) {
            json_out(false, 'Email already exist');
        }

        $otp = random_int(100000, 999999);
        $_SESSION['email_otp']         = $otp;
        $_SESSION['email_otp_to']      = $email;
        $_SESSION['email_otp_expires'] = time() + 300; // 5 minutes

        if (!sendOTP($email, $otp)) json_out(false, 'Failed to send OTP email. Please try again later.');

        // DEV ONLY: expose OTP in response for quick testing (remove in production)
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
        $password         = (string)($_POST['password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');

        $role   = 'User';     // per request
        $status = 'Verified'; // per request

        // Must have verified email (and same address as OTP target)
        if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || ($email !== ($_SESSION['email_otp_to'] ?? ''))) {
            json_out(false, 'Please verify your email first.');
        }

        // Validate fields
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors['email'] = 'Invalid email address.';
        if (!preg_match('/^09\d{9}$/', $phone))          $errors['phone'] = 'Invalid phone number. Must be 11 digits starting with 09.';
        if (strlen($password) < 8)                       $errors['password'] = 'Password must be at least 8 characters.';
        if ($password !== $confirm_password)             $errors['confirm_password'] = 'Passwords do not match.';
        if ($errors) json_out(false, 'Validation failed.', ['errors' => $errors]);

        // Fail fast on duplicates
        if (auth_email_exists($pdo, $email) || app_email_or_phone_exists($pdo, $email, $phone)) {
            json_out(false, 'Email already exist');
        }

        // Create Auth user via Admin API (server-side)
        [$code, $user, $raw] = supabase_admin_create_user($email, $password, [
            'app_role'   => $role,
            'app_status' => $status,
        ]);

        if ($code === 409 || $code === 422) json_out(false, 'Email already exist');

        $auth_id = $user['id'] ?? null;

        // Ensure Auth user actually persisted
        if ($code < 200 || $code >= 300 || !$auth_id || !is_uuid_v4($auth_id)) {
            debug_log("Admin create user failed or not persisted: HTTP {$code} " . json_encode($raw));
            json_out(false, 'Registration failed.');
        }

        // Insert app profile. If this fails, delete the Auth user we just created.
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Build columns dynamically to play nice with schemas that may have 'department'
            $cols = get_table_columns($pdo, 'public', 'users');
            $hasDept   = array_key_exists('department', $cols);
            $deptNull  = $hasDept ? ($cols['department'] === 'yes') : true;

            $sql = "insert into " . APP_TABLE . " (user_id, email, phone, role, password, status, created_at"
                . ($hasDept ? ", department" : "")
                . ") values (:user_id, :email, :phone, :role, :password, :status, now()"
                . ($hasDept ? ", :department" : "")
                . ")";

            $stmt = $pdo->prepare($sql);
            $params = [
                ':user_id'  => $auth_id,
                ':email'    => $email,
                ':phone'    => $phone,
                ':role'     => $role,
                ':password' => $hash,
                ':status'   => $status,
            ];
            if ($hasDept) {
                // If department is NOT NULL, set a safe default; else let it be null
                $params[':department'] = $deptNull ? null : 'General';
            }

            $stmt->execute($params);
            $pdo->commit();

            // Clear OTP session
            unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires']);

            json_out(true, '', [
                'user_id' => $auth_id,
                'message' => 'Registration successful.'
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();

            // Cleanup: remove the Auth user we just created to avoid leftovers
            if (!empty($auth_id)) {
                $deleted = supabase_admin_delete_user($auth_id);
                if (!$deleted) debug_log("Cleanup failed: could not delete auth user {$auth_id}");
            }

            $msg = 'Registration failed.';
            if ($e instanceof PDOException && $e->getCode() === '23505') {
                $msg = duplicate_violation_message($e);
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
