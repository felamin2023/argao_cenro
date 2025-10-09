<?php

/**
 * seedlingpermit.php (Seedling Requests Admin UI)
 * Server/PHP “top” section only (everything before <!DOCTYPE html>)
 * - Auth guard (admin + seedling dept)
 * - Supabase helpers (uses ENV or constants from connection.php)
 * - MHTML builder with circular APPROVED badge
 * - AJAX endpoints:
 *      • mark_read                – mark one notif (by notif_id or incident_id)
 *      • mark_all_read            – mark all seedling+incident notifs as read
 *      • details                  – returns meta, files, and requested_seeds text
 *      • decide                   – approve/reject (on approve regenerates & overwrites doc)
 *      • mark_notifs_for_approval – mark ONLY seedling-targeted notifs for this approval
 */

declare(strict_types=1);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php';

$user_id = (string)$_SESSION['user_id'];
try {
    $st = $pdo->prepare("
        SELECT first_name, last_name, email, role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin = $me && strtolower((string)$me['role']) === 'admin';
    $dept    = strtolower((string)($me['department'] ?? ''));
    $isSeed  = in_array($dept, ['seedling', 'seedlings', 'nursery', 'seedling section'], true);

    if (!$isAdmin || !$isSeed) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SEEDLING-ADMIN GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function notempty($v): bool
{
    return $v !== null && trim((string)$v) !== '' && $v !== 'null';
}
function time_elapsed_string($datetime, $full = false): string
{
    if (!$datetime) return '';
    $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago  = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);
    $weeks = (int)floor($diff->d / 7);
    $days  = $diff->d % 7;
    $map   = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = [];
    foreach ($map as $k => $label) {
        $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
    }
    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

$FILE_BASE = '';
function normalize_url(string $v, string $base): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v)) return $v;
    if ($base !== '') {
        $base = rtrim($base, '/');
        $v = ltrim($v, '/');
        return $base . '/' . $v;
    }
    return '';
}
function is_image_url(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
}

/* ====== Supabase env + helpers + storage + builder (APPROVED) ====== */

/* env_get with constants fallback (from connection.php) */
function env_get(string $k, ?string $def = null): ?string
{
    $v = getenv($k);
    if ($v === false || $v === null || $v === '') {
        $v = $_ENV[$k] ?? $_SERVER[$k] ?? null;
    }
    if (($v === null || $v === '') && defined($k)) {
        /** @noinspection PhpConstantReassignmentInspection */
        $v = constant($k);
    }
    return ($v !== null && $v !== '') ? (string)$v : $def;
}

/* Supabase basics (try ENV first, then constants SUPABASE_URL / SUPABASE_SERVICE_KEY) */
$SUPABASE_URL = rtrim((string)env_get('SUPABASE_URL', defined('SUPABASE_URL') ? SUPABASE_URL : ''), '/');
$SUPABASE_SERVICE_ROLE_KEY = (string)env_get('SUPABASE_SERVICE_ROLE_KEY', defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : '');
$REQ_BUCKET = env_get('SUPABASE_REQUIREMENTS_BUCKET', defined('REQUIREMENTS_BUCKET') ? REQUIREMENTS_BUCKET : 'requirements');
$REQ_BUCKET_PUBLIC = strtolower((string)env_get('SUPABASE_REQUIREMENTS_PUBLIC', 'true')) === 'true';
$SIG_BUCKET = env_get('SUPABASE_SIGNATURES_BUCKET', 'signatures'); // used if signature is private path

/* Ensure creds present */
function require_supabase_creds(): void
{
    global $SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY;
    if (!$SUPABASE_URL || !$SUPABASE_SERVICE_ROLE_KEY) {
        throw new RuntimeException('Supabase credentials are not configured on the server.');
    }
}

/* URL/path helpers */
function encode_storage_path(string $path): string
{
    $parts = array_map('rawurlencode', array_filter(explode('/', $path), fn($p) => $p !== ''));
    return implode('/', $parts);
}
function http_get_bytes(string $url, int $timeout = 30): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http < 200 || $http >= 300) {
        throw new RuntimeException("HTTP GET failed ($http): $err");
    }
    return (string)$resp;
}

/* Storage: upload / delete / download (private) */
function storage_upload_binary(string $bucket, string $objectPath, string $contentType, string $binary): void
{
    require_supabase_creds();
    global $SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY;

    $url = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_storage_path($objectPath);

    error_log("DEBUG: Uploading to: $bucket/$objectPath, Size: " . strlen($binary));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . $SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: ' . $contentType
        ],
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_TIMEOUT => 60,
    ]);

    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $http < 200 || $http >= 300) {
        error_log("DEBUG: Storage upload FAILED - HTTP: $http, Error: $err, Response: " . ($resp ?: 'none'));
        throw new RuntimeException("Storage upload failed ($http): " . ($resp ?: $err));
    }

    error_log("DEBUG: Storage upload SUCCESS - HTTP: $http");
}
function storage_delete_object(string $bucket, string $objectPath): void
{
    require_supabase_creds();
    global $SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY;
    $url = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_storage_path($objectPath);

    error_log("DEBUG: Deleting from: $bucket/$objectPath");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . $SUPABASE_SERVICE_ROLE_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http >= 400 && $http !== 404) {
        error_log("DEBUG: Storage delete FAILED - HTTP: $http, Response: " . (string)$resp);
        throw new RuntimeException("Storage delete failed ($http): " . (string)$resp);
    }

    error_log("DEBUG: Storage delete SUCCESS - HTTP: $http");
}
function storage_download_private(string $bucket, string $objectPath): string
{
    require_supabase_creds();
    global $SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY;
    $url = $SUPABASE_URL . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_storage_path($objectPath);

    error_log("DEBUG: Downloading from: $bucket/$objectPath");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . $SUPABASE_SERVICE_ROLE_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $http < 200 || $http >= 300) {
        error_log("DEBUG: Storage download FAILED - HTTP: $http, Error: $err");
        throw new RuntimeException("Storage download failed ($http): " . ($resp ?: $err));
    }

    error_log("DEBUG: Storage download SUCCESS - Size: " . strlen($resp));
    return (string)$resp;
}

/* Parse bucket/object from requirements.application_form */
function parse_bucket_and_path_from_appform(string $urlOrPath): array
{
    $v = trim($urlOrPath);
    if ($v === '') return ['', ''];
    if (preg_match('~^https?://[^/]+/storage/v1/object/(public/)?([^/]+)/(.+)$~i', $v, $m)) {
        $bucket = $m[2];
        $path   = $m[3];
        return [$bucket, $path];
    }
    $parts = explode('/', $v, 2);
    if (count($parts) === 2) return [$parts[0], $parts[1]];
    return ['', ''];
}

