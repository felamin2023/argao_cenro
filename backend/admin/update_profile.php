<?php
// backend/admin/update_profile.php
declare(strict_types=1);

/* ---------- Output mode: JSON only + error log ---------- */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/update_profile.error.log');
error_reporting(E_ALL);

/* Clean any buffered output (avoid BOM/whitespace before JSON) */
if (function_exists('ob_get_level')) {
    while (ob_get_level()) ob_end_clean();
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

/* ---------- DB ---------- */
require_once __DIR__ . '/../../backend/connection.php'; // must expose $pdo (PDO -> Supabase Postgres)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_uuid = (string)$_SESSION['user_id'];

/* ---------- Helpers ---------- */
function out_ok(array $extra = []): void
{
    echo json_encode(['success' => true] + $extra);
    exit;
}
function out_err(string $msg, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg] + $extra);
    exit;
}
function guess_mime_by_ext(string $ext): string
{
    $map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'svg'  => 'image/svg+xml',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
function safe_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}
function sanitize_name(string $s): string
{
    // collapse spaces, trim; allow letters, spaces, hyphen, apostrophe, dot
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return $s;
}

/* ---------- Load current user (for comparisons) ---------- */
$st = $pdo->prepare("
    SELECT user_id, image, email, first_name, last_name, age, department, password
    FROM public.users
    WHERE user_id = :uid
    LIMIT 1
");
$st->execute([':uid' => $user_uuid]);
$current = $st->fetch(PDO::FETCH_ASSOC);
if (!$current) {
    out_err('User not found.', ['code' => 'USER_NOT_FOUND'], 404);
}

/* ---------- Parse inputs ---------- */
$first_name       = sanitize_name((string)($_POST['first_name'] ?? ''));
$last_name        = sanitize_name((string)($_POST['last_name'] ?? ''));
$age_in           = trim((string)($_POST['age'] ?? ''));
$age              = ($age_in === '') ? null : (ctype_digit($age_in) ? (int)$age_in : null);
$email            = trim((string)($_POST['email'] ?? ''));          // UI disabled; ignored unless provided
$department       = trim((string)($_POST['department'] ?? ''));     // UI disabled; ignored
$phone            = trim((string)($_POST['phone'] ?? ''));          // optional
$password         = (string)($_POST['password'] ?? '');
$confirm_password = (string)($_POST['confirm_password'] ?? '');

/* ---------- Validations (server mirrors UI expectations) ---------- */
$errors = [];

/* Names: allow single-letter names, but not empty; cap length for sanity */
if (array_key_exists('first_name', $_POST)) {
    if ($first_name === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (safe_strlen($first_name) > 100) {
        $errors['first_name'] = 'First name is too long.';
    }
}
if (array_key_exists('last_name', $_POST)) {
    if ($last_name === '') {
        $errors['last_name'] = 'Last name is required.';
    } elseif (safe_strlen($last_name) > 100) {
        $errors['last_name'] = 'Last name is too long.';
    }
}

/* Age: optional, but must be an integer between 0 and 120 if provided */
if ($age_in !== '' && $age === null) {
    $errors['age'] = 'Age must be a whole number.';
} elseif ($age !== null && ($age < 0 || $age > 120)) {
    $errors['age'] = 'Age must be between 0 and 120.';
}

/* Password: optional; if present must match + min length 6 + not same as current */
if ($password !== '' || $confirm_password !== '') {
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    } elseif (safe_strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    } else {
        // Only check "different from current" if we actually have a hashed password saved
        $currHashed = (string)($current['password'] ?? '');
        if ($currHashed !== '' && preg_match('/^\$2y\$/', $currHashed)) {
            if (password_verify($password, $currHashed)) {
                $errors['password'] = 'New password must be different from current password.';
            }
        }
    }
}

if (!empty($errors)) {
    out_err('Validation failed.', ['errors' => $errors], 200);
}

/* ---------- Optional: image upload ----------
   Strategy:
   - If env SUPABASE_URL + SUPABASE_SERVICE_KEY are present → upload to Supabase Storage
   - Else → fallback to local storage folder: upload/admin_profiles/  (DB will store just the filename)
*/
$new_image_value = null;
if (!empty($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    try {
        $tmp      = $_FILES['profile_image']['tmp_name'];
        $origName = $_FILES['profile_image']['name'];
        $size     = (int)($_FILES['profile_image']['size'] ?? 0);
        if ($size <= 0) throw new RuntimeException('Uploaded file is empty.');

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Invalid image type. Allowed: ' . implode(', ', $allowed));
        }
        // 5MB soft limit
        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('Image is too large (max 5MB).');
        }

        // Detect MIME
        $mime = guess_mime_by_ext($ext);
        if (class_exists('finfo')) {
            $fi  = new finfo(FILEINFO_MIME_TYPE);
            $det = $fi->file($tmp);
            if (is_string($det) && $det !== '') $mime = $det;
        }

        $SUPABASE_URL         = rtrim((string)(getenv('SUPABASE_URL') ?: ''), '/');
        $SUPABASE_SERVICE_KEY = (string)(getenv('SUPABASE_SERVICE_KEY') ?: '');
        $BUCKET               = (string)(getenv('SUPABASE_BUCKET') ?: 'profile_photos');

        $object_key = $user_uuid . '/profile-' . time() . '.' . $ext;

        if ($SUPABASE_URL !== '' && stripos($SUPABASE_URL, 'supabase.co') !== false && $SUPABASE_SERVICE_KEY !== '') {
            // Upload to Supabase Storage
            if (!function_exists('curl_init')) {
                throw new RuntimeException('Server missing cURL PHP extension.');
            }

            $encoded_path = str_replace('%2F', '/', rawurlencode($object_key));
            $uploadUrl    = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($BUCKET) . '/' . $encoded_path;

            $payload = file_get_contents($tmp);
            if ($payload === false) throw new RuntimeException('Failed reading uploaded file.');

            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $SUPABASE_SERVICE_KEY,
                    'apikey: ' . $SUPABASE_SERVICE_KEY,
                    'Content-Type: ' . $mime,
                    'x-upsert: true',
                ],
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $resp = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);

            if ($resp === false)  throw new RuntimeException('Upload cURL error: ' . $cerr);
            if ($http >= 400)     throw new RuntimeException('Upload failed (HTTP ' . $http . ').');

            // Public URL (assuming bucket has public policy)
            $new_image_value = $SUPABASE_URL . '/storage/v1/object/public/' . $BUCKET . '/' . $object_key;
        } else {
            // Fallback: local storage
            $dir = __DIR__ . '/../../upload/admin_profiles';
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('Failed to create upload directory.');
                }
            }
            $safeBase = 'profile-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . '/' . $safeBase;

            if (!move_uploaded_file($tmp, $dest)) {
                throw new RuntimeException('Failed to save uploaded file.');
            }

            // Store only filename. Frontend prepends upload/admin_profiles/
            $new_image_value = $safeBase;
        }
    } catch (Throwable $e) {
        error_log('[PROFILE/IMAGE UPLOAD] ' . $e->getMessage());
        out_err('Image upload failed. ' . $e->getMessage(), ['code' => 'UPLOAD_FAILED']);
    }
}

