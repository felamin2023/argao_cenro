<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // provides $pdo

/*
  save_treecut.php
  Flow: client -> requirements -> application_form -> approval -> notifications
  Key: request_type/application_for MUST match approval_request_type_check.
       We auto-detect the allowed value containing "tree" from the constraint.
*/

/** Read allowed request_type values from approval_request_type_check */
function get_approval_request_types(PDO $pdo): array {
    // Find the check constraint text for public.approval
    $sql = "
      SELECT pg_get_constraintdef(c.oid) AS def
      FROM pg_constraint c
      JOIN pg_class r  ON r.oid = c.conrelid
      JOIN pg_namespace n ON n.oid = r.relnamespace
      WHERE n.nspname = 'public'
        AND r.relname = 'approval'
        AND c.conname ILIKE 'approval_request_type_check%'";
    $def = '';
    if ($stmt = $pdo->query($sql)) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['def'])) $def = (string)$row['def'];
    }
    if ($def === '') return [];

    // Extract strings like 'chainsaw'::text, 'lumber'::text, 'treecutting'::text
    $types = [];
    if (preg_match_all("/'([^']+)'::text/i", $def, $m)) {
        foreach ($m[1] as $val) $types[] = $val;
    } else {
        // Other postgres variants: ... IN ('chainsaw', 'lumber', 'treecutting')
        if (preg_match_all("/'([^']+)'/i", $def, $m2)) {
            foreach ($m2[1] as $val) $types[] = $val;
        }
    }
    // Unique, keep order
    return array_values(array_unique($types));
}

