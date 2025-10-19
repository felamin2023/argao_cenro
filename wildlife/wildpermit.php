<?php

/**
 * wildlife/wildpermit.php — TOP (PHP only, before <!DOCTYPE html>)
 * - Auth guard (Admin + Wildlife dept)
 * - Supabase Storage upload (approved_docs bucket)
 * - Server-side PDF generation (Dompdf)
 * - AJAX endpoints:
 *     • mark_read
 *     • mark_all_read
 *     • details
 *     • decide  (approve => generate PDF → upload → save URL in public.approved_docs)
 *     • mark_notifs_for_approval
 */

declare(strict_types=1);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('Asia/Manila');

/* ---------- Auth guard (Admin + Wildlife) ---------- */
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ---------- Helpers ---------- */

$user_id = (string)$_SESSION['user_id'];
try {
    $st = $pdo->prepare("
        SELECT first_name, last_name, email, role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin    = $me && strtolower((string)$me['role']) === 'admin';
    $isWildlife = $me && strtolower((string)$me['department']) === 'wildlife';

    if (!$isAdmin || !$isWildlife) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[WILD-ADMIN GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function notempty($v): bool
{
    return $v !== null && trim((string)$v) !== '' && $v !== 'null';
}
function time_elapsed_string($datetime, $full = false): string
{
    if (!$datetime) return '';
    $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago  = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);
    $weeks = (int)floor($diff->d / 7);
    $days  = $diff->d % 7;
    $map   = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = [];
    foreach ($map as $k => $label) {
        $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
    }
    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

$FILE_BASE = '';
function normalize_url(string $v, string $base): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('~^https?://~i', $v)) return $v;
    if ($base !== '') {
        $base = rtrim($base, '/');
        $v = ltrim($v, '/');
        return $base . '/' . $v;
    }
    return '';
}

/* ---------- Supabase Storage (approved_docs) ---------- */
$STORAGE_BUCKET = 'approved_docs';

/* read from env first, then constants from connection.php */
$SUPABASE_URL = getenv('SUPABASE_URL')
    ?: (defined('SUPABASE_URL') ? SUPABASE_URL : '');

$SUPABASE_SERVICE_ROLE = getenv('SUPABASE_SERVICE_ROLE_KEY')
    ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? constant('SUPABASE_SERVICE_ROLE_KEY')
        : (defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : ''));


/** Upload raw bytes to Supabase Storage and return public URL */
function supabase_storage_upload(string $bucket, string $objectPath, string $bytes, string $contentType = 'application/pdf'): ?string
{
    global $SUPABASE_URL, $SUPABASE_SERVICE_ROLE;
    if (!$SUPABASE_URL || !$SUPABASE_SERVICE_ROLE) return null;

    $url = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/" . rawurlencode($bucket) . "/" .
        implode('/', array_map('rawurlencode', explode('/', $objectPath)));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE,
            'apikey: ' . $SUPABASE_SERVICE_ROLE,
            'Content-Type: ' . $contentType,
            'x-upsert: true',
            'Cache-Control: public, max-age=31536000',
        ],
        CURLOPT_POSTFIELDS     => $bytes,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false || $http < 200 || $http >= 300) {
        error_log('[SUPABASE UPLOAD] HTTP ' . $http . ' ERR: ' . curl_error($ch) . ' BODY: ' . substr((string)$res, 0, 300));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $publicPath = implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    return rtrim($SUPABASE_URL, '/') . "/storage/v1/object/public/" . rawurlencode($bucket) . "/" . $publicPath;
}