/* Build MHTML (.doc) with a circular APPROVED badge (upper-right) */
function build_seedling_letter_mhtml_approved(array $client, string $sig_b64, string $purpose, array $items, string $request_date): array
{
    $first = trim((string)($client['first_name'] ?? ''));
    $middle = trim((string)($client['middle_name'] ?? ''));
    $last = trim((string)($client['last_name'] ?? ''));
    $sitio = trim((string)($client['sitio_street'] ?? ''));
    $brgy  = trim((string)($client['barangay'] ?? ''));
    $muni  = trim((string)($client['municipality'] ?? ''));
    $city  = trim((string)($client['city'] ?? ''));
    $lgu   = $city ?: $muni;

    $addr = [];
    if ($sitio) $addr[] = $sitio;
    if ($brgy)  $addr[] = 'Brgy. ' . $brgy;
    if ($lgu)   $addr[] = $lgu;
    $addressLine = implode(', ', $addr);
    $cityProv = ($lgu ? ($lgu . ', ') : '') . 'Cebu';
    $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
    $prettyDate = $request_date ? date('F j, Y', strtotime($request_date)) : date('F j, Y');

    $totalQty = 0;
    $seedTxts = [];
    foreach ($items as $it) {
        $nm = (string)($it['seedling_name'] ?? 'Seedling');
        $q  = (int)($it['qty'] ?? 0);
        if ($q > 0) {
            $seedTxts[] = h($nm) . ' (' . $q . ')';
            $totalQty += $q;
        }
    }
    $seedTxt = $seedTxts ? implode(', ', $seedTxts) : 'seedlings';

    $inner = '
        <div style="position:fixed;top:26px;right:26px;width:120px;height:120px;border-radius:50%;
                    background:#0F2A6B;color:#fff;display:flex;align-items:center;justify-content:center;
                    font-weight:700;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 8px rgba(0,0,0,.25);">
            APPROVED
        </div>

        <p style="text-align:right;margin-top:10px;">' . h($addressLine) . '<br>' . h($cityProv) . '<br>' . h($prettyDate) . '</p>
        <p><strong>CENRO Argao</strong></p>
        <p><strong>Subject: Request for Seedlings</strong></p>
        <p>Dear Sir/Madam,</p>
        <p style="text-align:justify;text-indent:50px;">I am writing to formally request ' . $totalQty . ' seedlings of ' . $seedTxt . ' for ' . h($purpose) . '.</p>
        <p style="text-align:justify;text-indent:50px;">The seedlings will be planted at ' . h($addressLine ?: $cityProv) . '.</p>
        <p style="text-align:justify;text-indent:50px;">I would be grateful if you could approve this request at your earliest convenience.</p>
        <p>Thank you for your time and consideration.</p>
        <p>Sincerely,<br><br>
            <img src="cid:sigimg" width="140" height="25" style="height:auto;border:1px solid #ccc;"><br>
            ' . h($fullName) . '<br>' . h($addressLine ?: $cityProv) . '
        </p>
    ';

    $htmlDoc = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Seedling Request Letter (Approved)</title>
        <style>body{font-family:Arial,sans-serif;line-height:1.6;margin:50px;color:#111}</style>
        </head><body>' . $inner . '</body></html>';

    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $htmlDoc . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///sig.png\r\n\r\n";
    $mhtml .= chunk_split($sig_b64, 76, "\r\n") . "\r\n--$boundary--";
    return [$mhtml, $fullName];
}

/* Regenerate and overwrite the requirements.application_form file for a given approval */
function regenerate_and_overwrite_requirement_doc(PDO $pdo, string $approvalId): void
{
    error_log("DEBUG: Starting document regeneration for approval: $approvalId");

    // 1) Load approval → requirement_id, client_id, submitted_at
    $st = $pdo->prepare("SELECT approval_id, client_id, requirement_id, submitted_at FROM public.approval WHERE approval_id=:aid LIMIT 1");
    $st->execute([':aid' => $approvalId]);
    $ap = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ap) throw new RuntimeException('Approval not found.');

    $rid = (string)$ap['requirement_id'];
    $cid = (string)$ap['client_id'];
    $reqDate = (string)($ap['submitted_at'] ?? '');

    error_log("DEBUG: Found approval - client_id: $cid, requirement_id: $rid");

    // 2) Load requirements row to get current URL/path
    $st2 = $pdo->prepare("SELECT application_form FROM public.requirements WHERE requirement_id=:rid LIMIT 1");
    $st2->execute([':rid' => $rid]);
    $req = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$req || !trim((string)$req['application_form'])) throw new RuntimeException('Requirements row or application_form missing.');

    $appForm = (string)$req['application_form'];
    error_log("DEBUG: Application form URL: $appForm");

    [$bucket, $objectPath] = parse_bucket_and_path_from_appform($appForm);
    if ($bucket === '' || $objectPath === '') throw new RuntimeException('Could not parse bucket/path from application_form.');

    error_log("DEBUG: Parsed bucket: $bucket, objectPath: $objectPath");

    // 3) Gather all seedlings under same requirement_id (names + qty)
    $st3 = $pdo->prepare("
        SELECT s.seedling_name, COALESCE(sr.quantity,0) AS qty
        FROM public.approval a
        JOIN public.seedling_requests sr ON sr.seedl_req_id = a.seedl_req_id
        JOIN public.seedlings s        ON s.seedlings_id = sr.seedlings_id
        WHERE a.requirement_id = :rid
        ORDER BY s.seedling_name
    ");
    $st3->execute([':rid' => $rid]);
    $items = $st3->fetchAll(PDO::FETCH_ASSOC) ?: [];
    error_log("DEBUG: Found " . count($items) . " seedling items");

    // 4) Load client (+ signature)
    $st4 = $pdo->prepare("SELECT first_name,middle_name,last_name,sitio_street,barangay,municipality,city,signature FROM public.client WHERE client_id=:cid LIMIT 1");
    $st4->execute([':cid' => $cid]);
    $client = $st4->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$client) throw new RuntimeException('Client not found.');
    error_log("DEBUG: Found client: " . ($client['first_name'] ?? 'Unknown'));

    // 5) Get signature bytes → base64
    $sig = trim((string)($client['signature'] ?? ''));
    if ($sig === '') {
        error_log("ERROR: Missing signature for client_id: $cid");
        throw new RuntimeException('Client signature not found for this request.');
    }

    error_log("DEBUG: Signature path: $sig");

    $pngBytes = '';
    if (preg_match('~^https?://~i', $sig)) {
        error_log("DEBUG: Downloading signature from URL");
        $pngBytes = http_get_bytes($sig);
    } else {
        $seg = explode('/', $sig, 2);
        if (count($seg) !== 2) {
            error_log("ERROR: Invalid signature path format: $sig");
            throw new RuntimeException('Invalid signature path format.');
        }
        error_log("DEBUG: Downloading signature from storage: {$seg[0]}/{$seg[1]}");
        $pngBytes = storage_download_private($seg[0], $seg[1]);
    }

    $sig_b64 = base64_encode($pngBytes);
    error_log("DEBUG: Signature downloaded successfully, size: " . strlen($pngBytes) . " bytes");

    // 6) Purpose fallback
    $purpose = 'approved seedling request';

    // 7) Build new MHTML with APPROVED badge
    error_log("DEBUG: Building MHTML document");
    [$mhtml] = build_seedling_letter_mhtml_approved($client, $sig_b64, $purpose, $items, $reqDate);
    error_log("DEBUG: MHTML built successfully, size: " . strlen($mhtml) . " bytes");

    // 8) Replace object in storage
    error_log("DEBUG: Replacing document in storage");
    storage_delete_object($bucket, $objectPath);
    storage_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);

    error_log("DEBUG: Document regeneration completed successfully for approval: $approvalId");
}

