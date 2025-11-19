<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo, SUPABASE_URL, SUPABASE_SERVICE_KEY

/* ------------------------------------------------------------------ */
/* -------------------------- storage helpers ------------------------ */
/* ------------------------------------------------------------------ */

const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';
const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB safety guard

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) return REQUIREMENTS_BUCKET;
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) return SUPABASE_BUCKET;
    return DEFAULT_REQUIREMENTS_BUCKET;
}

/** URL-encode each segment of the storage path so spaces/unicode are safe */
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
    if (!file_exists($tmpPath)) {
        throw new Exception("Temp upload not found for {$path}");
    }
    if (filesize($tmpPath) > MAX_UPLOAD_BYTES) {
        throw new Exception("File too large (> " . MAX_UPLOAD_BYTES . " bytes): {$path}");
    }

    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";

    $ch  = curl_init($url);
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

/* ------------------------------------------------------------------ */
/* ----------------------------- utils ------------------------------ */
/* ------------------------------------------------------------------ */

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

function table_exists(PDO $pdo, string $schema, string $table): bool
{
    $q = $pdo->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = :s AND table_name = :t
        LIMIT 1
    ");
    $q->execute([':s' => $schema, ':t' => $table]);
    return (bool)$q->fetchColumn();
}

/** Recursively strip HTML tags from strings in an array/object */
function sanitize_recursive(mixed $v): mixed
{
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $val) {
            $out[$k] = sanitize_recursive($val);
        }
        return $out;
    }
    if (is_object($v)) {
        foreach ($v as $k => $val) {
            $v->$k = sanitize_recursive($val);
        }
        return $v;
    }
    if (is_string($v)) {
        return strip_tags($v);
    }
    return $v;
}

/**
 * Ensure a safe JSON-encoded string (returns '[]' if invalid).
 * - Accepts a JSON string or a PHP array/object.
 * - Strips HTML tags from all string values.
 */
function ensure_json_string(mixed $in): string
{
    if (is_string($in)) {
        $dec = json_decode($in, true);
        if (!is_array($dec)) return '[]';
        $dec = sanitize_recursive($dec);
        return json_encode($dec, JSON_UNESCAPED_SLASHES);
    }
    if (is_array($in) || is_object($in)) {
        $dec = sanitize_recursive($in);
        return json_encode($dec, JSON_UNESCAPED_SLASHES);
    }
    return '[]';
}

