<?php
// File: backend/users/seedlings/request_seedlings.php
// Purpose: Generate letter -> upload to Supabase Storage bucket "requirements"
//          -> save URL/path to requirements.application_form
<<<<<<< HEAD
//          -> insert client, seedling_requests, approval rows
//          -> insert a summary notification linked to the first approval

declare(strict_types=1);

/* Force JSON-only responses & trap all PHP output/errors */
ob_start();
ini_set('display_errors', '0'); // never print HTML errors to client
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function ($severity, $message, $file, $line) {
    // turn all warnings/notices into exceptions we can JSONify
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $fatal = error_get_last();
    if ($fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // wipe any stray output and return JSON
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal server error. Check logs.']);
    }
});

session_start();
=======
//          -> insert seedling_requests (one per seedling) and approval rows.

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

/* ────────────────────────────────────────────────────────────────
   Composer autoload + .env (same pattern as register.php)
   ──────────────────────────────────────────────────────────────── */
try {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php'; // project root/vendor
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Autoload failed']);
    exit;
}

$ENV_ROOT = dirname(__DIR__, 2); // -> backend/
try {
    if (class_exists(Dotenv\Dotenv::class) && is_file($ENV_ROOT . '/.env')) {
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[SEED-REQ] Dotenv load error: ' . $e->getMessage());
}
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
>>>>>>> origin/main

/* ────────────────────────────────────────────────────────────────
   Helpers
   ──────────────────────────────────────────────────────────────── */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool
    {
        return 0 === strncmp($h, $n, strlen($n));
    }
}
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function out(bool $ok, string $err = '', array $extra = []): void
{
<<<<<<< HEAD
    $payload = ['success' => $ok] + ($err ? ['error' => $err] : []) + $extra;
    // drop any buffered echoes/notices to avoid corrupting JSON
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
=======
    echo json_encode(['success' => $ok] + ($err ? ['error' => $err] : []) + $extra);
>>>>>>> origin/main
    exit;
}
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}
function b64_from_data_url(?string $s): string
{
    $s = (string)$s;
    if ($s === '') return '';
    if (str_starts_with($s, 'data:image/')) {
        $parts = explode(',', $s, 2);
        $s = $parts[1] ?? '';
    }
    return preg_replace('/\s+/', '', $s) ?? '';
}
function env_get(string $k, ?string $def = null): ?string
{
    $v = getenv($k);
<<<<<<< HEAD
    if ($v === false || $v === null || $v === '') {
        $v = $_ENV[$k] ?? $_SERVER[$k] ?? null;
    }
    return ($v !== null && $v !== '') ? (string)$v : $def;
=======
    if ($v === false || $v === null || $v === '') $v = $_ENV[$k] ?? $_SERVER[$k] ?? null;
    return $v !== null && $v !== '' ? (string)$v : $def;
>>>>>>> origin/main
}
function encode_storage_path(string $path): string
{
    $parts = array_map('rawurlencode', array_filter(explode('/', $path), fn($p) => $p !== ''));
    return implode('/', $parts);
}
<<<<<<< HEAD
function load_env_file_if_needed(): void
{
    // Try to load backend/.env if core vars are missing
    $need = (!env_get('SUPABASE_URL') || !env_get('SUPABASE_SERVICE_ROLE_KEY'));
    if (!$need) return;

    $ENV_ROOT = dirname(__DIR__, 2); // -> backend/
    $envFile  = $ENV_ROOT . '/.env';
    if (!is_readable($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === '') continue;
        // strip wrapping quotes
        $v = trim($v, "\"'");
        if (getenv($k) === false) putenv("$k=$v");
        $_ENV[$k]    = $_ENV[$k]    ?? $v;
        $_SERVER[$k] = $_SERVER[$k] ?? $v;
    }
}
=======
>>>>>>> origin/main

/* ────────────────────────────────────────────────────────────────
   Auth + config
   ──────────────────────────────────────────────────────────────── */
try {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        out(false, 'Not authenticated');
    }
    $user_id = (string)$_SESSION['user_id'];

<<<<<<< HEAD
    // Load env from backend/.env if needed (no Composer)
    load_env_file_if_needed();

