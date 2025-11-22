<?php
// incoming.php — Seedlings Received (autocomplete + confirm modal + toasts + loading overlay)
// Inserts ONE ROW PER ITEM into seedling_intakes, filling seedlings_id when the name exists.
// For brand-new names, seedlings_id stays NULL.

// -------------------------
// Logout
// -------------------------
if (isset($_GET['logout'])) {
    session_start();
    session_destroy();
    header("Location: superadmin/superlogin.php");
    exit();
}

// -------------------------
// DB
// -------------------------
$pdo = null;
try {
    require_once __DIR__ . '/../backend/connection.php'; // must define $pdo
} catch (Throwable $e) {
    // keep $pdo = null so page still renders
}

// -------------------------
// Detect tables + seedlings_id column type
// -------------------------
$hasIntakes = false;
$intakesSeedlingsIdType = null;
try {
    if ($pdo) {
        $hasIntakes = (bool)$pdo->query("SELECT to_regclass('public.seedling_intakes')")->fetchColumn();
        if ($hasIntakes) {
            $q = $pdo->prepare("
                SELECT data_type
                FROM information_schema.columns
                WHERE table_schema='public' AND table_name='seedling_intakes' AND column_name='seedlings_id'
                LIMIT 1
            ");
            $q->execute();
            $intakesSeedlingsIdType = $q->fetchColumn() ?: null; // should be 'text' after migration
        }
    }
} catch (Throwable $e) {
    // ignore
}

// -------------------------
// AJAX: save seedling intakes
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seedlings_intake_create') {
    header('Content-Type: application/json');
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection not available']);
        exit;
    }

    try {
        if (!$hasIntakes) throw new Exception('Table public.seedling_intakes not found.');

        $agency_name   = trim((string)($_POST['agency_name'] ?? ''));
        $received_by   = trim((string)($_POST['received_by'] ?? ''));
        $date_received = trim((string)($_POST['date_received'] ?? ''));
        $species       = $_POST['species'] ?? [];
        $qtys          = $_POST['seedlings_delivered'] ?? [];

        if ($agency_name === '' || $received_by === '' || $date_received === '') {
            throw new Exception('Agency, Receiver, and Date Received are required.');
        }
        if (!is_array($species) || !is_array($qtys) || count($species) !== count($qtys)) {
            throw new Exception('Invalid items payload.');
        }

        // Clean list
        $items = [];
        for ($i = 0; $i < count($species); $i++) {
            $n = trim((string)$species[$i]);
            $q = (int)$qtys[$i];
            if ($n !== '' && $q > 0) $items[] = [$n, $q];
        }
        if (!$items) throw new Exception('No valid seedlings to add.');

        // Lookup prepared
        $selSeedId = $pdo->prepare("SELECT seedlings_id FROM public.seedlings WHERE lower(seedling_name)=lower(:name) LIMIT 1");

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO public.seedling_intakes
                (agency_name, seedlings_name, seedlings_id, quantity, date_received, received_by)
            VALUES (:agency_name, :seedlings_name, :seedlings_id, :quantity, :date_received, :received_by)
        ");

        // If column is still UUID (old), warn early (we'll still try best-effort)
        $isUuidCol = (strtolower((string)$intakesSeedlingsIdType) === 'uuid');
        $isUuid = function ($v) {
            return is_string($v) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v);
        };

        $pdo->beginTransaction();
        foreach ($items as [$n, $q]) {
            // default NULL for brand-new names
            $sid = null;

            // find matching seedlings_id (text like "seed_001")
            $selSeedId->execute([':name' => $n]);
            $found = $selSeedId->fetchColumn();

            if ($found !== false && $found !== null && $found !== '') {
                if ($isUuidCol) {
                    if ($isUuid($found)) $sid = $found;  // else stays NULL due to type mismatch
                } else {
                    $sid = $found;
                }
            }

            $stmt->execute([
                ':agency_name'    => $agency_name,
                ':seedlings_name' => $n,
                ':seedlings_id'   => $sid,
                ':quantity'       => $q,
                ':date_received'  => $date_received,
                ':received_by'    => $received_by
            ]);
        }
        $pdo->commit();

        if ($isUuidCol) {
            echo json_encode([
                'success' => true,
                'inserted' => count($items),
                'message' => 'Saved, but seedlings_id column is UUID. Convert it to TEXT to store IDs like "seed_001".'
            ]);
        } else {
            echo json_encode(['success' => true, 'inserted' => count($items)]);
        }
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// -------------------------
// Real counters for nav badges
// -------------------------
$current_page = basename($_SERVER['PHP_SELF']);
$quantities = [
    'total_received'   => 0,
    'total_released'   => 0,
    'total_discarded'  => 0,
    'total_balance'    => 0,
    'plantable_seedlings' => 0,
    'all_records' => 0
];

