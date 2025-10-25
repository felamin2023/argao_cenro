<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

/*
 * backend/users/update_profile.php
 * - Supports session user stored as UUID (users.user_id) or numeric (users.id)
 * - Sends OTP when email changes; verifies OTP
 * - Uploads profile image to Supabase Storage bucket "user_profiles" using PUT (no 0-byte files)
 * - Saves the public image URL to public.users.image
 * - NEW: If DEBUG_OTP=true (env), include 'otp_debug' in JSON and log to server error_log
 */

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}
$sessionVal = (string)$_SESSION['user_id'];   // could be UUID or numeric

require_once __DIR__ . '/../../backend/connection.php'; // must define $pdo (PDO pgsql)
require_once __DIR__ . '/../../backend/admin/send_otp.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --------- Debug switch for OTP exposure (presentation only) ----------
$DEBUG_OTP = filter_var(getenv('DEBUG_OTP') ?: 'false', FILTER_VALIDATE_BOOLEAN);

// --------- Supabase env helpers ----------
function sb_env_key(): ?string
{
    $svc = getenv('SUPABASE_SERVICE_ROLE');
    if ($svc) return $svc;
    $svcKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if ($svcKey) return $svcKey;
    $anon = getenv('SUPABASE_ANON_KEY');
    return $anon ?: null;
}
function sb_url(): ?string
{
    $u = getenv('SUPABASE_URL');
    return $u ? rtrim($u, '/') : null;
}
function storage_public_url(string $bucket, string $path): ?string
{
    $base = sb_url();
    if (!$base) return null;
    return $base . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
}

/** Upload to Supabase Storage sending RAW BYTES with PUT. */
function upload_to_supabase_storage(
    string $bucket,
    string $path,
    string $tmpFile,
    string $contentType,
    ?string &$errOut = null
): ?string {
    $base = sb_url();
    $key  = sb_env_key();
    if (!$base || !$key) {
        $errOut = 'Missing SUPABASE_URL or API key env';
        return null;
    }

    $data = @file_get_contents($tmpFile);
    if ($data === false) {
        $errOut = 'Cannot read uploaded tmp file';
        return null;
    }
    $len = strlen($data);
    if ($len === 0) {
        $errOut = 'Uploaded tmp file is empty (0 bytes)';
        return null;
    }

    $endpoint = $base . '/storage/v1/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path));
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'apikey: ' . $key,
            'Content-Type: ' . $contentType,
            'Content-Length: ' . $len,
            'x-upsert: true',
        ],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($http >= 200 && $http < 300) return storage_public_url($bucket, $path);
    $errOut = "Storage upload failed (HTTP {$http}). cURL: {$err}. Response: {$resp}";
    return null;
}

// --------- Identify user column from session (UUID vs numeric) ----------
$isUuid = (bool)preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $sessionVal);
$idCol   = $isUuid ? 'user_id' : 'id';
$idParam = $isUuid ? $sessionVal : (string)(int)$sessionVal;

// --------- Fetch current email & image ----------
$stmt = $pdo->prepare("SELECT user_id, email, image, first_name, last_name, age, phone FROM public.users WHERE {$idCol} = :id LIMIT 1");
$stmt->execute([':id' => $idParam]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit();
}
$current_email = (string)($row['email'] ?? '');
$current_image = (string)($row['image'] ?? '');
$current_first = (string)($row['first_name'] ?? '');
$current_last  = (string)($row['last_name'] ?? '');
$current_age   = $row['age'] ?? null;
$current_phone = (string)($row['phone'] ?? '');