=======
>>>>>>> origin/main
    $SUPABASE_URL = rtrim((string)env_get('SUPABASE_URL', ''), '/');
    $SUPABASE_SERVICE_ROLE_KEY = (string)env_get('SUPABASE_SERVICE_ROLE_KEY', '');
    if (!$SUPABASE_URL || !$SUPABASE_SERVICE_ROLE_KEY) {
        throw new RuntimeException('Supabase credentials are not configured on the server.');
    }

    $STORAGE_BUCKET   = env_get('SUPABASE_REQUIREMENTS_BUCKET', 'requirements');
    $BUCKET_IS_PUBLIC = strtolower(env_get('SUPABASE_REQUIREMENTS_PUBLIC', 'true')) === 'true';

<<<<<<< HEAD
    require_once dirname(__DIR__, 2) . '/connection.php'; // must set $pdo (PDO to Supabase PG)
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        throw new RuntimeException('Database connection not initialized.');
    }
=======
    require_once dirname(__DIR__, 2) . '/connection.php'; // -> $pdo
>>>>>>> origin/main

    /* ────────────────────────────────────────────────────────────
       Input
       ──────────────────────────────────────────────────────────── */
    $in            = get_json_body();
    $first         = trim($in['first_name'] ?? '');
    $middle        = trim($in['middle_name'] ?? '');
    $last          = trim($in['last_name'] ?? '');
    $org           = trim($in['organization'] ?? '');
    $purpose       = trim($in['purpose'] ?? '');
    $sitio         = trim($in['sitio_street'] ?? '');
    $barangay      = trim($in['barangay'] ?? '');
    $municipality  = trim($in['municipality'] ?? '');
    $city          = trim($in['city'] ?? '');
    $request_date  = trim($in['request_date'] ?? '');
    $sig_b64       = b64_from_data_url($in['signature_b64'] ?? '');
    $seedlings     = $in['seedlings'] ?? [];

    if (!$first || !$last) out(false, 'First and last name are required.');
    if (!$purpose)         out(false, 'Purpose is required.');
    if (!$request_date)    out(false, 'Request date is required.');
    if (!$sig_b64)         out(false, 'Signature is required.');

    if (!is_array($seedlings)) $seedlings = [];
    $seedlings = array_values(array_filter(array_map(function ($r) {
        $sid = trim((string)($r['seedlings_id'] ?? ''));
        $qty = (int)($r['qty'] ?? 0);
        return ($sid && $qty > 0) ? ['seedlings_id' => $sid, 'qty' => $qty] : null;
    }, $seedlings)));
    if (!count($seedlings)) out(false, 'Add at least one seedling with a valid quantity.');

    /* ────────────────────────────────────────────────────────────
       TX begin
       ──────────────────────────────────────────────────────────── */
    $pdo->beginTransaction();

<<<<<<< HEAD
    /* 1) ALWAYS create a fresh client row */
=======
    /* ────────────────────────────────────────────────────────────
       1) ALWAYS create a fresh client row (no update/upsert)
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $stmt = $pdo->prepare('
        INSERT INTO public.client
            (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city)
        VALUES
            (:uid, :first, :middle, :last, :sitio, :brgy, :muni, :city)
        RETURNING client_id
    ');
    $stmt->execute([
        ':uid'   => $user_id,
        ':first' => $first,
<<<<<<< HEAD
        ':middle'=> $middle ?: null,
=======
        ':middle' => $middle ?: null,
>>>>>>> origin/main
        ':last'  => $last,
        ':sitio' => $sitio ?: null,
        ':brgy'  => $barangay ?: null,
        ':muni'  => $municipality ?: null,
        ':city'  => $city ?: null,
    ]);
    $client_id = (string)$stmt->fetchColumn();

<<<<<<< HEAD
    /* 2) Validate seedlings exist */
=======
    /* ────────────────────────────────────────────────────────────
       2) Validate seedlings exist
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $ids = array_map(fn($r) => $r['seedlings_id'], $seedlings);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT seedlings_id, seedling_name, stock FROM public.seedlings WHERE seedlings_id IN ($ph)");
    $stmt->execute($ids);
    $catalog = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $catalog[$row['seedlings_id']] = $row;
    }
    foreach ($seedlings as $r) {
        if (!isset($catalog[$r['seedlings_id']])) {
            throw new RuntimeException('One or more selected seedlings do not exist.');
        }
    }

<<<<<<< HEAD
    /* 3) Build letter HTML → MHTML */
