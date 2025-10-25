<?php
// backend/admins/profile/update_profile.php
declare(strict_types=1);

/* JSON only + log to file */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/update_profile.error.log');
error_reporting(E_ALL);

/* Clean any buffered output */
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

require_once __DIR__ . '/../../connection.php'; // must expose $pdo (PDO -> Supabase/Postgres)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_uuid = (string)$_SESSION['user_id'];

/* ---------------- Helpers ---------------- */
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

$st = $pdo->prepare("SELECT image, email, first_name AS current_first_name, department AS current_department FROM public.users WHERE user_id = :uid LIMIT 1");
$st->execute([':uid' => $user_uuid]);
$current = $st->fetch(PDO::FETCH_ASSOC) ?: ['image' => null];

/* ---------------- Parse inputs ---------------- */
$first_name = trim((string)($_POST['first_name'] ?? ''));
$last_name  = trim((string)($_POST['last_name'] ?? ''));
$age        = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null; // blank => NULL
$phone      = trim((string)($_POST['phone'] ?? '')); // add a <input name="phone"> if you want it
$password   = (string)($_POST['password'] ?? '');
$password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
$email      = trim((string)($_POST['email'] ?? ''));
$department = trim((string)($_POST['department'] ?? ''));

/* ---------------- Optional: upload new avatar to Supabase ---------------- */
/* Only check storage config if a file is actually provided */
$new_public_image_url = null;
if (!empty($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    // Storage config from connection.php (constants or env). Default to 'profile_photos'.
    $SUPABASE_URL         = rtrim((string)(defined('SUPABASE_URL') ? SUPABASE_URL : (getenv('SUPABASE_URL') ?: '')), '/');
    $SUPABASE_SERVICE_KEY = (string)(defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : (getenv('SUPABASE_SERVICE_KEY') ?: ''));
    $BUCKET               = (string)(defined('SUPABASE_BUCKET') ? SUPABASE_BUCKET : (getenv('SUPABASE_BUCKET') ?: 'profile_photos'));

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
        $tmp      = $_FILES['profile_image']['tmp_name'];
        $origName = $_FILES['profile_image']['name'];
        $size     = (int)($_FILES['profile_image']['size'] ?? 0);
        if ($size <= 0) throw new RuntimeException('Uploaded file is empty.');

        $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true)) {
            throw new RuntimeException('Invalid image type.');
        }

        $mime = 'application/octet-stream';
        if (class_exists('finfo')) {
            $fi  = new finfo(FILEINFO_MIME_TYPE);
            $det = $fi->file($tmp);
            $mime = $det ?: guess_mime_by_ext($ext);
        } else {
            $mime = guess_mime_by_ext($ext);
        }

        $object_path  = $user_uuid . '/profile-' . time() . '.' . $ext;
        $encoded_path = str_replace('%2F', '/', rawurlencode($object_path));
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
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false)  throw new RuntimeException('Upload cURL error: ' . $cerr);
        if ($http >= 400)     throw new RuntimeException('Upload failed (HTTP ' . $http . '): ' . (is_string($resp) ? substr($resp, 0, 400) : ''));

        $new_public_image_url = $SUPABASE_URL . '/storage/v1/object/public/' . $BUCKET . '/' . $object_path;
    } catch (Throwable $e) {
        error_log('[PROFILE/IMAGE UPLOAD] ' . $e->getMessage());
        out_err('Image upload failed. See server log for details.', ['code' => 'UPLOAD_FAILED']);
    }
}

/* ---------------- Create a profile update request (do not change users table) ---------------- */
try {
    // Prevent multiple pending requests for the same user
    $st = $pdo->prepare("SELECT COUNT(*) FROM public.profile_update_requests WHERE user_id = :uid AND lower(status) = 'pending'");
    $st->execute([':uid' => $user_uuid]);
    $pendingCount = (int)$st->fetchColumn();
    if ($pendingCount > 0) {
        out_err('A pending profile update request already exists.', ['code' => 'PENDING_EXISTS']);
    }

    // If the email is being changed, ensure it's not already used by another user
    $currentEmail = trim((string)($current['email'] ?? ''));
    if ($email !== '' && strcasecmp($email, $currentEmail) !== 0) {
        $st = $pdo->prepare("SELECT 1 FROM public.users WHERE email = :email AND user_id != :uid LIMIT 1");
        $st->execute([':email' => $email, ':uid' => $user_uuid]);
        if ($st->fetchColumn()) {
            out_err('Email already in use.', ['code' => 'EMAIL_IN_USE']);
        }
    }

    $sql = "
        INSERT INTO public.profile_update_requests
            (user_id, image, first_name, last_name, age, email, department, phone, password)
        VALUES
            (:uid, :image, :first_name, :last_name, :age, :email, :department, :phone, :password)
        RETURNING reqpro_id, user_id, image, first_name, last_name, age, email, department, phone, status, created_at
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':uid' => $user_uuid,
        ':image' => $new_public_image_url !== null ? $new_public_image_url : null,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':age' => $age,
        ':email' => $email !== '' ? $email : null,
        ':department' => $department !== '' ? $department : null,
        ':phone' => $phone !== '' ? $phone : null,
        ':password' => $password_hash,
    ]);

    $inserted = $st->fetch(PDO::FETCH_ASSOC);
    if (!$inserted) {
        out_err('Failed creating request.', ['code' => 'DB_ERROR']);
    }

    // Create an admin notification pointing to this profile update request
    try {
        $reqpro_id = $inserted['reqpro_id'] ?? null;
        $notifyFrom = $user_uuid;
        // Prefer the submitted department; fall back to current user's department
        $dept = $department !== '' ? $department : (string)($current['current_department'] ?? '');
        $actorName = trim((string)($current['current_first_name'] ?? '')) ?: trim((string)($first_name ?? 'User'));
        $deptLabel = $dept !== '' ? $dept : 'Unknown';
        $message = sprintf('%s department, %s requested to update their profile.', $deptLabel, $actorName);

        $nst = $pdo->prepare('INSERT INTO public.notifications (message, "from", "to", reqpro_id) VALUES (:message, :from, :to, :reqpro_id)');
        $nst->execute([
            ':message' => $message,
            ':from' => $notifyFrom,
            ':to' => 'Cenro',
            ':reqpro_id' => $reqpro_id,
        ]);
    } catch (Throwable $ne) {
        // Log notification error but don't fail the main request
        error_log('[PROFILE/NOTIF CREATE] ' . $ne->getMessage());
    }

    out_ok(['request' => $inserted]);
} catch (Throwable $e) {
    error_log('[PROFILE/REQUEST CREATE] ' . $e->getMessage());
    out_err('Server error.', ['code' => 'DB_ERROR']);
}
