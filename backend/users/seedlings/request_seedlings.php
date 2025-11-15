<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

try {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Autoload failed']);
    exit;
}

$ENV_ROOT = dirname(__DIR__, 2);
try {
    if (class_exists(Dotenv\Dotenv::class) && is_file($ENV_ROOT . '/.env')) {
        Dotenv\Dotenv::createUnsafeImmutable($ENV_ROOT)->safeLoad();
    }
} catch (Throwable $e) {
    error_log('[SEED-REQ] Dotenv load error: ' . $e->getMessage());
}
if (!getenv('SUPABASE_URL') && is_readable($ENV_ROOT . '/.env')) {
    foreach (file($ENV_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if ($k !== '') {
            if (getenv($k) === false) putenv("$k=$v");
            $_ENV[$k] = $_ENV[$k] ?? $v;
            $_SERVER[$k] = $_SERVER[$k] ?? $v;
        }
    }
}

/* helpers */
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
    echo json_encode(['success' => $ok] + ($err ? ['error' => $err] : []) + $extra);
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
    if ($v === false || $v === null || $v === '') $v = $_ENV[$k] ?? $_SERVER[$k] ?? null;
    return $v !== null && $v !== '' ? (string)$v : $def;
}
function encode_storage_path(string $path): string
{
    $parts = array_map('rawurlencode', array_filter(explode('/', $path), fn($p) => $p !== ''));
    return implode('/', $parts);
}
function storage_upload_binary(string $supabaseUrl, string $serviceKey, string $bucket, string $objectPath, string $contentType, string $binary): array
{
    $url = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_storage_path($objectPath);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $serviceKey, 'apikey: ' . $serviceKey, 'Content-Type: ' . $contentType],
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ok = ($resp !== false && $http >= 200 && $http < 300);
    return [$ok, $http, $resp ?: $err];
}

/* auth + config */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    out(false, 'Not authenticated');
}
$user_id = (string)$_SESSION['user_id'];

$SUPABASE_URL = rtrim((string)env_get('SUPABASE_URL', ''), '/');
$SUPABASE_SERVICE_ROLE_KEY = (string)env_get('SUPABASE_SERVICE_ROLE_KEY', '');
if (!$SUPABASE_URL || !$SUPABASE_SERVICE_ROLE_KEY) out(false, 'Supabase credentials are not configured on the server.');

$REQ_BUCKET = env_get('SUPABASE_REQUIREMENTS_BUCKET', 'requirements');
$REQ_BUCKET_PUBLIC = strtolower(env_get('SUPABASE_REQUIREMENTS_PUBLIC', 'true')) === 'true';
$SIG_BUCKET = env_get('SUPABASE_SIGNATURES_BUCKET', 'signatures');
$SIG_BUCKET_PUBLIC = strtolower(env_get('SUPABASE_SIGNATURES_PUBLIC', 'true')) === 'true';

require_once dirname(__DIR__, 2) . '/connection.php'; // $pdo

/* input */
$in = get_json_body();
$mode = strtolower(trim((string)($in['mode'] ?? 'auto'))); // auto|reuse|new
$existingIdIn = trim((string)($in['existing_client_id'] ?? ''));

$first = trim($in['first_name'] ?? '');
$middle = trim($in['middle_name'] ?? '');
$last = trim($in['last_name'] ?? '');
$org = trim($in['organization'] ?? '');
$purpose = trim($in['purpose'] ?? '');
$sitio = trim($in['sitio_street'] ?? '');
$barangay = trim($in['barangay'] ?? '');
$municipality = trim($in['municipality'] ?? '');
$city = trim($in['city'] ?? '');
$request_date = trim($in['request_date'] ?? '');
$contact_number = trim($in['contact_number'] ?? '');
$sig_b64 = b64_from_data_url($in['signature_b64'] ?? '');
$seedlings = $in['seedlings'] ?? [];
$isReuseMode = ($mode === 'reuse');
$batch_key = trim((string)($in['batch_key'] ?? '')) ?: null;

