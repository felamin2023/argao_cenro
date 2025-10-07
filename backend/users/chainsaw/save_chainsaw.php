<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

/* ----------------------- storage helpers ----------------------- */
const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_REQUIREMENTS_BUCKET;
}

/** URL-encode each segment of the storage path so spaces & unicode are safe */
function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    $segments = explode('/', $path);
    $segments = array_map('rawurlencode', $segments);
    return implode('/', $segments);
}

function supa_public_url(string $bucket, string $path): string
{
    $encoded = encode_path_segments($path);
    return rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/{$bucket}/{$encoded}";
}

function supa_upload(string $bucket, string $path, string $tmpPath, string $mime): string
{
    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            // Do NOT overwrite existing files; each submission has its own folder anyway.
            'x-upsert: false',
        ],
        CURLOPT_POSTFIELDS     => file_get_contents($tmpPath),
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $code >= 300) {
        $err = $resp ?: curl_error($ch);
        curl_close($ch);
        throw new Exception("Storage upload failed ({$code}): {$err}");
    }
    curl_close($ch);
    return supa_public_url($bucket, $path);
}

function slugify_name(string $s): string
{
    $s = preg_replace('~[^\pL\d._-]+~u', '_', $s);
    $s = trim($s, '_');
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('~[^-\w._]+~', '', $s);
    $s = preg_replace('~_+~', '_', $s);
    return strtolower($s ?: 'file');
}
function pick_ext(array $file, string $fallback): string
{
    $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
    return $ext ? ('.' . strtolower($ext)) : $fallback;
}
function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}

