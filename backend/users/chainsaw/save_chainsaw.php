<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // provides $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

/* ---------- helpers ---------- */
const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_REQUIREMENTS_BUCKET;
}
function supa_public_url(string $bucket, string $path): string
{
    return rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/{$bucket}/" . ltrim($path, '/');
}
function supa_upload(string $bucket, string $path, string $tmpPath, string $mime): string
{
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/" . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
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

/* ---------- main ---------- */
try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');

    // users.user_id lookup (schema: public.users has user_id UUID)
    $idOrUuid = (string)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
    $stmt->execute([':v' => $idOrUuid]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = $urow['user_id'];

    /* read POST */
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $contact_num  = trim($_POST['contact_number'] ?? ''); // goes to application_form.contact_number
    $sitio_street = trim($_POST['sitio_street'] ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $province     = trim($_POST['province']     ?? '');

    // renewal extras (optional)
    $permit_number = trim($_POST['permit_number'] ?? '');
    $issuance_date = trim($_POST['issuance_date'] ?? ''); // no exact column; we won’t store unless you want a mapping
    $expiry_date   = trim($_POST['expiry_date']   ?? '');

    // chainsaw info
    $purpose             = trim($_POST['purpose']              ?? '');
    $brand               = trim($_POST['brand']                ?? '');
    $model               = trim($_POST['model']                ?? '');
    $date_of_acquisition = trim($_POST['date_of_acquisition']  ?? '');
    $serial_number       = trim($_POST['serial_number']        ?? '');
    $horsepower          = trim($_POST['horsepower']           ?? '');
    $max_guide           = trim($_POST['maximum_length_of_guide_bar'] ?? '');

    $complete_name = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));
    $present_address = implode(', ', array_filter([$sitio_street, $barangay, $municipality, $province]));

    $pdo->beginTransaction();

    /* 1) NEW client per submission (use only existing columns in public.client) */
    $clientSql = "
    INSERT INTO public.client
      (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality)
    VALUES
      (:uid, :first, :middle, :last, :sitio, :brgy, :mun)
    RETURNING client_id
  ";
    $stmt = $pdo->prepare($clientSql);
    $stmt->execute([
        ':uid'    => $user_uuid,
        ':first'  => $first_name,
        ':middle' => $middle_name,
        ':last'   => $last_name,
        ':sitio'  => $sitio_street,
        ':brgy'   => $barangay,
        ':mun'    => $municipality,
    ]);
    $client_id = $stmt->fetchColumn();
    if (!$client_id) throw new Exception('Failed to create client record');

    /* 2) requirements row */
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();
    $prefix = "chainsaw/{$requirement_id}/";

    /* 3) uploads */
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

    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $f = $_FILES['application_doc'];
        $safe = slugify_name("application_doc__" . ($f['name'] ?: 'chainsaw.doc'));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= '.doc';
        $urls['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
    }
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $f = $_FILES['signature_file'];
        $ext = pick_ext($f, '.png');
        $safe = slugify_name("signature__signature{$ext}");
        $urls['signature'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'image/png');
    }

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

    // update requirements with uploaded URLs (only the ones we have)
    $setParts = [];
    $params = [':rid' => $requirement_id];
    foreach ($fileMap as $postField => $dbCol) {
        if ($urls[$dbCol]) {
            $setParts[] = "{$dbCol} = :{$dbCol}";
            $params[":{$dbCol}"] = $urls[$dbCol];
        }
    }
    if ($urls['application_form']) {
        $setParts[] = "application_form = :application_form";
        $params[":application_form"] = $urls['application_form'];
    }
    if ($setParts) {
        $sql = "UPDATE public.requirements SET " . implode(', ', $setParts) . " WHERE requirement_id = :rid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /* 4) application_form row */
    $appSql = "
    INSERT INTO public.application_form
      (client_id, contact_number, application_for, type_of_permit,
       brand, model, date_of_acquisition, horsepower, maximum_length_of_guide_bar,
       complete_name, present_address, province, location,
       brand_model_serial_number_of_chain_saw, signature_of_applicant, additional_information,
       permit_number, expiry_date, date_today)
    VALUES
      (:client_id, :contact_number, 'chainsaw', :permit_type,
       :brand, :model, :date_of_acq, :horsepower, :max_guide,
       :complete_name, :present_address, :province, :location,
       :serial_number, :signature_url, :purpose,
       :permit_number, :expiry_date, to_char(now(),'YYYY-MM-DD'))
    RETURNING application_id
  ";
    $stmt = $pdo->prepare($appSql);
    $stmt->execute([
        ':client_id'      => $client_id,
        ':contact_number' => $contact_num,
        ':permit_type'    => $permit_type,
        ':brand'          => $brand,
        ':model'          => $model,
        ':date_of_acq'    => $date_of_acquisition,
        ':horsepower'     => $horsepower,
        ':max_guide'      => $max_guide,
        ':complete_name'  => $complete_name,
        ':present_address' => $present_address,
        ':province'       => $province,
        ':location'       => $municipality,
        ':serial_number'  => $serial_number,
        ':signature_url'  => $urls['signature'] ?? null,
        ':purpose'        => $purpose,
        ':permit_number'  => $permit_number ?: null,
        ':expiry_date'    => $expiry_date ?: null,
    ]);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    /* 5) approval row */
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

    $pdo->commit();

    echo json_encode([
        'ok'             => true,
        'client_id'      => $client_id,
        'requirement_id' => $requirement_id,
        'application_id' => $application_id,
        'approval_id'    => $approval_id,
        'storage_prefix' => "chainsaw/{$requirement_id}/",
        'bucket'         => $bucket,
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
