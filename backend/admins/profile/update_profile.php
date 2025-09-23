<?php
// backend/admins/profile/update_profile.php
declare(strict_types=1);

/* Strict JSON only + error log to file */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/update_profile.error.log');
error_reporting(E_ALL);

// Kill any buffered output (BOM, stray echoes)
if (function_exists('ob_get_level')) {
    while (ob_get_level()) {
        ob_end_clean();
    }
}
header('Content-Type: application/json; charset=utf-8');

session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../connection.php'; // $pdo + SUPABASE_* constants
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* PHPMailer helper */
require_once dirname(__DIR__, 2) . '/admin/send_otp.php'; // sendOTP($email, $code)
if (!is_callable('sendOTP')) {
    error_log('[BOOT] sendOTP not found after include');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mailer not available (sendOTP missing).']);
    exit;
}

$user_uuid = (string)$_SESSION['user_id'];

/* Storage config from connection.php (with env fallback) */
$SUPABASE_URL         = rtrim((string)(defined('SUPABASE_URL') ? SUPABASE_URL : (getenv('SUPABASE_URL') ?: '')), '/');
$SUPABASE_SERVICE_KEY = (string)(defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : (getenv('SUPABASE_SERVICE_KEY') ?: ''));
$BUCKET               = (string)(defined('SUPABASE_BUCKET') ? SUPABASE_BUCKET : (getenv('SUPABASE_BUCKET') ?: 'user_profiles'));

/* Helpers */
function out_ok(array $extra = [])
{
    echo json_encode(['success' => true] + $extra);
    exit;
}
function out_err(string $msg, array $extra = [], int $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg] + $extra);
    exit;
}
function guess_mime_by_ext(string $ext): string
{
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/* Fetch current user record once */
$st = $pdo->prepare("SELECT email, image FROM public.users WHERE user_id = :uid LIMIT 1");
$st->execute([':uid' => $user_uuid]);
$current = $st->fetch(PDO::FETCH_ASSOC) ?: ['email' => null, 'image' => null];

/* Router by action */
$action = (string)($_POST['action'] ?? '');

/* A) SEND EMAIL OTP — blocks if email already in use */
if ($action === 'send_email_otp') {
    $newEmail = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) out_err('Invalid email address.');

    // Unchanged email → no OTP needed
    if (!empty($current['email']) && strcasecmp($newEmail, (string)$current['email']) === 0) {
        out_ok(['message' => 'Email unchanged; OTP not required.', 'code' => 'UNCHANGED_EMAIL']);
    }

    // Email already used by another user?
    $q = $pdo->prepare("SELECT 1 FROM public.users WHERE lower(email) = :e AND user_id <> :uid LIMIT 1");
    $q->execute([':e' => $newEmail, ':uid' => $user_uuid]);
    if ($q->fetchColumn()) {
        out_err('Email already exists.', ['code' => 'EMAIL_IN_USE']);
    }

    // Create OTP for 5 minutes
    $otp = (string)random_int(100000, 999999);
    $_SESSION['email_otp']         = $otp;
    $_SESSION['email_otp_to']      = $newEmail;
    $_SESSION['email_otp_expires'] = time() + 300;
    unset($_SESSION['email_verified']);

    // Send — send_otp.php must not echo anything
    $sent = sendOTP($newEmail, $otp);
    if (!$sent) out_err('Failed to send OTP email. Please try again later.');
    out_ok(['message' => 'OTP sent.']);
}

