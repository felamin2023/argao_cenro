<?php

declare(strict_types=1);
session_start();

/* ======================= ACCESS GUARD ======================= */
// Must be logged in and an Admin (Wildlife)
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo; (ideally also SUPABASE_URL & SUPABASE_SERVICE_KEY)

/* ======================= UTILS ======================= */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function time_elapsed_string($datetime, $full = false): string
{
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
function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function encode_path_segments(string $path): string
{
    $path = ltrim($path, '/');
    $segments = explode('/', $path);
    $segments = array_map('rawurlencode', $segments);
    return implode('/', $segments);
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
    $ext = '.bin';
    if (str_starts_with($mime, 'image/')) {
        $map = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif', 'image/webp' => '.webp'];
        $ext = $map[$mime] ?? '.img';
    }
    return [$mime, $ext];
}
function slug(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'item';
}

/* ======================= SUPABASE STORAGE UPLOAD ======================= */
function supa_upload_bytes(string $bucket, string $path, string $bytes, string $contentType = 'application/octet-stream'): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY')) {
        return ['ok' => false, 'error' => 'SUPABASE_URL or SUPABASE_SERVICE_KEY not defined'];
    }
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . encode_path_segments($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'x-upsert: true',
            'Content-Type: ' . $contentType
        ],
        CURLOPT_POSTFIELDS => $bytes,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
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
        return ['ok' => true, 'public_url' => supa_public_url($bucket, $path), 'status' => $status];
    }
    return ['ok' => false, 'error' => 'HTTP ' . $status];
}

/* ======================= SAVE HANDLER (POST) ======================= */
$successMessage = '';
$errorMessage   = '';

$user_id = (string)$_SESSION['user_id'];

