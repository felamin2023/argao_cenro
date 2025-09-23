<?php
// ../backend/users/wildlife/save_wildlife.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../connection.php';

/* =============== Debug =============== */
$DEBUG = (isset($_GET['debug']) && $_GET['debug'] === '1') || (getenv('APP_DEBUG') === '1');
if (function_exists('ini_set')) {
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
}
error_reporting(E_ALL);
$DEBUG_ID = bin2hex(random_bytes(8));
$__DBG = ['steps' => [], 'warnings' => []];

set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$__DBG) {
  if (!(error_reporting() & $errno)) return false;
  $__DBG['warnings'][] = ['type' => $errno, 'message' => $errstr, 'file' => $errfile, 'line' => $errline];
  return true;
});

function respond(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* =============== Helpers =============== */
function existing_columns(PDO $pdo, string $schema, string $table, array $columns): array {
  $in = implode(',', array_fill(0, count($columns), '?'));
  $sql = "
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = ? AND table_name = ? AND column_name IN ($in)
  ";
  $params = array_merge([$schema, $table], $columns);
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return array_map(fn($r) => $r['column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}
function column_is_jsonb(PDO $pdo, string $schema, string $table, string $column): bool {
  $sql = "
    SELECT data_type
    FROM information_schema.columns
    WHERE table_schema = :s AND table_name = :t AND column_name = :c
    LIMIT 1
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':s'=>$schema, ':t'=>$table, ':c'=>$column]);
  $type = $stmt->fetchColumn();
  return is_string($type) && strtolower($type) === 'jsonb';
}

/* =============== Guards =============== */
$__DBG['steps'][] = 'Validate method & session';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  respond(['ok' => false, 'error' => 'Method Not Allowed', 'debug_id' => $DEBUG_ID], 405);
}
if (!isset($_SESSION['user_id'])) {
  respond(['ok' => false, 'error' => 'Not authenticated', 'debug_id' => $DEBUG_ID], 401);
}

/* Lenient CSRF */
$__DBG['steps'][] = 'Check CSRF (lenient)';
$csrf_given = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
$csrf_ok = $csrf_given && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $csrf_given);
if (!$csrf_ok) {
  $__DBG['warnings'][] = ['type' => 'CSRF', 'message' => 'CSRF token missing or invalid; proceeding (lenient).'];
}

/* =============== Inputs =============== */
$__DBG['steps'][] = 'Parse inputs';

$permit_type = strtolower(trim((string)($_POST['permit_type'] ?? '')));
if (!in_array($permit_type, ['new', 'renewal'], true)) $permit_type = 'new';

$first_name  = trim((string)($_POST['first_name'] ?? ''));
$middle_name = trim((string)($_POST['middle_name'] ?? ''));
$last_name   = trim((string)($_POST['last_name'] ?? ''));

$applicant_name = trim((string)($_POST['applicant_name'] ?? '')); // optional legacy

$residence_address       = trim((string)($_POST['residence_address'] ?? ''));
$telephone_number        = trim((string)($_POST['telephone_number'] ?? ''));
$establishment_name      = trim((string)($_POST['establishment_name'] ?? ''));
$establishment_address   = trim((string)($_POST['establishment_address'] ?? ''));
$establishment_telephone = trim((string)($_POST['establishment_telephone'] ?? ''));

$zoo                = ((string)($_POST['zoo'] ?? '0')) === '1';
$botanical_garden   = ((string)($_POST['botanical_garden'] ?? '0')) === '1';
$private_collection = ((string)($_POST['private_collection'] ?? '0')) === '1';
$postal_address     = trim((string)($_POST['postal_address'] ?? ''));

$wfp_number = trim((string)($_POST['wfp_number'] ?? ''));
$issue_date = trim((string)($_POST['issue_date'] ?? ''));

/* NEW: signature (data URL sent from frontend) */
$signature_data = trim((string)($_POST['signature_data'] ?? ''));
if ($signature_data !== '' && !preg_match('#^data:image/(png|jpeg);base64,#i', $signature_data)) {
  respond(['ok'=>false,'error'=>'Invalid signature format.','debug_id'=>$DEBUG_ID], 400);
}

$animals_raw = (string)($_POST['animals_json'] ?? '[]');
$animals = json_decode($animals_raw, true);
if (!is_array($animals)) $animals = [];

