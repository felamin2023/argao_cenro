<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // must define: $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

/* ===================== Helpers ===================== */
const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string {
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_REQUIREMENTS_BUCKET;
}
function supa_public_url(string $bucket, string $path): string {
    return rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/{$bucket}/" . ltrim($path, '/');
}
function supa_upload(string $bucket, string $path, string $tmpPath, string $mime): string {
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
function slugify_name(string $s): string {
    $s = preg_replace('~[^\pL\d._-]+~u', '_', $s);
    $s = trim($s, '_');
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('~[^-\w._]+~', '', $s);
    $s = preg_replace('~_+~', '_', $s);
    return strtolower($s ?: 'file');
}
function pick_ext(array $file, string $fallback): string {
    $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
    return $ext ? ('.' . strtolower($ext)) : $fallback;
}

/* ===================== Inline Debugger ===================== */
$DEBUG = (isset($_GET['debug']) && $_GET['debug']==='1') || (isset($_POST['debug']) && $_POST['debug']==='1');
$__stage = 'bootstrap'; $__sql = null; $__params = null;
function debug_payload(array $extra=[]): array {
    global $DEBUG, $__stage, $__sql, $__params;
    if (!$DEBUG) return [];
    $files = [];
    foreach ($_FILES as $k=>$f) {
        $files[$k] = [
            'name'=>$f['name']??'',
            'size'=>$f['size']??0,
            'type'=>$f['type']??'',
            'error'=>$f['error']??null,
            'present'=>!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])
        ];
    }
    return array_merge([
        'stage'=>$__stage, 'sql'=>$__sql, 'params'=>$__params,
        'session_user_id'=>$_SESSION['user_id']??null,
        'post_keys'=>array_keys($_POST), 'files'=>$files,
        'env'=>['has_SUPABASE_URL'=>defined('SUPABASE_URL'), 'has_SUPABASE_SERVICE_KEY'=>defined('SUPABASE_SERVICE_KEY')],
    ], $extra);
}