// re-check role/department
try {
    $st = $pdo->prepare("SELECT role, department, status FROM public.users WHERE user_id = :id LIMIT 1");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    $isAdmin    = $u && strtolower((string)$u['role']) === 'admin';
    $isWildlife = $u && strtolower((string)$u['department']) === 'wildlife';
    if (!$isAdmin || !$isWildlife) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[WILDLIFE-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'save_report') {
    try {
        $start_date    = (string)($_POST['start_date'] ?? '');
        $end_date      = (string)($_POST['end_date'] ?? '');
        $owner_name    = trim((string)($_POST['name'] ?? ''));            // -> full_name
        $owner_address = trim((string)($_POST['address'] ?? ''));         // -> address
        $wfp_no        = trim((string)($_POST['wfp_no'] ?? ''));          // -> wfp_number
        $farm_location = trim((string)($_POST['farm_location'] ?? ''));   // -> farm_location

        // species arrays (form uses [] names)
        $species_names      = $_POST['species_name']      ?? [];
        $stock_numbers      = $_POST['stock_number']      ?? [];          // -> accredited_stock_number (TEXT)
        $previous_balances  = $_POST['previous_balance']  ?? [];          // -> previous_quarter_balance (INT)
        $statuses           = $_POST['status']            ?? [];          // used to compute dead_count/total_stocks

        if (!$start_date || !$end_date || !$owner_name || !$owner_address || !$wfp_no || !$farm_location) {
            throw new RuntimeException('Missing required owner/report fields.');
        }
        if (empty($species_names)) {
            throw new RuntimeException('Please add at least one species.');
        }

        $pdo->beginTransaction();

        /* ------- Upsert owner in public.breeding_owners ------- */
        $breed_owner_id = null;
        $chk = $pdo->prepare("
            SELECT breed_owner_id
              FROM public.breeding_owners
             WHERE lower(wfp_number) = lower(:w)
             LIMIT 1
        ");
        $chk->execute([':w' => $wfp_no]);
        $breed_owner_id = (string)($chk->fetchColumn() ?: '');

        if (!$breed_owner_id) {
            $breed_owner_id = uuidv4();
            $ins = $pdo->prepare("
                INSERT INTO public.breeding_owners
                    (breed_owner_id, full_name, address, wfp_number, farm_location)
                VALUES
                    (:id, :n, :a, :w, :f)
            ");
            $ins->execute([
                ':id' => $breed_owner_id,
                ':n'  => $owner_name,
                ':a'  => $owner_address,
                ':w'  => $wfp_no,
                ':f'  => $farm_location,
            ]);
        } else {
            $upd = $pdo->prepare("
                UPDATE public.breeding_owners
                   SET full_name = :n,
                       address = :a,
                       farm_location = :f
                 WHERE breed_owner_id = :id
            ");
            $upd->execute([
                ':n'  => $owner_name,
                ':a'  => $owner_address,
                ':f'  => $farm_location,
                ':id' => $breed_owner_id,
            ]);
        }

        /* ------- Insert one row per species in public.breeding_report ------- */
        $files = $_FILES['species_image'] ?? null;

        for ($i = 0; $i < count($species_names); $i++) {
            $sp    = trim((string)$species_names[$i]);
            $accT  = (string)($stock_numbers[$i] ?? '0');    // accredited_stock_number (text)
            $prev  = (int)   ($previous_balances[$i] ?? 0);  // previous_quarter_balance (int)
            $sts   = (string)($statuses[$i] ?? 'alive');     // 'alive'|'dead'
            $dead  = ($sts === 'dead') ? (int)$accT : 0;     // simple interpretation
            $total = max(0, $prev + (int)$accT - $dead);

            $this_report_id = uuidv4(); // PK for this row (and folder name)
            $img_public_url = null;

            // upload image if provided for this index
            if ($files && isset($files['tmp_name'][$i]) && is_uploaded_file($files['tmp_name'][$i])) {
                [$mime, $ext] = guess_mime_and_ext($files['tmp_name'][$i]);
                $bytes = file_get_contents($files['tmp_name'][$i]);
                $path  = 'species/' . $user_id . '/' . $this_report_id . '/' . ($i + 1) . '-' . slug($sp) . $ext;
                $up = supa_upload_bytes('breeding_report', $path, $bytes, $mime);
                if (!$up['ok']) {
                    throw new RuntimeException('Upload failed for species image #' . ($i + 1) . ': ' . $up['error']);
                }
                $img_public_url = (string)$up['public_url'];
            }

            $insR = $pdo->prepare("
                INSERT INTO public.breeding_report
                    (breed_report_id,
                     breed_owner_id,
                     user_id,
                     start_date,
                     end_date,
                     species_photo,
                     species_name,
                     accredited_stock_number,
                     previous_quarter_balance,
                     dead_count,
                     total_stocks)
                VALUES
                    (:rid, :oid, :uid, :sd, :ed, :photo, :sp, :acc, :prev, :dead, :total)
            ");
            $insR->execute([
                ':rid'   => $this_report_id,
                ':oid'   => $breed_owner_id,
                ':uid'   => $user_id,
                ':sd'    => $start_date,
                ':ed'    => $end_date,
                ':photo' => $img_public_url,
                ':sp'    => $sp,
                ':acc'   => $accT,
                ':prev'  => $prev,
                ':dead'  => $dead,
                ':total' => $total,
            ]);
        }

        $pdo->commit();
        $successMessage = 'Breeding report saved successfully.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[BREEDING SAVE] ' . $e->getMessage());
        $errorMessage = 'Sorry, something went wrong while saving the report.';
    }
}

/* ======================= NOTIFICATIONS + INCIDENTS (unchanged UI) ======================= */
$wildNotifs = [];
$unreadWildlife = 0;

try {
    // A) notifications addressed to "wildlife"
    $notifRows = $pdo->query("
        SELECT
            n.notif_id,
            n.message,
            n.is_read,
            n.created_at,
            n.\"from\" AS notif_from,
            n.\"to\"   AS notif_to,
            a.approval_id,
            COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
            COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
            LOWER(COALESCE(a.request_type,''))                        AS request_type,
            c.first_name  AS client_first,
            c.last_name   AS client_last,
            NULL::text    AS incident_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadWildlife = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
          AND n.is_read = false
    ")->fetchColumn();

    // B) incidents
    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''),
                        COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE lower(COALESCE(category,'')) = 'wildlife monitoring'
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $incidentRows = array_map(function ($r) {
        return [
            'notif_id'        => null,
            'message'         => 'WildLife Monitoring incident: ' . (string)$r['body_text'],
            'is_read'         => $r['is_read'],
            'created_at'      => $r['created_at'],
            'notif_from'      => null,
            'notif_to'        => 'wildlife',
            'approval_id'     => null,
            'permit_type'     => null,
            'approval_status' => $r['status'],
            'request_type'    => 'wildlife',
            'client_first'    => null,
            'client_last'     => null,
            'incident_id'     => $r['incident_id'],
        ];
    }, $incRows);

    $unreadInc = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.incident_report
        WHERE lower(COALESCE(category,'')) = 'wildlife monitoring'
          AND is_read = false
    ")->fetchColumn();
    $unreadWildlife += $unreadInc;

    $wildNotifs = array_merge($notifRows, $incidentRows);
    usort($wildNotifs, function ($a, $b) {
        return strtotime((string)($b['created_at'] ?? 'now')) <=> strtotime((string)($a['created_at'] ?? 'now'));
    });
} catch (Throwable $e) {
    error_log('[WILDHOME NOTIFS] ' . $e->getMessage());
    $wildNotifs = [];
    $unreadWildlife = 0;
}

$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarterly Breeding Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==== (your styles kept; only tiny tweaks) ==== */
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --primary-light: #e9fff2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --border-color: #e0e0e0;
            --text-dark: #333333;
            --text-light: #666666;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --transition: all .2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
            padding-top: 100px
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary-color);
            color: var(--white);
            padding: 0 30px;
            height: 58px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1)
        }

        .logo {
            height: 45px;
            display: flex;
            margin-top: -1px;
            align-items: center;
            position: relative
        }

        .logo a {
            display: flex;
            align-items: center;
            height: 90%
        }

        .logo img {
            height: 98%;
            width: auto;
            transition: var(--transition)
        }

        .logo:hover img {
            transform: scale(1.05)
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 20px
        }

        .nav-item {
            position: relative
        }

        .nav-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgb(233, 255, 242);
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            color: black;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15)
        }

        .nav-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25)
        }

        .nav-icon i {
            font-size: 1.3rem;
            color: inherit;
            transition: color .3s ease
        }

        .nav-icon.active {
            position: relative
        }

        .nav-icon.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 2px;
            background: var(--white);
            border-radius: 2px
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            min-width: 300px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            padding: 0
        }

        .notifications-dropdown {
            min-width: 350px;
            max-height: 500px;
            overflow-y: auto
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .notification-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.2rem
        }

        .mark-all-read {
            color: var(--primary-color);
            cursor: pointer;
            font-size: .9rem;
            text-decoration: none;
            transition: var(--transition), transform .2s ease
        }

        .mark-all-read:hover {
            color: var(--primary-dark);
            transform: scale(1.1)
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            display: flex;
            align-items: flex-start
        }

        .notification-item.unread {
            background-color: rgba(43, 102, 37, .05)
        }

        .notification-item:hover {
            background: #f9f9f9
        }

        .notification-icon {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.2rem
        }

        .notification-content {
            flex: 1
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color)
        }

        .notification-message {
            color: var(--primary-color);
            font-size: .9rem;
            line-height: 1.4
        }

        .notification-time {
            color: #999;
            font-size: .8rem;
            margin-top: 5px
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: .9rem;
            transition: var(--transition);
            display: inline-block;
            padding: 5px 0
        }

        .view-all:hover {
            text-decoration: underline
        }

        .dropdown-menu.center {
            left: 50%;
            transform: translateX(-50%) translateY(10px)
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0)
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0)
        }

        .dropdown-menu:before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 20px;
            border-width: 10px;
            border-style: solid;
            border-color: transparent transparent var(--white) transparent
        }

        .dropdown-menu.center:before {
            left: 50%;
            right: auto;
            transform: translateX(-50%)
        }

        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            color: black;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1.1rem
        }

        .dropdown-item i {
            width: 30px;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            margin-right: 15px
        }

        .dropdown-item.active-page {
            background: rgb(225, 255, 220);
            color: var(--primary-dark);
            font-weight: bold;
            border-left: 4px solid var(--primary-color)
        }

        .dropdown-item:hover {
            background: var(--light-gray);
            padding-left: 30px
        }

        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            animation: pulse 2s infinite
        }

        @keyframes pulse {
            0% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.1)
            }

            100% {
                transform: scale(1)
            }
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 15px
        }

        .notification-link {
            display: flex;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            transition: var(--transition)
        }

        .notification-link:hover {
            background: #f9f9f9
        }

        .content {
            max-width: 1000px;
            margin: 0 auto
        }

        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            overflow: hidden;
            margin-bottom: 20px;
            max-width: 1200px;
            margin: 0 auto
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            padding: 25px 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-color)
        }

        .form-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: .5px
        }

        .form-body {
            padding: 20px
        }

        .form-section {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04)
        }

        .section-title {
            font-size: 18px;
            color: var(--primary-dark);
            margin-bottom: 15px;
            padding-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 20px;
            background: #000;
            border-radius: 2px
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px
        }

        .form-group {
            flex: 1;
            min-width: 280px;
            margin-bottom: 10px
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px
        }

        input[type="text"],
        input[type="date"],
        input[type="number"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #000;
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark)
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(43, 102, 37, .1)
        }

        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 6px;
            transition: var(--transition)
        }

        .radio-option:hover {
            background: var(--primary-light)
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--primary-color)
        }

        .image-upload-container {
            border: 1px dashed #000;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: var(--light-gray);
            transition: var(--transition);
            cursor: pointer;
            margin-bottom: 15px
        }

        .image-upload-container:hover {
            border-color: var(--primary-color);
            background: var(--primary-light)
        }

        .image-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-light)
        }

        .image-upload-label i {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px
        }

        .image-upload-input {
            display: none
        }

        .image-preview {
            margin-top: 20px;
            max-width: 100%;
            display: none
        }

        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1)
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            gap: 8px;
            font-size: 14px;
            border: none
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            min-width: 200px;
            padding: 12px 30px;
            margin-top: 5px
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 102, 37, .2)
        }

        .btn-outline {
            background: var(--white);
            border: 1px solid #000;
            color: var(--text-dark);
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition)
        }

        .btn-outline:hover {
            background: var(--light-gray);
            border-color: var(--primary-color);
            color: var(--primary-color)
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 5px;
            padding-top: 0
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #c3e6cb;
            animation: slideIn .3s ease
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0
            }

            to {
                transform: translateY(0);
                opacity: 1
            }
        }

        .success-message i {
            color: #155724;
            font-size: 20px
        }

        @media (max-width:768px) {
            header {
                padding: 0 15px
            }

            .form-body {
                padding: 20px
            }

            .form-section {
                padding: 20px
            }

            .form-row {
                gap: 20px
            }

            .form-group {
                min-width: 100%
            }

            .form-actions {
                flex-direction: column
            }

            .btn {
                width: 100%
            }
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5)
        }

        .modal-content {
            background: var(--white);
            margin: 5% auto;
            padding: 25px;
            border-radius: 4px;
            width: 90%;
            max-width: 600px;
            border: 1px solid var(--border-color)
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color)
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 18px
        }

        .close {
            color: var(--text-light);
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition)
        }

        .close:hover {
            color: var(--text-dark)
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color)
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 14px
        }

        .summary-table th {
            background: #f0fff5;
            text-align: left
        }

        .badge-mini {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid #ddd;
            font-size: 12px
        }

        .hidden {
            display: none
        }
    </style>

