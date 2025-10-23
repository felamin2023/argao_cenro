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

    $d   = levenshtein($a, $b);
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
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    if (!$pdo) {
        throw new Exception('DB not available');
    }

    $first   = trim($_POST['first_name']  ?? '');
    $middle  = trim($_POST['middle_name'] ?? '');
    $last    = trim($_POST['last_name']   ?? '');
    $desired = isset($_POST['desired_permit_type']) && in_array(strtolower($_POST['desired_permit_type']), ['new', 'renewal'], true)
        ? strtolower($_POST['desired_permit_type'])
        : 'new';

    $override_client_id = trim((string)($_POST['use_existing_client_id'] ?? ''));

    if ($first === '' && $middle === '' && $last === '' && $override_client_id === '') {
        echo json_encode(['ok' => true]);
        exit;
    }

    $client_id = null;

    if ($override_client_id !== '') {
        $chk = $pdo->prepare("SELECT client_id::text AS client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $chk->execute([':cid' => $override_client_id]);
        $client_id = $chk->fetchColumn() ?: null;
        if (!$client_id) {
            echo json_encode(['ok' => false, 'message' => 'Invalid client id.']);
            exit;
        }
    }

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

    $emit_existing = function (string $cid, string $desired) use ($pdo) {
        $nq = $pdo->prepare("SELECT first_name, middle_name, last_name FROM public.client WHERE client_id::text = :cid LIMIT 1");
        $nq->execute([':cid' => $cid]);
        $nrow = $nq->fetch(PDO::FETCH_ASSOC) ?: [];

        $fStmt = $pdo->prepare("
            SELECT
                COUNT(*) > 0                                                             AS has_records,
                bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')   AS has_pending_new,
                bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal') AS has_pending_renewal,
                bool_or(approval_status ILIKE 'for payment')                             AS has_for_payment,
                bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')   AS has_released_new
            FROM public.approval
            WHERE client_id::text = :cid AND request_type ILIKE 'wood'
        ");
        $fStmt->execute([':cid' => $cid]);
        $flagsRow = $fStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $uq = $pdo->prepare("
            SELECT 1
            FROM public.approval a
            JOIN public.approved_docs d ON d.approval_id = a.approval_id
            WHERE a.client_id::text = :cid
              AND a.request_type ILIKE 'wood'
              AND a.approval_status ILIKE 'released'
              AND a.permit_type ILIKE ANY (ARRAY['new','renewal'])
              AND d.expiry_date IS NOT NULL
              AND d.expiry_date::date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
            LIMIT 1
        ");
        $uq->execute([':cid' => $cid]);
        $has_unexpired = (bool)$uq->fetchColumn();

        $has_records         = !empty($flagsRow['has_records']);
        $has_pending_new     = !empty($flagsRow['has_pending_new']);
        $has_pending_renewal = !empty($flagsRow['has_pending_renewal']);
        $has_for_payment     = !empty($flagsRow['has_for_payment']);
        $has_released_new    = !empty($flagsRow['has_released_new']);

        $block = null;
        $message = null;
        $suggest = null;

        if ($has_for_payment) {
            $block = 'for_payment';
            $message = 'You still have a Wood Processing Plant permit marked for payment. Please settle it before submitting another request.';
        } else {
            if ($desired === 'new') {
                if ($has_released_new && !$has_unexpired) {
                    $suggest = 'renewal';
                }
                if ($has_pending_new) {
                    $block = 'pending_new';
                    $message = 'You already have a pending NEW Wood Processing Plant application.';
                } elseif ($has_pending_renewal) {
                    $block = 'pending_renewal';
                    $message = 'You already have a pending Wood Processing Plant renewal application.';
                }
            } else {
                if ($has_pending_new || $has_pending_renewal) {
                    $block = 'pending_renewal';
                    $message = 'You already have a pending Wood Processing Plant application. Please wait for the update first.';
                } elseif ($has_unexpired) {
                    $block = 'unexpired_permit';
                    $message = 'You still have an unexpired Wood Processing Plant permit. Please wait for it to expire before requesting a renewal.';
                }
            }
        }

        $first = (string)($nrow['first_name'] ?? '');
        $middle = (string)($nrow['middle_name'] ?? '');
        $last = (string)($nrow['last_name'] ?? '');
        $full = trim($first . ' ' . $middle . ' ' . $last);

        echo json_encode([
            'ok' => true,
            'decision' => 'existing',
            'client' => [
                'client_id' => $cid,
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'full_name' => $full,
            ],
            'flags' => [
                'has_records' => $has_records,
                'has_pending_new' => $has_pending_new,
                'has_pending_renewal' => $has_pending_renewal,
                'has_for_payment' => $has_for_payment,
                'has_released_new' => $has_released_new,
                'has_unexpired' => $has_unexpired,
            ],
            'block' => $block,
            'message' => $message,
            'suggest' => $suggest,
        ]);
        exit;
    };

    if ($client_id) {
        $emit_existing($client_id, $desired);
    }

    if ($override_client_id === '') {
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

        $inp = [norm($first), norm($middle), norm($last)];
        $best = null;
        $bestScore = 0.0;

        foreach ($rows as $r) {
            $cand = [
                $clean($r['first_name'] ?? ''),
                $clean($r['middle_name'] ?? ''),
                $clean($r['last_name'] ?? ''),
            ];

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
                $sf = smart_sim($A, $cand[0]);
                $sm = ($B === '') ? 0.65 : smart_sim($B, $cand[1]);
                $sl = smart_sim($C, $cand[2]);
                $score = 0.35 * $sf + 0.15 * $sm + 0.50 * $sl;
                if ($score > $localBest) $localBest = $score;
            }

            if ($localBest > $bestScore) {
                $bestScore = $localBest;
                $best = $r;
            }
        }

        if ($best && $bestScore >= $THRESHOLD) {
            $emit_existing((string)$best['client_id'], $desired);
        }
    }

    echo json_encode([
        'ok' => true,
        'decision' => 'none',
        'desired' => $desired,
        'block' => null,
        'message' => null
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
