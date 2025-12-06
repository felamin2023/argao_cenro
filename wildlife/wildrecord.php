<?php

declare(strict_types=1);

session_start();
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Manila');

/* Adjust path if your connection file is elsewhere */
require_once __DIR__ . '/../backend/connection.php'; // provides $pdo

/* ---- AJAX: mark single / mark all read (handled by this same page) ---- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['ajax'] === 'mark_read') {
            $notifId    = $_POST['notif_id']    ?? '';
            $incidentId = $_POST['incident_id'] ?? '';
            if (!$notifId && !$incidentId) {
                echo json_encode(['ok' => false, 'error' => 'missing ids']);
                exit;
            }

            if ($notifId) {
                $st = $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE notif_id=:id");
                $st->execute([':id' => $notifId]);
            }
            if ($incidentId) {
                $st = $pdo->prepare("UPDATE public.incident_report SET is_read=true WHERE incident_id=:id");
                $st->execute([':id' => $incidentId]);
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_all_read') {
            $pdo->beginTransaction();
            $pdo->exec("
                UPDATE public.notifications
                   SET is_read = true
                 WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false
            ");
            $pdo->exec("
                UPDATE public.incident_report
                   SET is_read = true
                 WHERE LOWER(COALESCE(category,''))='wildlife monitoring' AND is_read=false
            ");
            $pdo->commit();
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'unknown action']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[WILD NOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

/* ---- helpers used by the UI snippet ---- */
if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false): string
    {
        if (!$datetime) return '';
        $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $ago  = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        $diff = $now->diff($ago);
        $weeks = (int)floor($diff->d / 7);
        $days  = $diff->d % 7;
        $map   = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
        $parts = [];
        foreach ($map as $k => $label) {
            $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
            if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
        }
        if (!$full) $parts = array_slice($parts, 0, 1);
        return $parts ? implode(', ', $parts) . ' ago' : 'just now';
    }
}

/* ---- data needed by your pasted UI (badge + lists) ---- */
$wildNotifs = [];
$incRows = [];
$unreadWildlife = 0;

try {
    $wildNotifs = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name AS client_first, c.last_name AS client_last
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id   = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", ''))='wildlife'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='wildlife monitoring'
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadPermits = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications
        WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false
    ")->fetchColumn();

    $unreadIncidents = (int)$pdo->query("
        SELECT COUNT(*) FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='wildlife monitoring' AND is_read=false
    ")->fetchColumn();

    $unreadWildlife = $unreadPermits + $unreadIncidents;
} catch (Throwable $e) {
    error_log('[NOTIF BOOTSTRAP] ' . $e->getMessage());
    $wildNotifs = [];
    $incRows = [];
    $unreadWildlife = 0;
}


require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (and typically SUPABASE_URL & SUPABASE_SERVICE_KEY)

// If your env provides SUPABASE_* but they aren't defined as constants, define them.
if (!defined('SUPABASE_URL') && getenv('SUPABASE_URL')) {
    define('SUPABASE_URL', getenv('SUPABASE_URL'));
}
if (!defined('SUPABASE_SERVICE_KEY') && getenv('SUPABASE_SERVICE_KEY')) {
    define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY'));
}

/* ======================= Supabase helpers ======================= */
function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}
function supa_public_url(string $bucket, string $path): string
{
    if (!defined('SUPABASE_URL')) return '';
    return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . encode_path_segments($path);
}
function guess_mime_and_ext(string $tmpPath): array
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpPath) ?: 'application/octet-stream';
    finfo_close($finfo);
    $map = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/gif' => '.gif',
        'image/webp' => '.webp',
        'image/heic' => '.heic'
    ];
    return [$mime, $map[$mime] ?? '.bin'];
}
function supa_upload_bytes(string $bucket, string $path, string $bytes, string $contentType): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY')) {
        return ['ok' => false, 'error' => 'SUPABASE_URL or SUPABASE_SERVICE_KEY not defined'];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_path_segments($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'x-upsert: true',
            'Content-Type: ' . $contentType,
        ],
        CURLOPT_POSTFIELDS     => $bytes,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => $err];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'public_url' => supa_public_url($bucket, $path)];
    }
    return ['ok' => false, 'error' => 'HTTP ' . $status];
}