/* ---------- PDF builder utilities ---------- */
function img_data_uri(string $path): string
{
    if (!is_file($path)) return '';
    $mime = @mime_content_type($path) ?: 'image/png';
    $bin  = @file_get_contents($path);
    if ($bin === false) return '';
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

function build_permit_html(array $d): string
{
    $denr = img_data_uri(__DIR__ . '/denr.png');
    $ph   = img_data_uri(__DIR__ . '/pilipinas.png');
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $rows  = '';
    $total = 0;
    foreach ($d['animals'] as $a) {
        $cn  = $a['commonName']     ?? $a['common']     ?? '';
        $sn  = $a['scientificName'] ?? $a['scientific'] ?? '';
        $qty = (int)($a['quantity'] ?? 0);
        $total += $qty;
        $rows .= '<tr><td>' . $e($cn) . '</td><td>' . $e($sn) . '</td><td>' . $qty . '</td></tr>';
    }

    return '
<!doctype html><html><head><meta charset="utf-8"><style>
*{box-sizing:border-box;font-family:"Times New Roman",serif}
body{margin:0;background:#fff}
@page{size:letter;margin:.5in}

:root{ --stamp:#111; }

.container{position:relative;width:100%;min-height:11in;margin:0 auto;background:#fff}

/* ===== Header (table for Dompdf reliability) ===== */
.header-wrap{position:relative;margin:0 0 12px 0}
.header-table{width:100%;border-collapse:collapse;border-bottom:5px solid #f00}
.header-table td{vertical-align:middle}
.hcell-left{width: 90px}
.hcell-right{width:90px}
.hcell-left img{
  display:block;margin:0 auto;
  max-width:750px; max-height:75px;
}
.hcell-right img{
  display:block;margin:0 auto;
  max-width:90px; max-height:90px;
}
.title-block{text-align:center;padding:8px 6px}
.title-block h1{margin:0 0 6px 0;font-weight:800;font-size:20px;line-height:1.08}
.title-block .sub{margin:0;font-size:16px}

/* ===== Outline APPROVED stamp at upper-right ===== */
/* ===== APPROVED oval (Dompdf-safe, centered with padding) ===== */
/* ===== APPROVED oval (optically centered, Dompdf-safe) ===== */
.stamp{
  position:absolute; top:.18in; right:.18in;
  width:1.45in; height:.95in; border-radius:50%;
  border:6px solid var(--stamp); background:transparent; color:var(--stamp);
  display:table;                /* key: enables true vertical centering */
  transform:rotate(-12deg);
  z-index:10; pointer-events:none; overflow:hidden;
}
.stamp__text{
  display:table-cell; vertical-align:middle; text-align:center;
  height:.95in; width:100%;
  padding:0 .16in;               /* inner breathing room */
  text-transform:uppercase; letter-spacing:1.2px;
  font-weight:900; font-size:20px; line-height:1; /* no extra top gap */
}



/* ===== Body ===== */
.permit-number{font-size:15px;margin:12px 18px}
.permit-title{text-align:center;margin:15px 0 6px 0;font-size:20px;font-weight:700;text-decoration:underline}
.subtitle{text-align:center;margin-bottom:14px;font-size:16px}
.underline-field,.small-underline,.inline-underline{border-bottom:1px solid #000;display:inline-block;margin:0 3px;padding:0 3px}
.underline-field{min-width:280px}.inline-underline{min-width:200px}.small-underline{min-width:140px}
.permit-body{font-size:14px;line-height:1.5;color:#000;padding:0 18px 18px 18px}
.info-table{width:100%;border-collapse:collapse;margin:15px 0;font-size:13px;table-layout:fixed}
.info-table th,.info-table td{border:1px solid #000;padding:8px;text-align:left;word-break:break-word}
.info-table th{background:#f0f0f0}
.nothing-follows{text-align:center;margin-top:10px}
.contact-info{text-align:center;font-size:13px;margin-top:25px}
</style></head><body>

<div class="container" id="permit">

  <div class="header-wrap">
    <table class="header-table">
      <tr>
        <td class="hcell-left">
          <img src="' . $e($denr) . '" alt="DENR">
        </td>
        <td class="hcell-mid">
          <div class="title-block">
            <h1>Department of Environment and Natural Resources</h1>
            <p class="sub">Region 7</p>
          </div>
        </td>
        <td class="hcell-right">
          <img src="' . $e($ph) . '" alt="Bagong Pilipinas">
        </td>
      </tr>
    </table>
    <div class="stamp"><div class="stamp__text">APPROVED</div></div>

  </div>

  <div class="permit-number">
    <p>WFP No. ' . $e($d['wfp_no']) . '</p>
    <p>' . $e(strtoupper((string)$d['permit_type'])) . ' PERMIT</p>
    <p>SERIES OF ' . $e($d['series']) . '</p>
    <p>Date Issued: ' . $e($d['date_issued_fmt']) . '</p>
    <p>Expiry Date: ' . $e($d['expiry_date_fmt']) . '</p>
    
  </div>

  <div class="permit-title">WILDLIFE FARM PERMIT</div>
  <div class="subtitle">(Small Scale Farming)</div>

  <div class="permit-body">
    <p>
      Pursuant to the provisions of Republic Act No. 9147 otherwise known as the
      "Wildlife Resources Conservation and Protection Act" of 2001, as implemented by the
      Joint DENR-DA-PCSD Administrative Order No. 1, Series of 2004 and in consonance with
      the provisions of Section 5-9 of DENR Administrative Order No. 2004-55 dated August 31, 2004,
      and upon the recommendation of the Regional Wildlife Committee during its meeting on
      <strong>' . $e($d['meeting_date_fmt']) . '</strong> through RWC Resolution No. 04-' . $e($d['series']) . ',
    </p>

    <div>
      <span class="underline-field">' . $e($d['establishment_name']) . '</span>
      represented by <span class="inline-underline">' . $e($d['client_name']) . '</span>
      with facility located at <span class="inline-underline">' . $e($d['establishment_address']) . '</span>
      is hereby granted a Wildlife Farm Permit (WFP) subject to the terms, conditions and restrictions herein specified:
      valid until <strong>' . $e($d['expiry_date_fmt']) . '</strong>.
    </div>

    <ol class="terms-list">
      <li>
        The Permittee shall maintain and operate a wildlife breeding farm facility in
        <span class="underline-field">' . $e($d['establishment_address']) . '</span>
        with wildlife species for breeding, educational and trading/commercial purposes.
      </li>

      <table class="info-table">
        <tr><th>Common Name</th><th>Scientific Name</th><th>Quantity</th></tr>' . $rows . '
        <tr><td><strong>TOTAL</strong></td><td></td><td><strong>' . $e((string)$total) . '</strong></td></tr>
      </table>

      <p class="nothing-follows">NOTHING FOLLOWS</p>

      <li>The Permittee shall allow, upon notice, any DENR authorized representative(s) to visit and/or inspect the farm facility or premises and conduct an inventory of existing stocks;</li>
      <li>The Permittee shall submit monthly production and quarterly reports to the DENR Region 7 …</li>
      <li>… (retain full terms from your original) …</li>
    </ol>

    <div class="contact-info">
      <p>National Government Center, Sudlon, Lahug, Cebu City, Philippines 6000</p>
      <p>Tel. Nos: (+6332) 346-9612, 328-3335 Fax No: 328-3336</p>
      <p>E-mail: t7@denr.gov.ph / redeenr7@yahoo.com</p>
    </div>
  </div>
</div>
</body></html>';
}







/**
 * Generate PDF, upload to Supabase Storage, insert row in public.approved_docs, return URL/filename.
 * Storage key: approved_docs/wildlife/new permit/{client_id}/{approved_id}/files/{filename}.pdf
 * Expects $inputs = ['wfp_no','series','meeting_date' (YYYY-MM-DD)]
 */
function generate_and_store_permit(PDO $pdo, string $approvalId, array $inputs): array
{
    global $STORAGE_BUCKET;

    // Fetch application & client
    $st = $pdo->prepare("
        SELECT a.application_id, a.client_id, a.permit_type,
               af.additional_information,
               c.first_name, c.last_name
        FROM public.approval a
        LEFT JOIN public.application_form af ON af.application_id = a.application_id
        LEFT JOIN public.client c            ON c.client_id       = a.client_id
        WHERE a.approval_id=:aid
        LIMIT 1
    ");
    $st->execute([':aid' => $approvalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $permitType = (string)($row['permit_type'] ?? '');

    $clientId = (string)($row['client_id'] ?? '');
    $ai = [];
    if (!empty($row['additional_information'])) {
        $tmp = json_decode((string)$row['additional_information'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $ai = $tmp;
    }

    // Dates
    $dateIssued  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $expiryDate  = (clone $dateIssued)->modify('+2 years');
    $meetingDate = DateTime::createFromFormat('Y-m-d', (string)($inputs['meeting_date'] ?? ''))
        ?: new DateTime('now', new DateTimeZone('Asia/Manila'));

    // Data for template (no sample values)
    $data = [
        'wfp_no'                => (string)($inputs['wfp_no'] ?? ''),
        'series'                => (string)($inputs['series'] ?? ''),
        'meeting_date_fmt'      => $meetingDate->format('F j, Y'),
        'date_issued_fmt'       => $dateIssued->format('F j, Y'),
        'expiry_date_fmt'       => $expiryDate->format('F j, Y'),
        'establishment_name'    => $ai['establishment_name']    ?? ($ai['establishmentName'] ?? ''),
        'establishment_address' => $ai['establishment_address'] ?? ($ai['establishmentAddress'] ?? ''),
        'client_name'           => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'animals'               => is_array($ai['animals'] ?? null) ? $ai['animals'] : [],
        'permit_type'           => $permitType,
    ];

    // Render HTML → PDF
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml(build_permit_html($data), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    // Insert approved_docs row FIRST to get approved_id
    $ins = $pdo->prepare("
        INSERT INTO public.approved_docs
            (approval_id, approved_document, date_issued, expiry_date, wfp_no, series, meeting_date)
        VALUES
            (:aid, ''::text, :issued, :expiry, :wfp, :series, :meet)
        RETURNING approved_id
    ");
    $ins->execute([
        ':aid'    => $approvalId,
        ':issued' => $dateIssued->format('Y-m-d'),
        ':expiry' => $expiryDate->format('Y-m-d'),
        ':wfp'    => (string)$inputs['wfp_no'],
        ':series' => (string)$inputs['series'],
        ':meet'   => $meetingDate->format('Y-m-d'),
    ]);
    $approvedId = (string)$ins->fetchColumn();
    if (!$approvedId) {
        throw new RuntimeException('Failed to get approved_id.');
    }

    // Compose storage key per required structure
    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$inputs['wfp_no'] . '_' . (string)$inputs['series']);
    $filename = 'WFP_' . $safeBase . '_' . date('Ymd_His') . '.pdf';
    $objectKey = "wildlife/new permit/{$clientId}/{$approvedId}/{$filename}";


    // Upload to Supabase Storage
    $publicUrl = supabase_storage_upload($STORAGE_BUCKET, $objectKey, $pdf, 'application/pdf');
    if (!$publicUrl) {
        throw new RuntimeException('Supabase upload failed.');
    }

    // Update row with public URL
    $pdo->prepare("UPDATE public.approved_docs SET approved_document=:u WHERE approved_id=:id")
        ->execute([':u' => $publicUrl, ':id' => $approvedId]);

    return ['url' => $publicUrl, 'filename' => $filename, 'approved_id' => $approvedId];
}

/* ---------------- AJAX (mark single read) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_read') {
    header('Content-Type: application/json');
    $notifId    = $_POST['notif_id'] ?? '';
    $incidentId = $_POST['incident_id'] ?? '';
    if (!$notifId && !$incidentId) {
        echo json_encode(['ok' => false, 'error' => 'missing notif_id or incident_id']);
        exit();
    }
    try {
        if ($notifId)    $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE notif_id=:id")->execute([':id' => $notifId]);
        if ($incidentId) $pdo->prepare("UPDATE public.incident_report SET is_read=true WHERE incident_id=:id")->execute([':id' => $incidentId]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[WILD-APPSTAT MARK_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (MARK ALL READ) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_all_read') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $updPermits = $pdo->prepare("
            UPDATE public.notifications
               SET is_read = true
             WHERE LOWER(COALESCE(\"to\", '')) = 'wildlife'
               AND is_read = false
        ");
        $updPermits->execute();
        $countPermits = $updPermits->rowCount();

        $updInc = $pdo->prepare("
            UPDATE public.incident_report
               SET is_read = true
             WHERE LOWER(COALESCE(category, '')) = 'wildlife monitoring'
               AND is_read = false
        ");
        $updInc->execute();
        $countInc = $updInc->rowCount();

        $pdo->commit();
        echo json_encode(['ok' => true, 'updated' => ['permits' => $countPermits, 'incidents' => $countInc]]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[WILD-APPSTAT MARK_ALL_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (details) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'details') {
    header('Content-Type: application/json');
    $approvalId = $_GET['approval_id'] ?? '';
    if (!$approvalId) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }

    try {
        $st = $pdo->prepare("
          SELECT a.approval_id,
                 LOWER(COALESCE(a.request_type,'')) AS request_type,
                 COALESCE(NULLIF(btrim(a.permit_type),''),'none')        AS permit_type,
                 COALESCE(NULLIF(btrim(a.approval_status),''),'pending') AS approval_status,
                 a.submitted_at,
                 a.application_id,
                 a.requirement_id,
                 c.first_name, c.last_name
          FROM public.approval a
          LEFT JOIN public.client c ON c.client_id = a.client_id
          WHERE a.approval_id = :aid
            AND LOWER(COALESCE(a.request_type,'')) = 'wildlife'
          LIMIT 1
        ");
        $st->execute([':aid' => $approvalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'not found']);
            exit;
        }

        $appFields = [];
        if (notempty($row['application_id'])) {
            $st2 = $pdo->prepare("SELECT * FROM public.application_form WHERE application_id=:app LIMIT 1");
            $st2->execute([':app' => $row['application_id']]);
            $app = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($app as $k => $v) {
                $lk = strtolower((string)$k);
                if (in_array($lk, ['id', 'client_id', 'additional_information', 'additional_info', 'additionalinformation', 'additional'], true)) continue;
                if (!notempty($v)) continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $val   = (string)$v;
                $kind  = 'text';
                $trim = ltrim($val);
                if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $kind = 'json';
                        $val = $decoded;
                    }
                } elseif (preg_match('~^https?://~i', $val)) {
                    $kind = 'link';
                    // If it's a signature and the URL points to an image, render it as an image
                    $path = parse_url($val, PHP_URL_PATH) ?? '';
                    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $isImg = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
                    if ($isImg && stripos($label, 'signature') !== false) {
                        $kind = 'image';
                    }
                }
                $appFields[] = ['label' => $label, 'key' => $k, 'kind' => $kind, 'value' => $val];
            }
        }

        $files = [];
        if (notempty($row['requirement_id'])) {
            $st3 = $pdo->prepare("SELECT * FROM public.requirements WHERE requirement_id=:rid LIMIT 1");
            $st3->execute([':rid' => $row['requirement_id']]);
            $req = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($req as $k => $v) {
                if (in_array($k, ['id', 'requirement_id'], true)) continue;
                if (!notempty($v)) continue;
                $url = normalize_url((string)$v, $FILE_BASE);
                if ($url === '') continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $path  = parse_url($url, PHP_URL_PATH) ?? '';
                $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $files[] = ['name' => $label, 'url' => $url, 'ext' => $ext];
            }
        }

        echo json_encode([
            'ok' => true,
            'meta' => [
                'client'       => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'request_type' => $row['request_type'] ?? '',
                'permit_type'  => $row['permit_type'] ?? '',
                'status'       => $row['approval_status'] ?? '',
                'submitted_at' => $row['submitted_at'] ?? null,
            ],
            'application' => $appFields,
            'files'       => $files
        ]);
    } catch (Throwable $e) {
        error_log('[WILD-DETAILS AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (decide) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'decide') {
    header('Content-Type: application/json');
    $approvalId = $_POST['approval_id'] ?? '';
    $action     = strtolower(trim((string)($_POST['action'] ?? '')));
    $reason     = trim((string)($_POST['reason'] ?? ''));

    if (!$approvalId || !in_array($action, ['approve', 'reject', 'release'], true)) {
        echo json_encode(['ok' => false, 'error' => 'invalid params']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT a.approval_id, a.approval_status, a.request_type, a.client_id
            FROM public.approval a
            WHERE a.approval_id=:aid AND LOWER(COALESCE(a.request_type,''))='wildlife'
            FOR UPDATE
        ");
        $st->execute([':aid' => $approvalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'approval not found']);
            exit;
        }

        $status   = strtolower((string)($row['approval_status'] ?? 'pending'));
        $adminId  = $user_id;
        $fromDept = isset($me['department']) ? (string)$me['department'] : null;

        // who to notify (client user_id)
        $toUserId = null;
        if (!empty($row['client_id'])) {
            $stCli = $pdo->prepare("SELECT user_id FROM public.client WHERE client_id=:cid LIMIT 1");
            $stCli->execute([':cid' => $row['client_id']]);
            $toUserId = $stCli->fetchColumn() ?: null;
        }

        if ($action === 'approve') {
            /* ---- PENDING -> FOR PAYMENT ---- */
            if ($status !== 'pending') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'invalid state']);
                exit;
            }

            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status='for payment',
                       approved_at=NULL, approved_by=NULL,
                       rejected_at=NULL, reject_by=NULL, rejection_reason=NULL
                 WHERE approval_id=:aid
            ")->execute([':aid' => $approvalId]);

            // Notify client (client-facing)
            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromDept, :toUser)
            ")->execute([
                ':aid'      => $approvalId,
                ':msg'      => 'Your wildlife permit was approved. You have to pay personally.',
                ':fromDept' => $fromDept,
                ':toUser'   => $toUserId
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'for payment']);
        } elseif ($action === 'release') {
            /* ---- FOR PAYMENT -> RELEASED (WITH PDF) ---- */
            if ($status !== 'for payment') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'invalid state']);
                exit;
            }

            $wfpNo  = trim((string)($_POST['wfp_no'] ?? ''));
            $series = trim((string)($_POST['series'] ?? ''));
            $meet   = trim((string)($_POST['meeting_date'] ?? '')); // YYYY-MM-DD
            if ($wfpNo === '' || $series === '' || $meet === '') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Missing WFP No., Series, or Meeting Date']);
                exit;
            }

            // Generate + store PDF
            $docInfo = generate_and_store_permit($pdo, $approvalId, [
                'wfp_no'       => $wfpNo,
                'series'       => $series,
                'meeting_date' => $meet,
            ]);
            $docUrl = $docInfo['url'] ?? null;

            // Flip to released and stamp approver
            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status='released',
                       approved_at=now(), approved_by=:by
                 WHERE approval_id=:aid
            ")->execute([':by' => $adminId, ':aid' => $approvalId]);

            // Notify client (client-facing)
            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromDept, :toUser)
            ")->execute([
                ':aid'      => $approvalId,
                ':msg'      => 'Your wildlife permit was released. You can download your permit now.',
                ':fromDept' => $fromDept,
                ':toUser'   => $toUserId
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'released', 'document_url' => $docUrl]);
        } else {
            /* ---- PENDING -> REJECTED ---- */
            if ($status !== 'pending') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'invalid state']);
                exit;
            }
            if ($reason === '') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'reason required']);
                exit;
            }

            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status='rejected',
                       rejected_at=now(), reject_by=:by, rejection_reason=:reason
                 WHERE approval_id=:aid
            ")->execute([':by' => $adminId, ':reason' => $reason, ':aid' => $approvalId]);

            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromDept, :toUser)
            ")->execute([
                ':aid'      => $approvalId,
                ':msg'      => 'Your wildlife permit request was rejected. Reason: ' . $reason,
                ':fromDept' => $fromDept,
                ':toUser'   => $toUserId
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'rejected']);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[WILD-DECIDE AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}