$animals_norm = [];
foreach ($animals as $row) {
  if (!is_array($row)) continue;
  $common   = isset($row['commonName'])     ? (string)$row['commonName']     : '';
  $science  = isset($row['scientificName']) ? (string)$row['scientificName'] : '';
  $qty      = isset($row['quantity'])       ? (string)$row['quantity']       : '';
  $remarks  = isset($row['remarks'])        ? (string)$row['remarks']        : '';
  if ($common === '' && $science === '' && $qty === '') continue;
  $one = ['commonName'=>$common, 'scientificName'=>$science, 'quantity'=>$qty];
  if ($remarks !== '') $one['remarks'] = $remarks;
  $animals_norm[] = $one;
}

/* =============== Validation =============== */
$__DBG['steps'][] = 'Validate inputs';

if ($permit_type === 'new') {
  if ($first_name === '' || $last_name === '') {
    respond(['ok' => false, 'error' => 'First and Last name are required (New).', 'debug_id' => $DEBUG_ID], 400);
  }
} else {
  if ($first_name === '' || $last_name === '') {
    respond(['ok' => false, 'error' => 'First and Last name are required (Renewal).', 'debug_id' => $DEBUG_ID], 400);
  }
  if ($wfp_number === '') respond(['ok'=>false,'error'=>'Original WFP No. is required (Renewal).','debug_id'=>$DEBUG_ID], 400);
  if ($issue_date === '') respond(['ok'=>false,'error'=>'Issued on (date) is required (Renewal).','debug_id'=>$DEBUG_ID], 400);
}

if ($residence_address === '')     respond(['ok'=>false,'error'=>'Residence Address is required.','debug_id'=>$DEBUG_ID], 400);
if ($establishment_name === '')    respond(['ok'=>false,'error'=>'Name of Establishment is required.','debug_id'=>$DEBUG_ID], 400);
if ($establishment_address === '') respond(['ok'=>false,'error'=>'Address of Establishment is required.','debug_id'=>$DEBUG_ID], 400);
if (count($animals_norm) < 1)      respond(['ok'=>false,'error'=>'Please add at least one animal entry.','debug_id'=>$DEBUG_ID], 400);

/* Build complete_name from parts; fall back to applicant_name if all blank */
$complete_name_parts = trim(preg_replace('/\s+/', ' ', "$first_name $middle_name $last_name"));
$complete_name = $complete_name_parts !== '' ? $complete_name_parts : $applicant_name;

/* =============== Resolve auth user UUID =============== */
$__DBG['steps'][] = 'Resolve user UUID via public.users';
$idOrUuid = (string)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id FROM public.users WHERE id::text = :v OR user_id::text = :v LIMIT 1");
$stmt->execute([':v' => $idOrUuid]);
$urow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$urow || empty($urow['user_id'])) {
  respond(['ok' => false, 'error' => 'User record not found', 'debug_id' => $DEBUG_ID], 401);
}
$user_uuid = $urow['user_id'];

