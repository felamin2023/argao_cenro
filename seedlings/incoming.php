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
// AJAX: mark all notifications as read (Seedling + Tree Cutting incidents)
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'notifications_mark_all_seedling_read') {
    header('Content-Type: application/json');
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection not available']);
        exit;
    }
    try {
        $pdo->beginTransaction();

        // Mark all notifications addressed to Seedling as read
        $st1 = $pdo->prepare('UPDATE public.notifications SET is_read = TRUE WHERE lower("to") = :to AND is_read = FALSE');
        $st1->execute([':to' => 'seedling']);

        // Mark all incident reports with category Tree Cutting as read
        $st2 = $pdo->prepare('UPDATE public.incident_report SET is_read = TRUE WHERE lower(category) = :cat AND is_read = FALSE');
        $st2->execute([':cat' => 'tree cutting']);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not update notifications']);
    }
    exit;
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

        // If column is still UUID (old), warn early (we’ll still try best-effort)
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
// Header notifications (merge: Seedling + Tree Cutting incidents)
// -------------------------
$seedlingNotifs = [];
$unreadSeedlingCount = 0;

$treeIncidents = [];
$unreadIncidentsCount = 0;

if ($pdo) {
    // Seedling notifications (public.notifications WHERE to='Seedling')
    try {
        $st = $pdo->prepare('
            SELECT notif_id, message, is_read, created_at
            FROM public.notifications
            WHERE lower("to") = :to
            ORDER BY created_at DESC
            LIMIT 20
        ');
        $st->execute([':to' => 'seedling']);
        $seedlingNotifs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM public.notifications WHERE lower("to") = :to AND is_read = FALSE');
        $cntStmt->execute([':to' => 'seedling']);
        $unreadSeedlingCount = (int)$cntStmt->fetchColumn();
    } catch (Throwable $e) {
        $seedlingNotifs = [];
        $unreadSeedlingCount = 0;
    }

    // Incident reports (public.incident_report WHERE category='Tree Cutting')
    try {
        $st2 = $pdo->prepare('
            SELECT incident_id, what, more_description, is_read, created_at, status
            FROM public.incident_report
            WHERE lower(category) = :cat
            ORDER BY created_at DESC
            LIMIT 20
        ');
        $st2->execute([':cat' => 'tree cutting']);
        $treeIncidents = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cnt2 = $pdo->prepare('SELECT COUNT(*) FROM public.incident_report WHERE lower(category) = :cat AND is_read = FALSE');
        $cnt2->execute([':cat' => 'tree cutting']);
        $unreadIncidentsCount = (int)$cnt2->fetchColumn();
    } catch (Throwable $e) {
        $treeIncidents = [];
        $unreadIncidentsCount = 0;
    }
}

// Merge & sort (newest first)
$allNotifs = [];

// map seedling notifications
foreach ($seedlingNotifs as $n) {
    $allNotifs[] = [
        'source'     => 'seedling',
        'id'         => $n['notif_id'],
        'title'      => 'Notification',
        'message'    => (string)$n['message'],
        'is_read'    => (bool)$n['is_read'],
        'created_at' => (string)$n['created_at'],
    ];
}

// map incident reports
foreach ($treeIncidents as $r) {
    $msg = $r['what'] ?: $r['more_description'] ?: 'New Tree Cutting incident.';
    $status = $r['status'] ? (' [' . strtoupper((string)$r['status']) . ']') : '';
    $allNotifs[] = [
        'source'     => 'incident',
        'id'         => $r['incident_id'],
        'title'      => 'Incident Report',
        'message'    => (string)$msg . $status,
        'is_read'    => (bool)$r['is_read'],
        'created_at' => (string)$r['created_at'],
    ];
}

// sort by created_at desc
usort($allNotifs, function ($a, $b) {
    return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});

$unreadTotal = $unreadSeedlingCount + $unreadIncidentsCount;

// -------------------------
// helpers
// -------------------------
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_dt($ts)
{
    if (!$ts) return '';
    try {
        $d = new DateTime($ts);
        return $d->format('M d, Y g:i A');
    } catch (Throwable $e) {
        return (string)$ts;
    }
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
            cursor: pointer
        }

        .view-records-button {
            background: #00796b
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

        /* Simple notification list layout inside dropdown */
        .notifications-dropdown {
            padding: 10px 0;
        }

        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-bottom: 1px solid #eee;
        }

        .notification-item {
            padding: 12px 16px;
        }

        .notification-item.unread {
            background: #f6fff6;
        }

        .notification-link {
            display: flex;
            gap: 12px;
            text-decoration: none;
            color: #222;
        }

        .notification-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef7ee;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: .95rem;
        }

        .notification-time {
            font-size: .85rem;
            color: #666;
            margin-top: 2px;
        }

        .notification-footer {
            border-top: 1px solid #eee;
            padding: 10px 16px;
            text-align: center;
        }

        .notification-footer .view-all {
            text-decoration: none;
            color: #0b6;
            font-weight: 600;
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
                        <span class="quantity-badge"><?= (int)$quantities['total_received']; ?></span>
                    </a>

                    <a href="releasedrecords.php" class="dropdown-item">
                        <i class="fas fa-truck"></i>
                        <span class="item-text">Seedlings Released</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_released']; ?></span>
                    </a>

                    <a href="discardedrecords.php" class="dropdown-item">
                        <i class="fas fa-trash-alt"></i>
                        <span class="item-text">Seedlings Discarded</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_discarded']; ?></span>
                    </a>

                    <a href="balancerecords.php" class="dropdown-item">
                        <i class="fas fa-calculator"></i>
                        <span class="item-text">Seedlings Left</span>
                        <span class="quantity-badge"><?= (int)$quantities['total_balance']; ?></span>
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

            <!-- Notifications (Seedling + Tree Cutting) -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge" style="<?= ($unreadTotal > 0) ? '' : 'display:none;' ?>"><?= (int)$unreadTotal ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <?php if (count($allNotifs) === 0): ?>
                        <div class="notification-item">
                            <div class="notification-link" href="javascript:void(0)">
                                <div class="notification-icon">
                                    <i class="far fa-bell"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">No notifications</div>
                                    <div class="notification-message">You’re all caught up.</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allNotifs as $it): ?>
                            <?php $iconClass = $it['is_read'] ? 'far fa-bell' : 'fas fa-bell'; ?>
                            <div class="notification-item <?= $it['is_read'] ? '' : 'unread' ?>" data-id="<?= e($it['id']) ?>" data-src="<?= e($it['source']) ?>">
                                <a class="notification-link" href="javascript:void(0)">
                                    <div class="notification-icon">
                                        <i class="<?= $iconClass ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= e($it['title']) ?></div>
                                        <div class="notification-message"><?= e($it['message']) ?></div>
                                        <div class="notification-time"><?= e(fmt_dt($it['created_at'])) ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="notification-footer">
                        <a href="seedlingsnotification.php" class="view-all">View All Notifications</a>
                    </div>
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

        // Header-only JS (dropdowns + mobile toggle + mark-all-read)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) mobileToggle.addEventListener('click', () => navContainer.classList.toggle('active'));

            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dd => {
                const toggle = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');

                dd.addEventListener('mouseenter', () => {
                    if (window.innerWidth > 992) {
                        menu.style.opacity = '1';
                        menu.style.visibility = 'visible';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                    }
                });
                dd.addEventListener('mouseleave', e => {
                    if (window.innerWidth > 992 && !dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });

                if (window.innerWidth <= 992 && toggle) {
                    toggle.addEventListener('click', e => {
                        e.preventDefault();
                        e.stopPropagation();
                        document.querySelectorAll('.dropdown-menu').forEach(m => {
                            if (m !== menu) m.style.display = 'none';
                        });
                        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                    });
                }
            });

            document.addEventListener('click', e => {
                if (!e.target.closest('.dropdown') && window.innerWidth <= 992) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => menu.style.display = 'none');
                }
            });

            const badge = document.querySelector('.nav-item .badge');
            const markAll = document.querySelector('.mark-all-read');
            if (markAll) {
                markAll.addEventListener('click', async e => {
                    e.preventDefault();
                    try {
                        const body = new URLSearchParams();
                        body.append('action', 'notifications_mark_all_seedling_read');
                        const res = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: body.toString()
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) throw new Error('Failed to mark all as read');

                        // Update UI
                        document.querySelectorAll('.notifications-dropdown .notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                            const icon = item.querySelector('.notification-icon i');
                            if (icon) {
                                icon.classList.remove('fas');
                                icon.classList.add('far'); // switch to outline bell when marked read
                            }
                        });
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    } catch {
                        // keep header minimal; no toast
                    }
                });
            }
        });
    </script>
</body>

</html>