/* B) VERIFY EMAIL OTP */
if ($action === 'verify_email_otp') {
    $code = trim((string)($_POST['otp'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) out_err('Invalid OTP format.');

    if (!isset($_SESSION['email_otp'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires'])) {
        out_err('No OTP in session.');
    }
    if (time() > (int)$_SESSION['email_otp_expires']) out_err('OTP expired. Please resend.');
    if ($code !== (string)$_SESSION['email_otp'])      out_err('Incorrect OTP.');

    $_SESSION['email_verified'] = true;
    out_ok(['message' => 'OTP verified.']);
}

/* C) SUBMIT UPDATE REQUEST (insert into profile_update_requests only) */
$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name'] ?? ''));
$age        = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$email      = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? ''));
$phone      = trim((string)($_POST['phone'] ?? ''));
$password   = (string)($_POST['password'] ?? '');
$password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out_err('Invalid email address.');
}

/* Pre-flight checks for storage config (so errors are explicit) */
if ($SUPABASE_URL === '' || stripos($SUPABASE_URL, 'supabase.co') === false) {
    out_err('Storage misconfigured: SUPABASE_URL is not set.', ['code' => 'CONFIG_SUPABASE_URL']);
}
if ($SUPABASE_SERVICE_KEY === '' || stripos($SUPABASE_SERVICE_KEY, 'YOUR_SERVICE_ROLE_KEY') !== false) {
    out_err('Storage misconfigured: SUPABASE_SERVICE_KEY is not set.', ['code' => 'CONFIG_SUPABASE_KEY']);
}
if (!function_exists('curl_init')) {
    out_err('Server missing cURL PHP extension.', ['code' => 'NO_CURL']);
}

try {
    $pdo->beginTransaction();

    // 1) block duplicate pending
    $st = $pdo->prepare("
        SELECT 1
        FROM public.profile_update_requests
        WHERE user_id = :uid AND lower(status) = 'pending'
        LIMIT 1
    ");
    $st->execute([':uid' => $user_uuid]);
    if ($st->fetchColumn()) {
        $pdo->rollBack();
        out_err('You already have a pending profile update request.', ['code' => 'PENDING_EXISTS']);
    }

    // 2) email change gating + duplicate check
    $email_changed = ($email !== '' && strcasecmp((string)$email, (string)$current['email']) !== 0);
    if ($email_changed) {
        $q = $pdo->prepare("SELECT 1 FROM public.users WHERE lower(email) = :e AND user_id <> :uid LIMIT 1");
        $q->execute([':e' => strtolower($email), ':uid' => $user_uuid]);
        if ($q->fetchColumn()) {
            $pdo->rollBack();
            out_err('Email already exists.', ['code' => 'EMAIL_IN_USE']);
        }

        $ok = isset($_SESSION['email_verified'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires'])
            && $_SESSION['email_verified'] === true
            && strcasecmp((string)$_SESSION['email_otp_to'], $email) === 0
            && time() < (int)$_SESSION['email_otp_expires'];

        if (!$ok) {
            $pdo->rollBack();
            out_err('OTP verification required for email change.', ['code' => 'OTP_REQUIRED']);
        }
    }

    // 3) default image = current URL unless new file uploaded
    $public_url = !empty($current['image']) ? (string)$current['image'] : null;

    // 4) upload to Supabase Storage if file present
    if (!empty($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
        $tmp      = $_FILES['profile_image']['tmp_name'];
        $origName = $_FILES['profile_image']['name'];
        $size     = (int)($_FILES['profile_image']['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true)) {
            throw new RuntimeException('Invalid image type.');
        }

        // Determine MIME
        $mime = 'application/octet-stream';
        if (class_exists('finfo')) {
            $fi  = new finfo(FILEINFO_MIME_TYPE);
            $det = $fi->file($tmp);
            $mime = $det ?: guess_mime_by_ext($ext);
        } else {
            $mime = guess_mime_by_ext($ext);
        }

        // Build object path
        $image_path = $user_uuid . '/profile-' . time() . '.' . $ext;

        // Upload URL
        $encoded_path = str_replace('%2F', '/', rawurlencode($image_path));
        $uploadUrl = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($BUCKET) . '/' . $encoded_path;

        // Do upload
        $payload = file_get_contents($tmp);
        if ($payload === false) {
            throw new RuntimeException('Failed reading uploaded file.');
        }

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $SUPABASE_SERVICE_KEY,
                'apikey: ' . $SUPABASE_SERVICE_KEY, // some setups require both
                'Content-Type: ' . $mime,
                'x-upsert: true',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('Upload cURL error: ' . $cerr);
        }
        if ($http >= 400) {
            $snippet = is_string($resp) ? substr($resp, 0, 400) : '';
            throw new RuntimeException('Upload failed (HTTP ' . $http . '): ' . $snippet);
        }

        // Public URL (bucket must be public to render directly)
        $public_url = $SUPABASE_URL . '/storage/v1/object/public/' . $BUCKET . '/' . $image_path;
    }

    // 5) insert pending request (DOES NOT update public.users)
    $sql = "
      INSERT INTO public.profile_update_requests
        (user_id, image, first_name, last_name, age, email, department, phone,
         status, reason_for_rejection, password, is_read, created_at)
      VALUES
        (:uid, :image, :first_name, :last_name, :age, :email, :department, :phone,
         'pending', NULL, :password, false, now())
      RETURNING request_id
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':uid'        => $user_uuid,
        ':image'      => $public_url,
        ':first_name' => $first_name !== '' ? $first_name : null,
        ':last_name'  => $last_name  !== '' ? $last_name  : null,
        ':age'        => $age,
        ':email'      => $email      !== '' ? $email      : null,
        ':department' => $department !== '' ? $department : null,
        ':phone'      => $phone      !== '' ? $phone      : null,
        ':password'   => $password_hash
    ]);
    $request_id = (string)$st->fetchColumn();

    $pdo->commit();

    // Clear OTP flags after success so next email change must verify again
    if (!empty($email_changed)) {
        unset($_SESSION['email_verified'], $_SESSION['email_otp'], $_SESSION['email_otp_to'], $_SESSION['email_otp_expires']);
    }

    out_ok(['request_id' => $request_id, 'public_url' => $public_url]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PROFILE/REQUEST INSERT or UPLOAD] ' . $e->getMessage());

    $msg = $e->getMessage();
    if (stripos($msg, 'Upload') !== false || stripos($msg, 'cURL') !== false) {
        out_err('Image upload failed. See server log for details.', ['code' => 'UPLOAD_FAILED']);
    }

    out_err('Server error.');
}