if (!$isReuseMode && (!$first || !$last)) out(false, 'First and last name are required.');
if (!$purpose) out(false, 'Purpose is required.');
if (!$request_date) out(false, 'Request date is required.');
if (!$sig_b64) out(false, 'Signature is required.');
if (!is_array($seedlings)) $seedlings = [];
$seedlings = array_values(array_filter(array_map(function ($r) {
    $sid = trim((string)($r['seedlings_id'] ?? ''));
    $qty = (int)($r['qty'] ?? 0);
    return ($sid && $qty > 0) ? ['seedlings_id' => $sid, 'qty' => $qty] : null;
}, $seedlings)));
if (!count($seedlings)) out(false, 'Add at least one seedling with a valid quantity.');

/* duplicate-guard fingerprint (prevents accidental double writes) */
$fingerprintData = [
    'existing_client_id' => $existingIdIn,
    'contact_number' => $contact_number,
    'request_date' => $request_date,
    'purpose' => $purpose,
    'first_name' => $first,
    'middle_name' => $middle,
    'last_name' => $last,
    'organization' => $org,
    'sitio_street' => $sitio,
    'barangay' => $barangay,
    'municipality' => $municipality,
    'city' => $city,
    'seedlings' => $seedlings,
];
$fingerprint = hash('sha256', json_encode($fingerprintData));
$duplicateWindow = 90; // seconds
$prevSubmission = $_SESSION['last_seedling_submission'] ?? null;
if (
    isset($prevSubmission['hash'], $prevSubmission['ts'], $prevSubmission['response'])
    && $prevSubmission['hash'] === $fingerprint
    && (time() - (int)$prevSubmission['ts']) <= $duplicateWindow
) {
    out(true, '', $prevSubmission['response'] + ['duplicate' => true]);
}

