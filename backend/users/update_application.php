<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/connection.php';

const DEFAULT_REQUIREMENTS_BUCKET = 'requirements';

function bucket_name(): string
{
    if (defined('REQUIREMENTS_BUCKET') && REQUIREMENTS_BUCKET) {
        return REQUIREMENTS_BUCKET;
    }
    if (defined('SUPABASE_BUCKET') && SUPABASE_BUCKET) {
        return SUPABASE_BUCKET;
    }
    return DEFAULT_REQUIREMENTS_BUCKET;
}

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
    $data = @file_get_contents($tmpPath);
    if ($data === false) {
        throw new Exception('Failed to read uploaded file.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
        CURLOPT_POSTFIELDS     => $data,
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

function wordHeaderStylesLumber(): string
{
    return "
      body, div, p { line-height:1.8; font-family: Arial; font-size:11pt; margin:0; padding:0; }
      .section-title { font-weight:700; margin:15pt 0 6pt 0; text-decoration:underline; }
      .info-line { margin:12pt 0; }
      .underline { display:inline-block; min-width:300px; border-bottom:1px solid #000; padding:0 5px; margin:0 5px; }
      .declaration { margin-top:15pt; }
      .signature-line { margin-top:36pt; border-top:1px solid #000; width:50%; padding-top:3pt; }
      .header-container { position:relative; margin-bottom:20px; width:100%; }
      .header-logo { width:80px; height:80px; }
      .header-content { text-align:center; margin:0 auto; width:100%; }
      .header-content p { margin:0; padding:0; }
      .bold { font-weight:700; }
      .suppliers-table { width:100%; border-collapse:collapse; margin:15px 0; }
      .suppliers-table th, .suppliers-table td { border:1px solid #000; padding:8px; text-align:left; }
      .suppliers-table th { background:#f2f2f2; }
    ";
}

function escLumber(string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function suppliersTableHTMLLumber(array $suppliers): string
{
    $body = '';
    if (count($suppliers) > 0) {
        foreach ($suppliers as $s) {
            $name = escLumber($s['name'] ?? '');
            $volume = escLumber($s['volume'] ?? '');
            $body .= "<tr><td>{$name}</td><td>{$volume}</td></tr>";
        }
    } else {
        $body = "<tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
    }
    return "
    <table class=\"suppliers-table\">
      <tr><th>SUPPLIERS NAME/COMPANY</th><th>VOLUME</th></tr>
      {$body}
    </table>";
}

function buildNewDocHTMLLumber(string $logoHref, string $sigHref, array $F, array $suppliers): string
{
    $notStr = ($F['govEmp'] ?? '') === 'yes' ? '' : 'not';
    $fullName = escLumber($F['fullName'] ?? '');
    $age = escLumber($F['applicantAge'] ?? '');
    $address = escLumber($F['businessAddress'] ?? '');
    $opPlace = $F['operationPlace'] ?? '';
    $annVolume = $F['annualVolume'] ?? '';
    $annWorth = $F['annualWorth'] ?? '';
    $empCount = $F['employeesCount'] ?? '';
    $depCount = $F['dependentsCount'] ?? '';
    $market = $F['intendedMarket'] ?? '';
    $exp = $F['experience'] ?? '';
    $declName = $F['declarationName'] ?? '';
    $suppliers_table = suppliersTableHTMLLumber($suppliers);
    $headerStyles = wordHeaderStylesLumber();
    $sigHTML = $sigHref
        ? "<!--[if gte mso 9]><v:shape style=\"width:300px;height:110px;visibility:visible;mso-wrap-style:square\" stroked=\"f\" type=\"#_x0000_t75\"><v:imagedata src=\"{$sigHref}\" o:title=\"Signature\"/></v:shape><![endif]-->\n          <!--[if !mso]><!-- --><img src=\"{$sigHref}\" width=\"300\" height=\"110\" alt=\"Signature\"/><!--<![endif]-->\n          <p>Signature of Applicant</p>"
        : "<div class=\"signature-line\"></div>\n          <p>Signature of Applicant</p>";

    return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Lumber Dealer Permit Application</title>
  <style>{$headerStyles}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="{$logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="{$logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">NEW LUMBER DEALER PERMIT APPLICATION</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">{$fullName}</span>, <span class="underline">{$age}</span> years old, with business address at <span class="underline">{$address}</span>, hereby apply for registration as a Lumber Dealer.</p>

  <p class="info-line">1. I am {$notStr} a government employee and have {$notStr} received any compensation from the government.</p>

  <p class="info-line">2. Proposed place of operation: <span class="underline">{$opPlace}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">{$annVolume}</span> worth <span class="underline">{$annWorth}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">{$empCount}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">{$depCount}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  {$suppliers_table}

  <p class="info-line">6. Intended market (barangays and municipalities to be served): <span class="underline">{$market}</span></p>

  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">{$exp}</span></p>

  <p class="info-line">8. I will fully comply with Republic Act No. 123G and the rules and regulations of the Forest Management Bureau.</p>

  <p class="info-line">9. I understand that false statements or omissions may result in:</p>
  <ul style="margin-left:40px;">
    <li>Disapproval of this application</li>
    <li>Cancellation of registration</li>
    <li>Forfeiture of bond</li>
    <li>Criminal liability</li>
  </ul>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">{$declName}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      {$sigHTML}
    </div>
  </div>
</body>
</html>
HTML;
}

function buildRenewalDocHTMLLumber(string $logoHref, string $sigHref, array $F, array $suppliers): string
{
    $notStr = ($F['govEmp'] ?? '') === 'yes' ? '' : 'not';
    $fullName = escLumber($F['fullName'] ?? '');
    $age = escLumber($F['applicantAge'] ?? '');
    $address = escLumber($F['businessAddress'] ?? '');
    $opPlace = $F['operationPlace'] ?? '';
    $annVolume = $F['annualVolume'] ?? '';
    $annWorth = $F['annualWorth'] ?? '';
    $empCount = $F['employeesCount'] ?? '';
    $depCount = $F['dependentsCount'] ?? '';
    $market = $F['intendedMarket'] ?? '';
    $exp = $F['experience'] ?? '';
    $prevCert = $F['prevCert'] ?? '';
    $issuedDate = $F['issuedDate'] ?? '';
    $expiryDate = $F['expiryDate'] ?? '';
    $crLicense = $F['crLicense'] ?? '';
    $sawmillPermit = $F['sawmillPermit'] ?? '';
    $buyingOther = strtoupper($F['buyingOther'] ?? '');
    $declName = $F['declarationName'] ?? '';
    $suppliers_table = suppliersTableHTMLLumber($suppliers);
    $headerStyles = wordHeaderStylesLumber();
    $sigHTML = $sigHref
        ? "<!--[if gte mso 9]><v:shape style=\"width:300px;height:110px;visibility:visible;mso-wrap-style:square\" stroked=\"f\" type=\"#_x0000_t75\"><v:imagedata src=\"{$sigHref}\" o:title=\"Signature\"/></v:shape><![endif]-->\n          <!--[if !mso]><!-- --><img src=\"{$sigHref}\" width=\"300\" height=\"110\" alt=\"Signature\"/><!--<![endif]-->\n          <p>Signature of Applicant</p>"
        : "<div class=\"signature-line\"></div>\n          <p>Signature of Applicant</p>";

    return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="UTF-8">
  <title>Renewal of Lumber Dealer Permit</title>
  <style>{$headerStyles}</style>
  <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->
</head>
<body>
  <div class="header-container">
    <div style="text-align:center;">
      <!--[if gte mso 9]><v:shape id="Logo" style="width:80px;height:80px;visibility:visible;mso-wrap-style:square" stroked="f" type="#_x0000_t75"><v:imagedata src="{$logoHref}" o:title="Logo"/></v:shape><![endif]-->
      <!--[if !mso]><!-- --><img class="header-logo" src="{$logoHref}" alt="Logo"/><!--<![endif]-->
    </div>
    <div class="header-content">
      <p class="bold">Republic of the Philippines</p>
      <p class="bold">Department of Environment and Natural Resources</p>
      <p>Community Environment and Natural Resources Office (CENRO)</p>
      <p>Argao, Cebu</p>
    </div>
  </div>

  <h2 style="text-align:center;margin-bottom:20px;">RENEWAL OF LUMBER DEALER PERMIT</h2>

  <p class="info-line">The CENR Officer<br>Argao, Cebu</p>
  <p class="info-line">Sir:</p>

  <p class="info-line">I/We, <span class="underline">{$fullName}</span>, <span class="underline">{$age}</span> years old, with business address at <span class="underline">{$address}</span>, hereby apply for <b>renewal</b> of registration as a Lumber Dealer.</p>

  <p class="info-line">1. I am {$notStr} a government employee and have {$notStr} received any compensation from the government.</p>

  <p class="info-line">2. Place of operation: <span class="underline">{$opPlace}</span></p>

  <p class="info-line">3. Expected gross annual volume of business: <span class="underline">{$annVolume}</span> worth <span class="underline">{$annWorth}</span></p>

  <p class="info-line">4. Total number of employees: <span class="underline">{$empCount}</span></p>
  <p class="info-line" style="margin-left:20px;">Total number of dependents: <span class="underline">{$depCount}</span></p>

  <p class="info-line">5. List of Suppliers and Corresponding Volume</p>
  {$suppliers_table}

  <p class="info-line">6. Selling products to: <span class="underline">{$market}</span></p>
  <p class="info-line">7. My experience as a lumber dealer: <span class="underline">{$exp}</span></p>

  <p class="info-line">8. Previous Certificate of Registration No.: <span class="underline">{$prevCert}</span></p>
  <p class="info-line" style="margin-left:20px;">Issued On: <span class="underline">{$issuedDate}</span> &nbsp; Expires On: <span class="underline">{$expiryDate}</span></p>

  <p class="info-line">9. C.R. License No.: <span class="underline">{$crLicense}</span> &nbsp;&nbsp; Sawmill Permit No.: <span class="underline">{$sawmillPermit}</span></p>
  <p class="info-line">10. Buying logs/lumber from other sources: <span class="underline">{$buyingOther}</span></p>

  <div style="margin-top:40px;">
    <p>AFFIDAVIT OF TRUTH</p>
    <p>I, <span class="underline">{$declName}</span>, after being sworn to upon my oath, depose and say that I have read the foregoing application and that every statement therein is true and correct to the best of my knowledge and belief.</p>

    <div style="margin-top:60px;">
      {$sigHTML}
    </div>
  </div>
</body>
</html>
HTML;
}

function clean_field_name(string $field): string
{
    return preg_replace('/[^a-z0-9_]+/i', '_', trim($field));
}

function generate_storage_path(string $requestType, string $approvalId, string $originalName): string
{
    $base = $requestType !== '' ? $requestType : 'requests';
    $base = preg_replace('/[^a-z0-9_-]+/i', '-', $base);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = $ext ? '.' . $ext : '';
    $stamp = date('Ymd_His');
    $rand = substr(bin2hex(random_bytes(4)), 0, 8);
    return "{$base}/updates/{$approvalId}/{$stamp}_{$rand}{$ext}";
}

function letter_escape(string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function parse_storage_reference(string $value): ?array
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (preg_match('~^https?://[^/]+/storage/v1/object/(?:public/)?([^/]+)/(.+)$~i', $value, $matches)) {
        return [
            'bucket' => rawurldecode($matches[1]),
            'path' => rawurldecode($matches[2]),
        ];
    }
    $clean = ltrim($value, '/');
    $parts = explode('/', $clean, 2);
    if (count($parts) === 2) {
        return [
            'bucket' => rawurldecode($parts[0]),
            'path' => rawurldecode($parts[1]),
        ];
    }
    return null;
}

function supa_upload_binary(string $bucket, string $path, string $mime, string $binary): string
{
    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
        CURLOPT_POSTFIELDS     => $binary,
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

function supa_delete_object(string $bucket, string $path): void
{
    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception('Storage delete failed: ' . $err);
    }
    if ($code >= 300 && $code !== 404) {
        throw new Exception("Storage delete failed ({$code}): " . ($resp ?: 'unknown'));
    }
}

function regenerate_chainsaw_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, string $permitType, string $oldUrl): ?string
{
    $clientId = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    $permitType = strtolower(trim($permitType));
    if ($clientId === '' || $applicationId === '' || $requirementId === '') return null;

    // Fetch client
    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) throw new Exception('Client data missing for regenerated chainsaw application doc.');

    // Fetch application form
    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) throw new Exception('Application form data missing for regenerated chainsaw document.');
    $app = array_change_key_case($app, CASE_LOWER);

    // Build name and address
    $first = trim((string)($app['first_name'] ?? $client['first_name'] ?? ''));
    $middle = trim((string)($app['middle_name'] ?? $client['middle_name'] ?? ''));
    $last = trim((string)($app['last_name'] ?? $client['last_name'] ?? ''));
    $fullName = trim(preg_replace('/\s+/', ' ', ($first . ' ' . $middle . ' ' . $last)));

    $sitio = letter_escape($app['sitio_street'] ?? $client['sitio_street'] ?? '');
    $barangay = letter_escape($app['barangay'] ?? $client['barangay'] ?? '');
    $municipality = letter_escape($app['municipality'] ?? $client['municipality'] ?? '');
    $province = letter_escape($app['province'] ?? $client['city'] ?? $app['province'] ?? '');
    $addrParts = array_filter([$app['present_address'] ?? '', $sitio, $barangay, $municipality, $province]);
    $addressLine = letter_escape(implode(', ', $addrParts));

    $contact = letter_escape($app['contact_number'] ?? $client['contact_number'] ?? '');

    // Chainsaw-specific fields
    $permitNo = letter_escape($app['permit_number'] ?? $app['previous_permit_number'] ?? '');
    $issuance = letter_escape($app['issuance_date'] ?? $app['date_issued'] ?? '');
    $expiry = letter_escape($app['expiry_date'] ?? $app['expires_on'] ?? '');
    $purpose = letter_escape($app['purpose'] ?? '');
    $brand = letter_escape($app['brand'] ?? '');
    $model = letter_escape($app['model'] ?? '');
    $dateAcq = letter_escape($app['date_of_acquisition'] ?? $app['acquisition_date'] ?? '');
    $serial = letter_escape($app['serial_number'] ?? '');
    $hp = letter_escape($app['horsepower'] ?? '');
    $bar = letter_escape($app['maximum_length_of_guide_bar'] ?? $app['guide_bar_length'] ?? '');

    // Signature
    $sigValue = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);

    // Build doc HTML similar to client-side template
    $titleLine = $permitType === 'renewal' ? 'Application for Renewal of Chainsaw Permit' : 'Application for New Chainsaw Permit';
    // Use CID reference so embedded image in MHTML is displayed by Word/Outlook
    $signatureBlock = $sigBase64 ? '<div style="margin-top:28px;"><img src="cid:sigimg" width="300" height="110" style="display:block;border:1px solid #ddd;padding:4px;border-radius:4px;" alt="Signature"/><p style="margin-top:6px;">Signature of Applicant</p></div>' : '<div style="margin-top:40px;"><div style="border-top:1px solid #000;width:50%;padding-top:3pt;"></div><p>Signature of Applicant</p></div>';

    $docHTML = '<!DOCTYPE html><html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><title>Chainsaw Registration Form</title></head><body>';
    $docHTML .= '<div style="text-align:center"><p><b>Republic of the Philippines</b></p><p><b>Department of Environment and Natural Resources</b></p><p>Community Environment and Natural Resources Office (CENRO)</p><p>Argao, Cebu</p></div>';
    $docHTML .= '<h3 style="text-align:center;">' . $titleLine . '</h3>';
    $docHTML .= '<p><b>I. APPLICANT INFORMATION</b></p>';
    $docHTML .= '<p>Name: <u>' . letter_escape($fullName) . '</u></p>';
    $docHTML .= '<p>Address: <u>' . $addressLine . '</u></p>';
    $docHTML .= '<p>Contact Number: <u>' . $contact . '</u></p>';
    if ($permitType === 'renewal') {
        $docHTML .= '<p><b>II. EXISTING CHAINSAW PERMIT INFORMATION</b></p>';
        $docHTML .= '<p>Permit Number: <u>' . $permitNo . '</u></p>';
        $docHTML .= '<p>Date of Original Issuance: <u>' . $issuance . '</u></p>';
        $docHTML .= '<p>Expiry Date: <u>' . $expiry . '</u></p>';
    }
    $docHTML .= '<p><b>' . ($permitType === 'renewal' ? 'III' : 'II') . '. CHAINSAW INFORMATION AND DESCRIPTION</b></p>';
    $docHTML .= '<p>Purpose of Use: <u>' . $purpose . '</u></p>';
    $docHTML .= '<p>Brand: <u>' . $brand . '</u></p>';
    $docHTML .= '<p>Model: <u>' . $model . '</u></p>';
    $docHTML .= '<p>Date of Acquisition: <u>' . $dateAcq . '</u></p>';
    $docHTML .= '<p>Serial Number: <u>' . $serial . '</u></p>';
    $docHTML .= '<p>Horsepower: <u>' . $hp . '</u></p>';
    $docHTML .= '<p>Maximum Length of Guide Bar: <u>' . $bar . '</u></p>';
    $docHTML .= '<p><b>' . ($permitType === 'renewal' ? 'IV' : 'III') . '. DECLARATION AND SUBMISSION</b></p>';
    $docHTML .= $signatureBlock;
    $docHTML .= '</body></html>';

    // Build MHTML
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $docHTML . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///signature.png\r\n\r\n";
    if ($sigBase64) {
        $mhtml .= chunk_split($sigBase64, 76, "\r\n");
    } else {
        $mhtml .= chunk_split('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 76, "\r\n");
    }
    $mhtml .= "\r\n--$boundary--";

    // Upload using chainsaw prefix similar to save_chainsaw.php
    $bucket = bucket_name();
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $permitFolder = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "chainsaw/{$permitFolder}/{$clientId}/{$run}/";

    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'Applicant');
    $shortId = substr($clientId, 0, 8);
    $ymd = date('Ymd');
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
    $newUrl = '';
    $attempt = 0;
    while (true) {
        $fname = ($permitType === 'renewal' ? 'Chainsaw_Renewal' : 'Chainsaw_New') . "_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = $prefix . $fname;
        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    // Update DB
    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    // Delete old file if different
    if ($oldUrl !== '') {
        $oldInfo = parse_storage_reference($oldUrl);
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old chainsaw application_form object: ' . $deleteErr->getMessage());
            }
        }
    }

    return $newUrl;
}

function fetch_storage_object_base64(string $bucket, string $path): string
{
    $encoded = encode_path_segments($path);
    $url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/{$bucket}/{$encoded}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'apikey: ' . SUPABASE_SERVICE_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 300) {
        return '';
    }
    return base64_encode($resp);
}

function base64_from_data_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (str_starts_with($value, 'data:image/')) {
        $parts = explode(',', $value, 2);
        return $parts[1] ?? '';
    }
    return '';
}

function signature_base64_from_value(?string $value): string
{
    $val = trim((string)$value);
    if ($val === '') {
        return '';
    }
    $b64 = base64_from_data_url($val);
    if ($b64 !== '') {
        return $b64;
    }
    $info = parse_storage_reference($val);
    if (!$info) {
        return '';
    }
    return fetch_storage_object_base64($info['bucket'], $info['path']);
}

function parse_deleted_seedling_entry(string $entry): ?array
{
    $candidate = json_decode($entry, true);
    if (is_array($candidate)) {
        return $candidate;
    }
    $parts = explode(':', $entry);
    if (count($parts) >= 2) {
        return [
            'seedl_req_id' => trim($parts[0]),
            'seedlings_id' => trim($parts[1]),
            'batch_key'    => '',
        ];
    }
    return null;
}

function seedling_group_key(string $batchKey, string $seedlReqId): string
{
    if ($batchKey !== '') {
        return 'batch:' . $batchKey;
    }
    if ($seedlReqId !== '') {
        return 'seedl:' . $seedlReqId;
    }
    return 'batch:';
}

function select_seedling_group_anchor(PDO $pdo, string $seedlReqId, string $batchKey, array $excludeReqIds): ?string
{
    $seedlReqId = trim($seedlReqId);
    if ($seedlReqId === '') {
        return null;
    }
    $params = [':sid' => $seedlReqId];
    if ($batchKey !== '') {
        $sql = 'SELECT seedl_req_id FROM public.seedling_requests WHERE (batch_key = :batch_key OR seedl_req_id = :sid) ORDER BY seedl_req_id';
        $params[':batch_key'] = $batchKey;
    } else {
        $sql = 'SELECT seedl_req_id FROM public.seedling_requests WHERE seedl_req_id = :sid ORDER BY seedl_req_id';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($rows as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '' || isset($excludeReqIds[$candidate])) {
            continue;
        }
        return $candidate;
    }
    return null;
}

function ensure_seedling_rows_remain(PDO $pdo, ?string $clientId, array $deleteGroups, array $insertCounts): void
{
    if (!$clientId) {
        return;
    }
    $stmtByBatch = $pdo->prepare('SELECT COUNT(1) FROM public.seedling_requests WHERE batch_key = :batch_key AND client_id = :cid');
    $stmtBySeedl = $pdo->prepare('SELECT COUNT(1) FROM public.seedling_requests WHERE seedl_req_id = :sid AND client_id = :cid');
    foreach ($deleteGroups as $groupKey => $meta) {
        $batchKey = (string)($meta['batch_key'] ?? '');
        $seedlReqId = (string)($meta['seedl_req_id'] ?? '');
        $deleteCount = (int)($meta['delete_count'] ?? 0);
        if ($deleteCount <= 0) {
            continue;
        }
        if ($batchKey !== '') {
            $stmtByBatch->execute([':batch_key' => $batchKey, ':cid' => $clientId]);
            $existing = (int)$stmtByBatch->fetchColumn();
        } elseif ($seedlReqId !== '') {
            $stmtBySeedl->execute([':sid' => $seedlReqId, ':cid' => $clientId]);
            $existing = (int)$stmtBySeedl->fetchColumn();
        } else {
            continue;
        }
        $added = $insertCounts[$groupKey] ?? 0;
        if ($existing - $deleteCount + $added <= 0) {
            throw new Exception('At least one seedling must remain for this request.');
        }
    }
}

function build_seedling_letter(array $client, string $sigB64, string $purpose, array $seedlings, array $catalog, string $requestDate): array
{
    $toTitle = static function (?string $value): string {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords(strtolower($value));
    };

    $first = $toTitle($client['first_name'] ?? '');
    $middle = $toTitle($client['middle_name'] ?? '');
    $last = $toTitle($client['last_name'] ?? '');
    $sitio = $toTitle($client['sitio_street'] ?? '');
    $brgy = $toTitle($client['barangay'] ?? '');
    $muni = $toTitle($client['municipality'] ?? '');
    $city = $toTitle($client['city'] ?? '');
    $org = trim((string)($client['organization'] ?? ''));

    $lgu = $city ?: $muni;
    $addressParts = [];
    if ($sitio) $addressParts[] = $sitio;
    if ($brgy) $addressParts[] = 'Brgy. ' . $brgy;
    if ($lgu) $addressParts[] = $lgu;
    $addressLine = implode(', ', $addressParts);
    $cityProv = ($lgu ? ($lgu . ', ') : '') . 'Cebu';
    $fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
    $prettyDate = $requestDate ? date('F j, Y', strtotime($requestDate)) : date('F j, Y');

    $totalQty = 0;
    $seedTxts = [];
    foreach ($seedlings as $row) {
        $qty = (int)($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $name = $catalog[$row['seedlings_id']]['seedling_name'] ?? 'Seedling';
        $seedTxts[] = letter_escape($name) . ' (' . $qty . ')';
        $totalQty += $qty;
    }
    if ($totalQty === 0) {
        $seedTxts[] = 'Seedlings';
    }
    $seedTxt = implode(', ', $seedTxts);

    $inner = '
        <p style="text-align:right;">' . letter_escape($addressLine) . '<br>' . letter_escape($cityProv) . '<br>' . letter_escape($prettyDate) . '</p>
        <p><strong>CENRO Argao</strong></p>
        <p><strong>Subject: Request for Seedlings</strong></p>
        <p>Dear Sir/Madam,</p>
        <p style="text-align:justify;text-indent:50px;">I am writing to formally request ' . $totalQty . ' seedlings of ' . ($seedTxt ?: 'seedlings') . ' for ' . letter_escape($purpose) . '. The seedlings will be planted at ' . letter_escape($addressLine ?: $cityProv) . '.</p>
        <p style="text-align:justify;text-indent:50px;">The purpose of this request is ' . letter_escape($purpose) . '.</p>
        <p style="text-align:justify;text-indent:50px;">I would be grateful if you could approve this request at your earliest convenience.</p>
        <p style="text-align:justify;text-indent:50px;">Thank you for your time and consideration.</p>
        <p>Sincerely,<br><br>
            <img src="cid:sigimg" width="140" height="25" style="height:auto;border:1px solid #ccc;"><br>
            ' . letter_escape($fullName) . '<br>' . letter_escape($addressLine ?: $cityProv) . '<br>' . letter_escape($org) . '
        </p>
    ';

    $htmlDoc = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seedling Request Letter</title><style>body{font-family:Arial,sans-serif;line-height:1.6;margin:50px;color:#111}</style></head><body>' . $inner . '</body></html>';

    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $htmlDoc . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///sig.png\r\n\r\n";
    $mhtml .= chunk_split($sigB64, 76, "\r\n") . "\r\n--$boundary--";
    return [$mhtml, $fullName];
}

function regenerate_seedling_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, ?string $seedlReqId, string $oldUrl): void
{
    $clientId = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    $seedlReqId = trim((string)$seedlReqId);
    if ($clientId === '' || $applicationId === '' || $requirementId === '' || $seedlReqId === '') {
        return;
    }

    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        throw new Exception('Client data missing for regenerated application doc.');
    }

    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        throw new Exception('Application form data missing for regenerated document.');
    }

    $batchStmt = $pdo->prepare('SELECT batch_key FROM public.seedling_requests WHERE seedl_req_id = :sid LIMIT 1');
    $batchStmt->execute([':sid' => $seedlReqId]);
    $batchKey = trim((string)($batchStmt->fetchColumn() ?: ''));

    $seedSql = '
        SELECT sr.seedl_req_id, sr.seedlings_id, COALESCE(sr.quantity, 0) AS qty, s.seedling_name
        FROM public.seedling_requests sr
        JOIN public.seedlings s ON s.seedlings_id = sr.seedlings_id
        WHERE ';
    if ($batchKey !== '') {
        $seedSql .= '(sr.batch_key = :batch_key OR sr.seedl_req_id = :sid)';
    } else {
        $seedSql .= 'sr.seedl_req_id = :sid';
    }
    $seedSql .= ' ORDER BY s.seedling_name';
    $seedStmt = $pdo->prepare($seedSql);
    $seedParams = [':sid' => $seedlReqId];
    if ($batchKey !== '') {
        $seedParams[':batch_key'] = $batchKey;
    }
    $seedStmt->execute($seedParams);
    $seedRows = $seedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$seedRows) {
        throw new Exception('No seedlings found for regenerated document.');
    }

    $seedlings = [];
    $catalog = [];
    foreach ($seedRows as $row) {
        $sid = (string)($row['seedlings_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $qty = (int)($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $seedlings[] = [
            'seedlings_id' => $sid,
            'qty' => $qty,
        ];
        if (!isset($catalog[$sid])) {
            $catalog[$sid] = ['seedling_name' => (string)($row['seedling_name'] ?? 'Seedling')];
        }
    }
    if (!$seedlings) {
        throw new Exception('Seedling quantities are required to regenerate the document.');
    }

    $sigValue = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);
    $purpose = trim((string)($app['purpose_of_use'] ?? ''));
    $requestDate = trim((string)($app['date'] ?? $app['date_today'] ?? ''));
    if ($requestDate === '') {
        $requestDate = date('Y-m-d');
    }

    $clientForDoc = [
        'first_name' => $client['first_name'] ?? '',
        'middle_name' => $client['middle_name'] ?? '',
        'last_name' => $client['last_name'] ?? '',
        'sitio_street' => $client['sitio_street'] ?? '',
        'barangay' => $client['barangay'] ?? '',
        'municipality' => $client['municipality'] ?? '',
        'city' => $client['city'] ?? '',
        'organization' => $app['company_name'] ?? '',
    ];

    [$mhtml, $fullNameForFile] = build_seedling_letter($clientForDoc, $sigBase64, $purpose, $seedlings, $catalog, $requestDate);

    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'Letter');
    $shortId = substr($clientId, 0, 8);
    $ts = strtotime($requestDate);
    $ymd = $ts !== false ? date('Ymd', $ts) : date('Ymd');
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
    $bucket = bucket_name();
    $newUrl = '';
    $attempt = 0;
    while (true) {
        $fname = "Seedling_Request_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = "seedling/{$clientId}/{$fname}";
        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    if ($oldUrl !== '') {
        $oldInfo = parse_storage_reference($oldUrl);
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old application_form object: ' . $deleteErr->getMessage());
            }
        }
    }
}