/* ------------------------------------------------------------------ */
/* ------------------------------ main ------------------------------ */
/* ------------------------------------------------------------------ */

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    if (!$pdo) {
        throw new Exception('DB not available');
    }

    // Resolve current user's uuid (users.user_id) — accept numeric id or uuid in session
    $idOrUuid = (string)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id::text AS user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
    $stmt->execute([':v' => $idOrUuid]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = (string)$urow['user_id'];

    /* --------------------- Inputs (WOOD page) --------------------- */
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    // Names
    $first_name  = trim($_POST['first_name']  ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    if ($first_name === '' || $last_name === '') {
        throw new Exception('First and last name are required.');
    }

    $nf = norm($first_name);
    $nm = norm($middle_name);
    $nl = norm($last_name);

    // Client choice flags — accept BOTH new (lumber-like) and legacy wood params
    $override_client_id = trim((string)($_POST['use_client_id'] ?? $_POST['use_existing_client_id'] ?? ''));
    if ($override_client_id === '') $override_client_id = null;

    $force_new_client =
        !empty($_POST['force_new_client']) ||
        ((string)($_POST['confirm_new_client'] ?? '') === '1');

    // NEW-only inputs
    $new_business_address = trim($_POST['new_business_address'] ?? $_POST['business_address'] ?? '');
    $new_plant_location   = trim($_POST['new_plant_location']   ?? $_POST['plant_location']   ?? '');
    $new_contact_number   = trim($_POST['new_contact_number']   ?? $_POST['contact_number']   ?? '');
    $new_email_address    = trim($_POST['new_email_address']    ?? $_POST['email_address']    ?? '');
    $new_ownership_type   = trim($_POST['new_ownership_type']   ?? $_POST['ownership_type']   ?? '');

    // RENEWAL-only inputs
    $r_address        = trim($_POST['r_address']         ?? $_POST['address']            ?? '');
    $r_plant_location = trim($_POST['r_plant_location']  ?? $_POST['plant_location']     ?? '');
    $r_contact_number = trim($_POST['r_contact_number']  ?? $_POST['contact_number']     ?? '');
    $r_email_address  = trim($_POST['r_email_address']   ?? $_POST['email_address']      ?? '');
    $r_ownership_type = trim($_POST['r_ownership_type']  ?? $_POST['ownership_type']     ?? '');
    $r_prev_permit    = trim($_POST['r_previous_permit'] ?? $_POST['previous_permit_no'] ?? '');
    $r_expiry_date    = trim($_POST['r_expiry_date']     ?? $_POST['expiry_date']        ?? '');

    // Shared plant inputs
    $plant_type      = trim($_POST['plant_type']       ?? '');
    $daily_capacity  = trim($_POST['daily_capacity']   ?? '');
    $power_source    = trim($_POST['power_source']     ?? '');

    // Tables from UI (must be JSON strings or arrays; we'll sanitize to JSON strings)
    $machinery_rows_json = $_POST['machinery_rows_json'] ?? '[]';
    $supply_rows_json    = $_POST['supply_rows_json']    ?? '[]';

    // Declaration
    $declaration_name = trim($_POST['declaration_name']    ?? '');
    $declaration_addr = trim($_POST['declaration_address'] ?? '');

    $complete_name = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));

    // Map NEW vs RENEWAL
    $present_address = $permit_type === 'renewal' ? $r_address        : $new_business_address;
    $plant_location  = $permit_type === 'renewal' ? $r_plant_location : $new_plant_location;
    $contact_number  = $permit_type === 'renewal' ? $r_contact_number : $new_contact_number;
    $email_address   = $permit_type === 'renewal' ? $r_email_address  : $new_email_address;
    $ownership_type  = $permit_type === 'renewal' ? $r_ownership_type : $new_ownership_type;

    /* ------------------- pick/create client_id -------------------- */
    $pdo->beginTransaction();

    $client_id = null; // TEXT always

    if ($override_client_id) {
        // Validate and FETCH canonical name to enforce consistency
        $check = $pdo->prepare("
            SELECT client_id::text AS client_id, first_name, middle_name, last_name
            FROM public.client
            WHERE client_id::text = :id
            LIMIT 1
        ");
        $check->execute([':id' => $override_client_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Selected client not found.');
        }
        $client_id   = (string)$row['client_id'];

        // Force the stored names to match the existing client; declaration = FIRST + LAST
        $first_name  = (string)($row['first_name']  ?? '');
        $middle_name = (string)($row['middle_name'] ?? '');
        $last_name   = (string)($row['last_name']   ?? '');
        $declaration_name = trim($first_name . ' ' . $last_name);

        // Refresh normalized copies
        $nf = norm($first_name);
        $nm = norm($middle_name);
        $nl = norm($last_name);
    } else {
        // 1) Try exact normalized match first (use norm_* if present)
        $hasNormCols = column_exists($pdo, 'public', 'client', 'norm_first')
            && column_exists($pdo, 'public', 'client', 'norm_middle')
            && column_exists($pdo, 'public', 'client', 'norm_last');

        if ($hasNormCols) {
            $stmt = $pdo->prepare("
                SELECT client_id::text
                FROM public.client
                WHERE norm_first = :f AND norm_middle = :m AND norm_last = :l
                LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT client_id::text
                FROM public.client
                WHERE lower(trim(coalesce(first_name,'')))  = :f
                  AND lower(trim(coalesce(middle_name,''))) = :m
                  AND lower(trim(coalesce(last_name,'')))   = :l
                LIMIT 1
            ");
        }
        $stmt->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
        $client_id = $stmt->fetchColumn() ?: null;

        // 2) If still no client and NOT forced to create new (legacy flow), offer confirmation
        if (!$client_id && !$force_new_client) {
            // In the updated flow, this step is handled by precheck + UI modal.
            // Keep a conservative guard for callers who hit save directly: suggest candidates and STOP here.
            $candidates = [];
            $minSim = 0.60; // "very likely" same person
            $supportsTrgm = false;
            try {
                $trgm = $pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm' LIMIT 1");
                $supportsTrgm = (bool)$trgm->fetchColumn();
            } catch (\Throwable $e) {
                $supportsTrgm = false;
            }

            if ($supportsTrgm) {
                if ($hasNormCols) {
                    $candSql = "
                      SELECT client_id::text AS client_id, first_name, middle_name, last_name,
                        (
                          GREATEST(similarity(norm_first,  :a), similarity(norm_first,  :b), similarity(norm_first,  :c)) +
                          GREATEST(similarity(norm_middle, :a), similarity(norm_middle, :b), similarity(norm_middle, :c)) +
                          GREATEST(similarity(norm_last,   :a), similarity(norm_last,   :b), similarity(norm_last,   :c))
                        ) / 3.0 AS score
                      FROM public.client
                      ORDER BY score DESC
                      LIMIT 5
                    ";
                } else {
                    $candSql = "
                      SELECT client_id::text AS client_id, first_name, middle_name, last_name,
                        (
                          GREATEST(similarity(lower(trim(coalesce(first_name,''))),  :a), similarity(lower(trim(coalesce(first_name,''))),  :b), similarity(lower(trim(coalesce(first_name,''))),  :c)) +
                          GREATEST(similarity(lower(trim(coalesce(middle_name,''))), :a), similarity(lower(trim(coalesce(middle_name,''))), :b), similarity(lower(trim(coalesce(middle_name,''))), :c)) +
                          GREATEST(similarity(lower(trim(coalesce(last_name,''))),   :a), similarity(lower(trim(coalesce(last_name,''))),   :b), similarity(lower(trim(coalesce(last_name,''))),   :c))
                        ) / 3.0 AS score
                      FROM public.client
                      ORDER BY score DESC
                      LIMIT 5
                    ";
                }

                $cs = $pdo->prepare($candSql);
                $cs->execute([':a' => $nf, ':b' => $nm, ':c' => $nl]);
                while ($row = $cs->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($row['score']) && (float)$row['score'] >= $minSim) {
                        $candidates[] = [
                            'client_id'   => (string)$row['client_id'],
                            'first_name'  => (string)($row['first_name']  ?? ''),
                            'middle_name' => (string)($row['middle_name'] ?? ''),
                            'last_name'   => (string)($row['last_name']   ?? ''),
                            'score'       => (float)$row['score'],
                        ];
                    }
                }
            }

            if ($candidates) {
                // Build legacy convenience fields for the top candidate (align with precheck)
                $top = $candidates[0];
                $pdo->rollBack();
                echo json_encode([
                    'ok' => true,
                    'needs_confirm' => true,           // new alias (matches precheck)
                    'need_client_confirm' => true,     // legacy key (back-compat)
                    'message' => 'An existing client looks similar. Confirm to use it or proceed with creating a new one.',
                    'candidates' => $candidates,
                    'existing_client_id'     => $top['client_id'],
                    'existing_client_first'  => $top['first_name'],
                    'existing_client_middle' => $top['middle_name'],
                    'existing_client_last'   => $top['last_name'],
                    'existing_client_name'   => trim($top['first_name'] . ' ' . $top['middle_name'] . ' ' . $top['last_name']),
                    'suggestion_score'       => round((float)$top['score'], 2),
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        // 3) Still no client? Create new record
        if (!$client_id) {
            $hasNormCols = $hasNormCols ?? column_exists($pdo, 'public', 'client', 'norm_first');

            if ($hasNormCols) {
                $ins = $pdo->prepare("
                    INSERT INTO public.client
                      (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality,
                       contact_number, norm_first, norm_middle, norm_last)
                    VALUES
                      (:uid, :first, :middle, :last, NULL, NULL, :location, :contact, :nf, :nm, :nl)
                    RETURNING client_id::text
                ");
                $ins->execute([
                    ':uid'      => $user_uuid,
                    ':first'    => $first_name,
                    ':middle'   => $middle_name,
                    ':last'     => $last_name,
                    ':location' => $plant_location ?: null,
                    ':contact'  => $contact_number ?: null,
                    ':nf'       => $nf,
                    ':nm'       => $nm,
                    ':nl'       => $nl,
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO public.client
                      (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, contact_number)
                    VALUES
                      (:uid, :first, :middle, :last, NULL, NULL, :location, :contact)
                    RETURNING client_id::text
                ");
                $ins->execute([
                    ':uid'      => $user_uuid,
                    ':first'    => $first_name,
                    ':middle'   => $middle_name,
                    ':last'     => $last_name,
                    ':location' => $plant_location ?: null,
                    ':contact'  => $contact_number ?: null,
                ]);
            }

            $client_id = (string)$ins->fetchColumn();
            if (!$client_id) throw new Exception('Failed to create client record');
        }
    }

    /* ----------------- status checks for WOOD flow ---------------- */
    // Compute WOOD status flags (TEXT-safe client_id)
    $stmt = $pdo->prepare("
      SELECT
        bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')     AS has_pending_new,
        bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')     AS has_released_new,
        bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
        bool_or(approval_status ILIKE 'for payment')                                AS has_for_payment
      FROM public.approval
      WHERE client_id::text = :cid AND request_type ILIKE 'wood'
    ");
    $stmt->execute([':cid' => (string)$client_id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $hasPendingNew     = !empty($f['has_pending_new']);
    $hasReleasedNew    = !empty($f['has_released_new']);
    $hasPendingRenewal = !empty($f['has_pending_renewal']);
    $hasForPayment     = !empty($f['has_for_payment']);

    // Global blocker (final stage only — precheck no longer blocks before confirmation)
    if ($hasForPayment) {
        throw new Exception('You still have an unpaid wood permit on record (for payment). Please settle this at the office before filing another request.');
    }

    if ($permit_type === 'renewal') {
        if ($hasPendingRenewal) {
            throw new Exception('You already have a pending RENEWAL wood application.');
        }
        // Enforce: a renewal requires that the client already has a released NEW wood permit record
        if (!$hasReleasedNew) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'block' => 'need_released_new',
                'message' => 'To file a renewal, the client must already have a released NEW wood permit record.'
            ]);
            exit;
        }
    } else { // NEW
        if ($hasPendingNew) {
            throw new Exception('You already have a pending NEW wood application.');
        }
        if ($hasPendingRenewal) {
            throw new Exception('You have a pending RENEWAL; please wait for the update first.');
        }
        if ($hasReleasedNew) {
            // Precheck offers renewal; here we enforce at save-time
            throw new Exception('You already have a RELEASED NEW wood permit. Please file a renewal instead.');
        }
    }

    /* ----------------- requirements row & uploads ----------------- */
    // Create a requirements row then attach file URLs into natural columns
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = (string)$ridStmt->fetchColumn(); // UUID as string
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    // AFTER (new block)
    $bucket = bucket_name();
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $permitFolder = ($permit_type === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "wood/{$permitFolder}/{$client_id}/{$run}/"; // folder layout for all uploads in this request

    $uploaded_map = [];
    $signature_url = null;

    // 1) Application document (optional)
    if (!empty($_FILES['application_doc']) && is_uploaded_file($_FILES['application_doc']['tmp_name'])) {
        $file  = $_FILES['application_doc'];
        $ext   = pick_ext($file, '.doc');
        $fname = 'application' . $ext;
        $path  = $prefix . $fname;
        $mime  = $file['type'] ?: 'application/msword';
        $url   = supa_upload($bucket, $path, $file['tmp_name'], $mime);
        $uploaded_map['application_doc'] = $url;
    }

    // 2) Signature (optional)
    if (!empty($_FILES['signature_file']) && is_uploaded_file($_FILES['signature_file']['tmp_name'])) {
        $file  = $_FILES['signature_file'];
        $ext   = pick_ext($file, '.png');
        $fname = 'signature' . $ext;
        $path  = $prefix . $fname;
        $mime  = $file['type'] ?: 'image/png';
        $url   = supa_upload($bucket, $path, $file['tmp_name'], $mime);
        $uploaded_map['signature_file'] = $signature_url = $url;
    }

    // 3) Renewal files (r1..r7) — your new changes
    $renewalFileDefs = [
        'file-r1' => ['container' => 'uploaded-files-r1', 'label' => 'Previously Approved WPP Permit'],
        'file-r2' => ['container' => 'uploaded-files-r2', 'label' => 'Certificate of Good Standing'],
        'file-r3' => ['container' => 'uploaded-files-r3', 'label' => 'CCTV Installation Certificate'],
        'file-r4' => ['container' => 'uploaded-files-r4', 'label' => 'Monthly Production and Disposition Report'],
        'file-r5' => ['container' => 'uploaded-files-r5', 'label' => 'Certificate of Registration as Log/Veneer/Lumber Importer'],
        'file-r6' => ['container' => 'uploaded-files-r6', 'label' => 'Original Copy of Log/Veneer/Lumber Supply Contracts'],
        'file-r7' => ['container' => 'uploaded-files-r7', 'label' => 'Proof of Importation'],
    ];

    $renewalFilesJson = []; // shape: [containerId => [ {label, filename, url} ]]

    foreach ($renewalFileDefs as $field => $meta) {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) continue;
        $f = $_FILES[$field];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

        $safeName = slugify_name($f['name'] ?? $field);
        $ext = pick_ext($f, '.pdf');
        if (!str_ends_with($safeName, $ext)) {
            $safeName .= $ext;
        }

        $storagePath = $prefix . $field . '_' . $safeName;
        $mime = $f['type'] ?: 'application/octet-stream';

        $publicUrl = supa_upload($bucket, $storagePath, $f['tmp_name'], $mime);

        // keep old behavior for DB column mapping
        $uploaded_map[$field] = $publicUrl;

        // collect nice JSON for UI rendering
        $container = $meta['container'];
        if (!isset($renewalFilesJson[$container])) $renewalFilesJson[$container] = [];
        $renewalFilesJson[$container][] = [
            'label'    => $meta['label'],
            'filename' => $safeName,
            'url'      => $publicUrl,
        ];
    }

    // optional: a flat string if you still want it somewhere else
    $files_json_string = json_encode(['files' => $renewalFilesJson], JSON_UNESCAPED_SLASHES);

    // 4) Remaining files (everything except app doc, signature, and r1..r7)
    $skip = array_merge(['application_doc', 'signature_file'], array_keys($renewalFileDefs));
    foreach ($_FILES as $key => $file) {
        if (in_array($key, $skip, true)) continue;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) continue;

        $ext   = pick_ext($file, '.bin');
        $fname = slugify_name($key) . $ext;
        $path  = $prefix . $fname;
        $mime  = $file['type'] ?: 'application/octet-stream';
        $url   = supa_upload($bucket, $path, $file['tmp_name'], $mime);
        $uploaded_map[$key] = $url;
    }


    /* ---- Map files into requirements columns --------------------- */

    // Known mapping into natural wood_* columns (adjust to your schema)
    $reqMap = [
        // (Optional) If these columns exist in your requirements table, they will be filled.
        'file-a'  => 'wood_uploaded_application_form_url',  // only if column exists
        'file-b'  => 'wood_or_proof_payment_url',           // only if column exists

        // NEW set
        'file-c'  => 'wood_registration_certificate',
        'file-d'  => 'wood_authorization_doc',
        'file-e'  => 'wood_business_plan',
        'file-f'  => 'wood_business_permit',
        'file-g'  => 'wood_ecc_certificate',
        'file-h'  => 'wood_citizenship_proof',
        'file-i'  => 'wood_machine_ownership',
        'file-j'  => 'wood_gis_map',
        'file-k'  => 'wood_hotspot_certification',
        'file-l'  => 'wood_sustainable_sources',
        'file-o2' => 'wood_supply_contracts',
        'file-o3' => 'wood_tree_inventory',
        'file-o4' => 'wood_inventory_data',
        'file-o5' => 'wood_validation_report',
        'file-o7' => 'wood_ctp_ptpr',

        // RENEWAL set (added explicit handling for r1–r3)
        'file-r1' => 'wood_prev_wpp_permit',          // Previously approved WPP permit
        'file-r2' => 'wood_good_standing_cert',       // Certificate of Good Standing
        'file-r3' => 'wood_cctv_install_cert',        // CCTV installation certificate
        'file-r4' => 'wood_monthly_reports',
        'file-r5' => 'wood_importer_registration',
        'file-r6' => 'wood_importer_supply_contracts',
        'file-r7' => 'wood_proof_of_importation',
    ];

    // Build safe UPDATE for requirements
    $setCols = [];
    $params  = [':rid' => $requirement_id];

    // IMPORTANT: requirements.application_form = ONLY the generated .doc URL
    $application_form_value = $uploaded_map['application_doc'] ?? null;
    if (column_exists($pdo, 'public', 'requirements', 'application_form')) {
        $setCols[] = 'application_form = :application_form';
        $params[':application_form'] = $application_form_value;
    }

    // Write known file columns if they exist
    foreach ($reqMap as $inKey => $col) {
        if (!empty($uploaded_map[$inKey]) && column_exists($pdo, 'public', 'requirements', $col)) {
            $setCols[] = "\"$col\" = :$col";
            $params[":$col"] = $uploaded_map[$inKey];
        }
    }

    if ($setCols) {
        $sql = "UPDATE public.requirements SET " . implode(', ', $setCols) . " WHERE requirement_id = :rid";
        $pdo->prepare($sql)->execute($params);
    }

    /* ---------------- application_form (populate real cols) --------------- */
    if (!table_exists($pdo, 'public', 'application_form')) {
        throw new Exception('application_form table is missing.');
    }

    // FORCE JSON ONLY (never HTML) for machinery/suppliers fields
    $machTableText = ensure_json_string($machinery_rows_json);
    $suppliersText = ensure_json_string($supply_rows_json);

    // Keep only small extras in additional_information (not the whole payload)
    $af_additional_information = json_encode([
        'power_source' => $power_source,
        'declaration_address' => $declaration_addr ?: null,
    ], JSON_UNESCAPED_SLASHES);

    $afSql = "
      INSERT INTO public.application_form (
        client_id,
        application_for,
        type_of_permit,
        contact_number,
        email_address,
        present_address,
        legitimate_business_address,
        plant_location,
        plant_location_barangay_municipality_province,
        form_of_ownership,
        kind_of_wood_processing_plant,
        daily_rated_capacity_per8_hour_shift,
        machineries_and_equipment_to_be_used_with_specifications,
        suppliers_json,
        declaration_name,
        permit_number,
        expiry_date,
        complete_name,
        signature_of_applicant,
        additional_information
      ) VALUES (
        :client_id,
        'wood',
        :type_of_permit,
        :contact_number,
        :email_address,
        :present_address,
        :legitimate_business_address,
        :plant_location,
        :plant_location_bmp,
        :form_of_ownership,
        :kind_wpp,
        :daily_cap,
        :mach_table,
        :suppliers_json,
        :declaration_name,
        :permit_number,
        :expiry_date,
        :complete_name,
        :signature_of_applicant,
        :additional_information
      )
      RETURNING application_id
    ";

    $af = $pdo->prepare($afSql);
    $af->execute([
        ':client_id'                 => $client_id,
        ':type_of_permit'           => $permit_type,
        ':contact_number'           => $contact_number ?: null,
        ':email_address'            => $email_address ?: null,
        ':present_address'          => $present_address ?: null,
        ':legitimate_business_address' => $present_address ?: null,
        ':plant_location'           => $plant_location ?: null,
        ':plant_location_bmp'       => $plant_location ?: null,
        ':form_of_ownership'        => $ownership_type ?: null,
        ':kind_wpp'                 => $plant_type ?: null,
        ':daily_cap'                => $daily_capacity ?: null,
        ':mach_table'               => $machTableText,  // JSON ONLY
        ':suppliers_json'           => $suppliersText,  // JSON ONLY
        ':declaration_name'         => $declaration_name ?: $complete_name,
        ':permit_number'            => $r_prev_permit ?: null,
        ':expiry_date'              => $r_expiry_date ?: null,
        ':complete_name'            => $complete_name ?: null,
        ':signature_of_applicant'   => $signature_url ?: null,
        ':additional_information'   => $af_additional_information,
    ]);
    $application_id = (string)$af->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application_form row');

    /* ------------------------- approval row ------------------------ */
    // Build approval insert matching your schema
    $apCols = [];
    foreach (['client_id', 'request_type', 'permit_type', 'approval_status', 'requirement_id'] as $c) {
        if (column_exists($pdo, 'public', 'approval', $c)) $apCols[] = $c;
    }
    if ($application_id && column_exists($pdo, 'public', 'approval', 'application_id')) {
        $apCols[] = 'application_id';
    }
    if (column_exists($pdo, 'public', 'approval', 'submitted_at')) {
        $apCols[] = 'submitted_at';
    }
    if (!$apCols) throw new Exception('Approval table does not have expected columns.');

    $apColSql = implode(', ', $apCols);
    $apValSql = implode(', ', array_map(fn($c) => ':' . $c, $apCols));

    $apSql = "INSERT INTO public.approval ({$apColSql}) VALUES ({$apValSql}) RETURNING approval_id";
    $ap = $pdo->prepare($apSql);

    $apParams = [];
    foreach ($apCols as $c) {
        if ($c === 'client_id')            $apParams[':client_id'] = $client_id;
        elseif ($c === 'request_type')     $apParams[':request_type'] = 'wood';
        elseif ($c === 'permit_type')      $apParams[':permit_type'] = $permit_type;
        elseif ($c === 'approval_status')  $apParams[':approval_status'] = 'pending';
        elseif ($c === 'requirement_id')   $apParams[':requirement_id'] = $requirement_id;
        elseif ($c === 'application_id')   $apParams[':application_id'] = $application_id;
        elseif ($c === 'submitted_at')     $apParams[':submitted_at'] = date('Y-m-d H:i:s');
    }

    $ap->execute($apParams);
    $approval_id = $ap->fetchColumn();
    if (!$approval_id) throw new Exception('Failed to create approval');

    /* ------------------- admin notification ----------------------- */
    // Example message: "Juan requested a wood renewal permit."
    $nicePermit = strtolower($permit_type); // "new" | "renewal"
    $msg = sprintf('%s requested a wood %s permit.', $first_name ?: $complete_name, $nicePermit);

    // NOTE: "from" and "to" are reserved words -> keep them quoted
    $stmt = $pdo->prepare("
        INSERT INTO public.notifications (approval_id, incident_id, message, is_read, \"from\", \"to\")
        VALUES (:approval_id, NULL, :message, false, :from_user, :to_value)
        RETURNING notif_id
    ");
    $stmt->execute([
        ':approval_id' => $approval_id,
        ':message'     => $msg,
        ':from_user'   => $user_uuid,      // current logged-in user
        ':to_value'    => 'Tree Cutting',  // adjust if your routing differs
    ]);
    $notification_id = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'approval_id' => $approval_id,
        'application_id' => $application_id,
        'client_id' => $client_id,
        'requirement_id' => $requirement_id,
        'notification_id' => $notification_id,
        'uploaded' => $uploaded_map,
        'message' => 'Application submitted. We\'ll notify you once reviewed.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (\Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