if ($pdo) {
    try {
        $quantities['total_received']  = (int)($pdo->query("SELECT COALESCE(SUM(quantity),0) FROM public.seedling_intakes")->fetchColumn());
        $quantities['total_released']  = (int)($pdo->query("SELECT COALESCE(SUM(released_qty),0) FROM public.seedling_releases")->fetchColumn());
        $quantities['total_discarded'] = (int)($pdo->query("SELECT COALESCE(SUM(discard_qty),0) FROM public.seedling_discards")->fetchColumn());
        $quantities['total_balance']   = (int)($pdo->query("SELECT COALESCE(SUM(stock),0) FROM public.seedlings")->fetchColumn());
    } catch (Throwable $e) {
        // leave defaults if a table is missing
    }
}

// -------------------------
// Seedling options
// -------------------------
$seedlingOptions = [];
if ($pdo) {
    try {
        $q = $pdo->query("SELECT seedling_name FROM public.seedlings ORDER BY seedling_name ASC");
        $seedlingOptions = array_values(array_filter($q->fetchAll(PDO::FETCH_COLUMN) ?: [], fn($s) => $s !== ''));
    } catch (Throwable $e) {
        $seedlingOptions = [];
    }
}

// -------------------------
// Header notifications (seedlingshome.php style)
// -------------------------
$seedlingNotifs = [];
$unreadSeedling = 0;

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['ajax'] === 'mark_read') {
            $notifId = $_POST['notif_id'] ?? '';
            if (!$notifId) {
                echo json_encode(['ok' => false, 'error' => 'missing notif_id']);
                exit;
            }

            $st = $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE notif_id=:id");
            $st->execute([':id' => $notifId]);

            echo json_encode(['ok' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_all_read') {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE public.notifications SET is_read = true WHERE LOWER(COALESCE(\"to\", ''))='seedling' AND is_read=false");
            $pdo->commit();
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'unknown action']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[SEEDLING NOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

// -------------------------
// helpers
// -------------------------
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function time_elapsed_string($datetime, $full = false): string
{
    if (!$datetime) return '';
    $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago  = new DateTime($datetime, new DateTimeZone('UTC'));
    $ago->setTimezone(new DateTimeZone('Asia/Manila'));
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

try {
    $seedlingNotifs = $pdo->query("
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
            n.incident_id,
            n.reqpro_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'seedling'
        ORDER BY n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadSeedling = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'seedling'
          AND n.is_read = false
    ")->fetchColumn();
} catch (Throwable $e) {
    error_log('[SEEDLING NOTIFS] ' . $e->getMessage());
    $seedlingNotifs = [];
    $unreadSeedling = 0;
}

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SEEDLINGS RECEIVED</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/incoming.css">
    <style>
        :root {
            --primary-color: #2b6625;
            --primary-dark: #1e4a1a;
            --white: #fff;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --transition: .2s ease
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            color: #000
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
            padding-top: 100px
        }

        /* Header (kept local; do NOT import seedlingshome.css here) */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary-color);
            color: #fff;
            padding: 0 30px;
            height: 58px;
            position: fixed;
            left: 0;
            right: 0;
            top: 0;
            z-index: 1000
        }

        .logo img {
            height: 45px
        }

        .nav-container {
            display: flex;
            gap: 20px;
            align-items: center
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
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            cursor: pointer
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #fff;
            min-width: 300px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: .2s
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0)
        }

        .dropdown-menu.center {
            left: 50%;
            right: auto;
            transform: translateX(-50%) translateY(10px)
        }

        .dropdown-menu.center:hover,
        .dropdown:hover .dropdown-menu.center {
            transform: translateX(-50%) translateY(0)
        }

        .dropdown-item {
            padding: 15px 25px;
            display: flex;
            gap: 10px;
            text-decoration: none;
            color: #333
        }

        .dropdown-item.active-page {
            background: rgb(225, 255, 220);
            font-weight: 700;
            border-left: 4px solid var(--primary-color)
        }

        .quantity-badge {
            margin-left: auto;
            font-weight: 700;
            color: var(--primary-color)
        }

        .badge {
            position: absolute;
            top: 2px;
            right: 8px;
            background: #ff4757;
            color: #fff;
            border-radius: 50%;
            width: 14px;
            height: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
        }

        /* Page content styles (unchanged) */
        .main-content {
            margin-top: -3%;
            padding: 20px;
            max-width: 1200px;
            margin-inline: auto
        }

        .data-entry-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .78)
        }

        .form-header {
            grid-column: span 2
        }

        .form-title {
            text-align: center
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin: auto 5px;
            width: 100%
        }

        .form-group input {
            height: 45px;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(43, 102, 37, .2);
            background: #fff
        }

        .number-input-container {
            position: relative
        }

        .number-input {
            height: 45px;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9
        }

        .number-input-buttons {
            position: absolute;
            right: 1px;
            top: 1px;
            bottom: 1px;
            width: 30px;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #000;
            background: #f5f5f5;
            border-radius: 0 4px 4px 0
        }

        .number-input-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            cursor: pointer
        }

        .button-container {
            grid-column: span 2;
            display: flex;
            justify-content: center;
            gap: 20px
        }

        .submit-button,
        .view-records-button {
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 12px 25px;
            min-width: 190px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .view-records-button {
            background: #00796b;
        }

        .view-records-button:hover,
        .view-records-button:focus {
            background: #165033;
            outline: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
            text-decoration: none;
        }

        .species-list {
            grid-column: span 2;
            margin-top: 10px
        }

        .species-list-title {
            font-weight: 700;
            margin-bottom: 10px
        }

        #species-list-items {
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .species-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px;
            border: 1px solid #000;
            border-radius: 6px;
            background: #f9f9f9
        }

        .species-name {
            font-weight: 700
        }

        .remove-species {
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer
        }

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
            background: #fff;
            margin: 8% auto;
            padding: 28px;
            border-radius: 10px;
            max-width: 640px;
            position: relative
        }

        .modal-title {
            text-align: center;
            margin-bottom: 15px;
            font-weight: 700
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px
        }

        .modal-button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer
        }

        .modal-save {
            background: var(--primary-color);
            color: #fff
        }

        .modal-cancel {
            background: #f44336;
            color: #fff
        }

        .modal-form-row {
            display: flex;
            gap: 20px
        }

        .modal-form-group {
            flex: 1;
            display: flex;
            flex-direction: column
        }

        .modal-number-input {
            height: 45px;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9
        }

        .modal-close {
            position: absolute;
            right: 10px;
            top: 10px;
            border: none;
            background: transparent;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            padding: 6px;
            color: #333
        }

        .modal-close:hover {
            color: #000
        }

        .combo {
            position: relative
        }

        .combo-input {
            height: 45px;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9
        }

        .combo-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 260px;
            overflow: auto;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .12);
            z-index: 1100;
            display: none
        }

        .combo-item {
            padding: 10px 12px;
            cursor: pointer
        }

        .combo-item:hover,
        .combo-item.active {
            background: #eef2f7
        }

        .combo-empty {
            padding: 10px 12px;
            color: #444;
            font-style: italic
        }

        .toast-container {
            position: fixed;
            top: 72px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .toast {
            min-width: 260px;
            max-width: 420px;
            background: #fff;
            border-left: 6px solid #2b6625;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
            padding: 12px 14px
        }

        .toast.success {
            border-left-color: #16a34a
        }

        .toast.error {
            border-left-color: #dc2626
        }

        .toast-title {
            font-weight: 700;
            margin-bottom: 4px
        }

        .toast-msg {
            font-size: .95rem
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .85);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column
        }

        .loading-spinner {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 6px solid #e5e7eb;
            border-top-color: var(--primary-color);
            animation: spin 1s linear infinite
        }

        .loading-text {
            margin-top: 12px;
            font-weight: 700;
            color: #1f2937
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        /* Notification styles from seedlingshome.php */
        .dropdown-menu.notifications-dropdown {
            display: grid;
            grid-template-rows: auto 1fr auto;
            width: min(460px, 92vw);
            max-height: 72vh;
            overflow: hidden;
            padding: 0;
        }

        .notifications-dropdown .notification-header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }

        .notifications-dropdown .notification-list {
            overflow: auto;
            padding: 8px 0;
            background: #fff;
        }

        .notifications-dropdown .notification-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px 16px;
        }

        .notifications-dropdown .view-all {
            font-weight: 600;
            color: #1b5e20;
            text-decoration: none;
        }

        .notification-item {
            padding: 18px;
            background: #f8faf7;
        }

        .notification-item.unread {
            background: #eef7ee;
        }

        .notification-item+.notification-item {
            border-top: 1px solid #eef2f1;
        }

        .notification-icon {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: #1b5e20;
        }

        .notification-link {
            display: flex;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .notification-title {
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 6px;
        }

        .notification-time {
            color: #6b7280;
            font-size: .9rem;
            margin-top: 8px;
        }

        .notification-message {
            color: #234;
        }

        .mark-all-read {
            color: #1b5e20;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .mark-all-read:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- HEADER from seedlings home, with Seedlings Received active -->
    <header>
        <div class="logo">
            <a href="seedlingshome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-container">
            <!-- Main Dropdown Menu -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="incoming.php" class="dropdown-item active-page">
                        <i class="fas fa-seedling"></i>
                        <span class="item-text">Seedlings Received</span>
                    </a>

                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>

                    <a href="user_requestseedlings.php" class="dropdown-item">
                        <i class="fas fa-paper-plane"></i>
                        <span>Seedlings Request</span>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadSeedling ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="seedlingNotifList">
                        <?php
                        $combined = [];

                        // Permits / notifications
                        foreach ($seedlingNotifs as $nf) {
                            $combined[] = [
                                'id'          => $nf['notif_id'],
                                'notif_id'    => $nf['notif_id'],
                                'approval_id' => $nf['approval_id'] ?? null,
                                'incident_id' => $nf['incident_id'] ?? null,
                                'reqpro_id'   => $nf['reqpro_id'] ?? null,
                                'is_read'     => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'message'     => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a seedling request.')),
                                'ago'         => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'        => !empty($nf['reqpro_id']) ? 'seedlingsprofile.php' : (!empty($nf['approval_id']) ? 'user_requestseedlings.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'seedlingsnotification.php'))
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No seedling notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                                $notifTitle = !empty($item['incident_id']) ? 'Incident report' : (!empty($item['reqpro_id']) ? 'Profile update' : 'Seedling Request');
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= h($item['id']) ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= $notifTitle ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="seedlingsnotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?= $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span class="item-text">Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="item-text">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="toast-container" id="toast-container"></div>

    <div id="loading-overlay" class="loading-overlay" role="status" aria-live="polite" aria-busy="true">
        <div class="loading-spinner"></div>
        <div class="loading-text">Saving…</div>
    </div>

    <div class="main-content">
        <div style="max-width:1200px;margin:0 auto 10px auto;display:flex;justify-content:flex-end;padding:0 20px">
            <a href="receivedrecords.php" class="view-records-button">VIEW RECORDS</a>
        </div>

        <form class="data-entry-form" id="seedlings-form">
            <div class="form-header">
                <h1 class="form-title">SEEDLINGS RECEIVED FORM</h1>
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label for="agency_name">NAME OF AGENCY/COMPANY:</label>
                <input type="text" id="agency_name" name="agency_name" placeholder="Enter agency/company name" required>
            </div>

            <div class="form-group">
                <label for="species">SEEDLING NAME:</label>
                <input type="text" id="species" name="species[]" class="combo-input" placeholder="Type or choose…" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="seedlings-delivered">QUANTITY:</label>
                <div class="number-input-container">
                    <input type="number" id="seedlings-delivered" name="seedlings_delivered[]" min="0" value="0" class="number-input">
                    <div class="number-input-buttons">
                        <button type="button" class="number-input-button" onclick="seedlingsIncrementValue()">▲</button>
                        <button type="button" class="number-input-button" onclick="seedlingsDecrementValue()">▼</button>
                    </div>
                </div>
            </div>

            <div id="species-data-container" style="display:none;"></div>

            <div class="form-group">
                <label for="date_received">DATE RECEIVED:</label>
                <input type="date" id="date_received" name="date_received" required>
            </div>

            <div class="form-group">
                <label for="received_by">NAME OF RECEIVER:</label>
                <input type="text" id="received_by" name="received_by" placeholder="Enter receiver's name" required>
            </div>

            <div class="button-container">
                <button type="button" class="view-records-button" id="add-species-btn">ADD SEEDLINGS</button>
                <button type="submit" class="submit-button">SUBMIT</button>
            </div>

            <div class="species-list">
                <div class="species-list-title">Added Seedlings:</div>
                <div id="species-list-items"></div>
            </div>
        </form>
    </div>

    <!-- Add Additional Seedlings modal -->
    <div id="species-modal" class="modal">
        <div class="modal-content" id="species-modal-content">
            <button type="button" class="modal-close" id="close-species-modal" aria-label="Close">&times;</button>
            <h2 class="modal-title">Add Additional Seedlings</h2>
            <div class="modal-form-row">
                <div class="modal-form-group">
                    <label for="modal-species">SEEDLINGS NAME:</label>
                    <input type="text" id="modal-species" class="combo-input" placeholder="Type or choose…" autocomplete="off">
                </div>
                <div class="modal-form-group">
                    <label for="modal-seedlings">QUANTITY:</label>
                    <input type="number" id="modal-seedlings" min="0" value="0" class="modal-number-input">
                </div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="modal-button modal-cancel" id="cancel-species">Cancel</button>
                <button type="button" class="modal-button modal-save" id="save-species">Add Seedlings</button>
            </div>
        </div>
    </div>

    <!-- Confirm modal -->
    <div id="confirm-modal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Submit this form?</h2>
            <p id="confirm-msg" style="margin-top:8px">Are you sure you want to submit?</p>
            <div class="modal-buttons">
                <button type="button" class="modal-button modal-cancel" id="confirm-cancel">Cancel</button>
                <button type="button" class="modal-button modal-save" id="confirm-save">Yes, Submit</button>
            </div>
        </div>
    </div>

    <script>
        // options
        window.SEEDLING_OPTIONS = <?= json_encode($seedlingOptions, JSON_UNESCAPED_UNICODE); ?>;

        // toasts
        function showToast(type, msg) {
            const wrap = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = 'toast ' + (type === 'success' ? 'success' : 'error');
            el.innerHTML = `<div class="toast-title">${type==='success'?'Success':'Error'}</div><div class="toast-msg">${msg}</div>`;
            wrap.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transition = 'opacity .4s';
                setTimeout(() => wrap.removeChild(el), 400)
            }, 3500);
        }

        // loading overlay
        const loadingEl = () => document.getElementById('loading-overlay');

        function showLoading(text = 'Saving…') {
            const el = loadingEl();
            if (!el) return;
            el.querySelector('.loading-text').textContent = text;
            el.style.display = 'flex';
        }

        function hideLoading() {
            const el = loadingEl();
            if (!el) return;
            el.style.display = 'none';
        }

        // number helpers
        function seedlingsIncrementValue() {
            const i = document.getElementById('seedlings-delivered');
            if (i) i.value = (parseInt(i.value) || 0) + 1;
        }

        function seedlingsDecrementValue() {
            const i = document.getElementById('seedlings-delivered');
            if (i) i.value = Math.max(0, (parseInt(i.value) || 0) - 1);
        }

        // autocomplete
        function attachCombo(input) {
            const wrap = document.createElement('div');
            wrap.className = 'combo';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            const menu = document.createElement('div');
            menu.className = 'combo-menu';
            wrap.appendChild(menu);

            const stopAll = e => {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            };
            ['click', 'mousedown', 'mouseup', 'pointerdown', 'pointerup', 'touchstart', 'touchend'].forEach(evt => menu.addEventListener(evt, stopAll));
            input.addEventListener('mousedown', e => e.stopPropagation());
            input.addEventListener('click', e => e.stopPropagation());

            const opts = [...new Set((window.SEEDLING_OPTIONS || []).map(s => String(s)))];
            let activeIndex = -1;

            function render(list, q) {
                menu.innerHTML = '';
                activeIndex = -1;
                if (!list.length) {
                    const e = document.createElement('div');
                    e.className = 'combo-empty';
                    e.textContent = q ? `Add "${q}"` : 'Type to search…';
                    menu.appendChild(e);
                    menu.style.display = 'block';
                    return;
                }
                list.forEach((t, idx) => {
                    const item = document.createElement('div');
                    item.className = 'combo-item';
                    item.textContent = t;
                    const pick = ev => {
                        stopAll(ev);
                        select(t);
                    };
                    item.addEventListener('mousedown', pick);
                    item.addEventListener('click', pick);
                    menu.appendChild(item);
                    if (idx === 0) {
                        item.classList.add('active');
                        activeIndex = 0;
                    }
                });
                menu.style.display = 'block';
            }

            function filter(q) {
                q = q.toLowerCase().trim();
                if (!q) return render(opts.slice(0, 10), '');
                render(opts.filter(o => o.toLowerCase().includes(q)).slice(0, 12), q);
            }

            function move(d) {
                const items = [...menu.querySelectorAll('.combo-item')];
                if (!items.length) return;
                activeIndex = (activeIndex + d + items.length) % items.length;
                items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
            }

            function select(v) {
                input.value = v;
                closeMenu();
                const qty = document.getElementById('modal-seedlings');
                if (qty && input.id === 'modal-species') qty.focus();
            }

            function closeMenu() {
                menu.style.display = 'none';
            }

            input.addEventListener('input', () => filter(input.value));
            input.addEventListener('focus', () => filter(input.value));
            input.addEventListener('keydown', e => {
                if (menu.style.display !== 'block') return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    move(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    move(-1);
                } else if (e.key === 'Enter') {
                    const act = menu.querySelector('.combo-item.active');
                    if (act) {
                        e.preventDefault();
                        select(act.textContent);
                    }
                    closeMenu();
                } else if (e.key === 'Escape') {
                    closeMenu();
                }
            });
            document.addEventListener('click', e => {
                if (!wrap.contains(e.target)) closeMenu();
            });
        }

        function removeSpecies(btn) {
            const row = btn.closest('.species-item');
            if (!row) return;
            const id = row.dataset.entryId;
            row.remove();
            const hidden = document.getElementById('species-data-container');
            if (hidden && id) hidden.querySelectorAll(`[data-entry-id="${id}"]`).forEach(n => n.remove());
        }

        document.addEventListener('DOMContentLoaded', () => {
            attachCombo(document.getElementById('species'));
            attachCombo(document.getElementById('modal-species'));

            const modal = document.getElementById('species-modal');
            const openBtn = document.getElementById('add-species-btn');
            const closeBtn = document.getElementById('close-species-modal');

            const openSpeciesModal = () => {
                document.getElementById('modal-species').value = '';
                document.getElementById('modal-seedlings').value = '0';
                modal.style.display = 'block';
                setTimeout(() => document.getElementById('modal-species').focus(), 0);
            };
            openBtn?.addEventListener('click', openSpeciesModal);
            document.getElementById('cancel-species')?.addEventListener('click', () => modal.style.display = 'none');
            closeBtn?.addEventListener('click', () => modal.style.display = 'none');

            document.getElementById('save-species')?.addEventListener('click', () => {
                const species = (document.getElementById('modal-species').value || '').trim();
                const qty = parseInt(document.getElementById('modal-seedlings').value || '0');
                if (!species || qty <= 0) {
                    showToast('error', 'Please enter valid seedling name and quantity.');
                    return;
                }
                const entryId = 'entry-' + Date.now();

                const list = document.getElementById('species-list-items');
                const row = document.createElement('div');
                row.className = 'species-item';
                row.dataset.entryId = entryId;
                row.innerHTML = `<div class="species-info"><span class="species-name">${species}</span> <span class="species-quantity">${qty} seedlings</span></div>`;
                const rm = document.createElement('button');
                rm.className = 'remove-species';
                rm.type = 'button';
                rm.textContent = 'Remove';
                rm.addEventListener('click', function() {
                    removeSpecies(this);
                });
                row.appendChild(rm);
                list.appendChild(row);

                const hidden = document.getElementById('species-data-container');
                const h1 = document.createElement('input');
                h1.type = 'hidden';
                h1.name = 'species[]';
                h1.value = species;
                h1.setAttribute('data-entry-id', entryId);
                const h2 = document.createElement('input');
                h2.type = 'hidden';
                h2.name = 'seedlings_delivered[]';
                h2.value = qty;
                h2.setAttribute('data-entry-id', entryId);
                hidden.appendChild(h1);
                hidden.appendChild(h2);

                document.getElementById('modal-species').value = '';
                document.getElementById('modal-seedlings').value = '0';
                document.getElementById('modal-species').focus();
                showToast('success', 'Added to list.');
            });

            // confirm + submit
            const form = document.getElementById('seedlings-form');
            const confirmModal = document.getElementById('confirm-modal');
            const confirmMsg = document.getElementById('confirm-msg');
            const confirmCancel = document.getElementById('confirm-cancel');
            const confirmSave = document.getElementById('confirm-save');

            form?.addEventListener('submit', e => {
                e.preventDefault();

                const mainSpecies = (document.getElementById('species').value || '').trim();
                const mainQty = parseInt(document.getElementById('seedlings-delivered').value || '0');

                const hidden = document.getElementById('species-data-container');
                const hs = hidden.querySelectorAll('input[name="species[]"]');
                const hq = hidden.querySelectorAll('input[name="seedlings_delivered[]"]');

                const items = [];
                if (mainSpecies && mainQty > 0) items.push([mainSpecies, mainQty]);
                for (let i = 0; i < hs.length && i < hq.length; i++) {
                    const s = hs[i].value;
                    const q = parseInt(hq[i].value || '0');
                    if (s && q > 0) items.push([s, q]);
                }

                const agency = (document.getElementById('agency_name').value || '').trim();
                const recby = (document.getElementById('received_by').value || '').trim();
                const date = (document.getElementById('date_received').value || '').trim();

                if (!agency || !recby || !date) {
                    showToast('error', 'Agency, Receiver, and Date are required.');
                    return;
                }
                if (!items.length) {
                    showToast('error', 'Add at least one seedling with quantity > 0.');
                    return;
                }

                confirmMsg.textContent = 'Are you sure you want to submit?';
                confirmModal.style.display = 'block';

                const onSave = async () => {
                    confirmSave.removeEventListener('click', onSave);
                    confirmSave.disabled = true;
                    showLoading('Saving…');

                    const payload = new URLSearchParams();
                    payload.append('action', 'seedlings_intake_create');
                    payload.append('agency_name', agency);
                    payload.append('received_by', recby);
                    payload.append('date_received', date);
                    if (mainSpecies && mainQty > 0) {
                        payload.append('species[]', mainSpecies);
                        payload.append('seedlings_delivered[]', String(mainQty));
                    }
                    for (let i = 0; i < hs.length && i < hq.length; i++) {
                        payload.append('species[]', hs[i].value);
                        payload.append('seedlings_delivered[]', hq[i].value);
                    }

                    try {
                        const res = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: payload.toString()
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) throw new Error(data?.message || 'Save failed.');
                        showToast('success', data.message ? data.message : `Saved ${data.inserted} record(s).`);
                        form.reset();
                        document.getElementById('species-list-items').innerHTML = '';
                        hidden.innerHTML = '';
                        confirmModal.style.display = 'none';
                    } catch (err) {
                        showToast('error', err.message || 'Save failed.');
                        confirmModal.style.display = 'none';
                    } finally {
                        hideLoading();
                        confirmSave.disabled = false;
                    }
                };
                confirmSave.addEventListener('click', onSave, {
                    once: true
                });
            });

            confirmCancel?.addEventListener('click', () => confirmModal.style.display = 'none');
            confirmModal.addEventListener('click', e => {
                if (e.target === confirmModal) confirmModal.style.display = 'none';
            });
        });

        // Header notification JS (from seedlingshome.php)
        document.addEventListener('DOMContentLoaded', function() {
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
                    // Only close if clicking outside notifDropdown AND not on other nav items
                    if (!e.target.closest('#notifDropdown') && !e.target.closest('.nav-item')) close();
                });
            }

            // Mark ALL as read
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                // optimistic UI
                document.querySelectorAll('#seedlingNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
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
            document.getElementById('seedlingNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                e.preventDefault();
                e.stopPropagation();

                const item = link.closest('.notification-item');
                const notifId = item?.getAttribute('data-notif-id') || '';
                const href = link.getAttribute('href') || '#';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
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

            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }

            // Dropdown functionality
            const dropdowns = document.querySelectorAll('[data-dropdown]');

            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');

                // Show menu on hover (desktop)
                dropdown.addEventListener('mouseenter', () => {
                    if (window.innerWidth > 992) {
                        menu.style.opacity = '1';
                        menu.style.visibility = 'visible';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(0)' :
                            'translateY(0)';
                    }
                });

                // Hide menu when leaving (desktop)
                dropdown.addEventListener('mouseleave', (e) => {
                    if (window.innerWidth > 992 && !dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });

                // Toggle menu on click (mobile)
                if (window.innerWidth <= 992) {
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        // Close other dropdowns
                        document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                            if (otherMenu !== menu) {
                                otherMenu.style.display = 'none';
                            }
                        });

                        // Toggle current dropdown
                        if (menu.style.display === 'block') {
                            menu.style.display = 'none';
                        } else {
                            menu.style.display = 'block';
                        }
                    });
                }
            });

            // Close dropdowns when clicking outside (mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-dropdown]') && window.innerWidth <= 992) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.display = 'none';
                    });
                }
            });
        });
    </script>
</body>

</html>