function regenerate_lumber_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, string $permitType, string $oldUrl): void
{
    $clientId = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    $permitType = strtolower(trim($permitType));

    if ($clientId === '' || $applicationId === '' || $requirementId === '') {
        return;
    }

    // Fetch client data
    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        throw new Exception('Client data missing for regenerated lumber application doc.');
    }

    // Fetch application form data
    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        throw new Exception('Application form data missing for regenerated lumber document.');
    }

    // Convert app columns to lowercase for consistent access
    $app = array_change_key_case($app, CASE_LOWER);

    // Build form data array for document generation
    $fullName = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
    $F = [
        'fullName' => $fullName,
        'applicantAge' => $app['applicant_age'] ?? '',
        'businessAddress' => $app['business_address'] ?? '',
        'govEmp' => $app['is_government_employee'] ?? 'no',
        'operationPlace' => $app['proposed_place_of_operation'] ?? '',
        'annualVolume' => $app['expected_annual_volume'] ?? '',
        'annualWorth' => $app['estimated_annual_worth'] ?? '',
        'employeesCount' => $app['total_number_of_employees'] ?? '',
        'dependentsCount' => $app['total_number_of_dependents'] ?? '',
        'intendedMarket' => $app['intended_market'] ?? '',
        'experience' => $app['my_experience_as_alumber_dealer'] ?? '',
        'declarationName' => $app['declaration_name'] ?? $fullName,
        'prevCert' => $app['prev_certificate_no'] ?? '',
        'issuedDate' => $app['issued_date'] ?? '',
        'expiryDate' => $app['expiry_date'] ?? '',
        'crLicense' => $app['cr_license_no'] ?? '',
        'sawmillPermit' => $app['sawmill_permit_no'] ?? '',
        'buyingOther' => $app['buying_from_other_sources'] ?? '',
    ];

    // Parse suppliers from suppliers_json
    $suppliers = [];
    if (!empty($app['suppliers_json'])) {
        $decoded = json_decode($app['suppliers_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $s) {
                if (is_array($s) && !empty($s['name'])) {
                    $suppliers[] = [
                        'name' => $s['name'] ?? '',
                        'volume' => $s['volume'] ?? '',
                    ];
                }
            }
        }
    }

    // Get signature if available
    $sigValue = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);

    // Generate document HTML
    $isRenewal = $permitType === 'renewal';
    $docHTML = $isRenewal
        ? buildRenewalDocHTMLLumber('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', '', $F, $suppliers)
        : buildNewDocHTMLLumber('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', '', $F, $suppliers);

    // Create MIME HTML document
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $docHTML . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///sig.png\r\n\r\n";
    if ($sigBase64) {
        $mhtml .= chunk_split($sigBase64, 76, "\r\n");
    } else {
        $mhtml .= chunk_split('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 76, "\r\n");
    }
    $mhtml .= "\r\n--$boundary--";

    // Upload to storage
    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'Form');
    $shortId = substr($clientId, 0, 8);
    $ymd = date('Ymd');
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
    $bucket = bucket_name();
    $newUrl = '';
    $attempt = 0;

    while (true) {
        $fname = ($isRenewal ? 'Lumber_Renewal' : 'Lumber_New') . "_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = "lumber/{$clientId}/{$fname}";
        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    // Update the application_form URL in the database
    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    // Delete old file if it exists and is different
    if ($oldUrl !== '') {
        $oldInfo = parse_storage_reference($oldUrl);
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old lumber application_form object: ' . $deleteErr->getMessage());
            }
        }
    }
}