/* ======================= AJAX: update record ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action']) && $_POST['__action'] === 'update_report') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $breed_report_id = trim((string)($_POST['breed_report_id'] ?? ''));
        $breed_owner_id  = trim((string)($_POST['breed_owner_id'] ?? ''));

        if (!$breed_report_id || !$breed_owner_id) {
            throw new RuntimeException('Missing IDs.');
        }

        // fetch user_id for pathing
        $stmt = $pdo->prepare("SELECT user_id FROM public.breeding_report WHERE breed_report_id = :rid");
        $stmt->execute([':rid' => $breed_report_id]);
        $user_id = (string)$stmt->fetchColumn();
        if (!$user_id) throw new RuntimeException('Unknown report/user.');

        // sanitize text helpers
        $t = fn($k) => trim((string)($_POST[$k] ?? ''));

        // gather fields
        $owner_full_name = $t('full_name');
        $owner_address   = $t('address');
        $wfp_number      = $t('wfp_number');
        $farm_location   = $t('farm_location');

        $start_date      = $t('start_date');
        $end_date        = $t('end_date');
        $species_name    = $t('species_name');
        $accr_stock_no   = $t('accredited_stock_number');
        $prev_balance    = (string)($t('previous_quarter_balance') !== '' ? (int)$t('previous_quarter_balance') : null);
        $dead_count      = (string)($t('dead_count') !== '' ? (int)$t('dead_count') : null);
        $total_stocks    = (string)($t('total_stocks') !== '' ? (int)$t('total_stocks') : null);

        // current image (if no upload provided)
        $current_photo   = $t('species_photo');

        $pdo->beginTransaction();

        // Update breeding_owners
        $updOwner = $pdo->prepare("
            UPDATE public.breeding_owners
               SET full_name = :n,
                   address   = :a,
                   wfp_number = :w,
                   farm_location = :f
             WHERE breed_owner_id = :id
        ");
        $updOwner->execute([
            ':n' => $owner_full_name,
            ':a' => $owner_address,
            ':w' => $wfp_number,
            ':f' => $farm_location,
            ':id' => $breed_owner_id
        ]);

        // Handle image upload to Supabase Storage (keep old if none)
        $newPhoto = $current_photo;
        if (!empty($_FILES['species_image']['tmp_name']) && is_uploaded_file($_FILES['species_image']['tmp_name'])) {
            $tmp = $_FILES['species_image']['tmp_name'];
            [$mime, $ext] = guess_mime_and_ext($tmp);

            $safeId  = preg_replace('/[^a-z0-9-]+/i', '', (string)$breed_report_id);
            $safeUid = preg_replace('/[^a-z0-9-]+/i', '', (string)$user_id);
            $fname   = 'change-' . date('Ymd-His') . $ext;
            $path    = "species/{$safeUid}/{$safeId}/changes/{$fname}";

            $bytes = file_get_contents($tmp);
            $up = supa_upload_bytes('breeding_report', $path, $bytes, $mime);
            if (!$up['ok']) {
                throw new RuntimeException('Upload failed: ' . $up['error']);
            }
            $newPhoto = (string)$up['public_url']; // public URL in bucket
        }

        // Update breeding_report
        $updReport = $pdo->prepare("
            UPDATE public.breeding_report
               SET start_date = :sd,
                   end_date   = :ed,
                   species_photo = :ph,
                   species_name  = :sp,
                   accredited_stock_number = :sn,
                   previous_quarter_balance = :pb,
                   dead_count = :dc,
                   total_stocks = :ts
             WHERE breed_report_id = :rid
        ");
        $updReport->execute([
            ':sd' => $start_date ?: null,
            ':ed' => $end_date ?: null,
            ':ph' => $newPhoto,
            ':sp' => $species_name,
            ':sn' => $accr_stock_no,
            ':pb' => ($prev_balance === '' ? null : (int)$prev_balance),
            ':dc' => ($dead_count === '' ? null : (int)$dead_count),
            ':ts' => ($total_stocks === '' ? null : (int)$total_stocks),
            ':rid' => $breed_report_id
        ]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'record' => [
                'BREED REPORT ID' => $breed_report_id,
                'BREED OWNER ID'  => $breed_owner_id,
                'START' => $start_date,
                'END'   => $end_date,
                'OWNER NAME' => $owner_full_name,
                'Address'    => $owner_address,
                'WFP No'     => $wfp_number,
                'LOCATION OF FARM' => $farm_location,
                'UPLOADED IMAGE'   => $newPhoto,
                'SPECIES NAME'     => $species_name,
                'STOCK NO'         => $accr_stock_no,
                'PREV BALANCE'     => ($prev_balance === '' ? '' : (int)$prev_balance),
                'DEAD COUNT'       => ($dead_count === '' ? '' : (int)$dead_count),
                'TOTAL STOCKS'     => ($total_stocks === '' ? '' : (int)$total_stocks),
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[wildrecord update] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/* ======================= fetch records for table ======================= */
$rows = [];
try {
    $st = $pdo->query("
        SELECT
            br.breed_report_id,
            br.breed_owner_id,
            br.user_id,
            br.start_date,
            br.end_date,
            br.species_photo,
            br.species_name,
            br.accredited_stock_number,
            br.previous_quarter_balance,
            br.dead_count,
            br.total_stocks,
            bo.full_name,
            bo.address,
            bo.wfp_number,
            bo.farm_location
        FROM public.breeding_report br
        LEFT JOIN public.breeding_owners bo ON bo.breed_owner_id = br.breed_owner_id
        ORDER BY br.start_date DESC NULLS LAST, br.breed_report_id DESC
        LIMIT 500
    ");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[wildrecord fetch] ' . $e->getMessage());
    $rows = [];
}

// Prepare data for JS
$jsRecords = [];
foreach ($rows as $r) {
    $id = (string)$r['breed_report_id'];
    $jsRecords[$id] = [
        'BREED REPORT ID' => $id,
        'BREED OWNER ID'  => (string)($r['breed_owner_id'] ?? ''),
        'START' => (string)($r['start_date'] ?? ''),
        'END'   => (string)($r['end_date'] ?? ''),
        'OWNER NAME' => (string)($r['full_name'] ?? ''),
        'Address'    => (string)($r['address'] ?? ''),
        'WFP No'     => (string)($r['wfp_number'] ?? ''),
        'LOCATION OF FARM' => (string)($r['farm_location'] ?? ''),
        'UPLOADED IMAGE'   => (string)($r['species_photo'] ?? ''),
        'SPECIES NAME'     => (string)($r['species_name'] ?? ''),
        'STOCK NO'         => (string)($r['accredited_stock_number'] ?? ''),
        'PREV BALANCE'     => (string)($r['previous_quarter_balance'] ?? ''),
        'DEAD COUNT'       => (string)($r['dead_count'] ?? ''),
        'TOTAL STOCKS'     => (string)($r['total_stocks'] ?? ''),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/wildrecord.css">

    <!-- Tiny, scoped tweaks for toast + confirm -->
    <style>
        /* TOASTS */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 11000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            min-width: 260px;
            max-width: 380px;
            background: #2e7d32;
            color: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .2);
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font: 500 14px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial;
            opacity: 0;
            transform: translateY(-8px);
            animation: toastIn .25s ease forwards;
            pointer-events: auto;
        }

        .toast.error {
            background: #c62828;
        }

        .toast.info {
            background: #1565c0;
        }

        .toast .toast-close {
            margin-left: auto;
            cursor: pointer;
            opacity: .9;
        }

        @keyframes toastIn {
            to {
                opacity: 1;
                transform: none;
            }
        }

        /* CONFIRM MODAL */
        #confirmModal {
            display: none;
        }

        #confirmModal .modal-content {
            width: 420px;
            max-width: 92vw;
            padding: 16px 18px;
            border-radius: 12px;
        }

        #confirmModal .modal-header {
            margin-bottom: 8px;
        }

        #confirmModal .modal-header h2 {
            font-size: 18px;
            margin: 0;
        }

        #confirmModal .modal-body {
            padding: 0;
            margin-top: 6px;
            max-height: none;
            height: auto;
        }

        #confirmModal .modal-body p {
            margin: 0 0 12px;
        }

        #confirmModal .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 8px;
        }

        #confirmModal .modal-btn-cancel {
            background: #d7f5e3;
            color: #064b1f;
        }

        #confirmModal .modal-btn-cancel:hover {
            filter: brightness(.96);
        }

        /* Replace your existing .action-buttons and button styles with these: */