=======
    /* ────────────────────────────────────────────────────────────
       3) Build letter HTML → MHTML
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $lgu = $city ?: $municipality;
    $addr = [];
    if ($sitio)    $addr[] = $sitio;
    if ($barangay) $addr[] = 'Brgy. ' . $barangay;
    if ($lgu)      $addr[] = $lgu;
    $addressLine = implode(', ', $addr);
    $cityProv    = ($lgu ? ($lgu . ', ') : '') . 'Cebu';
    $fullName    = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
    $prettyDate  = date('F j, Y', strtotime($request_date));

    $totalQty = 0;
    $seedTxts = [];
    foreach ($seedlings as $r) {
        $nm = $catalog[$r['seedlings_id']]['seedling_name'] ?? 'Seedling';
        $seedTxts[] = h($nm) . ' (' . (int)$r['qty'] . ')';
        $totalQty += (int)$r['qty'];
    }
    $seedTxt = implode(', ', $seedTxts);

    $inner = '
        <p style="text-align:right;">' . h($addressLine) . '<br>' . h($cityProv) . '<br>' . h($prettyDate) . '</p>
        <p><strong>CENRO Argao</strong></p>
        <p><strong>Subject: Request for Seedlings</strong></p>
        <p>Dear Sir/Madam,</p>
        <p style="text-align:justify;text-indent:50px;">
            I am writing to formally request ' . $totalQty . ' seedlings of ' . $seedTxt . ' for ' . h($purpose) . '.
            The seedlings will be planted at ' . h($addressLine ?: $cityProv) . '.
        </p>
        <p style="text-align:justify;text-indent:50px;">The purpose of this request is ' . h($purpose) . '.</p>
        <p style="text-align:justify;text-indent:50px;">
            I would be grateful if you could approve this request at your earliest convenience. Please let me know if you require any additional information or documentation to process this request.
        </p>
        <p>Thank you for your time and consideration.</p>
        <p>Sincerely,<br><br>
            <img src="cid:sigimg" width="140" height="25" style="height:auto;border:1px solid #ccc;"><br>
            ' . h($fullName) . '<br>' . h($addressLine ?: $cityProv) . '<br>' . h($org) . '
        </p>
    ';

    $htmlDoc = '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Seedling Request Letter</title>
<style>body{font-family:Arial,sans-serif;line-height:1.6;margin:50px;color:#111}</style>
</head><body>' . $inner . '</body></html>';

    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml  = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\n";
    $mhtml .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
    $mhtml .= "Content-Transfer-Encoding: 8bit\r\n";
    $mhtml .= "Content-Location: file:///index.html\r\n\r\n";
    $mhtml .= $htmlDoc . "\r\n\r\n";
    $mhtml .= "--$boundary\r\n";
    $mhtml .= "Content-Type: image/png\r\n";
    $mhtml .= "Content-Transfer-Encoding: base64\r\n";
    $mhtml .= "Content-ID: <sigimg>\r\n";
    $mhtml .= "Content-Location: file:///sig.png\r\n\r\n";
    $mhtml .= chunk_split($sig_b64, 76, "\r\n");
    $mhtml .= "\r\n--$boundary--";

<<<<<<< HEAD
    /* 4) Upload to Supabase Storage (requirements/seedling/{client_id}/...) */
=======
    /* ────────────────────────────────────────────────────────────
       4) Upload to Supabase Storage (requirements/seedling/{client_id}/...)
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $fname = sprintf(
        'Seedling_Request_%s_%s_%s.doc',
        preg_replace('/[^A-Za-z0-9]+/', '_', $last ?: 'Letter'),
        date('Ymd', strtotime($request_date)),
        substr($client_id, 0, 8)
    );
    $objectPath = 'seedling/' . $client_id . '/' . $fname;

    $uploadUrl = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($STORAGE_BUCKET) . '/' . encode_storage_path($objectPath);
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . $SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/msword',
        ],
        CURLOPT_POSTFIELDS     => $mhtml,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $http >= 300) {
        $err = $resp ?: curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Storage upload failed (' . $http . '): ' . $err);
    }
    curl_close($ch);

<<<<<<< HEAD
    $signedUrl = null;
    if ($BUCKET_IS_PUBLIC) {
        $fileUrl = $SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode($STORAGE_BUCKET) . '/' . encode_storage_path($objectPath);
=======
    if ($BUCKET_IS_PUBLIC) {
        $fileUrl   = $SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode($STORAGE_BUCKET) . '/' . encode_storage_path($objectPath);
        $signedUrl = null;
>>>>>>> origin/main
    } else {
        $fileUrl = $STORAGE_BUCKET . '/' . $objectPath;
        $signEndpoint = $SUPABASE_URL . '/storage/v1/object/sign/' . rawurlencode($STORAGE_BUCKET);
        $signBody     = json_encode(['expiresIn' => 60 * 60 * 24 * 7, 'paths' => [$objectPath]]);
        $ch = curl_init($signEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
                'apikey: ' . $SUPABASE_SERVICE_ROLE_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $signBody,
        ]);
        $signResp = curl_exec($ch);
        $http2    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
<<<<<<< HEAD
=======
        $signedUrl = null;
>>>>>>> origin/main
        if ($http2 < 300 && $signResp) {
            $jr = json_decode($signResp, true);
            if (isset($jr['signedUrls'][0]['signedUrl'])) $signedUrl = $jr['signedUrls'][0]['signedUrl'];
            elseif (isset($jr['signedUrl'])) $signedUrl = $jr['signedUrl'];
        }
    }

<<<<<<< HEAD
    /* 5) requirements row */