/* ===================== Main ===================== */
try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');

    // CSRF
    if (!isset($_SESSION['csrf']) || !isset($_POST['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf'])) {
        throw new Exception('Invalid CSRF token');
    }

    // users.user_id lookup (accept internal id or uuid)
    $__stage = 'user_lookup';
    $idOrUuid = (string)($_SESSION['user_id']);
    $__sql = "SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1";
    $__params = [':v'=>$idOrUuid];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = $urow['user_id'];

    /* ---------- Read POST ---------- */
    $__stage = 'read_post';
    // Map UI 'dealer_new'/'dealer_renewal' → 'new'/'renewal'
    $permit_type_raw = strtolower($_POST['permit_type'] ?? 'dealer_new');
    $permit_type = ($permit_type_raw === 'dealer_renewal' || $permit_type_raw === 'renewal') ? 'renewal' : 'new';

    // name parts (always present from UI, renewal included)
    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');

    // contact / address (may be blank on renewal)
    $contact_num  = trim($_POST['contact_number'] ?? '');
    $sitio_street = trim($_POST['sitio_street'] ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $province     = trim($_POST['province']     ?? '');

    // optional extras (top-level)
    $permit_number = trim($_POST['permit_number'] ?? '');
    $expiry_date   = trim($_POST['expiry_date']   ?? '');

    // new/renewal common fields used in INSERT
    $applicant_age    = trim($_POST['applicant_age']    ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $govt_employee    = trim($_POST['govt_employee']    ?? 'no'); // 'yes' | 'no'

    // business / others
    $operation_place  = trim($_POST['operation_place']  ?? '');
    $annual_volume    = trim($_POST['annual_volume']    ?? '');
    $annual_worth     = trim($_POST['annual_worth']     ?? '');
    $employees_count  = trim($_POST['employees_count']  ?? '');
    $dependents_count = trim($_POST['dependents_count'] ?? '');
    $intended_market  = trim($_POST['intended_market']  ?? '');
    $experience       = trim($_POST['experience']       ?? '');
    $declaration_name = trim($_POST['declaration_name'] ?? '');

    $suppliers      = json_decode($_POST['suppliers_json']      ?? '[]', true);
    $renewal_extras = json_decode($_POST['renewal_extras_json'] ?? '{}', true);
    if (!is_array($suppliers)) $suppliers = [];
    if (!is_array($renewal_extras)) $renewal_extras = [];

    $complete_name   = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));
    $present_address = implode(', ', array_filter([$sitio_street, $barangay, $municipality, $province]));
    $additional_info = json_encode([
        'operation_place'  => $operation_place,
        'annual_volume'    => $annual_volume,
        'annual_worth'     => $annual_worth,
        'employees_count'  => $employees_count,
        'dependents_count' => $dependents_count,
        'intended_market'  => $intended_market,
        'experience'       => $experience,
        'declaration_name' => $declaration_name,
        'suppliers'        => $suppliers,
        'renewal_extras'   => $renewal_extras,
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

    /* ---------- DUPLICATE CHECK (before any uploads/inserts) ---------- */
    $__stage = 'dup_check';
    $__sql = "
      SELECT a.application_id, a.type_of_permit, ap.submitted_at, ap.approval_status
      FROM public.approval ap
      JOIN public.application_form a ON a.application_id = ap.application_id
      JOIN public.client c ON c.client_id = a.client_id
      WHERE ap.request_type = 'lumber'
        AND lower(a.type_of_permit) = lower(:permit_type)
        AND lower(c.first_name) = lower(:first_name)
        AND lower(coalesce(c.middle_name,'')) = lower(coalesce(:middle_name,''))
        AND lower(c.last_name)  = lower(:last_name)
      ORDER BY ap.submitted_at DESC
      LIMIT 1
    ";
    $__params = [
        ':permit_type'=>$permit_type,
        ':first_name'=>$first_name,
        ':middle_name'=>$middle_name,
        ':last_name'=>$last_name,
    ];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $dup = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dup) {
        $payload = array_merge([
            'ok' => false,
            'code' => 'duplicate',
            'message' => 'A lumber permit with the same name and permit type already exists.',
            'existing' => [
                'application_id'  => $dup['application_id'],
                'type_of_permit'  => $dup['type_of_permit'],
                'approval_status' => $dup['approval_status'],
                'submitted_at'    => $dup['submitted_at'],
            ],
        ], $DEBUG ? debug_payload() : []);
        http_response_code(409);
        echo json_encode($payload);
        exit;
    }

    /* ---------- proceed only if not duplicate ---------- */
    $pdo->beginTransaction();

    // 1) client
    $__stage = 'insert_client';
    $__sql = "
      INSERT INTO public.client
        (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality)
      VALUES
        (:uid, :first, :middle, :last, :sitio, :brgy, :mun)
      RETURNING client_id
    ";
    $__params = [
        ':uid'=>$user_uuid,
        ':first'=>$first_name,
        ':middle'=>$middle_name,
        ':last'=>$last_name,
        // store NULLs instead of empty strings
        ':sitio'=>$sitio_street !== '' ? $sitio_street : null,
        ':brgy'=>$barangay !== '' ? $barangay : null,
        ':mun'=>$municipality !== '' ? $municipality : null,
    ];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $client_id = $stmt->fetchColumn();
    if (!$client_id) throw new Exception('Failed to create client record');

    // 2) requirements
    $__stage = 'insert_requirements';
    $__sql = "INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id";
    $ridStmt = $pdo->query($__sql);
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();
    $prefix = "lumber/{$requirement_id}/";

    // 3) uploads
    $__stage = 'uploads';
    $urls = [
        'application_form'                => null, // from application_doc
        'lumber_csw_document'             => null,
        'lumber_supply_contract'          => null,
        'lumber_business_plan'            => null,
        'lumber_mayors_permit'            => null,
        'lumber_registration_certificate' => null,
        'lumber_tax_return'               => null,
        'lumber_monthly_reports'          => null,
        'lumber_or_copy'                  => null,
        'lumber_op_copy'                  => null,
    ];

    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $f = $_FILES['application_doc'];
        $safe = slugify_name("application_doc__" . ($f['name'] ?: 'lumber.doc'));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= '.doc';
        $urls['application_form'] = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/msword');
    }

    // signature (transparent PNG only)
    $signature_url = null;
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $f = $_FILES['signature_file'];
        $safe = slugify_name("signature__signature.png");
        $signature_url = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], 'image/png');
    }

    $fileMap = [
        'lumber_csw_document'             => 'lumber_csw_document',
        'lumber_supply_contract'          => 'lumber_supply_contract',
        'lumber_business_plan'            => 'lumber_business_plan',
        'lumber_mayors_permit'            => 'lumber_mayors_permit',
        'lumber_registration_certificate' => 'lumber_registration_certificate',
        'lumber_tax_return'               => 'lumber_tax_return',
        'lumber_monthly_reports'          => 'lumber_monthly_reports',
        'lumber_or_copy'                  => 'lumber_or_copy',
        'lumber_op_copy'                  => 'lumber_op_copy',
    ];
    $setParts = [];
    $params   = [':rid'=>$requirement_id];

    foreach ($fileMap as $postField => $dbCol) {
        if (!empty($_FILES[$postField]) && is_uploaded_file($_FILES[$postField]['tmp_name'])) {
            $f = $_FILES[$postField];
            $ext  = pick_ext($f, '.bin');
            $safe = slugify_name("{$postField}__" . ($f['name'] ?? "file{$ext}"));
            if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= $ext;
            $url = supa_upload($bucket, $prefix . $safe, $f['tmp_name'], $f['type'] ?: 'application/octet-stream');
            $urls[$dbCol] = $url;
            $setParts[] = "{$dbCol} = :{$dbCol}";
            $params[":{$dbCol}"] = $url;
        }
    }
    if ($urls['application_form']) {
        $setParts[] = "application_form = :application_form";
        $params[":application_form"] = $urls['application_form'];
    }
    if ($setParts) {
        $__stage = 'update_requirements';
        $__sql = "UPDATE public.requirements SET " . implode(', ', $setParts) . " WHERE requirement_id = :rid";
        $__params = $params;
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
    }

    // 4) application_form
    $__stage = 'insert_application_form';
    $appSql = "
      INSERT INTO public.application_form
        (client_id, contact_number, application_for, type_of_permit,
         complete_name, present_address, province, location,
         signature_of_applicant, additional_information,
         -- new/renewal common
         applicant_age, legitimate_business_address, is_government_employee,
         proposed_place_of_operation, expected_annual_volume, estimated_annual_worth,
         total_number_of_employees, total_number_of_dependents,
         intended_market, my_experience_as_alumber_dealer, declaration_name, suppliers_json,
         -- renewal extras
         registration_no, date_of_registration, license_number, permit_number, expiry_date,
         date_today)
      VALUES
        (:client_id, :contact_number, 'lumber', :permit_type,
         :complete_name, :present_address, :province, :location,
         :signature_url, :additional_information,
         :applicant_age, :business_address, :govt_employee,
         :operation_place, :annual_volume, :annual_worth,
         :employees_count, :dependents_count,
         :intended_market, :experience, :declaration_name, :suppliers_json,
         :prev_certificate, :issued_date, :cr_license, :permit_number, :expiry_date,
         to_char(now(),'YYYY-MM-DD'))
      RETURNING application_id
    ";
    $__sql = $appSql;
    $__params = [
        ':client_id'        => $client_id,
        ':contact_number'   => $contact_num !== '' ? $contact_num : null,
        ':permit_type'      => $permit_type, // 'new' | 'renewal'
        ':complete_name'    => $complete_name,
        ':present_address'  => $present_address !== '' ? $present_address : null,
        ':province'         => $province !== '' ? $province : null,
        ':location'         => $municipality !== '' ? $municipality : null,
        ':signature_url'    => $signature_url,
        ':additional_information' => $additional_info,

        ':applicant_age'    => $applicant_age !== '' ? $applicant_age : null,
        ':business_address' => $business_address !== '' ? $business_address : null,
        ':govt_employee'    => (strtolower($govt_employee) === 'yes') ? 'yes' : 'no',
        ':operation_place'  => $operation_place !== '' ? $operation_place : null,
        ':annual_volume'    => $annual_volume !== '' ? $annual_volume : null,
        ':annual_worth'     => $annual_worth !== '' ? $annual_worth : null,
        ':employees_count'  => $employees_count !== '' ? $employees_count : null,
        ':dependents_count' => $dependents_count !== '' ? $dependents_count : null,
        ':intended_market'  => $intended_market !== '' ? $intended_market : null,
        ':experience'       => $experience !== '' ? $experience : null,
        ':declaration_name' => $declaration_name !== '' ? $declaration_name : null,
        ':suppliers_json'   => json_encode($suppliers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),

        // renewal extras (from packed JSON; harmlessly NULL for 'new')
        ':prev_certificate' => ($renewal_extras['prevCertificate'] ?? '') ?: null,
        ':issued_date'      => ($renewal_extras['issuedDate'] ?? '') ?: null,
        ':cr_license'       => ($renewal_extras['crLicense'] ?? '') ?: null,
        ':permit_number'    => $permit_number !== '' ? $permit_number : null,
        ':expiry_date'      => $expiry_date !== '' ? $expiry_date : null,
    ];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    // 5) approval
    $__stage = 'insert_approval';
    $__sql = "
      INSERT INTO public.approval
        (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
      VALUES
        (:client_id, :requirement_id, 'lumber', 'pending', NULL, :permit_type, :application_id, now())
      RETURNING approval_id
    ";
    $__params = [
        ':client_id'=>$client_id,
        ':requirement_id'=>$requirement_id,
        ':permit_type'=>$permit_type,
        ':application_id'=>$application_id,
    ];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $approval_id = $stmt->fetchColumn();
    if (!$approval_id) throw new Exception('Failed to create approval record');

    // 6) notifications — INSERT tied to approval.approval_id (UUID)
    $__stage = 'insert_notification';
    $notifMessage = sprintf(
        '%s submitted a %s lumber dealer application (APP %s).',
        $complete_name !== '' ? $complete_name : 'Applicant',
        $permit_type,
        $application_id
    );
    $__sql = "
      INSERT INTO public.notifications (approval_id, message, status, is_read)
      VALUES (:approval_id, :message, 'pending', false)
      RETURNING notif_id, now() AS created_at, status, is_read
    ";
    $__params = [
        ':approval_id' => $approval_id, // FK → approval(approval_id)
        ':message'     => $notifMessage,
    ];
    $stmt = $pdo->prepare($__sql);
    $stmt->execute($__params);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $pdo->commit();

    echo json_encode(array_merge([
        'ok'=>true,
        'client_id'=>$client_id,
        'requirement_id'=>$requirement_id,
        'application_id'=>$application_id,
        'approval_id'=>$approval_id,
        'notification'=>[
            'notif_id'   => $notif['notif_id']   ?? null,
            'created_at' => $notif['created_at'] ?? null,
            'status'     => $notif['status']     ?? 'pending',
            'is_read'    => isset($notif['is_read']) ? (bool)$notif['is_read'] : false,
            'message'    => $notifMessage,
        ],
        'storage_prefix'=>"lumber/{$requirement_id}/",
        'bucket'=>bucket_name(),
    ], $DEBUG ? debug_payload() : []));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $err = ['ok'=>false, 'error'=>$e->getMessage()];
    if ($e instanceof PDOException && isset($e->errorInfo)) {
        $err['pdo'] = [
            'sqlstate'=>$e->errorInfo[0] ?? null,
            'code'    =>$e->errorInfo[1] ?? null,
            'detail'  =>$e->errorInfo[2] ?? null,
        ];
    }
    if ($DEBUG) $err = array_merge($err, debug_payload());
    http_response_code(400);
    echo json_encode($err);
}
