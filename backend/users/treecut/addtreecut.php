<?php
// ../backend/users/addtreecut/addtreecut.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // must define: $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

/* ----------------------------------------------------------------------
 | Helpers (Supabase + utils)
 * --------------------------------------------------------------------*/
const DEFAULT_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_BUCKET;
}

function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function supa_public_url(string $bucket, string $path): string
{
    return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . $bucket . '/' . encode_path_segments($path);
}

function supa_upload(string $bucket, string $path, string $tmp, string $mime): string
{
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . $bucket . '/' . encode_path_segments($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . ($mime ?: 'application/octet-stream'),
            'x-upsert: false',
        ],
        CURLOPT_POSTFIELDS     => file_get_contents($tmp),
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
        WHERE table_schema=:s AND table_name=:t AND column_name=:c
        LIMIT 1
    ");
    $q->execute([':s' => $schema, ':t' => $table, ':c' => $column]);
    return (bool)$q->fetchColumn();
}

/* ----------------------------------------------------------------------
 | Main
 * --------------------------------------------------------------------*/
try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated.');
    if (!$pdo) throw new Exception('Database unavailable.');

    // Resolve users.user_id (uuid). Session may hold numeric id or uuid.
    $idOrUuid = (string)$_SESSION['user_id'];
    $u = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text=:v OR user_id::text=:v LIMIT 1");
    $u->execute([':v' => $idOrUuid]);
    $urow = $u->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found.');
    $user_uuid = $urow['user_id'];

    /* ------- Inputs from FormData ------- */
    // Identity (required in UI)
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    if ($first_name === '' || $last_name === '') throw new Exception('First and last name are required.');

    // Contact & address
    $street       = trim($_POST['street']       ?? '');
    $barangay     = trim($_POST['barangay']     ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $province     = trim($_POST['province']     ?? '');
    $contact_no   = trim($_POST['contact_number'] ?? '');
    $email_addr   = trim($_POST['email']        ?? '');
    $registration = trim($_POST['registration_number'] ?? '');

    // Tree cutting specifics
    $location     = trim($_POST['location']     ?? '');
    $ownership    = trim($_POST['ownership']    ?? ''); // "Private" | "Government" | "Others"
    $other_owner  = trim($_POST['other_ownership'] ?? '');
    $purpose      = trim($_POST['purpose']      ?? '');

    // NEW: Land details
    $tax_declaration = trim($_POST['tax_declaration'] ?? '');
    $lot_no          = trim($_POST['lot_no'] ?? '');
    $contained_area  = trim($_POST['contained_area'] ?? '');

    // Species rows JSON (optional)
    $species_json = trim($_POST['species_rows_json'] ?? '');
    $species      = $species_json !== '' ? json_decode($species_json, true) : null;

    // This page has no renewal: always "none"
    $permit_type = 'none';

    $complete_name   = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));
    $present_address = trim(preg_replace('/\s+/', ' ', "{$street} {$barangay} {$municipality} {$province}"));

    /* ------- Ensure / create client row ------- */
    $nf = norm($first_name);
    $nm = norm($middle_name);
    $nl = norm($last_name);

    $hasNormCols = false;
    try {
        $pdo->query("SELECT norm_first, norm_middle, norm_last FROM public.client LIMIT 0");
        $hasNormCols = true;
    } catch (\Throwable $e) {
    }

    if ($hasNormCols) {
        $cs = $pdo->prepare("SELECT client_id FROM public.client WHERE norm_first=:f AND norm_middle=:m AND norm_last=:l LIMIT 1");
    } else {
        $cs = $pdo->prepare("
            SELECT client_id
            FROM public.client
            WHERE lower(trim(coalesce(first_name,'')))=:f
              AND lower(trim(coalesce(middle_name,'')))=:m
              AND lower(trim(coalesce(last_name,'')))=:l
            LIMIT 1
        ");
    }
    $cs->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
    $existing_client_id = $cs->fetchColumn() ?: null;

    $pdo->beginTransaction();

    if ($existing_client_id) {
        $client_id = $existing_client_id;
    } else {
        $ins = $pdo->prepare("
            INSERT INTO public.client
                (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
            VALUES
                (:uid, :first, :middle, :last, :street, :barangay, :municipality, :contact)
            RETURNING client_id
        ");
        $ins->execute([
            ':uid'          => $user_uuid,
            ':first'        => $first_name,
            ':middle'       => $middle_name,
            ':last'         => $last_name,
            ':street'       => $street ?: null,
            ':barangay'     => $barangay ?: null,
            ':municipality' => $municipality ?: null,
            ':contact'      => $contact_no ?: null,
        ]);
        $client_id = $ins->fetchColumn();
        if (!$client_id) throw new Exception('Failed to create client record.');
    }

    /* ------- Pending check (only one pending treecut per client) ------- */
    $chk = $pdo->prepare("
        SELECT 1 FROM public.approval
        WHERE client_id=:cid AND lower(request_type)='treecut' AND lower(approval_status)='pending'
        LIMIT 1
    ");
    $chk->execute([':cid' => $client_id]);
    if ($chk->fetchColumn()) throw new Exception('You already have a pending tree cutting request.');

    /* ------- Create empty requirements row ------- */
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record.');

    /* ------- Upload files to bucket ------- */
    $bucket  = bucket_name();
    $runKey  = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $prefix  = "treecut/{$client_id}/{$runKey}/";
    $uploaded_map = [];
    $signature_url = null; // will also be saved into application_form.signature_of_applicant

    // Map incoming file keys to requirements columns
    $fileMap = [
        'file_1'   => 'tree_cov_certificate',        // COV
        'file_3'   => 'tree_memorandum_report',      // Memorandum Report
        'file_4'   => 'tree_tally_sheets',           // Tally sheets (inventory)
        'file_5'   => 'geo_photos',                  // Geo-tagged photos
        'file_6'   => 'tree_sworn_statement',        // Sworn Statement
        'file_7a'  => 'tree_conveyance_copy',        // OR/CR of conveyance
        'file_7b'  => 'tree_drivers_license_copy',   // Driver's License
        'file_8'   => 'tree_purchase_order',         // Purchase Order
        'file_10a' => 'tree_tally_sheets_table',     // Tally sheets & stand/stock table
        'file_10b' => 'tree_tree_charting',          // Tree Charting

        // Extra uploads we also accept:
        'application_doc' => 'application_form',     // Generated .doc (MHTML) -> requirements.application_form
        // 'signature_file' has no column in requirements; stored in application_form.signature_of_applicant
    ];

    foreach ($_FILES as $key => $file) {
        if (empty($file) || !is_uploaded_file($file['tmp_name'])) continue;

        $ext  = pick_ext($file, '.bin');
        $safe = slugify_name($key . '__' . ($file['name'] ?: "file{$ext}"));
        if (pathinfo($safe, PATHINFO_EXTENSION) === '') $safe .= $ext;

        $public = supa_upload($bucket, $prefix . $safe, $file['tmp_name'], $file['type'] ?: 'application/octet-stream');
        $uploaded_map[$key] = $public;

        // Update requirements if there is a mapped column
        if (isset($fileMap[$key]) && column_exists($pdo, 'public', 'requirements', $fileMap[$key])) {
            $col = $fileMap[$key];
            $upd = $pdo->prepare("UPDATE public.requirements SET {$col}=:v WHERE requirement_id=:rid");
            $upd->execute([':v' => $public, ':rid' => $requirement_id]);
        }

        // Keep signature URL for application_form
        if ($key === 'signature_file') {
            $signature_url = $public;
        }
    }

    // Persist uploads mapping to requirements.uploads_json if exists
    if ($uploaded_map && column_exists($pdo, 'public', 'requirements', 'uploads_json')) {
        $j = $pdo->prepare("UPDATE public.requirements SET uploads_json=:j WHERE requirement_id=:rid");
        $j->execute([':j' => json_encode($uploaded_map, JSON_UNESCAPED_SLASHES), ':rid' => $requirement_id]);
    }

    /* ------- Create application_form row ------- */
    $ownership_str = $ownership === 'Others' && $other_owner !== ''
        ? 'Others: ' . $other_owner
        : ($ownership ?: null);

    // Flatten species rows to readable text (fallback)
    $species_text = null;
    if (is_array($species) && $species) {
        $lines = [];
        foreach ($species as $row) {
            $n = trim((string)($row['name']   ?? ''));
            $c = trim((string)($row['count']  ?? ''));
            $v = trim((string)($row['volume'] ?? ''));
            if ($n || $c || $v) $lines[] = "{$n} | {$c} | {$v}";
        }
        $species_text = $lines ? implode("\n", $lines) : null;
    }

    // Additional JSON blob for traceability
    $additional = [
        'submission_key'  => $runKey,
        'email_address'   => $email_addr ?: null,
        'registration_no' => $registration ?: null,
        'ownership_raw'   => $ownership ?: null,
        'other_ownership' => $other_owner ?: null,
        'tax_declaration' => $tax_declaration ?: null,
        'lot_no'          => $lot_no ?: null,
        'contained_area'  => $contained_area ?: null,
        'species_rows'    => $species,
        'uploads'         => $uploaded_map ?: new stdClass(),
    ];

    $afCols = [
        'client_id',
        'contact_number',
        'application_for',
        'type_of_permit',
        'complete_name',
        'present_address',
        'province',
        'location_of_area_trees_to_be_cut',
        'ownership_of_land',
        'number_and_species_of_trees_applied_for_cutting',
        'purpose_of_application_for_tree_cutting_permit',
        'email_address',
        'additional_information',
        'date_today'
    ];
    $afVals = [
        ':client_id',
        ':contact_number',
        "'treecut'",
        "'none'",
        ':complete_name',
        ':present_address',
        ':province',
        ':location',
        ':ownership',
        ':species_text',
        ':purpose',
        ':email',
        ':additional_info',
        "to_char(now(),'YYYY-MM-DD')"
    ];

    // Optional signature column support
    $sigCol = null;
    if (column_exists($pdo, 'public', 'application_form', 'signature_of_applicant')) $sigCol = 'signature_of_applicant';
    elseif (column_exists($pdo, 'public', 'application_form', 'signature_over_printed_name')) $sigCol = 'signature_over_printed_name';

    if ($sigCol !== null) {
        $afCols[] = $sigCol;
        $afVals[] = ':signature_url';
    }

    // NEW: include land details if columns exist
    if (column_exists($pdo, 'public', 'application_form', 'tax_declaration')) {
        $afCols[] = 'tax_declaration';
        $afVals[] = ':tax_declaration';
    }
    if (column_exists($pdo, 'public', 'application_form', 'lot_no')) {
        $afCols[] = 'lot_no';
        $afVals[] = ':lot_no';
    }
    if (column_exists($pdo, 'public', 'application_form', 'contained_area')) {
        $afCols[] = 'contained_area';
        $afVals[] = ':contained_area';
    }

    // NEW: include species_rows_json if column exists
    if (column_exists($pdo, 'public', 'application_form', 'species_rows_json')) {
        $afCols[] = 'species_rows_json';
        $afVals[] = ':species_json';
    }

    $sql = "INSERT INTO public.application_form (" . implode(',', $afCols) . ")
            VALUES (" . implode(',', $afVals) . ")
            RETURNING application_id";
    $af = $pdo->prepare($sql);
    $af->execute([
        ':client_id'       => $client_id,
        ':contact_number'  => $contact_no ?: null,
        ':complete_name'   => $complete_name,
        ':present_address' => $present_address ?: null,
        ':province'        => $province ?: null,
        ':location'        => $location ?: null,
        ':ownership'       => $ownership_str,
        ':species_text'    => $species_text,
        ':purpose'         => $purpose ?: null,
        ':email'           => $email_addr ?: null,
        ':additional_info' => json_encode($additional, JSON_UNESCAPED_SLASHES),
        ':signature_url'   => $signature_url, // may be null
        ':tax_declaration' => $tax_declaration ?: null,
        ':lot_no'          => $lot_no ?: null,
        ':contained_area'  => $contained_area ?: null,
        ':species_json'    => $species_json ?: null, // structured species rows JSON
    ]);
    $application_id = $af->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form.');

    /* ------- Create approval row (pending) ------- */
    $ap = $pdo->prepare("
        INSERT INTO public.approval
          (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
        VALUES
          (:client_id, :requirement_id, 'treecut', 'pending', NULL, 'none', :application_id, now())
        RETURNING approval_id
    ");
    $ap->execute([
        ':client_id'      => $client_id,
        ':requirement_id' => $requirement_id,
        ':application_id' => $application_id,
    ]);
    $approval_id = $ap->fetchColumn();
    if (!$approval_id) throw new Exception('Failed to create approval record.');

    /* ------- Notification (include "from" and "to") ------- */
    $msg = sprintf('%s requested a tree cutting permit.', $first_name ?: $complete_name);
    $nt = $pdo->prepare("
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
        VALUES (:aid, NULL, :msg, false, :from_user, :to_dept)
        RETURNING notif_id
    ");
    $nt->execute([
        ':aid'       => $approval_id,
        ':msg'       => $msg,
        ':from_user' => $user_uuid,     // current logged-in user
        ':to_dept'   => 'Tree Cutting', // target department
    ]);
    $notif_id = $nt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success'         => true,
        'client_id'       => $client_id,
        'requirement_id'  => $requirement_id,
        'application_id'  => $application_id,
        'approval_id'     => $approval_id,
        'notification_id' => $notif_id,
        'bucket'          => $bucket,
        'storage_prefix'  => $prefix,
        'uploaded'        => $uploaded_map, // includes application_doc and (if provided) signature_file
    ]);
} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}
