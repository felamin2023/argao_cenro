<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo

function norm(?string $s): string
{
    return strtolower(trim((string)$s));
}
function smart_sim(string $a, string $b): float
{
    $a = preg_replace('/[^a-z]/', '', strtolower(trim($a)));
    $b = preg_replace('/[^a-z]/', '', strtolower(trim($b)));
    if ($a === '' || $b === '') return 0.0;
    if ($a === $b) return 1.0;
    $d = levenshtein($a, $b);
    $max = max(strlen($a), strlen($b));
    $lev = 1.0 - ($d / $max);
    $min = min(strlen($a), strlen($b));
    $i = 0;
    while ($i < $min && $a[$i] === $b[$i]) $i++;
    $prefix = $min ? $i / $min : 0.0;
    $bonus = metaphone($a) === metaphone($b) ? 0.10 : 0.0;
    return min(1.0, max($lev, $prefix) + $bonus);
}

try {
    if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');
    if (!$pdo) throw new Exception('DB not available');

    $first   = trim($_POST['first_name']  ?? '');
    $middle  = trim($_POST['middle_name'] ?? '');
    $last    = trim($_POST['last_name']   ?? '');
    $desired = isset($_POST['desired_permit_type']) && in_array(strtolower($_POST['desired_permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['desired_permit_type']) : 'new';

    $override_client_id = trim((string)($_POST['use_client_id'] ?? $_POST['use_existing_client_id'] ?? ''));

    if ($first === '' && $middle === '' && $last === '' && $override_client_id === '') {
        echo json_encode(['ok' => true]);
        exit;
    }

    $client_id = null;

    // 1) explicit existing id
    if ($override_client_id !== '') {
        $chk = $pdo->prepare("SELECT client_id::text AS client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $chk->execute([':cid' => $override_client_id]);
        $client_id = $chk->fetchColumn() ?: null;
        if (!$client_id) {
            echo json_encode(['ok' => false, 'message' => 'Invalid client id.']);
            exit;
        }
    }

    // 2) exact case-insensitive match
    if (!$client_id) {
        $nf = norm($first);
        $nm = norm($middle);
        $nl = norm($last);
        $stmt = $pdo->prepare("
      SELECT client_id::text AS client_id
      FROM public.client
      WHERE lower(trim(coalesce(first_name,'')))  = :f
        AND lower(trim(coalesce(middle_name,''))) = :m
        AND lower(trim(coalesce(last_name,'')))   = :l
      LIMIT 1
    ");
        $stmt->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
        $client_id = $stmt->fetchColumn() ?: null;
    }

    // 3) fuzzy suggestion (unordered), no blocks here
    if (!$client_id && $override_client_id === '') {
        $THRESHOLD = 0.68;
        $CANDIDATE_LIMIT = 1000;
        $clean = fn(?string $s) => preg_replace('/[^a-z]/', '', strtolower(trim((string)$s)));
        $nf = $clean($first);
        $nm = $clean($middle);
        $nl = $clean($last);

        $q = $pdo->prepare("
      SELECT client_id::text AS client_id, first_name, middle_name, last_name
      FROM public.client
      WHERE
            left(regexp_replace(lower(coalesce(first_name,'')),  '[^a-z]', '', 'g'), 2) = left(:f,2)
         OR left(regexp_replace(lower(coalesce(middle_name,'')), '[^a-z]', '', 'g'), 2) = left(:m,2)
         OR left(regexp_replace(lower(coalesce(last_name,'')),  '[^a-z]', '', 'g'), 2) = left(:l,2)
      ORDER BY client_id DESC
      LIMIT {$CANDIDATE_LIMIT}
    ");
        $q->execute([':f' => $nf, ':m' => $nm, ':l' => $nl]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            $rows = $pdo->query("
        SELECT client_id::text AS client_id, first_name, middle_name, last_name
        FROM public.client
        ORDER BY client_id DESC
        LIMIT {$CANDIDATE_LIMIT}
      ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $inp  = [$nf, $nm, $nl];
        $best = null;
        $bestScore = 0.0;
        foreach ($rows as $r) {
            $cand = [$clean($r['first_name'] ?? ''), $clean($r['middle_name'] ?? ''), $clean($r['last_name'] ?? '')];
            $perms = [
                [$inp[0], $inp[1], $inp[2]],
                [$inp[0], $inp[2], $inp[1]],
                [$inp[1], $inp[0], $inp[2]],
                [$inp[1], $inp[2], $inp[0]],
                [$inp[2], $inp[0], $inp[1]],
                [$inp[2], $inp[1], $inp[0]],
            ];
            $local = 0.0;
            foreach ($perms as [$A, $B, $C]) {
                $sf = smart_sim($A, $cand[0]);
                $sm = ($B === '') ? 0.65 : smart_sim($B, $cand[1]); // middle optional baseline
                $sl = smart_sim($C, $cand[2]);
                $score = 0.35 * $sf + 0.15 * $sm + 0.50 * $sl;
                if ($score > $local) $local = $score;
            }
            if ($local > $bestScore) {
                $bestScore = $local;
                $best = $r;
            }
        }

        if ($best && $bestScore >= $THRESHOLD) {
            $suggested = [
                'client_id'   => (string)$best['client_id'],
                'first_name'  => (string)($best['first_name']  ?? ''),
                'middle_name' => (string)($best['middle_name'] ?? ''),
                'last_name'   => (string)($best['last_name']   ?? ''),
                'score'       => round($bestScore, 2),
            ];
            echo json_encode([
                'ok' => true,
                'needs_confirm' => true,
                'candidates' => [[
                    'client_id' => $suggested['client_id'],
                    'first_name' => $suggested['first_name'],
                    'middle_name' => $suggested['middle_name'],
                    'last_name' => $suggested['last_name'],
                    'score' => $suggested['score'],
                ]],
                'existing_client_id'     => $suggested['client_id'],
                'existing_client_first'  => $suggested['first_name'],
                'existing_client_middle' => $suggested['middle_name'],
                'existing_client_last'   => $suggested['last_name'],
                'existing_client_name'   => trim($suggested['first_name'] . ' ' . $suggested['middle_name'] . ' ' . $suggested['last_name']),
                'suggestion_score'       => $suggested['score'],
            ]);
            exit;
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // If we have a client, compute Chainsaw flags and echo name
    if ($client_id) {
        $nq = $pdo->prepare("SELECT first_name, middle_name, last_name FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $nq->execute([':cid' => $client_id]);
        $nr = $nq->fetch(PDO::FETCH_ASSOC) ?: [];
        $extra = [
            'existing_client_id'     => (string)$client_id,
            'existing_client_first'  => (string)($nr['first_name']  ?? ''),
            'existing_client_middle' => (string)($nr['middle_name'] ?? ''),
            'existing_client_last'   => (string)($nr['last_name']   ?? ''),
            'existing_client_name'   => trim(($nr['first_name'] ?? '') . ' ' . ($nr['middle_name'] ?? '') . ' ' . ($nr['last_name'] ?? '')),
        ];

        $stmt = $pdo->prepare("
      SELECT
        bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')     AS has_pending_new,
        bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')     AS has_released_new,
        bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
        bool_or(approval_status ILIKE 'for payment')                                AS has_for_payment
      FROM public.approval
      WHERE client_id::text = :cid AND request_type ILIKE 'chainsaw'
    ");

        $uStmt = $pdo->prepare("
    SELECT 1
    FROM public.approval a
    JOIN public.approved_docs d ON d.approval_id = a.approval_id
    WHERE a.client_id::text = :cid
      AND lower(a.request_type)   = 'chainsaw'
      AND lower(a.approval_status) = 'released'
      AND lower(a.permit_type)     IN ('new','renewal')
      AND d.expiry_date::date >= current_date
    LIMIT 1
");
        $stmt->execute([':cid' => $client_id]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $hasPendingNew     = !empty($f['has_pending_new']);
        $hasReleasedNew    = !empty($f['has_released_new']);
        $hasPendingRenewal = !empty($f['has_pending_renewal']);
        $hasForPayment     = !empty($f['has_for_payment']);

        $uStmt->execute([':cid' => $client_id]);
        $hasUnexpiredReleased = (bool)$uStmt->fetchColumn();

        if ($hasForPayment) {
            echo json_encode(array_merge(['ok' => true, 'block' => 'for_payment'], $extra));
            exit;
        }

        if ($desired === 'new') {
            if ($hasPendingNew) {
                echo json_encode(array_merge(['ok' => true, 'block' => 'pending_new'], $extra));
                exit;
            }
            if ($hasPendingRenewal) {
                echo json_encode(array_merge(['ok' => true, 'block' => 'pending_renewal'], $extra));
                exit;
            }
            if ($hasReleasedNew) {
                echo json_encode(array_merge(['ok' => true, 'offer' => 'renewal'], $extra));
                exit;
            }
        } else { // renewal
            if ($hasPendingRenewal) {
                echo json_encode(array_merge(['ok' => true, 'block' => 'pending_renewal'], $extra));
                exit;
            }

            // NEW: block if a released chainsaw permit (new/renewal) is still unexpired
            if (!empty($hasUnexpiredReleased)) {
                echo json_encode(array_merge(['ok' => true, 'block' => 'unexpired_permit'], $extra));
                exit;
            }
            if (!$hasReleasedNew) {
                echo json_encode(array_merge(['ok' => true, 'block' => 'need_approved_new'], $extra));
                exit;
            }
        }

        echo json_encode(array_merge(['ok' => true], $extra));
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit;
}
