<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../connection.php'; // $pdo

function norm(?string $s): string
{
  return strtolower(trim((string)$s));
}

// (kept for completeness)
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
  if (!isset($_SESSION['user_id'])) throw new Exception('Not authenticated');
  if (!$pdo) throw new Exception('DB not available');

  // -------- inputs --------
  $first   = trim($_POST['first_name']  ?? '');
  $middle  = trim($_POST['middle_name'] ?? '');
  $last    = trim($_POST['last_name']   ?? '');
  $desired = isset($_POST['desired_permit_type']) && in_array(strtolower($_POST['desired_permit_type']), ['new', 'renewal'], true)
    ? strtolower($_POST['desired_permit_type']) : 'new';

  // If user clicked “Use existing” in the modal
  $override_client_id = trim((string)($_POST['use_client_id'] ?? $_POST['use_existing_client_id'] ?? ''));


  // -------- resolve client --------
  $client_id = null;

  // (don’t assume UUID – accept any text and verify it exists)
  if ($override_client_id !== '') {
    $chk = $pdo->prepare("SELECT client_id FROM public.client WHERE client_id::text = :cid LIMIT 1");
    $chk->execute([':cid' => $override_client_id]);
    $client_id = $chk->fetchColumn() ?: null;
    if (!$client_id) {
      echo json_encode(['ok' => false, 'message' => 'Invalid client id.']);
      exit;
    }
  }

  if (!$client_id) {
    // exact name (normalized)
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

  // --- Fuzzy unordered suggestion (only if STILL no client and no override) ---
  if (!$client_id && $override_client_id === '') {
    $THRESHOLD = 0.68;       // good for typos like: jovani↔jovanie, flamin↔felamin, cebllos↔ceballos
    $CANDIDATE_LIMIT = 1000;

    $clean = function (?string $s): string {
      return preg_replace('/[^a-z]/', '', strtolower(trim((string)$s)));
    };
    $nf_clean = $clean($first);
    $nm_clean = $clean($middle);
    $nl_clean = $clean($last);

    // any 2-letter prefix match on any name part (broad candidate set)
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

    if (!$rows) {
      $q2 = $pdo->query("
                SELECT client_id, first_name, middle_name, last_name
                FROM public.client
                ORDER BY client_id DESC
                LIMIT {$CANDIDATE_LIMIT}
            ");
      $rows = $q2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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
    $inp  = [$nf_clean, $nm_clean, $nl_clean];

    foreach ($rows as $r) {
      $cand = [
        $normName($r['first_name']  ?? ''),
        $normName($r['middle_name'] ?? ''),
        $normName($r['last_name']   ?? '')
      ];

      // try permutations to ignore field-order mistakes
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
        $sm = ($B === '') ? 0.65 : $smartSim($B, $cand[1]); // middle optional baseline
        $sl = $smartSim($C, $cand[2]);
        $score = 0.35 * $sf + 0.15 * $sm + 0.50 * $sl; // last name weighted more
        if ($score > $localBest) $localBest = $score;
      }

      if ($localBest > $bestScore) {
        $bestScore = $localBest;
        $best = $r;
      }
    }

    if ($best && $bestScore >= $THRESHOLD) {
      echo json_encode([
        'ok'                       => true,
        'existing_client_id'       => (string)$best['client_id'],
        'existing_client_first'    => (string)($best['first_name']  ?? ''),
        'existing_client_middle'   => (string)($best['middle_name'] ?? ''),
        'existing_client_last'     => (string)($best['last_name']   ?? ''),
        'existing_client_name'     => trim(($best['first_name'] ?? '') . ' ' . ($best['middle_name'] ?? '') . ' ' . ($best['last_name'] ?? '')),
        'suggestion_score'         => round($bestScore, 2),
      ]);
      exit;
    }



    echo json_encode(['ok' => true]); // no suggestion → proceed as brand new
    exit;
  }

  // If we got a client_id (via exact match or override), check lumber approval flags
  if ($client_id) {
    $stmt = $pdo->prepare("
        SELECT
            bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'new')      AS has_pending_new,
            bool_or(approval_status ILIKE 'released'  AND permit_type ILIKE 'new')      AS has_released_new,
            bool_or(approval_status ILIKE 'pending'   AND permit_type ILIKE 'renewal')  AS has_pending_renewal,
            bool_or(approval_status ILIKE 'for payment')                                 AS has_for_payment
        FROM public.approval
        WHERE client_id = :cid AND request_type ILIKE 'lumber'
    ");
    $stmt->execute([':cid' => $client_id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $hasPendingNew     = !empty($f['has_pending_new']);
    $hasReleasedNew    = !empty($f['has_released_new']);
    $hasPendingRenewal = !empty($f['has_pending_renewal']);
    $hasForPayment     = !empty($f['has_for_payment']);

    // include client id so the UI can offer "Use existing"
    $extra = [];
    if ($client_id) {
      $nq = $pdo->prepare("SELECT first_name, middle_name, last_name FROM public.client WHERE client_id = :cid LIMIT 1");
      $nq->execute([':cid' => $client_id]);
      $nr = $nq->fetch(PDO::FETCH_ASSOC) ?: [];
      $extra = [
        'existing_client_id'     => (string)$client_id,
        'existing_client_first'  => (string)($nr['first_name']  ?? ''),
        'existing_client_middle' => (string)($nr['middle_name'] ?? ''),
        'existing_client_last'   => (string)($nr['last_name']   ?? ''),
        'existing_client_name'   => trim(($nr['first_name'] ?? '') . ' ' . ($nr['middle_name'] ?? '') . ' ' . ($nr['last_name'] ?? '')),
      ];
    }

    // global blocker for any "for payment" lumber approval
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
      // if a RELEASED NEW exists, do not allow another NEW; offer renewal
      if ($hasReleasedNew) {
        echo json_encode(array_merge(['ok' => true, 'offer' => 'renewal'], $extra));
        exit;
      }
    } else { // renewal
      if ($hasPendingRenewal) {
        echo json_encode(array_merge(['ok' => true, 'block' => 'pending_renewal'], $extra));
        exit;
      }
      // renewal requires a RELEASED NEW
      if (!$hasReleasedNew) {
        echo json_encode(array_merge(['ok' => true, 'block' => 'need_approved_new'], $extra));

        exit;
      }

      // NEW: block if any released (new/renewal) lumber permit is still unexpired in approved_docs
      $uq = $pdo->prepare("
    SELECT 1
    FROM public.approval a
    JOIN public.approved_docs d ON d.approval_id = a.approval_id
    WHERE a.client_id = :cid
      AND a.request_type ILIKE 'lumber'
      AND a.approval_status ILIKE 'released'
      AND a.permit_type ILIKE ANY (ARRAY['new','renewal'])
      AND d.expiry_date IS NOT NULL
      AND d.expiry_date::date >= CURRENT_DATE
    LIMIT 1
  ");
      $uq->execute([':cid' => $client_id]);
      $hasUnexpired = (bool)$uq->fetchColumn();

      if ($hasUnexpired) {
        echo json_encode(array_merge(['ok' => true, 'block' => 'unexpired_permit'], $extra));
        exit;
      }
    }
  }


  echo json_encode(array_merge(['ok' => true], $extra ?? []));
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
