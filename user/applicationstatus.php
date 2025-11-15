<?php

declare(strict_types=1);

/**
 * application_status.php
 * - Shows ALL approvals for the logged-in user
 * - Two-pane modal (left: form, right: files) with preview drawer
 * - Shows rejection reason + “Request again” (prefill via sessionStorage)
 * - Filters: Status, Request Type, Permit Type, Search by Client
 * - Header/nav copied from user_home.php (including Notifications UI)
 * - Notifications are now LIVE: public.notifications."to" = current user_id
 * - UPDATED: View modal opens instantly with skeleton (bone) UI while details load
 * - NEW: Download button for approved rows (requirements.application_form vs approved_docs.approved_document)
 */

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'user') {
    header('Location: user_login.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo

$FILE_BASE = '';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function notempty($v): bool
{
    return $v !== null && trim((string)$v) !== '' && $v !== 'null';
}
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
    return $v; // allow site-relative paths like /storage/... to pass through
}
/** Strip any rejection reason text from a notification message */
function strip_reason_from_message(?string $msg): string
{
    $t = trim((string)$msg);

    // Remove trailing "Reason: ..." or "Rejection Reason - ..." pieces
    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
    // Remove trailing explanatory clauses like "because ..." or "due to ..."
    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*$/i', '', $t);
    // Clean up extra spaces
    $t = trim(preg_replace('/\s{2,}/', ' ', $t));

    return $t;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'details') {
    header('Content-Type: application/json');
    $approvalId = $_GET['approval_id'] ?? '';
    if (!$approvalId) {
        echo json_encode(['ok' => false, 'error' => 'missing approval_id']);
        exit;
    }

    try {
        // 1) Query
        $st = $pdo->prepare("
          select
            a.approval_id,
            lower(coalesce(nullif(btrim(a.request_type), ''), ''))      as request_type,
            lower(coalesce(nullif(btrim(a.permit_type), ''), 'none'))   as permit_type,
            lower(coalesce(nullif(btrim(a.approval_status), ''), 'pending')) as approval_status,
            a.rejection_reason,
            a.submitted_at,
            a.application_id,
            a.requirement_id,
            a.seedl_req_id,
            c.client_id,
            c.first_name, c.middle_name, c.last_name,
            c.sitio_street, c.barangay, c.municipality, c.city, c.contact_number, c.signature,
            ad.approved_document,
            ad.no         as approved_doc_no,
            ad.date_issued as approved_doc_date_issued,
            ad.expiry_date as approved_doc_expiry_date
          from public.approval a
          left join public.client c on c.client_id = a.client_id
          left join lateral (
              select
                d.approved_document,
                d.no,
                d.date_issued,
                d.expiry_date
              from public.approved_docs d
              where d.approval_id = a.approval_id
              order by d.id desc
              limit 1
          ) ad on true
          where a.approval_id = :aid
            and c.user_id = :uid
          limit 1
        ");
        $st->execute([':aid' => $approvalId, ':uid' => $_SESSION['user_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'not found']);
            exit;
        }

        // 2) Build application fields
        $appFields = [];
        $signatureField = null;
        if (notempty($row['application_id'])) {
            $st2 = $pdo->prepare("select * from public.application_form where application_id = :app limit 1");
            $st2->execute([':app' => $row['application_id']]);
            $app = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($app as $k => $v) {
                if (in_array($k, ['id', 'client_id', 'application_id'], true) || !notempty($v)) continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $norm  = strtolower($label);
                if ($norm === 'additional information' || $norm === 'additional info') continue;
                if (strpos($norm, 'signature') !== false) {
                    $signatureField = [
                        'label' => $label,
                        'value' => (string)$v,
                        'field' => $k,
                        'is_signature' => true,
                        'origin' => 'application_form',
                    ];
                    continue;
                }
                // Hide `suppliers_json` from the generic application field list
                // for non-lumber requests so it doesn't show up in treecut views.
                $requestTypeRow = strtolower(trim((string)($row['request_type'] ?? '')));
                if ($k === 'suppliers_json' && $requestTypeRow !== 'lumber') {
                    continue;
                }

                // For treecut requests we already present a structured
                // `species` block (see treecut_details). Avoid showing any
                // raw species fields in the generic application list to
                // prevent duplicates or ugly output such as
                // "[object Object],[object Object]...". Skip any field
                // whose name or label mentions "species".
                if ($requestTypeRow === 'treecut') {
                    $lk = strtolower($k);
                    if (strpos($lk, 'species') !== false || stripos($label, 'species') !== false) {
                        continue;
                    }
                }

                $appFields[] = [
                    'label' => $label,
                    'value' => (string)$v,
                    'field' => $k,
                    'origin' => 'application_form',
                ];
            }
        }
        if ($signatureField) array_unshift($appFields, $signatureField);

        $clientFieldMap = [
            'first_name'   => 'First Name',
            'middle_name'  => 'Middle Name',
            'last_name'    => 'Last Name',
            'sitio_street' => 'Sitio / Street',
            'barangay'     => 'Barangay',
            'municipality' => 'Municipality',
            'city'         => 'City'
        ];
        $clientFields = [];
        foreach ($clientFieldMap as $col => $label) {
            $value = trim((string)($row[$col] ?? ''));
            if ($value === '') continue;
            $clientFields[] = [
                'label'  => $label,
                'value'  => $value,
                'field'  => $col,
                'origin' => 'client',
            ];
        }
        if ($clientFields) {
            $appFields = array_merge($clientFields, $appFields);
        }

        // 3) Files
        $files = [];
        $app = $app ?? [];
        if (notempty($row['requirement_id'])) {
            $st3 = $pdo->prepare("select * from public.requirements where requirement_id = :rid limit 1");
            $st3->execute([':rid' => $row['requirement_id']]);
            $req = $st3->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($req as $k => $v) {
                if (in_array($k, ['id', 'requirement_id'], true) || !notempty($v)) continue;
                $url = normalize_url((string)$v, $FILE_BASE);
                if ($url === '') continue;
                $label = ucwords(str_replace('_', ' ', $k));
                $path  = parse_url($url, PHP_URL_PATH) ?? '';
                $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $files[] = [
                    'name'  => $label,
                    'url'   => $url,
                    'ext'   => $ext,
                    'field' => $k,
                    'origin' => 'requirements',
                ];
            }
        }

        // 4) Seedling-specific display helpers
        $seedling_attributes = [];
        $seedlingBatchKey = '';
        $seedlingOptions = [];
        $reqTypeNormalized = strtolower(trim((string)($row['request_type'] ?? '')));
        if ($reqTypeNormalized === 'seedling') {
            $optionsStmt = $pdo->query("SELECT seedlings_id, seedling_name FROM public.seedlings ORDER BY seedling_name");
            foreach ($optionsStmt as $opt) {
                $optName = trim((string)($opt['seedling_name'] ?? ''));
                $optId = (string)($opt['seedlings_id'] ?? '');
                if ($optId === '' || $optName === '') {
                    continue;
                }
                $seedlingOptions[] = [
                    'id' => $optId,
                    'name' => $optName,
                ];
            }

            $nameParts = [];
            foreach (['first_name', 'middle_name', 'last_name'] as $k) {
                $v = trim((string)($row[$k] ?? ''));
                if ($v !== '') $nameParts[] = $v;
            }
            if ($nameParts) {
                $seedling_attributes[] = ['label' => 'Applicant', 'value' => implode(' ', $nameParts)];
            }

            $addressParts = [];
            foreach (['sitio_street', 'barangay', 'municipality', 'city'] as $col) {
                $val = trim((string)($row[$col] ?? ''));
                if ($val !== '') {
                    if ($col === 'barangay') {
                        $addressParts[] = 'Brgy. ' . $val;
                    } else {
                        $addressParts[] = $val;
                    }
                }
            }
            if ($addressParts) {
                $seedling_attributes[] = ['label' => 'Address', 'value' => implode(', ', $addressParts)];
            }

            $seedItems = [];
            if (!empty($row['seedl_req_id'])) {
                $batchStmt = $pdo->prepare("SELECT batch_key FROM public.seedling_requests WHERE seedl_req_id = :sid LIMIT 1");
                $batchStmt->execute([':sid' => $row['seedl_req_id']]);
                $batchKey = trim((string)$batchStmt->fetchColumn() ?: '');
                $seedlingBatchKey = $batchKey;

                $seedSql = "
                    SELECT sr.seedl_req_id, sr.seedlings_id, s.seedling_name, COALESCE(sr.quantity, 0) AS qty
                    FROM public.seedling_requests sr
                    JOIN public.seedlings s ON s.seedlings_id = sr.seedlings_id
                    WHERE ";
                if ($batchKey !== '') {
                    $seedSql .= "(sr.batch_key = :batch_key OR sr.seedl_req_id = :sid)";
                } else {
                    $seedSql .= "sr.seedl_req_id = :sid";
                }
                $seedSql .= " ORDER BY s.seedling_name";

                $seedStmt = $pdo->prepare($seedSql);
                $params = [':sid' => $row['seedl_req_id']];
                if ($batchKey !== '') {
                    $params[':batch_key'] = $batchKey;
                }
                $seedStmt->execute($params);
                $seedItems = $seedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $seedTexts = [];
            foreach ($seedItems as $seedRow) {
                $qty = (int)($seedRow['qty'] ?? 0);
                if ($qty > 0) {
                    $seedTexts[] = trim((string)($seedRow['seedling_name'] ?? '')) . ' (' . $qty . ')';
                }
            }
            if ($seedTexts) {
                $seedling_attributes[] = ['label' => 'Requested seedlings', 'value' => implode(', ', $seedTexts)];
            }

            $seedlingRowFields = [];
            foreach ($seedItems as $seedRow) {
                if (empty($seedRow['seedl_req_id']) || empty($seedRow['seedlings_id'])) {
                    continue;
                }
                $seedFieldName = sprintf('seedling_qty_%s_%s', $seedRow['seedl_req_id'], $seedRow['seedlings_id']);
                $seedlingRowFields[] = [
                    'label'  => 'Seedling',
                    'value'  => (string)((int)($seedRow['qty'] ?? 0)),
                    'field'  => $seedFieldName,
                    'origin' => 'seedling_requests',
                    'extra'  => [
                        'seedl_req_id' => $seedRow['seedl_req_id'] ?? '',
                        'seedlings_id' => $seedRow['seedlings_id'] ?? '',
                        'seedlings_old_id' => $seedRow['seedlings_id'] ?? '',
                        'seedling_batch_key' => $seedlingBatchKey,
                    ],
                    'hide_in_view' => true,
                ];
            }
            if ($seedlingRowFields) {
                $appFields = array_merge($seedlingRowFields, $appFields);
            }
        }

        $lumberDetails = null;
        $wildlifeDetails = null;
        // Prepare placeholders so other request-type branches can reference
        // these variables without causing "Undefined variable" warnings.
        $clientDetails = [];
        $approvedDocs = [];
        if ($reqTypeNormalized === 'lumber') {
            $appValues = $app ?? [];
            $lumberAppKeys = [
                'company_name',
                'present_address',
                'location',
                'proposed_place_of_operation',
                'expected_annual_volume',
                'estimated_annual_worth',
                'total_number_of_employees',
                'total_number_of_dependents',
                'intended_market',
                'my_experience_as_alumber_dealer',
                'declaration_name',
                'suppliers_json',
                'permit_number',
                'expiry_date',
                'cr_license_no',
                'buying_from_other_sources',
                'additional_information',
                'applicant_age',
                'is_government_employee',
            ];
            $lumberAppDetails = [];
            foreach ($lumberAppKeys as $key) {
                $value = trim((string)($appValues[$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                $lumberAppDetails[$key] = $value;
            }

            $suppliersList = [];
            if (!empty($appValues['suppliers_json'])) {
                $decodedSuppliers = json_decode((string)$appValues['suppliers_json'], true);
                if (is_array($decodedSuppliers)) {
                    foreach ($decodedSuppliers as $entry) {
                        if (!is_array($entry)) continue;
                        $suppliersList[] = [
                            'name' => trim((string)($entry['name'] ?? '')),
                            'volume' => trim((string)($entry['volume'] ?? '')),
                        ];
                    }
                }
            }

            $clientDetails = [];
            foreach (['first_name', 'middle_name', 'last_name'] as $col) {
                $value = trim((string)($row[$col] ?? ''));
                if ($value !== '') {
                    $clientDetails[$col] = $value;
                }
            }

            $approvedDocs = [];
            $docMappings = [
                'no' => trim((string)($row['approved_doc_no'] ?? '')),
                'date_issued' => trim((string)($row['approved_doc_date_issued'] ?? '')),
                'expiry_date' => trim((string)($row['approved_doc_expiry_date'] ?? '')),
            ];
            foreach ($docMappings as $key => $value) {
                if ($value === '') {
                    continue;
                }
                $approvedDocs[$key] = $value;
            }

            if ($lumberAppDetails || $clientDetails || $approvedDocs) {
                $lumberDetails = [
                    'application' => $lumberAppDetails + ['suppliers' => $suppliersList],
                    'client' => $clientDetails,
                    'approved_docs' => $approvedDocs,
                ];
            }
        }

        // Treecut details: include application-specific attributes and species rows
        $treecutDetails = null;
        if ($reqTypeNormalized === 'treecut') {
            $appValues = $app ?? [];
            $treecutKeys = [
                'ownership',
                'other_ownership',
                'purpose',
                'tax_declaration',
                'lot_no',
                'contained_area',
                'location',
                'registration_number',
            ];
            $treecutAppDetails = [];
            foreach ($treecutKeys as $k) {
                $v = trim((string)($appValues[$k] ?? ''));
                if ($v === '') continue;
                $treecutAppDetails[$k] = $v;
            }

            // species_rows_json stored as JSON string in application_form
            $speciesList = [];
            if (!empty($appValues['species_rows_json'])) {
                $decoded = json_decode((string)$appValues['species_rows_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (!is_array($entry)) continue;
                        $speciesList[] = [
                            'name' => trim((string)($entry['name'] ?? '')),
                            'count' => trim((string)($entry['count'] ?? '')),
                            'volume' => trim((string)($entry['volume'] ?? '')),
                        ];
                    }
                }
            }

            // compute totals from species rows
            $totalCount = 0;
            $totalVolume = 0.0;
            foreach ($speciesList as $s) {
                $totalCount += intval($s['count'] ?? 0);
                $totalVolume += floatval(str_replace(',', '', (string)($s['volume'] ?? 0)));
            }

            if ($treecutAppDetails || $clientDetails || $approvedDocs || $speciesList) {
                $treecutDetails = [
                    'application' => $treecutAppDetails + [
                        'species' => $speciesList,
                        'total_count' => (string)$totalCount,
                        'total_volume' => number_format($totalVolume, 2, '.', ''),
                    ],
                    'client' => $clientDetails,
                    'approved_docs' => $approvedDocs,
                ];
            }
        }

        // Wildlife details: parse application_form.additional_information and requirements
        if ($reqTypeNormalized === 'wildlife') {
            $appValues = $app ?? [];
            $decoded = [];
            if (!empty($appValues['additional_information'])) {
                $tmp = json_decode((string)$appValues['additional_information'], true);
                if (is_array($tmp)) $decoded = $tmp;
            }

            $categories = [];
            if (!empty($decoded['categories']) && is_array($decoded['categories'])) {
                $categories = [
                    'zoo' => !empty($decoded['categories']['zoo']),
                    'botanical_garden' => !empty($decoded['categories']['botanical_garden']),
                    'private_collection' => !empty($decoded['categories']['private_collection']),
                ];
            }

            $animals = [];
            if (!empty($decoded['animals']) && is_array($decoded['animals'])) {
                foreach ($decoded['animals'] as $entry) {
                    if (!is_array($entry)) continue;

                    // Normalise from both old and new formats
                    $commonName = trim((string)(
                        $entry['commonName']
                        ?? $entry['common_name']
                        ?? $entry['species']
                        ?? $entry['name']
                        ?? ''
                    ));

                    $scientificName = trim((string)(
                        $entry['scientificName']
                        ?? $entry['scientific_name']
                        ?? ''
                    ));

                    $quantity = trim((string)(
                        $entry['quantity']
                        ?? $entry['qty']
                        ?? $entry['count']
                        ?? ''
                    ));

                    $remarks = trim((string)($entry['remarks'] ?? ''));

                    $animals[] = [
                        // new keys (what the view + doc generator expect)
                        'commonName'     => $commonName,
                        'scientificName' => $scientificName,
                        'quantity'       => $quantity,
                        'remarks'        => $remarks,

                        // legacy-compatible aliases (so any old JS/logic using them still works)
                        'species' => $commonName,
                        'count'   => $quantity,
                    ];
                }
            }


            $wildAppDetails = [
                'permit_type' => $decoded['permit_type'] ?? ($appValues['type_of_permit'] ?? ''),
                'residence_address' => $decoded['residence_address'] ?? ($appValues['present_address'] ?? ''),
                'telephone_number' => $decoded['telephone_number'] ?? ($appValues['telephone_number'] ?? ''),

                // Name / address / telephone of establishment
                'establishment_name' => $decoded['establishment_name']
                    ?? ($decoded['name_of_establishment'] ?? ''),
                'establishment_address' => $decoded['establishment_address']
                    ?? ($decoded['address_of_establishment'] ?? ''),
                'establishment_telephone' => $decoded['establishment_telephone']
                    ?? ($decoded['establishment_telephone_number'] ?? ''),

                'postal_address' => $decoded['postal_address'] ?? '',
                'wfp_number' => $decoded['wfp_number'] ?? ($appValues['permit_number'] ?? ''),
                'issue_date' => $decoded['issue_date'] ?? '',
                'generated_application_doc' => $decoded['generated_application_doc'] ?? null,
                'client_resolution' => $decoded['client_resolution'] ?? null,
            ];


            // Include any requirement/file entries that were collected earlier in $files
            $reqFiles = [];
            if (!empty($files) && is_array($files)) {
                foreach ($files as $f) {
                    // keep label/url pairs where available
                    if (is_array($f) && (!empty($f['label']) || !empty($f['url']))) {
                        $reqFiles[] = $f;
                    }
                }
            }

            $wildlifeDetails = [
                'application' => $wildAppDetails,
                'categories' => $categories,
                'animals' => $animals,
                'requirements_files' => $reqFiles,
                'client' => $clientDetails,
                'approved_docs' => $approvedDocs,
            ];
            // Keep original additional_information (decoded) for client-side editors
            $meta_additional_information = $decoded;
        }

        if (!empty($seedling_attributes)) {
            $extras = array_map(function ($item) {
                return [
                    'label' => $item['label'] ?? '',
                    'value' => (string)($item['value'] ?? ''),
                    'field' => null,
                    'origin' => 'seedling',
                ];
            }, $seedling_attributes);
            $appFields = array_merge($extras, $appFields);
        }

        // 5) Download link rule:
        // - For seedling request_type the download is available when status is 'approved'
        // - For other request types the download is available when status is 'released'
        // Approved document is taken from approved_docs.approved_document
        $status       = strtolower((string)($row['approval_status'] ?? ''));
        $request_type = strtolower((string)($row['request_type'] ?? ''));
        $downloadUrl  = '';
        $shouldHaveDownload = false;
        if ($request_type === 'seedling') {
            $shouldHaveDownload = ($status === 'approved');
        } else {
            $shouldHaveDownload = ($status === 'released');
        }
        if ($shouldHaveDownload) {
            $downloadUrl = normalize_url((string)($row['approved_document'] ?? ''), $FILE_BASE);
        }

        // 5) Echo once
        echo json_encode([
            'ok'   => true,
            'meta' => [
                'client'             => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'first_name'         => ($row['first_name'] ?? ''),
                'last_name'          => ($row['last_name'] ?? ''),
                'client_id'          => ($row['client_id'] ?? null),
                'request_type'       => $row['request_type'] ?? '',
                'permit_type'        => $row['permit_type'] ?? 'none',
                'status'             => $status ?: 'pending',
                'reason'             => $row['rejection_reason'] ?? '',
                'submitted_at'       => $row['submitted_at'] ?? null,
                'approval_id'        => $row['approval_id'] ?? null,
                'seedl_req_id'       => $row['seedl_req_id'] ?? null,
                'seedling_batch_key' => $seedlingBatchKey,
                'seedling_options'   => $seedlingOptions,
                'download_url'       => $downloadUrl,
                'original_additional_information' => $meta_additional_information ?? [],
            ],
            'lumber_details' => $lumberDetails,
            'treecut_details' => $treecutDetails,
            'wildlife_details' => $wildlifeDetails,
            'application' => $appFields,
            'files'       => $files
        ]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS AJAX] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}


/* ---------- AJAX: notifications mark read / all read ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_all_read') {
    header('Content-Type: application/json');
    try {
        $st = $pdo->prepare('update public.notifications set is_read = true where "to" = :uid and is_read = false');
        $st->execute([':uid' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS mark_all_read] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_read') {
    header('Content-Type: application/json');
    $notifId = $_GET['notif_id'] ?? '';
    if (!$notifId) {
        echo json_encode(['ok' => false, 'error' => 'missing notif_id']);
        exit;
    }
    try {
        $st = $pdo->prepare('update public.notifications set is_read = true where notif_id = :nid and "to" = :uid');
        $st->execute([':nid' => $notifId, ':uid' => $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[APP-STATUS mark_read] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

/* ---------- PAGE DATA: approvals (all for this user) ---------- */
$rows = [];
try {
    // Join requirements for application_form; LATERAL join to get latest approved_docs for each approval
    $st = $pdo->prepare("
        select
          a.approval_id,
          lower(coalesce(nullif(btrim(a.request_type), ''), ''))      as request_type,
          lower(coalesce(nullif(btrim(a.permit_type), ''), 'none'))   as permit_type,
          lower(coalesce(nullif(btrim(a.approval_status), ''), 'pending')) as approval_status,
          a.submitted_at,
          a.application_id,
          a.requirement_id,
          c.first_name,
          r.application_form as req_application_form,
          ad.approved_document
        from public.approval a
        left join public.client c on c.client_id = a.client_id
        left join public.requirements r on r.requirement_id = a.requirement_id
        left join lateral (
            select d.approved_document
            from public.approved_docs d
            where d.approval_id = a.approval_id
            order by d.id desc
            limit 1
        ) ad on true
        where c.user_id = :uid
        order by a.submitted_at desc nulls last, a.approval_id desc
        limit 200
    ");
    $st->execute([':uid' => $_SESSION['user_id']]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[APP-STATUS FETCH] ' . $e->getMessage());
}
$hasRows = count($rows) > 0;

/* ---------- PAGE DATA: notifications (to = current user_id) ---------- */
$notifs = [];
$unreadCount = 0;
try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[APP-STATUS NOTIFS] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
    <title>Application Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --transition: all .2s ease;
        }

        /* === Header (copied/styled like user_home.php) === */
        body {
            background: #f9f9f9;
            color: #111827;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            padding-top: 100px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0 30px;
            height: 58px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
        }

        .logo {
            height: 45px;
            display: flex;
            align-items: center;
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%;
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition);
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-item {
            position: relative;
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgb(233, 255, 242);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: black;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0;
        }

        .dropdown-menu.center {
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(10px);
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent;
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }

        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.05rem;
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px;
        }

        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: .9rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.05);
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start;
            background: #fff;
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, .05);
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-icon {
            margin-right: 12px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--primary-color);
        }

        .notification-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .notification-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        /* === Page content styles (table, modal) — unchanged aside from spacing === */
        .main-content {
            padding: 0px 16px 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 1rem 0;

        }

        .title-wrap h1 {
            margin: 0;
            font-size: 30px;
            color: #2b6625;
        }

        .filters {
            display: flex;
            gap: .75rem;
            align-items: flex-end;
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

        .btn.small {
            padding: 7px 10px;
            font-size: .92rem
        }

        .btn.primary {
            background: #2b6625;
            border-color: #2b6625
        }

        .btn.ghost {
            background: #fff;
            color: #111827
        }

        .toast {
            position: fixed;
            top: 30px;
            left: 50%;
            min-width: 240px;
            max-width: min(340px, calc(100vw - 48px));
            background: var(--primary-color);
            color: var(--white);
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
            opacity: 0;
            transform: translate(-50%, -10px);
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            z-index: 1100;
            font-size: .95rem;
            text-align: center;
        }

        .toast.show {
            opacity: 1;
            transform: translate(-50%, 0);
            pointer-events: auto;
        }

        .toast-success {
            background: var(--primary-color);
        }

        .toast-error {
            background: #dc2626;
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
            font-size: .85rem
        }

        .pill.neutral {
            background: #f3f4f6;
            color: #374151
        }

        .badge.status {
            position: static;
            width: auto;
            height: auto;
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 7px;
            font-size: .82rem;
            background: #e5e7eb;
            color: #111827
        }

        .badge.approved {
            background: #ecfdf5;
            color: #065f46
        }

        .badge.pending {
            background: #fff7ed;
            color: #9a3412
        }

        .badge.rejected {
            background: #fef2f2;
            color: #991b1b
        }

        .empty-row {
            text-align: center;
            color: #6b7280;
        }

        /* Modal + preview drawer */
        .modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050
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
            width: min(1280px, 98vw);
            max-height: 92vh;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2);
            overflow: auto;
        }

        #editPane {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            overflow: auto;
            /* key line */
        }

        #editPane .edit-actions {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            padding: 12px 16px;
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

        .alert {
            margin: 10px 16px 0;
            padding: 12px 14px;
            background: #fff1f1;
            color: #9b1c1c;
            border: 1px solid #ffdede;
            border-radius: 10px
        }

        .modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
            min-height: 0
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
            padding: 12px 16px 28px;
            overflow: auto;
            flex: 1;
            min-height: 0
        }

        .edit-pane {
            padding: 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: none;
        }

        .edit-pane:not(.hidden) {
            display: block;
        }

        .edit-sections {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .edit-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 50vh;
            overflow: auto;
        }

        .edit-field-list,
        .edit-file-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .edit-form-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .edit-form-row label {
            font-weight: 600;
            font-size: .95rem;
            color: #374151;
        }

        .edit-form-row input[type="text"],
        .edit-form-row input[type="email"],
        .edit-form-row input[type="date"],
        .edit-form-row input[type="number"],
        .edit-form-row textarea,
        .edit-form-row select,
        .edit-form-row input[type="file"] {
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: .95rem;
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .seedling-row .seedling-inputs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .seedling-row .seedling-inputs select,
        .seedling-row .seedling-inputs .seedling-qty {
            flex: 1;
            min-width: 140px;
        }

        .seedling-qty {
            max-width: 150px;
        }

        .seedling-row-removed {
            display: none;
        }

        .seedling-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .seedling-actions.hidden {
            display: none;
        }

        .seedling-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #10b981;
            background: #fff;
            color: #10b981;
            font-weight: 600;
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease;
        }

        .seedling-add-btn:hover {
            background: #ecfdf5;
            border-color: #059669;
        }

        .seedling-remove-btn {
            border: none;
            background: none;
            color: #dc2626;
            font-size: .85rem;
            cursor: pointer;
            padding: 4px 8px;
            align-self: flex-start;
        }

        .seedling-remove-btn:hover {
            text-decoration: underline;
        }

        .edit-form-row textarea {
            min-height: 90px;
            resize: vertical;
        }

        .edit-form-row input:focus,
        .edit-form-row textarea:focus,
        .edit-form-row select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .edit-current-file {
            font-size: .85rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .edit-current-file a {
            color: #2563eb;
            text-decoration: none;
        }

        .edit-message {
            font-size: .9rem;
            color: #9b1c1c;
            padding: 8px 0;
            min-height: 18px;
        }

        .edit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
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

        .lumber-suppliers-section {
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        .lumber-suppliers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 8px;
        }

        .lumber-suppliers-title {
            font-weight: 600;
            color: #111827;
        }

        .lumber-suppliers-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #10b981;
            background: #fff;
            color: #10b981;
            font-weight: 600;
            cursor: pointer;
            transition: border-color .2s ease, background .2s ease;
            font-size: 0.85rem;
        }

        .lumber-suppliers-add:hover {
            background: #ecfdf5;
            border-color: #059669;
        }

        .lumber-suppliers-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .lumber-supplier-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .lumber-supplier-inputs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .lumber-supplier-inputs input {
            flex: 1;
            min-width: 140px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0 10px;
            font-size: 0.9rem;
            background: #fff;
        }

        .lumber-supplier-remove {
            border: none;
            background: none;
            color: #dc2626;
            font-size: 0.85rem;
            padding: 4px 8px;
            align-self: flex-start;
            cursor: pointer;
        }

        .lumber-supplier-remove i {
            margin-right: 4px;
        }

        .lumber-details {
            margin-bottom: 16px;
        }

        .lumber-details .lumber-section-title {
            font-size: .95rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #111827;
        }

        .field-image {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 6px;
            background: #fff
        }

        .field-image img {
            display: block;
            max-width: 100%;
            height: auto
        }

        /* Species editor / suppliers tables: keep within left pane, avoid horizontal scrolling */
        .species-editor,
        .suppliers-table,
        .species-table {
            max-width: 100%;
            overflow-x: auto;
            box-sizing: border-box;
        }

        .species-table {
            width: 100%;
            table-layout: fixed;
            /* prevent long inputs from expanding the whole table */
            border-collapse: collapse;
        }

        .species-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .species-table input[type="text"],
        .species-table input[type="number"] {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
        }

        /* Constrain species name input so it doesn't stretch too wide */
        .species-name {
            max-width: 420px;
            width: 100%;
        }

        /* Keep the control column (Remove button) narrow and prevent it from causing overflow */
        .species-table td:last-child {
            width: 56px;
            /* fixed narrow column for control */
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
        }

        .species-remove-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 36px;
            padding: 6px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
            cursor: pointer;
            color: #dc2626;
            font-size: 0.95rem;
        }

        .species-remove-btn i {
            color: #dc2626;
            font-size: 0.95rem;
        }

        .species-add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #065f46;
            font-weight: 700;
            cursor: pointer;
            width: auto;
            max-width: 240px;
        }

        /* Center the add button under the label */
        .species-editor>.species-add-btn {
            display: inline-flex;
            margin: 8px auto 12px;
        }

        .seedling-attrs {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            padding: 10px;
            margin-bottom: 12px;
            box-shadow: inset 0 0 0 1px #f5f5f5;
        }

        .seedling-attrs-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #065f46;
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

        .preview-drawer {
            position: fixed;
            top: 5%;
            right: 1%;
            width: min(640px, 96vw);
            height: 90vh;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2)
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6
        }

        .truncate {
            max-width: 75%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .preview-body {
            position: relative;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            scrollbar-gutter: stable both-edges
        }

        #previewImageWrap,
        #previewPdfWrap,
        #previewFrameWrap {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: hidden
        }

        .preview-body iframe,
        .preview-body object,
        .preview-body embed,
        #previewImage {
            display: block;
            width: 100%;
            height: 100%;
            border: 0
        }

        #previewImage {
            height: 100%
        }

        .hidden {
            display: none !important
        }

        @media (max-width:980px) {
            .modal-content {
                grid-template-columns: 1fr
            }

            .pane.left {
                border-right: 0;
                border-bottom: 1px solid #f3f4f6
            }

            .edit-sections {
                grid-template-columns: 1fr;
            }

            .meta-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .defrow {
                grid-template-columns: 1fr
            }
        }

        /* Active highlight for "Application Status" in the app menu (this page only) */
        .dropdown-menu a[href="application_status.php"] {
            background: var(--light-gray);
            color: var(--primary-color);
            font-weight: 700;
            padding-left: 30px;
            /* match hover offset, avoid jump */
            border-left: 4px solid var(--primary-color);
        }

        .dropdown-menu a[href="application_status.php"] i {
            color: var(--primary-dark) !important;
        }

        .dropdown-menu a[href="application_status.php"]:hover {
            background: var(--light-gray);
            /* keep stable on hover */
            padding-left: 30px;
        }

        /* ================= Skeleton (bone) UI ================= */
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
            margin: 12px 0 6px
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

        @keyframes spin {
            to {
                transform: rotate(1turn)
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo"><a href="user_home.php"><img src="seal.png" alt="Site Logo"></a></div>

        <!-- Mobile menu toggle (optional) -->
        <button class="mobile-toggle" style="display:none"><i class="fas fa-bars"></i></button>

        <!-- Navigation (copied from home) -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="user_reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Report Incident</span></a>
                    <a href="useraddseed.php" class="dropdown-item"><i class="fas fa-seedling"></i><span>Request Seedlings</span></a>
                    <a href="useraddwild.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="useraddtreecut.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting Permit</span></a>
                    <a href="useraddlumber.php" class="dropdown-item"><i class="fas fa-boxes"></i><span>Lumber Dealers Permit</span></a>
                    <a href="useraddwood.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing Permit</span></a>
                    <a href="useraddchainsaw.php" class="dropdown-item active-page"><i class="fas fa-tools"></i><span>Chainsaw Permit</span></a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>
                </div>
            </div>

            <!-- Notifications (LIVE) -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge" id="notifBadge"><?= h((string)$unreadCount) ?></span>
                    <?php endif; ?>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>

                    <?php if (!$notifs): ?>
                        <div class="notification-item">
                            <div class="notification-content">
                                <div class="notification-title">No record found</div>
                                <div class="notification-message">There are no notifications.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifs as $n): ?>
                            <?php
                            $unread = empty($n['is_read']);
                            $ts = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                            $title = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');

                            // Strip any rejection reason from the message for notifications UI
                            $msg = strip_reason_from_message($n['message'] ?? '');
                            if ($msg === '') {
                                $msg = 'There’s an update.';
                            }
                            ?>
                            <div class="notification-item <?= $unread ? 'unread' : '' ?>">
                                <a href="#" class="notification-link"
                                    data-notif-id="<?= h((string)$n['notif_id']) ?>"
                                    <?= $n['approval_id'] ? 'data-approval-id="' . h((string)$n['approval_id']) . '"' : '' ?>
                                    <?= $n['incident_id'] ? 'data-incident-id="' . h((string)$n['incident_id']) . '"' : '' ?>>
                                    <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= h($title) ?></div>
                                        <div class="notification-message"><?= h($msg) ?></div>
                                        <div class="notification-time" data-ts="<?= h((string)$ts) ?>">just now</div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="notification-footer">
                        <a href="user_notification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <section class="page-header">
            <div class="title-wrap">
                <h1>Application Status</h1>
                <p class="subtitle">Your permit requests</p>
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
                    <label for="filterReqType">Request Type</label>
                    <select id="filterReqType" class="input">
                        <option value="">All</option>
                    </select>
                </div>

                <div class="filter-row">
                    <label for="filterPermitType">Permit Type</label>
                    <select id="filterPermitType" class="input">
                        <option value="">All</option>
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
                    <colgroup>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col style="width:130px;"> <!-- <= adjust this number to taste -->
                    </colgroup>
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
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $st = strtolower((string)($r['approval_status'] ?? 'pending'));
                            $cls = $st === 'approved' ? 'approved' : ($st === 'rejected' ? 'rejected' : 'pending');
                            $rt  = strtolower((string)($r['request_type'] ?? ''));
                            $pt  = strtolower((string)($r['permit_type'] ?? 'none'));

                            // Compute download URL using rule:
                            // - seedling requests: show download when status === 'approved'
                            // - other requests: show download when status === 'released'
                            // Download link is taken from approved_docs.approved_document (if available)
                            $downloadUrl = '';
                            $showDownload = false;
                            if ($rt === 'seedling') {
                                $showDownload = ($st === 'approved');
                            } else {
                                $showDownload = ($st === 'released');
                            }
                            if ($showDownload) {
                                $downloadUrl = normalize_url((string)($r['approved_document'] ?? ''), $FILE_BASE);
                            }
                            ?>
                            <tr
                                data-approval-id="<?= h($r['approval_id']) ?>"
                                data-status="<?= h($st) ?>"
                                data-request-type="<?= h($rt) ?>"
                                data-permit-type="<?= h($pt) ?>"
                                data-client="<?= h($r['first_name'] ?? '') ?>"
                                data-download-url="<?= h($downloadUrl) ?>">
                                <td><?= h($r['first_name'] ?? '—') ?></td>
                                <td><span class="pill"><?= h($rt ?: '—') ?></span></td>
                                <td><span class="pill neutral"><?= h($pt) ?></span></td>
                                <td><span class="badge status <?= $cls ?>"><?= ucfirst($st) ?></span></td>
                                <td><?= h($r['submitted_at'] ? date('Y-m-d H:i', strtotime((string)$r['submitted_at'])) : '—') ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap; width: fit-content;">
                                        <button class="btn small" data-action="view"><i class="fas fa-eye"></i> View</button>
                                        <?php if ($showDownload): ?>
                                            <!-- <button class="btn small" data-action="download" <?= $downloadUrl ? '' : 'disabled title="No file available yet"' ?>>
                                                <i class="fas fa-download"></i> Download
                                            </button> -->
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Always render a "No record found" row so filtering can show it too -->
                        <tr id="noRows" class="empty-row" style="<?= $hasRows ? 'display:none' : '' ?>">
                            <td colspan="6" style="padding:1rem;">No record found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <div id="loadingIndicator" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9998">
        <div class="card" style="background:#fff;padding:18px 22px;border-radius:10px;display:flex;gap:10px;align-items:center;">
            <span class="loader" style="--loader-size:18px;width:var(--loader-size);height:var(--loader-size);border:2px solid #ddd;border-top-color:#2b6625;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span>
            <span id="loadingMessage">Working...</span>
        </div>
    </div>
    <div id="toast" class="toast" role="status" aria-live="polite" aria-hidden="true"></div>


    <!-- ===== TWO-PANE MODAL ===== -->
    <div id="viewModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-panel" role="document">
            <div class="modal-header">
                <h3 id="modalTitle">Request Details</h3>
                <div style="display:flex;gap:8px;align-items:center">
                    <button class="btn small primary hidden" id="btnDownloadIssued" type="button" aria-hidden="true">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="btn small ghost hidden" id="btnEditPending" type="button" aria-hidden="true">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn primary hidden" id="btnRequestAgain" type="button" aria-hidden="true">
                        <i class="fas fa-rotate-right"></i> Request again
                    </button>
                    <button class="btn small ghost hidden" id="btnCancelEdit" type="button" aria-hidden="true">
                        Cancel
                    </button>
                    <button class="btn small primary hidden" id="btnSaveEdit" type="submit" form="editForm" aria-hidden="true">
                        Save Changes
                    </button>
                    <button class="icon-btn" type="button" aria-label="Close" data-close-modal>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>


            <!-- SKELETON while loading -->
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
                        </div>
                    </div>
                    <div class="s-pane">
                        <div class="s-title sk md"></div>
                        <div class="s-list">
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                            <div class="sk row"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END SKELETON -->

            <div class="meta-strip">
                <div class="meta"><span class="label">Client</span><span id="metaClientName" class="value">—</span></div>
                <div class="meta"><span class="label">Request Type</span><span id="metaRequestType" class="pill">—</span></div>
                <div class="meta"><span class="label">Permit Type</span><span id="metaPermitType" class="pill neutral">—</span></div>
                <div class="meta"><span class="label">Status</span><span id="metaStatus" class="badge status">—</span></div>
            </div>

            <div id="rejectBanner" class="alert hidden"></div>

            <div class="modal-content">
                <section class="pane left">
                    <h4 class="pane-title"><i class="fas fa-list"></i> Application Form</h4>
                    <div id="formScroll" class="scroll-area">
                        <div id="lumberSection" class="lumber-details hidden">
                            <div class="lumber-section-title">Lumber Request Details</div>
                            <dl id="lumberDetailsList" class="deflist"></dl>
                        </div>
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

            <div id="editPane" class="edit-pane hidden">
                <form id="editForm" novalidate>
                    <input type="hidden" name="approval_id" id="editApprovalId" value="">
                    <input type="hidden" name="request_type" id="editRequestType" value="">
                    <input type="hidden" name="permit_type" id="editPermitType" value="">
                    <div class="edit-sections">
                        <section class="edit-section">
                            <h4 class="pane-title"><i class="fas fa-edit"></i> Edit Application Form</h4>
                            <div id="seedlingActions" class="seedling-actions hidden">
                                <button type="button" id="btnAddSeedling" class="seedling-add-btn">
                                    <i class="fas fa-plus"></i>
                                    Add another seedling
                                </button>
                            </div>
                            <div id="seedlingFields" class="edit-field-list seedling-field-list"></div>
                            <div id="lumberSuppliersSection" class="lumber-suppliers-section hidden">
                                <div class="lumber-suppliers-header">
                                    <span class="lumber-suppliers-title">Suppliers (Lumber only)</span>
                                    <button type="button" id="btnAddLumberSupplier" class="lumber-suppliers-add">
                                        <i class="fas fa-plus"></i>
                                        Add supplier
                                    </button>
                                </div>
                                <div id="lumberSuppliersList" class="lumber-suppliers-list"></div>
                                <input type="hidden" name="fields[suppliers_json]" id="suppliersJsonField" value="">
                                <input type="hidden" name="field_origins[suppliers_json]" value="application_form">
                            </div>
                            <div id="editFields" class="edit-field-list"></div>
                            <div id="deletedSeedlings"></div>
                        </section>
                        <section class="edit-section">
                            <h4 class="pane-title"><i class="fas fa-paperclip"></i> Update Documents</h4>
                            <div id="editFiles" class="edit-file-list"></div>
                        </section>
                    </div>
                    <div id="editMessage" class="edit-message" role="alert" aria-live="polite"></div>
                </form>
            </div>
        </div>

        <!-- Drawer -->
        <div id="filePreviewDrawer" class="preview-drawer hidden" aria-live="polite" aria-hidden="true">
            <div class="preview-header">
                <span id="previewTitle" class="truncate">Document</span>
                <button class="icon-btn" type="button" aria-label="Close preview" data-close-preview><i class="fas fa-times"></i></button>
            </div>
            <div class="preview-body">
                <div id="previewImageWrap" class="hidden"><img id="previewImage" alt="Preview"></div>
                <div id="previewPdfWrap" class="hidden">
                    <object id="previewPdf" type="application/pdf" data="" aria-label="PDF preview">
                        <iframe id="previewPdfFallback" src="" title="PDF preview" loading="lazy"></iframe>
                    </object>
                </div>
                <div id="previewFrameWrap" class="hidden"><iframe id="previewFrame" title="Document preview" loading="lazy"></iframe></div>
                <div id="previewLinkWrap" class="hidden" style="padding:16px;text-align:center">
                    <p class="muted">Preview not available. Open or download the file instead.</p>
                    <a id="previewDownload" class="btn" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open / Download</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* --- Dropdown hover like home page --- */
            document.querySelectorAll('.dropdown').forEach(dd => {
                const menu = dd.querySelector('.dropdown-menu');
                dd.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                });
                dd.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    });
                }
            });

            /* --- Build filter options from table --- */
            const reqTypeSet = new Set(),
                permitTypeSet = new Set();
            document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                if (tr.id === 'noRows') return;
                const rt = (tr.dataset.requestType || '').trim();
                const pt = (tr.dataset.permitType || '').trim();
                if (rt) reqTypeSet.add(rt);
                if (pt) permitTypeSet.add(pt);
            });
            const reqTypeSelect = document.getElementById('filterReqType');
            const permitTypeSelect = document.getElementById('filterPermitType');
            [...reqTypeSet].sort().forEach(v => reqTypeSelect.insertAdjacentHTML('beforeend', `<option value="${v}">${v}</option>`));
            [...permitTypeSet].sort().forEach(v => permitTypeSelect.insertAdjacentHTML('beforeend', `<option value="${v}">${v}</option>`));

            /* --- Modal utilities + state --- */
            const btnRequestAgain = document.getElementById('btnRequestAgain');
            const btnDownloadIssued = document.getElementById('btnDownloadIssued');
            const btnEditPending = document.getElementById('btnEditPending');
            const modalEl = document.getElementById('viewModal');
            const modalSkeleton = document.getElementById('modalSkeleton');
            const metaStripEl = document.querySelector('.meta-strip');
            const modalContent = document.querySelector('.modal-content');
            const editPane = document.getElementById('editPane');
            const editForm = document.getElementById('editForm');
            const editFieldsWrap = document.getElementById('editFields');
            const editFilesWrap = document.getElementById('editFiles');
            const editMessage = document.getElementById('editMessage');
            const seedlingActions = document.getElementById('seedlingActions');
            const btnAddSeedling = document.getElementById('btnAddSeedling');
            const seedlingFieldsWrap = document.getElementById('seedlingFields');
            const deletedSeedlingsWrap = document.getElementById('deletedSeedlings');
            const lumberSection = document.getElementById('lumberSection');
            const lumberDetailsList = document.getElementById('lumberDetailsList');
            const lumberSuppliersSection = document.getElementById('lumberSuppliersSection');
            const lumberSuppliersList = document.getElementById('lumberSuppliersList');
            const btnAddLumberSupplier = document.getElementById('btnAddLumberSupplier');
            const suppliersJsonField = document.getElementById('suppliersJsonField');
            const btnCancelEdit = document.getElementById('btnCancelEdit');
            const btnSaveEdit = document.getElementById('btnSaveEdit');
            const toastEl = document.getElementById('toast');
            const editApprovalInput = document.getElementById('editApprovalId');
            const editRequestTypeInput = document.getElementById('editRequestType');
            const editPermitTypeInput = document.getElementById('editPermitType');
            let toastTimeout = null;

            function showModalSkeleton() {
                modalSkeleton.classList.remove('hidden');
                metaStripEl.classList.add('hidden');
                modalContent.classList.add('hidden');
                editPane?.classList.add('hidden');
                btnRequestAgain.classList.add('hidden');
                if (btnDownloadIssued) btnDownloadIssued.classList.add('hidden');
                editMode = false;
                resetEditFormContents();
            }

            function hideModalSkeleton() {
                modalSkeleton.classList.add('hidden');
                metaStripEl.classList.remove('hidden');
                if (!editMode) modalContent.classList.remove('hidden');
            }

            let cachedDetails = null;
            let editMode = false;
            let seedlingRowCounter = 0;

            function showToast(message, {
                type = 'success',
                duration = 4000
            } = {}) {
                if (!toastEl) return;
                const variant = type === 'error' ? 'error' : 'success';
                toastEl.textContent = message;
                toastEl.className = `toast toast-${variant}`;
                toastEl.setAttribute('aria-hidden', 'false');
                toastEl.classList.remove('show');
                // Force reflow so the animation retriggers when called in succession.
                void toastEl.offsetWidth;
                toastEl.classList.add('show');
                if (toastTimeout) clearTimeout(toastTimeout);
                toastTimeout = setTimeout(() => {
                    toastEl.classList.remove('show');
                    toastEl.setAttribute('aria-hidden', 'true');
                }, Math.max(1000, duration));
            }

            function resetEditFormContents() {
                if (editFieldsWrap) editFieldsWrap.innerHTML = '';
                if (editFilesWrap) editFilesWrap.innerHTML = '';
                if (editMessage) editMessage.textContent = '';
                if (seedlingActions) seedlingActions.classList.add('hidden');
                if (btnAddSeedling) {
                    btnAddSeedling.disabled = true;
                    btnAddSeedling.setAttribute('aria-hidden', 'true');
                }
                if (deletedSeedlingsWrap) deletedSeedlingsWrap.innerHTML = '';
                if (seedlingFieldsWrap) seedlingFieldsWrap.innerHTML = '';
                // remove dynamic species JSON hidden field if present
                if (speciesJsonField && speciesJsonField.parentNode) {
                    speciesJsonField.parentNode.removeChild(speciesJsonField);
                }
                speciesJsonField = null;
                speciesRows = [];
                editForm?.reset();
            }

            function exitEditMode(silent = false) {
                if (!editPane) return;
                editMode = false;
                resetEditFormContents();
                editPane.classList.add('hidden');
                if (!silent && modalContent) modalContent.classList.remove('hidden');
                const meta = (cachedDetails && cachedDetails.meta) || {};
                if (btnEditPending) {
                    const canEdit = (meta.status || '').toLowerCase() === 'pending';
                    btnEditPending.classList.toggle('hidden', !canEdit);
                    btnEditPending.disabled = !canEdit;
                    btnEditPending.setAttribute('aria-hidden', canEdit ? 'false' : 'true');
                }
                if (btnCancelEdit) {
                    btnCancelEdit.classList.add('hidden');
                    btnCancelEdit.setAttribute('aria-hidden', 'true');
                    btnCancelEdit.disabled = true;
                }
                if (btnSaveEdit) {
                    btnSaveEdit.classList.add('hidden');
                    btnSaveEdit.setAttribute('aria-hidden', 'true');
                    btnSaveEdit.disabled = true;
                }
            }

            function createInputId(field) {
                return `edit-field-${field.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
            }

            function normalizeDateValue(value) {
                const trimmed = (value || '').trim();
                if (!trimmed) return '';
                if (/^\d{4}-\d{2}-\d{2}/.test(trimmed)) return trimmed.slice(0, 10);
                const parsed = Date.parse(trimmed);
                if (Number.isNaN(parsed)) return '';
                try {
                    return new Date(parsed).toISOString().slice(0, 10);
                } catch {
                    return '';
                }
            }

            const BLOCKED_EDIT_FIELDS = new Set([
                'application_id', 'application id',
                'application_for', 'application for',
                'type_of_permit', 'type of permit',
                'first_name', 'middle_name', 'last_name'
            ]);

            function isBlocked(field) {
                // check both DB name and label text
                const f = (field?.field || '').toLowerCase().trim();
                const l = (field?.label || '').toLowerCase().trim();
                return BLOCKED_EDIT_FIELDS.has(f) || BLOCKED_EDIT_FIELDS.has(l);
            }

            function buildSeedlingFieldName(field) {
                if (field && typeof field.field === 'string' && field.field.trim() !== '') {
                    return field.field;
                }
                seedlingRowCounter += 1;
                return `seedling_qty_new_${Date.now()}_${seedlingRowCounter}`;
            }

            function getSeedlingOptions(field) {
                if (field?.extra && Array.isArray(field.extra.seedling_options) && field.extra.seedling_options.length) {
                    return field.extra.seedling_options;
                }
                const metaOptions = (cachedDetails?.meta?.seedling_options) || [];
                return Array.isArray(metaOptions) ? metaOptions : [];
            }

            function renderSeedlingField(field) {
                const seedlingWrap = seedlingFieldsWrap || editFieldsWrap;
                if (!seedlingWrap) return;
                const fieldName = buildSeedlingFieldName(field);
                const row = document.createElement('div');
                row.className = 'edit-form-row seedling-row';

                const labelEl = document.createElement('label');
                const labelText = field.label || 'Seedling';
                const qtyInputId = createInputId(fieldName);
                labelEl.setAttribute('for', qtyInputId);
                labelEl.textContent = labelText;
                row.appendChild(labelEl);

                const controls = document.createElement('div');
                controls.className = 'seedling-inputs';

                const markRowRemoved = () => {
                    if (row.classList.contains('seedling-row-removed')) return;
                    metaPayload.is_deleted = true;
                    metaInput.value = JSON.stringify(metaPayload);
                    if (deletedSeedlingsWrap && metaPayload.seedl_req_id) {
                        const existing = row.dataset.deletedMarker === '1';
                        if (!existing) {
                            const marker = document.createElement('input');
                            marker.type = 'hidden';
                            marker.name = 'deleted_seedlings[]';
                            const deleteSeedlingId = metaPayload.seedlings_old_id || metaPayload.seedlings_id || '';
                            const payload = {
                                seedl_req_id: metaPayload.seedl_req_id,
                                seedlings_id: deleteSeedlingId,
                                batch_key: metaPayload.seedling_batch_key || ''
                            };
                            marker.value = JSON.stringify(payload);
                            deletedSeedlingsWrap.appendChild(marker);
                            row.dataset.deletedMarker = '1';
                        }
                    }
                    row.classList.add('seedling-row-removed');
                };

                const select = document.createElement('select');
                select.className = 'seedling-select';
                const options = getSeedlingOptions(field);
                let selectedSeedlingId = field?.extra?.seedlings_id ? String(field.extra.seedlings_id) : '';
                if (!selectedSeedlingId && options.length) {
                    selectedSeedlingId = options[0].id;
                }

                if (options.length) {
                    options.forEach(opt => {
                        const optionEl = document.createElement('option');
                        optionEl.value = opt.id;
                        optionEl.textContent = opt.name;
                        if (opt.id === selectedSeedlingId) {
                            optionEl.selected = true;
                        }
                        select.appendChild(optionEl);
                    });
                } else {
                    const optionEl = document.createElement('option');
                    optionEl.value = '';
                    optionEl.textContent = 'No seedlings available';
                    select.appendChild(optionEl);
                    select.disabled = true;
                }

                const qtyInput = document.createElement('input');
                qtyInput.type = 'number';
                qtyInput.min = '0';
                qtyInput.step = '1';
                qtyInput.id = qtyInputId;
                qtyInput.name = `fields[${fieldName}]`;
                qtyInput.value = field.value ?? '';
                qtyInput.placeholder = 'Quantity';
                qtyInput.className = 'seedling-qty';

                controls.appendChild(select);
                controls.appendChild(qtyInput);
                row.appendChild(controls);

                const originInput = document.createElement('input');
                originInput.type = 'hidden';
                originInput.name = `field_origins[${fieldName}]`;
                originInput.value = 'seedling_requests';
                row.appendChild(originInput);

                const fallbackBatchKey = (cachedDetails?.meta?.seedling_batch_key) || (cachedDetails?.meta?.seedl_req_id) || '';
                const currentBatchKey = field?.extra?.seedling_batch_key || fallbackBatchKey;
                const metaInput = document.createElement('input');
                metaInput.type = 'hidden';
                metaInput.name = `field_meta[${fieldName}]`;
                const metaPayload = {
                    seedl_req_id: field?.extra?.seedl_req_id ? field.extra.seedl_req_id : '',
                    seedlings_id: selectedSeedlingId,
                    seedlings_old_id: field?.extra?.seedlings_id ? field.extra.seedlings_id : '',
                    seedling_batch_key: currentBatchKey,
                };
                if (field?.extra?.is_new) {
                    metaPayload.is_new = true;
                }
                metaInput.value = JSON.stringify(metaPayload);
                row.appendChild(metaInput);

                select.addEventListener('change', () => {
                    metaPayload.seedlings_id = select.value || '';
                    metaInput.value = JSON.stringify(metaPayload);
                });

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'seedling-remove-btn';
                removeBtn.textContent = 'Remove';
                const isNewRow = Boolean(field?.extra?.is_new);
                if (isNewRow) {
                    removeBtn.addEventListener('click', () => {
                        row.remove();
                    });
                } else {
                    removeBtn.addEventListener('click', () => {
                        markRowRemoved();
                    });
                }
                controls.appendChild(removeBtn);

                seedlingWrap.appendChild(row);
            }

            function addSeedlingRow() {
                if (!cachedDetails || !cachedDetails.meta) return;
                const meta = cachedDetails.meta;
                const isSeedling = (meta.request_type || '').toLowerCase() === 'seedling';
                const options = Array.isArray(meta.seedling_options) ? meta.seedling_options : [];
                if (!isSeedling || !options.length) return;
                const field = {
                    origin: 'seedling_requests',
                    label: 'Seedling',
                    value: '',
                    extra: {
                        seedl_req_id: '',
                        seedlings_id: options[0].id,
                        seedlings_old_id: '',
                        seedling_batch_key: meta.seedling_batch_key || meta.seedl_req_id || '',
                        is_new: true,
                    },
                };
                renderSeedlingField(field);
            }

            function buildEditFieldRow(field) {
                if (!editFieldsWrap) return;

                const currentRequestType = (cachedDetails?.meta?.request_type || '').toLowerCase();
                if (field.field === 'suppliers_json' && currentRequestType === 'lumber') {
                    return;
                }
                // For treecut, the species rows are edited using the species editor
                // (JSON payload). Skip the textual species summary and the raw
                // JSON field to avoid duplicate controls.
                if (currentRequestType === 'treecut') {
                    const ff = (field.field || '').toLowerCase();
                    if (ff === 'species_rows_json' || ff.includes('number_and_species')) {
                        return;
                    }
                }

                // For wildlife, the structured animals editor will handle the
                // `additional_information` JSON payload; don't render the raw
                // additional_information field as a text input.
                if (currentRequestType === 'wildlife') {
                    const ff2 = (field.field || '').toLowerCase();
                    if (ff2 === 'additional_information' || ff2 === 'animals_json') {
                        return;
                    }
                }

                if (field.origin === 'seedling_requests') {
                    renderSeedlingField(field);
                    return;
                }

                // Skip blocked/system fields
                if (isBlocked(field)) return;

                const requestType = (cachedDetails?.meta?.request_type || '').toLowerCase();
                const candidateField = (field.field || '').toLowerCase();
                if ((requestType === 'seedling' || requestType === 'lumber' || requestType === 'wildlife') && candidateField === 'complete_name') {
                    return;
                }

                const fieldName = field.field || '';
                if (!fieldName || field.is_signature) return;

                const row = document.createElement('div');
                row.className = 'edit-form-row';

                const labelEl = document.createElement('label');
                const labelText = field.label || fieldName.replace(/_/g, ' ');
                const inputId = createInputId(fieldName);
                labelEl.setAttribute('for', inputId);
                labelEl.textContent = labelText;
                row.appendChild(labelEl);

                const lower = fieldName.toLowerCase();
                const value = field.value ?? '';
                const longText = typeof value === 'string' && value.length > 180;

                // Detect boolean-like fields (e.g. "Is Government Employee") and render
                // a checkbox toggle that syncs to a hidden `fields[...]` input so the
                // server always receives '1' or '0'. Match by label or field name.
                const lowerLabel = (field.label || '').toLowerCase();
                const isBooleanLike = lowerLabel.includes('government') || lowerLabel.includes('govt') || lowerLabel.includes('is government') || lowerLabel.includes('is govt') ||
                    candidateField === 'is_government_employee' || candidateField === 'is_govt_employee' || candidateField === 'is_government';

                if (isBooleanLike) {
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.id = inputId;
                    // Interpret truthy values (string '1', 'true', 'yes', numeric 1)
                    const checked = value === '1' || value === 1 || String(value).toLowerCase() === 'true' || String(value).toLowerCase() === 'yes' || value === 'on';
                    checkbox.checked = !!checked;

                    // Hidden input carries the actual named value for form submission
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `fields[${fieldName}]`;
                    hidden.value = checkbox.checked ? '1' : '0';

                    checkbox.addEventListener('change', () => {
                        hidden.value = checkbox.checked ? '1' : '0';
                    });

                    const wrap = document.createElement('div');
                    wrap.style.display = 'flex';
                    wrap.style.justifyContent = 'center';
                    wrap.style.marginTop = '6px';
                    wrap.appendChild(checkbox);
                    row.appendChild(wrap);
                    row.appendChild(hidden);
                } else {
                    let inputEl;
                    if (longText) {
                        inputEl = document.createElement('textarea');
                        inputEl.value = value ?? '';
                    } else {
                        inputEl = document.createElement('input');
                        let type = 'text';
                        if (lower.includes('date')) type = 'date';
                        else if (lower.includes('email')) type = 'email';
                        else if (lower.includes('contact') || lower.includes('phone')) type = 'tel';
                        else if (lower.includes('age')) type = 'number';
                        inputEl.type = type;
                        inputEl.value = (type === 'date') ? normalizeDateValue(value) : (value ?? '');
                    }

                    inputEl.id = inputId;
                    inputEl.name = `fields[${fieldName}]`;
                    row.appendChild(inputEl);
                }

                const originInput = document.createElement('input');
                originInput.type = 'hidden';
                originInput.name = `field_origins[${fieldName}]`;
                originInput.value = field.origin || 'application_form';
                row.appendChild(originInput);

                if (field.extra && typeof field.extra === 'object') {
                    const metaInput = document.createElement('input');
                    metaInput.type = 'hidden';
                    metaInput.name = `field_meta[${fieldName}]`;
                    metaInput.value = JSON.stringify(field.extra);
                    row.appendChild(metaInput);
                }

                editFieldsWrap.appendChild(row);
            }

            // Animals editor for wildlife requests (editable table similar to treecut species editor)
            // Animals + establishment editor for wildlife requests
            function renderWildlifeAnimalsEditor(initial = []) {
                if (!editFieldsWrap) return;

                // Full original additional_information (to preserve other keys)
                const originalAdditionalInfo = cachedDetails?.meta?.original_additional_information || {};

                // Establishment details (support both old and new key names)
                const establishment = {
                    name: originalAdditionalInfo.establishment_name ||
                        originalAdditionalInfo.name_of_establishment ||
                        '',
                    address: originalAdditionalInfo.establishment_address ||
                        originalAdditionalInfo.address_of_establishment ||
                        '',
                    telephone: originalAdditionalInfo.establishment_telephone ||
                        originalAdditionalInfo.establishment_telephone_number ||
                        '',
                };

                // Container
                const container = document.createElement('div');
                container.className = 'edit-form-row';

                const label = document.createElement('label');
                label.textContent = 'Wildlife Details';
                container.appendChild(label);

                // Establishment fields block
                const estWrap = document.createElement('div');
                estWrap.className = 'wildlife-establishment-fields';

                function addEstField(fieldKey, labelText, placeholder = '') {
                    const row = document.createElement('div');
                    row.className = 'wildlife-establishment-row';
                    row.style.marginTop = '4px';

                    const lbl = document.createElement('div');
                    lbl.textContent = labelText;
                    lbl.style.fontSize = '0.9rem';
                    lbl.style.marginBottom = '2px';

                    const inp = document.createElement('input');
                    inp.type = 'text';
                    inp.value = establishment[fieldKey] || '';
                    inp.placeholder = placeholder;
                    inp.style.width = '100%';
                    inp.style.boxSizing = 'border-box';

                    inp.addEventListener('input', (e) => {
                        establishment[fieldKey] = e.target.value;
                        syncHidden();
                    });

                    row.appendChild(lbl);
                    row.appendChild(inp);
                    estWrap.appendChild(row);
                }

                addEstField('name', 'Name of Establishment');
                addEstField('address', 'Address of Establishment');
                addEstField('telephone', 'Establishment Telephone Number');
                container.appendChild(estWrap);

                // Small label above animals table
                const tableLabel = document.createElement('div');
                tableLabel.style.marginTop = '10px';
                tableLabel.style.fontWeight = '600';
                tableLabel.textContent = 'Animals (editable)';
                container.appendChild(tableLabel);

                // Animals table
                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                table.style.marginTop = '6px';
                table.style.tableLayout = 'fixed';
                table.innerHTML = `
        <colgroup>
          <col style="width:35%">
          <col style="width:35%">
          <col style="width:13%">
          <col style="width:17%">
        </colgroup>
        <thead>
            <tr style="background:#f3f4f6;border-bottom:1px solid #d1d5db;">
                <th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Common Name</th>
                <th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Scientific Name</th>
                <th style="text-align:center;padding:6px;border:1px solid #e5e7eb;">Qty</th>
                <th style="text-align:center;padding:6px;border:1px solid #e5e7eb;">Action</th>
            </tr>
        </thead>
    `;
                const tbody = document.createElement('tbody');

                // Hidden input that carries the serialized additional_information JSON
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'fields[additional_information]';
                hidden.value = '';

                // Normalize animals from server
                const animals = Array.isArray(initial) ?
                    initial.map((a) => ({
                        commonName: a.commonName ||
                            a.common_name ||
                            a.species ||
                            a.name ||
                            a.common ||
                            '',
                        scientificName: a.scientificName ||
                            a.scientific_name ||
                            a.sciname ||
                            a.scientific ||
                            '',
                        quantity: a.quantity || a.qty || a.count || a.number || '',
                    })) : [];

                function syncHidden() {
                    // Merge original info + updated establishment + updated animals
                    const payload = {
                        ...originalAdditionalInfo, // keep all existing keys
                        establishment_name: establishment.name || '',
                        establishment_address: establishment.address || '',
                        establishment_telephone: establishment.telephone || '',
                        animals: animals.map((a) => ({
                            commonName: a.commonName || '',
                            scientificName: a.scientificName || '',
                            quantity: a.quantity || '',
                        })),
                    };
                    hidden.value = JSON.stringify(payload);
                    console.log('[WILDLIFE EDITOR] Updated additional_information:', payload);
                }

                function makeRow(item) {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #e5e7eb';

                    const tdCommon = document.createElement('td');
                    tdCommon.style.padding = '6px';
                    const inputCommon = document.createElement('input');
                    inputCommon.type = 'text';
                    inputCommon.value = item.commonName || '';
                    inputCommon.style.width = '100%';
                    inputCommon.style.boxSizing = 'border-box';
                    inputCommon.addEventListener('input', (e) => {
                        item.commonName = e.target.value;
                        syncHidden();
                    });
                    tdCommon.appendChild(inputCommon);

                    const tdSci = document.createElement('td');
                    tdSci.style.padding = '6px';
                    const inputSci = document.createElement('input');
                    inputSci.type = 'text';
                    inputSci.value = item.scientificName || '';
                    inputSci.style.width = '100%';
                    inputSci.style.boxSizing = 'border-box';
                    inputSci.addEventListener('input', (e) => {
                        item.scientificName = e.target.value;
                        syncHidden();
                    });
                    tdSci.appendChild(inputSci);

                    const tdQty = document.createElement('td');
                    tdQty.style.padding = '6px';
                    tdQty.style.textAlign = 'center';
                    const inputQty = document.createElement('input');
                    inputQty.type = 'number';
                    inputQty.min = '0';
                    inputQty.value = item.quantity || '';
                    inputQty.style.width = '64px';
                    inputQty.style.boxSizing = 'border-box';
                    inputQty.style.textAlign = 'right';
                    inputQty.addEventListener('input', (e) => {
                        item.quantity = e.target.value;
                        syncHidden();
                    });
                    tdQty.appendChild(inputQty);

                    const tdCtrl = document.createElement('td');
                    tdCtrl.style.padding = '6px';
                    tdCtrl.style.textAlign = 'center';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'species-remove-btn';
                    btn.innerHTML = '<i class="fas fa-trash"></i>';
                    btn.addEventListener('click', () => {
                        const idx = animals.indexOf(item);
                        if (idx >= 0) animals.splice(idx, 1);
                        tr.remove();
                        syncHidden();
                    });
                    tdCtrl.appendChild(btn);

                    tr.appendChild(tdCommon);
                    tr.appendChild(tdSci);
                    tr.appendChild(tdQty);
                    tr.appendChild(tdCtrl);
                    return tr;
                }

                animals.forEach((a) => tbody.appendChild(makeRow(a)));
                table.appendChild(tbody);

                const addWrap = document.createElement('div');
                addWrap.style.display = 'flex';
                addWrap.style.justifyContent = 'center';
                addWrap.style.margin = '8px auto 12px';
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'species-add-btn';
                addBtn.textContent = 'Add animal';
                addBtn.addEventListener('click', () => {
                    const newItem = {
                        commonName: '',
                        scientificName: '',
                        quantity: ''
                    };
                    animals.push(newItem);
                    tbody.appendChild(makeRow(newItem));
                    syncHidden();
                });
                addWrap.appendChild(addBtn);

                container.appendChild(addWrap);
                container.appendChild(table);
                container.appendChild(hidden);

                // initial sync
                syncHidden();

                editFieldsWrap.appendChild(container);
            }



            function buildEditFileRow(file) {
                if (!editFilesWrap) return;
                const fieldName = file.field || '';
                if (!fieldName) return;
                const row = document.createElement('div');
                row.className = 'edit-form-row';
                const labelEl = document.createElement('label');
                const labelText = file.name || fieldName.replace(/_/g, ' ');
                const inputId = createInputId(`file-${fieldName}`);
                labelEl.setAttribute('for', inputId);
                labelEl.textContent = labelText;
                row.appendChild(labelEl);

                if (file.url) {
                    const currentWrap = document.createElement('div');
                    currentWrap.className = 'edit-current-file';
                    const link = document.createElement('a');
                    link.href = file.url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = 'View current file';
                    currentWrap.appendChild(link);
                    row.appendChild(currentWrap);
                }

                const inputEl = document.createElement('input');
                inputEl.type = 'file';
                inputEl.id = inputId;
                inputEl.name = `files[${fieldName}]`;
                inputEl.accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.heic';
                row.appendChild(inputEl);

                const origin = document.createElement('input');
                origin.type = 'hidden';
                origin.name = `file_origins[${fieldName}]`;
                origin.value = file.origin || 'requirements';
                row.appendChild(origin);

                editFilesWrap.appendChild(row);
            }

            function logSeedlingRecords() {
                const rows = Array.isArray(cachedDetails?.application) ? cachedDetails.application : [];
                const seedRows = rows.filter(r => r && r.origin === 'seedling_requests');
                if (!seedRows.length) {
                    console.log('seedling rows: none');
                    return;
                }
                const summary = seedRows.map(r => ({
                    field: r.field,
                    seedl_req_id: r.extra?.seedl_req_id || '',
                    seedlings_id: r.extra?.seedlings_id || r.extra?.seedlings_old_id || '',
                    quantity: r.value ?? '',
                }));
                console.log('seedling rows:', summary);
            }

            function labelizeFieldName(key) {
                if (!key) return '';
                return key.toString().split('_').filter(Boolean).map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
            }

            function formatLumberDetailValue(raw) {
                if (raw === null || raw === undefined) return '';
                const text = raw.toString().trim();
                if (text === '') return '';
                if (text === '1' || text.toLowerCase() === 'true') return 'Yes';
                if (text === '0' || text.toLowerCase() === 'false') return 'No';
                return text;
            }

            let lumberSuppliers = [];

            // Species rows editor for treecut requests
            let speciesRows = [];
            let speciesJsonField = null;

            function updateSpeciesJsonField() {
                if (!speciesJsonField) return;
                const payload = speciesRows
                    .map(s => ({
                        name: (s.name || '').toString().trim(),
                        count: (s.count || '').toString().trim(),
                        volume: (s.volume || '').toString().trim(),
                    }))
                    .filter(s => s.name || s.count || s.volume);
                speciesJsonField.value = JSON.stringify(payload);
            }

            function renderSpeciesRows(data = []) {
                // Ensure we have a hidden input to submit species JSON
                if (!speciesJsonField) {
                    speciesJsonField = document.createElement('input');
                    speciesJsonField.type = 'hidden';
                    speciesJsonField.id = 'speciesJsonField';
                    speciesJsonField.name = 'fields[species_rows_json]';
                    // append to editForm so it gets submitted
                    if (editForm) editForm.appendChild(speciesJsonField);
                }

                speciesRows = Array.isArray(data) ? data.map(s => ({
                    name: s?.name ?? '',
                    count: s?.count ?? '',
                    volume: s?.volume ?? ''
                })) : [];
                // default one empty row
                if (!speciesRows.length) speciesRows.push({
                    name: '',
                    count: '',
                    volume: ''
                });

                // Remove existing species editor if present
                const existing = document.getElementById('speciesEditor');
                if (existing) existing.remove();

                const wrap = document.createElement('div');
                wrap.id = 'speciesEditor';
                wrap.className = 'edit-form-row species-editor';

                const label = document.createElement('label');
                label.textContent = 'Number and Species of Trees (editable)';
                wrap.appendChild(label);

                // Add button placed directly under the label (compact, centered)
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'species-add-btn';
                addBtn.textContent = 'Add species';
                addBtn.style.margin = '8px auto 12px';
                wrap.appendChild(addBtn);

                const table = document.createElement('table');
                table.className = 'suppliers-table species-table';
                table.style.width = '100%';
                // tweak column proportions so species name column isn't excessively wide
                table.innerHTML = '<thead><tr><th style="width:48%">Species</th><th style="width:18%">No. of Trees</th><th style="width:18%">Net Volume (cu.m)</th><th style="width:16%"></th></tr></thead>';
                const tbody = document.createElement('tbody');

                speciesRows.forEach((row, idx) => {
                    const tr = document.createElement('tr');
                    const tdName = document.createElement('td');
                    const inpName = document.createElement('input');
                    inpName.type = 'text';
                    inpName.value = row.name;
                    inpName.className = 'species-name';
                    inpName.addEventListener('input', () => {
                        speciesRows[idx].name = inpName.value;
                        updateSpeciesJsonField();
                    });
                    tdName.appendChild(inpName);

                    const tdCount = document.createElement('td');
                    const inpCount = document.createElement('input');
                    inpCount.type = 'number';
                    inpCount.min = '0';
                    inpCount.value = row.count;
                    inpCount.className = 'species-count';
                    inpCount.addEventListener('input', () => {
                        speciesRows[idx].count = inpCount.value;
                        updateSpeciesJsonField();
                    });
                    tdCount.appendChild(inpCount);

                    const tdVol = document.createElement('td');
                    const inpVol = document.createElement('input');
                    inpVol.type = 'number';
                    inpVol.step = '0.01';
                    inpVol.min = '0';
                    inpVol.value = row.volume;
                    inpVol.className = 'species-volume';
                    inpVol.addEventListener('input', () => {
                        speciesRows[idx].volume = inpVol.value;
                        updateSpeciesJsonField();
                    });
                    tdVol.appendChild(inpVol);

                    const tdCtrl = document.createElement('td');
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'species-remove-btn';
                    removeBtn.title = 'Remove';
                    removeBtn.setAttribute('aria-label', 'Remove species row');
                    removeBtn.innerHTML = '<i class="fas fa-trash-alt" aria-hidden="true"></i>';
                    removeBtn.addEventListener('click', () => {
                        if (speciesRows.length <= 1) {
                            speciesRows[0] = {
                                name: '',
                                count: '',
                                volume: ''
                            };
                        } else {
                            speciesRows.splice(idx, 1);
                        }
                        renderSpeciesRows(speciesRows);
                        updateSpeciesJsonField();
                    });
                    tdCtrl.appendChild(removeBtn);

                    tr.appendChild(tdName);
                    tr.appendChild(tdCount);
                    tr.appendChild(tdVol);
                    tr.appendChild(tdCtrl);
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                wrap.appendChild(table);

                addBtn.addEventListener('click', () => {
                    speciesRows.push({
                        name: '',
                        count: '',
                        volume: ''
                    });
                    renderSpeciesRows(speciesRows);
                    updateSpeciesJsonField();
                });

                // append after editFieldsWrap so user can edit species
                if (editFieldsWrap) editFieldsWrap.appendChild(wrap);

                updateSpeciesJsonField();
            }

            function updateSuppliersJsonField() {
                if (!suppliersJsonField) return;
                const payload = lumberSuppliers
                    .map(s => ({
                        name: s.name?.trim() || '',
                        volume: s.volume?.trim() || '',
                    }))
                    .filter(s => s.name || s.volume);
                suppliersJsonField.value = JSON.stringify(payload);
            }

            function renderSupplierRows(data = []) {
                if (!lumberSuppliersList) return;
                lumberSuppliers = Array.isArray(data) ? data.map(s => ({
                    name: s?.name ?? '',
                    volume: s?.volume ?? '',
                })) : [];
                if (!lumberSuppliers.length) {
                    lumberSuppliers.push({
                        name: '',
                        volume: ''
                    });
                }
                lumberSuppliersList.innerHTML = '';
                lumberSuppliers.forEach((supplier, index) => {
                    const row = document.createElement('div');
                    row.className = 'lumber-supplier-row edit-form-row';
                    const controls = document.createElement('div');
                    controls.className = 'lumber-supplier-inputs';

                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.placeholder = 'Supplier name';
                    nameInput.value = supplier.name;
                    nameInput.addEventListener('input', () => {
                        lumberSuppliers[index].name = nameInput.value;
                        updateSuppliersJsonField();
                    });

                    const volumeInput = document.createElement('input');
                    volumeInput.type = 'text';
                    volumeInput.placeholder = 'Volume';
                    volumeInput.value = supplier.volume;
                    volumeInput.addEventListener('input', () => {
                        lumberSuppliers[index].volume = volumeInput.value;
                        updateSuppliersJsonField();
                    });

                    controls.appendChild(nameInput);
                    controls.appendChild(volumeInput);
                    row.appendChild(controls);

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'lumber-supplier-remove';
                    removeBtn.innerHTML = '<i class="fas fa-minus"></i> Remove';
                    removeBtn.addEventListener('click', () => {
                        if (lumberSuppliers.length <= 1) {
                            lumberSuppliers[index] = {
                                name: '',
                                volume: ''
                            };
                        } else {
                            lumberSuppliers.splice(index, 1);
                        }
                        renderSupplierRows(lumberSuppliers);
                    });
                    row.appendChild(removeBtn);
                    lumberSuppliersList.appendChild(row);
                });
                updateSuppliersJsonField();
            }

            function getCachedLumberSuppliers() {
                if (!Array.isArray(cachedDetails?.application)) return [];
                const field = cachedDetails.application.find(f => (f?.field || '').toLowerCase() === 'suppliers_json');
                if (!field || !field.value) return [];
                try {
                    const parsed = JSON.parse(field.value);
                    if (Array.isArray(parsed)) {
                        return parsed.map(entry => ({
                            name: entry?.name ?? '',
                            volume: entry?.volume ?? ''
                        }));
                    }
                } catch (err) {
                    console.warn('Unable to parse suppliers_json', err);
                }
                return [];
            }

            btnAddLumberSupplier?.addEventListener('click', () => {
                lumberSuppliers.push({
                    name: '',
                    volume: ''
                });
                renderSupplierRows(lumberSuppliers);
            });

            // Machinery rows for WOOD requests
            let machineryRows = [];
            let machineryJsonField = null;

            // Supply rows for WOOD requests (raw material / supply contracts)
            let supplyRows = [];
            let supplyJsonField = null;

            function updateMachineryJsonField() {
                if (!machineryJsonField) return;
                try {
                    machineryJsonField.value = JSON.stringify(machineryRows || []);
                } catch (e) {
                    machineryJsonField.value = '[]';
                }
            }

            function renderMachineryRows(data = []) {
                machineryRows = Array.isArray(data) ? data.slice() : [];
                if (!machineryRows.length) machineryRows.push({
                    type: '',
                    brand: '',
                    power: '',
                    qty: ''
                });

                const wrap = document.createElement('div');
                wrap.className = 'machinery-rows-wrap';

                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                const tbody = document.createElement('tbody');

                machineryRows.forEach((row, idx) => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #eee';

                    const tdType = document.createElement('td');
                    const inType = document.createElement('input');
                    inType.type = 'text';
                    inType.value = row.type || '';
                    inType.placeholder = 'Type of Equipment';
                    inType.style.width = '100%';
                    inType.addEventListener('input', () => {
                        machineryRows[idx].type = inType.value;
                        updateMachineryJsonField();
                    });
                    tdType.appendChild(inType);

                    const tdBrand = document.createElement('td');
                    const inBrand = document.createElement('input');
                    inBrand.type = 'text';
                    inBrand.value = row.brand || '';
                    inBrand.placeholder = 'Brand / Model';
                    inBrand.style.width = '100%';
                    inBrand.addEventListener('input', () => {
                        machineryRows[idx].brand = inBrand.value;
                        updateMachineryJsonField();
                    });
                    tdBrand.appendChild(inBrand);

                    const tdPower = document.createElement('td');
                    const inPower = document.createElement('input');
                    inPower.type = 'text';
                    inPower.value = row.power || '';
                    inPower.placeholder = 'HP / Capacity';
                    inPower.style.width = '100%';
                    inPower.addEventListener('input', () => {
                        machineryRows[idx].power = inPower.value;
                        updateMachineryJsonField();
                    });
                    tdPower.appendChild(inPower);

                    const tdQty = document.createElement('td');
                    const inQty = document.createElement('input');
                    inQty.type = 'number';
                    inQty.min = '0';
                    inQty.value = row.qty || '';
                    inQty.style.width = '80px';
                    inQty.addEventListener('input', () => {
                        machineryRows[idx].qty = inQty.value;
                        updateMachineryJsonField();
                    });
                    tdQty.appendChild(inQty);

                    const tdCtrl = document.createElement('td');
                    const btnRem = document.createElement('button');
                    btnRem.type = 'button';
                    btnRem.className = 'remove-row-btn';
                    btnRem.textContent = 'Remove';
                    btnRem.addEventListener('click', () => {
                        if (machineryRows.length <= 1) {
                            machineryRows[0] = {
                                type: '',
                                brand: '',
                                power: '',
                                qty: ''
                            };
                        } else {
                            machineryRows.splice(idx, 1);
                        }
                        renderMachineryRows(machineryRows);
                        updateMachineryJsonField();
                    });
                    tdCtrl.appendChild(btnRem);

                    tr.appendChild(tdType);
                    tr.appendChild(tdBrand);
                    tr.appendChild(tdPower);
                    tr.appendChild(tdQty);
                    tr.appendChild(tdCtrl);
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);

                const addWrap = document.createElement('div');
                addWrap.style.display = 'flex';
                addWrap.style.justifyContent = 'center';
                addWrap.style.margin = '8px auto 12px';
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'add-row-btn';
                addBtn.textContent = 'Add equipment';
                addBtn.addEventListener('click', () => {
                    machineryRows.push({
                        type: '',
                        brand: '',
                        power: '',
                        qty: ''
                    });
                    renderMachineryRows(machineryRows);
                    updateMachineryJsonField();
                });
                addWrap.appendChild(addBtn);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'fields[machinery_rows_json]';
                machineryJsonField = hidden;

                wrap.appendChild(addWrap);
                wrap.appendChild(table);
                wrap.appendChild(hidden);

                updateMachineryJsonField();
                if (editFieldsWrap) editFieldsWrap.appendChild(wrap);
            }

            function updateSupplyJsonField() {
                if (!supplyJsonField) return;
                try {
                    supplyJsonField.value = JSON.stringify(supplyRows || []);
                } catch (e) {
                    supplyJsonField.value = '[]';
                }
            }

            function renderSupplyRows(data = []) {
                supplyRows = Array.isArray(data) ? data.slice() : [];
                if (!supplyRows.length) supplyRows.push({
                    supplier: '',
                    species: '',
                    volume: ''
                });

                const wrap = document.createElement('div');
                wrap.className = 'supply-rows-wrap';

                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                const tbody = document.createElement('tbody');

                supplyRows.forEach((row, idx) => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #eee';
                    const tdName = document.createElement('td');
                    const inName = document.createElement('input');
                    inName.type = 'text';
                    inName.value = row.supplier || '';
                    inName.placeholder = 'Supplier Name';
                    inName.style.width = '100%';
                    inName.addEventListener('input', () => {
                        supplyRows[idx].supplier = inName.value;
                        updateSupplyJsonField();
                    });
                    tdName.appendChild(inName);
                    const tdSpecies = document.createElement('td');
                    const inSpecies = document.createElement('input');
                    inSpecies.type = 'text';
                    inSpecies.value = row.species || '';
                    inSpecies.placeholder = 'Species';
                    inSpecies.style.width = '100%';
                    inSpecies.addEventListener('input', () => {
                        supplyRows[idx].species = inSpecies.value;
                        updateSupplyJsonField();
                    });
                    tdSpecies.appendChild(inSpecies);
                    const tdVol = document.createElement('td');
                    const inVol = document.createElement('input');
                    inVol.type = 'text';
                    inVol.value = row.volume || '';
                    inVol.placeholder = 'Contracted Vol';
                    inVol.style.width = '120px';
                    inVol.addEventListener('input', () => {
                        supplyRows[idx].volume = inVol.value;
                        updateSupplyJsonField();
                    });
                    tdVol.appendChild(inVol);
                    const tdCtrl = document.createElement('td');
                    const btnRem = document.createElement('button');
                    btnRem.type = 'button';
                    btnRem.className = 'remove-row-btn';
                    btnRem.textContent = 'Remove';
                    btnRem.addEventListener('click', () => {
                        if (supplyRows.length <= 1) {
                            supplyRows[0] = {
                                supplier: '',
                                species: '',
                                volume: ''
                            };
                        } else {
                            supplyRows.splice(idx, 1);
                        }
                        renderSupplyRows(supplyRows);
                        updateSupplyJsonField();
                    });
                    tdCtrl.appendChild(btnRem);
                    tr.appendChild(tdName);
                    tr.appendChild(tdSpecies);
                    tr.appendChild(tdVol);
                    tr.appendChild(tdCtrl);
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                const addWrap = document.createElement('div');
                addWrap.style.display = 'flex';
                addWrap.style.justifyContent = 'center';
                addWrap.style.margin = '8px auto 12px';
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'add-row-btn';
                addBtn.textContent = 'Add supplier';
                addBtn.addEventListener('click', () => {
                    supplyRows.push({
                        supplier: '',
                        species: '',
                        volume: ''
                    });
                    renderSupplyRows(supplyRows);
                    updateSupplyJsonField();
                });
                addWrap.appendChild(addBtn);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'fields[supply_rows_json]';
                supplyJsonField = hidden;
                wrap.appendChild(addWrap);
                wrap.appendChild(table);
                wrap.appendChild(hidden);
                updateSupplyJsonField();
                if (editFieldsWrap) editFieldsWrap.appendChild(wrap);
            }

            // Wildlife details rendering
            function renderWildlifeDetails(details) {
                if (!lumberDetailsList || !lumberSection) return;
                lumberDetailsList.innerHTML = '';
                const lumberTitleEl = document.querySelector('.lumber-section-title');
                if (lumberTitleEl) {
                    lumberTitleEl.textContent = 'Wildlife Permit Details';
                }

                if (!details || typeof details !== 'object') {
                    lumberSection.classList.add('hidden');
                    if (lumberSuppliersSection) lumberSuppliersSection.classList.add('hidden');
                    return;
                }

                let hasRow = false;

                // Helper to add a labeled row
                const addRow = (label, value) => {
                    if (!value) return;
                    const row = document.createElement('div');
                    row.className = 'defrow';
                    const dt = document.createElement('dt');
                    dt.textContent = label;
                    const dd = document.createElement('dd');
                    dd.textContent = value;
                    row.appendChild(dt);
                    row.appendChild(dd);
                    lumberDetailsList.appendChild(row);
                    hasRow = true;
                };

                // Client section
                const client = details.client || {};
                const clientName = [client.first_name, client.middle_name, client.last_name]
                    .filter(Boolean).join(' ').trim();
                if (clientName) addRow('Applicant', clientName);

                // Application section
                const app = details.application || {};
                // Permit Type already appears in the modal meta-strip; avoid duplicate here
                if (app.residence_address) addRow('Residence Address', app.residence_address);
                if (app.telephone_number) addRow('Telephone Number', app.telephone_number);

                // Categories section
                const cats = details.categories || {};
                const catList = [];
                if (cats.zoo) catList.push('Zoo');
                if (cats.botanical_garden) catList.push('Botanical Garden');
                if (cats.private_collection) catList.push('Private Collection');
                if (catList.length) addRow('Categories', catList.join(', '));

                // Establishment section (for new permits)
                if (app.establishment_name) addRow('Establishment Name', app.establishment_name);
                if (app.establishment_address) addRow('Establishment Address', app.establishment_address);
                if (app.establishment_telephone) addRow('Establishment Telephone', app.establishment_telephone);

                // Renewal-specific fields
                if (app.wfp_number) addRow('Original WFP No.', app.wfp_number);
                if (app.issue_date) addRow('Issued on', app.issue_date);

                // Postal address (bottom)
                if (app.postal_address) addRow('Postal Address', app.postal_address);

                // Animals section: render as a small table
                const animals = details.animals || [];
                if (Array.isArray(animals) && animals.length) {
                    const animalRow = document.createElement('div');
                    animalRow.className = 'defrow';
                    animalRow.style.gridColumn = '1 / -1';

                    const label = document.createElement('dt');
                    label.textContent = 'Animals';
                    animalRow.appendChild(label);

                    const tableWrap = document.createElement('dd');
                    const tbl = document.createElement('table');
                    tbl.style.width = '100%';
                    tbl.style.fontSize = '0.9rem';
                    tbl.innerHTML = `
                        <thead>
                            <tr style="background:#f3f4f6;border-bottom:1px solid #d1d5db;">
                                <th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Common Name</th>
                                <th style="text-align:left;padding:6px;border:1px solid #e5e7eb;">Scientific Name</th>
                                <th style="text-align:center;padding:6px;border:1px solid #e5e7eb;">Qty</th>
                            </tr>
                        </thead>
                    `;
                    const tbody = document.createElement('tbody');
                    // Log animals for debug (will appear in browser console)
                    try {
                        console.debug('renderWildlifeDetails: animals=', animals);
                    } catch (e) {}

                    animals.forEach(a => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #e5e7eb';

                        // If the entry is a plain string, use it as common name
                        if (typeof a === 'string') {
                            tr.innerHTML = `
                                <td style="padding:6px;border:1px solid #e5e7eb;">${a || '—'}</td>
                                <td style="padding:6px;border:1px solid #e5e7eb;">—</td>
                                <td style="padding:6px;border:1px solid #e5e7eb;text-align:center;">—</td>
                            `;
                            tbody.appendChild(tr);
                            return;
                        }

                        // Some backends may return numbers/null/arrays — handle defensively
                        if (!a || (typeof a !== 'object')) {
                            tr.innerHTML = `
                                <td style="padding:6px;border:1px solid #e5e7eb;">—</td>
                                <td style="padding:6px;border:1px solid #e5e7eb;">—</td>
                                <td style="padding:6px;border:1px solid #e5e7eb;text-align:center;">—</td>
                            `;
                            tbody.appendChild(tr);
                            return;
                        }

                        // Normalize possible field names for objects
                        const common = (a.commonName || a.common_name || a.species || a.name || a.common || a['common name']) || '';
                        const scientific = (a.scientificName || a.scientific_name || a.sciname || a.scientific) || '';
                        const qty = (a.quantity || a.qty || a.count || a.number) || '';

                        tr.innerHTML = `
                            <td style="padding:6px;border:1px solid #e5e7eb;">${common || '—'}</td>
                            <td style="padding:6px;border:1px solid #e5e7eb;">${scientific || '—'}</td>
                            <td style="padding:6px;border:1px solid #e5e7eb;text-align:center;">${qty || '—'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    tbl.appendChild(tbody);
                    tableWrap.appendChild(tbl);
                    animalRow.appendChild(tableWrap);
                    lumberDetailsList.appendChild(animalRow);
                    hasRow = true;
                }

                // Approved docs
                const appDocs = details.approved_docs || {};
                if (Object.keys(appDocs).length) {
                    Object.entries(appDocs).forEach(([key, value]) => {
                        if (value) {
                            const label = key === 'no' ? 'Permit No.' : key === 'date_issued' ? 'Date Issued' : key === 'expiry_date' ? 'Expiry Date' : key;
                            addRow(label, value);
                        }
                    });
                }

                lumberSection.classList.toggle('hidden', !hasRow);
                if (lumberSuppliersSection) lumberSuppliersSection.classList.add('hidden');
            }

            function renderLumberDetails(details, requestType) {
                if (!lumberDetailsList || !lumberSection) return;
                lumberDetailsList.innerHTML = '';
                const lumberTitleEl = document.querySelector('.lumber-section-title');
                if (lumberTitleEl) {
                    lumberTitleEl.textContent = requestType === 'treecut' ? 'Tree Cutting Details' : 'Lumber Request Details';
                }
                if ((!details || typeof details !== 'object') || (requestType !== 'lumber' && requestType !== 'treecut')) {
                    lumberSection.classList.add('hidden');
                    if (lumberSuppliersSection) {
                        lumberSuppliersSection.classList.add('hidden');
                    }
                    renderSupplierRows([]);
                    return;
                }
                let hasRow = false;
                const appendGroup = (labelPrefix, collection) => {
                    if (!collection || typeof collection !== 'object') return;
                    Object.entries(collection).forEach(([key, value]) => {
                        // Skip arrays or complex objects (e.g. species array)
                        // We only render primitive values (string/number/boolean)
                        if (Array.isArray(value)) return;
                        if (value !== null && typeof value === 'object') return;

                        const formatted = formatLumberDetailValue(value);
                        if (!formatted) return;
                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        const label = labelizeFieldName(key);
                        dt.textContent = labelPrefix ? `${labelPrefix} - ${label}` : label;
                        const dd = document.createElement('dd');
                        dd.textContent = formatted;
                        row.appendChild(dt);
                        row.appendChild(dd);
                        lumberDetailsList.appendChild(row);
                        hasRow = true;
                    });
                };
                appendGroup('Client', details.client);

                if (requestType === 'lumber') {
                    appendGroup('Application', details.application);
                    appendGroup('Approved document', details.approved_docs);
                    lumberSection.classList.toggle('hidden', !hasRow);
                    if (lumberSuppliersSection) {
                        lumberSuppliersSection.classList.remove('hidden');
                    }
                    const supplierEntries = Array.isArray(details.application?.suppliers) ? details.application.suppliers : [];
                    renderSupplierRows(supplierEntries);
                } else if (requestType === 'treecut') {
                    // show application fields, then render species table
                    appendGroup('Application', details.application);
                    appendGroup('Approved document', details.approved_docs);
                    // species: render as a definition row with a simple table inside
                    const species = Array.isArray(details.application?.species) ? details.application.species : [];
                    if (species.length) {
                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        dt.textContent = 'Species';
                        const dd = document.createElement('dd');

                        // Render species as a simple readable list instead of a table
                        species.forEach(s => {
                            const itemWrap = document.createElement('div');
                            itemWrap.style.marginBottom = '6px';
                            const name = document.createElement('span');
                            name.style.fontWeight = '600';
                            name.textContent = s.name || '';
                            const meta = document.createElement('span');
                            const parts = [];
                            if (s.count) parts.push((s.count || '') + ' tree' + (String(s.count) !== '1' ? 's' : ''));
                            if (s.volume) parts.push((s.volume || '') + ' cu.m');
                            if (parts.length) meta.textContent = ' — ' + parts.join(' — ');
                            itemWrap.appendChild(name);
                            itemWrap.appendChild(meta);
                            dd.appendChild(itemWrap);
                        });

                        // Totals line
                        const totals = document.createElement('div');
                        totals.style.marginTop = '8px';
                        totals.innerHTML = '<strong>TOTAL</strong> ' + (details.application?.total_count || '') + ' — ' + (details.application?.total_volume || '');
                        dd.appendChild(totals);

                        row.appendChild(dt);
                        row.appendChild(dd);
                        lumberDetailsList.appendChild(row);
                        hasRow = true;
                    }
                    lumberSection.classList.toggle('hidden', !hasRow);
                    if (lumberSuppliersSection) {
                        lumberSuppliersSection.classList.add('hidden');
                    }
                }
            }

            function enterEditMode() {
                if (!cachedDetails || !modalContent || !editPane) return;
                const meta = cachedDetails.meta || {};
                // const meta = cachedDetails.meta || {};
                const requestType = (meta.request_type || '').toLowerCase();
                const status = (meta.status || '').toLowerCase();
                const seedOptions = Array.isArray(meta.seedling_options) ? meta.seedling_options : [];
                const showSeedlingControls = requestType === 'seedling' &&
                    status === 'pending' &&
                    seedOptions.length > 0;
                editMode = true;
                modalContent.classList.add('hidden');
                editPane.classList.remove('hidden');
                if (btnEditPending) {
                    if (document.activeElement === btnEditPending) {
                        btnEditPending.blur();
                    }
                    btnEditPending.classList.add('hidden');
                    btnEditPending.setAttribute('aria-hidden', 'true');
                }
                if (btnCancelEdit) {
                    btnCancelEdit.classList.remove('hidden');
                    btnCancelEdit.setAttribute('aria-hidden', 'false');
                }
                if (btnSaveEdit) {
                    btnSaveEdit.classList.remove('hidden');
                    btnSaveEdit.setAttribute('aria-hidden', 'false');
                }
                resetEditFormContents();
                if (requestType === 'lumber') {
                    renderSupplierRows(getCachedLumberSuppliers());
                    lumberSuppliersSection?.classList.remove('hidden');
                } else {
                    renderSupplierRows([]);
                    lumberSuppliersSection?.classList.add('hidden');
                }
                if (seedlingActions) {
                    seedlingActions.classList.toggle('hidden', !showSeedlingControls);
                }
                if (btnAddSeedling) {
                    btnAddSeedling.disabled = !showSeedlingControls;
                    btnAddSeedling.setAttribute('aria-hidden', showSeedlingControls ? 'false' : 'true');
                }
                if (editApprovalInput) editApprovalInput.value = meta.approval_id || '';
                if (editRequestTypeInput) editRequestTypeInput.value = meta.request_type || '';
                if (editPermitTypeInput) editPermitTypeInput.value = meta.permit_type || '';

                if (Array.isArray(cachedDetails.application)) {
                    cachedDetails.application.forEach(field => {
                        // For treecut requests, skip the complete_name field
                        const fieldName = (field?.field || '').toLowerCase();
                        if (requestType === 'treecut' && fieldName === 'complete_name') {
                            return;
                        }
                        buildEditFieldRow(field);
                    });
                }

                // For treecut requests, provide a species rows editor so the
                // same species shown in view modal are editable in edit mode.
                if (requestType === 'treecut') {
                    try {
                        // Prefer explicit species array from treecut_details
                        let species = Array.isArray(cachedDetails?.treecut_details?.application?.species) ? cachedDetails.treecut_details.application.species : [];

                        // If no structured species rows exist, attempt to parse the
                        // textual application field (e.g. "Number And Species Of Trees Applied For Cutting")
                        if (!species || !species.length) {
                            const appRows = Array.isArray(cachedDetails?.application) ? cachedDetails.application : [];
                            const candidate = appRows.find(r => {
                                const f = (r?.field || '').toLowerCase();
                                const l = (r?.label || '').toLowerCase();
                                return f.includes('number_and_species') || l.includes('number and species') || f.includes('numberandspecies') || l.includes('number and species of trees');
                            });
                            if (candidate && candidate.value) {
                                const lines = String(candidate.value).split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                                species = lines.map(line => {
                                    // common separators: | or \t or multiple spaces or -
                                    let parts = line.split('|').map(p => p.trim()).filter(Boolean);
                                    if (parts.length < 2) {
                                        // try dash or tab or comma separation
                                        parts = line.split(/\s*-\s*|\t|,\s*/).map(p => p.trim()).filter(Boolean);
                                    }
                                    const name = parts[0] ?? '';
                                    const count = parts[1] ?? '';
                                    const volume = parts[2] ?? '';
                                    return {
                                        name,
                                        count,
                                        volume
                                    };
                                }).filter(s => s.name || s.count || s.volume);
                            }
                        }

                        renderSpeciesRows(species);
                    } catch (err) {
                        console.error('Failed to render species editor', err);
                    }
                }

                // For wildlife requests, render an editable animals table similar
                // to the treecut species editor so users can edit common/scientific/qty.
                if (requestType === 'wildlife') {
                    try {
                        const initialAnimals = Array.isArray(cachedDetails?.wildlife_details?.animals) ? cachedDetails.wildlife_details.animals : [];
                        renderWildlifeAnimalsEditor(initialAnimals);
                    } catch (err) {
                        console.error('Failed to render wildlife animals editor', err);
                    }
                }

                // For wood processing plant permit (wpp) requests, render
                // machinery and supply-contract editors so they are editable.
                if (requestType === 'wood' || requestType === 'wpp') {
                    try {
                        // Find a JSON machinery field in cachedDetails.application
                        let machinery = [];
                        let supplies = [];
                        if (Array.isArray(cachedDetails.application)) {
                            const appRows = cachedDetails.application;
                            const machCandidate = appRows.find(r => {
                                const f = (r?.field || '').toLowerCase();
                                const l = (r?.label || '').toLowerCase();
                                return f.includes('machin') || l.includes('machin') || f.includes('equipment') || l.includes('equipment');
                            });
                            const supCandidate = appRows.find(r => {
                                const f = (r?.field || '').toLowerCase();
                                const l = (r?.label || '').toLowerCase();
                                return f.includes('supply') || l.includes('supply') || f.includes('supplier') || l.includes('supplier') || f.includes('raw');
                            });
                            if (machCandidate && machCandidate.value) {
                                try {
                                    machinery = JSON.parse(machCandidate.value);
                                } catch (e) {
                                    machinery = [];
                                }
                            }
                            if (supCandidate && supCandidate.value) {
                                try {
                                    supplies = JSON.parse(supCandidate.value);
                                } catch (e) {
                                    supplies = [];
                                }
                            }
                        }

                        renderMachineryRows(machinery);
                        renderSupplyRows(supplies);
                    } catch (err) {
                        console.error('Failed to render wood editors', err);
                    }
                }

                const fileEntries = [];
                if (Array.isArray(cachedDetails.files)) {
                    cachedDetails.files.forEach(f => fileEntries.push(f));
                }
                if (Array.isArray(cachedDetails.application)) {
                    cachedDetails.application.filter(f => f && f.is_signature).forEach(f => {
                        fileEntries.push({
                            name: f.label || f.field,
                            url: f.value || '',
                            field: f.field,
                            origin: f.origin || 'application_form'
                        });
                    });
                }

                const seedlingCount = seedlingFieldsWrap ? seedlingFieldsWrap.children.length : 0;
                if (editFieldsWrap && editFieldsWrap.children.length === 0 && seedlingCount === 0) {
                    const info = document.createElement('p');
                    info.className = 'edit-current-file';
                    info.textContent = 'No editable form fields available.';
                    editFieldsWrap.appendChild(info);
                }

                const editableFiles = fileEntries.filter(f => f.field !== 'application_form');
                if (editableFiles.length) {
                    editableFiles.forEach(buildEditFileRow);
                } else if (editFilesWrap) {
                    const info = document.createElement('p');
                    info.className = 'edit-current-file';
                    info.textContent = 'No documents available to update.';
                    editFilesWrap.appendChild(info);
                }

                if (btnCancelEdit) btnCancelEdit.disabled = false;
                if (btnSaveEdit) btnSaveEdit.disabled = false;
                if (editMessage) editMessage.textContent = '';
            }

            async function forceDownload(url) {
                if (!url) return;
                try {
                    if (url.startsWith('data:')) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.setAttribute('download', 'document');
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        return;
                    }
                    const res = await fetch(url, {
                        credentials: 'omit'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const dispo = res.headers.get('Content-Disposition') || '';
                    let filename = 'document';
                    const m = dispo.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    if (m && m[1]) {
                        filename = decodeURIComponent(m[1].replace(/["']/g, ''));
                    } else {
                        const u = new URL(url, window.location.origin);
                        filename = (u.pathname.split('/').pop() || 'document').split('?')[0];
                    }

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.setAttribute('download', filename);
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
                } catch {
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = url;
                    document.body.appendChild(iframe);
                    setTimeout(() => iframe.remove(), 10000);
                }
            }


            async function openApproval(approvalId) {
                if (!approvalId) return;

                // Reset UI placeholders
                document.getElementById('metaClientName').textContent = '—';
                document.getElementById('metaRequestType').textContent = '—';
                document.getElementById('metaPermitType').textContent = '—';
                const ms = document.getElementById('metaStatus');
                ms.textContent = '—';
                ms.className = 'badge status';

                const banner = document.getElementById('rejectBanner');
                banner.classList.add('hidden');
                banner.textContent = '';

                editMode = false;
                resetEditFormContents();
                if (editPane) editPane.classList.add('hidden');
                if (btnEditPending) {
                    btnEditPending.classList.add('hidden');
                    btnEditPending.disabled = true;
                    btnEditPending.setAttribute('aria-hidden', 'true');
                }

                const list = document.getElementById('applicationFields');
                const filesList = document.getElementById('filesList');
                const formEmpty = document.getElementById('formEmpty');
                const filesEmpty = document.getElementById('filesEmpty');

                list.innerHTML = '';
                filesList.innerHTML = '';
                formEmpty.classList.add('hidden');
                filesEmpty.classList.add('hidden');
                if (lumberDetailsList) {
                    lumberDetailsList.innerHTML = '';
                }
                if (lumberSection) {
                    lumberSection.classList.add('hidden');
                }

                btnRequestAgain.classList.add('hidden');
                btnRequestAgain.disabled = true;
                btnRequestAgain.setAttribute('aria-hidden', 'true');

                // OPEN MODAL immediately with skeleton
                showModalSkeleton();
                modalEl.classList.remove('hidden');

                // Fetch details
                // Fetch details robustly: try to parse JSON, but if the server
                // returns non-JSON (PHP warning / error), capture the raw text
                // so we can show it in the UI for debugging instead of silently
                // failing with 'Failed to load details'.
                let res = {
                    ok: false
                };
                try {
                    const r = await fetch(`<?php echo basename(__FILE__); ?>?ajax=details&approval_id=${encodeURIComponent(approvalId)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const txt = await r.text();
                    try {
                        // try parse as JSON first
                        res = JSON.parse(txt);
                    } catch (parseErr) {
                        console.error('Failed to parse details JSON response:', parseErr);
                        console.error('Server response:', txt);
                        res = {
                            ok: false,
                            error: 'Invalid JSON response from server',
                            server_text: txt
                        };
                    }
                } catch (fetchErr) {
                    console.error('Failed to fetch details:', fetchErr);
                    res = {
                        ok: false,
                        error: String(fetchErr)
                    };
                }

                if (!res.ok) {
                    hideModalSkeleton();
                    ms.textContent = 'Error';
                    formEmpty.classList.remove('hidden');
                    filesEmpty.classList.remove('hidden');
                    const msg = res.error || 'Failed to load details';
                    // If server returned raw text (e.g. PHP error), include it for debugging
                    if (res.server_text) {
                        console.error('Server details response:', res.server_text);
                        alert(msg + '\n\nServer response:\n' + res.server_text.slice(0, 2000));
                    } else {
                        alert(msg);
                    }
                    return;
                }

                // Populate
                cachedDetails = res;
                const meta = res.meta || {};
                document.getElementById('metaClientName').textContent = meta.client || '—';
                document.getElementById('metaRequestType').textContent = meta.request_type || '—';
                document.getElementById('metaPermitType').textContent = meta.permit_type || '—';

                // Update modal title with permit type for wildlife
                const requestType = (meta.request_type || '').toLowerCase();
                const modalTitle = document.getElementById('modalTitle');
                if (requestType === 'wildlife' && meta.permit_type) {
                    modalTitle.textContent = `Wildlife ${meta.permit_type.charAt(0).toUpperCase() + meta.permit_type.slice(1)} Permit`;
                } else {
                    modalTitle.textContent = 'Request Details';
                }
                // pass the correct details object depending on request type
                if (requestType === 'treecut') {
                    renderLumberDetails(res.treecut_details, requestType);
                } else if (requestType === 'wildlife') {
                    renderWildlifeDetails(res.wildlife_details);
                } else {
                    renderLumberDetails(res.lumber_details, requestType);
                }
                const st = (meta.status || '').toLowerCase();
                ms.textContent = st ? st[0].toUpperCase() + st.slice(1) : '—';
                // 'released' uses the green badge like approved
                ms.className = 'badge status ' + ((st === 'released' || st === 'approved') ? 'approved' : (st === 'rejected' ? 'rejected' : 'pending'));

                if (btnEditPending) {
                    const canEdit = st === 'pending';
                    btnEditPending.classList.toggle('hidden', !canEdit);
                    btnEditPending.disabled = !canEdit;
                    btnEditPending.setAttribute('aria-hidden', canEdit ? 'false' : 'true');
                }

                // Toggle modal Download button only for released
                if (btnDownloadIssued) {
                    btnDownloadIssued.classList.add('hidden');
                    btnDownloadIssued.disabled = true;
                    btnDownloadIssued.setAttribute('aria-hidden', 'true');
                    btnDownloadIssued.onclick = null;

                    const dlUrl = (meta.download_url || '').trim();
                    if (st === 'released' && dlUrl) {
                        btnDownloadIssued.classList.remove('hidden');
                        btnDownloadIssued.disabled = false;
                        btnDownloadIssued.setAttribute('aria-hidden', 'false');
                        btnDownloadIssued.onclick = () => forceDownload(dlUrl);
                    }
                }


                if (st === 'rejected' && (meta.reason || '').trim() !== '') {
                    banner.textContent = 'Reason for rejection: ' + meta.reason;
                    banner.classList.remove('hidden');
                    btnRequestAgain.classList.remove('hidden');
                    btnRequestAgain.disabled = false;
                    btnRequestAgain.setAttribute('aria-hidden', 'false');
                }

                // Application fields
                list.innerHTML = '';
                if (Array.isArray(res.application) && res.application.length) {
                    formEmpty.classList.add('hidden');
                    res.application.forEach(({
                        label,
                        value,
                        hide_in_view
                    }) => {
                        if (hide_in_view) return;
                        const norm = (label || '').trim().toLowerCase();
                        if (norm === 'additional information' || norm === 'additional info') return;

                        // Avoid showing raw JSON/object-valued species fields in
                        // the generic Application list (e.g. "Application - Species")
                        // which render as "[object Object],...". If this label
                        // mentions "species" and the value is a JSON array of
                        // objects, skip it here (the structured species block is
                        // rendered separately for treecut requests).
                        if (norm.includes('species')) {
                            try {
                                if (typeof value === 'string' && value.trim().startsWith('[')) {
                                    const parsed = JSON.parse(value);
                                    if (Array.isArray(parsed) && parsed.length && typeof parsed[0] === 'object') {
                                        return;
                                    }
                                }
                            } catch (e) {
                                // ignore parse errors and fall through
                            }
                            // also skip labels like "Application - Species"
                            if (norm.includes('application')) return;
                        }
                        const row = document.createElement('div');
                        row.className = 'defrow';
                        const dt = document.createElement('dt');
                        dt.textContent = label;
                        const dd = document.createElement('dd');
                        const isImageLike = norm.includes('signature') || /^https?:\/\//i.test(value || '') || (value || '').startsWith('data:image/');
                        if (isImageLike) {
                            const img = document.createElement('img');
                            img.src = value;
                            img.alt = label;
                            img.loading = 'lazy';
                            const wrap = document.createElement('div');
                            wrap.className = 'field-image';
                            wrap.appendChild(img);
                            dd.appendChild(wrap);
                        } else {
                            // If the field value is an array (or JSON array string),
                            // render it as a readable list rather than letting
                            // the default toString() produce "[object Object]...".
                            try {
                                let out = value;
                                // If value looks like a JSON array string, try to parse it
                                if (typeof out === 'string' && out.trim().startsWith('[')) {
                                    try {
                                        const parsed = JSON.parse(out);
                                        out = parsed;
                                    } catch (e) {
                                        // leave as string if parse fails
                                    }
                                }

                                if (Array.isArray(out)) {
                                    if (out.length && typeof out[0] === 'object') {
                                        // array of objects: render one-per-line with sensible fields
                                        out.forEach(item => {
                                            const line = document.createElement('div');
                                            const parts = [];
                                            if (item && typeof item === 'object') {
                                                if (item.name) parts.push(item.name);
                                                if (item.count) parts.push(item.count + ' tree' + (String(item.count) !== '1' ? 's' : ''));
                                                if (item.volume) parts.push((item.volume || '') + ' cu.m');
                                            } else {
                                                parts.push(String(item));
                                            }
                                            line.textContent = parts.filter(Boolean).join(' — ');
                                            dd.appendChild(line);
                                        });
                                    } else {
                                        // array of strings/numbers
                                        dd.textContent = out.join(', ');
                                    }
                                } else {
                                    dd.textContent = value;
                                }
                            } catch (err) {
                                // fallback to plain text
                                dd.textContent = value;
                            }
                        }
                        row.appendChild(dt);
                        row.appendChild(dd);
                        list.appendChild(row);
                    });
                } else {
                    formEmpty.classList.remove('hidden');
                }

                // Files
                filesList.innerHTML = '';
                if (Array.isArray(res.files) && res.files.length) {
                    filesEmpty.classList.add('hidden');
                    res.files.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'file-item';
                        li.dataset.fileUrl = f.url;
                        li.dataset.fileName = f.name;
                        li.dataset.fileExt = (f.ext || '').toLowerCase();
                        li.innerHTML = `<i class="far fa-file"></i><span class="name">${f.name}</span><span class="hint">${(f.ext||'').toUpperCase()}</span>`;
                        filesList.appendChild(li);
                    });
                } else {
                    filesEmpty.classList.remove('hidden');
                }

                // Reveal content
                hideModalSkeleton();
            }

            // table "View"
            document.getElementById('statusTableBody')?.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action="view"]');
                if (!btn) return;
                const tr = btn.closest('tr');
                const approvalId = tr?.dataset.approvalId;
                openApproval(approvalId);
            });

            // table "Download" (force download without opening a page or tab)
            document.getElementById('statusTableBody')?.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action="download"]');
                if (!btn) return;

                const tr = btn.closest('tr');
                const url = (tr?.dataset.downloadUrl || '').trim();
                if (!url) {
                    alert('Download link is not available yet.');
                    return;
                }

                try {
                    // Fast path for data URLs
                    if (url.startsWith('data:')) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.setAttribute('download', 'document');
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        return;
                    }

                    const res = await fetch(url, {
                        credentials: 'omit'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    // Try filename from headers; else derive from URL path
                    const dispo = res.headers.get('Content-Disposition') || '';
                    let filename = 'document';
                    const m = dispo.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    if (m && m[1]) {
                        filename = decodeURIComponent(m[1].replace(/["']/g, ''));
                    } else {
                        const u = new URL(url, window.location.origin);
                        filename = (u.pathname.split('/').pop() || 'document').split('?')[0];
                    }

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.setAttribute('download', filename);
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
                } catch (err) {
                    // Fallback: hidden iframe download (no navigation)
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = url;
                    document.body.appendChild(iframe);
                    setTimeout(() => iframe.remove(), 10000);
                }
            });

            // close modal
            document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => {
                exitEditMode();
                document.getElementById('viewModal').classList.add('hidden');
                closePreview();
            }));
            document.querySelector('.modal-backdrop')?.addEventListener('click', () => {
                exitEditMode();
                document.getElementById('viewModal').classList.add('hidden');
                closePreview();
            });

            // file preview
            document.getElementById('filesList')?.addEventListener('click', (e) => {
                const li = e.target.closest('.file-item');
                if (!li) return;
                showPreview(li.dataset.fileName || 'Document', li.dataset.fileUrl || '#', (li.dataset.fileExt || '').toLowerCase());
            });
            document.querySelector('[data-close-preview]')?.addEventListener('click', closePreview);

            function closePreview() {
                document.getElementById('filePreviewDrawer').classList.add('hidden');
                document.getElementById('previewImage').src = '';
                document.getElementById('previewFrame').src = '';
                document.getElementById('previewPdf').data = '';
                document.getElementById('previewPdfFallback').src = '';
            }

            function showPreview(name, url, ext) {
                const drawer = document.getElementById('filePreviewDrawer');
                document.getElementById('previewTitle').textContent = name;
                const imgWrap = document.getElementById('previewImageWrap');
                const pdfWrap = document.getElementById('previewPdfWrap');
                const frameWrap = document.getElementById('previewFrameWrap');
                const linkWrap = document.getElementById('previewLinkWrap');
                imgWrap.classList.add('hidden');
                pdfWrap.classList.add('hidden');
                frameWrap.classList.add('hidden');
                linkWrap.classList.add('hidden');

                const imgExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const offExt = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                const txtExt = ['txt', 'csv', 'json', 'md', 'log'];

                if (imgExt.includes(ext) || url.startsWith('data:image/')) {
                    document.getElementById('previewImage').src = url;
                    imgWrap.classList.remove('hidden');
                } else if (ext === 'pdf') {
                    const pdfUrl = url + (url.includes('#') ? '' : '#') + 'zoom=page-width';
                    document.getElementById('previewPdf').data = pdfUrl;
                    document.getElementById('previewPdfFallback').src = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    pdfWrap.classList.remove('hidden');
                } else if (offExt.includes(ext)) {
                    document.getElementById('previewFrame').src = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(url);
                    frameWrap.classList.remove('hidden');
                } else if (txtExt.includes(ext)) {
                    document.getElementById('previewFrame').src = 'https://docs.google.com/gview?embedded=1&url=' + encodeURIComponent(url);
                    frameWrap.classList.remove('hidden');
                } else {
                    document.getElementById('previewDownload').href = url;
                    linkWrap.classList.remove('hidden');
                }
                drawer.classList.remove('hidden');
            }

            // filters
            const filterStatus = document.getElementById('filterStatus');
            const filterReqType = document.getElementById('filterReqType');
            const filterPermit = document.getElementById('filterPermitType');
            const searchName = document.getElementById('searchName');
            const rowsCount = document.getElementById('rowsCount');
            const noRowsTr = document.getElementById('noRows');

            document.getElementById('btnClearFilters')?.addEventListener('click', () => {
                filterStatus.value = '';
                filterReqType.value = '';
                filterPermit.value = '';
                searchName.value = '';
                applyFilters();
            });
            [filterStatus, filterReqType, filterPermit, searchName].forEach(el => el.addEventListener('input', applyFilters));

            function applyFilters() {
                const st = (filterStatus.value || '').toLowerCase();
                const rt = (filterReqType.value || '').toLowerCase();
                const pt = (filterPermit.value || '').toLowerCase();
                const q = (searchName.value || '').trim().toLowerCase();
                let shown = 0;
                document.querySelectorAll('#statusTableBody tr').forEach(tr => {
                    if (tr.id === 'noRows') return;
                    const name = (tr.dataset.client || '').toLowerCase();
                    const stat = (tr.dataset.status || '').toLowerCase();
                    const reqT = (tr.dataset.requestType || '').toLowerCase();
                    const permT = (tr.dataset.permitType || '').toLowerCase();
                    let ok = true;
                    if (st && stat !== st) ok = false;
                    if (rt && reqT !== rt) ok = false;
                    if (pt && permT !== pt) ok = false;
                    if (q && !name.includes(q)) ok = false;
                    tr.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                if (noRowsTr) noRowsTr.style.display = shown === 0 ? 'table-row' : 'none';
                rowsCount.textContent = `${shown} result${shown===1?'':'s'}`;
            }

            // Run once to ensure the noRows state is correct when page loads
            applyFilters?.();


            btnEditPending?.addEventListener('click', () => {
                console.log('Edit button clicked for approval=', cachedDetails?.meta?.approval_id, 'status=', cachedDetails?.meta?.status, 'requestType=', cachedDetails?.meta?.request_type);
                logSeedlingRecords();
                enterEditMode();
            });

            btnAddSeedling?.addEventListener('click', (e) => {
                e.preventDefault();
                addSeedlingRow();
            });

            btnCancelEdit?.addEventListener('click', () => {
                exitEditMode();
            });

            editForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!cachedDetails || !cachedDetails.meta) return;
                if (editMessage) editMessage.textContent = '';

                const overlay = document.getElementById('loadingIndicator');
                const loadingMsgEl = document.getElementById('loadingMessage');

                const meta = cachedDetails.meta;
                const approvalId = meta.approval_id;
                if (!approvalId) {
                    if (editMessage) editMessage.textContent = 'Missing approval reference.';
                    return;
                }

                const formData = new FormData(editForm);
                formData.set('approval_id', approvalId);
                formData.set('request_type', meta.request_type || '');
                formData.set('permit_type', meta.permit_type || '');

                if (btnSaveEdit) btnSaveEdit.disabled = true;
                if (btnCancelEdit) btnCancelEdit.disabled = true;

                try {
                    if (overlay) {
                        if (loadingMsgEl) loadingMsgEl.textContent = 'Saving changes…';
                        overlay.style.display = 'flex';
                    }

                    const response = await fetch('../backend/users/update_application.php', {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data?.ok) {
                        const msg = data?.error || 'Update failed. Please try again.';
                        if (editMessage) editMessage.textContent = msg;
                        return;
                    }

                    // Wildlife regeneration is handled server-side inside update_application.php
                    // No client-side regeneration call is required here.

                    if (overlay && loadingMsgEl) loadingMsgEl.textContent = 'Refreshing details…';
                    exitEditMode(true);
                    await openApproval(approvalId);
                    showToast('Application updated successfully.');
                } catch (err) {
                    if (editMessage) editMessage.textContent = 'Network error. Please try again.';
                } finally {
                    if (btnSaveEdit) btnSaveEdit.disabled = false;
                    if (btnCancelEdit) btnCancelEdit.disabled = false;
                    if (overlay) overlay.style.display = 'none';
                }
            });


            // Request again (guard to rejected only)
            btnRequestAgain?.addEventListener('click', () => {
                if (!cachedDetails || !cachedDetails.meta) return;
                const statusNow = (cachedDetails.meta.status || '').toLowerCase();
                if (statusNow !== 'rejected') return;
                const meta = cachedDetails.meta;
                const reqType = (meta.request_type || '').toLowerCase();
                const permit = (meta.permit_type || '').toLowerCase();
                const routeMap = {
                    wildlife: 'useraddwild.php',
                    chainsaw: 'useraddchainsaw.php',
                    lumber: 'useraddlumber.php',
                    wood: 'useraddwood.php',
                    seedling: 'useraddseed.php',
                    treecut: 'useraddtreecut.php'
                };
                const target = routeMap[reqType] || 'user_home.php';
                const prefill = {
                    first_name: meta.first_name || '',
                    last_name: meta.last_name || '',
                    client_full_name: (meta.client || '').trim()
                };
                try {
                    const payload = {
                        approval_id: meta.approval_id,
                        request_type: reqType,
                        permit_type: permit,
                        prefill,
                        application: (cachedDetails.application || [])
                    };
                    sessionStorage.setItem('reapply_payload', JSON.stringify(payload));
                } catch {}
                let qs = '';
                if (reqType === 'wildlife' && permit === 'new') qs = `?mode=new&from=${encodeURIComponent(meta.approval_id||'')}`;
                else if (permit) qs = `?mode=${encodeURIComponent(permit)}&from=${encodeURIComponent(meta.approval_id||'')}`;
                window.location.href = target + qs;
            });

            /* ===== Notifications: timeago, open, mark read ===== */
            // relative times
            document.querySelectorAll('.notification-time[data-ts]').forEach(el => {
                const ts = Number(el.dataset.ts || '0') * 1000;
                if (!ts) return;
                el.textContent = timeAgo(ts);
                el.title = new Date(ts).toLocaleString();
            });

            function timeAgo(ms) {
                const s = Math.floor((Date.now() - ms) / 1000);
                if (s < 60) return 'just now';
                const m = Math.floor(s / 60);
                if (m < 60) return `${m} minute${m>1?'s':''} ago`;
                const h = Math.floor(m / 60);
                if (h < 24) return `${h} hour${h>1?'s':''} ago`;
                const d = Math.floor(h / 24);
                if (d < 7) return `${d} day${d>1?'s':''} ago`;
                const w = Math.floor(d / 7);
                if (w < 5) return `${w} week${w>1?'s':''} ago`;
                const mo = Math.floor(d / 30);
                if (mo < 12) return `${mo} month${mo>1?'s':''} ago`;
                const y = Math.floor(d / 365);
                return `${y} year${y>1?'s':''} ago`;
            }

            // mark all read
            const badge = document.getElementById('notifBadge');
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                const r = await fetch(`<?php echo basename(__FILE__); ?>?ajax=mark_all_read`, {
                        method: 'POST'
                    })
                    .then(r => r.json()).catch(() => ({
                        ok: false
                    }));
                if (!r.ok) return;
                document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                if (badge) badge.style.display = 'none';
            });

            // click a single notification
            document.querySelector('.notifications-dropdown')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                e.preventDefault();
                const notifId = link.dataset.notifId;
                const approvalId = link.dataset.approvalId || '';
                const incidentId = link.dataset.incidentId || '';

                // mark this one read (optimistic)
                link.closest('.notification-item')?.classList.remove('unread');
                const left = document.querySelectorAll('.notification-item.unread').length;
                if (badge) {
                    if (left > 0) {
                        badge.textContent = String(left);
                        badge.style.display = 'flex';
                    } else badge.style.display = 'none';
                }
                fetch(`<?php echo basename(__FILE__); ?>?ajax=mark_read&notif_id=${encodeURIComponent(notifId)}`, {
                    method: 'POST'
                }).catch(() => {});

                // open target
                if (approvalId) openApproval(approvalId);
                else if (incidentId) window.location.href = `user_reportaccident.php?view=${encodeURIComponent(incidentId)}`;
            });
        });
    </script>

</body>

</html>