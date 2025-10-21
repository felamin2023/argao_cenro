<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo

function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}

// helper: normalized Levenshtein ratio (kept for completeness)
function sim(string $a, string $b): float
{
    $a = norm($a);
    $b = norm($b);
    if ($a === '' || $b === '') return 0.0;
    if ($a === $b) return 1.0;
    $d = levenshtein($a, $b);
    $max = max(strlen($a), strlen($b));
    return $max > 0 ? 1.0 - ($d / $max) : 0.0;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    if (!$pdo) {
        throw new Exception('DB not available');
    }

    // -------- inputs --------
    $first   = trim($_POST['first_name']  ?? '');
    $middle  = trim($_POST['middle_name'] ?? '');
    $last    = trim($_POST['last_name']   ?? '');
    $desired = isset($_POST['desired_permit_type']) && in_array(strtolower($_POST['desired_permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['desired_permit_type'])
        : 'new';

    // NEW: prefer explicit client id chosen from "Use existing"
    $override_client_id = trim((string)($_POST['use_existing_client_id'] ?? ''));

    // -------- resolve client --------
    $client_id = null;

    if ($override_client_id !== '') {
        // If client_id is UUID, keep this guard; otherwise remove/adjust
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $override_client_id)) {
            echo json_encode(['ok' => false, 'message' => 'Invalid client id.']);
            exit;
        }
        $chk = $pdo->prepare("SELECT client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $chk->execute([':cid' => $override_client_id]);
        $client_id = $chk->fetchColumn() ?: null;
    }

    if (!$client_id) {
        // fall back to exact-name match
        $nf = norm($first);
        $nm = norm($middle);
        $nl = norm($last);

        $stmt = $pdo->prepare("
            SELECT client_id FROM public.client
            WHERE lower(trim(coalesce(first_name,'')))  = :f
              AND lower(trim(coalesce(middle_name,''))) = :m
              AND lower(trim(coalesce(last_name,'')))   = :l
            LIMIT 1
        ");
        $stmt->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
        $client_id = $stmt->fetchColumn();
    }

    // --- Fuzzy unordered suggestion (handles typos + scrambled order)
    // Only run this when there is NO override id AND we still have no exact match.
    if (!$client_id && $override_client_id === '') {
        $THRESHOLD = 0.68;       // similarity threshold
        $CANDIDATE_LIMIT = 1000; // max candidates to inspect

        // letters-only inputs (for 2-letter prefix matching)
        $clean = function (?string $s): string {
            return preg_replace('/[^a-z]/', '', strtolower(trim((string)$s)));
        };
        $nf_clean = $clean($first);
        $nm_clean = $clean($middle);
        $nl_clean = $clean($last);

        // Broader candidate set: share a 2-letter prefix on ANY name field
        $q = $pdo->prepare("
            SELECT client_id, first_name, middle_name, last_name
            FROM public.client
            WHERE
                  left(regexp_replace(lower(coalesce(first_name,'')),  '[^a-z]', '', 'g'), 2) = left(:f,2)
               OR left(regexp_replace(lower(coalesce(middle_name,'')), '[^a-z]', '', 'g'), 2) = left(:m,2)
               OR left(regexp_replace(lower(coalesce(last_name,'')),  '[^a-z]', '', 'g'), 2) = left(:l,2)
            ORDER BY client_id DESC
            LIMIT {$CANDIDATE_LIMIT}
        ");
        $q->execute([':f' => $nf_clean, ':m' => $nm_clean, ':l' => $nl_clean]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fallback: newest N clients if no prefix matches
        if (!$rows) {
            $q2 = $pdo->query("
                SELECT client_id, first_name, middle_name, last_name
                FROM public.client
                ORDER BY client_id DESC
                LIMIT {$CANDIDATE_LIMIT}
            ");
            $rows = $q2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // helpers
        $normName = function (?string $s): string {
            return preg_replace('/[^a-z]/', '', strtolower(trim((string)$s)));
        };
        $smartSim = function (string $a, string $b): float {
            $a = preg_replace('/[^a-z]/', '', strtolower(trim($a)));
            $b = preg_replace('/[^a-z]/', '', strtolower(trim($b)));
            if ($a === '' || $b === '') return 0.0;
            if ($a === $b) return 1.0;

            $d   = levenshtein($a, $b);
            $max = max(strlen($a), strlen($b));
            $lev = 1.0 - ($d / $max);

            $min = min(strlen($a), strlen($b));
            $i = 0;
            while ($i < $min && $a[$i] === $b[$i]) $i++;
            $prefix = $min ? $i / $min : 0.0;

            $bonus = metaphone($a) === metaphone($b) ? 0.10 : 0.0;

            return min(1.0, max($lev, $prefix) + $bonus);
        };

        $best = null;
        $bestScore = 0.0;

        $inp = [$nf, $nm, $nl];
        foreach ($rows as $r) {
            $cand = [
                $normName($r['first_name']  ?? ''),
                $normName($r['middle_name'] ?? ''),
                $normName($r['last_name']   ?? '')
            ];

            // try all permutations to ignore field-order mistakes
            $perms = [
                [$inp[0], $inp[1], $inp[2]],
                [$inp[0], $inp[2], $inp[1]],
                [$inp[1], $inp[0], $inp[2]],
                [$inp[1], $inp[2], $inp[0]],
                [$inp[2], $inp[0], $inp[1]],
                [$inp[2], $inp[1], $inp[0]],
            ];

            $localBest = 0.0;
            foreach ($perms as [$A, $B, $C]) {
                $sf = $smartSim($A, $cand[0]);
                $sm = $B === '' ? 0.65 : $smartSim($B, $cand[1]); // middle optional baseline
                $sl = $smartSim($C, $cand[2]);

                // slightly heavier last name
                $score = 0.35 * $sf + 0.15 * $sm + 0.50 * $sl;

                if ($score > $localBest) $localBest = $score;
            }

            if ($localBest > $bestScore) {
                $bestScore = $localBest;
                $best = $r;
            }
        }

        if ($best && $bestScore >= $THRESHOLD) {
            echo json_encode([
                'ok' => true,
                'suggest_existing_client' => [
                    'client_id' => (string)$best['client_id'],
                    'full_name' => trim(($best['first_name'] ?? '') . ' ' . ($best['middle_name'] ?? '') . ' ' . ($best['last_name'] ?? '')),
                    'score'     => round($bestScore, 2),
                ]
            ]);
            exit;
        }

        // no suggestion — allow flow to continue as brand new
        echo json_encode(['ok' => true]);
        exit;
    }

    // If we get here with a client_id, evaluate approval flags for wildlife.
    if ($client_id) {
        $stmt = $pdo->prepare("
            SELECT
                bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')     AS has_pending_new,
                bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')     AS has_released_new,
                bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
                bool_or(approval_status ILIKE 'for payment')                               AS has_for_payment
            FROM public.approval
            WHERE client_id = :cid AND request_type ILIKE 'wildlife'
        ");
        $stmt->execute([':cid' => $client_id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $hasPendingNew     = !empty($f['has_pending_new']);
        $hasReleasedNew    = !empty($f['has_released_new']);
        $hasPendingRenewal = !empty($f['has_pending_renewal']);
        $hasForPayment     = !empty($f['has_for_payment']);

        // Unpaid wildlife blocks everything
        if ($hasForPayment) {
            echo json_encode([
                'ok'      => true,
                'block'   => 'for_payment',
                'message' => 'You still have an unpaid wildlife permit (status: for payment). Please pay personally before filing another request.'
            ]);
            exit;
        }

        if ($desired === 'new') {
            if ($hasPendingNew) {
                echo json_encode(['ok' => true, 'block' => 'pending_new']);
                exit;
            }
            // Offer to switch to renewal if they already have a released NEW
            if ($hasReleasedNew) {
                echo json_encode(['ok' => true, 'offer' => 'renewal']);
                exit;
            }
        } else { // renewal
            if ($hasPendingRenewal) {
                echo json_encode(['ok' => true, 'block' => 'pending_renewal']);
                exit;
            }
            if (!$hasReleasedNew) {
                echo json_encode(['ok' => true, 'block' => 'need_released_new']);
                exit;
            }

            // NEW: block renewal if there is any released wildlife approval with a NOT-YET-EXPIRED permit
            $q = $pdo->prepare("
        SELECT 1
        FROM public.approval a
        JOIN public.approved_docs d ON d.approval_id = a.approval_id
        WHERE a.client_id = :cid
          AND a.request_type ILIKE 'wildlife'
          AND a.approval_status ILIKE 'released'
          AND d.expiry_date IS NOT NULL
          AND d.expiry_date::date >= CURRENT_DATE
        LIMIT 1
    ");
            $q->execute([':cid' => $client_id]);
            if ($q->fetchColumn()) {
                echo json_encode([
                    'ok'    => true,
                    'block' => 'unexpired_permit',
                    'message' => '<div style="padding:16px 20px;line-height:1.6">You still have an <b>unexpired</b> wildlife permit.<br><br>Please wait until your current permit <b>expires</b> before requesting a renewal.</div>'
                ]);
                exit;
            }
        }
    }

    // default OK
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
