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
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false || $code >= 300) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("Upload failed ({$code}): {$err} {$resp}");
    }
    curl_close($ch);
    return supa_public_url($bucket, $path);
}
function slugify_name(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name);
    $name = preg_replace('/-+/', '-', $name);
    return trim($name, '-');
}
function pick_ext(array $file, string $fallback = '.bin'): string {
    $name = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $ext ? ('.' . $ext) : $fallback;
}
function normalize(string $v): string { return strtolower(trim($v)); }
function debug_payload(): array {
    return [
        'post'  => $_POST,
        'files' => array_map(fn($f) => array_merge($f, ['tmp_name' => basename($f['tmp_name'] ?? '')]), $_FILES ?? []),
        'session' => $_SESSION ?? [],
    ];
}

/* ===================== Main ===================== */
try {
    // Optional user resolution: from POST user_id or session; proceed even if absent
    $__stage = 'user_lookup';
    $user_uuid = null;
    $idOrUuid = (string)($_POST['user_id'] ?? ($_SESSION['user_id'] ?? ''));
    if ($idOrUuid !== '') {
        $__sql = "SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1";
        $__params = [':v'=>$idOrUuid];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        $urow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($urow) $user_uuid = $urow['user_id'];
    }

    /* ---------- Read POST ---------- */
    $__stage = 'read_post';
    // Map UI 'dealer_new'/'dealer_renewal' → 'new'/'renewal'
    $permit_type_raw = strtolower($_POST['permit_type'] ?? 'dealer_new');
    $permit_type = ($permit_type_raw === 'dealer_renewal' || $permit_type_raw === 'renewal') ? 'renewal' : 'new';
    $type_word_for_message = ($permit_type === 'renewal') ? 'renewal' : 'permit';

    // name parts (always present from UI, renewal included)
    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $complete_name = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));

    // contact / address (renewal also sends these now)
    $contact_num  = trim($_POST['contact_number'] ?? '');
    $sitio_street = trim($_POST['sitio_street'] ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $province     = trim($_POST['province']     ?? ''); // Province → client.city

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
    $intended_market  = trim($_POST['intended_market']  ?? '');
    $employees_count  = trim($_POST['employees_count']  ?? '');
    $dependents_count = trim($_POST['dependents_count'] ?? '');
    $experience       = trim($_POST['experience']       ?? '');
    $declaration_name = trim($_POST['declaration_name'] ?? '');

    // suppliers (JSON from UI)
    $suppliers_json = (string)($_POST['suppliers_json'] ?? '[]');
    $suppliers = json_decode($suppliers_json, true);
    if (!is_array($suppliers)) $suppliers = [];

    // renewal extras
    $renewal_extras_json = (string)($_POST['renewal_extras_json'] ?? '{}');
    $renewal_extras = json_decode($renewal_extras_json, true);
    if (!is_array($renewal_extras)) $renewal_extras = [];

    // build present address text
    $present_address = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$sitio_street, $barangay, $municipality]))));
    $additional_info = json_encode([
        'request_type' => 'lumber',
        'permit_type'  => $permit_type,
        'form_version' => 1,
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

    /* (duplicate check removed to match chainsaw behavior) */

    /* ---------- Resolve/Upsert client ---------- */
    $__stage = 'client_resolve';
    $client_id = null;

    // 1a) by explicit POST client_id
    if (!$client_id) {
        $from_post_cid = trim($_POST['client_id'] ?? '');
        if ($from_post_cid !== '') {
            $__sql = "SELECT client_id FROM public.client WHERE client_id::text = :cid LIMIT 1";
            $__params = [':cid'=>$from_post_cid];
            $stmt = $pdo->prepare($__sql);
            $stmt->execute($__params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $client_id = $row['client_id'];
        }
    }

    // 1b) by user (if we have a user_uuid)
    if (!$client_id && $user_uuid) {
        $__sql = "SELECT client_id FROM public.client WHERE user_id = :uid ORDER BY client_id ASC LIMIT 1";
        $__params = [':uid'=>$user_uuid];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $client_id = $row['client_id'];
        }
    }

    // 1c) by name match
    if (!$client_id && $first_name && $last_name) {
        $cmp_fn = normalize($first_name);
        $cmp_mn = normalize($middle_name);
        $cmp_ln = normalize($last_name);
        $__sql = "
          SELECT client_id
            FROM public.client
           WHERE lower(first_name) = :fn
             AND lower(coalesce(middle_name,'')) = :mn
             AND lower(last_name) = :ln
           ORDER BY id ASC
           LIMIT 1
        ";
        $__params = [':fn'=>$cmp_fn, ':mn'=>$cmp_mn, ':ln'=>$cmp_ln];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $client_id = $row['client_id'];
        }
    }

    // 1d) create if none
    if (!$client_id) {
        $__stage = 'insert_client';
        $__sql = "
          INSERT INTO public.client
            (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city)
          VALUES
            (:uid, :first, :middle, :last, :sitio, :brgy, :mun, :city)
          RETURNING client_id
        ";
        $__params = [
            ':uid'      => $user_uuid,
            ':first'    => $first_name,
            ':middle'   => $middle_name !== '' ? $middle_name : null,
            ':last'     => $last_name,
            ':sitio'    => $sitio_street !== '' ? $sitio_street : null,
            ':brgy'     => $barangay !== '' ? $barangay : null,
            ':mun'      => $municipality !== '' ? $municipality : null,
            ':city'     => $province !== '' ? $province : null,
        ];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        $client_id = $stmt->fetchColumn();
        if (!$client_id) throw new Exception('Failed to create client');
    } else {
        // possibly update existing client address if changed / missing user_id
        $__stage = 'maybe_update_client';
        $__sql = "SELECT user_id, sitio_street, barangay, municipality, city FROM public.client WHERE client_id = :cid";
        $__params = [':cid'=>$client_id];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        $cur = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cmp_sitio = normalize($sitio_street);
        $cmp_brgy  = normalize($barangay);
        $cmp_mun   = normalize($municipality);
        $cmp_city  = normalize($province);

        $all_same = (
            normalize($cur['sitio_street'] ?? '') === $cmp_sitio &&
            normalize($cur['barangay']     ?? '') === $cmp_brgy &&
            normalize($cur['municipality'] ?? '') === $cmp_mun &&
            normalize($cur['city']         ?? '') === $cmp_city
        );

        $needs_user_id = empty($cur['user_id']) && !empty($user_uuid);

        if (!$all_same || $needs_user_id) {
            $__stage = 'update_client';
            $__sql = "
              UPDATE public.client
                 SET user_id      = COALESCE(:uid, user_id),
                     sitio_street = COALESCE(:sitio, sitio_street),
                     barangay     = COALESCE(:brgy, barangay),
                     municipality = COALESCE(:mun, municipality),
                     city         = COALESCE(:city, city)
               WHERE client_id    = :cid
            ";
            $__params = [
                ':uid'   => $needs_user_id ? $user_uuid : null,
                ':sitio' => (!$all_same && $sitio_street !== '') ? $sitio_street : null,
                ':brgy'  => (!$all_same && $barangay !== '')     ? $barangay     : null,
                ':mun'   => (!$all_same && $municipality !== '') ? $municipality : null,
                ':city'  => (!$all_same && $province !== '')     ? $province     : null,
                ':cid'   => $client_id,
            ];
            $stmt = $pdo->prepare($__sql);
            $stmt->execute($__params);
        }
        // else: identical & user_id already set → skip update
    }

    // 2) requirements
    $__stage = 'insert_requirements';
    $__sql = "INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id";
    $ridStmt = $pdo->query($__sql);
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    $bucket = bucket_name();
    $prefix = "lumber/{$requirement_id}/";

    // 3) uploads (no signature handling here)
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
        $__sql = "UPDATE public.requirements SET " . implode(", ", $setParts) . " WHERE requirement_id = :rid";
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($params);
    }

    // 4) application_form (signature_of_applicant removed)
    $__stage = 'insert_application_form';
    $appSql = "
      INSERT INTO public.application_form
        (client_id, contact_number, application_for, type_of_permit,
         complete_name, present_address, province, location,
         additional_information,
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
         :additional_information,
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

        // renewal extras (harmlessly NULL for 'new')
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

    /* ---------- notifications — robust multi-variant insert ---------- */
    $__stage = 'insert_notification';
    $notifMessage = sprintf('%s submitted a lumber %s', $complete_name ?: 'Applicant', $type_word_for_message);

    $notif = [];
    $pdo->exec("SAVEPOINT notif_try_1");
    try {
        // Variant A: approval_id + message + status + is_read
        $__sql = "
          INSERT INTO public.notifications (approval_id, message, status, is_read)
          VALUES (:approval_id, :message, 'pending', false)
          RETURNING notif_id, now() AS created_at, status, is_read
        ";
        $__params = [ ':approval_id'=>$approval_id, ':message'=>$notifMessage ];
        $stmt = $pdo->prepare($__sql);
        $stmt->execute($__params);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $eA) {
        $pdo->exec("ROLLBACK TO SAVEPOINT notif_try_1");
        $pdo->exec("SAVEPOINT notif_try_2");
        try {
            // Variant B: approval_id + message + is_read
            $__sql = "
              INSERT INTO public.notifications (approval_id, message, is_read)
              VALUES (:approval_id, :message, false)
              RETURNING notif_id, now() AS created_at, is_read
            ";
            $__params = [ ':approval_id'=>$approval_id, ':message'=>$notifMessage ];
            $stmt = $pdo->prepare($__sql);
            $stmt->execute($__params);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $eB) {
            $pdo->exec("ROLLBACK TO SAVEPOINT notif_try_2");
            $pdo->exec("SAVEPOINT notif_try_3");
            try {
                // Variant C: approval_id + message + status
                $__sql = "
                  INSERT INTO public.notifications (approval_id, message, status)
                  VALUES (:approval_id, :message, 'pending')
                  RETURNING notif_id, now() AS created_at, status
                ";
                $__params = [ ':approval_id'=>$approval_id, ':message'=>$notifMessage ];
                $stmt = $pdo->prepare($__sql);
                $stmt->execute($__params);
                $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $eC) {
                $pdo->exec("ROLLBACK TO SAVEPOINT notif_try_3");
                $pdo->exec("SAVEPOINT notif_try_4");
                try {
                    // Variant D: approval_id + message
                    $__sql = "
                      INSERT INTO public.notifications (approval_id, message)
                      VALUES (:approval_id, :message)
                      RETURNING notif_id, now() AS created_at
                    ";
                    $__params = [ ':approval_id'=>$approval_id, ':message'=>$notifMessage ];
                    $stmt = $pdo->prepare($__sql);
                    $stmt->execute($__params);
                    $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                } catch (PDOException $eD) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT notif_try_4");
                    // Last resort: only message
                    $__sql = "
                      INSERT INTO public.notifications (message)
                      VALUES (:message)
                      RETURNING notif_id, now() AS created_at
                    ";
                    $__params = [ ':message'=>$notifMessage ];
                    $stmt = $pdo->prepare($__sql);
                    $stmt->execute($__params);
                    $notif = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                }
            }
        }
    }

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
            'status'     => $notif['status']     ?? null,
            'is_read'    => isset($notif['is_read']) ? (bool)$notif['is_read'] : null,
            'message'    => $notifMessage,
        ],
        'storage_prefix'=>"lumber/{$requirement_id}/",
        'bucket'=>bucket_name(),
    ], debug_payload()));
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
    http_response_code(400);
    echo json_encode($err);
}
