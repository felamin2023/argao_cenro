<?php
require 'backend/connection.php';
$sql = <<<'SQL'
SELECT
    ad.no AS permit_no,
    ad.date_issued AS doc_date_issued,
    ad.expiry_date AS doc_expiry_date,
    a.approval_id,
    a.client_id,
    c.first_name AS client_first,
    c.middle_name AS client_middle,
    c.last_name AS client_last,
    c.sitio_street,
    c.barangay,
    c.municipality AS client_municipality,
    c.city AS client_city,
    c.contact_number AS client_contact,
    af.contact_number AS af_contact_number,
    af.present_address,
    af.province AS af_province,
    af.location,
    af.purpose_of_use,
    af.brand,
    af.model,
    af.date_of_acquisition,
    af.serial_number_chainsaw,
    af.horsepower,
    af.maximum_length_of_guide_bar,
    af.permit_number AS stored_permit_number,
    af.expiry_date AS af_expiry_date,
    req.chainsaw_cert_terms,
    req.chainsaw_cert_sticker,
    req.chainsaw_staff_work,
    req.chainsaw_permit_to_sell,
    req.chainsaw_business_permit,
    req.chainsaw_old_registration
FROM public.approved_docs ad
JOIN public.approval a ON a.approval_id = ad.approval_id
LEFT JOIN public.client c ON c.client_id = a.client_id
LEFT JOIN public.application_form af ON af.application_id = a.application_id
LEFT JOIN public.requirements req ON req.requirement_id = a.requirement_id
WHERE NULLIF(btrim(ad.no), '') IS NOT NULL
ORDER BY COALESCE(ad.date_issued, a.submitted_at) DESC NULLS LAST
LIMIT 5
SQL;
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$permitRecords = [];
foreach ($rows as $row) {
    $permitNo = trim((string)($row['permit_no'] ?? ''));
    if ($permitNo === '') continue;
    $approvalId = $row['approval_id'] ?? null;
    $issuedLabel = '';
    if (!empty($row['doc_date_issued'])) {
        try {
            $issuedLabel = (new DateTime((string)$row['doc_date_issued']))->format('M j, Y');
        } catch (Throwable $e) {
            $issuedLabel = '';
        }
    }
    $files = array_filter([
        'chainsaw_cert_terms' => $row['chainsaw_cert_terms'] ?? null,
        'chainsaw_cert_sticker' => $row['chainsaw_cert_sticker'] ?? null,
        'chainsaw_staff_work' => $row['chainsaw_staff_work'] ?? null,
        'chainsaw_permit_to_sell' => $row['chainsaw_permit_to_sell'] ?? null,
        'chainsaw_business_permit' => $row['chainsaw_business_permit'] ?? null,
        'chainsaw_old_registration' => $row['chainsaw_old_registration'] ?? null,
    ], static fn($v) => !empty($v));
    $permitRecords[] = [
        'approval_id' => $approvalId,
        'permit_no' => $permitNo,
        'client_id' => $row['client_id'],
        'issued_date' => $row['doc_date_issued'] ?? null,
        'expiry_date' => $row['doc_expiry_date'] ?? ($row['af_expiry_date'] ?? null),
        'client' => [
            'first' => $row['client_first'] ?? '',
            'middle' => $row['client_middle'] ?? '',
            'last' => $row['client_last'] ?? '',
        ],
        'address' => [
            'street' => $row['sitio_street'] ?? '',
            'barangay' => $row['barangay'] ?? '',
            'municipality' => $row['client_municipality'] ?? $row['client_city'] ?? '',
            'province' => $row['client_province'] ?? $row['af_province'] ?? '',
            'full' => $row['present_address'] ?? '',
        ],
        'contact_number' => $row['af_contact_number'] ?? $row['client_contact'] ?? '',
        'purpose' => $row['purpose_of_use'] ?? '',
        'brand' => $row['brand'] ?? '',
        'model' => $row['model'] ?? '',
        'date_of_acquisition' => $row['date_of_acquisition'] ?? '',
        'serial_number' => $row['serial_number_chainsaw'] ?? '',
        'horsepower' => $row['horsepower'] ?? '',
        'guide_bar' => $row['maximum_length_of_guide_bar'] ?? '',
        'files' => $files,
        'label' => $issuedLabel ? ($permitNo . ' - ' . $issuedLabel) : $permitNo,
    ];
}
var_export($permitRecords);
