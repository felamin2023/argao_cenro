<?php
// precheck_wood.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // provides $pdo

function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}

try {
    if (!$pdo) {
        throw new Exception('DB not available');
    }

    // ---- Inputs ----
    $first   = norm($_POST['first_name']  ?? '');
    $middle  = norm($_POST['middle_name'] ?? '');
    $last    = norm($_POST['last_name']   ?? '');
    // what the user is currently trying to submit (either "new" or "renewal")
    $desired = norm($_POST['desired_permit_type'] ?? '');

    // If we don't even have a name, nothing to precheck.
    if ($first === '' || $last === '') {
        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- Client lookup (supports normalized columns if present) ----
    $hasNormCols = false;
    try {
        $pdo->query("SELECT norm_first, norm_middle, norm_last FROM public.client LIMIT 0");
        $hasNormCols = true;
    } catch (\Throwable $ignored) {
    }

    if ($hasNormCols) {
        $stmt = $pdo->prepare("
      SELECT client_id FROM public.client
      WHERE norm_first = :first
        AND norm_middle = :middle
        AND norm_last = :last
      LIMIT 1
    ");
    } else {
        $stmt = $pdo->prepare("
      SELECT client_id FROM public.client
      WHERE lower(trim(coalesce(first_name,'')))  = :first
        AND lower(trim(coalesce(middle_name,''))) = :middle
        AND lower(trim(coalesce(last_name,'')))   = :last
      LIMIT 1
    ");
    }
    $stmt->execute([':first' => $first, ':middle' => $middle, ':last' => $last]);
    $client_id = $stmt->fetchColumn();

    // ---- If no client found ----
    // Special case: user is attempting a RENEWAL but there is no client on record.
    // Tell the frontend to show the "Need Approved NEW" modal and offer to switch.
    if (!$client_id) {
        if ($desired === 'renewal') {
            echo json_encode(['ok' => true, 'block' => 'need_approved_new']);
            exit;
        }
        // If NEW (or unspecified), allow as usual.
        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- Gather status flags for WOOD/WPP requests ----
    // We use request_type = 'wood' to distinguish from other flows (e.g., 'chainsaw').
    $hasPendingNew      = false;
    $hasApprovedNew     = false;
    $hasPendingRenewal  = false;

    // any PENDING NEW wood processing?
    $stmt = $pdo->prepare("
    SELECT 1 FROM public.approval
    WHERE client_id = :cid
      AND lower(request_type) = 'wood'
      AND lower(permit_type)  = 'new'
      AND lower(approval_status) = 'pending'
    LIMIT 1
  ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingNew = (bool) $stmt->fetchColumn();

    // any APPROVED NEW wood processing?
    $stmt = $pdo->prepare("
    SELECT 1 FROM public.approval
    WHERE client_id = :cid
      AND lower(request_type) = 'wood'
      AND lower(permit_type)  = 'new'
      AND lower(approval_status) = 'approved'
    ORDER BY approved_at DESC NULLS LAST
    LIMIT 1
  ");
    $stmt->execute([':cid' => $client_id]);
    $hasApprovedNew = (bool) $stmt->fetchColumn();

    // any PENDING RENEWAL wood processing?
    $stmt = $pdo->prepare("
    SELECT 1 FROM public.approval
    WHERE client_id = :cid
      AND lower(request_type) = 'wood'
      AND lower(permit_type)  = 'renewal'
      AND lower(approval_status) = 'pending'
    LIMIT 1
  ");
    $stmt->execute([':cid' => $client_id]);
    $hasPendingRenewal = (bool) $stmt->fetchColumn();

    /*
    Decision matrix:

    If user is submitting RENEWAL:
      - block if there's already a pending renewal
      - block if there is NO approved NEW
      - otherwise allow

    If user is submitting NEW (or didn't specify):
      - block if there's a pending NEW
      - also block if there's already a pending RENEWAL (avoid parallel flows)
      - if there's an approved NEW and no pending renewal -> offer renewal
      - else allow
  */

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

    // default path: NEW (or unspecified)
    if ($hasPendingNew) {
        echo json_encode(['ok' => true, 'block' => 'pending_new']);
        exit;
    }
    if ($hasPendingRenewal) {
        echo json_encode(['ok' => true, 'block' => 'pending_renewal']);
        exit;
    }
    if ($hasApprovedNew) {
        // No pending renewal; suggest moving to renewal
        echo json_encode(['ok' => true, 'offer' => 'renewal']);
        exit;
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