/* duplicate check */
$existing = null;
if (!$isReuseMode) {
    $dup = $pdo->prepare('
        SELECT client_id, first_name, middle_name, last_name,
               sitio_street, barangay, municipality, city, signature, contact_number
        FROM public.client
        WHERE lower(btrim(coalesce(first_name,\'\')))  = lower(:f)
          AND lower(btrim(coalesce(middle_name,\'\'))) = lower(:m)
          AND lower(btrim(coalesce(last_name,\'\')))   = lower(:l)
        ORDER BY id DESC LIMIT 1
    ');
    $dup->execute([':f' => $first, ':m' => $middle, ':l' => $last]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($mode === 'auto' && $existing) out(true, '', ['needs_decision' => true, 'existing_client' => $existing]);
    if ($mode === 'auto') $mode = 'new';
}

/* validate seedlings catalog */
$ids = array_map(fn($r) => $r['seedlings_id'], $seedlings);
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("SELECT seedlings_id, seedling_name, stock FROM public.seedlings WHERE seedlings_id IN ($ph)");
$st->execute($ids);
$catalog = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $catalog[$row['seedlings_id']] = $row;
foreach ($seedlings as $r) if (!isset($catalog[$r['seedlings_id']])) out(false, 'One or more selected seedlings do not exist.');

/* letter builder */
$build_letter_mhtml = function (array $client, string $sig_b64, string $purpose, array $seedlings, array $catalog, string $request_date): array {
    $toTitle = static function (?string $s): string {
        $s = trim((string)$s);
        if ($s === '') return '';
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords(strtolower($s));
    };

    $first = $toTitle($client['first_name'] ?? '');
    $middle = $toTitle($client['middle_name'] ?? '');
    $last = $toTitle($client['last_name'] ?? '');
    $sitio = $toTitle($client['sitio_street'] ?? '');
    $brgy = $toTitle($client['barangay'] ?? '');
    $muni = $toTitle($client['municipality'] ?? '');
    $city = $toTitle($client['city'] ?? '');
    $org = trim((string)($client['organization'] ?? ''));

    $lgu = $city ?: $muni;
    $addr = [];
    if ($sitio) $addr[] = $sitio;
    if ($brgy) $addr[] = 'Brgy. ' . $brgy;
    if ($lgu) $addr[] = $lgu;
    $addressLine = implode(', ', $addr);
    $cityProv = ($lgu ? ($lgu . ', ') : '') . 'Cebu';
    $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
    $prettyDate = date('F j, Y', strtotime($request_date));

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
        <p style="text-align:justify;text-indent:50px;">I am writing to formally request ' . $totalQty . ' seedlings of ' . $seedTxt . ' for ' . h($purpose) . '. The seedlings will be planted at ' . h($addressLine ?: $cityProv) . '.</p>
        <p style="text-align:justify;text-indent:50px;">The purpose of this request is ' . h($purpose) . '.</p>
        <p style="text-align:justify;text-indent:50px;">I would be grateful if you could approve this request at your earliest convenience.</p>
        <p style="text-align:justify;text-indent:50px;">Thank you for your time and consideration.</p>
        <p>Sincerely,<br><br>
            <img src="cid:sigimg" width="140" height="25" style="height:auto;border:1px solid #ccc;"><br>
            ' . h($fullName) . '<br>' . h($addressLine ?: $cityProv) . '<br>' . h($org) . '
        </p>
    ';

    $htmlDoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seedling Request Letter</title><style>body{font-family:Arial,sans-serif;line-height:1.6;margin:50px;color:#111}</style></head><body>' . $inner . '</body></html>';

    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $htmlDoc . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///sig.png\r\n\r\n";
    $mhtml .= chunk_split($sig_b64, 76, "\r\n") . "\r\n--$boundary--";
    return [$mhtml, $fullName];
};

try {
    $pdo->beginTransaction();

    $client_id = null;
    $client_for_doc = null;
    $sigUrl = null;
    $application_id = null;

    if ($mode === 'reuse') {
        $cid = $existingIdIn ?: ($existing['client_id'] ?? '');
        if (!$cid) throw new RuntimeException('Missing existing client id.');
        $q = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, contact_number, signature FROM public.client WHERE client_id=:cid LIMIT 1');
        $q->execute([':cid' => $cid]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Existing client not found.');
        $client_id = (string)$row['client_id'];
        $client_for_doc = $row;
    } else {
        // upload signature
        $png = base64_decode($sig_b64, true);
        if ($png === false || $png === '') throw new RuntimeException('Signature decode failed.');
        $sigPath = 'seedling/' . $user_id . '/' . time() . '_signature.png';
        [$ok, $http, $body] = storage_upload_binary($SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY, $SIG_BUCKET, $sigPath, 'image/png', $png);
        if (!$ok) throw new RuntimeException('Signature upload failed (' . $http . '): ' . $body);
        $sigUrl = $SIG_BUCKET_PUBLIC ? $SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode($SIG_BUCKET) . '/' . encode_storage_path($sigPath) : $SIG_BUCKET . '/' . $sigPath;

        // insert client
        $insC = $pdo->prepare('
            INSERT INTO public.client (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature, contact_number)
            VALUES (:uid,:f,:m,:l,:sitio,:brgy,:muni,:city,:sig,:contact)
            RETURNING client_id
        ');
        $insC->execute([
            ':uid' => $user_id,
            ':f' => $first,
            ':m' => $middle ?: null,
            ':l' => $last,
            ':sitio' => $sitio ?: null,
            ':brgy' => $barangay ?: null,
            ':muni' => $municipality ?: null,
            ':city' => $city ?: null,
            ':sig' => $sigUrl,
            ':contact' => $contact_number ?: null
        ]);
        $client_id = (string)$insC->fetchColumn();

        $client_for_doc = ['first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'sitio_street' => $sitio, 'barangay' => $barangay, 'municipality' => $municipality, 'city' => $city, 'organization' => $org];
    }

    // build letter and upload
    [$mhtml, $fullNameForFile] = $build_letter_mhtml($client_for_doc, $sig_b64, $purpose, $seedlings, $catalog, $request_date);

    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client_for_doc['last_name'] ?? $last) ?: 'Letter');
    $shortId = substr($client_id, 0, 8);
    $ymd = date('Ymd', strtotime($request_date));
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);

    $fname = "Seedling_Request_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
    $objectPath = "seedling/{$client_id}/{$fname}";

    [$okDoc, $httpDoc, $bodyDoc] = storage_upload_binary($SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY, $REQ_BUCKET, $objectPath, 'application/msword', $mhtml);
    if (!$okDoc && (int)$httpDoc === 409) {
        $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        $fname = "Seedling_Request_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = "seedling/{$client_id}/{$fname}";
        [$okDoc, $httpDoc, $bodyDoc] = storage_upload_binary($SUPABASE_URL, $SUPABASE_SERVICE_ROLE_KEY, $REQ_BUCKET, $objectPath, 'application/msword', $mhtml);
    }
    if (!$okDoc) throw new RuntimeException('Storage upload failed (' . $httpDoc . '): ' . $bodyDoc);

    $fileUrl = $REQ_BUCKET_PUBLIC ? $SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode($REQ_BUCKET) . '/' . encode_storage_path($objectPath)
        : $REQ_BUCKET . '/' . $objectPath;

    // requirements row
    $stmt = $pdo->prepare('INSERT INTO public.requirements (application_form) VALUES (:f) RETURNING requirement_id');
    $stmt->execute([':f' => $fileUrl]);
    $requirement_id = (string)$stmt->fetchColumn();

    // application_form row (seedling metadata)
    $formFirst = trim((string)($client_for_doc['first_name'] ?? $first));
    $formMiddle = trim((string)($client_for_doc['middle_name'] ?? $middle));
    $formLast = trim((string)($client_for_doc['last_name'] ?? $last));
    $completeName = trim($formFirst . ' ' . ($formMiddle ? $formMiddle . ' ' : '') . $formLast);
    if ($completeName === '') {
        $completeName = null;
    }

    $sitioForForm = trim((string)($client_for_doc['sitio_street'] ?? $sitio));
    $brgyForForm = trim((string)($client_for_doc['barangay'] ?? $barangay));
    $muniForForm = trim((string)($client_for_doc['municipality'] ?? $municipality));
    $cityForForm = trim((string)($client_for_doc['city'] ?? $city));
    $addressParts = [];
    if ($sitioForForm) $addressParts[] = $sitioForForm;
    if ($brgyForForm) $addressParts[] = 'Brgy. ' . $brgyForForm;
    if ($muniForForm) $addressParts[] = $muniForForm;
    if ($cityForForm) $addressParts[] = $cityForForm;
    $presentAddress = $addressParts ? implode(', ', $addressParts) : null;
    $location = $cityForForm ?: $muniForForm ?: null;

    $contactNumberForForm = trim($contact_number ?: ($client_for_doc['contact_number'] ?? ''));
    if ($contactNumberForForm === '') $contactNumberForForm = null;

    $orgName = trim($org);
    if ($orgName === '') $orgName = null;

    $requestDateNormalized = null;
    if ($request_date) {
        $ts = strtotime($request_date);
        $requestDateNormalized = $ts !== false ? date('Y-m-d', $ts) : $request_date;
    }

    $signatureForForm = trim((string)($sigUrl ?: ($client_for_doc['signature'] ?? '')));
    if ($signatureForForm === '') {
        $signatureForForm = null;
    }

    $insApp = $pdo->prepare('
        INSERT INTO public.application_form
            (client_id, contact_number, application_for, type_of_permit,
             complete_name, company_name, present_address, province, location,
             purpose_of_use, date, date_today, signature_of_applicant)
        VALUES
            (:client_id, :contact_number, :application_for, :type_of_permit,
             :complete_name, :company_name, :present_address, :province, :location,
             :purpose_of_use, :date, :date_today, :signature)
        RETURNING application_id
    ');
    $insApp->execute([
        ':client_id' => $client_id,
        ':contact_number' => $contactNumberForForm,
        ':application_for' => 'seedling',
        ':type_of_permit' => 'seedling',
        ':complete_name' => $completeName,
        ':company_name' => $orgName,
        ':present_address' => $presentAddress,
        ':province' => 'Cebu',
        ':location' => $location,
        ':purpose_of_use' => $purpose,
        ':date' => $requestDateNormalized,
        ':date_today' => $requestDateNormalized,
        ':signature' => $signatureForForm
    ]);
    $application_id = (string)$insApp->fetchColumn();

    // seedling_requests (one per seedling)
    $insSeed = $pdo->prepare('INSERT INTO public.seedling_requests (client_id, seedlings_id, quantity, batch_key) VALUES (:cid,:sid,:qty,:batch_key) RETURNING seedl_req_id');
    $seed_req_ids = [];
    foreach ($seedlings as $r) {
        $insSeed->execute([':cid' => $client_id, ':sid' => $r['seedlings_id'], ':qty' => (int)$r['qty'], ':batch_key' => $batch_key]);
        $seed_req_ids[] = (string)$insSeed->fetchColumn();
    }

    // approval (one per entire request)
    $insAppr = $pdo->prepare('
        INSERT INTO public.approval (client_id, requirement_id, request_type, submitted_at, seedl_req_id, application_id)
        VALUES (:cid, :rid, :rtype, now(), :sreq, :app_id)
        RETURNING approval_id
    ');
    $seedlReference = $seed_req_ids[0] ?? null;
    $insAppr->execute([
        ':cid' => $client_id,
        ':rid' => $requirement_id,
        ':rtype' => 'seedling',
        ':sreq' => $seedlReference,
        ':app_id' => $application_id
    ]);
    $approval_ids = [(string)$insAppr->fetchColumn()];

    // ───────────────────────────────────────────────────────────────
    // SINGLE notification for the whole request (aggregated message)
    // ───────────────────────────────────────────────────────────────
    $seedNames = [];
    foreach ($seedlings as $r) {
        $seedNames[] = $catalog[$r['seedlings_id']]['seedling_name'] ?? 'Seedling';
    }
    $clientFullName = trim(($client_for_doc['first_name'] ?? $first) . ' ' . ((trim((string)($client_for_doc['middle_name'] ?? $middle)) !== '') ? ($client_for_doc['middle_name'] . ' ') : '') . ($client_for_doc['last_name'] ?? $last));
    $notifMsg = sprintf('%s requested %s.', $clientFullName, implode(', ', $seedNames));

    $firstApprovalId = $approval_ids[0] ?? null; // reference one approval (schema supports only one)
    $insNotif = $pdo->prepare('
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, "from", "to")
        VALUES (:approval_id, NULL, :msg, FALSE, :from_user, :to_dept)
        RETURNING notif_id
    ');
    $insNotif->execute([
        ':approval_id' => $firstApprovalId,
        ':msg' => $notifMsg,
        ':from_user' => $user_id,
        ':to_dept' => 'Seedling',
    ]);
    $notif_id = (string)$insNotif->fetchColumn();

    $pdo->commit();

    $successPayload = [
        'needs_decision' => false,
        'mode_used' => $mode,
        'client_id' => $client_id,
        'client_signature_url' => $sigUrl, // null in reuse mode
        'requirement_id' => $requirement_id,
        'seedling_request_ids' => $seed_req_ids,
        'approval_ids' => $approval_ids,
        'notification_ids' => [$notif_id],
        'application_form_url' => $fileUrl,
        'application_id' => $application_id,
    ];

    $_SESSION['last_seedling_submission'] = [
        'hash' => $fingerprint,
        'ts' => time(),
        'response' => $successPayload,
    ];

    out(true, '', $successPayload);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    out(false, $e->getMessage());
}
