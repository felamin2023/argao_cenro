<?php
// backend/users/report_incident.php
declare(strict_types=1);

session_start();

/* ---------- JSON-only output & robust error handling ---------- */
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

/* ---------- Gate: logged-in User role only ---------- */
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'code' => 'unauthorized']);
    exit;
}

/* ---------- Env & DB ---------- */
require_once dirname(__DIR__) . '/bootstrap_env.php';
require_once dirname(__DIR__) . '/connection.php'; // must expose $pdo (PDO to Supabase PG)

/* ---------- Config ---------- */
$SUPABASE_URL  = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? $_SERVER['SUPABASE_URL'] ?? ''), '/');
$SERVICE_KEY   = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ($_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] ?? '');
$BUCKET        = 'incident_report';
$BUCKET_PUBLIC = filter_var(getenv('SUPABASE_STORAGE_PUBLIC') ?: 'false', FILTER_VALIDATE_BOOLEAN); // keep false (private)
if (!$SUPABASE_URL || !$SERVICE_KEY) {
    echo json_encode(['success' => false, 'message' => 'Missing SUPABASE_URL or SERVICE_ROLE key', 'code' => 'env_missing']);
    exit;
}

/* ---------- Helpers ---------- */
function is_uuid(string $s): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s);
}
function uuidv4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function sanitize_filename(string $n): string
{
    return trim(preg_replace('/[^\w.\-]+/u', '_', $n), '_');
}
function storage_public_url(string $base, string $bucket, string $path): string
{
    return rtrim($base, '/') . "/storage/v1/object/public/{$bucket}/" . ltrim($path, '/');
}
/** returns [ok(bool), code(int), body(string), err(string)] */
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
            'Authorization: Bearer ' . $service,   // service-role; server-only
            'apikey: ' . $service,
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

/* ---------- Inputs ---------- */
$user_id  = (string)$_SESSION['user_id'];
$who      = trim($_POST['who'] ?? '');
$where    = trim($_POST['where'] ?? '');
$contact  = trim($_POST['contact'] ?? '');
$whenIn   = trim($_POST['when'] ?? '');
$why      = trim($_POST['why'] ?? '');
$what     = trim($_POST['what'] ?? '');
$category = trim($_POST['categories'] ?? '');
$desc     = trim($_POST['description'] ?? ''); // form field is "description" but DB column is more_description

/* Build an echo payload we’ll include on every response */
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

/* ---------- Quick validation ---------- */
if (!$who || !$where || !$contact || !$whenIn || !$why || !$what || !$category || !$desc) {
    respond_err('Please fill out all required fields.', 'validation');
}
if (!is_uuid($user_id)) {
    respond_err('Session user_id is not a valid UUID.', 'bad_user_id');
}
$chk = $pdo->prepare("select 1 from public.users where user_id = :id limit 1");
$chk->execute([':id' => $user_id]);
if (!$chk->fetchColumn()) {
    respond_err('Session user_id not found in public.users (FK would fail).', 'fk_missing_user');
}

/* ---------- Parse datetime as Asia/Manila -> ISO 8601 ---------- */
try {
    $dt = new DateTime($whenIn, new DateTimeZone('Asia/Manila'));
    $whenIso = $dt->format('c'); // timestamptz-friendly
} catch (Throwable $e) {
    respond_err('Invalid date/time.', 'bad_datetime', ['error' => $e->getMessage()]);
}

/* ---------- Files (max 5) ---------- */
if (!isset($_FILES['photos'])) respond_err('Please attach photos (up to 5).', 'no_files');
$F = $_FILES['photos'];
$cnt = is_array($F['name']) ? count($F['name']) : 0;
if ($cnt < 1) respond_err('Please attach at least one photo.', 'no_files_count');
if ($cnt > 5) $cnt = 5;
for ($i = 0; $i < $cnt; $i++) {
    $err = (int)($F['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) respond_err("Upload failed for one file (PHP error {$err}).", 'php_upload_error', ['file_index' => $i]);
}

/* ---------- Upload to Storage ---------- */
$incident_id = uuidv4();
$prefix = "{$user_id}/{$incident_id}";
$urlsOrPaths = [];

for ($i = 0; $i < $cnt; $i++) {
    $tmp  = $F['tmp_name'][$i];
    $name = sanitize_filename((string)$F['name'][$i]);
    $size = (int)$F['size'][$i];
    if ($size <= 0) respond_err('One uploaded file is empty.', 'empty_file', ['file_index' => $i]);
    if ($size > 10 * 1024 * 1024) respond_err('One image is too large (max 10MB).', 'too_large', ['file_index' => $i, 'size' => $size]);

    $mime = mime_content_type($tmp) ?: 'application/octet-stream';
    if (!preg_match('/^(image\/(jpeg|png|webp|gif))$/i', $mime)) {
        respond_err('Only jpeg/png/webp/gif allowed.', 'bad_type', ['file_index' => $i, 'mime' => $mime]);
    }

    $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: (explode('/', $mime)[1] ?? 'bin');
    $fname = sprintf('%s_%02d.%s', time(), $i + 1, $ext);
    $path  = "{$prefix}/{$fname}";
    $bin   = file_get_contents($tmp);
    if ($bin === false) respond_err('Failed to read file.', 'read_fail', ['file_index' => $i]);

    [$ok, $http, $body, $cerr] = storage_upload($SUPABASE_URL, $SERVICE_KEY, $BUCKET, $path, $bin, $mime, false);
    if (!$ok) {
        respond_err('Failed to upload one of the images.', 'storage_upload_failed', [
            'file_index' => $i,
            'http_code' => $http,
            'storage_body' => $body,
            'curl_error' => $cerr
        ]);
    }
    $urlsOrPaths[] = $BUCKET_PUBLIC ? storage_public_url($SUPABASE_URL, $BUCKET, $path) : $path;
}

/* ---------- Insert into DB (photos is jsonb, description column is more_description) ---------- */
$photos_json = json_encode($urlsOrPaths, JSON_UNESCAPED_SLASHES);

try {
    $sql = "insert into public.incident_report
          (incident_id,user_id,\"who\",\"what\",\"where\",\"when\",\"why\",
           contact_no,photos,category,more_description,status)
          values
          (:incident_id,:user_id,:who,:what,:where,:when,:why,
           :contact, CAST(:photos AS jsonb), :category, :description, :status)
          returning id, incident_id";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':incident_id' => $incident_id,
        ':user_id' => $user_id,
        ':who' => $who,
        ':what' => $what,
        ':where' => $where,
        ':when' => $whenIso,
        ':why' => $why,
        ':contact' => $contact,
        ':photos' => $photos_json,
        ':category' => $category,
        ':description' => $desc,
        ':status' => 'pending'
    ]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    respond_ok(['message' => 'Incident submitted successfully.', 'data' => $row, 'photos_saved' => $urlsOrPaths]);
} catch (PDOException $e) {
    $sqlstate = $e->getCode();                 // e.g., 42703 undefined column, 23503 FK, 23502 NOT NULL
    $detail   = $e->errorInfo[2] ?? '';
    $constraint = null;
    if ($detail && preg_match('/constraint "([^"]+)"/i', $detail, $m)) $constraint = $m[1];
    error_log('[REPORT-INCIDENT][DB] ' . $sqlstate . ' :: ' . $detail);
    respond_err('Failed to save incident.', 'db_error', [
        'sqlstate' => $sqlstate,
        'constraint' => $constraint,
        'detail' => $detail
    ]);
}
