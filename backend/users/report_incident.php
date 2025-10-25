<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(function ($sev, $msg, $file, $line) {
    throw new ErrorException($msg, 0, $sev, $file, $line);
});
set_exception_handler(function ($e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'php_exception', 'message' => $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'code' => 'php_fatal', 'message' => $err['message']]);
    }
});
ob_start();

if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'code' => 'unauthorized']);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap_env.php';
require_once dirname(__DIR__) . '/connection.php';

$SUPABASE_URL  = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? $_SERVER['SUPABASE_URL'] ?? ''), '/');
$SERVICE_KEY   = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ($_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] ?? '');
$BUCKET        = 'incident_report';
$BUCKET_PUBLIC = filter_var(getenv('SUPABASE_STORAGE_PUBLIC') ?: 'false', FILTER_VALIDATE_BOOLEAN);
if (!$SUPABASE_URL || !$SERVICE_KEY) {
    echo json_encode(['success' => false, 'message' => 'Missing SUPABASE_URL or SERVICE_ROLE key', 'code' => 'env_missing']);
    exit;
}

function is_uuid(string $s): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s);
}
function sanitize_filename(string $n): string
{
    return trim(preg_replace('/[^\w.\-]+/u', '_', $n), '_');
}
function storage_public_url(string $base, string $bucket, string $path): string
{
    return rtrim($base, '/') . "/storage/v1/object/public/{$bucket}/" . ltrim($path, '/');
}
function storage_upload(string $base, string $service, string $bucket, string $path, string $bin, string $mime, bool $upsert = false): array
{
    $url = rtrim($base, '/') . "/storage/v1/object/{$bucket}/" . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: ' . $mime,
            'x-upsert: ' . ($upsert ? 'true' : 'false'),
            'Authorization: Bearer ' . $service,
            'apikey' => $service,
        ],
        CURLOPT_POSTFIELDS     => $bin,
        CURLOPT_TIMEOUT        => 45,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $ok = ($body !== false) && $code >= 200 && $code < 300;
    return [$ok, $code, (string)$body, (string)$err];
}

$user_id  = (string)$_SESSION['user_id'];
$who      = trim($_POST['who'] ?? '');
$where    = trim($_POST['where'] ?? '');
$contact  = trim($_POST['contact'] ?? '');
$whenIn   = trim($_POST['when'] ?? '');
$why      = trim($_POST['why'] ?? '');
$what     = trim($_POST['what'] ?? '');
$category = trim($_POST['categories'] ?? '');
$desc     = trim($_POST['description'] ?? '');

$filesEcho = [];
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $N = count($_FILES['photos']['name']);
    for ($i = 0; $i < $N; $i++) {
        $filesEcho[] = [
            'name'  => (string)($_FILES['photos']['name'][$i] ?? ''),
            'size'  => (int)($_FILES['photos']['size'][$i] ?? 0),
            'type'  => (string)($_FILES['photos']['type'][$i] ?? ''),
            'error' => (int)($_FILES['photos']['error'][$i] ?? -1),
        ];
    }
}
$ECHO = [
    'user_id'        => $user_id,
    'who'            => $who,
    'what'           => $what,
    'where'          => $where,
    'when_raw'       => $whenIn,
    'why'            => $why,
    'contact_no'     => $contact,
    'category'       => $category,
    'description'    => $desc,
    'files'          => $filesEcho,
];
function respond_ok(array $payload = [])
{
    global $ECHO;
    echo json_encode(['success' => true, 'echo' => $ECHO] + $payload);
    exit;
}
function respond_err(string $msg, string $code, array $extra = [])
{
    global $ECHO;
    echo json_encode(['success' => false, 'message' => $msg, 'code' => $code, 'echo' => $ECHO] + $extra);
    exit;
}

if (!$who || !$where || !$contact || !$whenIn || !$why || !$what || !$category || !$desc) respond_err('Please fill out all required fields.', 'validation');
if (!is_uuid($user_id)) respond_err('Session user_id is not a valid UUID.', 'bad_user_id');

$chk = $pdo->prepare("select 1 from public.users where user_id = :id limit 1");
$chk->execute([':id' => $user_id]);
if (!$chk->fetchColumn()) respond_err('Session user_id not found in public.users (FK would fail).', 'fk_missing_user');

try {
    $dt = new DateTime($whenIn, new DateTimeZone('Asia/Manila'));
    $whenIso = $dt->format('c');
} catch (Throwable $e) {
    respond_err('Invalid date/time.', 'bad_datetime', ['error' => $e->getMessage()]);
}