/* ---------------- AJAX (mark single read) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_read') {
    header('Content-Type: application/json');
    $notifId    = $_POST['notif_id'] ?? '';
    $incidentId = $_POST['incident_id'] ?? '';
    if (!$notifId && !$incidentId) {
        echo json_encode(['ok' => false, 'error' => 'missing notif_id or incident_id']);
        exit();
    }
    try {
        if ($notifId)    $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE notif_id=:id")->execute([':id' => $notifId]);
        if ($incidentId) $pdo->prepare("UPDATE public.incident_report SET is_read=true WHERE incident_id=:id")->execute([':id' => $incidentId]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[SEEDLING MARK_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (MARK ALL READ) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_all_read') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $updPermits = $pdo->prepare("
            UPDATE public.notifications
               SET is_read = true
             WHERE LOWER(COALESCE(\"to\", '')) = 'seedling'
               AND is_read = false
        ");
        $updPermits->execute();
        $countPermits = $updPermits->rowCount();

        $updInc = $pdo->prepare("
            UPDATE public.incident_report
               SET is_read = true
             WHERE LOWER(COALESCE(category, '')) = 'tree cutting'
               AND is_read = false
        ");
        $updInc->execute();
        $countInc = $updInc->rowCount();

        $pdo->commit();
        echo json_encode(['ok' => true, 'updated' => ['permits' => (int)$countPermits, 'incidents' => (int)$countInc]]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[SEEDLING MARK_ALL_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (details) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'details') {
    header('Content-Type: application/json');
    $approvalId = $_GET['approval_id'] ?? '';
    if (!$approvalId) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }

    try {
        $st = $pdo->prepare("
          SELECT a.approval_id,
                 LOWER(COALESCE(a.request_type,'')) AS request_type,
                 COALESCE(NULLIF(btrim(a.permit_type),''),'none')        AS permit_type,
                 COALESCE(NULLIF(btrim(a.approval_status),''),'pending') AS approval_status,
                 a.submitted_at,
                 a.application_id,
                 a.requirement_id,
                 a.seedl_req_id,
                 c.first_name, c.last_name
          FROM public.approval a
          LEFT JOIN public.client c ON c.client_id = a.client_id
          WHERE a.approval_id = :aid
            AND LOWER(COALESCE(a.request_type,'')) IN ('seedling')
          LIMIT 1
        ");
        $st->execute([':aid' => $approvalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'not found']);
            exit;
        }

        // Requested seeds text
        $reqText = '—';
        if (!empty($row['seedl_req_id'])) {
            $sti = $pdo->prepare("
                SELECT s.seedling_name, COALESCE(sr.quantity,0) AS qty
                FROM public.seedling_requests sr
                JOIN public.seedlings s ON s.seedlings_id = sr.seedlings_id
                WHERE sr.seedl_req_id = :sid
                ORDER BY s.seedling_name
            ");
            $sti->execute([':sid' => $row['seedl_req_id']]);
            $items = $sti->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($items) {
                $parts = [];
                foreach ($items as $it) {
                    $nm = (string)$it['seedling_name'];
                    $q  = (int)$it['qty'];
                    if ($q > 0) $parts[] = $nm . ' (' . $q . ')';
                }
                if ($parts) $reqText = implode(', ', $parts);
            }
        }

        // Files list (from requirements)
        $files = [];
        if (notempty($row['requirement_id'])) {
            $st3 = $pdo->prepare("SELECT * FROM public.requirements WHERE requirement_id=:rid LIMIT 1");
            $st3->execute([':rid' => $row['requirement_id']]);
            $req = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($req as $k => $v) {
                if (in_array($k, ['id', 'requirement_id'], true)) continue;
                if (!notempty($v)) continue;
                $url = normalize_url((string)$v, $FILE_BASE);
                if ($url === '') continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $path  = parse_url($url, PHP_URL_PATH) ?? '';
                $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $files[] = ['name' => $label, 'url' => $url, 'ext' => $ext];
            }
        }

        echo json_encode([
            'ok' => true,
            'meta' => [
                'client'            => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'request_type'      => $row['request_type'] ?? '',
                'permit_type'       => $row['permit_type'] ?? '',
                'status'            => $row['approval_status'] ?? '',
                'submitted_at'      => $row['submitted_at'] ?? null,
                'requested_seeds'   => $reqText,   // 👈 added
            ],
            'files' => $files
        ]);
    } catch (Throwable $e) {
        error_log('[SEEDLING-DETAILS AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (decide) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'decide') {
    header('Content-Type: application/json');

    error_reporting(E_ALL);
    ini_set('display_errors', 0);

    $approvalId = $_POST['approval_id'] ?? '';
    $action     = strtolower(trim((string)$_POST['action'] ?? ''));
    $reason     = trim((string)($_POST['reason'] ?? ''));

    error_log("DEBUG: Decide AJAX - approval_id: $approvalId, action: $action");

    if (!$approvalId || !in_array($action, ['approve', 'reject'], true)) {
        error_log("ERROR: Invalid params - approval_id: $approvalId, action: $action");
        echo json_encode(['ok' => false, 'error' => 'invalid params']);
        exit();
    }

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("
            SELECT a.approval_id, a.approval_status, a.request_type, a.client_id
            FROM public.approval a
            WHERE a.approval_id=:aid
              AND LOWER(COALESCE(a.request_type,'')) IN ('seedling')
            FOR UPDATE
        ");
        $st->execute([':aid' => $approvalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            error_log("ERROR: Approval not found - approval_id: $approvalId");
            echo json_encode(['ok' => false, 'error' => 'approval not found']);
            exit;
        }

        $currentStatus = strtolower((string)$row['approval_status']);
        if ($currentStatus !== 'pending') {
            $pdo->rollBack();
            error_log("ERROR: Already decided - approval_id: $approvalId, status: $currentStatus");
            echo json_encode(['ok' => false, 'error' => 'already decided']);
            exit;
        }

        $adminId  = $user_id;
        $fromDept = $me['department'] ?? null;
        $toUserId = null;
        if (!empty($row['client_id'])) {
            $stCli = $pdo->prepare("SELECT user_id FROM public.client WHERE client_id=:cid LIMIT 1");
            $stCli->execute([':cid' => $row['client_id']]);
            $toUserId = $stCli->fetchColumn() ?: null;
        }

        if ($action === 'approve') {
            error_log("DEBUG: Processing approval for: $approvalId");

            try {
                // 1) Flip status to approved
                $pdo->prepare("
                    UPDATE public.approval
                       SET approval_status='approved',
                           approved_at=now(), approved_by=:by,
                           rejected_at=NULL, reject_by=NULL, rejection_reason=NULL
                     WHERE approval_id=:aid
                ")->execute([':by' => $adminId, ':aid' => $approvalId]);

                // 2) Regenerate file and overwrite existing object in Supabase
                error_log("DEBUG: Starting document regeneration for: $approvalId");
                try {
                    regenerate_and_overwrite_requirement_doc($pdo, (string)$approvalId);
                    error_log("DEBUG: Document regeneration completed for: $approvalId");
                } catch (Exception $e) {
                    error_log("ERROR: Document regeneration failed: " . $e->getMessage());
                    throw new RuntimeException("Failed to regenerate document: " . $e->getMessage());
                }

                // 3) Notify user (their notification; remains unread for them)
                $pdo->prepare("
                    INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                    VALUES (:aid, :msg, false, now(), :fromDept, :toUser)
                ")->execute([
                    ':aid'      => $approvalId,
                    ':msg'      => 'Your seedling request was approved.',
                    ':fromDept' => $fromDept,
                    ':toUser'   => $toUserId
                ]);

                $pdo->commit();
                error_log("DEBUG: Approval completed successfully for: $approvalId");
                echo json_encode(['ok' => true, 'status' => 'approved']);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("ERROR: Approval transaction failed: " . $e->getMessage());
                echo json_encode(['ok' => false, 'error' => 'Approval process failed: ' . $e->getMessage()]);
            }
        } else {
            if ($reason === '') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'reason required']);
                exit;
            }

            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status='rejected',
                       rejected_at=now(), reject_by=:by, rejection_reason=:reason
                 WHERE approval_id=:aid
            ")->execute([':by' => $adminId, ':reason' => $reason, ':aid' => $approvalId]);

            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromDept, :toUser)
            ")->execute([
                ':aid'      => $approvalId,
                ':msg'      => 'Your seedling request was rejected. Reason: ' . $reason,
                ':fromDept' => $fromDept,
                ':toUser'   => $toUserId
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'rejected']);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[SEEDLING-DECIDE AJAX ERROR] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error: ' . $e->getMessage()]);
    }
    exit();
}

/* ---------------- AJAX (mark notifications for *this* approval — seedling target only) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_notifs_for_approval') {
    header('Content-Type: application/json');
    $aid = $_POST['approval_id'] ?? '';
    if (!$aid) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }
    try {
        $st = $pdo->prepare("
            UPDATE public.notifications
               SET is_read = true
             WHERE approval_id = :aid
               AND is_read = false
               AND LOWER(COALESCE(\"to\", '')) = 'seedling'
        ");
        $st->execute([':aid' => $aid]);
        echo json_encode(['ok' => true, 'count' => (int)$st->rowCount()]);
    } catch (Throwable $e) {
        error_log('[SEEDLING-MARK-READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- NOTIFS for header ---------------- */