/** Pick the correct request_type value for tree cutting */
function choose_tree_request_type(array $allowed): string {
    if (empty($allowed)) {
        // Safe default guess if we couldn't read constraint
        return 'treecutting';
    }
    // First, direct candidates
    $candidates = ['treecutting','tree_cutting','treecut','tree'];
    foreach ($candidates as $cand) {
        foreach ($allowed as $a) {
            if (strcasecmp($a, $cand) === 0) return $a;
        }
    }
    // Otherwise, pick the first allowed value that contains "tree"
    foreach ($allowed as $a) {
        if (stripos($a, 'tree') !== false) return $a;
    }
    // Last resort: fall back to first allowed (but tell caller)
    return $allowed[0];
}

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');

    // CSRF
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        throw new Exception('Invalid CSRF token');
    }

    // Resolve user UUID (public.users.user_id)
    $idOrUuid = (string)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
    $stmt->execute([':v' => $idOrUuid]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$urow) throw new Exception('User record not found');
    $user_uuid = $urow['user_id'];

    /* -------- read POST -------- */
    $permit_type = isset($_POST['permit_type']) && in_array(strtolower($_POST['permit_type']), ['new','renewal'], true)
        ? strtolower($_POST['permit_type']) : 'new';

    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $contact_num  = trim($_POST['contact_number'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');

    $sitio_street = trim($_POST['street']      ?? '');
    $barangay     = trim($_POST['barangay']    ?? '');
    $municipality = trim($_POST['municipality']?? '');
    $province     = trim($_POST['province']    ?? '');

    $location     = trim($_POST['location']    ?? '');
    $ownership    = trim($_POST['ownership']   ?? '');
    $purpose      = trim($_POST['purpose']     ?? '');

    $species_json = $_POST['species_json'] ?? '[]';
    $species_arr  = json_decode(is_string($species_json) ? $species_json : '[]', true);
    if (!is_array($species_arr)) $species_arr = [];

    $total_count  = trim($_POST['total_count']  ?? '0');
    $total_volume = trim($_POST['total_volume'] ?? '0.00');

    // Derived
    $complete_name   = trim(preg_replace('/\s+/', ' ', "{$first_name} {$middle_name} {$last_name}"));
    $present_address = implode(', ', array_filter([$sitio_street, $barangay, $municipality, $province]));

    // Basic validations
    if ($first_name === '' || $last_name === '') throw new Exception('First and Last name are required');
    foreach (['sitio_street','barangay','municipality','province'] as $k) {
        if ($$k === '') throw new Exception('Complete address is required');
    }
    if ($contact_num === '' || $email === '') throw new Exception('Contact number and email are required');
    if ($location === '') throw new Exception('Location is required');
    if ($purpose === '') throw new Exception('Purpose is required');

    // Figure out the EXACT request_type value the DB accepts
    $allowed = get_approval_request_types($pdo);          // e.g. ['chainsaw','lumber','treecutting',...]
    $request_type = choose_tree_request_type($allowed);   // e.g. 'treecutting'
    $application_for = $request_type;                     // keep consistent

    $pdo->beginTransaction();

    /* 1) client */
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

    /* 2) requirements (no files) */
    $ridStmt = $pdo->query("INSERT INTO public.requirements DEFAULT VALUES RETURNING requirement_id");
    $requirement_id = $ridStmt->fetchColumn();
    if (!$requirement_id) throw new Exception('Failed to create requirements record');

    /* 3) application_form */
    $additional_info = [
        'email'               => $email,
        'registration_number' => $registration_number,
        'ownership'           => $ownership,
        'purpose'             => $purpose,
        'location_detail'     => $location,
        'species'             => $species_arr,
        'total_count'         => (int)$total_count,
        'total_volume'        => (float)$total_volume,
        'form'                => 'tree_cutting',
    ];
    $ai_json = json_encode($additional_info, JSON_UNESCAPED_UNICODE);

    $appSql = "
      INSERT INTO public.application_form
        (client_id, contact_number, application_for, type_of_permit,
         complete_name, present_address, province, location,
         brand_model_serial_number_of_chain_saw, signature_of_applicant, additional_information,
         permit_number, expiry_date, date_today)
      VALUES
        (:client_id, :contact_number, :application_for, :permit_type,
         :complete_name, :present_address, :province, :location,
         NULL, NULL, :additional_information,
         NULL, NULL, to_char(now(),'YYYY-MM-DD'))
      RETURNING application_id
    ";
    $stmt = $pdo->prepare($appSql);
    $stmt->execute([
        ':client_id'              => $client_id,
        ':contact_number'         => $contact_num,
        ':application_for'        => $application_for, // <- matches approval.request_type
        ':permit_type'            => $permit_type,
        ':complete_name'          => $complete_name,
        ':present_address'        => $present_address,
        ':province'               => $province,
        ':location'               => $location,
        ':additional_information' => $ai_json,
    ]);
    $application_id = $stmt->fetchColumn();
    if (!$application_id) throw new Exception('Failed to create application form');

    /* 4) approval — use the EXACT allowed request_type */
    $stmt = $pdo->prepare("
      INSERT INTO public.approval
        (client_id, requirement_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
      VALUES
        (:client_id, :requirement_id, :request_type, 'pending', NULL, :permit_type, :application_id, now())
      RETURNING approval_id
    ");
    $stmt->execute([
        ':client_id'      => $client_id,
        ':requirement_id' => $requirement_id,
        ':request_type'   => $request_type,   // <- detected from constraint
        ':permit_type'    => $permit_type,
        ':application_id' => $application_id,
    ]);
    $approval_id = $stmt->fetchColumn();
    if (!$approval_id) throw new Exception('Failed to create approval record');

    /* 5) notifications (same style as chainsaw) */
    $notifMessage = sprintf(
        '%s submitted a %s tree cutting application (APP %s).',
        $complete_name ?: 'Applicant',
        $permit_type,
        $application_id
    );
    $stmt = $pdo->prepare("
      INSERT INTO public.notifications (approval_id, message, status, is_read)
      VALUES (:approval_id, :message, 'pending', false)
      RETURNING notif_id, created_at
    ");
    $stmt->execute([
        ':approval_id' => $approval_id,
        ':message'     => $notifMessage,
    ]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    echo json_encode([
        'ok'              => true,
        'client_id'       => $client_id,
        'requirement_id'  => $requirement_id,
        'application_id'  => $application_id,
        'approval_id'     => $approval_id,
        'request_type'    => $request_type,
        'notification'    => [
            'notif_id'   => $notif['notif_id']   ?? null,
            'created_at' => $notif['created_at'] ?? null,
            'status'     => 'pending',
            'message'    => $notifMessage,
        ],
        // helpful debug (remove if you prefer)
        'allowed_request_types' => $allowed,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