// --------- Handle RESEND OTP ----------
if (isset($_POST['resend']) && $_POST['resend'] === 'true') {
    if (empty($_SESSION['otp_email'])) {
        echo json_encode(['success' => false, 'error' => 'No email to resend to.']);
        exit();
    }
    $email = (string)$_SESSION['otp_email'];
    $new_otp = random_int(100000, 999999);
    $_SESSION['otp_code'] = $new_otp;
    $_SESSION['otp_sent'] = time();
    $_SESSION['otp_attempts'] = 0;

    if (!sendOTP($email, $new_otp)) {
        echo json_encode(['success' => false, 'error' => 'Failed to resend verification email.']);
        exit();
    }
    if ($DEBUG_OTP) {
        error_log("[DEBUG OTP] {$email}: {$new_otp}");
        echo json_encode(['success' => true, 'otp_required' => true, 'message' => 'New verification code sent!', 'otp_debug' => (string)$new_otp]);
    } else {
        echo json_encode(['success' => true, 'otp_required' => true, 'message' => 'New verification code sent!']);
    }
    exit();
}

// --------- If OTP submitted, validate ----------
if (isset($_POST['otp_code'])) {
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    if ($_SESSION['otp_attempts'] > 5) {
        echo json_encode(['success' => false, 'error' => 'Too many attempts. Please try again later.']);
        exit();
    }
    if (!isset($_SESSION['otp_code'], $_SESSION['otp_sent'])) {
        echo json_encode(['success' => false, 'error' => 'Verification session expired.']);
        exit();
    }
    if (time() - (int)$_SESSION['otp_sent'] > 300) {
        unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email'], $_SESSION['otp_attempts']);
        echo json_encode(['success' => false, 'error' => 'Verification code has expired.']);
        exit();
    }
    $submitted_otp = trim((string)$_POST['otp_code']);
    if ($submitted_otp !== (string)$_SESSION['otp_code']) {
        echo json_encode(['success' => false, 'error' => 'Invalid verification code.']);
        exit();
    }
    // success => clear OTP session
    unset($_SESSION['otp_code'], $_SESSION['otp_sent'], $_SESSION['otp_email'], $_SESSION['otp_attempts']);
}

// --------- Collect inputs ----------
$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name'] ?? ''));
$age_raw    = trim((string)($_POST['age'] ?? ''));
$phone      = trim((string)($_POST['phone'] ?? ''));
$email      = trim((string)($_POST['email'] ?? ''));
$password   = trim((string)($_POST['password'] ?? ''));
$confirm    = trim((string)($_POST['confirm_password'] ?? ''));

if ($password && $password !== $confirm) {
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit();
}
$age = ($age_raw === '' ? null : (is_numeric($age_raw) ? (int)$age_raw : null));

if ($first_name === '') {
    $first_name = $current_first;
}
if ($last_name === '') {
    $last_name = $current_last;
}
if ($age === null && $age_raw === '' && $current_age !== null) {
    $age = (int)$current_age;
}
if ($phone === '') {
    $phone = $current_phone;
}

// --------- Email change flow (pre-check) ----------
$email_changed = false;
if ($email === '') {
    $email = $current_email;
} else {
    $email_changed = (strcasecmp($email, $current_email) !== 0);
}
if ($email_changed && !isset($_POST['otp_code'])) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM public.users WHERE lower(email) = lower(:em) AND {$idCol} <> :id");
    $q->execute([':em' => $email, ':id' => $idParam]);
    $exists = (int)$q->fetchColumn() > 0;
    if ($exists) {
        echo json_encode(['success' => false, 'error' => 'Email already registered.']);
        exit();
    }

    $otp_code = random_int(100000, 999999);
    $_SESSION['otp_code'] = $otp_code;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_sent'] = time();
    $_SESSION['otp_attempts'] = 0;

    if (!sendOTP($email, $otp_code)) {
        echo json_encode(['success' => false, 'error' => 'Failed to send verification email.']);
        exit();
    }

    if ($DEBUG_OTP) {
        error_log("[DEBUG OTP] {$email}: {$otp_code}");
        echo json_encode(['success' => false, 'otp_required' => true, 'message' => 'Verification code sent to your email', 'otp_debug' => (string)$otp_code]);
    } else {
        echo json_encode(['success' => false, 'otp_required' => true, 'message' => 'Verification code sent to your email']);
    }
    exit();
}