/* ---------- Email uniqueness (only if actually changing & provided) ---------- */
if ($email !== '' && strcasecmp($email, (string)$current['email']) !== 0) {
    $st = $pdo->prepare("SELECT 1 FROM public.users WHERE email = :email AND user_id != :uid LIMIT 1");
    $st->execute([':email' => $email, ':uid' => $user_uuid]);
    if ($st->fetchColumn()) {
        out_err('Email already in use.', ['code' => 'EMAIL_IN_USE']);
    }
}

/* ---------- Build dynamic UPDATE ---------- */
$fields = [];
$params = [':uid' => $user_uuid];

// Only set columns that the client actually sent (so we don’t overwrite with nulls unexpectedly)
if (array_key_exists('first_name', $_POST)) {
    $fields['first_name'] = $first_name;
}
if (array_key_exists('last_name',  $_POST)) {
    $fields['last_name']  = $last_name;
}
if (array_key_exists('age',        $_POST)) {
    $fields['age']        = $age;
} // NULL if blank
if ($email !== '') {
    $fields['email']     = $email;
} // UI disabled; ignored if empty
if ($phone !== '') {
    $fields['phone']     = $phone;
} // optional
if ($new_image_value !== null) {
    $fields['image']     = $new_image_value;
}

// Password (hashed) if provided & valid
if ($password !== '' && empty($errors['password']) && empty($errors['confirm_password'])) {
    $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
}

if (!$fields) {
    out_ok(['updated' => false, 'message' => 'Nothing to update.']);
}

$setParts = [];
foreach ($fields as $col => $val) {
    $setParts[] = $col . ' = :' . $col;
    $params[':' . $col] = $val;
}

$sql = 'UPDATE public.users SET ' . implode(', ', $setParts) . ' WHERE user_id = :uid RETURNING user_id, image, first_name, last_name, age, email, department';

try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $updated = $st->fetch(PDO::FETCH_ASSOC);

    if (!$updated) {
        out_err('Failed to update profile.', ['code' => 'DB_UPDATE_FAIL']);
    }

    // Optional audit log example:
    // try {
    //     $log = $pdo->prepare('INSERT INTO public.admin_activity_logs (admin_user_id, admin_department, action, details, ip) VALUES (:uid, :dept, :act, :det, :ip)');
    //     $log->execute([
    //         ':uid'  => $user_uuid,
    //         ':dept' => (string)($current['department'] ?? 'unknown'),
    //         ':act'  => 'Profile update',
    //         ':det'  => json_encode(array_keys($fields)),
    //         ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
    //     ]);
    // } catch (Throwable $le) { error_log('[PROFILE/LOG] ' . $le->getMessage()); }

    out_ok(['user' => $updated, 'updated' => true]);
} catch (Throwable $e) {
    error_log('[PROFILE/UPDATE EXEC] ' . $e->getMessage());
    out_err('Failed to update profile. Please try again.', ['code' => 'DB_ERROR']);
}
