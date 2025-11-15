<?php
require 'backend/connection.php';
$sql = <<<'SQL'
SELECT
    ad.no AS permit_no,
    ad.date_issued,
    ad.expiry_date,
    a.approval_id,
    a.client_id
FROM public.approved_docs ad
JOIN public.approval a ON a.approval_id = ad.approval_id
WHERE NULLIF(btrim(ad.no), '') IS NOT NULL
ORDER BY COALESCE(ad.date_issued, a.submitted_at) DESC NULLS LAST
LIMIT 200
SQL;
$stmt = $pdo->query($sql);
var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