/* Container for action buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
    width: 100%;
    padding: 2px;
}

/* Base button style - COMPACT VERSION */
.view-btn, .edit-btn {
    border: none;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    font-family: 'calibri';
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: 0.3px;
}

.view-btn {
    background: linear-gradient(135deg, #4CAF50, #388E3C);
    color: white;
}

.edit-btn {
    background: linear-gradient(135deg, #2196F3, #1976D2);
    color: white;
}

/* Icon-only version (even smaller) */
.view-btn i, .edit-btn i {
    font-size: 10px;
    margin-right: 2px;
}

/* Button hover effects */
.view-btn:hover, .edit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.view-btn:active, .edit-btn:active {
    transform: translateY(0);
}

         .nav-item .badge {
    position: absolute;
    top: -2px;
    right: 4px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    min-width: 19px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    z-index: 100;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    /* Prevent all animations/transforms on the badge */
    transition: none !important;
    animation: none !important;
    transform: none !important;
    will-change: transform;
}

/* Ensure parent doesn't affect badge position */
.nav-icon {
    position: relative;
    transform-style: flat; /* Prevent 3D transforms from affecting children */
    backface-visibility: hidden; /* Improve rendering stability */
}

/* Specifically target the notification dropdown badge */
#notifDropdown .badge {
    top: -2px !important;
    right: 4px !important;
    position: absolute;
}
    </style>
</head>

<body>

    <header>
        <div class="logo"><a href="wildhome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item active-page"><i class="fas fa-plus-circle"></i><span>Wildlife Management</span></a>
                    <a href="wildpermit.php" class="dropdown-item"><i class="fas fa-paw"></i><span>Wildlife Permit</span></a>
                    <a href="reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Incident Reports</span></a>
                </div>
            </div>
            <!-- <div class="nav-item">
                <div class="nav-icon"><a href="wildmessage.php" aria-label="Messages"><i class="fas fa-envelope" style="color:black;"></i></a></div>
            </div> -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="wildNotifList">
                        <?php
                        $combined = [];

                        // Permits
                        foreach ($wildNotifs as $nf) {
                            $combined[] = [
                                'id'      => $nf['notif_id'],
                                'is_read' => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'type'    => 'permit',
                                'message' => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' requested a wildlife permit.')),
                                'ago'     => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'    => !empty($nf['approval_id']) ? 'wildeach.php?id=' . urlencode((string)$nf['approval_id']) : 'wildnotification.php'
                            ];
                        }

                        // Incidents
                        foreach ($incRows as $ir) {
                            $combined[] = [
                                'id'      => $ir['incident_id'],
                                'is_read' => ($ir['is_read'] === true || $ir['is_read'] === 't' || $ir['is_read'] === 1 || $ir['is_read'] === '1'),
                                'type'    => 'incident',
                                'message' => trim((string)$ir['body_text']),
                                'ago'     => time_elapsed_string($ir['created_at'] ?? date('c')),
                                'link'    => 'reportaccident.php?focus=' . urlencode((string)$ir['incident_id'])
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $title = $item['type'] === 'permit' ? 'Permit request' : 'Incident report';
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= $item['type'] === 'permit' ? h($item['id']) : '' ?>"
                                    data-incident-id="<?= $item['type'] === 'incident' ? h($item['id']) : '' ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="wildnotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <a href="breedingreport.php" class="back-icon"><i class="fas fa-arrow-left"></i></a>

    <div class="wildlife-container">
        <div class="container">
            <div class="header" style="background:#ffffff;border-radius:12px;padding:18px 20px;box-shadow:0 6px 15px rgba(0,0,0,0.2);margin-bottom:30px;color:black;">
              
                <h1 class="title" style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-size:42px;font-weight:900;color:#000;text-align:center;margin:0;">WILDLIFE MONITORING RECORDS</h1>


                 <div class="controls">
                      
               <div class="search">
    <i class="fas fa-search search-icon"></i>
    <input type="text" placeholder="SEARCH HERE" class="search-input" id="search-input">
</div>
               

            </div>

           
               
            </div>

            <div class="table-container">
                <table class="wildlife-table" id="recordsTable">
                    <thead>
                        <tr>
                            <th>WFP NO</th>
                            <th>OWNER NAME</th>
                            <th>SPECIES NAME</th>
                            <th>PREVIOUS BALANCE</th>
                             <th>TOTAL STOCKS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="recordsTbody">
                        <?php if (empty($rows)): ?>
                            <tr id="noRecordsRow">
                                <td colspan="6" style="text-align:center;">No records found.</td>
                            </tr>
                            <?php else: foreach ($rows as $r):
                                $rid = (string)$r['breed_report_id'];
                                $labelId = 'WL-' . strtoupper(substr($rid, 0, 6));
                            ?>
                                <tr data-record-id="<?= htmlspecialchars($rid) ?>">
                                    <td><?= htmlspecialchars($labelId) ?></td>
                                    <td><?= htmlspecialchars((string)($r['full_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($r['species_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($r['accredited_stock_number'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($r['previous_quarter_balance'] ?? '')) ?></td>
                                    <td>
                                        <button class="view-btn" onclick="openModal('<?= htmlspecialchars($rid) ?>', 'view')">👁️</button>
                                        <button class="edit-btn" onclick="openModal('<?= htmlspecialchars($rid) ?>', 'edit')">✎</button>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- no bottom SAVE button -->
        </div>
    </div>

    <!-- Detail modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">WILDLIFE MONITORING DETAILS</h2>
                <span class="close" onclick="requestCloseModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-actions" id="modalActions"></div>
        </div>
    </div>

    <!-- Image full-view modal -->
    <div id="imgModal" style="position:fixed; inset:0; background:rgba(0,0,0,.8); display:none; align-items:center; justify-content:center; z-index:10000;">
        <span id="imgModalClose" style="position:absolute; top:16px; right:24px; font-size:32px; color:#fff; cursor:pointer;">&times;</span>
        <img id="imgModalContent" src="" alt="Full View" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.4);" />
    </div>

    <!-- Confirm (UI, not native) -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="height:fit-content;">
            <div class="modal-header">
                <h2>Discard changes?</h2>
                <span class="close" onclick="hideConfirm()">&times;</span>
            </div>
            <div class="modal-body" style="display:flex; flex-direction:column;">
                <p id="confirmMessage">Are you sure you want to cancel? All unsaved changes will be lost.</p>
                <div class="confirm-actions">
                    <button class="modal-btn modal-btn-cancel" onclick="hideConfirm()">Keep editing</button>
                    <button class="modal-btn modal-btn-save" id="confirmYesBtn">Discard</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const NOTIF_ENDPOINT = '<?php echo basename(__FILE__); ?>'; // calls THIS page for AJAX

            // Minimal dropdown open/close just for the bell
            const dd = document.getElementById('notifDropdown');
            if (dd) {
                const trigger = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');
                const open = () => {
                    dd.classList.add('open');
                    trigger?.setAttribute('aria-expanded', 'true');
                    if (menu) {
                        menu.style.opacity = '1';
                        menu.style.visibility = 'visible';
                    }
                };
                const close = () => {
                    dd.classList.remove('open');
                    trigger?.setAttribute('aria-expanded', 'false');
                    if (menu) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                    }
                };
                trigger?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dd.classList.toggle('open');
                    if (dd.classList.contains('open')) open();
                    else close();
                });
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#notifDropdown')) close();
                });
            }

            // Mark ALL as read
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();

                // optimistic UI
                document.querySelectorAll('#wildNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }

                try {
                    const res = await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                } catch {
                    location.reload();
                }
            });

            // Mark ONE as read + follow link
            document.getElementById('wildNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                e.preventDefault();

                const item = link.closest('.notification-item');
                const notifId = item?.getAttribute('data-notif-id') || '';
                const incidentId = item?.getAttribute('data-incident-id') || '';
                const href = link.getAttribute('href') || '#';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);
                    await fetch(`${NOTIF_ENDPOINT}?ajax=mark_read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch {}

                item?.classList.remove('unread');
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    const n = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, n - 1);
                    badge.textContent = String(next);
                    if (next <= 0) badge.style.display = 'none';
                }
                window.location.href = href;
            });
        });
    </script>


    <script>
        const recordData = <?php echo json_encode($jsRecords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let currentRecordId = null;
        let currentMode = 'view';

        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) mobileToggle.addEventListener('click', () => navContainer.classList.toggle('active'));

            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                dropdown.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                });
                dropdown.addEventListener('mouseleave', (e) => {
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
                menu.addEventListener('mouseleave', (e) => {
                    if (!dropdown.contains(e.relatedTarget)) {
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

            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                    const badge = document.querySelector('.badge');
                    if (badge) badge.style.display = 'none';
                });
            }

            // Image full-view
            document.getElementById('imgModalClose').onclick = closeImageViewer;
            document.getElementById('imgModal').addEventListener('click', (e) => {
                if (e.target.id === 'imgModal') closeImageViewer();
            });

            // ESC handling
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const cm = document.getElementById('confirmModal');
                    if (cm.style.display === 'block') {
                        hideConfirm();
                        return;
                    }
                    const im = document.getElementById('imgModal');
                    if (im.style.display === 'flex') {
                        closeImageViewer();
                        return;
                    }
                }
            });

            /* ===== Filters: live on type/click/change ===== */
            // The markup uses id="search-input" (dash), ensure JS queries the correct id.
            const searchInput = document.getElementById('search-input');
            const startSel = document.getElementById('filterStartMonth');
            const endSel = document.getElementById('filterEndMonth');
            const yearInput = document.getElementById('filterYear');
            const filterBtn = document.getElementById('applyFilter');
            const tbody = document.getElementById('recordsTbody');

            // Parse YYYY-MM-DD safely into local Date
            const parseYMD = (s) => {
                if (!s) return null;
                const t = String(s).slice(0, 10);
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(t);
                if (!m) return null;
                const y = +m[1],
                    mo = +m[2] - 1,
                    d = +m[3];
                return new Date(y, mo, d);
            };

            const filterRecords = () => {
                const q = ((searchInput && searchInput.value) ? searchInput.value : '').trim().toLowerCase();
                const sm = (startSel && startSel.value) ? parseInt(startSel.value, 10) : null;
                const em = (endSel && endSel.value) ? parseInt(endSel.value, 10) : null;
                const yr = (yearInput && yearInput.value) ? parseInt(yearInput.value, 10) : null;

                const haveYear = !!yr;
                const haveStartM = !!sm;
                const haveEndM = !!em;
                const anyDateFilter = haveYear || haveStartM || haveEndM;

                let visibleCount = 0;

                tbody.querySelectorAll('tr[data-record-id]').forEach(row => {
                    const id = row.getAttribute('data-record-id');
                    const rec = recordData[id] || {};

                    const st = parseYMD(rec['START']);
                    const en = parseYMD(rec['END']);
                    const stMonth = st ? (st.getMonth() + 1) : null;
                    const stYear = st ? st.getFullYear() : null;
                    const enMonth = en ? (en.getMonth() + 1) : null;
                    const enYear = en ? en.getFullYear() : null;

                    const wlId = 'WL-' + id.slice(0, 6).toUpperCase();
                    const hay = [
                        wlId, rec['OWNER NAME'], rec['SPECIES NAME'],
                        rec['STOCK NO'], rec['PREV BALANCE'], rec['WFP No'],
                        rec['LOCATION OF FARM']
                    ].map(v => (v || '') + '').join(' ').toLowerCase();

                    let okText = true;
                    if (q) okText = hay.includes(q);

                    let okDate = true;
                    if (anyDateFilter) {
                        okDate = true;

                        // If only YEAR
                        if (haveYear && !haveStartM && !haveEndM) {
                            okDate = (stYear === yr) || (enYear === yr);
                        }

                        // If START MONTH selected
                        if (haveStartM && !haveYear) {
                            okDate = okDate && (stMonth === sm);
                        } else if (haveStartM && haveYear) {
                            okDate = okDate && (stMonth === sm && stYear === yr);
                        }

                        // If END MONTH selected
                        if (haveEndM && !haveYear) {
                            okDate = okDate && (enMonth === em);
                        } else if (haveEndM && haveYear) {
                            okDate = okDate && (enMonth === em && enYear === yr);
                        }

                        // If a month is required but the date is missing, fail the check
                        if ((haveStartM && !st) || (haveEndM && !en)) {
                            okDate = false;
                        }
                    }

                    const show = okText && okDate;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                // Show/hide "No records" row dynamically
                let noRow = document.getElementById('noRecordsRow');
                if (visibleCount === 0) {
                    if (!noRow) {
                        noRow = document.createElement('tr');
                        noRow.id = 'noRecordsRow';
                        noRow.innerHTML = `<td colspan="6" style="text-align:center;">No records found.</td>`;
                        tbody.appendChild(noRow);
                    }
                    noRow.style.display = '';
                } else if (noRow) {
                    noRow.style.display = 'none';
                }
            };

            // live on every type/click/change (guard elements which may not exist on this page)
            if (searchInput) searchInput.addEventListener('input', filterRecords);
            if (startSel) startSel.addEventListener('change', filterRecords);
            if (endSel) endSel.addEventListener('change', filterRecords);
            if (yearInput) {
                yearInput.addEventListener('input', filterRecords);
                yearInput.addEventListener('change', filterRecords);
            }
            if (filterBtn) filterBtn.addEventListener('click', (e) => {
                e.preventDefault();
                filterRecords();
            });

            // Export CSV (current filtered view)
            document.getElementById('exportCsv').addEventListener('click', function(e) {
                e.preventDefault();
                const table = document.getElementById('recordsTable');
                const headerCells = Array.from(table.querySelectorAll('thead th'));
                const head = headerCells.slice(0, headerCells.length - 1).map(th => th.textContent.trim());

                const rows = [];
                document.querySelectorAll('#recordsTbody tr[data-record-id]').forEach(tr => {
                    if (tr.style.display === 'none') return;
                    const cells = Array.from(tr.cells).slice(0, tr.cells.length - 1).map(td => td.textContent.trim());
                    rows.push(cells);
                });

                if (!rows.length) {
                    showToast('No rows to export.', 'info');
                    return;
                }

                const esc = (v) => {
                    const s = (v ?? '').toString();
                    if (/[",\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                    return s;
                };
                const csv = [head.map(esc).join(','), ...rows.map(r => r.map(esc).join(','))].join('\n');
                const blob = new Blob(["\ufeff" + csv], {
                    type: 'text/csv;charset=utf-8'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                const ts = new Date();
                const pad = n => (n < 10 ? '0' : '') + n;
                const fname = `wildlife_records_${ts.getFullYear()}${pad(ts.getMonth()+1)}${pad(ts.getDate())}_${pad(ts.getHours())}${pad(ts.getMinutes())}${pad(ts.getSeconds())}.csv`;
                a.href = url;
                a.download = fname;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                showToast('CSV downloaded.', 'success');
            });

            // initial filter state (none)
            filterRecords();
        });

        // TOASTS
        function showToast(message, type = 'success', timeout = 3500) {
            const container = document.getElementById('toastContainer');
            const el = document.createElement('div');
            el.className = `toast ${type === 'error' ? 'error' : (type === 'info' ? 'info' : '')}`;
            el.innerHTML = `
                <i class="fa-solid ${type==='error' ? 'fa-circle-xmark' : (type==='info' ? 'fa-circle-info' : 'fa-circle-check')}"></i>
                <div>${message}</div>
                <i class="fa-solid fa-xmark toast-close" title="Close"></i>
            `;
            const remove = () => {
                el.style.transition = 'opacity .2s ease, transform .2s ease';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(() => el.remove(), 200);
            };
            el.querySelector('.toast-close').addEventListener('click', remove);
            container.appendChild(el);
            setTimeout(remove, timeout);
        }

        // IMAGE VIEWER
        function openImageViewer(src) {
            const overlay = document.getElementById('imgModal');
            const imgEl = document.getElementById('imgModalContent');
            imgEl.src = src || '';
            overlay.style.display = 'flex';
        }

        function closeImageViewer() {
            const overlay = document.getElementById('imgModal');
            const imgEl = document.getElementById('imgModalContent');
            imgEl.src = '';
            overlay.style.display = 'none';
        }

        // CONFIRM (UI)
        let _confirmYesHandler = null;

        function showConfirm(message, onYes) {
            document.getElementById('confirmMessage').textContent = message || 'Are you sure?';
            _confirmYesHandler = onYes || null;
            const cm = document.getElementById('confirmModal');
            cm.style.display = 'block';
            document.getElementById('confirmYesBtn').onclick = () => {
                hideConfirm();
                if (typeof _confirmYesHandler === 'function') {
                    _confirmYesHandler();
                } else if (currentMode === 'edit') {
                    // Ensure Discard always closes the edit modal
                    closeModal();
                }
            };
            cm.onclick = (e) => {
                if (e.target === cm) hideConfirm();
            };
        }

        function hideConfirm() {
            const cm = document.getElementById('confirmModal');
            cm.style.display = 'none';
            _confirmYesHandler = null;
            cm.onclick = null;
        }

        // DETAIL MODAL
        function requestCloseModal() {
            if (currentMode === 'edit') {
                showConfirm('Discard changes and close?', () => closeModal());
            } else {
                closeModal();
            }
        }

        function openModal(id, mode) {
            currentRecordId = id;
            currentMode = mode;
            const modal = document.getElementById('detailModal');
            const modalContent = modal.querySelector('.modal-content');
            const modalTitle = document.getElementById('modalTitle');
            const modalActions = document.getElementById('modalActions');

            const record = recordData[id] || {};
            modalTitle.textContent = mode === 'edit' ? 'EDIT WILDLIFE MONITORING DETAILS' : 'WILDLIFE MONITORING DETAILS';
            if (mode === 'edit') modalContent.classList.add('edit-mode');
            else modalContent.classList.remove('edit-mode');

            let detailsHtml = '';
            let imageBlock = '';

            const keysOrder = [
                'OWNER NAME', 'Address', 'WFP No', 'LOCATION OF FARM',
                'START', 'END',
                'SPECIES NAME', 'STOCK NO', 'PREV BALANCE', 'DEAD COUNT', 'TOTAL STOCKS',
                'UPLOADED IMAGE'
            ];
            const keys = Object.keys(record);
            const ordered = keysOrder.filter(k => k in record).concat(keys.filter(k => !keysOrder.includes(k)));

            for (const key of ordered) {
                const value = record[key];
                if (key === 'BREED REPORT ID' || key === 'BREED OWNER ID') continue;

                if (key === 'UPLOADED IMAGE') {
                    const imgSrc = value ? value : '';
                    const thumbStyle = 'display:block;width:180px;height:180px;object-fit:cover;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)';
                    if (mode === 'edit') {
                        imageBlock = `
                            <div class="image-section" style="display:block;width:100%;margin-top:16px;">
                                <div class="detail-label" style="margin-bottom:6px;">${key}:</div>
                                <div class="image-upload-container" onclick="document.getElementById('species_image').click()">
                                    <input type="file" name="species_image" id="species_image" accept="image/*" class="image-upload-input" style="display:none;">
                                    <div class="image-upload-label" style="margin-bottom:10px;">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Click to upload species image</span>
                                        <small style="display:block;margin-top:5px;color:var(--text-light);">(JPEG, PNG, max 5MB)</small>
                                    </div>
                                    <div class="image-preview" id="image-preview">
                                        ${imgSrc ? `<img src="${imgSrc}" alt="Species Image" data-src="${imgSrc}" class="image-thumb-clickable" style="${thumbStyle}">` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        imageBlock = `
                            <div class="image-section" style="display:block;width:100%;margin-top:16px;">
                                <div class="detail-label" style="margin-bottom:6px;">${key}:</div>
                                ${imgSrc ? `<img src="${imgSrc}" alt="Species Image" data-src="${imgSrc}" class="image-thumb-clickable" style="${thumbStyle}">` : '<em>No image</em>'}
                            </div>
                        `;
                    }
                    continue;
                }

                let inputType = 'text';
                if (key === 'START' || key === 'END') inputType = 'date';
                if (key.includes('NO') || key.includes('BALANCE') || key.includes('STOCKS') || key.includes('COUNT')) inputType = 'number';
                if (key.includes('REMARKS')) inputType = 'textarea';

                detailsHtml += `
                    <div class="detail-row">
                        <div class="detail-label">${key}:</div>
                        <div class="detail-value">${value ?? ''}</div>
                        <div class="detail-value-edit">
                            ${inputType === 'textarea' ? `
                                <textarea id="${key}" rows="4">${value ?? ''}</textarea>
                            ` : `
                                <input type="${inputType}" id="${key}" value="${value ?? ''}">
                            `}
                        </div>
                    </div>
                `;
            }

            document.getElementById('modalBody').innerHTML = detailsHtml + imageBlock;

            const imageInput = document.getElementById('species_image');
            if (imageInput) {
                imageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const imagePreview = document.getElementById('image-preview');
                            imagePreview.innerHTML = `<img src="${e.target.result}" alt="Species Image" data-src="${e.target.result}" class="image-thumb-clickable" style="display:block;width:180px;height:180px;object-fit:cover;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1)">`;
                            attachThumbOpen();
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            function attachThumbOpen() {
                document.querySelectorAll('.image-thumb-clickable').forEach(el => {
                    el.addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        openImageViewer(el.getAttribute('data-src'));
                    });
                });
            }
            attachThumbOpen();

            let actionButtons = '';
            if (mode === 'view') {
                actionButtons = ``;
                document.getElementById('modalBody').classList.add('view-mode');
                document.getElementById('modalBody').classList.remove('edit-mode');
            } else {
                actionButtons = `
                    <button class="modal-btn modal-btn-save" onclick="saveChanges()">Save Changes</button>
                    <button class="modal-btn modal-btn-cancel" onclick="cancelEdit()">Cancel</button>
                `;
                document.getElementById('modalBody').classList.add('edit-mode');
                document.getElementById('modalBody').classList.remove('view-mode');
            }

            document.getElementById('modalActions').innerHTML = actionButtons;
            modal.style.display = 'block';
        }

        async function saveChanges() {
            try {
                const rec = recordData[currentRecordId] || {};
                const fd = new FormData();
                fd.append('__action', 'update_report');
                fd.append('breed_report_id', rec['BREED REPORT ID'] || currentRecordId);
                fd.append('breed_owner_id', rec['BREED OWNER ID'] || '');

                fd.append('full_name', (document.getElementById('OWNER NAME')?.value ?? rec['OWNER NAME'] ?? ''));
                fd.append('address', (document.getElementById('Address')?.value ?? rec['Address'] ?? ''));
                fd.append('wfp_number', (document.getElementById('WFP No')?.value ?? rec['WFP No'] ?? ''));
                fd.append('farm_location', (document.getElementById('LOCATION OF FARM')?.value ?? rec['LOCATION OF FARM'] ?? ''));

                fd.append('start_date', (document.getElementById('START')?.value ?? rec['START'] ?? ''));
                fd.append('end_date', (document.getElementById('END')?.value ?? rec['END'] ?? ''));
                fd.append('species_name', (document.getElementById('SPECIES NAME')?.value ?? rec['SPECIES NAME'] ?? ''));
                fd.append('accredited_stock_number', (document.getElementById('STOCK NO')?.value ?? rec['STOCK NO'] ?? ''));
                fd.append('previous_quarter_balance', (document.getElementById('PREV BALANCE')?.value ?? rec['PREV BALANCE'] ?? ''));
                fd.append('dead_count', (document.getElementById('DEAD COUNT')?.value ?? rec['DEAD COUNT'] ?? ''));
                fd.append('total_stocks', (document.getElementById('TOTAL STOCKS')?.value ?? rec['TOTAL STOCKS'] ?? ''));
                fd.append('species_photo', rec['UPLOADED IMAGE'] ?? '');

                const imageInput = document.getElementById('species_image');
                if (imageInput && imageInput.files && imageInput.files.length) {
                    fd.append('species_image', imageInput.files[0]);
                }

                const resp = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd
                });
                const data = await resp.json();

                if (!data.ok) {
                    showToast('Failed to save changes: ' + (data.error || 'Unknown error'), 'error');
                    return;
                }

                const updated = data.record || {};
                recordData[currentRecordId] = {
                    ...rec,
                    ...updated
                };

                const tableRow = document.querySelector(`tr[data-record-id="${currentRecordId}"]`);
                if (tableRow) {
                    const wlId = 'WL-' + (currentRecordId.slice(0, 6).toUpperCase());
                    tableRow.cells[0].textContent = wlId;
                    tableRow.cells[1].textContent = updated['OWNER NAME'] ?? rec['OWNER NAME'] ?? '';
                    tableRow.cells[2].textContent = updated['SPECIES NAME'] ?? rec['SPECIES NAME'] ?? '';
                    tableRow.cells[3].textContent = updated['STOCK NO'] ?? rec['STOCK NO'] ?? '';
                    tableRow.cells[4].textContent = updated['PREV BALANCE'] ?? rec['PREV BALANCE'] ?? '';
                }

                showToast('Changes saved successfully!', 'success');
                closeModal();
            } catch (err) {
                console.error(err);
                showToast('An unexpected error occurred.', 'error');
            }
        }

        function cancelEdit() {
            showConfirm('Are you sure you want to cancel? All unsaved changes will be lost.', () => {
                showToast('Edit canceled', 'info');
                closeModal();
            });
        }

        function closeModal() {
            const modal = document.getElementById('detailModal');
            modal.style.display = 'none';
        }

        // Close detail modal by clicking overlay
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                if (currentMode === 'edit') {
                    showConfirm('Discard changes and close?', () => closeModal());
                } else {
                    closeModal();
                }
            }
        });
    </script>
</body>

</html>