<?php
require 'backend/connection.php';
$mode = $argv[1] ?? null;
$arg = $argv[2] ?? null;
if ($mode === 'user' && $arg) {
    $stmt = $pdo->prepare('SELECT user_id, email FROM public.users WHERE user_id = :uid');
    $stmt->execute([':uid' => $arg]);
    var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
if ($mode === 'clients' && $arg) {
    $stmt = $pdo->prepare('SELECT client_id, user_id FROM public.client WHERE user_id = :uid');
    $stmt->execute([':uid' => $arg]);
    var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
if ($mode === 'permits' && $arg) {
    $sql = <<<'SQL'
WITH user_clients AS (
    SELECT client_id FROM public.client WHERE user_id = :uid
)
SELECT ad.no AS permit_no, ad.date_issued, a.approval_status
FROM public.approved_docs ad
JOIN public.approval a ON a.approval_id = ad.approval_id
WHERE a.request_type ILIKE 'chainsaw'
  AND a.client_id IN (SELECT client_id FROM user_clients)
  AND NULLIF(btrim(ad.no), '') IS NOT NULL
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $arg]);
    var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
if ($mode === 'nullclients') {
    $sql = <<<'SQL'
SELECT COUNT(*) AS cnt
FROM public.approval a
JOIN public.client c ON c.client_id = a.client_id
WHERE lower(a.request_type) = 'chainsaw'
  AND c.user_id IS NULL
SQL;
    $stmt = $pdo->query($sql);
    var_export($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}
if ($mode === 'users') {
    $sql = <<<'SQL'
SELECT DISTINCT c.user_id, u.email, ad.no
FROM public.approval a
JOIN public.approved_docs ad ON ad.approval_id = a.approval_id
JOIN public.client c ON c.client_id = a.client_id
LEFT JOIN public.users u ON u.user_id = c.user_id
WHERE lower(a.request_type) = 'chainsaw'
SQL;
    $stmt = $pdo->query($sql);
    var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
$sql = <<<'SQL'
SELECT a.approval_id, a.request_type, a.approval_status, ad.no, a.client_id, c.user_id
FROM public.approval a
JOIN public.approved_docs ad ON ad.approval_id = a.approval_id
LEFT JOIN public.client c ON c.client_id = a.client_id
WHERE lower(a.request_type) = 'chainsaw'
ORDER BY ad.date_issued DESC NULLS LAST
LIMIT 20
SQL;
$stmt = $pdo->query($sql);
var_export($stmt->fetchAll(PDO::FETCH_ASSOC));