</head>

<body>
    <header>
        <div class="logo">
            <a href="wildhome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item active-page">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Record</span>
                    </a>

                    <a href="wildpermit.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Messages Icon -->
            <!-- <div class="nav-item">
                <div class="nav-icon">
                    <a href="wildmessage.php" aria-label="Messages">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div> -->

            <!-- Notifications -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>

                <div class="dropdown-menu notifications-dropdown">
                    <!-- Sticky header -->
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>

                    <!-- Scrollable list -->
                    <div class="notification-list" id="wildNotifList">
                        <?php if (empty($wildNotifs)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($wildNotifs as $nf):
                                $st = strtolower((string)($nf['approval_status'] ?? 'pending'));
                                $isIncident = !empty($nf['incident_id']);

                                if ($isIncident) {
                                    $icon = $st === 'approved' ? 'fa-check-circle'
                                        : ($st === 'rejected' ? 'fa-times-circle' : 'fa-exclamation-triangle');
                                    $title = 'Incident ' . ucfirst($st);
                                } else {
                                    $icon = 'fa-file';
                                    $title = 'New wildlife permit request';
                                }

                                $permit = strtolower((string)($nf['permit_type'] ?? ''));
                                $who = h((string)($nf['client_first'] ?? 'A client'));
                                $msg = trim((string)($nf['message'] ?? ''));
                                if ($msg === '') {
                                    $msg = $who . ' requested a wildlife ' . ($permit ?: 'new') . ' permit.';
                                }

                                $ago = time_elapsed_string($nf['created_at'] ?? date('c'));
                                if (!empty($nf['incident_id'])) {
                                    $href = 'reportaccident.php?focus=' . urlencode((string)$nf['incident_id']);
                                } elseif (!empty($nf['approval_id'])) {
                                    $href = 'wildeach.php?id=' . urlencode((string)$nf['approval_id']);
                                } else {
                                    $href = 'wildnotification.php';
                                }

                                $isRead = ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1');
                            ?>
                                <div class="notification-item <?= $isRead ? '' : 'unread' ?> status-<?= h($st) ?>"
                                    data-notif-id="<?= h((string)($nf['notif_id'] ?? '')) ?>"
                                    data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                                    <a href="<?= h($href) ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <i class="fas <?= h($icon) ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($msg) ?></div>
                                            <div class="notification-time"><?= h($ago) ?></div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Sticky footer -->
                    <div class="notification-footer">
                        <a href="wildnotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
            <a href="wildrecord.php" class="btn btn-outline">
                <i class="fas fa-list"></i> View Records
            </a>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?= h($successMessage) ?></span>
            </div>
        <?php elseif (!empty($errorMessage)): ?>
            <div class="success-message" style="background:#fde2e1;color:#8a1f11;border-color:#f9b7b2;">
                <i class="fas fa-exclamation-triangle" style="color:#8a1f11;"></i>
                <span><?= h($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- ======= FORM (multipart + arrays) ======= -->
        <form id="breedingForm" class="form-container" method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="__action" value="save_report">

            <div class="form-header">
                <h1>WILDLIFE BREEDING REPORT</h1>
            </div>

            <div class="form-body">
                <!-- Record Period -->
                <div class="form-section">
                    <h2 class="section-title">RECORD PERIOD</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                    </div>
                </div>

                <!-- Owner Information -->
                <div class="form-section">
                    <h2 class="section-title">OWNER INFORMATION</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" placeholder="Enter owner's full name" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" placeholder="Enter complete address" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="wfp_no">WFP Number</label>
                            <input type="text" id="wfp_no" name="wfp_no" placeholder="Enter WFP number" required>
                        </div>
                        <div class="form-group">
                            <label for="farm_location">Farm Location</label>
                            <input type="text" id="farm_location" name="farm_location" placeholder="Enter farm location" required>
                        </div>
                    </div>
                </div>

                <!-- Wildlife Stock Details -->
                <div class="form-section">
                    <h2 class="section-title">WILDLIFE STOCK DETAILS</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Species Image (optional)</label>
                            <div class="image-upload-container" onclick="document.getElementById('species_image_main').click()">
                                <input type="file" name="species_image[]" id="species_image_main" accept="image/*" class="image-upload-input">
                                <div class="image-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload species image</span>
                                    <small style="display: block; margin-top: 5px; color: var(--text-light);">(JPEG, PNG, max 5MB)</small>
                                </div>
                                <div class="image-preview" id="image-preview-main"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="species_name">Species Name</label>
                            <select id="species_name" name="species_name[]" required>
                                <option value="">Select Species</option>
                                <option value="Agapornis">Agapornis</option>
                                <option value="Cockatiel">Cockatiel</option>
                                <option value="Lovebird">Lovebird</option>
                                <option value="Parakeet">Parakeet</option>
                                <option value="Macaw">Macaw</option>
                                <option value="African Grey">African Grey</option>
                                <option value="Eclectus">Eclectus</option>
                                <option value="Amazon">Amazon</option>
                                <option value="Conure">Conure</option>
                                <option value="Finch">Finch</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stock_number">Breeding Stock Number</label>
                            <input type="number" id="stock_number" name="stock_number[]" placeholder="Enter stock number" min="0" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="previous_balance">Previous Quarter Balance</label>
                            <input type="number" id="previous_balance" name="previous_balance[]" placeholder="Enter previous balance" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="status[0]" value="alive" checked>
                                    <span>Alive</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="status[0]" value="dead">
                                    <span>Dead</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Extra species get appended here (hidden) -->
                    <div id="speciesList" class="hidden"></div>

                    <div class="form-row">
                        <div class="form-group" style="margin-top: 20px; text-align: center; width: 100%;">
                            <button type="button" class="btn btn-outline" onclick="openSpeciesModal()" style="width: auto;">
                                <i class="fas fa-plus"></i> Add Another Species
                            </button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" id="openConfirm" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Species Modal (inside the form so file inputs submit) -->
            <div id="speciesModal" class="modal">
                <div class="modal-content" style="overflow-y: auto; height: 80vh;">
                    <div class="modal-header">
                        <h2>Add Another Species</h2>
                        <span class="close" onclick="closeSpeciesModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Upload Image (optional)</label>
                            <div class="image-upload-container" onclick="document.getElementById('modal_species_image').click()">
                                <input type="file" id="modal_species_image" accept="image/*" class="image-upload-input">
                                <div class="image-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload species image</span>
                                </div>
                                <div class="image-preview" id="modal-image-preview"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modal_species_name">Species Name</label>
                            <select id="modal_species_name">
                                <option value="">Select Species</option>
                                <option value="Agapornis">Agapornis</option>
                                <option value="Cockatiel">Cockatiel</option>
                                <option value="Lovebird">Lovebird</option>
                                <option value="Parakeet">Parakeet</option>
                                <option value="Macaw">Macaw</option>
                                <option value="African Grey">African Grey</option>
                                <option value="Eclectus">Eclectus</option>
                                <option value="Amazon">Amazon</option>
                                <option value="Conure">Conure</option>
                                <option value="Finch">Finch</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modal_stock_number">Breeding Stock Number</label>
                            <input type="number" id="modal_stock_number" placeholder="Enter stock number" min="0">
                        </div>
                        <div class="form-group">
                            <label for="modal_previous_balance">Previous Quarter Balance</label>
                            <input type="number" id="modal_previous_balance" placeholder="Enter previous balance" min="0">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="modal_status" value="alive" checked>
                                    <span>Alive</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="modal_status" value="dead">
                                    <span>Dead</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeSpeciesModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="addSpeciesFromModal()">
                            <i class="fas fa-plus"></i> Add Species
                        </button>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div id="confirmModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Confirm Save</h2>
                        <span class="close" onclick="closeConfirmModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom:10px;">Please review the summary before saving:</p>
                        <div id="confirmSummary"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Confirm & Save
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
    <script>
        // ======= Image preview (main)
        document.getElementById('species_image_main').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image-preview-main');
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    preview.style.display = 'block';
                    preview.innerHTML = `<img src="${ev.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
                preview.innerHTML = '';
            }
        });

        // ======= Species Modal open/close
        function openSpeciesModal() {
            document.getElementById('speciesModal').style.display = 'block';
        }

        function closeSpeciesModal() {
            document.getElementById('speciesModal').style.display = 'none';
        }

        // ======= Modal image preview
        (function attachModalImagePreviewListener() {
            const input = document.getElementById('modal_species_image');
            if (!input) return;
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById('modal-image-preview');
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        preview.style.display = 'block';
                        preview.innerHTML = `<img src="${ev.target.result}" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                    preview.innerHTML = '';
                }
            });
        })();

        // ======= Keep a counter for status radio group indexes
        let speciesIdx = 1; // 0 is used by the main species radios (status[0])

        // ======= Add species from modal into hidden list (so files submit)
        function addSpeciesFromModal() {
            const nameSel = document.getElementById('modal_species_name');
            const stockEl = document.getElementById('modal_stock_number');
            const prevEl = document.getElementById('modal_previous_balance');
            const fileEl = document.getElementById('modal_species_image');
            const sts = (document.querySelector('input[name="modal_status"]:checked') || {}).value || 'alive';

            const sp = (nameSel.value || '').trim();
            const st = parseInt(stockEl.value || '0', 10);
            const pb = parseInt(prevEl.value || '0', 10);

            if (!sp) {
                alert('Please select a species.');
                return;
            }

            const list = document.getElementById('speciesList');

            // text/number/status as hidden array inputs
            const hiddenWrap = document.createElement('div');
            hiddenWrap.className = 'hidden';
            hiddenWrap.innerHTML = `
        <input type="hidden" name="species_name[]" value="${sp}">
        <input type="hidden" name="stock_number[]" value="${st}">
        <input type="hidden" name="previous_balance[]" value="${pb}">
    `;
            list.appendChild(hiddenWrap);

            // status needs to align index; create radio inputs collapsed for this row
            const stWrap = document.createElement('div');
            stWrap.className = 'hidden';
            stWrap.innerHTML = `
        <input type="radio" name="status[${speciesIdx}]" value="alive" ${sts==='alive'?'checked':''}>
        <input type="radio" name="status[${speciesIdx}]" value="dead"  ${sts==='dead'?'checked':''}>
    `;
            list.appendChild(stWrap);

            // move the actual file input node so its FileList submits
            if (fileEl && fileEl.files && fileEl.files.length) {
                const moved = fileEl; // move node
                moved.name = 'species_image[]';
                moved.id = 'species_image_' + Date.now();
                list.appendChild(moved);

                // recreate a fresh input back in the MODAL container for the next add
                const modalContainer = document.getElementById('modal_species_image') ? document.getElementById('modal_species_image').parentElement : document.querySelector('#speciesModal .image-upload-container');
                const fresh = document.createElement('input');
                fresh.type = 'file';
                fresh.accept = 'image/*';
                fresh.className = 'image-upload-input';
                fresh.id = 'modal_species_image';
                // reattach preview listener for the new modal input
                fresh.addEventListener('change', function(e) {
                    const f = e.target.files[0];
                    const pv = document.getElementById('modal-image-preview');
                    if (f) {
                        const r = new FileReader();
                        r.onload = (ev) => {
                            pv.style.display = 'block';
                            pv.innerHTML = `<img src="${ev.target.result}" alt="Preview">`;
                        };
                        r.readAsDataURL(f);
                    } else {
                        pv.style.display = 'none';
                        pv.innerHTML = '';
                    }
                });
                if (modalContainer) modalContainer.appendChild(fresh);

                // reset modal preview
                const pv = document.getElementById('modal-image-preview');
                pv.style.display = 'none';
                pv.innerHTML = '';
            }

            speciesIdx++;
            alert('Species added.');
            closeSpeciesModal();
        }

        // ======= Confirmation modal
        function buildSummaryHTML() {
            const name = document.getElementById('name').value || '';
            const addr = document.getElementById('address').value || '';
            const wfp = document.getElementById('wfp_no').value || '';
            const farm = document.getElementById('farm_location').value || '';
            const sd = document.getElementById('start_date').value || '';
            const ed = document.getElementById('end_date').value || '';

            // collect species rows (main + extras)
            const names = []
                .concat(Array.from(document.querySelectorAll('select[name="species_name[]"]')).map(el => el.value))
                .concat(Array.from(document.querySelectorAll('#speciesList input[name="species_name[]"]')).map(el => el.getAttribute('value')))
                .filter(Boolean);

            const stocks = []
                .concat(Array.from(document.querySelectorAll('input[name="stock_number[]"]')).map(el => el.value))
                .concat(Array.from(document.querySelectorAll('#speciesList input[name="stock_number[]"]')).map(el => el.getAttribute('value')));

            const prevs = []
                .concat(Array.from(document.querySelectorAll('input[name="previous_balance[]"]')).map(el => el.value))
                .concat(Array.from(document.querySelectorAll('#speciesList input[name="previous_balance[]"]')).map(el => el.getAttribute('value')));

            // statuses: read the checked radio for each index
            const stats = [];
            for (let i = 0; i < speciesIdx; i++) {
                const r = document.querySelector(`input[name="status[${i}]"]:checked`);
                stats.push(r ? r.value : 'alive');
            }

            let rows = '';
            for (let i = 0; i < names.length; i++) {
                rows += `<tr>
            <td>${i+1}</td>
            <td>${names[i]}</td>
            <td>${stocks[i] ?? ''}</td>
            <td>${prevs[i] ?? ''}</td>
            <td><span class="badge-mini">${stats[i] ?? ''}</span></td>
        </tr>`;
            }

            return `
        <div><strong>Period:</strong> ${sd} &rarr; ${ed}</div>
        <div><strong>Owner:</strong> ${name}</div>
        <div><strong>Address:</strong> ${addr}</div>
        <div><strong>WFP No.:</strong> ${wfp}</div>
        <div><strong>Farm:</strong> ${farm}</div>
        <hr style="margin:12px 0;">
        <table class="summary-table">
            <thead><tr><th>#</th><th>Species</th><th>Stock</th><th>Prev Bal</th><th>Status</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="5">No species added.</td></tr>'}</tbody>
        </table>
    `;
        }

        function openConfirmModal() {
            const sum = document.getElementById('confirmSummary');
            sum.innerHTML = buildSummaryHTML();
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // Open confirm when clicking main Save button
        document.getElementById('openConfirm').addEventListener('click', function() {
            // basic validation prompt (HTML5 will also catch required)
            if (!document.getElementById('start_date').value || !document.getElementById('end_date').value) {
                alert('Please set the record period.');
                return;
            }
            if (!document.getElementById('name').value || !document.getElementById('wfp_no').value) {
                alert('Please fill owner name and WFP number.');
                return;
            }
            openConfirmModal();
        });

        // Close modals on outside click
        window.onclick = function(e) {
            const speciesM = document.getElementById('speciesModal');
            const confirmM = document.getElementById('confirmModal');
            if (e.target === speciesM) closeSpeciesModal();
            if (e.target === confirmM) closeConfirmModal();
        };

        // ======= Dropdown hover (kept behavior)
        document.addEventListener('DOMContentLoaded', function() {
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
            });
        });
    </script>

</body>


</html>