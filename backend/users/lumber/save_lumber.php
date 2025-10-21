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
            // Do not overwrite existing files
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

    // users.user_id lookup (your schema has both id and user_id)
    $idOrUuid = (string)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
    $stmt->execute([':v' => $idOrUuid]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = $urow['user_id'];

    // Inputs
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    // Client choice flags from the UI modal
    $override_client_id = trim((string)($_POST['use_client_id'] ?? '')); // if user picked “Use existing”
    $force_new_client   = !empty($_POST['force_new_client']);            // if user picked “Create new”

    // Names (accept both new/renewal field names)
    $first_name  = trim($_POST['first_name']   ?? ($_POST['first_name_ren']   ?? ''));
    $middle_name = trim($_POST['middle_name']  ?? ($_POST['middle_name_ren']  ?? ''));
    $last_name   = trim($_POST['last_name']    ?? ($_POST['last_name_ren']    ?? ''));

    // Business/company name (accept both new/renewal field names just in case)
    $business_name = trim($_POST['business_name'] ?? ($_POST['business_name_ren'] ?? ''));

    // Basic profile (Lumber-specific fields)
    $applicant_age = trim($_POST['applicant_age'] ?? ($_POST['applicant_age_ren'] ?? ''));
    $is_govt = strtolower(trim($_POST['is_government_employee'] ?? ($_POST['is_government_employee_ren'] ?? '')));
    if (!in_array($is_govt, ['yes', 'no'], true)) $is_govt = null;

    $business_address = trim($_POST['business_address'] ?? ($_POST['business_address_ren'] ?? ''));
    $operation_place  = trim($_POST['operation_place']  ?? ($_POST['operation_place_ren']  ?? ''));

    $annual_volume = trim($_POST['annual_volume'] ?? ($_POST['annual_volume_ren'] ?? ''));
    $annual_worth  = trim($_POST['annual_worth']  ?? ($_POST['annual_worth_ren']  ?? ''));

    $employees_count  = trim($_POST['employees_count']  ?? ($_POST['employees_count_ren']  ?? ''));
    $dependents_count = trim($_POST['dependents_count'] ?? ($_POST['dependents_count_ren'] ?? ''));

    $intended_market = trim($_POST['intended_market'] ?? ($_POST['intended_market_ren'] ?? ''));
    $experience      = trim($_POST['experience']      ?? ($_POST['experience_ren']      ?? ''));
    $declaration_name = trim($_POST['declaration_name'] ?? ($_POST['declaration_name_ren'] ?? ''));

    $suppliers_json = (string)($_POST['suppliers_json'] ?? ($_POST['suppliers_json_ren'] ?? ''));
    if ($suppliers_json === '') $suppliers_json = '[]';

    // Renewal-only extras (accept a few aliases)
    $prev_cert_no  = trim($_POST['prev_certificate_no']
        ?? $_POST['prev_certificate']
        ?? $_POST['previous_certificate']
        ?? ($_POST['permit_number'] ?? ''));
    $issued_date   = trim($_POST['issued_date'] ?? '');
    $expiry_date   = trim($_POST['expiry_date'] ?? ($_POST['expires_on'] ?? ''));
    $cr_license_no = trim($_POST['cr_license_no'] ?? '');
    $sawmill_permit_no = trim($_POST['sawmill_permit_no'] ?? '');
    $buying_from_other_sources = strtolower(trim($_POST['buying_from_other_sources'] ?? ''));
    if (!in_array($buying_from_other_sources, ['yes', 'no'], true)) $buying_from_other_sources = null;

    $complete_name = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));

    // --- Server-side PRECHECK (hard rules) ---
    $nf = norm($first_name);
    $nm = norm($middle_name);
    $nl = norm($last_name);

    // Try normalized columns; fallback to case-insensitive
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

    // --- Client: honor user choice (override or force new), else reuse by exact name, else create ---
    $client_id = null;

    // 1) If the UI sent an explicit existing id, verify and use it
    if ($override_client_id !== '') {
        $chk = $pdo->prepare("SELECT client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $chk->execute([':cid' => $override_client_id]);
        $client_id = $chk->fetchColumn() ?: null;
        if (!$client_id) {
            throw new Exception('Invalid existing client id.');
        }

        // When using existing client, enforce DB names and force declaration = FIRST + LAST
        $nq = $pdo->prepare("SELECT first_name, middle_name, last_name FROM public.client WHERE client_id = :cid LIMIT 1");
        $nq->execute([':cid' => $client_id]);
        $nr = $nq->fetch(PDO::FETCH_ASSOC) ?: [];

        $first_name  = (string)($nr['first_name']  ?? $first_name);
        $middle_name = (string)($nr['middle_name'] ?? $middle_name);
        $last_name   = (string)($nr['last_name']   ?? $last_name);
        $declaration_name = trim($first_name . ' ' . $last_name);
    }

    // 2) If no override and user explicitly wants a new client, create it now
    if (!$client_id && $force_new_client) {
        $stmt = $pdo->prepare("
            INSERT INTO public.client
              (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
            VALUES
              (:uid, :first, :middle, :last, NULL, NULL, NULL, NULL)
            RETURNING client_id
        ");
        $stmt->execute([
            ':uid'    => $user_uuid,
            ':first'  => $first_name,
            ':middle' => $middle_name,
            ':last'   => $last_name,
        ]);
        $client_id = $stmt->fetchColumn();
        if (!$client_id) throw new Exception('Failed to create client record');
    }

    // 3) Otherwise, reuse by exact name if found, else create
    if (!$client_id) {
        if ($existing_client_id) {
            $client_id = $existing_client_id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO public.client
                  (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
                VALUES
                  (:uid, :first, :middle, :last, NULL, NULL, NULL, NULL)
                RETURNING client_id
            ");
            $stmt->execute([
                ':uid'    => $user_uuid,
                ':first'  => $first_name,
                ':middle' => $middle_name,
                ':last'   => $last_name,
            ]);
            $client_id = $stmt->fetchColumn();
            if (!$client_id) throw new Exception('Failed to create client record');
        }
    }

    /* ---- Status flags on this client (LUMBER) ----
       NOTE: your approval_status allowed values are:
             'pending', 'for payment', 'released', 'canceled', 'rejected'
    */

    // Global: any "for payment" on lumber?
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'lumber'
          AND lower(approval_status) = 'for payment'
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasForPayment = (bool)$stmt->fetchColumn();

    // PENDING NEW
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'lumber'
          AND lower(permit_type)  = 'new'
          AND lower(approval_status) = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingNew = (bool)$stmt->fetchColumn();

    // RELEASED NEW
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'lumber'
          AND lower(permit_type)  = 'new'
          AND lower(approval_status) = 'released'
        ORDER BY approved_at DESC NULLS LAST
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasReleasedNew = (bool)$stmt->fetchColumn();

    // PENDING RENEWAL
    $stmt = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id = :cid
          AND lower(request_type) = 'lumber'
          AND lower(permit_type)  = 'renewal'
          AND lower(approval_status) = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingRenewal = (bool)$stmt->fetchColumn();

    // ---- Enforce rules (HARD BLOCKS) ----
    if ($hasForPayment) {
        throw new Exception('You have a Lumber application marked FOR PAYMENT. Please settle it before filing another.');
    }

    if ($permit_type === 'renewal') {
        if ($hasPendingRenewal) {
            throw new Exception('You already have a pending RENEWAL lumber application.');
        }
        if (!$hasReleasedNew) {
            throw new Exception('To file a renewal, you must have a RELEASED new lumber dealer permit on record.');
        }
        // NEW: Do not allow renewal if any released (new/renewal) lumber permit is still unexpired (approved_docs.expiry_date)
        $uq = $pdo->prepare("
  SELECT 1
  FROM public.approval a
  JOIN public.approved_docs d ON d.approval_id = a.approval_id
  WHERE a.client_id = :cid
    AND a.request_type ILIKE 'lumber'
    AND a.approval_status ILIKE 'released'
    AND a.permit_type ILIKE ANY (ARRAY['new','renewal'])
    AND d.expiry_date IS NOT NULL
    AND d.expiry_date::date >= CURRENT_DATE
  LIMIT 1
");
        $uq->execute([':cid' => $client_id]);
        if ($uq->fetchColumn()) {
            throw new Exception('You still have an unexpired lumber permit. Please wait until it expires before requesting a renewal.');
        }
    } else { // NEW
        if ($hasPendingNew) {
            throw new Exception('You already have a pending NEW lumber application.');
        }
        if ($hasPendingRenewal) {
            throw new Exception('You have a pending RENEWAL; please wait for the update first.');
        }
        if ($hasReleasedNew) {
            throw new Exception('You already have a RELEASED NEW lumber dealer permit; please file a renewal instead.');
        }
    }

    // --- requirements row ---
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();

    // Unique submission key & folder (prevents overwrites and keeps every request)
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

    // Folder structure: lumber/new permit/<client_id>/<run>/...  OR  lumber/renewal permit/<client_id>/<run>/...
    $permitFolder = ($permit_type === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "lumber/{$permitFolder}/{$client_id}/{$run}/";

    // --- uploads container ---
    $urls = [
        'lumber_csw_document'             => null, // 1
        'geo_photos'                      => null, // 2
        'application_form'                => null, // generated doc
        'lumber_supply_contract'          => null, // 4
        'lumber_business_plan'            => null, // 5 (new only)
        'lumber_mayors_permit'            => null, // 6
        'lumber_registration_certificate' => null, // 7
        'lumber_tax_return'               => null, // 8 (new only)
        'lumber_monthly_reports'          => null, // 9 (renewal only)
        'lumber_or_copy'                  => null, // 10a
        'lumber_op_copy'                  => null, // 10b
        'signature'                       => null,
    ];

    // generated application document (Word via MHTML)
    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $f = $_FILES['application_doc'];
        $safe = slugify_name("application_doc__" . ($f['name'] ?: 'lumber.doc'));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= '.doc';
        $urls['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
    }
    // signature file (optional)
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $f = $_FILES['signature_file'];
        $ext = pick_ext($f, '.png');
        $safe = slugify_name("signature__signature{$ext}");
        $urls['signature'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'image/png');
    }

    // Other uploads (from the page)
    $fileMap = [
        'lumber_csw_document'             => 'lumber_csw_document',          // file-1
        'geo_photos'                      => 'geo_photos',                    // file-2
        'lumber_supply_contract'          => 'lumber_supply_contract',        // file-4
        'lumber_business_plan'            => 'lumber_business_plan',          // file-5 (new)
        'lumber_mayors_permit'            => 'lumber_mayors_permit',          // file-6
        'lumber_registration_certificate' => 'lumber_registration_certificate', // file-7
        'lumber_tax_return'               => 'lumber_tax_return',             // file-8 (new)
        'lumber_monthly_reports'          => 'lumber_monthly_reports',        // file-9 (renewal)
        'lumber_or_copy'                  => 'lumber_or_copy',                // file-10a
        'lumber_op_copy'                  => 'lumber_op_copy',                // file-10b
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

    // --- application_form row (LUMBER) ---
    $present_address = $business_address;
    $province = null;
    $location = $operation_place;

    // Additional info blob for misc fields (and to keep storage run key)
    $extraInfo = [];
    if ($sawmill_permit_no !== '') $extraInfo[] = "sawmill_permit_no={$sawmill_permit_no}";
    $extraInfo[] = "submission_key={$run}";
    $additional_info = $extraInfo ? implode('; ', $extraInfo) : null;

    $stmt = $pdo->prepare("
        INSERT INTO public.application_form
          (client_id, contact_number, application_for, type_of_permit,
           complete_name, company_name, present_address, province, location,
           applicant_age, is_government_employee,
           proposed_place_of_operation, expected_annual_volume, estimated_annual_worth,
           total_number_of_employees, total_number_of_dependents,
           intended_market, my_experience_as_alumber_dealer, declaration_name,
           suppliers_json, signature_of_applicant,
           permit_number, expiry_date, cr_license_no, buying_from_other_sources,
           additional_information, date_today)
        VALUES
          (:client_id, NULL, 'lumber', :permit_type,
           :complete_name, :company_name, :present_address, :province, :location,
           :applicant_age, :is_govt,
           :operation_place, :annual_volume, :annual_worth,
           :employees_count, :dependents_count,
           :intended_market, :experience, :declaration_name,
           :suppliers_json, :signature_url,
           :permit_number, :expiry_date, :cr_license_no, :buying_sources,
           :additional_info, to_char(now(),'YYYY-MM-DD'))
        RETURNING application_id
    ");
    $stmt->execute([
        ':client_id'        => $client_id,
        ':permit_type'      => $permit_type,
        ':complete_name'    => $complete_name,
        ':company_name'     => ($business_name !== '') ? $business_name : null,
        ':present_address'  => $present_address ?: null,
        ':province'         => $province,
        ':location'         => $location ?: null,
        ':applicant_age'    => $applicant_age ?: null,
        ':is_govt'          => $is_govt,
        ':operation_place'  => $operation_place ?: null,
        ':annual_volume'    => $annual_volume ?: null,
        ':annual_worth'     => $annual_worth ?: null,
        ':employees_count'  => $employees_count ?: null,
        ':dependents_count' => $dependents_count ?: null,
        ':intended_market'  => $intended_market ?: null,
        ':experience'       => $experience ?: null,
        ':declaration_name' => $declaration_name ?: $complete_name,
        ':suppliers_json'   => $suppliers_json,
        ':signature_url'    => $urls['signature'] ?? null,
        ':permit_number'    => $prev_cert_no ?: null,
        ':expiry_date'      => $expiry_date ?: null,
        ':cr_license_no'    => $cr_license_no ?: null,
        ':buying_sources'   => $buying_from_other_sources,
        ':additional_info'  => $additional_info,
    ]);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    // --- approval row ---
    $stmt = $pdo->prepare("
        INSERT INTO public.approval
          (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
        VALUES
          (:client_id, :requirement_id, 'lumber', 'pending', NULL, :permit_type, :application_id, now())
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

    // --- admin notification (INCLUDES "from" and "to") ---
    $nicePermit = strtolower($permit_type); // "new" or "renewal"
    $msg = sprintf('%s requested a lumber %s permit.', $first_name ?: $complete_name, $nicePermit);
    $stmt = $pdo->prepare("
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
        VALUES (:approval_id, NULL, :message, false, :from_user, :to_value)
        RETURNING notif_id
    ");
    $stmt->execute([
        ':approval_id' => $approval_id,
        ':message'     => $msg,
        ':from_user'   => (string)$user_uuid,
        ':to_value'    => 'admin',
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'approval_id' => (string)$approval_id,
        'application_id' => (string)$application_id,
        'message' => 'Lumber application submitted.'
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