/* ---------------- AJAX (mark notifications for approval) ---------------- */
/* ---------------- AJAX (mark notifications for approval) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_notifs_for_approval') {
    header('Content-Type: application/json');
    $aid = $_POST['approval_id'] ?? '';
    if (!$aid) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }
    try {
        $pdo->prepare("
            UPDATE public.notifications
               SET is_read = true
             WHERE approval_id = :aid
               AND is_read = false
               AND LOWER(BTRIM(COALESCE(\"to\", ''))) = 'wildlife'
        ")->execute([':aid' => $aid]);

        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[WILD-MARK-READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}


/* ---------------- NOTIFS for header ---------------- */
$wildNotifs = [];
$unreadWildlife = 0;
try {
    $notifRows = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name  AS client_first, c.last_name AS client_last,
               NULL::text AS incident_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", ''))='wildlife'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $wildNotifs = $notifRows;

    $unreadPermits = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", ''))='wildlife' AND n.is_read=false
    ")->fetchColumn();

    $unreadIncidents = (int)$pdo->query("
        SELECT COUNT(*) FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='wildlife monitoring' AND is_read=false
    ")->fetchColumn();

    $unreadWildlife = $unreadPermits + $unreadIncidents;

    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE lower(COALESCE(category,''))='wildlife monitoring'
        ORDER BY created_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[WILDHOME NOTIFS-FOR-NAV] ' . $e->getMessage());
    $wildNotifs = [];
    $unreadWildlife = 0;
}

/* ---------------- Page data ---------------- */
$rows = [];
try {
    $rows = $pdo->query("
        SELECT a.approval_id,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               COALESCE(NULLIF(btrim(a.permit_type),''),'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status),''),'pending') AS approval_status,
               a.submitted_at,
               c.first_name
        FROM public.approval a
        LEFT JOIN public.client c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(a.request_type,''))='wildlife'
        ORDER BY a.submitted_at DESC NULLS LAST, a.approval_id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[WILD-ADMIN LIST] ' . $e->getMessage());
}

$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
    <title>Wildlife Approvals</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="/denr/superadmin/css/wildhome.css" />

    <style>
        :root {
            color-scheme: light;
        }

        body {
            background: #f3f4f6 !important;
            color: #111827;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial;
        }

        .hidden {
            display: none !important;
        }

        body::before {
            content: none !important;
        }

        .nav-item .badge {
            position: absolute;
            top: -6px;
            right: -6px;
        }

        .nav-item.dropdown.open .badge {
            display: none;
        }

        .dropdown-menu.notifications-dropdown {
            display: grid;
            grid-template-rows: auto 1fr auto;
            width: min(460px, 92vw);
            max-height: 72vh;
            overflow: hidden;
            padding: 0
        }

        .notifications-dropdown .notification-header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb
        }

        .notifications-dropdown .notification-list {
            overflow: auto;
            padding: 8px 0;
            background: #fff
        }

        .notifications-dropdown .notification-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px 16px
        }

        .notifications-dropdown .view-all {
            font-weight: 600;
            color: #1b5e20;
            text-decoration: none
        }

        .notification-item {
            padding: 18px;
            background: #f8faf7
        }

        .notification-item.unread {
            background: #eef7ee
        }

        .notification-item+.notification-item {
            border-top: 1px solid #eef2f1
        }

        .notification-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: #1b5e20
        }

        .notification-link {
            display: flex;
            text-decoration: none;
            color: inherit
        }

        .notification-title {
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 6px
        }

        .notification-time {
            color: #6b7280;
            font-size: .9rem;
            margin-top: 8px
        }

        .notification-message {
            color: #234
        }

        .main-content {
            padding: 10px 16px 24px;
            max-width: 1200px;
            margin: 0 auto
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap
        }

        .title-wrap h1 {
            margin: 0;
            color: #2b6625
        }

        .filters {
            display: flex;
            gap: .75rem;
            align-items: flex-end;
            flex-wrap: wrap
        }

        .filter-row {
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .input {
            padding: 10px 12px;
            min-width: 12rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #111827
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #111827;
            color: #fff;
            cursor: pointer;
            text-decoration: none
        }

        .btn.ghost {
            background: #fff;
            color: #111827
        }

        .btn.small {
            padding: 7px 10px;
            font-size: .92rem
        }

        .btn.success {
            background: #065f46;
            border-color: #065f46
        }

        .btn.danger {
            background: #991b1b;
            border-color: #991b1b
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden
        }

        .card-header,
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1rem;
            border-bottom: 1px solid #f3f4f6
        }

        .card-footer {
            border-top: 1px solid #f3f4f6;
            border-bottom: none
        }

        .table {
            width: 100%;
            border-collapse: collapse
        }

        .table th,
        .table td {
            padding: .75rem;
            border-bottom: 1px solid #f3f4f6;
            text-align: left;
            background: #fff
        }

        .table thead th {
            font-weight: 600;
            color: #374151;
            background: #fafafa
        }

        .pill {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: .85rem;
            white-space: nowrap
        }

        .pill.neutral {
            background: #f3f4f6;
            color: #374151
        }

        /* Status plain text (no animation) */
        .status-val {
            display: inline-block;
            font-weight: 600;
            color: #111827;
            background: transparent;
            padding: 0;
            border-radius: 0;
            line-height: 1.2;
            white-space: nowrap;
            min-width: 100px;
            animation: none !important;
            transform: none !important
        }

        .status-val.approved {
            color: #065f46
        }

        .status-val.pending {
            color: #9a3412
        }

        .status-val.rejected {
            color: #991b1b
        }

        .badge.status {
            animation: none !important;
            transform: none !important;
            background: transparent !important;
            color: inherit !important;
            padding: 0 !important;
            min-width: 0 !important;
            border-radius: 0 !important
        }

        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 120px
        }

        /* Modal / Drawer / Skeleton (same as before) */
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050
        }

        .modal.show {
            display: flex
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .55)
        }

        .modal-panel {
            position: relative;
            z-index: 1;
            background: #fff;
            width: min(1200px, 96vw);
            max-height: 92vh;
            border-radius: 16px;
            overflow: auto;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .icon-btn {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer
        }

        .meta-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 10px 16px;
            border-bottom: 1px solid #f3f4f6;
            background: #fafafa
        }

        .meta .label {
            display: block;
            font-size: .75rem;
            color: #6b7280
        }

        .meta .value {
            font-weight: 600
        }

        .status-text {
            font-weight: 700;
            color: #111827
        }

        .modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 420px
        }

        .pane {
            display: flex;
            flex-direction: column;
            min-height: 0
        }

        .pane.left {
            border-right: 1px solid #f3f4f6
        }

        .pane-title {
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6
        }

        .scroll-area {
            padding: 12px 16px;
            overflow: auto;
            max-height: calc(90vh - 210px)
        }

        .deflist {
            margin: 0
        }

        .defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #f3f4f6
        }

        .defrow dt {
            color: #6b7280
        }

        .defrow dd {
            margin: 0;
            word-break: break-word
        }

        .file-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer
        }

        .file-item:hover {
            background: #f9fafb
        }

        .file-item .name {
            font-weight: 500
        }

        .file-item .hint {
            margin-left: auto;
            color: #6b7280;
            font-size: .85rem
        }

        .modal-actions {
            display: flex;
            gap: 20px;
            padding: 10px 16px;
            border-top: 1px solid #f3f4f6;
            background: #fff;
            justify-content: center;
            position: sticky;
            bottom: 0;
            z-index: 1
        }

        .preview-drawer {
            position: fixed;
            top: 2%;
            right: 2%;
            width: min(720px, 96vw);
            height: 96vh;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            z-index: 1100;
            display: none;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .preview-drawer.show {
            display: flex
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            flex: 0 0 56px
        }

        .truncate {
            max-width: 75%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .preview-body {
            flex: 1 1 auto;
            min-height: 0;
            height: calc(96vh - 56px);
            overflow: auto
        }

        #previewImageWrap,
        #previewFrameWrap,
        #previewLinkWrap {
            height: 100%
        }

        #previewImage {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain
        }

        #previewFrame {
            width: 100%;
            height: 100%;
            border: 0
        }

        .json-pre {
            white-space: pre-wrap;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            overflow: auto
        }

        .mini-table {
            width: 100%;
            border-collapse: collapse
        }

        .mini-table th,
        .mini-table td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            font-size: .9rem
        }

        .mini-table thead th {
            background: #fafafa;
            color: #374151
        }

        .toast {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .2);
            opacity: 0;
            transform: translateY(-8px);
            pointer-events: none;
            transition: opacity .2s, transform .2s;
            z-index: 1400
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0)
        }

        .toast.success {
            background: #065f46
        }

        .toast.error {
            background: #991b1b
        }

        .blocker {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .65);
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            z-index: 1500
        }

        .blocker.show {
            display: flex
        }

        .lds {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid #d1d5db;
            border-top-color: #2b6625;
            animation: spin 1s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .confirm-wrap {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1300
        }

        .confirm-wrap.show {
            display: flex
        }

        .confirm-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .45)
        }

        .confirm-panel {
            position: relative;
            z-index: 1;
            width: min(520px, 92vw);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .confirm-title {
            margin: 0 0 6px
        }

        .input-textarea {
            width: 100%;
            min-height: 110px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            font: inherit;
            resize: vertical
        }

        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 6px
        }

        @media (max-width:980px) {
            .modal-content {
                grid-template-columns: 1fr
            }

            .pane.left {
                border-right: 0;
                border-bottom: 1px solid #f3f4f6
            }

            .meta-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .defrow {
                grid-template-columns: 1fr
            }
        }

        /* Skeleton */
        .s-wrap.hidden {
            display: none !important
        }

        .s-wrap {
            padding: 12px 16px 16px
        }

        .s-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px
        }

        .s-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            min-height: 420px
        }

        .s-pane {
            border-top: 1px solid #f3f4f6
        }

        .s-pane+.s-pane {
            border-left: 1px solid #f3f4f6
        }

        .s-title {
            height: 18px;
            width: 40%;
            margin: 12px 0 6px 0
        }

        .s-list {
            padding: 12px 16px;
            display: grid;
            gap: 10px;
            max-height: calc(90vh - 210px);
            overflow: auto
        }

        .sk {
            position: relative;
            overflow: hidden;
            border-radius: 6px;
            background: #e5e7eb
        }

        .sk::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .6), rgba(255, 255, 255, 0));
            animation: shimmer 1.1s infinite
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%)
            }
        }

        .sk.sm {
            height: 12px
        }

        .sk.md {
            height: 14px
        }

        .sk.lg {
            height: 16px
        }

        .sk.row {
            height: 14px;
            width: 100%
        }

        .sk.w25 {
            width: 25%
        }

        .sk.w35 {
            width: 35%
        }

        .sk.w45 {
            width: 45%
        }

        .sk.w60 {
            width: 60%
        }

        .sk.w80 {
            width: 80%
        }

        .sk.w100 {
            width: 100%
        }

        .s-defrow {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px
        }

        @media (max-width:980px) {
            .s-meta {
                grid-template-columns: repeat(2, 1fr)
            }

            .s-body {
                grid-template-columns: 1fr
            }

            .s-pane+.s-pane {
                border-left: 0;
                border-top: 1px solid #f3f4f6
            }

            .s-defrow {
                grid-template-columns: 1fr
            }
        }

        .logo::after {
            content: none
        }

        /* --- Active state for dropdown items (Wildlife Permit) --- */
        .dropdown-menu .dropdown-item {
            position: relative;
        }

        .dropdown-menu .dropdown-item.active {
            background: #eef7ee;
            /* soft green */
            font-weight: 700;
            color: #1b5e20;
        }

        .dropdown-menu .dropdown-item.active i {
            color: #1b5e20;
        }

        .dropdown-menu .dropdown-item.active::before {
            content: "";
            position: absolute;
            left: 8px;
            /* little accent bar on the left */
            top: 8px;
            bottom: 8px;
            width: 4px;
            border-radius: 4px;
            background: #1b5e20;
        }

        .status-val.for-payment {
            color: #1d4ed8;
        }

        /* blue */
        .status-val.released {
            color: #065f46;
        }

        .form-image {
            display: block;
            max-width: 320px;
            /* keep it reasonable in the def list */
            max-height: 240px;
            width: auto;
            height: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }


        /* green */
    </style>
