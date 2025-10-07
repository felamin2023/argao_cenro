<?php
// save_wood.php
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
            'x-upsert: false', // unique path per submission → no overwrite
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

function column_exists(PDO $pdo, string $schema, string $table, string $column): bool
{
    $q = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = :s AND table_name = :t AND column_name = :c
    LIMIT 1
  ");
    $q->execute([':s' => $schema, ':t' => $table, ':c' => $column]);
    return (bool)$q->fetchColumn();
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

    /* --------------------- Inputs (WOOD page) --------------------- */
    // Required by UI: 'permit_type' is 'new' | 'renewal'
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    // Names come from the top section (new vs renewal) – frontend already picks which to send
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');

    // General (NEW)
    $new_business_address = trim($_POST['new_business_address'] ?? '');
    $new_plant_location   = trim($_POST['new_plant_location']   ?? '');
    $new_contact_number   = trim($_POST['new_contact_number']   ?? '');
    $new_email_address    = trim($_POST['new_email_address']    ?? '');
    $new_ownership_type   = trim($_POST['new_ownership_type']   ?? '');

    // General (RENEWAL)
    $r_address         = trim($_POST['r_address']        ?? '');
    $r_plant_location  = trim($_POST['r_plant_location'] ?? '');
    $r_contact_number  = trim($_POST['r_contact_number'] ?? '');
    $r_email_address   = trim($_POST['r_email_address']  ?? '');
    $r_ownership_type  = trim($_POST['r_ownership_type'] ?? '');
    $r_prev_permit     = trim($_POST['r_previous_permit'] ?? '');
    $r_expiry_date     = trim($_POST['r_expiry_date']    ?? '');

    // Shared sections
    $plant_type        = trim($_POST['plant_type']       ?? '');
    $daily_capacity    = trim($_POST['daily_capacity']   ?? '');
    $power_source      = trim($_POST['power_source']     ?? '');
    // Dynamic tables come JSON-encoded by the frontend script (rows with columns)
    $machinery_rows    = trim($_POST['machinery_rows_json'] ?? ''); // JSON string or ''
    $supply_rows       = trim($_POST['supply_rows_json']    ?? ''); // JSON string or ''
    // Declaration fields (optional)
    $declaration_name  = trim($_POST['declaration_name'] ?? '');
    $declaration_addr  = trim($_POST['declaration_address'] ?? '');

    $complete_name = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));

    // For application_form mapping, re-use present_address/location
    $present_address = $permit_type === 'renewal' ? $r_address : $new_business_address;
    $plant_location  = $permit_type === 'renewal' ? $r_plant_location : $new_plant_location;
    $contact_number  = $permit_type === 'renewal' ? $r_contact_number : $new_contact_number;
    $email_address   = $permit_type === 'renewal' ? $r_email_address : $new_email_address;
    $ownership_type  = $permit_type === 'renewal' ? $r_ownership_type : $new_ownership_type;

    /* --------------------- Server-side PRECHECK ------------------- */
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

    // Client row
    if ($existing_client_id) {
        $client_id = $existing_client_id;
    } else {
        $stmt = $pdo->prepare("
      INSERT INTO public.client
        (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
      VALUES
        (:uid, :first, :middle, :last, NULL, NULL, :location, :contact)
      RETURNING client_id
    ");
        $stmt->execute([
            ':uid'     => $user_uuid,
            ':first'   => $first_name,
            ':middle'  => $middle_name,
            ':last'    => $last_name,
            ':location' => $plant_location ?: null,
            ':contact' => $contact_number ?: null,
        ]);
        $client_id = $stmt->fetchColumn();
        if (!$client_id) throw new Exception('Failed to create client record');
    }

    // ---- Status flags (WOOD flow) ----
    // PENDING NEW
    $stmt = $pdo->prepare("
    SELECT 1 FROM public.approval
    WHERE client_id = :cid
      AND lower(request_type) = 'wood'
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
      AND lower(request_type) = 'wood'
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
      AND lower(request_type) = 'wood'
      AND lower(permit_type)  = 'renewal'
      AND lower(approval_status) = 'pending'
    LIMIT 1
  ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingRenewal = (bool)$stmt->fetchColumn();

    // Hard rules (mirror chainsaw semantics)
    if ($permit_type === 'renewal') {
        if ($hasPendingRenewal) {
            throw new Exception('You already have a pending RENEWAL wood application.');
        }
        if (!$hasApprovedNew) {
            throw new Exception('To file a renewal, you must have an APPROVED new wood processing plant permit on record.');
        }
    } else { // NEW
        if ($hasPendingNew) {
            throw new Exception('You already have a pending NEW wood application.');
        }
        if ($hasPendingRenewal) {
            throw new Exception('You have a pending RENEWAL; please wait for the update first.');
        }
    }

    /* ----------------- requirements row & uploads ----------------- */
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();

    // Unique submission key & folder
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    // wood/new permit/<client_id>/<run>/... OR wood/renewal permit/<client_id>/<run>/...
    $permitFolder = ($permit_type === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "wood/{$permitFolder}/{$client_id}/{$run}/";

    // Collect all uploaded files (we'll store a map: input_name => public_url)
    $uploaded_map = [];

    // Application doc (MHTML .doc generated by the frontend) – optional but expected
    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $f = $_FILES['application_doc'];
        $safe = slugify_name("application_doc__" . ($f['name'] ?: 'wood_application.doc'));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= '.doc';
        $uploaded_map['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
    }

    // Signature image (optional)
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $f = $_FILES['signature_file'];
        $ext = pick_ext($f, '.png');
        $safe = slugify_name("signature__signature{$ext}");
        $uploaded_map['signature'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'image/png');
    }

    // Every other file input from the WOOD page – accept & upload generically
    // Known IDs from the UI (NEW: a..l, o2,o3,o4,o5,o7 ; RENEWAL: r1..r7) but we’ll just loop $_FILES.
    foreach ($_FILES as $inputName => $file) {
        if (in_array($inputName, ['application_doc', 'signature_file'], true)) continue;
        if (empty($file) || !is_uploaded_file($file['tmp_name'])) continue;
        $ext  = pick_ext($file, '.bin');
        $safe = slugify_name("{$inputName}__" . ($file['name'] ?: "file{$ext}"));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= $ext;
        $url = supa_upload($bucket, $prefix . $safe, $file['tmp_name'], $file['type'] ?: 'application/octet-stream');
        $uploaded_map[$inputName] = $url;
    }

    // Try to persist uploads into requirements table if a JSON column exists
    $hasUploadsJson = column_exists($pdo, 'public', 'requirements', 'uploads_json');
    if ($hasUploadsJson && $uploaded_map) {
        $stmt = $pdo->prepare("UPDATE public.requirements SET uploads_json = :j WHERE requirement_id = :rid");
        $stmt->execute([':j' => json_encode($uploaded_map, JSON_UNESCAPED_SLASHES), ':rid' => $requirement_id]);
    } else {
        // Fall back: store at least application_form/signature if columns exist (best-effort)
        $set = [];
        $params = [':rid' => $requirement_id];
        if (isset($uploaded_map['application_form']) && column_exists($pdo, 'public', 'requirements', 'application_form')) {
            $set[] = "application_form = :application_form";
            $params[':application_form'] = $uploaded_map['application_form'];
        }
        if (isset($uploaded_map['signature']) && column_exists($pdo, 'public', 'requirements', 'signature')) {
            $set[] = "signature = :signature";
            $params[':signature'] = $uploaded_map['signature'];
        }
        if ($set) {
            $sql = "UPDATE public.requirements SET " . implode(', ', $set) . " WHERE requirement_id = :rid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    /* ---------------- application_form row (generic) --------------- */
    // We’ll re-use the existing application_form table, setting application_for='wood'
    // and encode wood-specific fields in `additional_information`.
    $extra = [
        "submission_key"   => $run,
        "email_address"    => $email_address,
        "ownership_type"   => $ownership_type,
        "plant_type"       => $plant_type,
        "daily_capacity"   => $daily_capacity,
        "power_source"     => $power_source,
        "machinery_rows"   => $machinery_rows !== '' ? json_decode($machinery_rows, true) : null,
        "supply_rows"      => $supply_rows !== '' ? json_decode($supply_rows, true) : null,
        "declaration_name" => $declaration_name ?: null,
        "declaration_addr" => $declaration_addr ?: null,
    ];
    if ($permit_type === 'renewal') {
        $extra['previous_permit_no'] = $r_prev_permit ?: null;
        $extra['expiry_date_src']    = $r_expiry_date   ?: null;
    }
    // If the client wants to quickly see the raw uploads, include them here too
    if ($uploaded_map) $extra['uploads'] = $uploaded_map;

    $stmt = $pdo->prepare("
    INSERT INTO public.application_form
      (client_id, contact_number, application_for, type_of_permit,
       brand, model, date_of_acquisition, horsepower, maximum_length_of_guide_bar,
       complete_name, present_address, province, location,
       serial_number_chainsaw, signature_of_applicant,
       purpose_of_use, additional_information,
       permit_number, expiry_date, date_today)
    VALUES
      (:client_id, :contact_number, 'wood', :permit_type,
       NULL, NULL, NULL, NULL, NULL,
       :complete_name, :present_address, NULL, :location,
       NULL, :signature_url,
       NULL, :additional_info,
       :permit_number, :expiry_date, to_char(now(),'YYYY-MM-DD'))
    RETURNING application_id
  ");
    $stmt->execute([
        ':client_id'       => $client_id,
        ':contact_number'  => $contact_number ?: null,
        ':permit_type'     => $permit_type,
        ':complete_name'   => $complete_name,
        ':present_address' => $present_address ?: null,
        ':location'        => $plant_location ?: null,
        ':signature_url'   => $uploaded_map['signature'] ?? null,
        ':additional_info' => json_encode($extra, JSON_UNESCAPED_SLASHES),
        ':permit_number'   => $permit_type === 'renewal' ? ($r_prev_permit ?: null) : null,
        ':expiry_date'     => $permit_type === 'renewal' ? ($r_expiry_date ?: null) : null,
    ]);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    /* ---------------------- approval row -------------------------- */
    $stmt = $pdo->prepare("
    INSERT INTO public.approval
      (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
    VALUES
      (:client_id, :requirement_id, 'wood', 'pending', NULL, :permit_type, :application_id, now())
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

    /* ------------------- admin notification ----------------------- */
    $nicePermit = strtolower($permit_type); // "new" or "renewal"
    $msg = sprintf('%s requested a wood %s permit.', $first_name ?: $complete_name, $nicePermit);
    $stmt = $pdo->prepare("
    INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
    VALUES (:approval_id, NULL, :message, false, :from_user, :to_value)
    RETURNING notif_id
  ");
    $stmt->execute([
        ':approval_id' => $approval_id,
        ':message'     => $msg,
        ':from_user'   => $user_uuid,     // current logged-in user
        ':to_value'    => 'Tree Cutting', // requested target value
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
        // useful echoes for frontend debugging
        'uploaded'        => $uploaded_map,
    ]);
} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
