<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo

function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}

try {
    if (!$pdo) throw new Exception('DB not available');

    $first   = norm($_POST['first_name']  ?? '');
    $middle  = norm($_POST['middle_name'] ?? '');
    $last    = norm($_POST['last_name']   ?? '');
    $desired = norm($_POST['desired_permit_type'] ?? ''); // "new" | "renewal"

    if ($first === '' || $last === '') {
        echo json_encode(['ok' => true]); // nothing to precheck
        exit;
    }

    // Client lookup
    $stmt = $pdo->prepare("
        SELECT client_id
        FROM public.client
        WHERE lower(trim(coalesce(first_name,'')))  = :f
          AND lower(trim(coalesce(middle_name,''))) = :m
          AND lower(trim(coalesce(last_name,'')))   = :l
        LIMIT 1
    ");
    $stmt->execute([':f' => $first, ':m' => $middle, ':l' => $last]);
    $client_id = $stmt->fetchColumn();

    if (!$client_id) {
        if ($desired === 'renewal') {
            echo json_encode(['ok' => true, 'block' => 'need_approved_new']); // must have approved NEW first
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Status flags
    $q = "
        SELECT
            bool_or(approval_status ILIKE 'pending'  AND permit_type ILIKE 'new')      AS has_pending_new,
            bool_or(approval_status ILIKE 'approved' AND permit_type ILIKE 'new')      AS has_approved_new,
            bool_or(approval_status ILIKE 'pending'  AND permit_type ILIKE 'renewal')  AS has_pending_renewal
        FROM public.approval
        WHERE client_id = :cid AND request_type ILIKE 'wildlife'
    ";
    $stmt = $pdo->prepare($q);
    $stmt->execute([':cid' => $client_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['has_pending_new' => false, 'has_approved_new' => false, 'has_pending_renewal' => false];

    $hasPendingNew     = (bool)$row['has_pending_new'];
    $hasApprovedNew    = (bool)$row['has_approved_new'];
    $hasPendingRenewal = (bool)$row['has_pending_renewal'];

    if ($desired === 'renewal') {
        if ($hasPendingRenewal) {
            echo json_encode(['ok' => true, 'block' => 'pending_renewal']);
            exit;
        }
        if (!$hasApprovedNew) {
            echo json_encode(['ok' => true, 'block' => 'need_approved_new']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // NEW
    if ($hasPendingNew) {
        echo json_encode(['ok' => true, 'block' => 'pending_new']);
        exit;
    }
    if ($hasPendingRenewal) {
        echo json_encode(['ok' => true, 'block' => 'pending_renewal']);
        exit;
    }
    if ($hasApprovedNew) {
        echo json_encode(['ok' => true, 'offer' => 'renewal']);
        exit;
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