function regenerate_treecut_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, string $oldUrl): void
{
    $clientId = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    if ($clientId === '' || $applicationId === '' || $requirementId === '') {
        return;
    }

    // Fetch client data
    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        throw new Exception('Client data missing for regenerated treecut application doc.');
    }

    // Fetch application form data
    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        throw new Exception('Application form data missing for regenerated treecut document.');
    }
    $app = array_change_key_case($app, CASE_LOWER);

    // Collect fields used by the treecut template
    $first = trim((string)($client['first_name'] ?? ''));
    $middle = trim((string)($client['middle_name'] ?? ''));
    $last = trim((string)($client['last_name'] ?? ''));
    $applicantName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);

    $street = letter_escape($app['sitio_street'] ?? $client['sitio_street'] ?? '');
    $barangay = letter_escape($app['barangay'] ?? $client['barangay'] ?? '');
    $municipality = letter_escape($app['municipality'] ?? $client['municipality'] ?? '');
    $province = letter_escape($app['province'] ?? 'Cebu');

    $contact = letter_escape($app['contact_number'] ?? $client['contact_number'] ?? '');
    $email = letter_escape($app['email'] ?? '');
    $regno = letter_escape($app['registration_number'] ?? '');

    $location = letter_escape($app['location'] ?? '');
    $purpose = letter_escape($app['purpose'] ?? '');
    $taxDecl = letter_escape($app['tax_declaration'] ?? '');
    $lotNo = letter_escape($app['lot_no'] ?? '');
    $contAr = letter_escape($app['contained_area'] ?? '');
    $ownership = letter_escape($app['ownership'] ?? '');

    // species rows stored as JSON in application form or additional_information
    $species = [];
    if (!empty($app['species_rows_json'])) {
        $decoded = json_decode((string)$app['species_rows_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                if (!is_array($entry)) continue;
                $species[] = [
                    'name' => letter_escape(trim((string)($entry['name'] ?? ''))),
                    'count' => letter_escape(trim((string)($entry['count'] ?? ''))),
                    'volume' => letter_escape(trim((string)($entry['volume'] ?? ''))),
                ];
            }
        }
    }

    // If not found in dedicated column, try to get from additional_information JSON blob
    if (empty($species) && !empty($app['additional_information'])) {
        $additionalInfo = json_decode((string)$app['additional_information'], true);
        if (is_array($additionalInfo) && isset($additionalInfo['species_rows'])) {
            $speciesRaws = $additionalInfo['species_rows'];
            if (is_array($speciesRaws)) {
                foreach ($speciesRaws as $entry) {
                    if (!is_array($entry)) continue;
                    $species[] = [
                        'name' => letter_escape(trim((string)($entry['name'] ?? ''))),
                        'count' => letter_escape(trim((string)($entry['count'] ?? ''))),
                        'volume' => letter_escape(trim((string)($entry['volume'] ?? ''))),
                    ];
                }
            }
        }
    }

    $totalCount = trim((string)($app['total_count'] ?? $app['totalcount'] ?? ''));
    $totalVol = trim((string)($app['total_volume'] ?? $app['totalvolume'] ?? ''));
    // If totals are not present, compute from species rows
    if ($totalCount === '' || $totalVol === '') {
        $computedCount = 0;
        $computedVol = 0.0;
        foreach ($species as $s) {
            $computedCount += intval(str_replace(',', '', (string)($s['count'] ?? '0')));
            $computedVol += floatval(str_replace(',', '', (string)($s['volume'] ?? '0')));
        }
        if ($totalCount === '') $totalCount = (string)$computedCount;
        if ($totalVol === '') $totalVol = number_format($computedVol, 2, '.', '');
    }
    $totalCount = letter_escape($totalCount);
    $totalVol = letter_escape($totalVol);

    $sigValue = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);

    // Build HTML similar to client-side template
    $headerStyles = 'body,div,p,td{font-family:Arial,sans-serif;font-size:11pt;margin:0;line-height:1.5;padding:0;} .underline{text-decoration:underline;} table{border-collapse:collapse;width:100%;} table.bordered-table{border:1px solid #000;} table.bordered-table td,table.bordered-table th{border:1px solid #000;padding:5px;vertical-align:top;} .text-center{text-align:center;} .section-title{margin:15pt 0 6pt 0;font-weight:bold;} .signature-line{margin-top:24pt;border-top:1px solid #000;width:50%;padding-top:3pt;}';

    $speciesRowsHtml = '';
    foreach ($species as $s) {
        $n = $s['name'] ?? '';
        $c = $s['count'] ?? '';
        $v = $s['volume'] ?? '';
        $speciesRowsHtml .= "<tr><td>" . ($n) . "</td><td>" . ($c) . "</td><td>" . ($v) . "</td></tr>";
    }

    $sigBlock = $sigBase64 ? "<img src=\"signature.png\" width=\"300\" height=\"110\" style=\"display:block;margin:8px 0 6px 0;border:1px solid #000;\" alt=\"Signature\">" : '';

    $docHTML = '<!DOCTYPE html>' .
        '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">' .
        '<head><meta charset="UTF-8"><title>Application for Tree Cutting Permit</title>' .
        '<style>' . $headerStyles . '</style><!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]--></head>' .
        '<body>' .
        '<div class="text-center"><p>Republic of the Philippines</p><p>Department of Environment and Natural Resources (DENR)</p>' .
        '<p>Community Environment and Natural Resources Office (CENRO)</p><p>Lamacan, Argao, Cebu, Philippines 6021</p>' .
        '<p>Tel. Nos. (+6332) 4600-711 | E-mail: <span class="underline">cenroargao@denr.gov.ph</span></p></div>' .
        '<h3 class="text-center">APPLICATION FOR TREE CUTTING PERMIT</h3>' .
        '<p class="section-title">PART I. APPLICANT\'S INFORMATION</p>' .
        '<p>1. Applicant: ' . letter_escape($applicantName) . '</p>' .
        '<p>2. Address:</p>' .
        '<p>&nbsp;&nbsp;&nbsp;&nbsp;Sitio/Street: ' . $street . '</p>' .
        '<p>&nbsp;&nbsp;&nbsp;&nbsp;Barangay: ' . $barangay . '</p>' .
        '<p>&nbsp;&nbsp;&nbsp;&nbsp;Municipality: ' . $municipality . '</p>' .
        '<p>&nbsp;&nbsp;&nbsp;&nbsp;Province: ' . $province . '</p>' .
        '<p>3. Contact No.: ' . $contact . '</p>' .
        '<p>4. Email Address: ' . $email . '</p>' .
        '<p>5. If Corporation: SEC/DTI Registration No. ' . $regno . '</p>' .
        '<p class="section-title">PART II. TREE CUTTING DETAILS</p>' .
        '<p>1. Location of Area/Trees to be Cut: ' . $location . '</p>' .
        '<p>2. Ownership of Land: ' . $ownership . '</p>' .
        '<p style="margin-left:18px;">Tax Declaration No.: ' . $taxDecl . ' &nbsp;|&nbsp; Lot No.: ' . $lotNo . ' &nbsp;|&nbsp; Contained Area: ' . $contAr . '</p>' .
        '<p>3. Number and Species of Trees Applied for Cutting:</p>' .
        '<table class="bordered-table"><tr><th>Species</th><th>No. of Trees</th><th>Net Volume (cu.m)</th></tr>' .
        $speciesRowsHtml .
        '<tr><td><strong>TOTAL</strong></td><td><strong>' . $totalCount . '</strong></td><td><strong>' . $totalVol . '</strong></td></tr></table>' .
        '<p>4. Purpose of Application for Tree Cutting Permit:</p><p>' . $purpose . '</p>' .
        '<p class="section-title">PART III. DECLARATION OF APPLICANT</p>' .
        '<p>I hereby certify that the information provided in this application is true and correct. I understand that the approval of this application is subject to verification and evaluation by DENR, and that I shall comply with all terms and conditions of the Tree Cutting Permit once issued.</p>' .
        $sigBlock .
        '<div class="signature-line">Signature Over Printed Name</div>' .
        '</body></html>';

    // Build MHTML
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $docHTML . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///signature.png\r\n\r\n";
    if ($sigBase64) {
        $mhtml .= chunk_split($sigBase64, 76, "\r\n");
    } else {
        $mhtml .= chunk_split('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 76, "\r\n");
    }
    $mhtml .= "\r\n--$boundary--";

    // Upload
    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'TreeCut');
    $shortId = substr($clientId, 0, 8);
    $ymd = date('Ymd');
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
    $bucket = bucket_name();
    $newUrl = '';
    $attempt = 0;
    while (true) {
        $fname = "TreeCut_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = "treecut/{$clientId}/{$fname}";
        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    if ($oldUrl !== '') {
        $oldInfo = parse_storage_reference($oldUrl);
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old treecut application_form object: ' . $deleteErr->getMessage());
            }
        }
    }
}