</head>

<body>

    <header>
        <div class="logo"><a href="wildhome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i><span>Add Record</span>
                    </a>

                    <!-- Make this ACTIVE on this page -->
                    <a href="wildpermit.php" class="dropdown-item active" aria-current="page">
                        <i class="fas fa-paw"></i><span>Wildlife Permit</span>
                    </a>

                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="wildNotifList">
                        <?php
                        $combined = [];

                        // Permits
                        foreach ($wildNotifs as $nf) {
                            $combined[] = [
                                'id'      => $nf['notif_id'],
                                'is_read' => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'type'    => 'permit',
                                'message' => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' requested a wildlife permit.')),
                                'ago'     => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'    => !empty($nf['approval_id']) ? 'wildeach.php?id=' . urlencode((string)$nf['approval_id']) : 'wildnotification.php'
                            ];
                        }

                        // Incidents
                        foreach ($incRows as $ir) {
                            $combined[] = [
                                'id'      => $ir['incident_id'],
                                'is_read' => ($ir['is_read'] === true || $ir['is_read'] === 't' || $ir['is_read'] === 1 || $ir['is_read'] === '1'),
                                'type'    => 'incident',
                                'message' => trim((string)$ir['body_text']),
                                'ago'     => time_elapsed_string($ir['created_at'] ?? date('c')),
                                'link'    => 'reportaccident.php?focus=' . urlencode((string)$ir['incident_id'])
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $title = $item['type'] === 'permit' ? 'Permit request' : 'Incident report';
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= $item['type'] === 'permit' ? h($item['id']) : '' ?>"
                                    data-incident-id="<?= $item['type'] === 'incident' ? h($item['id']) : '' ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="wildnotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon <?php echo $current_page === 'forestry-profile' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas a-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <section class="page-header">
            <div class="title-wrap">
                <h1>Wildlife Approvals</h1>
                <p class="subtitle">All requests of type <strong>wildlife</strong></p>
            </div>
            <div class="filters">
                <div class="filter-row">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" class="input">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-row">
                    <label for="searchName">Search Client</label>
                    <input id="searchName" class="input" type="text" placeholder="First name…">
                </div>
                <button id="btnClearFilters" class="btn ghost" type="button"><i class="fas fa-eraser"></i> Clear</button>
            </div>
        </section>

        <main class="card">
            <div class="card-header">
                <h2>Requests</h2>
                <div class="right-actions"><span id="rowsCount" class="muted"><?= count($rows) ?> results</span></div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client First Name</th>
                            <th>Request Type</th>
                            <th>Permit Type</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="statusTableBody">
                        <?php foreach ($rows as $r):
                            $st = strtolower((string)($r['approval_status'] ?? 'pending'));
                            $cls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending'); ?>
                            <tr data-approval-id="<?= h($r['approval_id']) ?>">
                                <td><?= h($r['first_name'] ?? '—') ?></td>
                                <td><span class="pill">wildlife</span></td>
                                <td><span class="pill neutral"><?= h(strtolower((string)$r['permit_type'])) ?></span></td>
                                <td><span class="status-val <?= $cls ?>"><?= ucfirst($st) ?></span></td>
                                <td><?= h($r['submitted_at'] ? date('Y-m-d H:i', strtotime((string)$r['submitted_at'])) : '—') ?></td>
                                <td><button class="btn small" data-action="view"><i class="fas fa-eye"></i> View</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!$rows): ?>
                    <div class="empty"><i class="far fa-folder-open"></i>
                        <p>No wildlife requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-panel" role="document">
            <div class="modal-header">
                <h3 id="modalTitle">Request Details</h3>
                <button class="icon-btn" type="button" aria-label="Close" data-close-modal><i class="fas fa-times"></i></button>
            </div>

            <!-- Skeleton while loading -->
            <div id="modalSkeleton" class="s-wrap hidden" aria-hidden="true">
                <div class="s-meta">
                    <div class="sk sm w60"></div>
                    <div class="sk sm w45"></div>
                    <div class="sk sm w35"></div>
                    <div class="sk sm w25"></div>
                </div>
                <div class="s-body">
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="s-defrow">
                                <div class="sk sm w60"></div>
                                <div class="sk sm w80"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w35"></div>
                                <div class="sk sm w100"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w45"></div>
                                <div class="sk sm w80"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w25"></div>
                                <div class="sk sm w60"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w45"></div>
                                <div class="sk sm w100"></div>
                            </div>
                            <div class="s-defrow">
                                <div class="sk sm w35"></div>
                                <div class="sk sm w80"></div>
                            </div>
                        </div>
                    </div>
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real content -->
            <div class="meta-strip" id="metaStrip">
                <div class="meta"><span class="label">Client</span><span id="metaClientName" class="value">—</span></div>
                <div class="meta"><span class="label">Request Type</span><span id="metaRequestType" class="pill">—</span></div>
                <div class="meta"><span class="label">Permit Type</span><span id="metaPermitType" class="pill neutral">—</span></div>
                <div class="meta"><span class="label">Status</span><span id="metaStatus" class="status-text">—</span></div>
            </div>

            <div class="modal-content" id="modalContent">
                <section class="pane left">
                    <h4 class="pane-title"><i class="fas fa-list"></i> Application Form</h4>
                    <div id="formScroll" class="scroll-area">
                        <dl id="applicationFields" class="deflist"></dl>
                        <div id="formEmpty" class="hidden" style="text-align:center;color:#6b7280;">No form data.</div>
                    </div>
                </section>

                <section class="pane right">
                    <h4 class="pane-title"><i class="fas fa-paperclip"></i> Documents</h4>
                    <div id="filesScroll" class="scroll-area">
                        <ul id="filesList" class="file-list"></ul>
                        <div id="filesEmpty" class="hidden" style="text-align:center;color:#6b7280;">No documents uploaded.</div>
                    </div>
                </section>
            </div>

            <div class="modal-actions" id="modalActions"></div>
        </div>

        <!-- Preview Drawer -->
        <div id="filePreviewDrawer" class="preview-drawer" aria-live="polite" aria-hidden="true">
            <div class="preview-header">
                <span id="previewTitle" class="truncate">Document</span>
                <button class="icon-btn" type="button" aria-label="Close" data-close-preview><i class="fas fa-times"></i></button>
            </div>
            <div class="preview-body">
                <div id="previewImageWrap" class="hidden"><img id="previewImage" alt="Preview"></div>
                <div id="previewFrameWrap" class="hidden"><iframe id="previewFrame" title="Document preview" loading="lazy"></iframe></div>
                <div id="previewLinkWrap" class="hidden" style="padding:16px;text-align:center">
                    <p class="muted">Preview not available. Open or download the file instead.</p>
                    <a id="previewDownload" class="btn" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open / Download</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve/Reject confirms + toast/blocker -->
    <div id="approveConfirm" class="confirm-wrap" role="dialog" aria-modal="true">
        <div class="confirm-backdrop" data-close-confirm></div>
        <div class="confirm-panel">
            <h4 class="confirm-title">Approve this wildlife request?</h4>
            <p>This action will mark the request as <strong>Approved</strong> and notify the client.</p>
            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn success" id="approveConfirmBtn"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </div>
    </div>

    <div id="rejectConfirm" class="confirm-wrap" role="dialog" aria-modal="true">
        <div class="confirm-backdrop" data-close-confirm></div>
        <div class="confirm-panel">
            <h4 class="confirm-title">Reject this wildlife request?</h4>
            <label for="rejectReason" style="font-size:.9rem;color:#374151;">Reason for rejection</label>
            <textarea id="rejectReason" class="input-textarea" placeholder="Provide a short reason…" spellcheck="false"></textarea>
            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn danger" id="rejectConfirmBtn"><i class="fas fa-times"></i> Confirm</button>
            </div>
        </div>
    </div>
    <div id="releaseConfirm" class="confirm-wrap" role="dialog" aria-modal="true">
        <div class="confirm-backdrop" data-close-confirm></div>
        <div class="confirm-panel">
            <h4 class="confirm-title">Release this wildlife permit?</h4>
            <p>This action will mark the request as <strong>Released</strong>, generate the PDF, and notify the client.</p>

            <!-- inputs are injected here by JS (WFP No., Series, Meeting Date) -->

            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn success" id="releaseConfirmBtn"><i class="fas fa-file-export"></i> Confirm</button>
            </div>
        </div>
    </div>


    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <div id="screenBlocker" class="blocker">
        <div class="lds"></div><span>Updating…</span>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => navContainer.classList.toggle('active'));

            /* Dropdowns */
            const dropdowns = document.querySelectorAll('[data-dropdown]');
            const isTouch = matchMedia('(pointer: coarse)').matches;
            dropdowns.forEach(dd => {
                const trigger = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');
                if (!trigger || !menu) return;

                const open = () => {
                    dd.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                };
                const close = () => {
                    dd.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    if (isTouch) menu.style.display = 'none';
                };

                if (!isTouch) {
                    dd.addEventListener('mouseenter', open);
                    dd.addEventListener('mouseleave', (e) => {
                        if (!dd.contains(e.relatedTarget)) close();
                    });
                } else {
                    trigger.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const openNow = dd.classList.contains('open');
                        document.querySelectorAll('[data-dropdown].open').forEach(o => {
                            if (o !== dd) o.classList.remove('open');
                        });
                        if (openNow) close();
                        else {
                            menu.style.display = 'block';
                            open();
                        }
                    });
                }
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-dropdown]')) {
                    document.querySelectorAll('[data-dropdown].open').forEach(dd => {
                        const menu = dd.querySelector('.dropdown-menu');
                        dd.classList.remove('open');
                        if (menu) {
                            menu.style.opacity = '0';
                            menu.style.visibility = 'hidden';
                            menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                            if (matchMedia('(pointer: coarse)').matches) menu.style.display = 'none';
                        }
                    });
                }
            });

            /* MARK ALL AS READ */
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();

                // Optimistic UI
                document.querySelectorAll('#wildNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }

                try {
                    const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                } catch (_) {
                    location.reload();
                }
            });

            /* Single notification → mark read */
            document.getElementById('wildNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                const item = link.closest('.notification-item');
                if (!item) return;
                e.preventDefault();
                const href = link.getAttribute('href') || 'wildnotification.php';
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);
                    await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {}

                item.classList.remove('unread');
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    const n = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, n - 1);
                    badge.textContent = String(next);
                    if (next <= 0) badge.style.display = 'none';
                }
                window.location.href = href;
            });

            /* Toast + blocker helpers */
            function showToast(msg, type = 'success') {
                const t = document.getElementById('toast');
                t.textContent = msg;
                t.className = 'toast show ' + (type === 'error' ? 'error' : 'success');
                setTimeout(() => {
                    t.className = 'toast';
                    t.textContent = '';
                }, 2000);
            }
            const blocker = document.getElementById('screenBlocker');
            const block = (on) => blocker.classList.toggle('show', !!on);

            /* Modal + Skeleton helpers */
            const modalEl = document.getElementById('viewModal');
            const modalSkeleton = document.getElementById('modalSkeleton');
            const modalContent = document.getElementById('modalContent');
            const metaStrip = document.getElementById('metaStrip');
            const modalActions = document.getElementById('modalActions');

            function showModalSkeleton() {
                modalSkeleton.classList.remove('hidden');
                modalContent.classList.add('hidden');
                metaStrip.classList.add('hidden');
                modalActions.classList.add('hidden');
            }

            function hideModalSkeleton() {
                modalSkeleton.classList.add('hidden');
                metaStrip.classList.remove('hidden');
                modalContent.classList.remove('hidden');
            }

            function openViewModal() {
                modalEl.classList.add('show');
            }

            function closeViewModal() {
                modalEl.classList.remove('show');
                closePreview();
            }
            document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', closeViewModal));
            document.querySelector('.modal-backdrop')?.addEventListener('click', closeViewModal);

            /* File preview */
            function closePreview() {
                const dr = document.getElementById('filePreviewDrawer');
                dr.classList.remove('show');
                document.getElementById('previewImage').src = '';
                document.getElementById('previewFrame').src = '';
            }

            function showPreview(name, url, ext) {
                const drawer = document.getElementById('filePreviewDrawer');
                const imgWrap = document.getElementById('previewImageWrap');
                const frameWrap = document.getElementById('previewFrameWrap');
                const linkWrap = document.getElementById('previewLinkWrap');
                document.getElementById('previewTitle').textContent = name;
                imgWrap.classList.add('hidden');
                frameWrap.classList.add('hidden');
                linkWrap.classList.add('hidden');
                const imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const offExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const txtExt = ['txt', 'csv', 'json', 'md', 'log'];
                if (imgExt.includes(ext) && url) {
                    document.getElementById('previewImage').src = url;
                    imgWrap.classList.remove('hidden');
                } else if (ext === 'pdf' && url) {
                    document.getElementById('previewFrame').src = url;
                    frameWrap.classList.remove('hidden');
                } else if (offExt.includes(ext) && url) {
                    const viewer = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
                    document.getElementById('previewFrame').src = viewer;
                    frameWrap.classList.remove('hidden');
                } else if (txtExt.includes(ext) && url) {
                    const gview = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    document.getElementById('previewFrame').src = gview;
                    frameWrap.classList.remove('hidden');
                } else {
                    const a = document.getElementById('previewDownload');
                    a.href = url || '#';
                    linkWrap.classList.remove('hidden');
                }
                drawer.classList.add('show');
            }
            document.getElementById('filesList')?.addEventListener('click', (e) => {
                const li = e.target.closest('.file-item');
                if (!li) return;
                showPreview(li.dataset.fileName || 'Document', li.dataset.fileUrl || '#', (li.dataset.fileExt || '').toLowerCase());
            });
            document.querySelector('[data-close-preview]')?.addEventListener('click', closePreview);

            /* View button -> open modal + fetch details */
            let currentApprovalId = null;
            document.getElementById('statusTableBody')?.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action="view"]');
                if (!btn) return;
                const tr = btn.closest('tr');
                currentApprovalId = tr?.dataset.approvalId;
                if (!currentApprovalId) return;

                showModalSkeleton();

                // Reset placeholders
                document.getElementById('metaClientName').textContent = '—';
                document.getElementById('metaRequestType').textContent = '—';
                document.getElementById('metaPermitType').textContent = '—';
                document.getElementById('metaStatus').textContent = '—';
                document.getElementById('applicationFields').innerHTML = '';
                document.getElementById('formEmpty').classList.add('hidden');
                document.getElementById('filesList').innerHTML = '';
                document.getElementById('filesEmpty').classList.add('hidden');
                modalActions.innerHTML = '';

                openViewModal();

                // Fire-and-forget: mark related notifs read
                fetch('<?php echo basename(__FILE__); ?>?ajax=mark_notifs_for_approval', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        approval_id: currentApprovalId
                    }).toString()
                }).catch(() => {});

                // Fetch details
                const res = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(currentApprovalId)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(r => r.json()).catch(() => ({
                    ok: false
                }));

                if (!res.ok) {
                    hideModalSkeleton();
                    document.getElementById('metaStatus').textContent = 'Error';
                    document.getElementById('applicationFields').innerHTML = '';
                    document.getElementById('formEmpty').classList.remove('hidden');
                    document.getElementById('filesList').innerHTML = '';
                    document.getElementById('filesEmpty').classList.remove('hidden');
                    alert('Failed to load details.');
                    return;
                }

                // Meta
                const meta = res.meta || {};
                document.getElementById('metaClientName').textContent = meta.client || '—';
                document.getElementById('metaRequestType').textContent = meta.request_type || '—';
                document.getElementById('metaPermitType').textContent = meta.permit_type || '—';
                const st = (meta.status || '').trim().toLowerCase();
                const ms = document.getElementById('metaStatus');
                ms.textContent = st ? st[0].toUpperCase() + st.slice(1) : '—';
                ms.className = 'status-text';

                // Application fields
                const list = document.getElementById('applicationFields');
                const empty = document.getElementById('formEmpty');
                list.innerHTML = '';

                function isPlainObject(v) {
                    return v && typeof v === 'object' && !Array.isArray(v);
                }

                function renderJSON(val) {
                    let data = val;
                    if (typeof data === 'string') {
                        try {
                            data = JSON.parse(data);
                        } catch {}
                    }
                    if (Array.isArray(data) && data.length && data.every(isPlainObject)) {
                        const keys = [...new Set(data.flatMap(o => Object.keys(o)))];
                        const table = document.createElement('table');
                        table.className = 'mini-table';
                        const thead = document.createElement('thead');
                        const trh = document.createElement('tr');
                        keys.forEach(k => {
                            const th = document.createElement('th');
                            th.textContent = k;
                            trh.appendChild(th);
                        });
                        thead.appendChild(trh);
                        table.appendChild(thead);
                        const tb = document.createElement('tbody');
                        data.forEach(o => {
                            const tr = document.createElement('tr');
                            keys.forEach(k => {
                                const td = document.createElement('td');
                                td.textContent = o[k] ?? '';
                                tr.appendChild(td);
                            });
                            tb.appendChild(tr);
                        });
                        table.appendChild(tb);
                        return table;
                    }
                    const pre = document.createElement('pre');
                    pre.className = 'json-pre';
                    try {
                        pre.textContent = JSON.stringify(data, null, 2);
                    } catch {
                        pre.textContent = String(val);
                    }
                    return pre;
                }

                if (Array.isArray(res.application) && res.application.length) {
                    empty.classList.add('hidden');
                    res.application.forEach(item => {
                        const {
                            label,
                            value,
                            kind
                        } = item;
                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        dt.textContent = label;
                        const dd = document.createElement('dd');
                        if (kind === 'image') {
                            const img = document.createElement('img');
                            img.src = value;
                            img.alt = label || 'Signature';
                            img.className = 'form-image';
                            dd.appendChild(img);
                        } else if (kind === 'link') {
                            const a = document.createElement('a');
                            a.href = value;
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.textContent = value;
                            dd.appendChild(a);
                        } else if (kind === 'json') {
                            dd.appendChild(renderJSON(value));
                        } else {
                            dd.textContent = value;
                        }

                        row.appendChild(dt);
                        row.appendChild(dd);
                        list.appendChild(row);
                    });
                } else {
                    empty.classList.remove('hidden');
                }

                // Files
                const filesList = document.getElementById('filesList');
                const filesEmpty = document.getElementById('filesEmpty');
                filesList.innerHTML = '';
                if (Array.isArray(res.files) && res.files.length) {
                    filesEmpty.classList.add('hidden');
                    res.files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.tabIndex = 0;
                        li.dataset.fileUrl = f.url || '';
                        li.dataset.fileName = f.name || 'Document';
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="far fa-file"></i><span class="name">${f.name}</span><span class="hint">${(f.ext||'').toUpperCase()}</span>`;
                        filesList.appendChild(li);
                    });
                } else {
                    filesEmpty.classList.remove('hidden');
                }

                // Actions based on status
                renderActionsForStatus(st);
                modalActions.classList.toggle('hidden', !(st === 'pending' || st === 'for payment'));

                hideModalSkeleton();
            });

            /* ====== Approve / Release / Reject flow ====== */
            let pendingAction = null;

            // Show inputs on the RELEASE confirm modal
            function ensureReleaseInputs() {
                const panel = document.querySelector('#releaseConfirm .confirm-panel');
                if (!panel || document.getElementById('relFieldWfpNo')) return;

                const block = document.createElement('div');
                block.style.display = 'grid';
                block.style.gridTemplateColumns = '1fr 1fr';
                block.style.gap = '10px';
                block.style.marginTop = '8px';

                const thisYear = new Date().getFullYear();
                const todayISO = new Date().toISOString().slice(0, 10);

                block.innerHTML = `
            <div style="grid-column:1/-1;">
                <label for="relFieldWfpNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">WFP No.</label>
                <input id="relFieldWfpNo" class="input" type="text" placeholder="e.g., WFP-${thisYear}-00001" />
            </div>
            <div>
                <label for="relFieldSeries" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Series</label>
                <input id="relFieldSeries" class="input" type="text" value="${thisYear}" />
            </div>
            <div>
                <label for="relFieldMeetingDate" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Meeting Date</label>
                <input id="relFieldMeetingDate" class="input" type="date" value="${todayISO}" />
            </div>
        `;
                panel.insertBefore(block, panel.querySelector('.confirm-actions'));
            }

            function openConfirm(which) {
                pendingAction = which;
                if (which === 'approve') {
                    document.getElementById('approveConfirm')?.classList.add('show');
                } else if (which === 'release') {
                    ensureReleaseInputs();
                    document.getElementById('releaseConfirm')?.classList.add('show');
                } else {
                    document.getElementById('rejectConfirm')?.classList.add('show');
                    const rr = document.getElementById('rejectReason');
                    if (rr) rr.value = '';
                }
            }

            function closeAllConfirms() {
                document.getElementById('approveConfirm')?.classList.remove('show');
                document.getElementById('releaseConfirm')?.classList.remove('show');
                document.getElementById('rejectConfirm')?.classList.remove('show');
            }
            document.querySelectorAll('[data-close-confirm],[data-cancel-confirm]').forEach(el => el.addEventListener('click', closeAllConfirms));

            // Send decisions (approve = for payment, release = with inputs & PDF, reject = needs reason)
            async function sendDecision(action, reason = '') {
                if (!currentApprovalId) return {
                    ok: false,
                    error: 'Missing approval id'
                };

                const form = new URLSearchParams();
                form.set('approval_id', currentApprovalId);
                form.set('action', action);

                if (action === 'reject') {
                    form.set('reason', reason);
                } else if (action === 'release') {
                    const wfpNo = (document.getElementById('relFieldWfpNo')?.value || '').trim();
                    const series = (document.getElementById('relFieldSeries')?.value || '').trim();
                    const meet = (document.getElementById('relFieldMeetingDate')?.value || '').trim();
                    if (!wfpNo || !series || !meet) {
                        return {
                            ok: false,
                            error: 'Please complete WFP No., Series, and Meeting Date.'
                        };
                    }
                    form.set('wfp_no', wfpNo);
                    form.set('series', series);
                    form.set('meeting_date', meet);
                }

                return fetch('<?php echo basename(__FILE__); ?>?ajax=decide', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: form.toString()
                }).then(r => r.json()).catch(() => ({
                    ok: false,
                    error: 'network error'
                }));
            }

            function slugStatus(s) {
                return (s || '').toLowerCase().replace(/\s+/g, '-');
            }

            function renderActionsForStatus(st) {
                modalActions.innerHTML = '';
                if (st === 'pending') {
                    const approveBtn = document.createElement('button');
                    approveBtn.className = 'btn success';
                    approveBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
                    approveBtn.addEventListener('click', () => openConfirm('approve'));

                    const rejectBtn = document.createElement('button');
                    rejectBtn.className = 'btn danger';
                    rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
                    rejectBtn.addEventListener('click', () => openConfirm('reject'));

                    modalActions.appendChild(approveBtn);
                    modalActions.appendChild(rejectBtn);
                    modalActions.classList.remove('hidden');
                } else if (st === 'for payment') {
                    const releaseBtn = document.createElement('button');
                    releaseBtn.className = 'btn success';
                    releaseBtn.innerHTML = '<i class="fas fa-file-export"></i> Release';
                    releaseBtn.addEventListener('click', () => openConfirm('release'));
                    modalActions.appendChild(releaseBtn);
                    modalActions.classList.remove('hidden');
                } else {
                    modalActions.classList.add('hidden');
                }
            }

            function applyDecisionUI(status) {
                const title = status[0].toUpperCase() + status.slice(1);
                const ms = document.getElementById('metaStatus');
                ms.textContent = title;
                ms.className = 'status-text';

                renderActionsForStatus(status);

                const tr = document.querySelector(`tr[data-approval-id="${CSS.escape(currentApprovalId)}"]`);
                if (tr) {
                    const tdStatus = tr.children[3];
                    if (tdStatus) tdStatus.innerHTML = `<span class="status-val ${slugStatus(status)}">${title}</span>`;
                }
            }

            // Approve -> For payment (no inputs)
            document.getElementById('approveConfirmBtn')?.addEventListener('click', async () => {
                if (pendingAction !== 'approve') return;

                block(true);
                const res = await sendDecision('approve');
                block(false);

                if (!res.ok) {
                    alert(res.error || 'Failed to approve');
                    return;
                }
                applyDecisionUI('for payment');
                closeAllConfirms();
                showToast('Requirement approved', 'success');
            });

            // Release -> Released (with inputs & PDF)
            document.getElementById('releaseConfirmBtn')?.addEventListener('click', async () => {
                if (pendingAction !== 'release') return;

                block(true);
                const res = await sendDecision('release');
                block(false);

                if (!res.ok) {
                    alert(res.error || 'Failed to release');
                    return;
                }
                applyDecisionUI('released');
                closeAllConfirms();
                showToast('Request released', 'success');
            });

            // Reject (reason required)
            document.getElementById('rejectConfirmBtn')?.addEventListener('click', async () => {
                if (pendingAction !== 'reject') return;
                const reason = (document.getElementById('rejectReason')?.value || '').trim();
                if (!reason) {
                    alert('Please provide a reason.');
                    return;
                }
                block(true);
                const res = await sendDecision('reject', reason);
                block(false);
                if (!res.ok) {
                    alert(res.error || 'Failed to reject');
                    return;
                }
                applyDecisionUI('rejected');
                closeAllConfirms();
                showToast('Request rejected', 'success');
            });

            /* Filters */
            const filterStatus = document.getElementById('filterStatus');
            const searchName = document.getElementById('searchName');
            const rowsCount = document.getElementById('rowsCount');
            document.getElementById('btnClearFilters')?.addEventListener('click', () => {
                filterStatus.value = '';
                searchName.value = '';
                applyFilters();
            });
            [filterStatus, searchName].forEach(el => el.addEventListener('input', applyFilters));

            function applyFilters() {
                const st = (filterStatus.value || '').toLowerCase();
                const q = (searchName.value || '').trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                    const name = (tr.children[0]?.textContent || '').trim().toLowerCase();
                    const stat = (tr.children[3]?.textContent || '').trim().toLowerCase();
                    let ok = true;
                    if (st && stat !== st) ok = false;
                    if (q && !name.includes(q)) ok = false;
                    tr.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                rowsCount.textContent = `${shown} result${shown===1?'':'s'}`;
            }
        });
    </script>


</body>




</html>