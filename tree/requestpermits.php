<?php

/**
 * treepermit.php (Tree Cutting Admin UI) — TOP (PHP) SECTION
 * - Session/admin guard
 * - Dompdf + Supabase helpers
 * - Chainsaw + Wood PDF HTML builders
 * - generate_and_store_chainsaw() & generate_and_store_wood() save to Supabase Storage
 * - AJAX handlers (mark read / mark-all / mark_notifs_for_approval / details / decide)
 *
 * NOTE on Storage layout (bucket = approved_docs):
 *   chainsaw/new permit/{client_id}/{approved_id}/{filename}.pdf
 *   chainsaw/renewal permit/{client_id}/{approved_id}/{filename}.pdf
 *   wood/new permit/{client_id}/{approved_id}/{filename}.pdf
 *   wood/renewal permit/{client_id}/{approved_id}/{filename}.pdf
 */

declare(strict_types=1);
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php';

/* ------------------------------------------------------------------
   PDF + Storage helpers (Dompdf + Supabase)
------------------------------------------------------------------- */
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$STORAGE_BUCKET = 'approved_docs';

/* Read Supabase config from env or constants (fallbacks) */
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

/** Inline image helper -> data URI (for Dompdf) */
function img_data_uri(string $path): string
{
    if (!is_file($path)) return '';
    $mime = @mime_content_type($path) ?: 'image/png';
    $bin  = @file_get_contents($path);
    if ($bin === false) return '';
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/** Build Chainsaw Certificate HTML (Dompdf-friendly) */
function build_chainsaw_html(array $d): string
{
    $e   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $denr = img_data_uri(__DIR__ . '/denr.png');
    $ph   = img_data_uri(__DIR__ . '/pilipinas.png');

    return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *{box-sizing:border-box;font-family:"Times New Roman",serif;color:#000}
    @page{size:letter;margin:.5in}
    body{margin:0;background:#fff}
    .container{width:100%;min-height:11in}
    .header{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:12px}
    .header td{vertical-align:middle}
    .header .col-left{width:95px}
    .header .col-right{width:120px;text-align:right}
    .header .col-center{text-align:center}
    .logo-left{display:block;width:80px;height:80px;object-fit:contain}
    .logo-right{display:block;width:110px;height:110px;object-fit:contain}
    .denr h1{margin:0 0 4px 0;font-size:17px;line-height:1.2}
    .denr p{margin:0;font-size:13px;line-height:1.1}
    .title{text-align:center;margin:12px 0 6px;font-size:20px;font-weight:700;text-decoration:underline}
    .number{text-align:center;margin-bottom:12px;font-size:15px}
    .underline{display:inline-block;border-bottom:1px solid #000;min-width:240px;padding:0 3px}
    .small{display:inline-block;border-bottom:1px solid #000;min-width:140px;padding:0 3px}
    .body{font-size:14px;line-height:1.5}
    .center{text-align:center;margin:12px 0}
    .info-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:13px}
    .info-table td{padding:4px 8px;vertical-align:top}
    .approval{margin-top:18px}
    .approval h2{font-size:16px;margin:0 0 6px 0;text-decoration:underline}
    .approval .name{font-weight:700;font-size:15px;margin:10px 0 2px}
    .approval .pos{font-size:13px}
    .fee{margin-top:14px;font-size:13px}
  </style>
</head>
<body>
  <div class="container" id="certificate">

    <!-- Header -->
    <table class="header">
      <tr>
        <td class="col-left">
          <img class="logo-left" src="' . $e($denr) . '" alt="DENR">
        </td>
        <td class="col-center">
          <div class="denr">
            <h1>Department of Environment and Natural Resources</h1>
            <p>Region 7</p>
            <p>Province of Cebu</p>
            <p>Municipality of Argao</p>
          </div>
        </td>
        <td class="col-right">
          <img class="logo-right" src="' . $e($ph) . '" alt="Pilipinas">
        </td>
      </tr>
    </table>

    <div class="title">CERTIFICATE OF REGISTRATION</div>
    ' . (!empty($d['permit_type'])
        ? '<p style="text-align:center;margin:4px 0 8px;">Type of Permit: <span class="underline">' . $e(strtoupper((string)$d['permit_type'])) . '</span></p>'
        : ''
    ) . '
    <div class="number">NO. <span class="underline">' . $e($d["no"]) . '</span></div>

    <div class="body">
      <p>After having complied with the provisions of DENR Administrative Order No. 2003-29, series of 2003 otherwise known as the "Implementing Guidelines of Chainsaw Act of 2002 (R.A. No 9175) entitled <strong>ACT REGULATING THE POSSESSION, OWNERSHIP, SALE, IMPORTATION AND USE OF CHAINSAWS PENALIZING VIOLATIONS THEREOF AND FOR OTHER RELATED PURPOSES</strong>, this Certificate of Registration to possess, own or use a chainsaw is hereby issued to:</p>

      <div class="center">
        <p><span class="underline">' . $e($d["client_name"]) . '</span> (Name)</p>
        <p><span class="underline">' . $e($d["client_address"]) . '</span> (Address)</p>
      </div>

      <p style="margin:12px 0;">bearing the following information and descriptions:</p>

      <table class="info-table">
        <tr>
          <td>Use of the Chainsaw:</td>
          <td><span class="underline">' . $e($d["purpose_of_use"]) . '</span></td>
        </tr>
        <tr>
          <td>Brand: <span class="small">' . $e($d["brand"]) . '</span></td>
          <td>Model: <span class="small">' . $e($d["model"]) . '</span></td>
        </tr>
        <tr>
          <td>Date of Acquisition: <span class="small">' . $e($d["date_of_acquisition_fmt"]) . '</span></td>
          <td>Serial No.: <span class="small">' . $e($d["serial_no"]) . '</span></td>
        </tr>
        <tr>
          <td>Horsepower: <span class="small">' . $e($d["horsepower"]) . '</span></td>
          <td></td>
        </tr>
        <tr>
          <td>Maximum Length of Guide bar: <span class="small">' . $e($d["max_guide_bar"]) . '</span></td>
          <td></td>
        </tr>
        <tr>
          <td>Issued on: <span class="small">' . $e($d["issued_on_fmt"]) . '</span></td>
          <td>at: <span class="small">' . $e($d["issued_at"]) . '</span></td>
        </tr>
        <tr>
          <td>Expiry Date: <span class="small">' . $e($d["expiry_on_fmt"]) . '</span></td>
          <td></td>
        </tr>
      </table>

      <p><strong>An authenticated copy of this Certificate must accompany the Chainsaw at all times.</strong></p>
    </div>

    <div class="approval">
      <h2>APPROVED:</h2>
      <div class="name">VICENTE RUSTICOM. CALIZAR, RPF</div>
      <div class="pos">CENR Officer</div>

      <div class="fee">
        <p>Registration Fee: P500.00</p>
        <p>O.R. No. <span class="small">' . $e($d["orno"]) . '</span></p>
        <p>Date Issued: <span class="small">' . $e($d["or_date_fmt"]) . '</span></p>
      </div>
    </div>
  </div>
</body>
</html>';
}


function build_lumber_html(array $d): string
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $denr = img_data_uri(__DIR__ . '/denr.png');
    $ph   = img_data_uri(__DIR__ . '/pilipinas.png');

    // supplier rows: [['name/company','volume'],...]
    $supRows = $d['suppliers'] ?? [];
    while (count($supRows) < 3) $supRows[] = ['', '']; // keep table height

    $today      = $e($d['date_today_fmt'] ?? '');
    $expiry     = $e($d['expiry_on_fmt'] ?? '');
    $orNo       = $e($d['or_no'] ?? '');
    $perf       = $e($d['perf_bond_amt_fmt'] ?? ''); // already formatted "₱ 1,000.00"
    $issuedAt   = $e($d['issued_at'] ?? '');
    $regNo      = $e($d['registration_no'] ?? '');
    $clientName = $e($d['client_name'] ?? '');
    $clientAddr = $e($d['client_address'] ?? '');

    // optional business name (fallback to client)
    $bizName  = $e($d['business_name'] ?: ($d['client_name'] ?? ''));
    // optional place of business (safe fallback)
    $bizPlace = $e($d['business_place'] ?? '');

    // permit type label shown to the right of Registration No.
    $permitTypeRaw = $d['permit_type_label'] ?? ($d['permit_type'] ?? 'New Permit');
    $permitType    = $e($permitTypeRaw);

    return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Lumber Dealer Certificate</title>
  <style>
    /* ===== Base / Chainsaw header ===== */
    *{box-sizing:border-box;font-family:"Times New Roman",serif;color:#000}
    @page{ margin:.6in .75in; }
    body{margin:0;background:#fff}
    .container{width:100%;padding:0 .20in}

    .header{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:12px; border-bottom:4px solid #f00;}
    .header td{vertical-align:middle}
    .header .col-left{width:95px}
    .header .col-right{width:120px;text-align:right}
    .header .col-center{text-align:center}
    .logo-left{display:block;width:80px;height:80px;object-fit:contain}
    .logo-right{display:block;width:110px;height:110px;object-fit:contain}
    .denr h1{margin:0 0 4px 0;font-size:17px;line-height:1.2}
    .denr p{margin:0;font-size:13px;line-height:1.1}

    /* ===== Lumber-specific ===== */
    .title{text-align:center;margin:12px 0 6px;font-size:20px;font-weight:700;text-decoration:underline}
    /* Turn the Registration line into a centered flex row */
    .number{display:flex;justify-content:center;align-items:baseline;gap:10px;margin-bottom:12px;font-size:15px}
    .underline{display:inline-block;border-bottom:1px solid #000;min-width:240px;padding:0 3px}
    /* Short underline only for the Registration No. */
    .u-short{min-width:140px}
    .ptype{display:inline-block;font-weight:700;text-transform:uppercase;white-space:nowrap}
    .small{display:inline-block;border-bottom:1px solid #000;min-width:140px;padding:0 3px}
    .body{font-size:14px;line-height:1.5}
    .center{text-align:center;margin:12px 0}

    .info-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:13px;border:1px solid #000}
    .info-table th,.info-table td{border:1px solid #000;padding:2px 4px;vertical-align:top}
    .info-table th{text-align:center;font-weight:700}

    .fees{margin-top:12px}
    .row{display:flex;align-items:flex-start;margin-bottom:3px;font-size:11px}
    .col4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-top:8px;font-size:11px}
    .sig{display:flex;align-items:center;justify-content:center;padding-top:12px;min-height:70px}
    .appr-name{font-weight:700;font-size:12px;margin:8px 0 2px}
    .appr-pos{font-size:10px;line-height:1.1;text-align:center}
  </style>
</head>
<body>
  <div class="container" id="permit">

    <!-- Chainsaw-style Header -->
    <table class="header">
      <tr>
        <td class="col-left">
          <img class="logo-left" src="' . $e($denr) . '" alt="DENR">
        </td>
        <td class="col-center">
          <div class="denr">
            <h1>Department of Environment and Natural Resources</h1>
            <p>Region 7</p>
            <p>Province of Cebu</p>
            <p>Municipality of Argao</p>
          </div>
        </td>
        <td class="col-right">
          <img class="logo-right" src="' . $e($ph) . '" alt="Pilipinas">
        </td>
      </tr>
    </table>

    <div class="title">CERTIFICATE OF REGISTRATION</div>
    <div class="number">
      
      <div style="display: flex; text-align: center;width: 100%;"><span>Registration No.</span><span class="underline u-short">' . $regNo . ' </span><span>' . $permitType . '</span></div>
      
    </div>

    <div class="body">
      <p class="center">This is to certify that,</p>

      <div class="center" style="margin:8px 0;">
        <p><strong>CEBU LUMBER TRADING,</strong></p>
        <p><span class="underline">' . $bizName . '</span> (Business Name)</p>
        <p><span class="underline">' . $clientName . '</span>, a Filipino Citizen of <span class="underline">' . $clientAddr . '</span> which has been registered</p>
        <p>(Proprietor) (Address)</p>
        <p>in this Office as</p>
        <p><strong>LUMBER DEALER.</strong></p>
      </div>

      <p style="margin:8px 0;">
        Pursuant to the pertinent provision of P.D. No. 705, as amended, in accordance with the provision of Republic Act 1239, and the Regulation promulgated thereto, and subject to the Terms and Condition enumerated in the succeeding pages (marked as Annex A), and such other additional regulation which may hereinafter be prescribed. The registrant has lumber supply contract (e) with the following:
      </p>

      <table class="info-table">
        <tr>
          <th>SUPPLIERS NAME/COMPANY</th>
          <th>VOLUME</th>
        </tr>' .
        implode("", array_map(function ($r) {
            $c0 = htmlspecialchars($r[0] ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $c1 = htmlspecialchars($r[1] ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            return "<tr><td>{$c0}</td><td>{$c1}</td></tr>";
        }, $supRows)) . '
      </table>

      <p style="margin:8px 0;">
        The Place of its/his/her business operation is in <span class="underline">' . $bizPlace . '</span>, Cebu.
        This Certificate of Registration is non-negotiable and non-transferable and, unless sooner terminated, will expire on <span class="underline">' . $expiry . '</span>
        issued <span class="underline">' . $today . '</span> at <span class="underline">' . $issuedAt . '</span>.
      </p>

      <div class="fees">
        <div class="row"><span>Performance Bond No. ' . $perf . ' O.R. No. ' . $orNo . '</span></div>
        <div class="row">Date: <span class="small">' . $today . '</span></div>

        <div class="col4">
          <div>
            <div class="row">Application Fee: ₱600.00</div>
            <div class="row">O.R. <span class="small">' . $orNo . '</span></div>
            <div class="row">Date: <span class="small">' . $today . '</span></div>
          </div>
          <div>
            <div class="row">Registration Fee: ₱480.00</div>
            <div class="row">O.R. No. <span class="small">' . $orNo . '</span></div>
            <div class="row">Date: <span class="small">' . $today . '</span></div>
          </div>
          <div>
            <div class="row">Paid ₱1,000.00 under O.R. No. <span class="small">' . $orNo . '</span></div>
            <div class="row">dated <span class="small">' . $today . '</span> as</div>
            <div class="row">Administrative Penalty for violation of R.A. 1239.</div>
          </div>
          <div class="sig">
            <div>
              <div class="appr-name">PAQUITO D MELICOR, JR., CESO IV</div>
              <div class="appr-pos">Regional Executive Director</div>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /.body -->
  </div><!-- /.container -->
</body>
</html>';
}
/** Build Tree Cutting Permit HTML (reusing Wood header + CSS) */
function build_treecut_html(array $d): string
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $denr = img_data_uri(__DIR__ . '/denr.png');
    $ph   = img_data_uri(__DIR__ . '/pilipinas.png');

    // table rows for species
    $rows = $d['species_rows'] ?? [];
    $rowsHtml = '';
    $totalCount = 0;
    $totalVol   = 0.0;
    foreach ($rows as $r) {
        $name   = $e($r['name']   ?? '');
        $count  = (int)($r['count'] ?? 0);
        $volume = (float)($r['volume'] ?? 0);
        $totalCount += $count;
        $totalVol   += $volume;
        $rowsHtml  .= '<tr><td>' . $name . '</td><td>' . $count . '</td><td>' . number_format($volume, 2) . '</td></tr>';
    }
    $rowsHtml .= '<tr><td><strong>TOTAL</strong></td><td><strong>' . $totalCount . '</strong></td><td><strong>' . number_format($totalVol, 2) . '</strong></td></tr>';

    $clientName   = $e($d['client_name'] ?? '');
    $permAddress  = $e($d['permanent_address'] ?? '');
    $tcpNo        = $e($d['tcp_no'] ?? '');
    $orNo         = $e($d['or_no'] ?? '');
    $issuedOn     = $e($d['issued_on_fmt'] ?? '');
    $grossTotal   = $e($d['total_gross_fmt'] ?? '');
    $netHarvest   = $e($d['net_harvest_fmt'] ?? '');

    $taxDecl      = $e($d['tax_declaration'] ?? '');
    $lotNo        = $e($d['lot_no'] ?? '');
    $contained    = $e($d['contained_area'] ?? '');

    return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tree Cutting Permit</title>
  <style>
    /* ====== base (same family as wood) ====== */
    *{box-sizing:border-box;font-family:"Times New Roman",serif;color:#000}
    @page{size:letter;margin:.5in}
    body{margin:0;background:#fff}
    .container{width:100%;min-height:11in}
    .header{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:12px}
    .header td{vertical-align:middle}
    .header .col-left{width:95px}
    .header .col-right{width:120px;text-align:right}
    .header .col-center{text-align:center}
    .logo-left{display:block;width:80px;height:80px;object-fit:contain}
    .logo-right{display:block;width:110px;height:110px;object-fit:contain}
    .denr h1{margin:0 0 4px 0;font-size:17px;line-height:1.2}
    .denr p{margin:0;font-size:13px;line-height:1.1}

    /* ====== wood-style section rule & titles ====== */
    .permit-title-section{border-top:4px solid #f00;padding-top:10px;margin-top:10px}
    .permit-title{text-align:center;margin:10px 0;font-size:18px;font-weight:700;text-decoration:underline}
    .permit-number{text-align:center;margin-bottom:12px;font-size:14px}
    .underline{display:inline-block;border-bottom:1px solid #000;min-width:250px;padding:0 3px}
    .small{display:inline-block;border-bottom:1px solid #000;min-width:120px;padding:0 3px}
    .body{font-size:13px;line-height:1.45;text-align:justify;margin-bottom:10px}
    .center{text-align:center;margin:10px 0}

    /* fees strip (as in your sample) */
    .fees{display:flex;justify-content:space-between;margin:8px 0 6px}
    .fees .col{font-size:13px}
    .fees .col p{margin:2px 0}

    /* table */
    .info-table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12px;border:1px solid #000}
    .info-table th,.info-table td{border:1px solid #000;padding:6px;vertical-align:top}
    .info-table th{text-align:left;font-weight:700}

    /* misc */
    .terms{margin:10px 0 0 18px;font-size:12px}
    .contact{margin-top:16px;text-align:center;font-size:12px}
  </style>
</head>
<body>
  <div class="container" id="permit">

    <!-- Header (same as wood) -->
    <table class="header">
      <tr>
        <td class="col-left"><img class="logo-left" src="' . $e($denr) . '" alt="DENR"></td>
        <td class="col-center">
          <div class="denr">
            <h1>Department of Environment and Natural Resources</h1>
            <p>Region 7</p>
            <p>Province of Cebu</p>
            <p>Municipality of Argao</p>
          </div>
        </td>
        <td class="col-right"><img class="logo-right" src="' . $e($ph) . '" alt="Pilipinas"></td>
      </tr>
    </table>

    <div class="permit-title-section">
      <div class="fees">
        <div class="col">
          <p>Forest Charges: PNONE</p>
          <p>O.R. No.: <span class="small">' . $orNo . '</span></p>
          <p>Date Issued: <span class="small">' . $issuedOn . '</span></p>
        </div>
        <div class="col" style="text-align:left;">
          <p>Inventory fee: ₱ 1,200.00</p>
          <p>Cert, Coath Fee: ₱ 86.00</p>
          <p>O.R. No.: <span class="small">' . $orNo . '</span></p>
          <p>Date Issued: <span class="small">' . $issuedOn . '</span></p>
        </div>
      </div>

      <div class="permit-title">TREE CUTTING PERMIT</div>
      <div class="permit-number">TCP No. <span class="small">' . $tcpNo . '</span></div>
    </div>

    <div class="body">
      <p>
        Pursuant to Presidential Decree No. 705, as amended, DENR Administrative Order No. 2022-10 dated May 30, 2022,
        regarding the "Revised DENR Manual of Authorities on Technical Matters", and to existing Forestry laws, rules,
        and regulations, a Tree Cutting Permit is hereby granted to:
      </p>

      <div class="center" style="margin:8px 0;">
        <p><span class="underline">' . $clientName . '</span> (Name of Applicant/Corporation)</p>
        <p>with permanent address <span class="underline">' . $permAddress . '</span></p>
      </div>

      <p style="margin:8px 0;">
        to cut trees with a total gross volume of <strong>' . $grossTotal . ' cu.m.</strong> and a net harvestable volume of
        <strong>' . $netHarvest . ' cu.m.</strong>
      </p>

      <ol class="terms">
        <li>
          Trees within a private property covered by a <strong>Tax Declaration No. ' . $taxDecl . '</strong> per
          <strong>Lot No. ' . $lotNo . '</strong> containing an area of <strong>' . $contained . '</strong> located in
          <strong>' . $permAddress . '</strong>, having a net harvestable volume of <strong>' . $netHarvest . ' cu.m.</strong>
          after deducting allowances for defects and harvesting, as shown in the Table below.
        </li>
      </ol>

      <table class="info-table">
        <thead>
          <tr>
            <th>Species</th>
            <th>No. of Trees</th>
            <th>Net Volume (cu.m.)</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>

      <ol class="terms" start="2">
        <li>Prior to tree cutting operation, placards or signboards with dimensions of 4 ft by 8 ft shall be installed at conspicuous places indicating the permittee, purpose and number of trees to be cut.</li>
        <li>The tree cutting operation shall at all times be under the direct supervision of the Regional Executive Director or his/her duly authorized representatives.</li>
        <li>The trees cut and parts thereof shall belong to the permittee; transport shall require necessary documents from the Local DENR Field Office concerned.</li>
        <li>The chainsaw used shall be registered pursuant to R.A. 9175 and implementing rules under DAO 2005-24.</li>
      </ol>

      <div class="contact">
        <div>Lamacan, Argao, Cebu, Philippines 6021</div>
        <div>Tel. Nos. (+6332) 4600-711</div>
        <div>E-mail: cenroargao@denr.gov.ph</div>
      </div>
    </div>
  </div>
</body>
</html>';
}


function generate_and_store_treecut(PDO $pdo, string $approvalId, array $inputs): array
{
    global $STORAGE_BUCKET;

    // 1) approval → app/client + permit_type
    $st = $pdo->prepare("
        SELECT a.application_id, a.client_id, LOWER(COALESCE(a.permit_type,'new')) AS permit_type
        FROM public.approval a
        WHERE a.approval_id = :aid
        LIMIT 1
    ");
    $st->execute([':aid' => $approvalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $clientId      = (string)($row['client_id'] ?? '');
    $applicationId = (string)($row['application_id'] ?? '');
    $permitType    = (string)($row['permit_type'] ?? 'new');
    $folderType    = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';

    // 2) application_form
    $app = [];
    if ($applicationId !== '') {
        $s = $pdo->prepare("SELECT * FROM public.application_form WHERE application_id=:id LIMIT 1");
        $s->execute([':id' => $applicationId]);
        $app = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // 3) client
    $client = [];
    if ($clientId !== '') {
        $s = $pdo->prepare("SELECT * FROM public.client WHERE client_id=:cid LIMIT 1");
        $s->execute([':cid' => $clientId]);
        $client = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $clientName = trim(((string)($client['first_name'] ?? '')) . ' ' . ((string)($client['last_name'] ?? '')));

    // 4) parse additional_information → species_rows
    $ai = [];
    if (!empty($app['additional_information'])) {
        $tmp = json_decode((string)$app['additional_information'], true);
        if (is_array($tmp)) $ai = $tmp;
    }
    $speciesRaw = $ai['species_rows'] ?? ($ai['speciesRows'] ?? []);
    if (is_string($speciesRaw)) {
        $dec = json_decode($speciesRaw, true);
        if (is_array($dec)) $speciesRaw = $dec;
    }
    $speciesRows = [];
    if (is_array($speciesRaw)) {
        foreach ($speciesRaw as $r) {
            if (!is_array($r)) continue;
            $speciesRows[] = [
                'name'   => (string)($r['name'] ?? ''),
                'count'  => (int)($r['count'] ?? 0),
                'volume' => (float)($r['volume'] ?? 0),
            ];
        }
    }

    // 5) totals
    $totalGross = 0.0;
    foreach ($speciesRows as $r) $totalGross += (float)$r['volume'];

    // 6) inputs & dates
    $tcpNo      = trim((string)($inputs['tcp_no'] ?? ''));
    $orNo       = trim((string)($inputs['or_no'] ?? ''));
    $netHarvest = trim((string)($inputs['net_harvest'] ?? ''));
    $tz         = new DateTimeZone('Asia/Manila');
    $issuedDT   = new DateTime('now', $tz);
    $issuedFmt  = $issuedDT->format('F j, Y');

    // 7) build data for HTML
    $data = [
        'tcp_no'            => $tcpNo,
        'or_no'             => $orNo,
        'issued_on_fmt'     => $issuedFmt,
        'client_name'       => $clientName,
        'permanent_address' => (string)($app['location_of_area_trees_to_be_cut'] ?? ''),
        'tax_declaration'   => (string)($app['tax_declaration'] ?? ''),
        'lot_no'            => (string)($app['lot_no'] ?? ''),
        'contained_area'    => (string)($app['contained_area'] ?? ''),
        'species_rows'      => $speciesRows,
        'total_gross_fmt'   => number_format($totalGross, 2),
        'net_harvest_fmt'   => number_format((float)preg_replace('/[^\d.]/', '', $netHarvest), 2),
    ];

    // 8) render PDF (wood-like options)
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml(build_treecut_html($data), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    // 9) insert approved_docs → get approved_id (save modal inputs)
    $ins = $pdo->prepare("
        INSERT INTO public.approved_docs
            (approval_id, approved_document, date_issued, expiry_date, tcp_no, orno, net_harvest_volume)
        VALUES
            (:aid, ''::text, :issued, NULL, :tcp, :orno, :netv)
        RETURNING approved_id
    ");
    $ins->execute([
        ':aid'    => $approvalId,
        ':issued' => $issuedDT->format('Y-m-d'),
        ':tcp'    => $tcpNo,
        ':orno'   => $orNo,
        ':netv'   => $netHarvest,
    ]);
    $approvedId = (string)$ins->fetchColumn();
    if (!$approvedId) throw new RuntimeException('Failed to get approved_id for treecut.');

    // 10) upload to storage
    $safeTcp   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tcpNo ?: 'TCP');
    $filename  = 'TREECUT_PERMIT_' . $safeTcp . '_' . date('Ymd_His') . '.pdf';
    $objectKey = "treecut/{$folderType}/{$clientId}/{$approvedId}/{$filename}";

    $publicUrl = supabase_storage_upload($STORAGE_BUCKET, $objectKey, $pdf, 'application/pdf');
    if (!$publicUrl) throw new RuntimeException('Supabase upload failed (treecut).');

    $pdo->prepare("UPDATE public.approved_docs SET approved_document=:u WHERE approved_id=:id")
        ->execute([':u' => $publicUrl, ':id' => $approvedId]);

    return ['url' => $publicUrl, 'filename' => $filename, 'approved_id' => $approvedId];
}









function generate_and_store_lumber(PDO $pdo, string $approvalId, array $inputs): array
{
    global $STORAGE_BUCKET;

    // approval → application/client
    $st = $pdo->prepare("
        SELECT a.application_id, a.client_id, LOWER(COALESCE(a.permit_type,'new')) AS permit_type
        FROM public.approval a
        WHERE a.approval_id = :aid
        LIMIT 1
    ");
    $st->execute([':aid' => $approvalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $clientId      = (string)($row['client_id'] ?? '');
    $applicationId = (string)($row['application_id'] ?? '');
    $permitType    = (string)($row['permit_type'] ?? 'new');
    $folderType    = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';

    // application_form
    $app = [];
    if ($applicationId !== '') {
        $s = $pdo->prepare("SELECT * FROM public.application_form WHERE application_id=:id LIMIT 1");
        $s->execute([':id' => $applicationId]);
        $app = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // client
    $client = [];
    if ($clientId !== '') {
        $s = $pdo->prepare("SELECT * FROM public.client WHERE client_id=:cid LIMIT 1");
        $s->execute([':cid' => $clientId]);
        $client = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $first = trim((string)($client['first_name'] ?? ''));
    $last  = trim((string)($client['last_name'] ?? ''));
    $clientName = trim($first . ' ' . $last);
    $addrParts = [];
    foreach (['sitio_street', 'barangay', 'municipality', 'city'] as $k) {
        $v = trim((string)($client[$k] ?? ''));
        if ($v !== '') $addrParts[] = $v;
    }
    $clientAddress = implode(', ', $addrParts);

    // Registration No. from modal inputs
    $registrationNo = trim((string)($inputs['registration_no'] ?? ''));

    // Update the application_form table with the registration number
    if ($registrationNo !== '' && $applicationId !== '') {
        $pdo->prepare("
            UPDATE public.application_form
               SET registration_no = :reg_no
             WHERE application_id = :app_id
        ")->execute([':reg_no' => $registrationNo, ':app_id' => $applicationId]);
    }

    // fields from app
    $businessName   = (string)($app['business_name'] ?? '');
    $businessPlace  = (string)($app['business_place'] ?? ($client['municipality'] ?? ($client['city'] ?? '')));

    // Parse suppliers (JSON or legacy)
    $suppliers = [];
    $suppliersJson = $app['suppliers_json'] ?? null;

    if ($suppliersJson && is_string($suppliersJson)) {
        $decodedSuppliers = json_decode($suppliersJson, true);
        if (is_array($decodedSuppliers)) {
            foreach ($decodedSuppliers as $supplier) {
                if (is_array($supplier)) {
                    $suppliers[] = [
                        (string)($supplier['name'] ?? $supplier['supplier'] ?? ''),
                        (string)($supplier['volume'] ?? '')
                    ];
                } elseif (is_string($supplier)) {
                    $suppliers[] = [$supplier, ''];
                }
            }
        }
    }

    if (empty($suppliers)) {
        $rawSup = $app['supplier_name_company'] ?? null;
        $rawVol = $app['volume'] ?? null;
        $toArr = function ($v) {
            if (is_array($v)) return $v;
            $s = trim((string)$v);
            if ($s === '') return [];
            $j = json_decode($s, true);
            if (is_array($j)) return $j;
            $s = str_replace(["\r\n", "\r"], "\n", $s);
            $parts = array_map('trim', preg_split('~\n|,~', $s));
            return array_values(array_filter($parts, fn($x) => $x !== ''));
        };
        $names = $toArr($rawSup);
        $vols  = $toArr($rawVol);
        $n = max(count($names), count($vols));
        for ($i = 0; $i < $n; $i++) {
            $suppliers[] = [
                (string)($names[$i] ?? ''),
                (string)($vols[$i] ?? '')
            ];
        }
    }

    while (count($suppliers) < 3) {
        $suppliers[] = ['', ''];
    }

    // dates: today + expiry = today + 2 years
    $tz       = new DateTimeZone('Asia/Manila');
    $todayDT  = new DateTime('now', $tz);
    $todayFmt = $todayDT->format('F j, Y');
    $expiryDT = (clone $todayDT)->modify('+2 years');
    $expiryFmt = $expiryDT->format('F j, Y');

    // modal inputs
    $issuedAt = trim((string)($inputs['issued_at'] ?? ''));
    $bondAmt  = (string)($inputs['perf_bond_amt'] ?? '');
    $orNo     = trim((string)($inputs['or_no'] ?? ''));

    // format peso (₱)
    $fmtPeso = function (string $num): string {
        $n = preg_replace('/[^\d.]/', '', $num);
        if ($n === '' || !is_numeric($n)) return '₱ 0.00';
        return '₱ ' . number_format((float)$n, 2);
    };

    $data = [
        'registration_no'    => $registrationNo,
        'client_name'        => $clientName,
        'client_address'     => $clientAddress,
        'business_name'      => $businessName,
        'business_place'     => $businessPlace,
        'suppliers'          => $suppliers,
        'date_today_fmt'     => $todayFmt,
        'expiry_on_fmt'      => $expiryFmt,          // <-- pass formatted expiry to HTML
        'issued_at'          => $issuedAt,
        'perf_bond_amt_fmt'  => $fmtPeso($bondAmt),
        'or_no'              => $orNo,
    ];

    // render → PDF
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml(build_lumber_html($data), 'UTF-8');
    $dompdf->setPaper('letter', 'landscape');
    $dompdf->render();
    $pdf = $dompdf->output();

    // Insert approved_docs (expiry = today + 2 years)
    $ins = $pdo->prepare("
        INSERT INTO public.approved_docs
            (approval_id, approved_document, date_issued, expiry_date, no, orno, place_of_issue)
        VALUES
            (:aid, ''::text, :issued, :expiry, :no, :orno, :place)
        RETURNING approved_id
    ");
    $ins->execute([
        ':aid'    => $approvalId,
        ':issued' => $todayDT->format('Y-m-d'),
        ':expiry' => $expiryDT->format('Y-m-d'),  // <-- 2 years from today
        ':no'     => $registrationNo,             // store registration number
        ':orno'   => $orNo,
        ':place'  => $issuedAt ?: null,
    ]);
    $approvedId = (string)$ins->fetchColumn();
    if (!$approvedId) {
        throw new RuntimeException('Failed to get approved_id for lumber.');
    }

    // upload to storage
    $safeNo   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $registrationNo ?: 'LUMBER');
    $filename = 'LUMBER_CERT_' . $safeNo . '_' . date('Ymd_His') . '.pdf';
    $objectKey = "lumber/{$folderType}/{$clientId}/{$approvedId}/{$filename}";

    $publicUrl = supabase_storage_upload($STORAGE_BUCKET, $objectKey, $pdf, 'application/pdf');
    if (!$publicUrl) throw new RuntimeException('Supabase upload failed (lumber).');

    $pdo->prepare("UPDATE public.approved_docs SET approved_document=:u WHERE approved_id=:id")
        ->execute([':u' => $publicUrl, ':id' => $approvedId]);

    return ['url' => $publicUrl, 'filename' => $filename, 'approved_id' => $approvedId];
}





/**
 * UPDATED: Build Wood Processing Plant Permit HTML
 * - Robust address fallbacks (Complete Business Address / Location parts)
 * - Robust capacity key fallbacks
 * - Suppliers/species/contracted volume from multiple JSON keys OR saved HTML table
 */
function build_wood_html(array $d): string
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $denr = img_data_uri(__DIR__ . '/denr.png');
    $ph   = img_data_uri(__DIR__ . '/pilipinas.png');

    // helpers
    $pick = function (...$vals): string {
        foreach ($vals as $v) {
            $s = trim((string)$v);
            if ($s !== '' && strtolower($s) !== 'null') return $s;
        }
        return '';
    };
    $join = function (array $parts): string {
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string)$p);
            if ($p !== '' && strtolower($p) !== 'null') $out[] = $p;
        }
        return implode(', ', $out);
    };

    // Dates
    try {
        $issuedOn = new DateTime($d['issued_on'] ?? 'now', new DateTimeZone('Asia/Manila'));
    } catch (Throwable $t) {
        $issuedOn = new DateTime('now', new DateTimeZone('Asia/Manila'));
    }
    $expiryOn = (clone $issuedOn)->modify('+2 years');

    $issued_on_fmt = $issuedOn->format('F j, Y');
    $expiry_on_fmt = $expiryOn->format('F j, Y');

    // Parse additional_information JSON (string or array)
    $ai = [];
    if (!empty($d['additional_information'])) {
        $tmp = is_array($d['additional_information']) ? $d['additional_information']
            : json_decode((string)$d['additional_information'], true);
        if (is_array($tmp)) $ai = $tmp;
    }

    // Plant subtype (from JSON plant_type)
    $plant_subtype   = (string)($ai['plant_type'] ?? '');
    $plant_subtype_u = $plant_subtype !== '' ? mb_strtoupper($plant_subtype) : 'WOOD PROCESSING PLANT';

    // Office address (Complete Business Address / Present Address / parts)
    $present_addr = $pick(
        $d['present_address'] ?? null,
        $d['business_address'] ?? null,
        $ai['complete_business_address'] ?? null,
        $ai['business_address'] ?? null,
        $join([
            $ai['business_house_no'] ?? null,
            $ai['business_street'] ?? null,
            $ai['business_barangay'] ?? null,
            $ai['business_municipality'] ?? null,
            $ai['business_city'] ?? null,
            $ai['business_province'] ?? null
        ])
    );

    // Plant location (Plant Location (Barangay/Municipality/Province) / Location / parts)
    $site_location = $pick(
        $d['location'] ?? null,
        $ai['plant_location'] ?? null,
        $join([
            $ai['plant_barangay'] ?? null,
            $ai['plant_municipality'] ?? null,
            $ai['plant_city'] ?? null,
            $ai['plant_province'] ?? null
        ])
    );

    // Capacity (tolerant to key variants)
    $daily_capacity = $pick(
        $ai['daily_capacity'] ?? null,
        $d['daily_rated_capacity_per8_hour_shift'] ?? null,
        $d['daily_rated_capacity_per_8_hour_shift'] ?? null,
        $d['daily_rated_capacity'] ?? null
    );

    // Suppliers/species/contracted volume
    $supply_rows = [];
    $raw = null;
    foreach (['supply_rows', 'supplyRows', 'supply_contracts', 'supply_contract_rows', 'suppliers'] as $k) {
        if (!empty($ai[$k])) {
            $raw = $ai[$k];
            break;
        }
    }
    if (is_string($raw)) {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) $raw = $tmp;
    }
    if (is_array($raw)) {
        foreach ($raw as $r) {
            if (is_array($r)) {
                $isList = array_keys($r) === range(0, count($r) - 1);
                if ($isList) {
                    $supply_rows[] = [
                        (string)($r[0] ?? ''),
                        (string)($r[1] ?? ''),
                        (string)($r[2] ?? '')
                    ];
                } else {
                    $supply_rows[] = [
                        $pick($r['supplier_name'] ?? null, $r['supplier'] ?? null, $r['name'] ?? null),
                        $pick($r['species'] ?? null, $r['species_name'] ?? null),
                        $pick($r['contracted_volume'] ?? null, $r['contractedVol'] ?? null, $r['volume'] ?? null, $r['vol'] ?? null)
                    ];
                }
            }
        }
    }
    // Fallback: parse an HTML table that was saved as supply_table_html
    if (!$supply_rows && !empty($ai['supply_table_html'])) {
        $html = (string)$ai['supply_table_html'];
        if (preg_match_all('~<tr[^>]*>(.*?)</tr>~is', $html, $m)) {
            foreach ($m[1] as $tr) {
                if (preg_match_all('~<t[dh][^>]*>(.*?)</t[dh]>~is', $tr, $cells)) {
                    $vals = array_map(fn($c) => trim(html_entity_decode(strip_tags($c))), $cells[1]);
                    if (!$vals) continue;
                    if (preg_match('~supplier|species|contract~i', implode(' ', $vals))) continue; // skip header rows
                    $supply_rows[] = [$vals[0] ?? '', $vals[1] ?? '', $vals[2] ?? ''];
                }
            }
        }
    }
    // keep at least 3 rows for layout
    while (count($supply_rows) < 3) $supply_rows[] = ['', '', ''];

    // Core fields from $d/application
    $permit_no   = (string)($d['no'] ?? '');
    $issued_at   = (string)($d['issued_at'] ?? '');
    $client_name = (string)($d['client_name'] ?? '');
    $permit_kind = strtolower((string)($d['permit_type'] ?? 'new'));
    $permit_kind = in_array($permit_kind, ['new', 'renewal'], true) ? $permit_kind : 'new';

    return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Wood Processing Plant Permit</title>
  <style>
    *{box-sizing:border-box;font-family:"Times New Roman",serif;color:#000}
    @page{size:letter;margin:.5in}
    body{margin:0;background:#fff}
    .container{width:100%;min-height:11in}
    .header{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:12px}
    .header td{vertical-align:middle}
    .header .col-left{width:95px}
    .header .col-right{width:120px;text-align:right}
    .header .col-center{text-align:center}
    .logo-left{display:block;width:80px;height:80px;object-fit:contain}
    .logo-right{display:block;width:110px;height:110px;object-fit:contain}
    .denr h1{margin:0 0 4px 0;font-size:17px;line-height:1.2}
    .denr p{margin:0;font-size:13px;line-height:1.1}
    .permit-title-section{border-top:4px solid #f00;padding-top:10px;margin-top:10px}
    .permit-title{text-align:center;margin:10px 0;font-size:18px;font-weight:700;text-decoration:underline}
    .permit-subtitle{text-align:center;margin:0 0 10px 0;font-size:16px;font-weight:700}
    .permit-number{text-align:center;margin-bottom:10px;font-size:14px}
    .underline{display:inline-block;border-bottom:1px solid #000;min-width:250px;padding:0 3px}
    .small{display:inline-block;border-bottom:1px solid #000;min-width:120px;padding:0 3px}
    .body{font-size:13px;line-height:1.45;text-align:justify;margin-bottom:15px}
    .center{text-align:center;margin:10px 0}
    .info-table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12px;border:1px solid #000}
    .info-table th,.info-table td{border:1px solid #000;padding:3px 6px;vertical-align:top}
    .info-table th{text-align:center;font-weight:700}
    .note{margin:8px 0;font-size:11px}
    .approval{margin-top:16px;text-align:center}
    .approval h2{font-size:14px;margin:0 0 6px 0;text-decoration:underline}
    .approval .name{font-weight:700;font-size:13px;margin:10px 0 2px}
    .approval .pos{font-size:11px;line-height:1.1}
    .address{margin-top:.5in}
  </style>
</head>
<body>
  <div class="container" id="permit">

    <table class="header">
      <tr>
        <td class="col-left">
          <img class="logo-left" src="' . $e($denr) . '" alt="DENR">
        </td>
        <td class="col-center">
          <div class="denr">
            <h1>Department of Environment and Natural Resources</h1>
            <p>Region 7</p>
            <p>Province of Cebu</p>
            <p>Municipality of Argao</p>
          </div>
        </td>
        <td class="col-right">
          <img class="logo-right" src="' . $e($ph) . '" alt="Pilipinas">
        </td>
      </tr>
    </table>

    <div class="permit-title-section">
      <div class="permit-title">WOOD PROCESSING PLANT PERMIT</div>
      <div class="permit-subtitle">' . $e($plant_subtype_u) . '</div>
      <div class="permit-number">No: <span class="small">' . $e($permit_no) . '</span></div>
      <div class="permit-number">' . $e($permit_kind) . ' permit</div>
    </div>

    <div class="body">
      <p>
        Pursuant to Presidential Decree No. 705, DAO No. 2021-05 dated March 26, 2021,
        "Revised Regulations Governing the Establishment and Operations of Wood Processing Plants (WPPs)"
        and other existing laws and regulations, a Wood Processing Plant Permit is hereby issued to:
      </p>

      <div class="center">
        <p><strong>' . $e($client_name) . '</strong></p>
        <p><span class="underline">' . $e($client_name) . '</span> (Name)</p>
        <p>
          a proprietor of the Philippines with office address located at
          <span class="underline">' . $e($present_addr) . '</span>, to operate a ' . $e($permit_kind) . ' Wood Processing Plant
          (' . $e($plant_subtype) . ') located at <span class="underline">' . $e($site_location) . '</span>,
          having a Daily Rated Capacity of approximately
          <span class="underline">' . $e($daily_capacity) . '</span> per 8-hour shift of operation.
        </p>
      </div>

      <p style="margin:10px 0;">The permittee has Log/Lumber Supply Contracts for a period of five (5) years, to wit:</p>

      <table class="info-table">
        <tr>
          <th>SUPPLIERS</th>
          <th>SPECIES</th>
          <th>CONTRACTED VOL.</th>
        </tr>' .
        implode("", array_map(function ($r) {
            $c0 = htmlspecialchars($r[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $c1 = htmlspecialchars($r[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $c2 = htmlspecialchars($r[2] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            return "<tr><td>{$c0}</td><td>{$c1}</td><td>{$c2}</td></tr>";
        }, $supply_rows)) . '
      </table>

      <p class="note">*see list of PTPR/CTPO holders at the back of this Permit</p>

      <p style="margin:10px 0;">
        which volume is considered adequate to supply the wood requirements of the mill under this permit.
      </p>

      <p style="margin:10px 0;">
        This permit is subject to the provisions of Presidential Decree No. 705 as amended by Executive Order No. 277 and other applicable laws,
        including the rules and regulations promulgated thereto, and subject to the Terms and Conditions enumerated in the succeeding pages
        (marked as Annex A), and such other additional regulations which may hereinafter be prescribed.
      </p>

      <p style="margin:10px 0;">
        This permit is effective on the date of issue and expires on <span class="small">' . $e($expiry_on_fmt) . '</span>.
      </p>

      <p style="margin:10px 0;">
        Issued on <span class="small">' . $e($issued_on_fmt) . '</span>
        at <span class="small">' . $e($issued_at) . '</span> Philippines.
      </p>
    </div>

    <div class="approval">
      <h2>Approved:</h2>
      <div class="name">ATTY. JUAN MIGUEL T. CUNA, CESO I</div>
      <div class="pos">Undersecretary for Field Operations - Luzon, Visayas and</div>
      <div class="pos">Supervising Undersecretary for Mines and Geosciences</div>
      <div class="pos">Bureau-Luzon and Visayas, Environmental Management</div>
      <div class="pos">Bureau-Luzon and Visayas</div>

      <div class="address">
        <div class="pos">Visayas Avenue, Diliman, Quezon City 1100, Philippines</div>
        <div class="pos">www.denr.gov.ph</div>
      </div>
    </div>

  </div>
</body>
</html>';
}

/**
 * Generate Chainsaw PDF, upload to Supabase, write approved_docs, return URL/filename.
 * Storage key: chainsaw/{new permit|renewal permit}/{client_id}/{approved_id}/{filename}.pdf
 * $inputs = ['no' => '...', 'orno' => '...', 'issued_on' => 'YYYY-MM-DD', 'or_date' => 'YYYY-MM-DD', 'issued_at' => 'Place']
 */
function generate_and_store_chainsaw(PDO $pdo, string $approvalId, array $inputs): array
{
    global $STORAGE_BUCKET;

    // Pull approval → application + client ids + permit_type
    $st = $pdo->prepare("
        SELECT a.application_id, a.client_id, LOWER(COALESCE(a.permit_type,'new')) AS permit_type
        FROM public.approval a
        WHERE a.approval_id = :aid
        LIMIT 1
    ");
    $st->execute([':aid' => $approvalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $clientId      = (string)($row['client_id'] ?? '');
    $applicationId = (string)($row['application_id'] ?? '');
    $permitType    = (string)($row['permit_type'] ?? 'new');
    $folderType    = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';

    // Application form (chainsaw attributes live here)
    $app = [];
    if ($applicationId !== '') {
        $st2 = $pdo->prepare("SELECT * FROM public.application_form WHERE application_id=:id LIMIT 1");
        $st2->execute([':id' => $applicationId]);
        $app = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Client (get name + address from client table)
    $client = [];
    if ($clientId !== '') {
        $st3 = $pdo->prepare("SELECT * FROM public.client WHERE client_id=:cid LIMIT 1");
        $st3->execute([':cid' => $clientId]);
        $client = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $first = trim((string)($client['first_name'] ?? ''));
    $last  = trim((string)($client['last_name'] ?? ''));
    $clientName = trim($first . ' ' . $last);

    $parts = [];
    foreach (['sitio_street', 'barangay', 'municipality', 'city'] as $k) {
        $v = trim((string)($client[$k] ?? ''));
        if ($v !== '') $parts[] = $v;
    }
    $clientAddress = implode(', ', $parts);

    // Map fields from application_form
    $purpose = (string)($app['purpose_of_use'] ?? '');
    $brand   = (string)($app['brand'] ?? '');
    $model   = (string)($app['model'] ?? '');
    $dateAcqRaw = (string)($app['date_of_acquisition'] ?? '');
    $serial  = (string)($app['serial_number_chainsaw'] ?? '');
    $hp      = (string)($app['horsepower'] ?? '');
    $bar     = (string)($app['maximum_length_of_guide_bar'] ?? '');

    // Dates (allow override from POST)
    $tz = new DateTimeZone('Asia/Manila');

    $issuedOn = DateTime::createFromFormat('Y-m-d', (string)($inputs['issued_on'] ?? ''), $tz);
    if (!$issuedOn) $issuedOn = new DateTime('now', $tz);

    $orDate = DateTime::createFromFormat('Y-m-d', (string)($inputs['or_date'] ?? ''), $tz);
    if (!$orDate) $orDate = clone $issuedOn;

    $expiryOn = (clone $issuedOn)->modify('+2 years');

    // Place of issue
    $issuedAt = (string)($inputs['issued_at'] ?? 'CENRO Argao, Cebu');

    // Format "date of acquisition"
    $dateAcq = DateTime::createFromFormat('Y-m-d', $dateAcqRaw, $tz) ?: (DateTime::createFromFormat('m/d/Y', $dateAcqRaw, $tz) ?: null);
    $dateAcqFmt = $dateAcq ? $dateAcq->format('F j, Y') : $dateAcqRaw;

    $data = [
        'no'                      => (string)($inputs['no'] ?? ''),
        'orno'                    => (string)($inputs['orno'] ?? ''),
        'client_name'             => $clientName,
        'client_address'          => $clientAddress,
        'purpose_of_use'          => $purpose,
        'brand'                   => $brand,
        'model'                   => $model,
        'date_of_acquisition_fmt' => $dateAcqFmt,
        'serial_no'               => $serial,
        'horsepower'              => $hp,
        'max_guide_bar'           => $bar,
        'issued_on_fmt'           => $issuedOn->format('F j, Y'),
        'expiry_on_fmt'           => $expiryOn->format('F j, Y'),
        'or_date_fmt'             => $orDate->format('F j, Y'),
        'issued_at'               => $issuedAt,
        'permit_type'             => $permitType,
    ];

    // Render HTML → PDF
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml(build_chainsaw_html($data), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    // Insert approved_docs row FIRST to get approved_id (includes place_of_issue)
    $ins = $pdo->prepare("
        INSERT INTO public.approved_docs
            (approval_id, approved_document, date_issued, expiry_date, no, orno, place_of_issue)
        VALUES
            (:aid, ''::text, :issued, :expiry, :no, :orno, :place)
        RETURNING approved_id
    ");
    $ins->execute([
        ':aid'    => $approvalId,
        ':issued' => $issuedOn->format('Y-m-d'),
        ':expiry' => $expiryOn->format('Y-m-d'),
        ':no'     => (string)$inputs['no'],
        ':orno'   => (string)$inputs['orno'],
        ':place'  => $issuedAt,
    ]);
    $approvedId = (string)$ins->fetchColumn();
    if (!$approvedId) {
        throw new RuntimeException('Failed to get approved_id for chainsaw.');
    }

    // Storage object key
    $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$inputs['no']);
    $filename  = 'CHAINSAW_CERT_' . ($safeNo !== '' ? $safeNo . '_' : '') . date('Ymd_His') . '.pdf';
    $objectKey = "chainsaw/{$folderType}/{$clientId}/{$approvedId}/{$filename}";

    // Upload to Supabase Storage
    $publicUrl = supabase_storage_upload($STORAGE_BUCKET, $objectKey, $pdf, 'application/pdf');
    if (!$publicUrl) {
        throw new RuntimeException('Supabase upload failed (chainsaw).');
    }

    // Update with URL
    $pdo->prepare("UPDATE public.approved_docs SET approved_document=:u WHERE approved_id=:id")
        ->execute([':u' => $publicUrl, ':id' => $approvedId]);

    return ['url' => $publicUrl, 'filename' => $filename, 'approved_id' => $approvedId];
}

/**
 * UPDATED: Generate WPP (Wood) PDF, upload to Supabase, write approved_docs, return URL/filename.
 * - Reads addresses robustly (present address / complete business address variants)
 * - Pulls capacity + supply table from additional_information JSON (tolerant to key variants) or HTML table fallback
 * Storage key: wood/{new permit|renewal permit}/{client_id}/{approved_id}/{filename}.pdf
 * $inputs = ['no' => 'WPP-XXXX', 'issued_on' => 'YYYY-MM-DD', 'issued_at' => 'Place']
 */
function generate_and_store_wood(PDO $pdo, string $approvalId, array $inputs): array
{
    global $STORAGE_BUCKET;

    // Pull approval → application + client ids + permit_type
    $st = $pdo->prepare("
        SELECT a.application_id, a.client_id, LOWER(COALESCE(a.permit_type,'new')) AS permit_type
        FROM public.approval a
        WHERE a.approval_id = :aid
        LIMIT 1
    ");
    $st->execute([':aid' => $approvalId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $clientId      = (string)($row['client_id'] ?? '');
    $applicationId = (string)($row['application_id'] ?? '');
    $permitType    = (string)($row['permit_type'] ?? 'new');
    $folderType    = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';

    // Application (addresses + JSON)
    $app = [];
    if ($applicationId !== '') {
        $st2 = $pdo->prepare("SELECT * FROM public.application_form WHERE application_id=:id LIMIT 1");
        $st2->execute([':id' => $applicationId]);
        $app = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Client (name)
    $client = [];
    if ($clientId !== '') {
        $st3 = $pdo->prepare("SELECT * FROM public.client WHERE client_id=:cid LIMIT 1");
        $st3->execute([':cid' => $clientId]);
        $client = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $first = trim((string)($client['first_name'] ?? ''));
    $last  = trim((string)($client['last_name'] ?? ''));
    $clientName = trim($first . ' ' . $last);

    // Dates
    $tz = new DateTimeZone('Asia/Manila');
    $issuedOn = DateTime::createFromFormat('Y-m-d', (string)($inputs['issued_on'] ?? ''), $tz);
    if (!$issuedOn) $issuedOn = new DateTime('now', $tz);
    $expiryOn = (clone $issuedOn)->modify('+2 years');

    // Place
    $issuedAt = (string)($inputs['issued_at'] ?? 'CENRO Argao, Cebu');

    // Decode additional_information early (so we can mine address/location parts if needed)
    $ai = [];
    if (!empty($app['additional_information'])) {
        $tmp = json_decode((string)$app['additional_information'], true);
        if (is_array($tmp)) $ai = $tmp;
    }

    // helpers for robust addressing
    $pick = function (...$vals): string {
        foreach ($vals as $v) {
            $s = trim((string)$v);
            if ($s !== '' && strtolower($s) !== 'null') return $s;
        }
        return '';
    };
    $join = function (array $parts): string {
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string)$p);
            if ($p !== '' && strtolower($p) !== 'null') $out[] = $p;
        }
        return implode(', ', $out);
    };

    $presentAddr = $pick(
        $app['present_address'] ?? null,
        $app['business_address'] ?? null,
        $ai['complete_business_address'] ?? null,
        $ai['business_address'] ?? null,
        $join([
            $ai['business_house_no'] ?? null,
            $ai['business_street'] ?? null,
            $ai['business_barangay'] ?? null,
            $ai['business_municipality'] ?? null,
            $ai['business_city'] ?? null,
            $ai['business_province'] ?? null
        ])
    );

    $siteLocation = $pick(
        $app['location'] ?? null,
        $ai['plant_location'] ?? null,
        $join([
            $ai['plant_barangay'] ?? null,
            $ai['plant_municipality'] ?? null,
            $ai['plant_city'] ?? null,
            $ai['plant_province'] ?? null
        ])
    );

    // Data for HTML
    $data = [
        'no'                                   => (string)($inputs['no'] ?? ''),
        'client_name'                          => $clientName,
        'present_address'                      => $presentAddr,                         // Address #1 (robust)
        'location'                             => $siteLocation,                        // Address #2 (robust)
        'daily_rated_capacity_per8_hour_shift' => (string)($app['daily_rated_capacity_per8_hour_shift'] ?? ''), // legacy fallback
        'daily_rated_capacity_per_8_hour_shift' => (string)($app['daily_rated_capacity_per_8_hour_shift'] ?? ''), // extra fallback
        'daily_rated_capacity'                 => (string)($app['daily_rated_capacity'] ?? ''),                 // extra fallback
        'additional_information'               => $ai ?: (string)($app['additional_information'] ?? ''),        // pass decoded if available
        'permit_type'                          => $permitType,
        'issued_on'                            => $issuedOn->format('Y-m-d'),
        'issued_at'                            => $issuedAt,
    ];

    // Render HTML → PDF
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($opts);
    $dompdf->loadHtml(build_wood_html($data), 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    // Insert approved_docs row FIRST to get approved_id (keep WPP no in wfp_no)
    $ins = $pdo->prepare("
        INSERT INTO public.approved_docs
            (approval_id, approved_document, date_issued, expiry_date, wfp_no, place_of_issue)
        VALUES
            (:aid, ''::text, :issued, :expiry, :wno, :place)
        RETURNING approved_id
    ");
    $ins->execute([
        ':aid'    => $approvalId,
        ':issued' => $issuedOn->format('Y-m-d'),
        ':expiry' => $expiryOn->format('Y-m-d'),
        ':wno'    => (string)$inputs['no'],
        ':place'  => $issuedAt,
    ]);
    $approvedId = (string)$ins->fetchColumn();
    if (!$approvedId) {
        throw new RuntimeException('Failed to get approved_id for wood permit.');
    }

    // Storage object key
    $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$inputs['no']);
    $filename  = 'WPP_PERMIT_' . ($safeNo !== '' ? $safeNo . '_' : '') . date('Ymd_His') . '.pdf';
    $objectKey = "wood/{$folderType}/{$clientId}/{$approvedId}/{$filename}";

    // Upload
    $publicUrl = supabase_storage_upload($STORAGE_BUCKET, $objectKey, $pdf, 'application/pdf');
    if (!$publicUrl) {
        throw new RuntimeException('Supabase upload failed (wood).');
    }

    // Update with URL
    $pdo->prepare("UPDATE public.approved_docs SET approved_document=:u WHERE approved_id=:id")
        ->execute([':u' => $publicUrl, ':id' => $approvedId]);

    return ['url' => $publicUrl, 'filename' => $filename, 'approved_id' => $approvedId];
}

/* ------------------------------------------------------------------ */

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

    $isAdmin     = $me && strtolower((string)$me['role']) === 'admin';
    $dept        = strtolower((string)($me['department'] ?? ''));
    $isTreeDept  = in_array($dept, ['forestry', 'tree cutting', 'tree-cutting'], true);

    if (!$isAdmin || !$isTreeDept) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[TREE-ADMIN GUARD] ' . $e->getMessage());
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
function is_image_url(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
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
        error_log('[TREE-APPSTAT MARK_READ] ' . $e->getMessage());
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
             WHERE LOWER(COALESCE(\"to\", '')) = 'tree cutting'
               AND is_read = false
        ");
        $updPermits->execute();
        $countPermits = $updPermits->rowCount();

        $updInc = $pdo->prepare("
            UPDATE public.incident_report
               SET is_read = true
             WHERE LOWER(COALESCE(category, '')) = 'tree cutting'
               AND is_read = false
        ");
        $updInc->execute();
        $countInc = $updInc->rowCount();

        $pdo->commit();
        echo json_encode(['ok' => true, 'updated' => ['permits' => (int)$countPermits, 'incidents' => (int)$countInc]]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[TREE-APPSTAT MARK_ALL_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}

/* ---------------- AJAX (mark notifs for a specific approval as read) ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_notifs_for_approval') {
    header('Content-Type: application/json');
    $approvalId = $_POST['approval_id'] ?? '';
    if (!$approvalId) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit();
    }
    try {
        $st = $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE approval_id=:aid AND is_read=false");
        $st->execute([':aid' => $approvalId]);
        echo json_encode(['ok' => true, 'count' => (int)$st->rowCount()]);
    } catch (Throwable $e) {
        error_log('[TREE-APPSTAT MARK_NOTIFS_FOR_APPROVAL] ' . $e->getMessage());
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
            AND LOWER(COALESCE(a.request_type,'')) IN ('treecut','lumber','wood','chainsaw')
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
                $trim  = ltrim($val);

                if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $kind = 'json';
                        $val = $decoded;
                    }
                } elseif (preg_match('~^https?://~i', $val)) {
                    $kind = is_image_url($val) ? 'image' : 'link';
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
        error_log('[TREE-DETAILS AJAX] ' . $e->getMessage());
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

        // Lock record and validate
        $st = $pdo->prepare("
            SELECT a.approval_id, a.approval_status, a.request_type, a.client_id,
                   LOWER(COALESCE(a.permit_type,'new')) AS permit_type
            FROM public.approval a
            WHERE a.approval_id = :aid
              AND LOWER(COALESCE(a.request_type,'')) IN ('treecut','lumber','wood','chainsaw')
            FOR UPDATE
        ");
        $st->execute([':aid' => $approvalId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'approval not found']);
            exit;
        }

        $statusNow = strtolower((string)$row['approval_status']);
        $reqType   = strtolower((string)$row['request_type']);
        $reqTypeLabel = ($reqType === 'treecut') ? 'tree cutting' : $reqType;


        // Resolve requester user_id (used as notifications."to")
        $toUserId = null;
        $clientId = (string)($row['client_id'] ?? '');
        if ($clientId !== '') {
            $stC = $pdo->prepare("SELECT user_id FROM public.client WHERE client_id=:cid LIMIT 1");
            $stC->execute([':cid' => $clientId]);
            $toUserId = (string)($stC->fetchColumn() ?: '');
        }
        if (!$toUserId) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Cannot resolve requester user_id for notifications.']);
            exit();
        }

        /* ----------------------------- APPROVE → FOR PAYMENT ----------------------------- */
        if ($action === 'approve') {
            if ($statusNow !== 'pending') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'already decided']);
                exit;
            }

            // Move to FOR PAYMENT (no doc generation here)
            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status   = 'for payment',
                       approved_at       = NULL,
                       approved_by       = NULL,
                       rejected_at       = NULL,
                       reject_by         = NULL,
                       rejection_reason  = NULL
                 WHERE approval_id = :aid
            ")->execute([':aid' => $approvalId]);

            // Notify client (to = requester user_id)
            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromVal, :toVal)
            ")->execute([
                ':aid'     => $approvalId,
                ':msg'     => 'Your ' . $reqTypeLabel . ' permit was approved. You have to pay personally',
                ':fromVal' => 'Tree Cutting',
                ':toVal'   => $toUserId,
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'for payment']);
            exit;
        }

        /* -------------------------------------- REJECT ----------------------------------- */
        if ($action === 'reject') {
            if ($statusNow !== 'pending') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'already decided']);
                exit;
            }
            if ($reason === '') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'reason required']);
                exit;
            }

            $adminId = (string)$_SESSION['user_id'];

            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status   = 'rejected',
                       rejected_at       = now(),
                       reject_by         = :by,
                       rejection_reason  = :reason
                 WHERE approval_id = :aid
            ")->execute([
                ':by'     => $adminId,
                ':reason' => $reason,
                ':aid'    => $approvalId
            ]);

            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromVal, :toVal)
            ")->execute([
                ':aid'     => $approvalId,
                ':msg'     => 'Your ' . $reqTypeLabel . ' permit was rejected. You can check the reason',
                ':fromVal' => 'Tree Cutting',
                ':toVal'   => $toUserId,
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'rejected']);
            exit;
        }

        /* ------------------------------------- RELEASE ----------------------------------- */
        if ($action === 'release') {
            // Only allowed from FOR PAYMENT
            if ($statusNow !== 'for payment') {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'record must be in "for payment" status']);
                exit;
            }

            // Collect per-type inputs (already provided by your existing modal)
            $documentUrl = null;

            if ($reqType === 'chainsaw') {
                $no       = trim((string)($_POST['cs_cert_no']   ?? $_POST['no'] ?? ''));
                $orno     = trim((string)($_POST['cs_or_no']     ?? $_POST['orno'] ?? ''));
                $issuedOn = trim((string)($_POST['cs_issued_on'] ?? $_POST['issued_on'] ?? ''));
                $orDate   = trim((string)($_POST['cs_or_date']   ?? $_POST['or_date']   ?? ''));
                $place    = trim((string)($_POST['cs_place']     ?? $_POST['issued_at'] ?? ''));

                if ($no === '' || $orno === '' || $issuedOn === '' || $orDate === '' || $place === '') {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Missing chainsaw details (no, orno, issued_on, or_date, place)']);
                    exit;
                }

                $docInfo = generate_and_store_chainsaw($pdo, $approvalId, [
                    'no'        => $no,
                    'orno'      => $orno,
                    'issued_on' => $issuedOn,
                    'or_date'   => $orDate,
                    'issued_at' => $place,
                ]);
                $documentUrl = $docInfo['url'] ?? null;
            } elseif ($reqType === 'wood') {
                $no       = trim((string)($_POST['w_no']        ?? $_POST['no']        ?? ''));
                $issuedOn = trim((string)($_POST['w_issued_on'] ?? $_POST['issued_on'] ?? ''));
                $place    = trim((string)($_POST['w_issued_at'] ?? $_POST['issued_at'] ?? ''));

                if ($no === '' || $issuedOn === '' || $place === '') {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Missing wood permit details (no, issued_on, issued_at)']);
                    exit;
                }

                $docInfo = generate_and_store_wood($pdo, $approvalId, [
                    'no'        => $no,
                    'issued_on' => $issuedOn,
                    'issued_at' => $place,
                ]);
                $documentUrl = $docInfo['url'] ?? null;
            } elseif ($reqType === 'lumber') {
                $regNo    = trim((string)($_POST['lb_registration_no'] ?? ''));
                $issuedAt = trim((string)($_POST['lb_issued_at'] ?? ''));
                $bondAmt  = trim((string)($_POST['lb_perf_bond_amt'] ?? ''));
                $orNo     = trim((string)($_POST['lb_or_no'] ?? ''));
                $expiryOn = trim((string)($_POST['lb_expiry_on'] ?? ''));

                if ($regNo === '' || $issuedAt === '' || $bondAmt === '' || $orNo === '' || $expiryOn === '') {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Missing lumber details (registration_no, issued_at, perf_bond_amt, or_no, expiry_on)']);
                    exit;
                }

                $docInfo = generate_and_store_lumber($pdo, $approvalId, [
                    'registration_no' => $regNo,
                    'issued_at'       => $issuedAt,
                    'perf_bond_amt'   => $bondAmt,
                    'or_no'           => $orNo,
                    'expiry_on'       => $expiryOn,
                ]);
                $documentUrl = $docInfo['url'] ?? null;
            } elseif ($reqType === 'treecut') {
                $tcpNo      = trim((string)($_POST['tc_tcp_no']      ?? ''));
                $orNo       = trim((string)($_POST['tc_or_no']       ?? ''));
                $netHarvest = trim((string)($_POST['tc_net_harvest'] ?? ''));

                if ($tcpNo === '' || $orNo === '' || $netHarvest === '') {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Missing treecut details (tcp_no, or_no, net_harvest)']);
                    exit;
                }
                if (!is_numeric(preg_replace('/[^\d.]/', '', $netHarvest))) {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Net harvest volume must be a number.']);
                    exit;
                }

                $docInfo = generate_and_store_treecut($pdo, $approvalId, [
                    'tcp_no'      => $tcpNo,
                    'or_no'       => $orNo,
                    'net_harvest' => $netHarvest,
                ]);
                $documentUrl = $docInfo['url'] ?? null;
            } else {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Unsupported request type']);
                exit;
            }

            // Update status → RELEASED
            $pdo->prepare("
                UPDATE public.approval
                   SET approval_status = 'released',
                       approved_at     = now(),
                       approved_by     = :by
                 WHERE approval_id    = :aid
            ")->execute([
                ':by'  => (string)$_SESSION['user_id'],
                ':aid' => $approvalId
            ]);

            // Notify client (to = requester user_id)
            $pdo->prepare("
                INSERT INTO public.notifications (approval_id, message, is_read, created_at, \"from\", \"to\")
                VALUES (:aid, :msg, false, now(), :fromVal, :toVal)
            ")->execute([
                ':aid'     => $approvalId,
                ':msg'     => 'Your ' . $reqTypeLabel . ' permit is now released. You can download the file now',
                ':fromVal' => 'Tree Cutting',
                ':toVal'   => $toUserId,
            ]);

            $pdo->commit();
            echo json_encode(['ok' => true, 'status' => 'released', 'document_url' => $documentUrl]);
            exit;
        }

        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'unsupported action']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[TREE-DECIDE AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
}





/* ---------------- NOTIFS for header ---------------- */
$treeNotifs = [];
$unreadTree = 0;
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
        WHERE LOWER(COALESCE(n.\"to\", ''))='tree cutting'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $treeNotifs = $notifRows;

    $unreadPermits = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", ''))='tree cutting' AND n.is_read=false
    ")->fetchColumn();

    $unreadIncidents = (int)$pdo->query("
        SELECT COUNT(*) FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='tree cutting' AND is_read=false
    ")->fetchColumn();

    $unreadTree = $unreadPermits + $unreadIncidents;

    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE lower(COALESCE(category,''))='tree cutting'
        ORDER BY created_at DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[TREEHOME NOTIFS-FOR-NAV] ' . $e->getMessage());
    $treeNotifs = [];
    $unreadTree = 0;
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
        WHERE LOWER(COALESCE(a.request_type,'')) IN ('treecut','lumber','wood','chainsaw')
        ORDER BY a.submitted_at DESC NULLS LAST, a.approval_id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[TREE-ADMIN LIST] ' . $e->getMessage());
}

$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>








<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
    <title>Tree Cutting Approvals</title>

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

        .status-val {
            display: inline-block;
            font-weight: 600;
            color: #111827;
            background: transparent;
            padding: 0;
            border-radius: 0;
            line-height: 1.2;
            white-space: nowrap;
            min-width: 100px
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
            background: transparent;
            color: inherit;
            padding: 0;
            min-width: 0;
            border-radius: 0
        }

        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 120px
        }

        /* Modal / Drawer / Skeleton */
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

        .app-img {
            max-width: 100%;
            height: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px
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

        .dropdown-menu .dropdown-item {
            position: relative
        }

        .dropdown-menu .dropdown-item.active {
            background: #eef7ee;
            font-weight: 700;
            color: #1b5e20
        }

        .dropdown-menu .dropdown-item.active i {
            color: #1b5e20
        }

        .dropdown-menu .dropdown-item.active::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 8px;
            bottom: 8px;
            width: 4px;
            border-radius: 4px;
            background: #1b5e20
        }

        .empty {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 10px 0px;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo"><a href="treehome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <!-- App / hamburger -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <!-- ACTIVE on this page -->
                    <a href="treepermit.php" class="dropdown-item active" aria-current="page">
                        <i class="fas fa-file-signature"></i><span>Request Permits</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Bell -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadTree ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="treeNotifList">
                        <?php
                        $combined = [];

                        // Permits
                        foreach ($treeNotifs as $nf) {
                            $combined[] = [
                                'id'      => $nf['notif_id'],
                                'is_read' => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'type'    => 'permit',
                                'message' => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a request.')),
                                'ago'     => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'    => 'treepermit.php'
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
                                    <div class="notification-title">No tree cutting notifications</div>
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

                    <div class="notification-footer"><a href="reportaccident.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon <?php echo $current_page === 'forestry-profile' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>


    <div class="main-content">
        <section class="page-header">
            <div class="title-wrap">
                <h1>Permit Request Approvals</h1>
                <p class="subtitle">Requests of type <strong>treecut, lumber, wood, chainsaw</strong></p>
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
                            $cls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending');
                            $req = strtolower((string)($r['request_type'] ?? ''));
                        ?>
                            <tr data-approval-id="<?= h($r['approval_id']) ?>">
                                <td><?= h($r['first_name'] ?? '—') ?></td>
                                <td><span class="pill"><?= h($req) ?></span></td>
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
                        <p>No tree cutting requests yet.</p>
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

            <!-- Skeleton -->
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
                <button class="icon-btn" type="button" aria-label="Close preview" data-close-preview><i class="fas fa-times"></i></button>
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
            <h4 class="confirm-title">Approve this request?</h4>
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
            <h4 class="confirm-title">Reject this request?</h4>
            <label for="rejectReason" style="font-size:.9rem;color:#374151;">Reason for rejection</label>
            <textarea id="rejectReason" class="input-textarea" placeholder="Provide a short reason…" spellcheck="false"></textarea>
            <div class="confirm-actions">
                <button class="btn ghost" data-cancel-confirm>Cancel</button>
                <button class="btn danger" id="rejectConfirmBtn"><i class="fas fa-times"></i> Confirm</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>
    <div id="screenBlocker" class="blocker">
        <div class="lds"></div><span>Updating…</span>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* --------------------------- Mobile nav toggle --------------------------- */
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => navContainer.classList.toggle('active'));

            /* ------------------------------- Dropdowns ------------------------------- */
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
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(0)' :
                        'translateY(0)';
                };
                const close = () => {
                    dd.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(10px)' :
                        'translateY(10px)';
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
                            menu.style.transform = menu.classList.contains('center') ?
                                'translateX(-50%) translateY(10px)' :
                                'translateY(10px)';
                            if (isTouch) menu.style.display = 'none';
                        }
                    });
                }
            });

            /* ----------------------------- Toast/blocker ----------------------------- */
            function showToast(msg, type = 'success') {
                const t = document.getElementById('toast');
                if (!t) {
                    console.warn('toast element missing');
                    return;
                }
                t.textContent = msg;
                t.className = 'toast show ' + (type === 'error' ? 'error' : 'success');
                setTimeout(() => {
                    t.className = 'toast';
                    t.textContent = '';
                }, 2000);
            }
            const blocker = document.getElementById('screenBlocker');

            function block(on) {
                blocker?.classList.toggle('show', !!on);
            }

            /* --------------------------------- Filters -------------------------------- */
            const filterStatus = document.getElementById('filterStatus');
            const searchName = document.getElementById('searchName');
            const clearBtn = document.getElementById('btnClearFilters');
            const tableBody = document.getElementById('statusTableBody');
            const rowsCount = document.getElementById('rowsCount');

            function applyFilters() {
                const statusVal = (filterStatus?.value || '').toLowerCase();
                const nameVal = (searchName?.value || '').toLowerCase().trim();

                let visible = 0;
                tableBody?.querySelectorAll('tr').forEach(tr => {
                    const statusCell = tr.querySelector('.status-val');
                    const nameCell = tr.cells?.[0];
                    const statusTxt = (statusCell?.textContent || '').toLowerCase().trim();
                    const nameTxt = (nameCell?.textContent || '').toLowerCase().trim();

                    const statusOk = !statusVal || statusTxt === statusVal;
                    const nameOk = !nameVal || nameTxt.includes(nameVal);

                    const show = statusOk && nameOk;
                    tr.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                if (rowsCount) rowsCount.textContent = `${visible} results`;
            }

            filterStatus?.addEventListener('change', applyFilters);
            searchName?.addEventListener('input', applyFilters);
            clearBtn?.addEventListener('click', () => {
                if (filterStatus) filterStatus.value = '';
                if (searchName) searchName.value = '';
                applyFilters();
            });

            /* --------------------------------- Modals --------------------------------- */
            const modalEl = document.getElementById('viewModal');
            const modalSkeleton = document.getElementById('modalSkeleton');
            const modalContent = document.getElementById('modalContent');
            const metaStrip = document.getElementById('metaStrip');
            const modalActions = document.getElementById('modalActions');

            function showModalSkeleton() {
                modalSkeleton?.classList.remove('hidden');
                modalContent?.classList.add('hidden');
                metaStrip?.classList.add('hidden');
                modalActions?.classList.add('hidden');
            }

            function hideModalSkeleton() {
                modalSkeleton?.classList.add('hidden');
                metaStrip?.classList.remove('hidden');
                modalContent?.classList.remove('hidden');
                modalActions?.classList.remove('hidden');
            }

            function openViewModal() {
                modalEl?.classList.add('show');
            }

            function closeViewModal() {
                modalEl?.classList.remove('show');
                closePreview();
            }
            document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', closeViewModal));
            document.querySelector('.modal-backdrop')?.addEventListener('click', closeViewModal);

            /* ---------------------------- File preview drawer ---------------------------- */
            function closePreview() {
                const dr = document.getElementById('filePreviewDrawer');
                dr?.classList.remove('show');
                const img = document.getElementById('previewImage');
                const frm = document.getElementById('previewFrame');
                if (img) img.src = '';
                if (frm) frm.src = '';
            }

            function showPreview(name, url, ext) {
                const drawer = document.getElementById('filePreviewDrawer');
                const imgWrap = document.getElementById('previewImageWrap');
                const frameWrap = document.getElementById('previewFrameWrap');
                const linkWrap = document.getElementById('previewLinkWrap');
                const title = document.getElementById('previewTitle');

                if (title) title.textContent = name;
                imgWrap?.classList.add('hidden');
                frameWrap?.classList.add('hidden');
                linkWrap?.classList.add('hidden');

                const imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const offExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const txtExt = ['txt', 'csv', 'json', 'md', 'log'];
                if (imgExt.includes(ext) && url) {
                    const img = document.getElementById('previewImage');
                    if (img) img.src = url;
                    imgWrap?.classList.remove('hidden');
                } else if (ext === 'pdf' && url) {
                    const frm = document.getElementById('previewFrame');
                    if (frm) frm.src = url;
                    frameWrap?.classList.remove('hidden');
                } else if (offExt.includes(ext) && url) {
                    const viewer = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
                    const frm = document.getElementById('previewFrame');
                    if (frm) frm.src = viewer;
                    frameWrap?.classList.remove('hidden');
                } else if (txtExt.includes(ext) && url) {
                    const gview = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    const frm = document.getElementById('previewFrame');
                    if (frm) frm.src = gview;
                    frameWrap?.classList.remove('hidden');
                } else {
                    const a = document.getElementById('previewDownload');
                    if (a) a.href = url || '#';
                    linkWrap?.classList.remove('hidden');
                }
                drawer?.classList.add('show');
            }
            document.getElementById('filesList')?.addEventListener('click', (e) => {
                const li = e.target.closest('.file-item');
                if (!li) return;
                showPreview(
                    li.dataset.fileName || 'Document',
                    li.dataset.fileUrl || '#',
                    (li.dataset.fileExt || '').toLowerCase()
                );
            });
            document.querySelector('[data-close-preview]')?.addEventListener('click', closePreview);

            /* -------------------------- View row → load & render -------------------------- */
            let currentApprovalId = null;
            let currentRequestType = '';

            // 🔧 Robust delegated handler for various "view" buttons anywhere on the page
            document.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action="view"], .btn-view, [data-view], [data-role="view"]');
                if (!btn) return;

                // Find the nearest row and flexibly obtain the id
                const tr = btn.closest('tr');
                const rowId =
                    btn.dataset.approvalId ||
                    btn.dataset.id ||
                    tr?.dataset.approvalId ||
                    tr?.dataset.id;

                if (!rowId) {
                    console.warn('View button clicked but no approval id found on button/row.');
                    showToast('Missing record id on this row.', 'error');
                    return;
                }

                currentApprovalId = rowId;

                if (!modalEl) {
                    console.warn('#viewModal not found in DOM.');
                    showToast('View modal missing in this page.', 'error');
                    return;
                }

                // Reset placeholders and open modal immediately with skeleton
                showModalSkeleton();
                openViewModal();

                // Reset basics
                const metaClientName = document.getElementById('metaClientName');
                const metaRequestType = document.getElementById('metaRequestType');
                const metaPermitType = document.getElementById('metaPermitType');
                const metaStatus = document.getElementById('metaStatus');
                const appFieldsWrap = document.getElementById('applicationFields');
                const formEmpty = document.getElementById('formEmpty');
                const filesList = document.getElementById('filesList');
                const filesEmpty = document.getElementById('filesEmpty');

                if (metaClientName) metaClientName.textContent = '—';
                if (metaRequestType) metaRequestType.textContent = '—';
                if (metaPermitType) metaPermitType.textContent = '—';
                if (metaStatus) {
                    metaStatus.textContent = '—';
                    metaStatus.className = 'status-text';
                }
                if (appFieldsWrap) appFieldsWrap.innerHTML = '';
                formEmpty?.classList.add('hidden');
                if (filesList) filesList.innerHTML = '';
                filesEmpty?.classList.add('hidden');
                if (modalActions) modalActions.innerHTML = '';

                // Mark related notifs read (permits) & update bell
                try {
                    const resNotif = await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_notifs_for_approval', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            approval_id: currentApprovalId
                        }).toString()
                    }).then(r => r.json());

                    if (resNotif && resNotif.ok) {
                        const badge = document.querySelector('#notifDropdown .badge');
                        if (badge) {
                            const n = parseInt(badge.textContent || '0', 10) || 0;
                            const dec = parseInt(resNotif.count || 0, 10) || 0;
                            const next = Math.max(0, n - dec);
                            badge.textContent = String(next);
                            if (next <= 0) badge.style.display = 'none';
                        }
                        document.querySelectorAll('#treeNotifList .notification-item.unread').forEach(el => {
                            const a = el.querySelector('a.notification-link');
                            if (a && a.getAttribute('href') === 'treepermit.php') el.classList.remove('unread');
                        });
                    }
                } catch {}

                // Fetch details
                let res = null;
                try {
                    res = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(currentApprovalId)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }).then(r => r.json());
                } catch (err) {
                    res = {
                        ok: false,
                        error: String(err)
                    };
                }

                if (!res?.ok) {
                    hideModalSkeleton();
                    if (metaStatus) metaStatus.textContent = 'Error';
                    if (appFieldsWrap) appFieldsWrap.innerHTML = '';
                    formEmpty?.classList.remove('hidden');
                    if (filesList) filesList.innerHTML = '';
                    filesEmpty?.classList.remove('hidden');
                    showToast('Failed to load details.', 'error');
                    return;
                }

                const meta = res.meta || {};
                currentRequestType = (meta.request_type || '').toLowerCase();

                if (metaClientName) metaClientName.textContent = meta.client || '—';
                if (metaRequestType) metaRequestType.textContent = meta.request_type || '—';
                if (metaPermitType) metaPermitType.textContent = meta.permit_type || '—';

                const st = (meta.status || '').trim().toLowerCase();
                if (metaStatus) {
                    metaStatus.textContent = st ? st[0].toUpperCase() + st.slice(1) : '—';
                    metaStatus.className = 'status-text';
                }

                // Render application fields
                function isPlainObject(v) {
                    return v && typeof v === 'object' && !Array.isArray(v);
                }

                function renderJSON(value) {
                    let data = value;
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
                        const tbody = document.createElement('tbody');
                        data.forEach(row => {
                            const tr = document.createElement('tr');
                            keys.forEach(k => {
                                const td = document.createElement('td');
                                const cell = row[k];
                                td.textContent = (cell === null || cell === undefined) ? '' : (typeof cell === 'object' ? JSON.stringify(cell) : String(cell));
                                tr.appendChild(td);
                            });
                            tbody.appendChild(tr);
                        });
                        table.appendChild(tbody);
                        return table;
                    }
                    const pre = document.createElement('pre');
                    pre.className = 'json-pre';
                    try {
                        pre.textContent = JSON.stringify(data, null, 2);
                    } catch {
                        pre.textContent = typeof value === 'string' ? value : String(value);
                    }
                    return pre;
                }

                const appFields = Array.isArray(res.application) ? res.application : [];
                if (!appFields.length) {
                    formEmpty?.classList.remove('hidden');
                } else if (appFieldsWrap) {
                    appFields.forEach(field => {
                        const kind = (field.kind || 'text').toLowerCase();
                        const label = field.label || field.key || 'Field';
                        const value = field.value;

                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        dt.textContent = label;
                        const dd = document.createElement('dd');
                        if (kind === 'image' && typeof value === 'string') {
                            const img = document.createElement('img');
                            img.className = 'app-img';
                            img.src = value;
                            img.alt = label;
                            dd.appendChild(img);
                        } else if (kind === 'link' && typeof value === 'string') {
                            const a = document.createElement('a');
                            a.href = value;
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.textContent = value;
                            dd.appendChild(a);
                        } else if (kind === 'json') {
                            dd.appendChild(renderJSON(value));
                        } else {
                            dd.textContent = (value === null || value === undefined) ? '—' : String(value);
                        }
                        row.appendChild(dt);
                        row.appendChild(dd);
                        appFieldsWrap.appendChild(row);
                    });
                }

                // Render files
                const files = Array.isArray(res.files) ? res.files : [];
                const filesUL = document.getElementById('filesList');
                const filesEmptyEl = document.getElementById('filesEmpty');
                if (filesUL) filesUL.innerHTML = '';
                if (!files.length) {
                    filesEmptyEl?.classList.remove('hidden');
                } else if (filesUL) {
                    files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.dataset.fileName = f.name || 'Document';
                        li.dataset.fileUrl = f.url || '#';
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="fas fa-file"></i><span class="name">${f.name || 'Document'}</span><span class="hint">${(f.ext || '').toUpperCase()}</span>`;
                        filesUL.appendChild(li);
                    });
                }

                hideModalSkeleton();
                renderActions(st); // pending -> approve/reject; for payment -> release
            });

            /* ----------------------- Approve/Reject/Release actions ----------------------- */
            const approveConfirmEl = document.getElementById('approveConfirm');
            const rejectConfirmEl = document.getElementById('rejectConfirm');
            const approveWrap = approveConfirmEl; // alias used by ensure* helpers

            function renderActions(statusLower) {
                if (!modalActions) return;
                modalActions.innerHTML = '';
                modalActions.classList.remove('hidden');

                // PENDING → Approve / Reject
                if (statusLower === 'pending') {
                    const approveBtn = document.createElement('button');
                    approveBtn.className = 'btn success';
                    approveBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
                    approveBtn.addEventListener('click', () => {
                        if (!approveConfirmEl) return;
                        approveConfirmEl.dataset.mode = 'approve';
                        const title = approveConfirmEl.querySelector('.confirm-title');
                        if (title) title.textContent = 'Mark this request as FOR PAYMENT?';
                        const p = approveConfirmEl.querySelector('.confirm-panel p');
                        if (p) p.textContent = 'This will set the status to “For payment” and notify the client.';
                        clearConfirmForm();
                        approveConfirmEl.querySelector('#approveConfirmBtn')?.classList.remove('primary-alt');
                        approveConfirmEl.querySelector('#approveConfirmBtn')?.classList.add('primary');
                        const btn = approveConfirmEl.querySelector('#approveConfirmBtn');
                        if (btn) btn.textContent = 'Confirm';
                        approveConfirmEl.classList.add('show');
                    });

                    const rejectBtn = document.createElement('button');
                    rejectBtn.className = 'btn danger';
                    rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
                    rejectBtn.addEventListener('click', () => {
                        const rr = document.getElementById('rejectReason');
                        if (rr) rr.value = '';
                        rejectConfirmEl?.classList.add('show');
                    });

                    modalActions.appendChild(approveBtn);
                    modalActions.appendChild(rejectBtn);
                    return;
                }

                // FOR PAYMENT → Release (open the modal with dynamic inputs)
                if (statusLower === 'for payment') {
                    const releaseBtn = document.createElement('button');
                    releaseBtn.className = 'btn success';
                    releaseBtn.innerHTML = '<i class="fas fa-box-open"></i> Release';
                    releaseBtn.addEventListener('click', () => openReleaseModal());
                    modalActions.appendChild(releaseBtn);
                    return;
                }
            }

            // Close confirm modals (cancel/backdrop)
            approveConfirmEl?.addEventListener('click', (e) => {
                if (e.target.matches('[data-cancel-confirm], .confirm-backdrop')) {
                    approveConfirmEl.classList.remove('show');
                    setApproveError('');
                    clearConfirmForm();
                }
            });
            rejectConfirmEl?.addEventListener('click', (e) => {
                if (e.target.matches('[data-cancel-confirm], .confirm-backdrop')) {
                    rejectConfirmEl.classList.remove('show');
                }
            });

            /* ---------------------------- Helpers for release UI ---------------------------- */
            function todayLocalISO() {
                const d = new Date();
                const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                return local.toISOString().slice(0, 10);
            }

            function addYearsISO(isoYYYYMMDD, years) {
                if (!isoYYYYMMDD) return todayLocalISO();
                const d = new Date(isoYYYYMMDD + 'T00:00:00');
                d.setFullYear(d.getFullYear() + (Number(years) || 0));
                const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                return local.toISOString().slice(0, 10);
            }

            function setApproveError(msg) {
                let err = document.getElementById('approveInlineError');
                if (!err) return;
                err.textContent = msg || '';
                err.style.display = msg ? 'block' : 'none';
            }

            function clearConfirmForm() {
                approveWrap?.querySelectorAll('.confirm-form').forEach(n => n.remove());
                setApproveError('');
            }

            /* === Wood inputs (with Expiry auto) === */
            function ensureWoodInputs() {
                const panel = approveWrap?.querySelector('.confirm-panel');
                if (!panel) return;
                if (panel.querySelector('#wNo')) return; // already injected

                let err = document.getElementById('approveInlineError');
                if (!err) {
                    err = document.createElement('div');
                    err.id = 'approveInlineError';
                    err.setAttribute('role', 'alert');
                    err.style.cssText =
                        'display:none;background:#fde8e8;color:#991b1b;border:1px solid #f5c2c2;padding:8px 10px;border-radius:8px;';
                    panel.insertBefore(err, panel.querySelector('.confirm-actions'));
                }

                const blockNode = document.createElement('div');
                blockNode.className = 'confirm-form';
                blockNode.style.display = 'grid';
                blockNode.style.gridTemplateColumns = '1fr 1fr';
                blockNode.style.gap = '12px';
                blockNode.style.marginTop = '8px';

                const today = todayLocalISO();
                const expires = addYearsISO(today, 2);

                blockNode.innerHTML = `
      <p style="grid-column:1/-1;margin:0 0 4px 0;font-weight:600">
        Enter details for <span style="color:#1b5e20">Wood Processing Plant Permit.</span>
      </p>

      <div>
        <label for="wNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Permit No.</label>
        <input id="wNo" class="input" type="text" placeholder="e.g., WPP-2025-001">
      </div>

      <div>
        <label for="wIssuedOn" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Issued on</label>
        <input id="wIssuedOn" class="input" type="date" value="${today}">
        <div style="font-size:.85rem;color:#6b7280;margin-top:6px;">Expiry is set 2 years after this date.</div>
      </div>

      <div>
        <label for="wExpiryOn" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Expires on</label>
        <input id="wExpiryOn" class="input" type="date" value="${expires}" readonly>
      </div>

      <div>
        <label for="wIssuedAt" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Issued at (City/Office)</label>
        <input id="wIssuedAt" class="input" type="text" placeholder="e.g., Argao, Cebu">
        <div style="font-size:.85rem;color:#6b7280;margin-top:6px;">Will render as: <em>at &lt;value&gt; Philippines</em>.</div>
      </div>
    `;
                panel.insertBefore(blockNode, panel.querySelector('.confirm-actions'));

                const issuedEl = blockNode.querySelector('#wIssuedOn');
                const expEl = blockNode.querySelector('#wExpiryOn');
                issuedEl.addEventListener('input', () => {
                    expEl.value = addYearsISO(issuedEl.value || today, 2);
                    setApproveError('');
                });
                blockNode.querySelectorAll('input').forEach(i => {
                    i.addEventListener('input', () => setApproveError(''));
                });
            }

            /* === Chainsaw inputs (with Expiry) === */
            function ensureChainsawInputs() {
                const panel = approveWrap?.querySelector('.confirm-panel');
                if (!panel) return;
                if (panel.querySelector('#csCertNo')) return;

                let err = document.getElementById('approveInlineError');
                if (!err) {
                    err = document.createElement('div');
                    err.id = 'approveInlineError';
                    err.setAttribute('role', 'alert');
                    err.style.cssText =
                        'display:none;background:#fde8e8;color:#991b1b;border:1px solid #f5c2c2;padding:8px 10px;border-radius:8px;';
                    err.textContent = '';
                    panel.insertBefore(err, panel.querySelector('.confirm-actions'));
                }

                const blockNode = document.createElement('div');
                blockNode.className = 'confirm-form';
                blockNode.style.display = 'grid';
                blockNode.style.gridTemplateColumns = '1fr 1fr';
                blockNode.style.gap = '12px';
                blockNode.style.marginTop = '8px';

                const today = todayLocalISO();
                const expires = addYearsISO(today, 2);

                blockNode.innerHTML = `
      <p style="grid-column:1/-1;margin:0 0 4px 0;font-weight:600">
        Enter details for <span style="color:#1b5e20">Chainsaw Certificate of Registration.</span>
      </p>

      <div>
        <label for="csCertNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Certificate No.</label>
        <input id="csCertNo" class="input" type="text" placeholder="e.g., 12345">
      </div>

      <div>
        <label for="csOrNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">O. R. No.</label>
        <input id="csOrNo" class="input" type="text" placeholder="e.g., 987654">
      </div>

      <div>
        <label for="csIssuedOn" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Issued on</label>
        <input id="csIssuedOn" class="input" type="date" value="${today}">
        <div style="font-size:.85rem;color:#6b7280;margin-top:6px;">Expiry will be 2 years from this date.</div>
      </div>

      <div>
        <label for="csExpiryOn" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Expires on</label>
        <input id="csExpiryOn" class="input" type="date" value="${expires}" readonly>
      </div>

      <div style="grid-column:1/-1;">
        <label for="csOrDate" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Date Issued (for O.R.)</label>
        <input id="csOrDate" class="input" type="date" value="${today}">
      </div>

      <div>
        <label for="csPlace" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Place of issue</label>
        <input id="csPlace" class="input" type="text" placeholder="e.g., CENRO Argao, Cebu" value="CENRO Argao, Cebu">
      </div>
    `;
                panel.insertBefore(blockNode, panel.querySelector('.confirm-actions'));

                const issuedEl = blockNode.querySelector('#csIssuedOn');
                const expEl = blockNode.querySelector('#csExpiryOn');
                issuedEl.addEventListener('input', () => {
                    expEl.value = addYearsISO(issuedEl.value || today, 2);
                    setApproveError('');
                });
                blockNode.querySelectorAll('input').forEach(i => {
                    i.addEventListener('input', () => setApproveError(''));
                });
            }

            /* === Treecut inputs === */
            function ensureTreecutInputs() {
                const modal = document.getElementById('approveConfirm');
                if (!modal) return false;

                const panel = modal.querySelector('.confirm-panel') || modal;

                if (panel.querySelector('#tcTcpNo')) return true;

                let err = panel.querySelector('#approveInlineError');
                if (!err) {
                    err = document.createElement('div');
                    err.id = 'approveInlineError';
                    err.setAttribute('role', 'alert');
                    err.style.cssText = [
                        'display:none',
                        'background:#fde8e8',
                        'color:#991b1b',
                        'border:1px solid #f5c2c2',
                        'padding:8px 10px',
                        'border-radius:8px',
                        'margin-bottom:8px'
                    ].join(';');

                    const actions = panel.querySelector('.confirm-actions');
                    if (actions) panel.insertBefore(err, actions);
                    else panel.appendChild(err);
                }

                const block = document.createElement('div');
                block.className = 'confirm-form treecut-fields';
                block.style.display = 'grid';
                block.style.gridTemplateColumns = '1fr 1fr';
                block.style.gap = '12px';
                block.style.marginTop = '8px';

                const today = (() => {
                    const d = new Date();
                    const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                    return local.toISOString().slice(0, 10);
                })();

                block.innerHTML = `
      <p style="grid-column:1/-1;margin:0 0 6px 0;font-weight:600">
        Enter details for <span style="color:#1b5e20">Tree Cutting Permit</span>.
      </p>

      <div>
        <label for="tcTcpNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">TCP No. *</label>
        <input id="tcTcpNo" class="input" type="text" placeholder="e.g., C-Argao-2025-001" required>
      </div>

      <div>
        <label for="tcOrNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">O.R. No. *</label>
        <input id="tcOrNo" class="input" type="text" placeholder="e.g., 3114270" required>
      </div>

      <div>
        <label for="tcNetHarvest" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Net harvest volume (cu.m) *</label>
        <input id="tcNetHarvest" class="input" type="number" min="0" step="0.01" inputmode="decimal" placeholder="e.g., 14.91" required>
        <small style="display:block;color:#6b7280;margin-top:4px;">Use decimals only (e.g., 14.91)</small>
      </div>

      <div>
        <label style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Date Issued (auto)</label>
        <input class="input" type="date" value="${today}" readonly>
      </div>
    `;

                const actions = panel.querySelector('.confirm-actions');
                if (actions) panel.insertBefore(block, actions);
                else panel.appendChild(block);

                block.querySelectorAll('input').forEach((i) => {
                    i.addEventListener('input', () => {
                        err.textContent = '';
                        err.style.display = 'none';
                    });
                });

                block.validateTreecut = function() {
                    const tcp = block.querySelector('#tcTcpNo')?.value.trim();
                    const orno = block.querySelector('#tcOrNo')?.value.trim();
                    const netv = block.querySelector('#tcNetHarvest')?.value.trim();

                    if (!tcp || !orno || !netv) {
                        err.textContent = 'Please fill in TCP No., O.R. No., and Net harvest volume.';
                        err.style.display = 'block';
                        return false;
                    }
                    if (isNaN(Number(netv))) {
                        err.textContent = 'Net harvest volume must be a number.';
                        err.style.display = 'block';
                        return false;
                    }
                    return true;
                };

                return true;
            }

            /* === Lumber inputs === */
            function ensureLumberInputs() {
                const panel = document.getElementById('approveConfirm')?.querySelector('.confirm-panel');
                if (!panel || panel.querySelector('#lbIssuedAt')) return;

                let err = document.getElementById('approveInlineError');
                if (!err) {
                    err = document.createElement('div');
                    err.id = 'approveInlineError';
                    err.setAttribute('role', 'alert');
                    err.style.cssText = 'display:none;background:#fde8e8;color:#991b1b;border:1px solid #f5c2c2;padding:8px 10px;border-radius:8px;';
                    panel.insertBefore(err, panel.querySelector('.confirm-actions'));
                }

                const block = document.createElement('div');
                block.className = 'confirm-form';
                block.style.display = 'grid';
                block.style.gridTemplateColumns = '1fr 1fr';
                block.style.gap = '12px';
                block.style.marginTop = '8px';

                const today = todayLocalISO();
                const expiry = addYearsISO(today, 2);

                block.innerHTML = `
      <p style="grid-column:1/-1;margin:0 0 4px 0;font-weight:600">
        Enter details for <span style="color:#1b5e20">Lumber Dealer Certificate</span>.
      </p>

      <div>
        <label for="lbRegistrationNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Registration No. *</label>
        <input id="lbRegistrationNo" class="input" type="text" placeholder="e.g., LMB-2025-001" required>
      </div>

      <div>
        <label for="lbIssuedAt" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Issued at (address) *</label>
        <input id="lbIssuedAt" class="input" type="text" placeholder="e.g., Argao, Cebu" required>
        <div style="font-size:.85rem;color:#6b7280;margin-top:6px;">The certificate will show: issued ${today} at &lt;value&gt;.</div>
      </div>

      <div>
        <label for="lbPerfBondAmt" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Performance Bond Amount (₱) *</label>
        <input id="lbPerfBondAmt" class="input" type="number" min="0" step="0.01" placeholder="e.g., 1000" required>
      </div>

      <div>
        <label for="lbOrNo" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">O.R. No. *</label>
        <input id="lbOrNo" class="input" type="text" placeholder="e.g., 3114270" required>
      </div>

      <div>
        <label for="lbExpiryOn" style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Expiry (auto)</label>
        <input id="lbExpiryOn" class="input" type="date" value="${expiry}" readonly>
        <div style="font-size:.85rem;color:#6b7280;margin-top:6px;">Auto-set to 2 years from today.</div>
      </div>

      <div>
        <label style="display:block;font-size:.9rem;color:#374151;margin-bottom:4px;">Date (auto)</label>
        <input class="input" type="date" value="${today}" readonly>
      </div>
    `;
                panel.insertBefore(block, panel.querySelector('.confirm-actions'));

                block.querySelectorAll('input').forEach(i => {
                    i.addEventListener('input', () => {
                        const el = document.getElementById('approveInlineError');
                        if (el) {
                            el.textContent = '';
                            el.style.display = 'none';
                        }
                    });
                });
            }

            /* --------------------------- Approve & Reject flows --------------------------- */
            document.getElementById('approveConfirmBtn')?.addEventListener('click', async () => {
                if (!currentApprovalId) return;

                const mode = approveConfirmEl?.dataset.mode || 'approve';

                // ---------- APPROVE -> move to "for payment"
                if (mode === 'approve') {
                    approveConfirmEl?.classList.remove('show');
                    try {
                        block(true);
                        const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=decide', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new URLSearchParams({
                                approval_id: currentApprovalId,
                                action: 'approve'
                            }).toString()
                        }).then(r => r.json());

                        if (!res || res.ok !== true) throw new Error(res?.error || 'Update failed');

                        const ms = document.getElementById('metaStatus');
                        if (ms) {
                            ms.textContent = 'For payment';
                            ms.className = 'status-text';
                        }

                        const row = document.querySelector(`tr[data-approval-id="${CSS.escape(currentApprovalId)}"], tr[data-id="${CSS.escape(currentApprovalId)}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.status-val');
                            if (statusCell) {
                                statusCell.textContent = 'For payment';
                                statusCell.className = 'status-val';
                            }
                        }

                        renderActions('for payment');
                        showToast('Requirement approved', 'success');
                    } catch (err) {
                        console.error(err);
                        showToast('Failed to update status.', 'error');
                    } finally {
                        block(false);
                    }
                    return;
                }

                // ---------- RELEASE -> generate file + update to "released"
                if (mode === 'release') {
                    const payload = collectReleasePayload();
                    if (!payload) return; // validation already shown
                    approveConfirmEl?.classList.remove('show');
                    clearConfirmForm();
                    setApproveError('');
                    if (typeof window.treeReleaseSubmit === 'function') {
                        window.treeReleaseSubmit(payload);
                    } else {
                        showToast('Release handler not found.', 'error');
                    }
                }
            });

            // Reject → POST decide=reject with reason
            document.getElementById('rejectConfirmBtn')?.addEventListener('click', async () => {
                if (!currentApprovalId) return;
                const reason = (document.getElementById('rejectReason')?.value || '').trim();
                if (!reason) {
                    showToast('Please enter a reason.', 'error');
                    return;
                }
                rejectConfirmEl?.classList.remove('show');

                try {
                    block(true);
                    const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=decide', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            approval_id: currentApprovalId,
                            action: 'reject',
                            reason
                        }).toString()
                    }).then(r => r.json());

                    if (!res || res.ok !== true) throw new Error(res?.error || 'Update failed');

                    const ms = document.getElementById('metaStatus');
                    if (ms) {
                        ms.textContent = 'Rejected';
                        ms.className = 'status-text';
                    }

                    const row = document.querySelector(`tr[data-approval-id="${CSS.escape(currentApprovalId)}"], tr[data-id="${CSS.escape(currentApprovalId)}"]`);
                    if (row) {
                        const statusCell = row.querySelector('.status-val');
                        if (statusCell) {
                            statusCell.textContent = 'Rejected';
                            statusCell.className = 'status-val rejected';
                        }
                    }

                    modalActions.innerHTML = '';
                    showToast('Rejected successfully & client notified.', 'success');
                } catch (err) {
                    console.error(err);
                    showToast('Failed to reject.', 'error');
                } finally {
                    block(false);
                }
            });

            /* --------------------------- Release modal + submit --------------------------- */
            function openReleaseModal() {
                if (!approveConfirmEl) return;
                approveConfirmEl.dataset.mode = 'release';

                const title = approveConfirmEl.querySelector('.confirm-title');
                if (title) title.textContent = 'Release this request?';

                const p = approveConfirmEl.querySelector('.confirm-panel p');
                if (p) p.textContent = 'Fill in the details for the generated file, then confirm to release.';

                clearConfirmForm();

                if (currentRequestType === 'wood') ensureWoodInputs();
                else if (currentRequestType === 'chainsaw') ensureChainsawInputs();
                else if (currentRequestType === 'treecut') ensureTreecutInputs();
                else if (currentRequestType === 'lumber') ensureLumberInputs();

                const btn = approveConfirmEl.querySelector('#approveConfirmBtn');
                if (btn) {
                    btn.textContent = 'Confirm & Release';
                    btn.classList.remove('primary');
                    btn.classList.add('primary-alt');
                }

                approveConfirmEl.classList.add('show');
            }

            function collectReleasePayload() {
                const type = currentRequestType;
                const need = (cond, msg) => {
                    if (!cond) {
                        setApproveError(msg);
                        return false;
                    }
                    setApproveError('');
                    return true;
                };

                if (type === 'wood') {
                    const no = document.getElementById('wNo')?.value.trim();
                    const issued_on = document.getElementById('wIssuedOn')?.value;
                    const issued_at = document.getElementById('wIssuedAt')?.value.trim();
                    if (!need(no, 'Permit No. is required.')) return null;
                    if (!need(issued_on, 'Issued on is required.')) return null;
                    if (!need(issued_at, 'Issued at is required.')) return null;
                    return {
                        w_no: no,
                        w_issued_on: issued_on,
                        w_issued_at: issued_at
                    };
                }

                if (type === 'chainsaw') {
                    const cert = document.getElementById('csCertNo')?.value.trim();
                    const orno = document.getElementById('csOrNo')?.value.trim();
                    const issued_on = document.getElementById('csIssuedOn')?.value;
                    const or_date = document.getElementById('csOrDate')?.value;
                    const place = document.getElementById('csPlace')?.value.trim();
                    if (!need(cert, 'Certificate No. is required.')) return null;
                    if (!need(orno, 'O.R. No. is required.')) return null;
                    if (!need(issued_on, 'Issued on is required.')) return null;
                    if (!need(or_date, 'Date Issued (O.R.) is required.')) return null;
                    if (!need(place, 'Place of issue is required.')) return null;
                    return {
                        cs_cert_no: cert,
                        cs_or_no: orno,
                        cs_issued_on: issued_on,
                        cs_or_date: or_date,
                        cs_place: place
                    };
                }

                if (type === 'treecut') {
                    const tcp = document.getElementById('tcTcpNo')?.value.trim();
                    const orno = document.getElementById('tcOrNo')?.value.trim();
                    const net = document.getElementById('tcNetHarvest')?.value.trim();
                    if (!need(tcp && orno && net, 'Please fill in TCP No., O.R. No., and Net harvest volume.')) return null;
                    if (!need(!isNaN(Number(net)), 'Net harvest volume must be a number.')) return null;
                    return {
                        tc_tcp_no: tcp,
                        tc_or_no: orno,
                        tc_net_harvest: net
                    };
                }

                if (type === 'lumber') {
                    const reg = document.getElementById('lbRegistrationNo')?.value.trim();
                    const issued_at = document.getElementById('lbIssuedAt')?.value.trim();
                    const bond = document.getElementById('lbPerfBondAmt')?.value.trim();
                    const orno = document.getElementById('lbOrNo')?.value.trim();
                    const expiry = document.getElementById('lbExpiryOn')?.value;
                    if (!need(reg, 'Registration No. is required.')) return null;
                    if (!need(issued_at, 'Issued at is required.')) return null;
                    if (!need(bond && !isNaN(Number(bond)), 'Performance Bond Amount must be provided.')) return null;
                    if (!need(orno, 'O.R. No. is required.')) return null;
                    if (!need(expiry, 'Expiry date is required.')) return null;
                    return {
                        lb_registration_no: reg,
                        lb_issued_at: issued_at,
                        lb_perf_bond_amt: bond,
                        lb_or_no: orno,
                        lb_expiry_on: expiry
                    };
                }

                setApproveError('Unsupported request type.');
                return null;
            }

            /* --------------------------- Release submission hook --------------------------- */
            window.treeReleaseSubmit = async function(payload) {
                if (!currentApprovalId) {
                    showToast('No record selected.', 'error');
                    return;
                }

                try {
                    block(true);

                    const params = new URLSearchParams();
                    params.set('approval_id', currentApprovalId);
                    params.set('action', 'release');
                    if (payload && typeof payload === 'object') {
                        Object.entries(payload).forEach(([k, v]) => {
                            if (v !== undefined && v !== null) params.append(k, String(v));
                        });
                    }

                    const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=decide', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: params.toString()
                    }).then(r => r.json());

                    if (!res || res.ok !== true) throw new Error(res?.error || 'Release failed');

                    const ms = document.getElementById('metaStatus');
                    if (ms) {
                        ms.textContent = 'Released';
                        ms.className = 'status-text';
                    }

                    const row = document.querySelector(`tr[data-approval-id="${CSS.escape(currentApprovalId)}"], tr[data-id="${CSS.escape(currentApprovalId)}"]`);
                    if (row) {
                        const statusCell = row.querySelector('.status-val');
                        if (statusCell) {
                            statusCell.textContent = 'Released';
                            statusCell.className = 'status-val approved';
                        }
                    }

                    if (modalActions) modalActions.innerHTML = '';
                    showToast('Permit released', 'success');

                    // If your backend returns the file url: if (res.document_url) window.open(res.document_url, '_blank');
                } catch (err) {
                    console.error(err);
                    showToast(err.message || 'Failed to release.', 'error');
                } finally {
                    block(false);
                }
            };

            // Initial filter count compute
            applyFilters();
        });
    </script>






</body>




</html>