function buildNewDocHTMLWood(array $F, string $machineryRowsHTML, string $supplyRowsHTML, string $sigBlock): string
{
    $applicantName = letter_escape($F['applicantName'] ?? '');
    $businessAddress = letter_escape($F['businessAddress'] ?? '');
    $plantLocation = letter_escape($F['plantLocation'] ?? '');
    $contactNumber = letter_escape($F['contactNumber'] ?? '');
    $emailAddress = letter_escape($F['emailAddress'] ?? '');
    $ownershipType = letter_escape($F['ownershipType'] ?? '');
    $plantType = letter_escape($F['plantType'] ?? '');
    $dailyCapacity = letter_escape($F['dailyCapacity'] ?? '');
    $powerSource = letter_escape($F['powerSource'] ?? '');
    $declarationName = letter_escape($F['declarationName'] ?? $applicantName);
    $declarationAddress = letter_escape($F['declarationAddress'] ?? '');

    return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align:center; margin-bottom: 20px;">Application for Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">{$applicantName}</span></p>
  <p class="info-line">Complete Business Address: <span class="underline">{$businessAddress}</span></p>
  <p class="info-line">Plant Location (Barangay/Municipality/Province): <span class="underline">{$plantLocation}</span></p>
  <p class="info-line">Contact Number(s): <span class="underline">{$contactNumber}</span> Email Address: <span class="underline">{$emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">{$ownershipType}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">{$plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">{$dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      {$machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">{$powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      {$supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION AND SIGNATURE</p>
  <div class="declaration">
    <p>I, <span class="underline">{$declarationName}</span>, of legal age, a citizen of the Philippines, with residence at <span class="underline">{$declarationAddress}</span>, do hereby certify that the foregoing information and documents are true and correct to the best of my knowledge.</p>
    <p>I further understand that any false statement or misrepresentation shall be ground for denial, cancellation, or revocation of the permit, without prejudice to legal actions that may be filed against me.</p>
    <div style="margin-top: 16px;">
      {$sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>
HTML;
}

function buildRenewalDocHTMLWood(array $F, string $machineryRowsHTML, string $supplyRowsHTML, string $sigBlock): string
{
    $applicantName = letter_escape($F['applicantName'] ?? '');
    $address = letter_escape($F['address'] ?? '');
    $plantLocation = letter_escape($F['plantLocation'] ?? '');
    $contactNumber = letter_escape($F['contactNumber'] ?? '');
    $emailAddress = letter_escape($F['emailAddress'] ?? '');
    $ownershipType = letter_escape($F['ownershipType'] ?? '');
    $previousPermit = letter_escape($F['previousPermit'] ?? '');
    $expiryDate = letter_escape($F['expiryDate'] ?? '');
    $plantType = letter_escape($F['plantType'] ?? '');
    $dailyCapacity = letter_escape($F['dailyCapacity'] ?? '');
    $powerSource = letter_escape($F['powerSource'] ?? '');
    $declarationName = letter_escape($F['declarationName'] ?? $applicantName);

    return <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:w="urn:schemas-microsoft-com:office:word" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Renewal of Wood Processing Plant Permit Application</title>
<style>
  body, div, p { line-height: 1.8; font-family: Arial; font-size: 11pt; margin: 0; padding: 0; }
  .section-title { font-weight: normal; margin: 15pt 0 6pt 0; }
  .info-line { margin: 12pt 0; }
  .underline { display: inline-block; min-width: 300px; border-bottom: 1px solid #000; padding: 0 5px; margin: 0 5px; }
  .bold { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin: 12pt 0; }
  table, th, td { border: 1px solid #000; }
  th, td { padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .signature-line { margin-top: 12pt; border-top: 1px solid #000; width: 50%; padding-top: 3pt; }
</style>
</head>
<body>
  <div style="text-align:center;">
    <p class="bold">Republic of the Philippines</p>
    <p class="bold">Department of Environment and Natural Resources</p>
    <p>Community Environment and Natural Resources Office (CENRO)</p>
    <p>Argao, Cebu</p>
  </div>

  <h3 style="text-align: center; margin-bottom: 20px;">Application for Renewal of Wood Processing Plant Permit</h3>

  <p class="section-title">I. GENERAL INFORMATION</p>
  <p class="info-line">Name of Applicant / Company: <span class="underline">{$applicantName}</span></p>
  <p class="info-line">Address: <span class="underline">{$address}</span></p>
  <p class="info-line">Plant Location: <span class="underline">{$plantLocation}</span></p>
  <p class="info-line">Contact Number: <span class="underline">{$contactNumber}</span> Email: <span class="underline">{$emailAddress}</span></p>
  <p class="info-line">Type of Ownership: <span class="underline">{$ownershipType}</span></p>
  <p class="info-line">Previous Permit No.: <span class="underline">{$previousPermit}</span> Expiry Date: <span class="underline">{$expiryDate}</span></p>

  <p class="section-title">II. PLANT DESCRIPTION AND OPERATION</p>
  <p class="info-line">Kind of Wood Processing Plant: <span class="underline">{$plantType}</span></p>
  <p class="info-line">Daily Rated Capacity (per 8-hour shift): <span class="underline">{$dailyCapacity}</span></p>

  <p class="info-line">Machineries and Equipment to be Used (with specifications):</p>
  <table>
    <thead>
      <tr>
        <th>Type of Equipment/Machinery</th>
        <th>Brand/Model</th>
        <th>Horsepower/Capacity</th>
        <th>Quantity</th>
      </tr>
    </thead>
    <tbody>
      {$machineryRowsHTML}
    </tbody>
  </table>

  <p class="info-line">Source of Power Supply: <span class="underline">{$powerSource}</span></p>

  <p class="section-title">III. SUPPLY CONTRACTS AND RAW MATERIAL REQUIREMENTS</p>
  <p class="info-line">The applicant has Log/Lumber Supply Contracts for a minimum period of five (5) years.</p>
  <table>
    <thead>
      <tr>
        <th>Supplier Name</th>
        <th>Species</th>
        <th>Contracted Vol.</th>
      </tr>
    </thead>
    <tbody>
      {$supplyRowsHTML}
    </tbody>
  </table>

  <p class="section-title">IV. DECLARATION</p>
  <div class="declaration">
    <p>I, <span class="underline">{$declarationName}</span>, hereby certify that the above information is true and correct, and all requirements for renewal are submitted.</p>
    <div style="margin-top: 16px;">
      {$sigBlock}
      <div class="signature-line"></div>
      <p>Signature of Applicant</p>
    </div>
  </div>
</body>
</html>
HTML;
}

function regenerate_wood_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, string $permitType, string $oldUrl): ?string
{
    $clientId = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    $permitType = strtolower(trim($permitType));
    if ($clientId === '' || $applicationId === '' || $requirementId === '') return null;

    // Fetch client
    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) throw new Exception('Client data missing for regenerated wood application doc.');

    // Fetch application form data
    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) throw new Exception('Application form data missing for regenerated wood document.');
    $app = array_change_key_case($app, CASE_LOWER);

    // Prepare machinery rows HTML
    $machineryRowsHTML = '<tr><td colspan="4"></td></tr>';
    if (!empty($app['machineries_and_equipment_to_be_used_with_specifications'])) {
        $decoded = json_decode($app['machineries_and_equipment_to_be_used_with_specifications'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $machineryRowsHTML = '';
            foreach ($decoded as $m) {
                if (is_array($m)) {
                    $type = letter_escape($m['type'] ?? ($m['name'] ?? ''));
                    $brand = letter_escape($m['brand'] ?? ($m['model'] ?? ''));
                    $power = letter_escape($m['power'] ?? ($m['capacity'] ?? ''));
                    $qty = letter_escape($m['qty'] ?? ($m['quantity'] ?? ''));
                    $machineryRowsHTML .= "<tr><td>{$type}</td><td>{$brand}</td><td>{$power}</td><td>{$qty}</td></tr>";
                }
            }
        }
    }

    // Prepare supply rows HTML
    $supplyRowsHTML = '<tr><td colspan="3"></td></tr>';
    if (!empty($app['suppliers_json'])) {
        $decoded = json_decode($app['suppliers_json'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $supplyRowsHTML = '';
            foreach ($decoded as $s) {
                if (is_array($s)) {
                    $supplier = letter_escape($s['supplier'] ?? ($s['name'] ?? ''));
                    $species = letter_escape($s['species'] ?? '');
                    $volume = letter_escape($s['volume'] ?? ($s['contracted_vol'] ?? ''));
                    $supplyRowsHTML .= "<tr><td>{$supplier}</td><td>{$species}</td><td>{$volume}</td></tr>";
                }
            }
        }
    }

    // Extract additional_information JSON if present
    $additionalInfo = [];
    if (!empty($app['additional_information'])) {
        $decoded = json_decode($app['additional_information'], true);
        if (is_array($decoded)) $additionalInfo = $decoded;
    }

    // Prepare fields array
    $F = [];
    if ($permitType === 'renewal') {
        $F = [
            'applicantName' => letter_escape(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))),
            'address' => letter_escape($app['present_address'] ?? ''),
            'plantLocation' => letter_escape($app['plant_location'] ?? ''),
            'contactNumber' => letter_escape($app['contact_number'] ?? ''),
            'emailAddress' => letter_escape($app['email_address'] ?? ''),
            'ownershipType' => letter_escape($app['form_of_ownership'] ?? ''),
            'previousPermit' => letter_escape($app['previous_certificate_of_registration_no'] ?? $app['previous_permit_no'] ?? ''),
            'expiryDate' => letter_escape($app['expiry_date'] ?? $app['expires_on'] ?? ''),
            'plantType' => letter_escape($app['kind_of_wood_processing_plant'] ?? ''),
            'dailyCapacity' => letter_escape($app['daily_rated_capacity_per8_hour_shift'] ?? ''),
            'powerSource' => letter_escape($app['source_of_power_supply'] ?? $additionalInfo['power_source'] ?? ''),
            'declarationName' => letter_escape($app['declaration_name_renewal'] ?? $additionalInfo['declaration_name'] ?? $app['declaration_name'] ?? trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))),
        ];
    } else {
        // New permit
        $F = [
            'applicantName' => letter_escape(trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))),
            'businessAddress' => letter_escape($app['present_address'] ?? $app['legitimate_business_address'] ?? ''),
            'plantLocation' => letter_escape($app['plant_location'] ?? ''),
            'contactNumber' => letter_escape($app['contact_number'] ?? ''),
            'emailAddress' => letter_escape($app['email_address'] ?? ''),
            'ownershipType' => letter_escape($app['form_of_ownership'] ?? ''),
            'plantType' => letter_escape($app['kind_of_wood_processing_plant'] ?? ''),
            'dailyCapacity' => letter_escape($app['daily_rated_capacity_per8_hour_shift'] ?? ''),
            'powerSource' => letter_escape($app['source_of_power_supply'] ?? $additionalInfo['power_source'] ?? ''),
            'declarationName' => letter_escape($app['declaration_name_new'] ?? $additionalInfo['declaration_name'] ?? $app['declaration_name'] ?? trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''))),
            'declarationAddress' => letter_escape($app['declaration_address'] ?? $additionalInfo['declaration_address'] ?? ''),
        ];
    }

    // Signature handling
    $sigValue = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);

    $sigBlock = '';
    if ($sigBase64) {
        $sigBlock = "<img src=\"data:image/png;base64,{$sigBase64}\" width=\"150\" height=\"60\" style=\"display:block;margin:8px 0 6px 0;border:1px solid #000;\" alt=\"Signature\">";
    }

    // Build HTML document
    if ($permitType === 'renewal') {
        $docHTML = buildRenewalDocHTMLWood($F, $machineryRowsHTML, $supplyRowsHTML, $sigBlock);
    } else {
        $docHTML = buildNewDocHTMLWood($F, $machineryRowsHTML, $supplyRowsHTML, $sigBlock);
    }

    // Create MHTML container
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $docHTML . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///sig.png\r\n\r\n";
    if ($sigBase64) {
        $mhtml .= chunk_split($sigBase64, 76, "\r\n");
    } else {
        $mhtml .= chunk_split('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 76, "\r\n");
    }
    $mhtml .= "\r\n--$boundary--";

    // Upload to storage using the requested prefix structure: wood/{permitFolder}/{client_id}/{run}/
    $bucket = bucket_name();
    $run = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $permitFolder = ($permitType === 'renewal') ? 'renewal permit' : 'new permit';
    $prefix = "wood/{$permitFolder}/{$clientId}/{$run}/";

    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'Form');
    $shortId = substr($clientId, 0, 8);
    $ymd = date('Ymd');
    $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
    $newUrl = '';
    $attempt = 0;
    while (true) {
        $fname = ($permitType === 'renewal' ? 'Wood_Renewal' : 'Wood_New') . "_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = $prefix . $fname;
        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    // Update requirements.application_form with the new URL
    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    // Delete old file if changed
    if ($oldUrl !== '') {
        $oldInfo = parse_storage_reference($oldUrl);
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old wood application_form object: ' . $deleteErr->getMessage());
            }
        }
    }

    // Return the new file url so callers (UI) can consume it if needed
    return $newUrl;
}