/* =============== DB Write =============== */
try {
  $__DBG['steps'][] = 'Begin transaction';
  $pdo->beginTransaction();

  /* 1) client (new per submission) */
  $__DBG['steps'][] = 'Insert client';
  $stmt = $pdo->prepare("
    INSERT INTO public.client
      (user_id, first_name, middle_name, last_name, sitio_street, barangay, municipality)
    VALUES
      (:uid, :first, :middle, :last, NULL, NULL, NULL)
    RETURNING client_id
  ");
  $stmt->execute([
    ':uid'    => $user_uuid,
    ':first'  => $first_name,
    ':middle' => $middle_name,
    ':last'   => $last_name,
  ]);
  $client_id = $stmt->fetchColumn();
  if (!$client_id) throw new RuntimeException('Failed to create client record');

  /* 2) application_form */
  $__DBG['steps'][] = 'Insert application_form';
  $additional_information = [
    'form' => 'wildlife',
    'permit_type' => $permit_type,
    'categories' => [
      'zoo' => $zoo,
      'botanical_garden' => $botanical_garden,
      'private_collection' => $private_collection,
    ],
    'postal_address' => $postal_address,
    'animals' => $animals_norm,
    'renewal' => ['wfp_number' => $wfp_number, 'issue_date' => $issue_date],
    'establishment' => [
      'name' => $establishment_name,
      'address' => $establishment_address,
      'telephone' => $establishment_telephone,
    ],
  ];
  $additional_information_txt = json_encode($additional_information, JSON_UNESCAPED_UNICODE);

  $isJsonb = column_is_jsonb($pdo, 'public', 'application_form', 'additional_information');
  $aiCast  = $isJsonb ? '::jsonb' : '';

  // NOTE: include signature_of_applicant in the insert
  $sql = "
    INSERT INTO public.application_form
      (client_id, contact_number, application_for, type_of_permit,
       complete_name, present_address, province, location,
       signature_of_applicant, additional_information, date_today)
    VALUES
      (:client_id, :contact_number, 'wildlife', :permit_type,
       :complete_name, :present_address, NULL, NULL,
       :signature_of_applicant, :additional_information{$aiCast}, to_char(now(),'YYYY-MM-DD'))
    RETURNING application_id
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':client_id'              => $client_id,
    ':contact_number'         => $telephone_number,
    ':permit_type'            => $permit_type,
    ':complete_name'          => $complete_name,
    ':present_address'        => $residence_address,
    // store the data URL directly in the text column
    ':signature_of_applicant' => ($signature_data !== '' ? $signature_data : null),
    ':additional_information' => $additional_information_txt,
  ]);
  $application_id = $stmt->fetchColumn();
  if (!$application_id) throw new RuntimeException('Failed to create application_form');

  /* 2b) optional: type_wildlife */
  $__DBG['steps'][] = 'Update type_wildlife if present';
  $selected = [];
  if ($zoo)                $selected[] = 'zoo';
  if ($botanical_garden)   $selected[] = 'botanical_garden';
  if ($private_collection) $selected[] = 'private_collection';
  $type_wildlife_value = $selected ? implode(',', $selected) : null;

  $have = existing_columns($pdo, 'public', 'application_form', ['type_wildlife']);
  if (in_array('type_wildlife', $have, true)) {
    $q = "UPDATE public.application_form SET type_wildlife = :tw WHERE application_id = :app_id";
    $pdo->prepare($q)->execute([':tw' => $type_wildlife_value, ':app_id' => $application_id]);
  } else {
    $__DBG['warnings'][] = ['type'=>'SCHEMA','message'=>"Column public.application_form.type_wildlife not found; skipped"];
  }

  /* 3) approval */
  $__DBG['steps'][] = 'Insert approval';
  $stmt = $pdo->prepare("
    INSERT INTO public.approval
      (client_id, request_type, approval_status, seedl_req_id, permit_type, application_id, submitted_at)
    VALUES
      (:client_id, 'wildlife', 'pending', NULL, :permit_type, :application_id, now())
    RETURNING approval_id
  ");
  $stmt->execute([
    ':client_id'      => $client_id,
    ':permit_type'    => $permit_type,
    ':application_id' => $application_id,
  ]);
  $approval_id = $stmt->fetchColumn();
  if (!$approval_id) throw new RuntimeException('Failed to create approval record');

  /* 4) notifications */
  $__DBG['steps'][] = 'Insert notification';
  $notifMessage = sprintf('%s submitted a %s wildlife application (APP %s).',
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

  $resp = [
    'ok' => true,
    'client_id' => $client_id,
    'application_id' => $application_id,
    'approval_id' => $approval_id,
    'type_wildlife' => $type_wildlife_value,
    'notification' => [
      'notif_id'   => $notif['notif_id']   ?? null,
      'created_at' => $notif['created_at'] ?? null,
      'status'     => 'pending',
      'message'    => $notifMessage,
    ],
    'debug_id' => $DEBUG_ID,
  ];
  if ($DEBUG) $resp['debug'] = $__DBG;

  respond($resp, 200);

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();

  $status = 400;
  $msg = $e->getMessage();
  if (!preg_match('/(Invalid|required|Please add)/i', $msg)) {
    $status = 500;
  }
  $payload = ['ok' => false, 'error' => $msg, 'debug_id' => $DEBUG_ID];
  if ($DEBUG) {
    $payload['debug'] = array_merge($__DBG, [
      'exception' => [
        'type' => get_class($e),
        'message' => $msg,
        'code' => (int)$e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ],
    ]);
  }
  respond($payload, $status);
}