$seedNotifs = [];
$unreadSeed = 0;
try {
    $notifRows = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name  AS client_first, c.last_name AS client_last,
               NULL::text AS incident_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", ''))='seedling'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $seedNotifs = $notifRows;

    $unreadPermits = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", ''))='seedling' AND n.is_read=false
    ")->fetchColumn();

    $unreadIncidents = (int)$pdo->query("
        SELECT COUNT(*) FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='tree cutting' AND is_read=false
    ")->fetchColumn();

    $unreadSeed = $unreadPermits + $unreadIncidents;

    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE lower(COALESCE(category,''))='tree cutting'
        ORDER BY created_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[SEEDLING NOTIFS-FOR-NAV] ' . $e->getMessage());
    $seedNotifs = [];
    $unreadSeed = 0;
    $incRows = [];
}

/* ---------------- Page data ---------------- */
$rows = [];
try {
    $rows = $pdo->query("
        SELECT a.approval_id,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               COALESCE(NULLIF(btrim(a.permit_type),''),'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status),''),'pending') AS approval_status,
               a.submitted_at,
               c.first_name
        FROM public.approval a
        LEFT JOIN public.client c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(a.request_type,'')) IN ('seedling')
        ORDER BY a.submitted_at DESC NULLS LAST, a.approval_id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[SEEDLING PAGE DATA] ' . $e->getMessage());
    $rows = [];
}

$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
    <title>Seedling Requests</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/denr/superadmin/css/wildhome.css" />

    <style>
        :root {
            color-scheme: light;
        }

        body {
            background: #f3f4f6 !important;
            color: #111827;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial;
        }

        .hidden {
            display: none !important;
        }

        body::before {
            content: none !important;
        }

        .nav-item .badge {
            position: absolute;
            top: -6px;
            right: -6px;
        }

        .nav-item.dropdown.open .badge {
            display: none;
        }

        .dropdown-menu.notifications-dropdown {
            display: grid;
            grid-template-rows: auto 1fr auto;
            width: min(460px, 92vw);
            max-height: 72vh;
            overflow: hidden;
            padding: 0
        }

        .notifications-dropdown .notification-header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb
        }

        .notifications-dropdown .notification-list {
            overflow: auto;
            padding: 8px 0;
            background: #fff
        }

        .notifications-dropdown .notification-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px 16px
        }

        .notifications-dropdown .view-all {
            font-weight: 600;
            color: #1b5e20;
            text-decoration: none
        }

        .notification-item {
            padding: 18px;
            background: #f8faf7
        }

        .notification-item.unread {
            background: #eef7ee
        }

        .notification-item+.notification-item {
            border-top: 1px solid #eef2f1
        }

        .notification-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: #1b5e20
        }

        .notification-link {
            display: flex;
            text-decoration: none;
            color: inherit
        }

        .notification-title {
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 6px
        }

        .notification-time {
            color: #6b7280;
            font-size: .9rem;
            margin-top: 8px
        }

        .notification-message {
            color: #234
        }

        .main-content {
            padding: 10px 16px 24px;
            max-width: 1200px;
            margin: 0 auto
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap
        }

        .title-wrap h1 {
            margin: 0;
            color: #2b6625
        }

        .filters {
            display: flex;
            gap: .75rem;
            align-items: flex-end;
            flex-wrap: wrap
        }

        .filter-row {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .input {
            padding: 10px 12px;
            min-width: 12rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #111827
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #111827;
            color: #fff;
            cursor: pointer;
            text-decoration: none
        }

        .btn.ghost {
            background: #fff;
            color: #111827
        }

        .btn.small {
            padding: 7px 10px;
            font-size: .92rem
        }

        .btn.success {
            background: #065f46;
            border-color: #065f46
        }

        .btn.danger {
            background: #991b1b;
            border-color: #991b1b
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden
        }

        .card-header,
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1rem;
            border-bottom: 1px solid #f3f4f6
        }

        .card-footer {
            border-top: 1px solid #f3f4f6;
            border-bottom: none
        }

        .table {
            width: 100%;
            border-collapse: collapse
        }

        .table th,
        .table td {
            padding: .75rem;
            border-bottom: 1px solid #f3f4f6;
            text-align: left;
            background: #fff
        }

        .table thead th {
            font-weight: 600;
            color: #374151;
            background: #fafafa
        }

        .pill {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: .85rem;
            white-space: nowrap
        }

        .pill.neutral {
            background: #f3f4f6;
            color: #374151
        }

        .status-val {
            display: inline-block;
            font-weight: 600;
            color: #111827;
            background: transparent;
            padding: 0;
            border-radius: 0;
            line-height: 1.2;
            white-space: nowrap;
            min-width: 100px
        }

        .status-val.approved {
            color: #065f46
        }

        .status-val.pending {
            color: #9a3412
        }

        .status-val.rejected {
            color: #991b1b
        }

        .badge.status {
            background: transparent;
            color: inherit;
            padding: 0;
            min-width: 0;
            border-radius: 0
        }

        /* Ensure status text in modal is styled */
        .status-text {
            font-weight: 700;
            color: #111827
        }

        /* Modal / Drawer */
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050
        }

        .modal.show {
            display: flex
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .55)
        }

        .modal-panel {
            position: relative;
            z-index: 1;
            background: #fff;
            width: min(1200px, 96vw);
            max-height: 92vh;
            border-radius: 16px;
            overflow: auto;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .icon-btn {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer
        }

        .modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 420px
        }

        .pane {
            display: flex;
            flex-direction: column;
            min-height: 0
        }

        .pane.left {
            border-right: 1px solid #f3f4f6
        }

        .pane-title {
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .scroll-area {
            padding: 12px 16px;
            overflow: auto;
            max-height: calc(90vh - 210px)
        }

        .deflist {
            margin: 0
        }

        .defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #f3f4f6
        }

        .defrow dt {
            color: #6b7280
        }

        .defrow dd {
            margin: 0;
            word-break: break-word
        }

        .file-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer
        }

        .file-item:hover {
            background: #f9fafb
        }

        .file-item .name {
            font-weight: 500
        }

        .file-item .hint {
            margin-left: auto;
            color: #6b7280;
            font-size: .85rem
        }

        .modal-actions {
            display: flex;
            gap: 20px;
            padding: 10px 16px;
            border-top: 1px solid #f3f4f6;
            background: #fff;
            justify-content: center;
            position: sticky;
            bottom: 0;
            z-index: 1
        }

        .preview-drawer {
            position: fixed;
            top: 2%;
            right: 2%;
            width: min(720px, 96vw);
            height: 96vh;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            z-index: 1100;
            display: none;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .preview-drawer.show {
            display: flex
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            flex: 0 0 56px
        }

        .truncate {
            max-width: 75%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .preview-body {
            flex: 1 1 auto;
            min-height: 0;
            height: calc(96vh - 56px);
            overflow: auto
        }

        #previewImageWrap,
        #previewFrameWrap,
        #previewLinkWrap {
            height: 100%
        }

        #previewImage {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain
        }

        #previewFrame {
            width: 100%;
            height: 100%;
            border: 0
        }

        /* Skeleton (bone) */
        .s-wrap {
            padding: 12px 16px 16px
        }

        .s-wrap.hidden {
            display: none !important;
        }

        .s-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            min-height: 420px
        }

        .s-pane {
            border-top: 1px solid #f3f4f6
        }

        .s-pane+.s-pane {
            border-left: 1px solid #f3f4f6
        }

        .s-title.sk {
            height: 18px;
            width: 40%;
            margin: 12px 0 6px 0
        }

        .s-list {
            padding: 12px 16px;
            display: grid;
            gap: 10px;
            max-height: calc(90vh - 210px);
            overflow: auto
        }

        .sk {
            position: relative;
            overflow: hidden;
            border-radius: 6px;
            background: #e5e7eb
        }

        .sk::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .6), rgba(255, 255, 255, 0));
            animation: shimmer 1.1s infinite
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%)
            }
        }

        .sk.sm {
            height: 12px
        }

        .sk.md {
            height: 14px
        }

        .sk.row {
            height: 14px;
            width: 100%
        }

        .sk.w25 {
            width: 25%
        }

        .sk.w35 {
            width: 35%
        }

        .sk.w45 {
            width: 45%
        }

        .sk.w60 {
            width: 60%
        }

        .sk.w80 {
            width: 80%
        }

        .sk.w100 {
            width: 100%
        }

        .s-defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px
        }

        @media (max-width:980px) {
            .modal-content {
                grid-template-columns: 1fr
            }

            .pane.left {
                border-right: 0;
                border-bottom: 1px solid #f3f4f6
            }

            .defrow {
                grid-template-columns: 1fr
            }

            .s-body {
                grid-template-columns: 1fr
            }

            .s-pane+.s-pane {
                border-left: 0;
                border-top: 1px solid #f3f4f6
            }

            .s-defrow {
                grid-template-columns: 1fr
            }
        }

        .logo::after {
            content: none
        }

        .dropdown-menu .dropdown-item {
            position: relative
        }

        .dropdown-menu .dropdown-item.active {
            background: #eef7ee;
            font-weight: 700;
            color: #1b5e20
        }

        .dropdown-menu .dropdown-item.active i {
            color: #1b5e20
        }

        .dropdown-menu .dropdown-item.active::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 8px;
            bottom: 8px;
            width: 4px;
            border-radius: 4px;
            background: #1b5e20
        }

        /* ****** FIX: overlays & toast/blocker ****** */
        .confirm-wrap {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1300
        }

        .confirm-wrap.show {
            display: flex
        }

        .confirm-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .45)
        }

        .confirm-panel {
            position: relative;
            z-index: 1;
            width: min(520px, 92vw);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .confirm-title {
            margin: 0 0 6px
        }

        .input-textarea {
            width: 100%;
            min-height: 110px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            font: inherit;
            resize: vertical
        }

        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 6px
        }

        .toast {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .2);
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
            transition: opacity .2s, transform .2s;
            z-index: 1400
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0)
        }

        .toast.success {
            background: #065f46
        }

        .toast.error {
            background: #991b1b
        }

        .blocker {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .65);
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            z-index: 1500
        }

        .blocker.show {
            display: flex
        }

        .lds {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid #d1d5db;
            border-top-color: #2b6625;
            animation: spin 1s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        /* ******************************************* */
    </style>
</head>

<body>
    <header>
        <div class="logo"><a href="seedlingshome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <!-- App / hamburger -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="seedlingpermit.php" class="dropdown-item active" aria-current="page">
                        <i class="fas fa-seedling"></i><span>Seedling Requests</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Bell (Seedling notifs + Tree Cutting incidents) -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadSeed ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="treeNotifList">
                        <?php
                        $combined = [];
                        foreach ($seedNotifs as $nf) {
                            $combined[] = [
                                'id'      => $nf['notif_id'],
                                'is_read' => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'type'    => 'permit',
                                'message' => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a request.')),
                                'ago'     => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'    => 'seedlingpermit.php'
                            ];
                        }
                        foreach ($incRows as $ir) {
                            $combined[] = [
                                'id'      => $ir['incident_id'],
                                'is_read' => ($ir['is_read'] === true || $ir['is_read'] === 't' || $ir['is_read'] === 1 || $ir['is_read'] === '1'),
                                'type'    => 'incident',
                                'message' => trim((string)$ir['body_text']),
                                'ago'     => time_elapsed_string($ir['created_at'] ?? date('c')),
                                'link'    => 'reportaccident.php?focus=' . urlencode((string)$ir['incident_id'])
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $title = $item['type'] === 'permit' ? 'Seedling notification' : 'Incident report';
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= $item['type'] === 'permit' ? h($item['id']) : '' ?>"
                                    data-incident-id="<?= $item['type'] === 'incident' ? h($item['id']) : '' ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                    <div class="notification-footer"><a href="reportaccident.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon <?php echo $current_page === 'seedling-profile' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <section class="page-header">
            <div class="title-wrap">
                <h1>Seedling Requests</h1>
                <p class="subtitle">Requests of type <strong>seedling</strong></p>
            </div>
            <div class="filters">
                <div class="filter-row">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" class="input">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-row">
                    <label for="searchName">Search Client</label>
                    <input id="searchName" class="input" type="text" placeholder="First name…">
                </div>
                <button id="btnClearFilters" class="btn ghost" type="button"><i class="fas fa-eraser"></i> Clear</button>
            </div>
        </section>

        <main class="card">
            <div class="card-header">
                <h2>Requests</h2>
                <div class="right-actions"><span id="rowsCount" class="muted"><?= count($rows) ?> results</span></div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client First Name</th>
                            <th>Request Type</th>
                            <!-- Permit Type column removed -->
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="statusTableBody">
                        <?php foreach ($rows as $r):
                            $st  = strtolower((string)($r['approval_status'] ?? 'pending'));
                            $cls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending');
                            $req = strtolower((string)($r['request_type'] ?? ''));
                        ?>
                            <tr data-approval-id="<?= h($r['approval_id']) ?>">
                                <td><?= h($r['first_name'] ?? '—') ?></td>
                                <td><span class="pill"><?= h($req) ?></span></td>
                                <!-- Permit Type cell removed -->
                                <td><span class="status-val <?= $cls ?>"><?= ucfirst($st) ?></span></td>
                                <td><?= h($r['submitted_at'] ? date('Y-m-d H:i', strtotime((string)$r['submitted_at'])) : '—') ?></td>
                                <td><button class="btn small" data-action="view"><i class="fas fa-eye"></i> View</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!$rows): ?>
                    <div class="empty"><i class="far fa-folder-open"></i>
                        <p>No seedling requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-panel" role="document">
            <div class="modal-header">
                <h3 id="modalTitle">Request Details</h3>
                <button class="icon-btn" type="button" aria-label="Close" data-close-modal><i class="fas fa-times"></i></button>
            </div>

            <!-- Skeleton (bone) -->
            <div id="modalSkeleton" class="s-wrap hidden" aria-hidden="true">
                <div class="s-body">
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="s-defrow">
                                <div class="sk sm w60"></div>
                                <div class="sk sm w80"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w35"></div>
                                <div class="sk sm w60"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w35"></div>
                                <div class="sk sm w35"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w25"></div>
                                <div class="sk sm w45"></div>
                            </div>
                        </div>
                    </div>
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real content -->
            <div class="modal-content" id="modalContent">
                <!-- LEFT: Request Info -->
                <section class="pane left">
                    <h4 class="pane-title"><i class="fas fa-circle-info"></i> Request Info</h4>
                    <div class="scroll-area">
                        <dl class="deflist">
                            <div class="defrow">
                                <dt>Client</dt>
                                <dd id="infoClientName">—</dd>
                            </div>
                            <div class="defrow">
                                <dt>Request Type</dt>
                                <dd><span class="pill" id="infoRequestType">—</span></dd>
                            </div>
                            <!-- Permit Type row removed -->
                            <div class="defrow">
                                <dt>Status</dt>
                                <dd><span class="status-text" id="infoStatus">—</span></dd>
                            </div>
                            <div class="defrow">
                                <dt>Requested Seeds</dt>
                                <dd id="infoSeeds"><span class="muted">—</span></dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- RIGHT: Documents -->
                <section class="pane right">
                    <h4 class="pane-title"><i class="fas fa-paperclip"></i> Documents</h4>
                    <div id="filesScroll" class="scroll-area">
                        <ul id="filesList" class="file-list"></ul>
                        <div id="filesEmpty" class="hidden" style="text-align:center;color:#6b7280;">No documents uploaded.</div>
                    </div>
                </section>
            </div>

            <div class="modal-actions" id="modalActions"></div>
        </div>

        <!-- Preview Drawer -->
        <div id="filePreviewDrawer" class="preview-drawer" aria-live="polite" aria-hidden="true">
            <div class="preview-header">
                <span id="previewTitle" class="truncate">Document</span>
                <button class="icon-btn" type="button" aria-label="Close preview" data-close-preview><i class="fas fa-times"></i></button>
            </div>
            <div class="preview-body">
                <div id="previewImageWrap" class="hidden"><img id="previewImage" alt="Preview"></div>
                <div id="previewFrameWrap" class="hidden"><iframe id="previewFrame" title="Document preview" loading="lazy"></iframe></div>
                <div id="previewLinkWrap" class="hidden" style="padding:16px;text-align:center">
                    <p class="muted">Preview not available. Open or download the file instead.</p>
                    <a id="previewDownload" class="btn" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open / Download</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve/Reject confirms + toast/blocker -->
    <div id="approveConfirm" class="confirm-wrap" role="dialog" aria-modal="true">
        <div class="confirm-backdrop" data-close-confirm></div>
        <div class="confirm-panel">
            <h4 class="confirm-title">Approve this request?</h4>
            <p>This action will mark the request as <strong>Approved</strong> and notify the client.</p>
            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn success" id="approveConfirmBtn"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </div>
    </div>

    <div id="rejectConfirm" class="confirm-wrap" role="dialog" aria-modal="true">
        <div class="confirm-backdrop" data-close-confirm></div>
        <div class="confirm-panel">
            <h4 class="confirm-title">Reject this request?</h4>
            <label for="rejectReason" style="font-size:.9rem;color:#374151;">Reason for rejection</label>
            <textarea id="rejectReason" class="input-textarea" placeholder="Provide a short reason…" spellcheck="false"></textarea>
            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn danger" id="rejectConfirmBtn"><i class="fas fa-times"></i> Confirm</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>
    <div id="screenBlocker" class="blocker">
        <div class="lds"></div><span>Updating…</span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => navContainer.classList.toggle('active'));

            /* Dropdowns */
            const dropdowns = document.querySelectorAll('[data-dropdown]');
            const isTouch = matchMedia('(pointer: coarse)').matches;
            dropdowns.forEach(dd => {
                const trigger = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');
                if (!trigger || !menu) return;

                const open = () => {
                    dd.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                };
                const close = () => {
                    dd.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    if (isTouch) menu.style.display = 'none';
                };

                if (!isTouch) {
                    dd.addEventListener('mouseenter', open);
                    dd.addEventListener('mouseleave', (e) => {
                        if (!dd.contains(e.relatedTarget)) close();
                    });
                } else {
                    trigger.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const openNow = dd.classList.contains('open');
                        document.querySelectorAll('[data-dropdown].open').forEach(o => {
                            if (o !== dd) o.classList.remove('open');
                        });
                        if (openNow) close();
                        else {
                            menu.style.display = 'block';
                            open();
                        }
                    });
                }
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-dropdown]')) {
                    document.querySelectorAll('[data-dropdown].open').forEach(dd => {
                        const menu = dd.querySelector('.dropdown-menu');
                        dd.classList.remove('open');
                        if (menu) {
                            menu.style.opacity = '0';
                            menu.style.visibility = 'hidden';
                            menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                            if (matchMedia('(pointer: coarse)').matches) menu.style.display = 'none';
                        }
                    });
                }
            });

            /* MARK ALL AS READ */
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('#treeNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }

                try {
                    const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                } catch (_) {
                    location.reload();
                }
            });

            /* Click any single notification → mark read */
            document.getElementById('treeNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                const item = link.closest('.notification-item');
                if (!item) return;
                e.preventDefault();
                const href = link.getAttribute('href') || 'reportaccident.php';
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);
                    await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {}

                item.classList.remove('unread');
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    const n = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, n - 1);
                    badge.textContent = String(next);
                    if (next <= 0) badge.style.display = 'none';
                }
                window.location.href = href;
            });

            /* Toast + blocker helpers */
            function showToast(msg, type = 'success') {
                const t = document.getElementById('toast');
                t.textContent = msg;
                t.className = 'toast show ' + (type === 'error' ? 'error' : 'success');
                setTimeout(() => {
                    t.className = 'toast';
                    t.textContent = '';
                }, 2000);
            }
            const blocker = document.getElementById('screenBlocker');
            const block = (on) => blocker.classList.toggle('show', !!on);

            /* Modal helpers */
            const modalEl = document.getElementById('viewModal');
            const modalSkeleton = document.getElementById('modalSkeleton');
            const modalContent = document.getElementById('modalContent');
            const modalActions = document.getElementById('modalActions');

            function showModalSkeleton() {
                modalSkeleton.classList.remove('hidden');
                modalContent.classList.add('hidden');
                modalActions.classList.add('hidden');
            }

            function hideModalSkeleton() {
                modalSkeleton.classList.add('hidden');
                modalContent.classList.remove('hidden');
            }

            function openViewModal() {
                modalEl.classList.add('show');
            }

            function closeViewModal() {
                modalEl.classList.remove('show');
                closePreview();
            }
            document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', closeViewModal));
            document.querySelector('.modal-backdrop')?.addEventListener('click', closeViewModal);

            /* File preview */
            function closePreview() {
                const dr = document.getElementById('filePreviewDrawer');
                dr.classList.remove('show');
                document.getElementById('previewImage').src = '';
                document.getElementById('previewFrame').src = '';
            }

            function showPreview(name, url, ext) {
                const drawer = document.getElementById('filePreviewDrawer');
                const imgWrap = document.getElementById('previewImageWrap');
                theFrameWrap = document.getElementById('previewFrameWrap');
                const linkWrap = document.getElementById('previewLinkWrap');
                document.getElementById('previewTitle').textContent = name;
                imgWrap.classList.add('hidden');
                theFrameWrap.classList.add('hidden');
                linkWrap.classList.add('hidden');
                const imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const offExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const txtExt = ['txt', 'csv', 'json', 'md', 'log'];
                if (imgExt.includes(ext) && url) {
                    document.getElementById('previewImage').src = url;
                    imgWrap.classList.remove('hidden');
                } else if (ext === 'pdf' && url) {
                    document.getElementById('previewFrame').src = url;
                    theFrameWrap.classList.remove('hidden');
                } else if (offExt.includes(ext) && url) {
                    const viewer = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
                    document.getElementById('previewFrame').src = viewer;
                    theFrameWrap.classList.remove('hidden');
                } else if (txtExt.includes(ext) && url) {
                    const gview = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    document.getElementById('previewFrame').src = gview;
                    theFrameWrap.classList.remove('hidden');
                } else {
                    const a = document.getElementById('previewDownload');
                    a.href = url || '#';
                    linkWrap.classList.remove('hidden');
                }
                drawer.classList.add('show');
            }
            document.getElementById('filesList')?.addEventListener('click', (e) => {
                const li = e.target.closest('.file-item');
                if (!li) return;
                showPreview(li.dataset.fileName || 'Document', li.dataset.fileUrl || '#', (li.dataset.fileExt || '').toLowerCase());
            });
            document.querySelector('[data-close-preview]')?.addEventListener('click', closePreview);

            /* View button -> open modal + fetch details + mark notifs read */
            let currentApprovalId = null;
            document.getElementById('statusTableBody')?.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action="view"]');
                if (!btn) return;
                const tr = btn.closest('tr');
                currentApprovalId = tr?.dataset.approvalId;
                if (!currentApprovalId) return;

                showModalSkeleton();

                // Reset placeholders
                document.getElementById('infoClientName').textContent = '—';
                document.getElementById('infoRequestType').textContent = '—';
                document.getElementById('infoStatus').textContent = '—';
                document.getElementById('infoSeeds').innerHTML = '<span class="muted">—</span>';
                document.getElementById('filesList').innerHTML = '';
                document.getElementById('filesEmpty').classList.add('hidden');
                modalActions.innerHTML = '';

                openViewModal();

                // Mark related notifs read & update bell
                try {
                    const resMark = await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_notifs_for_approval', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            approval_id: currentApprovalId
                        }).toString()
                    }).then(r => r.json());
                    if (resMark && resMark.ok) {
                        const badge = document.querySelector('#notifDropdown .badge');
                        if (badge) {
                            const n = parseInt(badge.textContent || '0', 10) || 0;
                            const dec = parseInt(resMark.count || 0, 10) || 0;
                            const next = Math.max(0, n - dec);
                            badge.textContent = String(next);
                            if (next <= 0) badge.style.display = 'none';
                        }
                        document.querySelectorAll('#treeNotifList .notification-item.unread').forEach(el => {
                            const a = el.querySelector('a.notification-link');
                            if (a && a.getAttribute('href') === 'seedlingpermit.php') el.classList.remove('unread');
                        });
                    }
                } catch (_) {}

                // Fetch details
                const res = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(currentApprovalId)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(r => r.json()).catch(() => ({
                    ok: false
                }));

                if (!res.ok) {
                    hideModalSkeleton();
                    document.getElementById('infoStatus').textContent = 'Error';
                    document.getElementById('filesList').innerHTML = '';
                    document.getElementById('filesEmpty').classList.remove('hidden');
                    alert('Failed to load details.');
                    return;
                }

                // LEFT Request Info
                const meta = res.meta || {};
                document.getElementById('infoClientName').textContent = meta.client || '—';
                document.getElementById('infoRequestType').textContent = meta.request_type || '—';
                const st = (meta.status || '').trim().toLowerCase();
                const msLeft = document.getElementById('infoStatus');
                msLeft.textContent = st ? st[0].toUpperCase() + st.slice(1) : '—';
                msLeft.className = 'status-text';

                // Requested Seeds (FIXED: read meta.requested_seeds if res.seeds is not provided)
                const seedsWrap = document.getElementById('infoSeeds');
                seedsWrap.innerHTML = '';
                if (Array.isArray(res.seeds) && res.seeds.length) {
                    const ul = document.createElement('ul');
                    ul.style.listStyle = 'disc inside';
                    ul.style.margin = '0';
                    ul.style.paddingLeft = '16px';
                    res.seeds.forEach(s => {
                        const li = document.createElement('li');
                        li.textContent = `${s.name} (${s.qty})`;
                        ul.appendChild(li);
                    });
                    seedsWrap.appendChild(ul);
                } else if (meta.requested_seeds && meta.requested_seeds !== '—') {
                    seedsWrap.textContent = meta.requested_seeds;
                } else {
                    seedsWrap.textContent = '—';
                }

                // Files
                const filesList = document.getElementById('filesList');
                const filesEmpty = document.getElementById('filesEmpty');
                filesList.innerHTML = '';
                if (Array.isArray(res.files) && res.files.length) {
                    filesEmpty.classList.add('hidden');
                    res.files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.tabIndex = 0;
                        li.dataset.fileUrl = f.url || '';
                        li.dataset.fileName = f.name || 'Document';
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="far fa-file"></i><span class="name">${f.name}</span><span class="hint">${(f.ext||'').toUpperCase()}</span>`;
                        filesList.appendChild(li);
                    });
                } else {
                    filesEmpty.classList.remove('hidden');
                }

                // Actions (only for pending)
                modalActions.innerHTML = '';
                if (st === 'pending') {
                    const approveBtn = document.createElement('button');
                    approveBtn.className = 'btn success';
                    approveBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
                    approveBtn.addEventListener('click', () => openConfirm('approve'));

                    const rejectBtn = document.createElement('button');
                    rejectBtn.className = 'btn danger';
                    rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
                    rejectBtn.addEventListener('click', () => openConfirm('reject'));

                    modalActions.appendChild(approveBtn);
                    modalActions.appendChild(rejectBtn);
                    modalActions.classList.remove('hidden');
                } else {
                    modalActions.classList.add('hidden');
                }

                hideModalSkeleton();
            });

            /* Approve / Reject flow */
            let pendingAction = null;

            function openConfirm(which) {
                pendingAction = which;
                if (which === 'approve') document.getElementById('approveConfirm').classList.add('show');
                else {
                    document.getElementById('rejectConfirm').classList.add('show');
                    document.getElementById('rejectReason').value = '';
                }
            }

            function closeAllConfirms() {
                document.getElementById('approveConfirm').classList.remove('show');
                document.getElementById('rejectConfirm').classList.remove('show');
            }
            document.querySelectorAll('[data-close-confirm],[data-cancel-confirm]').forEach(el => el.addEventListener('click', closeAllConfirms));

            async function sendDecision(action, reason = '') {
                if (!currentApprovalId) return {
                    ok: false,
                    error: 'Missing approval id'
                };
                const form = new URLSearchParams();
                form.set('approval_id', currentApprovalId);
                form.set('action', action);
                if (action === 'reject') form.set('reason', reason);
                return fetch('<?php echo basename(__FILE__); ?>?ajax=decide', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: form.toString()
                }).then(r => r.json()).catch(() => ({
                    ok: false,
                    error: 'network error'
                }));
            }

            function applyDecisionUI(status) {
                const msLeft = document.getElementById('infoStatus');
                msLeft.textContent = status[0].toUpperCase() + status.slice(1);
                msLeft.className = 'status-text';
                document.getElementById('modalActions').innerHTML = '';

                const tr = document.querySelector(`tr[data-approval-id="${CSS.escape(currentApprovalId)}"]`);
                if (tr) {
                    // status column is now index 2 (after removing Permit Type col)
                    const tdStatus = tr.children[2];
                    if (tdStatus) tdStatus.innerHTML = `<span class="status-val ${status}">${status[0].toUpperCase()+status.slice(1)}</span>`;
                }
            }

            // Refresh files list after approve (cache-busted so the new stamped doc shows)
            async function refreshFilesAfterDecision() {
                if (!currentApprovalId) return;
                const res = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(currentApprovalId)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(r => r.json()).catch(() => ({
                    ok: false
                }));
                if (!res.ok) return;

                const filesList = document.getElementById('filesList');
                const filesEmpty = document.getElementById('filesEmpty');
                filesList.innerHTML = '';
                if (Array.isArray(res.files) && res.files.length) {
                    filesEmpty.classList.add('hidden');
                    const bust = `v=${Date.now()}`;
                    res.files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.tabIndex = 0;
                        const url = f.url ? (f.url + (f.url.includes('?') ? '&' : '?') + bust) : '#';
                        li.dataset.fileUrl = url;
                        li.dataset.fileName = f.name || 'Document';
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="far fa-file"></i><span class="name">${f.name}</span><span class="hint">${(f.ext||'').toUpperCase()}</span>`;
                        filesList.appendChild(li);
                    });
                } else {
                    filesEmpty.classList.remove('hidden');
                }
            }

            document.getElementById('approveConfirmBtn')?.addEventListener('click', async () => {
                if (pendingAction !== 'approve') return;
                block(true);
                const res = await sendDecision('approve');
                block(false);
                if (!res.ok) {
                    alert(res.error || 'Failed to approve');
                    return;
                }
                applyDecisionUI('approved');
                await refreshFilesAfterDecision(); // show the regenerated/stamped doc
                closeAllConfirms();
                showToast('Request approved', 'success');
            });

            document.getElementById('rejectConfirmBtn')?.addEventListener('click', async () => {
                if (pendingAction !== 'reject') return;
                const reason = (document.getElementById('rejectReason').value || '').trim();
                if (!reason) {
                    alert('Please provide a reason.');
                    return;
                }
                block(true);
                const res = await sendDecision('reject', reason);
                block(false);
                if (!res.ok) {
                    alert(res.error || 'Failed to reject');
                    return;
                }
                applyDecisionUI('rejected');
                closeAllConfirms();
                showToast('Request rejected', 'success');
            });

            /* Filters */
            const filterStatus = document.getElementById('filterStatus');
            const searchName = document.getElementById('searchName');
            const rowsCount = document.getElementById('rowsCount');
            document.getElementById('btnClearFilters')?.addEventListener('click', () => {
                filterStatus.value = '';
                searchName.value = '';
                applyFilters();
            });
            [filterStatus, searchName].forEach(el => el.addEventListener('input', applyFilters));

            function applyFilters() {
                const st = (filterStatus.value || '').toLowerCase();
                const q = (searchName.value || '').trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                    const name = (tr.children[0]?.textContent || '').trim().toLowerCase();
                    // status column is now index 2
                    const stat = (tr.children[2]?.textContent || '').trim().toLowerCase();
                    let ok = true;
                    if (st && stat !== st) ok = false;
                    if (q && !name.includes(q)) ok = false;
                    tr.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                rowsCount.textContent = `${shown} result${shown===1?'':'s'}`;
            }
        });
    </script>


</body>

</html>

</html>