function regenerate_wildlife_application_form(PDO $pdo, string $clientId, string $applicationId, string $requirementId, string $permitType, string $oldUrl): void
{
    $clientId      = trim($clientId);
    $applicationId = trim($applicationId);
    $requirementId = trim($requirementId);
    $permitType    = strtolower(trim($permitType));

    if ($clientId === '' || $applicationId === '' || $requirementId === '') {
        return;
    }

    // Fetch client data
    $clientStmt = $pdo->prepare('SELECT client_id, first_name, middle_name, last_name, sitio_street, barangay, municipality, city, signature, contact_number FROM public.client WHERE client_id = :cid LIMIT 1');
    $clientStmt->execute([':cid' => $clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        throw new Exception('Client data missing for regenerated wildlife application doc.');
    }

    // Fetch application form data
    $appStmt = $pdo->prepare('SELECT * FROM public.application_form WHERE application_id = :id LIMIT 1');
    $appStmt->execute([':id' => $applicationId]);
    $app = $appStmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        throw new Exception('Application form data missing for regenerated wildlife document.');
    }
    $app = array_change_key_case($app, CASE_LOWER);

    // Helper: title case similar to titleCase() in JS
    $toTitle = static function (?string $value): string {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords(strtolower($value));
    };

    $isRenewal = ($permitType === 'renewal');

    // Parse additional_information JSON
    $decoded = [];
    if (!empty($app['additional_information'])) {
        $dec = json_decode((string)$app['additional_information'], true);
        if (is_array($dec)) {
            $decoded = $dec;
        }
    }

    // ---------- Names ----------
    $docFirstName  = $toTitle($app['first_name']  ?? $client['first_name']  ?? '');
    $docMiddleName = $toTitle($app['middle_name'] ?? $client['middle_name'] ?? '');
    $docLastName   = $toTitle($app['last_name']   ?? $client['last_name']   ?? '');
    $fullNameParts = array_filter([$docFirstName, $docMiddleName, $docLastName], static function ($v) {
        return $v !== '';
    });
    $fullName = implode(' ', $fullNameParts);

    // ---------- Addresses & establishment info ----------

    // 1) Residence address
    //    Primary: additional_information.residence_address (snake/camel/old)
    //    Fallback: application_form.present_address
    //    Fallback: built from client address
    $rawResidence =
        ($decoded['residence_address']  ?? null) ??
        ($decoded['ResidenceAddress']   ?? null) ??
        ($decoded['residenceAddress']   ?? null) ??
        ($app['residence_address']      ?? null) ?? // just in case older schema
        ($app['present_address']        ?? null) ??
        '';

    if ($rawResidence === '') {
        $addrParts = [];
        if (!empty($client['sitio_street'])) $addrParts[] = $client['sitio_street'];
        if (!empty($client['barangay']))     $addrParts[] = $client['barangay'];
        if (!empty($client['municipality'])) $addrParts[] = $client['municipality'];
        if (!empty($client['city']))         $addrParts[] = $client['city'];
        $rawResidence = implode(', ', $addrParts);
    }
    $docResidenceAddress = $toTitle($rawResidence);

    // 2) Establishment name/address/telephone
    //    Saved by save_wildlife.php inside additional_information as:
    //      establishment_name, establishment_address, establishment_telephone
    //    Old data may use EstablishmentName / EstablishmentAddress / establishmentTelephone.
    //    For renewal we can also fall back to renewal_of_my_certificate_of_wildlife_registration_of.
    $rawEstablishmentName =
        ($decoded['establishment_name'] ?? null) ??
        ($decoded['EstablishmentName']  ?? null) ??
        ($decoded['establishmentName']  ?? null) ??
        ($app['establishment_name']     ?? null) ??
        ($isRenewal ? ($app['renewal_of_my_certificate_of_wildlife_registration_of'] ?? null) : null) ??
        '';

    $rawEstablishmentAddr =
        ($decoded['establishment_address'] ?? null) ??
        ($decoded['EstablishmentAddress']  ?? null) ??
        ($decoded['establishmentAddress']  ?? null) ??
        ($app['establishment_address']     ?? null) ??
        '';

    $rawEstablishmentTel =
        ($decoded['establishment_telephone'] ?? null) ??
        ($decoded['EstablishmentTelephone']  ?? null) ??
        ($decoded['establishmentTelephone']  ?? null) ??
        ($app['establishment_telephone']     ?? null) ??
        '';

    $docEstablishmentName    = $toTitle($rawEstablishmentName);
    $docEstablishmentAddress = $toTitle($rawEstablishmentAddr);
    $establishmentTelephone  = trim((string)$rawEstablishmentTel);

    // 3) Postal address
    //    Saved as postal_address in additional_information (or camelCase / old)
    $rawPostalAddress =
        ($decoded['postal_address'] ?? null) ??
        ($decoded['PostalAddress']  ?? null) ??
        ($decoded['postalAddress']  ?? null) ??
        ($app['postal_address']     ?? null) ??
        $rawResidence;

    $docPostalAddress = $toTitle($rawPostalAddress);

    // 4) Telephones, WFP info
    //    telephone_number is stored both in application_form and additional_information.
    $telephoneNumber = trim((string)(
        $decoded['telephone_number']
        ?? $decoded['TelephoneNumber']
        ?? $decoded['telephoneNumber']
        ?? $app['telephone_number']
        ?? $app['contact_number']
        ?? $client['contact_number']
        ?? ''
    ));

    // WFP number & issue date (for renewal paragraph)
    $wfpNumber = trim((string)(
        $decoded['wfp_number']
        ?? $decoded['WfpNumber']
        ?? $decoded['wfpNumber']
        ?? $app['wfp_number']             // just in case schema was extended
        ?? $app['permit_number']          // set by save_wildlife.php for renewal
        ?? ''
    ));

    $issueDate = trim((string)(
        $decoded['issue_date']
        ?? $decoded['IssueDate']
        ?? $decoded['issueDate']
        ?? $app['issue_date']
        ?? ''
    ));

    // ---------- Categories (zoo/botanical/private_collection) ----------
    $boolFromScalar = static function ($v): bool {
        $v = trim((string)$v);
        if ($v === '') return false;
        $lv = strtolower($v);
        return in_array($lv, ['1', 'true', 'yes', 'y', 'on'], true);
    };

    $categories = is_array($decoded['categories'] ?? null) ? $decoded['categories'] : [];

    $zooVal       = $app['zoo'] ?? ($categories['zoo'] ?? '');
    $botanicalVal = $app['botanical_garden'] ?? ($categories['botanical_garden'] ?? '');
    $privateVal   = $app['private_collection'] ?? ($categories['private_collection'] ?? '');

    $zoo         = $boolFromScalar($zooVal);
    $botanical   = $boolFromScalar($botanicalVal);
    $privateColl = $boolFromScalar($privateVal);

    // Use Unicode checkbox characters so generated .doc displays marks reliably
    $check = static function (bool $b): string {
        return $b ? '☑' : '☐';
    };

    // ---------- Animals ----------
    $animals = [];
    if (!empty($decoded['animals']) && is_array($decoded['animals'])) {
        foreach ($decoded['animals'] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $animals[] = [
                'common'  => letter_escape(trim((string)($a['commonName']     ?? $a['common_name']     ?? ''))),
                'sci'     => letter_escape(trim((string)($a['scientificName'] ?? $a['scientific_name'] ?? ''))),
                'qty'     => letter_escape(trim((string)($a['quantity']       ?? $a['qty']             ?? ''))),
                'remarks' => letter_escape(trim((string)($a['remarks']        ?? $a['remark']          ?? ''))),
            ];
        }
    }

    if (!$animals) {
        $animalsJson = '';
        if ($isRenewal && !empty($app['renewal_animals_json'])) {
            $animalsJson = (string)$app['renewal_animals_json'];
        } elseif (!empty($app['animals_json'])) {
            $animalsJson = (string)$app['animals_json'];
        }
        if ($animalsJson !== '') {
            $arr = json_decode($animalsJson, true);
            if (is_array($arr)) {
                foreach ($arr as $a) {
                    if (!is_array($a)) continue;
                    $animals[] = [
                        'common'  => letter_escape(trim((string)($a['commonName']     ?? $a['common_name']     ?? ''))),
                        'sci'     => letter_escape(trim((string)($a['scientificName'] ?? $a['scientific_name'] ?? ''))),
                        'qty'     => letter_escape(trim((string)($a['quantity']       ?? $a['qty']             ?? ''))),
                        'remarks' => letter_escape(trim((string)($a['remarks']        ?? $a['remark']          ?? ''))),
                    ];
                }
            }
        }
    }

    // Signature
    $sigValue  = trim((string)($app['signature_of_applicant'] ?? $client['signature'] ?? ''));
    $sigBase64 = signature_base64_from_value($sigValue);

    // ---------- Build HTML (same structure as before, now with non-empty establishment block) ----------

    $headerHtml = '
      <div style="text-align:center;margin-bottom:20px;">
        <p style="font-weight:bold;">Republic of the Philippines</p>
        <p style="font-weight:bold;">Department of Environment and Natural Resources</p>
        <p style="font-weight:bold;">REGION 7</p>
        <p>______</p>
        <p>Date</p>
      </div>
    ';

    // Animals table rows
    if ($animals) {
        $animalsTableRows = '';
        foreach ($animals as $a) {
            $common  = $a['common']  ?? '';
            $sci     = $a['sci']     ?? '';
            $qty     = $a['qty']     ?? '';
            $remarks = $a['remarks'] ?? '';
            $animalsTableRows .= '<tr><td>' . $common . '</td><td>' . $sci . '</td><td>' . $qty . '</td>';
            if ($isRenewal) {
                $animalsTableRows .= '<td>' . $remarks . '</td>';
            }
            $animalsTableRows .= '</tr>';
        }
    } else {
        if ($isRenewal) {
            $animalsTableRows = '
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            ';
        } else {
            $animalsTableRows = '
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            ';
        }
    }

    $fullNameEsc     = letter_escape($fullName);
    $docResidenceEsc = letter_escape($docResidenceAddress);
    $telephoneEsc    = letter_escape($telephoneNumber);
    $estNameEsc      = letter_escape($docEstablishmentName);
    $estAddrEsc      = letter_escape($docEstablishmentAddress);
    $estTelEsc       = letter_escape($establishmentTelephone);
    $wfpEsc          = letter_escape($wfpNumber);
    $issueEsc        = letter_escape($issueDate);
    $docPostalEsc    = letter_escape($docPostalAddress);

    if ($isRenewal) {
        $introHtml = <<<HTML
    <p class="info-line">I, <span class="underline">{$fullNameEsc}</span> with address at <span class="underline">{$docResidenceEsc}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">{$telephoneEsc}</span>, have the honor to request for the</p>
    <p class="info-line indent">renewal of my Certificate of Wildlife Registration of <span class="underline">{$estNameEsc}</span></p>
    <p class="info-line indent">located at <span class="underline">{$estAddrEsc}</span> with Tel. no. <span class="underline-small">{$estTelEsc}</span></p>
    <p class="info-line indent">and Original WFP No. <span class="underline-small">{$wfpEsc}</span> issued on <span class="underline-small">{$issueEsc}</span>, and</p>
    <p class="info-line">registration of animals/stocks maintained which are as follows:</p>
HTML;
        $finalParagraphText = 'I understand that this application for renewal does not by itself grant the right to continue possession of any wild animals until the corresponding Renewal Certificate of Registration is issued.';
        $docTitle           = 'Wildlife Registration Renewal Application';
        $applicationLine    = 'APPLICATION FOR: RENEWAL CERTIFICATE OF WILDLIFE REGISTRATION';
    } else {
        $introHtml = <<<HTML
    <p class="info-line">I <span class="underline">{$fullNameEsc}</span> with address at <span class="underline">{$docResidenceEsc}</span></p>
    <p class="info-line indent">and Tel. no. <span class="underline-small">{$telephoneEsc}</span> have the honor to apply for the registration of <span class="underline">{$estNameEsc}</span></p>
    <p class="info-line indent">located at <span class="underline">{$estAddrEsc}</span> with Tel. no. <span class="underline-small">{$estTelEsc}</span> and registration of animals/stocks maintained</p>
    <p class="info-line">there at which are as follows:</p>
HTML;
        $finalParagraphText = 'I understand that the filling of this application conveys no right to possess any wild animals until Certificate of Registration is issued to me by the Regional Director of the DENR Region 7.';
        $docTitle           = 'Wildlife Registration Application';
        $applicationLine    = 'APPLICATION FOR: CERTIFICATE OF WILDLIFE REGISTRATION';
    }

    $zooCheckbox       = $check($zoo);
    $botanicalCheckbox = $check($botanical);
    $privateCheckbox   = $check($privateColl);
    $animalsHeaderExtra = $isRenewal ? '<th>Remarks (Alive/Deceased)</th>' : '';

    $docHTML = <<<HTML
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>{$docTitle}</title>
<style>
  body, div, p { line-height:1.6; font-family:Arial; font-size:11pt; margin:0; padding:0; }
  .bold{ font-weight:bold; }
    .checkbox{ font-family: 'Segoe UI Symbol', 'Arial Unicode MS', 'DejaVu Sans', Arial, sans-serif; font-size:14pt; vertical-align:middle; }
  .underline{ display:inline-block; border-bottom:1px solid #000; min-width:260px; padding:0 5px; margin:0 5px; }
  .underline-small{ display:inline-block; border-bottom:1px solid #000; min-width:150px; padding:0 5px; margin:0 5px; }
  .indent{ margin-left:40px; }
  .info-line{ margin:12pt 0; }
  table{ width:100%; border-collapse:collapse; margin:15pt 0; }
  table, th, td { border:1px solid #000; }
  th, td { padding:8px; text-align:left; }
</style>
</head>
<body>
{$headerHtml}

<p style="text-align:center;margin-bottom:20px;" class="bold">
  {$applicationLine}
</p>

<p style="margin-bottom:15px;">
  <span class="checkbox">{$zooCheckbox}</span> Zoo
  <span class="checkbox">{$botanicalCheckbox}</span> Botanical Garden
  <span class="checkbox">{$privateCheckbox}</span> Private Collection
</p>

<p class="info-line">The Regional Executive Director</p>
<p class="info-line">DENR Region 7</p>
<p class="info-line">National Government Center,</p>
<p class="info-line">Sudion, Lahug, Cebu City</p>

<p class="info-line">(Submit in Duplicate)</p>
<p class="info-line">Sir/Madam:</p>

{$introHtml}

<table>
  <tr>
    <th>Common Name</th>
    <th>Scientific Name</th>
    <th>Quantity</th>
    {$animalsHeaderExtra}
  </tr>
  {$animalsTableRows}
</table>

<p class="info-line">
  {$finalParagraphText}
</p>

<div style="margin-top:28px;">
HTML;

    if ($sigBase64) {
        $docHTML .= '<img src="cid:sigimg" style="max-height:60px;display:block;margin-top:8pt;border:1px solid #000;" alt="Signature"/>';
    } else {
        $docHTML .= '<div style="margin-top:40px;border-top:1px solid #000;width:50%;padding-top:3pt;"></div>';
    }

    $docHTML .= <<<HTML
  <p>Signature of Applicant</p>
</div>

<p class="info-line">Postal Address: <span class="underline">{$docPostalEsc}</span></p>

</body>
</html>
HTML;

    // ---------- Build MHTML wrapper ----------
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(8));
    $mhtml  = "MIME-Version: 1.0\r\n";
    $mhtml .= "Content-Type: multipart/related; boundary=\"$boundary\"; type=\"text/html\"\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\nContent-Location: file:///index.html\r\n\r\n" . $docHTML . "\r\n\r\n";
    $mhtml .= "--$boundary\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <sigimg>\r\nContent-Location: file:///signature.png\r\n\r\n";
    if ($sigBase64) {
        $mhtml .= chunk_split($sigBase64, 76, "\r\n");
    } else {
        $mhtml .= chunk_split('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 76, "\r\n");
    }
    $mhtml .= "\r\n--$boundary--";

    // ---------- Upload ----------
    $bucket  = bucket_name();
    $oldInfo = parse_storage_reference($oldUrl);

    $sanLast = preg_replace('/[^A-Za-z0-9]+/', '_', ($client['last_name'] ?? '') ?: 'Wildlife');
    $shortId = substr($clientId, 0, 8);
    $ymd     = date('Ymd');
    $uniq    = substr(bin2hex(random_bytes(4)), 0, 8);
    $isRenewalPrefix = $isRenewal ? 'Wildlife_Renewal' : 'Wildlife_New';

    $newUrl  = '';
    $attempt = 0;

    while (true) {
        $fname      = "{$isRenewalPrefix}_{$sanLast}_{$ymd}_{$shortId}_{$uniq}.doc";
        $objectPath = "wildlife/{$clientId}/{$fname}";

        try {
            $newUrl = supa_upload_binary($bucket, $objectPath, 'application/msword', $mhtml);
            break;
        } catch (Throwable $uploadErr) {
            $attempt++;
            if ($attempt >= 3 || strpos($uploadErr->getMessage(), '(409)') === false) {
                throw $uploadErr;
            }
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }

    // Update DB
    $updateReq = $pdo->prepare('UPDATE public.requirements SET application_form = :file WHERE requirement_id = :id');
    $updateReq->execute([':file' => $newUrl, ':id' => $requirementId]);

    // Delete old file if different
    if ($oldUrl !== '') {
        $newInfo = parse_storage_reference($newUrl);
        if ($oldInfo && (!$newInfo || $oldInfo['bucket'] !== $newInfo['bucket'] || $oldInfo['path'] !== $newInfo['path'])) {
            try {
                supa_delete_object($oldInfo['bucket'], $oldInfo['path']);
            } catch (Throwable $deleteErr) {
                error_log('[UPDATE APPLICATION] failed to delete old wildlife application_form object: ' . $deleteErr->getMessage());
            }
        }
    }
}




try {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Not authenticated.');
    }

    $approvalId = trim((string)($_POST['approval_id'] ?? ''));
    if ($approvalId === '') {
        throw new Exception('Missing approval_id.');
    }

    $fields = $_POST['fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }

    $fieldOrigins = $_POST['field_origins'] ?? [];
    if (!is_array($fieldOrigins)) {
        $fieldOrigins = [];
    }
    $fieldMeta = $_POST['field_meta'] ?? [];
    if (!is_array($fieldMeta)) {
        $fieldMeta = [];
    }

    $requestType = strtolower(trim((string)($_POST['request_type'] ?? '')));
    $permitType = strtolower(trim((string)($_POST['permit_type'] ?? '')));

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        select
            a.application_id,
            a.requirement_id,
            a.seedl_req_id,
            a.client_id,
            lower(coalesce(a.approval_status,'')) as status,
        lower(coalesce(a.request_type,''))     as request_type,
        lower(coalesce(a.permit_type,''))      as permit_type
    from public.approval a
    join public.client c on c.client_id = a.client_id
    where a.approval_id = :aid
      and c.user_id = :uid
    limit 1
");
    $stmt->execute([
        ':aid' => $approvalId,
        ':uid' => $_SESSION['user_id'],
    ]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$approval) {
        throw new Exception('Approval not found.');
    }

    if (($approval['status'] ?? '') !== 'pending') {
        throw new Exception('Only pending requests can be edited.');
    }

    $applicationId = $approval['application_id'] ?? null;
    $requirementId = $approval['requirement_id'] ?? null;

    // This is the "anchor" seedling request id for this approval.
    // For request_type = 'seedling' it must never become NULL because of the
    // approval_seedl_req_required_for_seedling check constraint.
    $approvalSeedlReqId = $approval['seedl_req_id'] ?? null;

    $seedlingBatchKey = '';
    if (!empty($approvalSeedlReqId)) {
        $batchStmt = $pdo->prepare('SELECT batch_key FROM public.seedling_requests WHERE seedl_req_id = :sid LIMIT 1');
        $batchStmt->execute([':sid' => $approvalSeedlReqId]);
        $seedlingBatchKey = trim((string)($batchStmt->fetchColumn() ?: ''));
    }
    $seedlingGroupKey = $seedlingBatchKey !== '' ? $seedlingBatchKey : (string)($approvalSeedlReqId ?? '');

    $dbRequestType = strtolower((string)($approval['request_type'] ?? '')) ?: $requestType;
    $dbPermitType  = strtolower((string)($approval['permit_type'] ?? '')) ?: $permitType;


    $appRow = [];
    if ($applicationId) {
        $stmt = $pdo->prepare("select * from public.application_form where application_id = :id limit 1");

        $stmt->execute([':id' => $applicationId]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        // allow case-insensitive matching
        $appRow = array_change_key_case($appRow, CASE_LOWER);
    }

    $clientId = $approval['client_id'] ?? null;
    $clientRow = [];
    if ($clientId) {
        $stmt = $pdo->prepare("select * from public.client where client_id = :id limit 1");
        $stmt->execute([':id' => $clientId]);
        $clientRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $clientRow = array_change_key_case($clientRow, CASE_LOWER);
    }


    $reqRow = [];
    if ($requirementId) {
        $stmt = $pdo->prepare("select * from public.requirements where requirement_id = :id limit 1");
        $stmt->execute([':id' => $requirementId]);
        $reqRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $previousApplicationFormUrl = trim((string)($reqRow['application_form'] ?? ''));

    // Never allow these app_form columns to be updated
    $blockedAppCols = [
        'application_id',       // generated
        'application_for',      // read-only in edit mode
        'type_of_permit',       // read-only in edit mode
        // common protected/system fields:
        'request_type',
        'permit_type',
        'approval_status',
        'status',
        'created_at',
        'updated_at',
        'submitted_at'
    ];

    $appUpdates = [];
    $clientUpdates = [];
    $seedlingUpdates = [];
    $seedlingDeletes = [];
    $seedlingInserts = [];
    $batchDeleteGroups = [];
    $batchInsertCounts = [];

    $deletedSeedlings = $_POST['deleted_seedlings'] ?? [];
    if (!is_array($deletedSeedlings)) {
        $deletedSeedlings = [];
    }
    foreach ($deletedSeedlings as $entry) {
        $data = parse_deleted_seedling_entry((string)$entry);
        if (empty($data)) {
            continue;
        }

        $rowSeedlReqId = trim((string)($data['seedl_req_id'] ?? ''));
        $seedlingsId   = trim((string)($data['seedlings_id'] ?? ''));
        $batchKey      = trim((string)($data['batch_key'] ?? ''));

        if ($rowSeedlReqId === '' || $seedlingsId === '') {
            continue;
        }

        $groupKey = seedling_group_key($batchKey, $rowSeedlReqId);
        $batchDeleteGroups[$groupKey]['batch_key']    = $batchKey;
        $batchDeleteGroups[$groupKey]['seedl_req_id'] = $rowSeedlReqId;
        $batchDeleteGroups[$groupKey]['delete_count'] = ($batchDeleteGroups[$groupKey]['delete_count'] ?? 0) + 1;

        $seedlingDeletes[] = [
            'seedl_req_id' => $rowSeedlReqId,
            'seedlings_id' => $seedlingsId,
        ];
    }

    $deletedSeedlReqIds = [];
    foreach ($seedlingDeletes as $entry) {
        $sid = trim((string)($entry['seedl_req_id'] ?? ''));
        if ($sid !== '') {
            $deletedSeedlReqIds[$sid] = true;
        }
    }
    $anchorWillBeDeleted = !empty($approvalSeedlReqId) && isset($deletedSeedlReqIds[(string)$approvalSeedlReqId]);

    foreach ($fields as $field => $value) {
        if (!is_string($field)) continue;
        $fieldName = strtolower(clean_field_name($field)); // normalize
        if ($fieldName === '') continue;
        $origin = strtolower(trim((string)($fieldOrigins[$field] ?? 'application_form')));
        if ($origin === 'client') {
            if (!$clientId || !array_key_exists($fieldName, $clientRow)) continue;
            $clientUpdates[$fieldName] = trim((string)$value);
            continue;
        }
        if ($origin === 'seedling_requests') {
            $metaValue = $fieldMeta[$field] ?? '';
            $metaData = [];
            if ($metaValue !== '') {
                $decoded = json_decode($metaValue, true);
                if (is_array($decoded)) {
                    $metaData = $decoded;
                }
            }

            $seedlingsId = trim((string)($metaData['seedlings_id'] ?? ''));
            if ($seedlingsId === '') {
                continue;
            }

            $rowSeedlReqId   = trim((string)($metaData['seedl_req_id'] ?? ''));
            $batchKey        = trim((string)($metaData['seedling_batch_key'] ?? ''));
            if ($batchKey === '' && $rowSeedlReqId !== '') {
                $batchKey = $rowSeedlReqId;
            }

            $isNew           = !empty($metaData['is_new']) || $rowSeedlReqId === '';
            $isDeleted       = !empty($metaData['is_deleted']);
            $seedlingsOldId  = trim((string)($metaData['seedlings_old_id'] ?? ''));
            $quantity        = (int)$value;

            // Is this the special "anchor" row referenced by approval.seedl_req_id?
            $isAnchor = !empty($approvalSeedlReqId) && $rowSeedlReqId === (string)$approvalSeedlReqId;

            if ($isDeleted) {
                if (!$isAnchor) {
                    // Normal case: we can safely delete this seedling row.
                    $oldId = $seedlingsOldId !== '' ? $seedlingsOldId : $seedlingsId;
                    if ($rowSeedlReqId !== '' && $oldId !== '') {
                        $seedlingDeletes[] = [
                            'seedl_req_id' => $rowSeedlReqId,
                            'seedlings_id' => $oldId,
                        ];
                    }
                } else {
                    // Anchor deletion is handled later once a new anchor exists.
                }
                continue;
            }

            if ($isNew) {
                $seedlingInserts[] = [
                    'seedlings_id' => $seedlingsId,
                    'quantity'     => $quantity,
                    'batch_key'    => $batchKey !== '' ? $batchKey : ($seedlingGroupKey !== '' ? $seedlingGroupKey : null),
                ];
                $groupKey = seedling_group_key($batchKey, $rowSeedlReqId);
                $batchInsertCounts[$groupKey] = ($batchInsertCounts[$groupKey] ?? 0) + 1;
            } else {
                $seedlingUpdates[] = [
                    'seedl_req_id'     => $rowSeedlReqId,
                    'seedlings_id'     => $seedlingsId,
                    'quantity'         => $quantity,
                    'seedlings_old_id' => $seedlingsOldId !== '' ? $seedlingsOldId : $seedlingsId,
                ];
            }

            continue;
        }


        // If this field targets application_form, allow it only when the
        // column exists OR it's one of the treecut JSON/total fields which
        // we may create on-demand later.
        $allowed_treecut_cols = ['species_rows_json', 'total_count', 'total_volume'];
        if (!array_key_exists($fieldName, $appRow) && !in_array($fieldName, $allowed_treecut_cols, true)) {
            continue;
        }
        if (in_array($fieldName, $blockedAppCols, true)) {
            continue;
        }
        $appUpdates[$fieldName] = trim((string)$value);
    }

    // Accept alias keys produced by the client-side WOOD editors and map them
    // to the real application_form columns used by the wood (WPP) flow.
    // The edit UI posts `fields[machinery_rows_json]` and `fields[supply_rows_json]`.
    // Map those into the legacy DB columns so the updates are persisted.
    $woodAliasMap = [
        'machinery_rows_json' => 'machineries_and_equipment_to_be_used_with_specifications',
        'supply_rows_json'    => 'suppliers_json',
        'supply_rows'         => 'suppliers_json',
    ];
    foreach ($woodAliasMap as $alias => $targetCol) {
        if (array_key_exists($alias, $fields) && (!array_key_exists($targetCol, $appRow) || true)) {
            $val = $fields[$alias];
            if (is_array($val) || is_object($val)) {
                $val = json_encode($val, JSON_UNESCAPED_SLASHES);
            } else {
                $val = (string)$val;
            }
            // Only set when non-empty to avoid overwriting with blank strings
            if (trim($val) !== '') {
                $appUpdates[$targetCol] = $val;
            }
        }
    }

    // Handle wood/wpp fields that are stored in additional_information JSON
    // Map form field names to JSON keys (save_wood.php stores as 'power_source', not 'source_of_power_supply')
    // NOTE: declaration_name is a direct column, not JSON; only power_source and declaration_address are JSON
    $woodJsonFieldMap = [
        'source_of_power_supply' => 'power_source',    // form field -> JSON key
        'declaration_address'    => 'declaration_address',
    ];
    $additionalInfoUpdate = false;
    $additionalInfoData = [];

    // Load existing additional_information if present
    if (!empty($appRow['additional_information'])) {
        $decoded = json_decode($appRow['additional_information'], true);
        if (is_array($decoded)) {
            $additionalInfoData = $decoded;
        }
    }

    // Merge submitted wood fields into additional_information
    foreach ($woodJsonFieldMap as $formField => $jsonKey) {
        if (array_key_exists($formField, $fields)) {
            $val = trim((string)$fields[$formField]);
            if ($val !== '') {
                $additionalInfoData[$jsonKey] = $val;
                $additionalInfoUpdate = true;
            }
        }
    }

    // Update additional_information if any wood fields were modified
    if ($additionalInfoUpdate) {
        $appUpdates['additional_information'] = json_encode($additionalInfoData, JSON_UNESCAPED_SLASHES);
    }



    $fileOrigins = $_POST['file_origins'] ?? [];
    if (!is_array($fileOrigins)) {
        $fileOrigins = [];
    }

    $appFileUpdates = [];
    $reqFileUpdates = [];

    if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $bucket = bucket_name();
        foreach ($_FILES['files']['name'] as $field => $name) {
            $fieldName = strtolower(clean_field_name((string)$field));
            if ($fieldName === '') {
                continue;
            }

            $tmpName = $_FILES['files']['tmp_name'][$field] ?? '';
            $error = $_FILES['files']['error'][$field] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE || !is_uploaded_file($tmpName)) {
                continue;
            }

            $origin = strtolower((string)($fileOrigins[$field] ?? 'requirements'));
            $origin = in_array($origin, ['application_form', 'requirements'], true) ? $origin : 'requirements';

            if ($origin === 'application_form' && !array_key_exists($fieldName, $appRow)) {
                continue;
            }
            if ($origin === 'requirements' && !array_key_exists($fieldName, $reqRow)) {
                continue;
            }

            $mime = 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = finfo_file($finfo, $tmpName);
                    if ($detected) {
                        $mime = $detected;
                    }
                    finfo_close($finfo);
                }
            }

            $storagePath = generate_storage_path($dbRequestType ?: $requestType, (string)$approvalId, (string)$name);
            $publicUrl = supa_upload($bucket, $storagePath, $tmpName, $mime);

            if ($origin === 'application_form') {
                $appFileUpdates[$fieldName] = $publicUrl;
            } else {
                $reqFileUpdates[$fieldName] = $publicUrl;
            }
        }
    }

    foreach ($appFileUpdates as $field => $url) {
        $appUpdates[$field] = $url;
    }

    if ($applicationId && $appUpdates) {
        // Ensure certain treecut-related columns exist so updates don't silently skip
        $allowedEnsure = ['species_rows_json', 'total_count', 'total_volume'];
        $needEnsure = array_values(array_intersect($allowedEnsure, array_keys($appUpdates)));
        if ($needEnsure) {
            $colStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'application_form'");
            $colStmt->execute();
            $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $existingMap = array_flip($existingCols);
            foreach ($needEnsure as $col) {
                if (!isset($existingMap[$col])) {
                    try {
                        $pdo->exec('ALTER TABLE public.application_form ADD COLUMN "' . $col . '" text');
                        // reflect the addition locally so subsequent array_key_exists checks pass
                        $appRow[$col] = '';
                    } catch (Throwable $err) {
                        // Log and continue — failure to add column shouldn't break the main update flow
                        error_log('[UPDATE APPLICATION] failed to add column ' . $col . ': ' . $err->getMessage());
                    }
                }
            }
        }
        $setParts = [];
        $params = [':id' => $applicationId];

        // Normalize boolean-like fields that have CHECK constraints
        $normalizeYesNo = function ($v) {
            $s = trim((string)$v);
            if ($s === '') return null;
            $low = strtolower($s);
            if (in_array($low, ['1', 'true', 'yes', 'on'], true)) return 'yes';
            if (in_array($low, ['0', 'false', 'no', 'off'], true)) return 'no';
            return null; // leave as NULL to avoid failing check constraint
        };

        foreach ($appUpdates as $field => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', $field)) continue;
            if (in_array(strtolower($field), $blockedAppCols, true)) continue; // double guard

            // sanitize known checked/text fields to satisfy DB CHECK constraints
            if (in_array(strtolower($field), ['is_government_employee', 'buying_from_other_sources'], true)) {
                $norm = $normalizeYesNo($value);
                $params[":app_{$field}"] = $norm;
            } else {
                $params[":app_{$field}"] = $value;
            }
            $setParts[] = "\"{$field}\" = :app_{$field}";
        }
        if ($setParts) {
            $sql = 'update public.application_form set ' . implode(', ', $setParts) . ' where application_id = :id';
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute($params);
            } catch (Throwable $e) {
                error_log('[UPDATE APPLICATION] application_form update failed: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    if ($clientId && $clientUpdates) {
        $setParts = [];
        $params = [':id' => $clientId];
        foreach ($clientUpdates as $field => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', $field)) continue;
            if (!array_key_exists($field, $clientRow)) continue;
            $setParts[] = "\"{$field}\" = :client_{$field}";
            $params[":client_{$field}"] = $value;
        }
        if ($setParts) {
            $sql = 'update public.client set ' . implode(', ', $setParts) . ' where client_id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    if ($clientId && $batchDeleteGroups) {
        ensure_seedling_rows_remain($pdo, $clientId, $batchDeleteGroups, $batchInsertCounts);
    }

    if ($clientId && $seedlingInserts) {
        $insertStmt = $pdo->prepare('INSERT INTO public.seedling_requests (client_id, seedlings_id, quantity, batch_key) VALUES (:cid, :sid, :qty, :batch_key)');
        foreach ($seedlingInserts as $ins) {
            $insertStmt->execute([
                ':cid' => $clientId,
                ':sid' => $ins['seedlings_id'],
                ':qty' => $ins['quantity'],
                ':batch_key' => $ins['batch_key'],
            ]);
        }
    }

    if ($clientId && $anchorWillBeDeleted) {
        $newSeedlReqId = select_seedling_group_anchor($pdo, (string)$approvalSeedlReqId, $seedlingGroupKey, $deletedSeedlReqIds);
        if ($newSeedlReqId === null) {
            throw new Exception('Unable to preserve a remaining seedling request anchor.');
        }
        if ($newSeedlReqId !== (string)$approvalSeedlReqId) {
            $updateApprovalStmt = $pdo->prepare('UPDATE public.approval SET seedl_req_id = :sid WHERE approval_id = :aid');
            $updateApprovalStmt->execute([
                ':sid' => $newSeedlReqId,
                ':aid' => $approvalId,
            ]);
        }
        $approvalSeedlReqId = $newSeedlReqId;
    }

    if ($clientId && $seedlingDeletes) {
        $deleteStmt = $pdo->prepare('DELETE FROM public.seedling_requests WHERE seedl_req_id = :sid AND seedlings_id = :did');
        foreach ($seedlingDeletes as $del) {
            $deleteStmt->execute([
                ':sid' => $del['seedl_req_id'],
                ':did' => $del['seedlings_id'],
            ]);
        }
    }

    if (!empty($seedlingUpdates)) {
        $seedStmt = $pdo->prepare('UPDATE public.seedling_requests SET quantity = :qty, seedlings_id = :new_did WHERE seedl_req_id = :sid AND seedlings_id = :old_did');
        foreach ($seedlingUpdates as $upd) {
            if (empty($upd['seedl_req_id']) || empty($upd['seedlings_id'])) continue;
            $oldSeedlingsId = empty($upd['seedlings_old_id']) ? $upd['seedlings_id'] : $upd['seedlings_old_id'];
            $seedStmt->execute([
                ':qty' => (int)$upd['quantity'],
                ':sid' => $upd['seedl_req_id'],
                ':new_did' => $upd['seedlings_id'],
                ':old_did' => $oldSeedlingsId,
            ]);
        }
    }


    if ($requirementId && $reqFileUpdates) {
        $setParts = [];
        $params = [':id' => $requirementId];
        foreach ($reqFileUpdates as $field => $value) {
            if (!preg_match('/^[a-z0-9_]+$/i', $field)) {
                continue;
            }
            $setParts[] = "\"{$field}\" = :req_{$field}";
            $params[":req_{$field}"] = $value;
        }
        if ($setParts) {
            $sql = 'update public.requirements set ' . implode(', ', $setParts) . ' where requirement_id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    $returned_new_application_form_url = null;
    if ($clientId && $dbRequestType === 'seedling' && $applicationId && $requirementId && $approvalSeedlReqId) {
        regenerate_seedling_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            (string)$approvalSeedlReqId,
            $previousApplicationFormUrl
        );
    }

    if ($clientId && $dbRequestType === 'lumber' && $applicationId && $requirementId) {
        regenerate_lumber_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            $dbPermitType,
            $previousApplicationFormUrl
        );
    }

    if ($clientId && $dbRequestType === 'chainsaw' && $applicationId && $requirementId) {
        $returned_new_application_form_url = regenerate_chainsaw_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            $dbPermitType,
            $previousApplicationFormUrl
        );
    }

    if ($clientId && $dbRequestType === 'treecut' && $applicationId && $requirementId) {
        regenerate_treecut_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            $previousApplicationFormUrl
        );
    }

    if ($clientId && $dbRequestType === 'wood' && $applicationId && $requirementId) {
        $returned_new_application_form_url = regenerate_wood_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            $dbPermitType,
            $previousApplicationFormUrl
        );
    }

    if ($clientId && $dbRequestType === 'wildlife' && $applicationId && $requirementId) {
        regenerate_wildlife_application_form(
            $pdo,
            (string)$clientId,
            (string)$applicationId,
            (string)$requirementId,
            $dbPermitType,
            $previousApplicationFormUrl
        );
    }


    $pdo->commit();

    $resp = ['ok' => true];
    if (!empty($returned_new_application_form_url)) {
        $resp['application_form_url'] = $returned_new_application_form_url;
    }
    echo json_encode($resp);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