/* ----------------------------- main ---------------------------- */
try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');
    if (!$pdo) throw new Exception('DB not available');

    // users.user_id lookup
    $idOrUuid = (string)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
    $stmt->execute([':v' => $idOrUuid]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = $urow['user_id'];

    // Inputs
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $contact_num  = trim($_POST['contact_number'] ?? '');
    $sitio_street = trim($_POST['sitio_street'] ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $province     = trim($_POST['province']     ?? '');

    $permit_number = trim($_POST['permit_number'] ?? '');
    $issuance_date = trim($_POST['issuance_date'] ?? ''); // (not a dedicated column)
    $expiry_date   = trim($_POST['expiry_date']   ?? '');

    $purpose             = trim($_POST['purpose']              ?? '');
    $brand               = trim($_POST['brand']                ?? '');
    $model               = trim($_POST['model']                ?? '');
    $date_of_acquisition = trim($_POST['date_of_acquisition']  ?? '');
    $serial_number       = trim($_POST['serial_number']        ?? '');
    $horsepower          = trim($_POST['horsepower']           ?? '');
    $max_guide           = trim($_POST['maximum_length_of_guide_bar'] ?? '');

    $complete_name   = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));
    $present_address = implode(', ', array_filter([$sitio_street, $barangay, $municipality, $province]));

    // --- Server-side PRECHECK (hard rules) ---
    $nf = norm($first_name);
    $nm = norm($middle_name);
    $nl = norm($last_name);

    // Try normalized columns; fallback
    $hasNormCols = false;
    try {
        $pdo->query("SELECT norm_first, norm_middle, norm_last FROM public.client LIMIT 0");
        $hasNormCols = true;
    } catch (\Throwable $ignored) {
    }

    if ($hasNormCols) {
        $stmt = $pdo->prepare("SELECT client_id FROM public.client WHERE norm_first=:f AND norm_middle=:m AND norm_last=:l LIMIT 1");
    } else {
        $stmt = $pdo->prepare("
            SELECT client_id
            FROM public.client
            WHERE lower(trim(coalesce(first_name,'')))  = :f
              AND lower(trim(coalesce(middle_name,''))) = :m
              AND lower(trim(coalesce(last_name,'')))   = :l
            LIMIT 1
        ");
    }
    $stmt->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
    $existing_client_id = $stmt->fetchColumn() ?: null;

    $pdo->beginTransaction();

    // --- Client: reuse if found, else create ---
    if ($existing_client_id) {
        $client_id = $existing_client_id;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO public.client
                (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
            VALUES
                (:uid, :first, :middle, :last, :sitio, :brgy, :mun, :contact)
            RETURNING client_id
        ");
        $stmt->execute([
            ':uid'     => $user_uuid,
            ':first'   => $first_name,
            ':middle'  => $middle_name,
            ':last'    => $last_name,
            ':sitio'   => $sitio_street,
            ':brgy'    => $barangay,
            ':mun'     => $municipality,
            ':contact' => $contact_num,
        ]);
        $client_id = $stmt->fetchColumn();
        if (!$client_id) throw new Exception('Failed to create client record');
    }

    // ---- Status flags on this client ----
    // PENDING NEW
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'chainsaw'
          AND lower(permit_type)  = 'new'
          AND lower(approval_status) = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingNew = (bool)$stmt->fetchColumn();

    // APPROVED NEW
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'chainsaw'
          AND lower(permit_type)  = 'new'
          AND lower(approval_status) = 'approved'
        ORDER BY approved_at DESC NULLS LAST
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasApprovedNew = (bool)$stmt->fetchColumn();

    // PENDING RENEWAL
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'chainsaw'
          AND lower(permit_type)  = 'renewal'
          AND lower(approval_status) = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingRenewal = (bool)$stmt->fetchColumn();

    // ---- Enforce rules (HARD BLOCKS) ----
    if ($permit_type === 'renewal') {
        if ($hasPendingRenewal) {
            throw new Exception('You already have a pending RENEWAL chainsaw application.');
        }
        if (!$hasApprovedNew) {
            throw new Exception('To file a renewal, you must have an APPROVED new chainsaw permit on record.');
        }
    } else { // NEW
        if ($hasPendingNew) {
            throw new Exception('You already have a pending NEW chainsaw application.');
        }
        if ($hasPendingRenewal) {
            throw new Exception('You have a pending RENEWAL; please wait for the update first.');
        }
    }

    // --- requirements row ---
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();

    // --------- UNIQUE submission key & folder (no overwrites, keep every request) ----------
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    // chainsaw/new permit/<client_id>/<run>/...  OR  chainsaw/renewal permit/<client_id>/<run>/...
    $permitFolder = ($permit_type === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "chainsaw/{$permitFolder}/{$client_id}/{$run}/";
    // ---------------------------------------------------------------------------------------

    // --- uploads ---
    $urls = [
        'chainsaw_cert_terms'       => null,
        'chainsaw_cert_sticker'     => null,
        'chainsaw_staff_work'       => null,
        'geo_photos'                => null,
        'chainsaw_permit_to_sell'   => null,
        'chainsaw_business_permit'  => null,
        'chainsaw_old_registration' => null,
        'application_form'          => null,
        'signature'                 => null,
    ];

    // Generated application document (Word via MHTML) from the frontend
    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $f = $_FILES['application_doc'];
        $safe = slugify_name("application_doc__" . ($f['name'] ?: 'chainsaw.doc'));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= '.doc';
        $urls['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
    }

    // Signature (optional)
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $f = $_FILES['signature_file'];
        $ext = pick_ext($f, '.png');
        $safe = slugify_name("signature__signature{$ext}");
        $urls['signature'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'image/png');
    }

    // Other uploads
    $fileMap = [
        'chainsaw_cert_terms'       => 'chainsaw_cert_terms',
        'chainsaw_cert_sticker'     => 'chainsaw_cert_sticker',
        'chainsaw_staff_work'       => 'chainsaw_staff_work',
        'geo_photos'                => 'geo_photos',
        'chainsaw_permit_to_sell'   => 'chainsaw_permit_to_sell',
        'chainsaw_business_permit'  => 'chainsaw_business_permit',
        'chainsaw_old_registration' => 'chainsaw_old_registration',
    ];
    foreach ($fileMap as $postField => $dbCol) {
        if (!empty($_FILES[$postField]) && is_uploaded_file($_FILES[$postField]['tmp_name'])) {
            $f = $_FILES[$postField];
            $ext = pick_ext($f, '.bin');
            $safe = slugify_name("{$postField}__" . ($f['name'] ?: "file{$ext}"));
            if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= $ext;
            $urls[$dbCol] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/octet-stream');
        }
    }

    // Update requirements row with uploaded urls
    $set = [];
    $params = [':rid' => $requirement_id];
    foreach ($fileMap as $postField => $dbCol) {
        if ($urls[$dbCol]) {
            $set[] = "{$dbCol} = :{$dbCol}";
            $params[":{$dbCol}"] = $urls[$dbCol];
        }
    }
    if ($urls['application_form']) {
        $set[] = "application_form = :application_form";
        $params[":application_form"] = $urls['application_form'];
    }
    if ($set) {
        $sql = "UPDATE public.requirements SET " . implode(', ', $set) . " WHERE requirement_id = :rid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // --- application_form row ---
    // Build additional_information (store submission_key and optional issuance_date)
    $extraInfo = ["submission_key={$run}"];
    if ($issuance_date !== '') $extraInfo[] = "issuance_date={$issuance_date}";
    $additional_information = implode('; ', $extraInfo);

    $stmt = $pdo->prepare("
        INSERT INTO public.application_form
          (client_id, contact_number, application_for, type_of_permit,
           brand, model, date_of_acquisition, horsepower, maximum_length_of_guide_bar,
           complete_name, present_address, province, location,
           serial_number_chainsaw, signature_of_applicant,
           purpose_of_use, additional_information,
           permit_number, expiry_date, date_today)
        VALUES
          (:client_id, :contact_number, 'chainsaw', :permit_type,
           :brand, :model, :date_of_acq, :horsepower, :max_guide,
           :complete_name, :present_address, :province, :location,
           :serial_number, :signature_url,
           :purpose, :additional_info,
           :permit_number, :expiry_date, to_char(now(),'YYYY-MM-DD'))
        RETURNING application_id
    ");
    $stmt->execute([
        ':client_id'       => $client_id,
        ':contact_number'  => $contact_num,
        ':permit_type'     => $permit_type,
        ':brand'           => $brand,
        ':model'           => $model,
        ':date_of_acq'     => $date_of_acquisition,
        ':horsepower'      => $horsepower,
        ':max_guide'       => $max_guide,
        ':complete_name'   => $complete_name,
        ':present_address' => $present_address,
        ':province'        => $province,
        ':location'        => $municipality,
        ':serial_number'   => $serial_number,
        ':signature_url'   => $urls['signature'] ?? null,
        ':purpose'         => $purpose,
        ':additional_info' => $additional_information ?: null,
        ':permit_number'   => $permit_number ?: null,
        ':expiry_date'     => $expiry_date ?: null,
    ]);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    // --- approval row ---
    $stmt = $pdo->prepare("
        INSERT INTO public.approval
          (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
        VALUES
          (:client_id, :requirement_id, 'chainsaw', 'pending', NULL, :permit_type, :application_id, now())
        RETURNING approval_id
    ");
    $stmt->execute([
        ':client_id'      => $client_id,
        ':requirement_id' => $requirement_id,
        ':permit_type'    => $permit_type,
        ':application_id' => $application_id,
    ]);
    $approval_id = $stmt->fetchColumn();
    if (!$approval_id) throw new Exception('Failed to create approval record');

    // --- admin notification (now with "from" and "to") ---
    $nicePermit = strtolower($permit_type); // "new" or "renewal"
    $msg = sprintf('%s requested a chainsaw %s permit.', $first_name ?: $complete_name, $nicePermit);
    $stmt = $pdo->prepare("
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
        VALUES (:approval_id, NULL, :message, false, :from_user, :to_value)
        RETURNING notif_id
    ");
    $stmt->execute([
        ':approval_id' => $approval_id,
        ':message'     => $msg,
        ':from_user'   => $user_uuid,      // current logged-in user id (uuid)
        ':to_value'    => 'Tree Cutting',  // requested target value
    ]);
    $notif_id = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'ok'              => true,
        'client_id'       => $client_id,
        'requirement_id'  => $requirement_id,
        'application_id'  => $application_id,
        'approval_id'     => $approval_id,
        'notification_id' => $notif_id,
        'storage_prefix'  => $prefix,
        'bucket'          => $bucket,
        'submission_key'  => $run,
    ]);
} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