// --------- Handle profile image upload ----------
$image_value = $current_image; // keep current if no new file
if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Image upload error code: ' . $_FILES['profile_image']['error']]);
        exit();
    }
    if (empty($_FILES['profile_image']['size']) || (int)$_FILES['profile_image']['size'] === 0) {
        echo json_encode(['success' => false, 'error' => 'Uploaded image is empty (0 bytes).']);
        exit();
    }

    $tmp  = $_FILES['profile_image']['tmp_name'];
    $name = $_FILES['profile_image']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg';

    // Detect content type
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $m = finfo_file($finfo, $tmp);
            if ($m) $mime = $m;
            finfo_close($finfo);
        }
    }

    $bucket = 'user_profiles';
    $userPathKey = $isUuid ? $idParam : (string)(int)$idParam;
    $path   = $userPathKey . '/avatar-' . time() . '.' . $ext;

    $errText = null;
    $publicUrl = upload_to_supabase_storage($bucket, $path, $tmp, $mime, $errText);
    if ($publicUrl) {
        $image_value = $publicUrl;
    } else {
        echo json_encode(['success' => false, 'error' => 'Image upload failed: ' . ($errText ?? 'unknown error')]);
        exit();
    }
}

// --------- Perform UPDATE ----------
$fields = [
    'image'      => $image_value,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'age'        => $age,
    'email'      => $email,
    'phone'      => $phone,
];
$params = [
    ':img'  => $fields['image'],
    ':fn'   => $fields['first_name'],
    ':ln'   => $fields['last_name'],
    ':age'  => $fields['age'],
    ':em'   => $fields['email'],
    ':ph'   => $fields['phone'],
    ':id'   => $idParam,
];

$sql = "UPDATE public.users
        SET image = :img,
            first_name = :fn,
            last_name = :ln,
            age = :age,
            email = :em,
            phone = :ph";

if ($password !== '') {
    $sql .= ", password = :pwd";
    $params[':pwd'] = password_hash($password, PASSWORD_DEFAULT);
}
$sql .= " WHERE {$idCol} = :id";

try {
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    if ($ok) {
        $targetClientUserId = $row['user_id'] ?? null;
        if ($targetClientUserId) {
            try {
                $clientExistsStmt = $pdo->prepare("SELECT client_id FROM public.client WHERE user_id = :uid LIMIT 1");
                $clientExistsStmt->execute([':uid' => $targetClientUserId]);
                $clientRow = $clientExistsStmt->fetch(PDO::FETCH_ASSOC);

                if ($clientRow) {
                    $clientUpdate = $pdo->prepare("
                        UPDATE public.client
                        SET first_name = :fn,
                            last_name = :ln,
                            contact_number = :ph
                        WHERE user_id = :uid
                    ");
                    $clientUpdate->execute([
                        ':fn'  => $fields['first_name'],
                        ':ln'  => $fields['last_name'],
                        ':ph'  => $fields['phone'],
                        ':uid' => $targetClientUserId,
                    ]);
                } else {
                    $clientInsert = $pdo->prepare("
                        INSERT INTO public.client (user_id, first_name, last_name, contact_number)
                        VALUES (:uid, :fn, :ln, :ph)
                    ");
                    $clientInsert->execute([
                        ':uid' => $targetClientUserId,
                        ':fn'  => $fields['first_name'],
                        ':ln'  => $fields['last_name'],
                        ':ph'  => $fields['phone'],
                    ]);
                }
            } catch (Throwable $clientErr) {
                error_log('[update_profile] Failed to sync client table: ' . $clientErr->getMessage());
            }
        }
        $refreshStmt = $pdo->prepare("SELECT first_name, last_name, age, email, phone FROM public.users WHERE {$idCol} = :id LIMIT 1");
        $refreshStmt->execute([':id' => $idParam]);
        $fresh = $refreshStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        echo json_encode([
            'success' => true,
            'updated_rows' => $stmt->rowCount(),
            'user' => $fresh,
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $e->getMessage()]);
}