=======
    /* ────────────────────────────────────────────────────────────
       5) requirements row (only application_form set)
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $stmt = $pdo->prepare('INSERT INTO public.requirements (application_form) VALUES (:f) RETURNING requirement_id');
    $stmt->execute([':f' => $fileUrl]);
    $requirement_id = (string)$stmt->fetchColumn();

<<<<<<< HEAD
    /* 6) seedling_requests rows (one per seedling) */
=======
    /* ────────────────────────────────────────────────────────────
       6) seedling_requests rows (one per seedling)
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $insSeed = $pdo->prepare('
        INSERT INTO public.seedling_requests (client_id, seedlings_id, quantity)
        VALUES (:cid, :sid, :qty)
        RETURNING seedl_req_id
    ');
    $seed_req_ids = [];
    foreach ($seedlings as $r) {
        $insSeed->execute([
            ':cid' => $client_id,
            ':sid' => $r['seedlings_id'],
            ':qty' => (int)$r['qty'],
        ]);
        $seed_req_ids[] = (string)$insSeed->fetchColumn();
    }

<<<<<<< HEAD
    /* 7) approval rows (one per seedling request) */
=======
    /* ────────────────────────────────────────────────────────────
       7) approval rows (one per seedling request)
       ──────────────────────────────────────────────────────────── */
>>>>>>> origin/main
    $insAppr = $pdo->prepare('
        INSERT INTO public.approval (client_id, requirement_id, request_type, submitted_at, seedl_req_id)
        VALUES (:cid, :rid, :rtype, now(), :sreq)
        RETURNING approval_id
    ');
    $approval_ids = [];
    foreach ($seed_req_ids as $sid) {
        $insAppr->execute([
            ':cid'   => $client_id,
            ':rid'   => $requirement_id,
            ':rtype' => 'seedling',
            ':sreq'  => $sid,
        ]);
        $approval_ids[] = (string)$insAppr->fetchColumn();
    }

<<<<<<< HEAD
    /* 8) one summary notification for the whole request */
    $summary = [];
    foreach ($seedlings as $r) {
        $nm  = $catalog[$r['seedlings_id']]['seedling_name'] ?? 'Seedling';
        $qty = (int)$r['qty'];
        $summary[] = "{$nm} ({$qty})";
    }
    $msg = 'Seedling request submitted: ' . implode(', ', $summary) . ' — pending review.';

    // link to the first approval (or null)
    $firstApproval = $approval_ids[0] ?? null;

    $insNotif = $pdo->prepare('
        INSERT INTO public.notifications (approval_id, message)
        VALUES (:aid, :msg)
        RETURNING notif_id
    ');
    $insNotif->execute([':aid' => $firstApproval, ':msg' => $msg]);
    $notif_ids = [(string)$insNotif->fetchColumn()];

    /* ✅ commit everything so rows persist */
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
=======
    $pdo->commit();
>>>>>>> origin/main

    out(true, '', [
        'client_id'            => $client_id,
        'requirement_id'       => $requirement_id,
        'seedling_request_ids' => $seed_req_ids,
        'approval_ids'         => $approval_ids,
<<<<<<< HEAD
        'notification_ids'     => $notif_ids,
=======
>>>>>>> origin/main
        'application_form_url' => $fileUrl,
        'signed_url_preview'   => $signedUrl,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    out(false, $e->getMessage());
}