if (!isset($_FILES['photos'])) respond_err('Please attach photos (up to 5).', 'no_files');
$F = $_FILES['photos'];
$cnt = is_array($F['name']) ? count($F['name']) : 0;
if ($cnt < 1) respond_err('Please attach at least one photo.', 'no_files_count');
if ($cnt > 5) $cnt = 5;
for ($i = 0; $i < $cnt; $i++) {
    $err = (int)($F['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) respond_err("Upload failed for one file (PHP error {$err}).", 'php_upload_error', ['file_index' => $i]);
}

$urlsOrPaths = [];
$incident_id = null;

try {
    // Display name fallback: first_name → last_name → email local-part → "User"
    $getName = $pdo->prepare("
        SELECT COALESCE(
            NULLIF(btrim(first_name), ''),
            NULLIF(btrim(last_name), ''),
            NULLIF(btrim(split_part(email, '@', 1)), ''),
            'User'
        ) AS display_name
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $getName->execute([':id' => $user_id]);
    $displayName = (string)($getName->fetchColumn() ?: 'User');

    // Begin transaction
    $pdo->beginTransaction();

    // Insert incident (DB generates incident_id like 'icdt001')
    $ins = $pdo->prepare("
        INSERT INTO public.incident_report
            (user_id, \"who\", \"what\", \"where\", \"when\", \"why\",
             contact_no, category, more_description, status)
        VALUES
            (:user_id, :who, :what, :where, :when, :why,
             :contact, :category, :description, :status)
        RETURNING id, incident_id
    ");
    $ins->execute([
        ':user_id'     => $user_id,
        ':who'         => $who,
        ':what'        => $what,
        ':where'       => $where,
        ':when'        => $whenIso,
        ':why'         => $why,
        ':contact'     => $contact,
        ':category'    => $category,
        ':description' => $desc,
        ':status'      => 'pending',
    ]);
    $row = $ins->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['incident_id'])) throw new RuntimeException('Failed to get generated incident_id.');
    $incident_id = (string)$row['incident_id'];

    // Upload files under {user_id}/{incident_id}
    $prefix = "{$user_id}/{$incident_id}";
    for ($i = 0; $i < $cnt; $i++) {
        $tmp  = $F['tmp_name'][$i];
        $name = sanitize_filename((string)$F['name'][$i]);
        $size = (int)$F['size'][$i];
        if ($size <= 0) throw new RuntimeException('One uploaded file is empty.');
        if ($size > 10 * 1024 * 1024) throw new RuntimeException('One image is too large (max 10MB).');

        $mime = mime_content_type($tmp) ?: 'application/octet-stream';
        if (!preg_match('/^(image\/(jpeg|png|webp|gif))$/i', $mime)) {
            throw new RuntimeException("Only jpeg/png/webp/gif allowed. (got {$mime})");
        }

        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: (explode('/', $mime)[1] ?? 'bin');
        $fname = sprintf('%s_%02d.%s', time(), $i + 1, $ext);
        $path  = "{$prefix}/{$fname}";
        $bin   = file_get_contents($tmp);
        if ($bin === false) throw new RuntimeException('Failed to read one uploaded file.');

        [$ok, $http, $body, $cerr] = storage_upload($SUPABASE_URL, $SERVICE_KEY, $BUCKET, $path, $bin, $mime, false);
        if (!$ok) throw new RuntimeException("Storage upload failed (HTTP {$http}) {$cerr} {$body}");
        $urlsOrPaths[] = $BUCKET_PUBLIC ? storage_public_url($SUPABASE_URL, $BUCKET, $path) : $path;
    }

    // Update incident photos JSON
    $photos_json = json_encode($urlsOrPaths, JSON_UNESCAPED_SLASHES);
    $upd = $pdo->prepare("UPDATE public.incident_report SET photos = CAST(:photos AS jsonb) WHERE incident_id = :incident_id");
    $upd->execute([':photos' => $photos_json, ':incident_id' => $incident_id]);

    // Insert notification (approval_id NULL; message with display name)
    $notifMsg = sprintf('%s reported an incident', $displayName);
    $insN = $pdo->prepare('
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, created_at, "from", "to")
        VALUES (NULL, :incident_id, :message, FALSE, now(), :from, :to)
        RETURNING notif_id
    ');

    // determine "from" and "to" values for the notification
    $notif_from = $user_id;
    $catLower = strtolower(trim((string)$category));
    if (strpos($catLower, 'tree') !== false) {
        $notif_to = 'Tree Cutting';
    } elseif (strpos($catLower, 'marine') !== false) {
        $notif_to = 'Marine';
    } elseif (strpos($catLower, 'seedl') !== false || strpos($catLower, 'seedling') !== false) {
        $notif_to = 'Seedling';
    } elseif (strpos($catLower, 'wild') !== false) {
        $notif_to = 'Wildlife';
    } else {
        // fallback: use the raw category string if present, otherwise 'cenro'
        $notif_to = $category !== '' ? $category : 'cenro';
    }

    $insN->execute([
        ':incident_id' => $incident_id,
        ':message'     => $notifMsg,
        ':from'        => $notif_from,
        ':to'          => $notif_to,
    ]);
    $notif = $insN->fetch(PDO::FETCH_ASSOC);

    // Commit
    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'message'      => 'Incident submitted successfully.',
        'data'         => [
            'id'          => $row['id'] ?? null,
            'incident_id' => $incident_id,
            'notif_id'    => $notif['notif_id'] ?? null,
        ],
        'photos_saved' => $urlsOrPaths,
        'echo'         => $ECHO
    ]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[REPORT-INCIDENT][DB] ' . $e->getMessage());
    respond_err('Failed to save incident.', 'db_or_upload_error', ['detail' => $e->getMessage(), 'incident_id' => $incident_id]);
}
