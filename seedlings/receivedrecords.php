<?php
// receivedrecords.php — list + actions (release with live validation,
// delete with modal confirmation (only when NOT released), view details,
// creates discards/releases; updates seedlings stock; top-center notifications,
// and a loading overlay during long operations)
//
// Requires ../backend/connection.php to define $pdo (PDO for Postgres).

$current_page = basename($_SERVER['PHP_SELF']);

// ---- DB connection ----
$pdo = null;
try {
    require_once __DIR__ . '/../backend/connection.php';
} catch (Throwable $e) {
    $pdo = null;
}

// ---- AJAX HANDLERS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection not available']);
        exit;
    }

    try {
        // Mark all notifications (Seedling + Tree Cutting incidents) as read
        if ($_POST['action'] === 'notifications_mark_all_seedling_read') {
            $pdo->beginTransaction();

            // Mark all notifications addressed to Seedling as read
            $st1 = $pdo->prepare('UPDATE public.notifications SET is_read = TRUE WHERE lower("to") = :to AND is_read = FALSE');
            $st1->execute([':to' => 'seedling']);

            // Mark all incident reports with category Tree Cutting as read
            $st2 = $pdo->prepare('UPDATE public.incident_report SET is_read = TRUE WHERE lower(category) = :cat AND is_read = FALSE');
            $st2->execute([':cat' => 'tree cutting']);

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        }

        // Delete intake (and related)
        if ($_POST['action'] === 'delete_intake') {
            $intake_id = trim((string)($_POST['intake_id'] ?? ''));
            if ($intake_id === '') throw new Exception('Missing intake_id');

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM public.seedling_releases WHERE intake_id = :id")->execute([':id' => $intake_id]);
            $pdo->prepare("DELETE FROM public.seedling_discards WHERE intake_id = :id")->execute([':id' => $intake_id]);
            $pdo->prepare("DELETE FROM public.seedling_intakes  WHERE intake_id = :id")->execute([':id' => $intake_id]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        }

        // Release intake (dead -> discards, released -> releases; update seedlings stock)
        if ($_POST['action'] === 'release_intake') {
            $intake_id   = trim((string)($_POST['intake_id'] ?? ''));
            $dead_qty    = (int)($_POST['dead_qty'] ?? 0);
            $destination = trim((string)($_POST['destination'] ?? ''));

            if ($intake_id === '') throw new Exception('Missing intake_id');
            if ($dead_qty < 0) throw new Exception('Dead quantity cannot be negative');

            $q = $pdo->prepare("SELECT intake_id, agency_name, seedlings_name, seedlings_id, quantity, date_received, received_by
                                FROM public.seedling_intakes WHERE intake_id = :id LIMIT 1");
            $q->execute([':id' => $intake_id]);
            $intake = $q->fetch(PDO::FETCH_ASSOC);
            if (!$intake) throw new Exception('Intake not found');

            $received_qty = (int)$intake['quantity'];
            if ($dead_qty > $received_qty) throw new Exception('Dead quantity exceeds received quantity');

            $released_qty = $received_qty - $dead_qty;

            $pdo->beginTransaction();

            // Discards
            if ($dead_qty > 0) {
                $pdo->prepare("INSERT INTO public.seedling_discards (intake_id, discard_qty) VALUES (:iid, :q)")
                    ->execute([':iid' => $intake_id, ':q' => $dead_qty]);
            }

            // Releases + stock updates
            if ($released_qty > 0) {
                $pdo->prepare("INSERT INTO public.seedling_releases (intake_id, released_qty, destination)
                               VALUES (:iid, :q, :d)")
                    ->execute([':iid' => $intake_id, ':q' => $released_qty, ':d' => $destination]);

                $seedlings_id = $intake['seedlings_id'] ?: null;
                $seedling_name = trim((string)$intake['seedlings_name']);

                if ($seedlings_id) {
                    $pdo->prepare("UPDATE public.seedlings SET stock = COALESCE(stock,0)+:inc WHERE seedlings_id = :sid")
                        ->execute([':inc' => $released_qty, ':sid' => $seedlings_id]);
                } else {
                    // Try resolve by name (case-insensitive)
                    $sel = $pdo->prepare("SELECT seedlings_id FROM public.seedlings WHERE lower(seedling_name)=lower(:n) LIMIT 1");
                    $sel->execute([':n' => $seedling_name]);
                    $sid = $sel->fetchColumn();

                    if ($sid) {
                        $pdo->prepare("UPDATE public.seedlings SET stock = COALESCE(stock,0)+:inc WHERE seedlings_id=:sid")
                            ->execute([':inc' => $released_qty, ':sid' => $sid]);
                        $pdo->prepare("UPDATE public.seedling_intakes SET seedlings_id=:sid WHERE intake_id=:iid")
                            ->execute([':sid' => $sid, ':iid' => $intake_id]);
                    } else {
                        // create new seed record
                        $ins = $pdo->prepare("INSERT INTO public.seedlings (seedling_name, stock) VALUES (:n,:s) RETURNING seedlings_id");
                        $ins->execute([':n' => $seedling_name, ':s' => $released_qty]);
                        $sid = $ins->fetchColumn();
                        $pdo->prepare("UPDATE public.seedling_intakes SET seedlings_id=:sid WHERE intake_id=:iid")
                            ->execute([':sid' => $sid, ':iid' => $intake_id]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'released' => $released_qty, 'discarded' => $dead_qty]);
            exit;
        }

        // Details for "View"
        if ($_POST['action'] === 'intake_details') {
            $intake_id = trim((string)($_POST['intake_id'] ?? ''));
            if ($intake_id === '') throw new Exception('Missing intake_id');

            $base = $pdo->prepare("SELECT i.*, s.stock AS current_stock
                                   FROM public.seedling_intakes i
                                   LEFT JOIN public.seedlings s ON i.seedlings_id = s.seedlings_id
                                   WHERE i.intake_id = :id LIMIT 1");
            $base->execute([':id' => $intake_id]);
            $i = $base->fetch(PDO::FETCH_ASSOC);
            if (!$i) throw new Exception('Intake not found');

            // If no stock via FK, attempt by name
            if ($i['current_stock'] === null) {
                $q = $pdo->prepare("SELECT stock, seedlings_id FROM public.seedlings WHERE lower(seedling_name)=lower(:n) LIMIT 1");
                $q->execute([':n' => $i['seedlings_name']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $i['current_stock'] = $row['stock'];
                    if (!$i['seedlings_id']) $i['seedlings_id'] = $row['seedlings_id'];
                }
            }

            $dis = $pdo->prepare("SELECT discard_id, discard_qty, date_discarded
                                  FROM public.seedling_discards WHERE intake_id=:id ORDER BY date_discarded ASC");
            $dis->execute([':id' => $intake_id]);
            $discards = $dis->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $rel = $pdo->prepare("SELECT release_id, released_qty, release_date, destination
                                  FROM public.seedling_releases WHERE intake_id=:id ORDER BY release_date ASC");
            $rel->execute([':id' => $intake_id]);
            $releases = $rel->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $sum_dead = 0;
            foreach ($discards as $d) $sum_dead += (int)$d['discard_qty'];
            $sum_rel  = 0;
            foreach ($releases as $r) $sum_rel += (int)$r['released_qty'];

            echo json_encode([
                'success' => true,
                'intake' => [
                    'intake_id'      => $i['intake_id'],
                    'agency_name'    => $i['agency_name'],
                    'seedlings_name' => $i['seedlings_name'],
                    'seedlings_id'   => $i['seedlings_id'],
                    'quantity'       => (int)$i['quantity'],
                    'date_received'  => $i['date_received'],
                    'received_by'    => $i['received_by'],
                    'current_stock'  => $i['current_stock'],
                ],
                'totals' => [
                    'discarded' => $sum_dead,
                    'released'  => $sum_rel
                ],
                'discards' => $discards,
                'releases' => $releases
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ---- SAMPLE COUNTERS (used by nav) ----
$quantities = [
    'total_received' => 1250,
    'plantable_seedlings' => 980,
    'total_released' => 720,
    'total_discarded' => 150,
    'total_balance' => 380,
    'all_records' => 2150
];

// ---- FETCH INTAKES + release existence ----
$rows = [];
$relMap = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT intake_id, agency_name, seedlings_name, seedlings_id, quantity, date_received, received_by, created_at
            FROM public.seedling_intakes
            ORDER BY created_at DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $r = $pdo->query("SELECT intake_id, COUNT(*) AS cnt FROM public.seedling_releases GROUP BY intake_id");
        foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $relMap[$x['intake_id']] = (int)$x['cnt'];
        }
    } catch (Throwable $e) {
        $rows = [];
        $relMap = [];
    }
}

function canReleaseOneMonthOld(?string $date_received): bool
{
    if (!$date_received) return false;
    try {
        $d = new DateTime($date_received);
        $cut = (clone $d)->modify('+1 month');
        $now = new DateTime('today');
        return $now >= $cut;
    } catch (Throwable $e) {
        return false;
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
// helpers (for header HTML)
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
    <title>Seedlings Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f5a24;
            --primary-dark: #0a3b17;
            --white: #fff;
            --muted: #f4f6f5;
            --text: #0f172a;
            --danger: #dc2626;
            --ok: #16a34a;

            /* Header palette (from incoming.php) */
            --primary-color: #2b6625;
            --primary-color-dark: #1e4a1a;
            --light-gray: #f5f5f5;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #f5f7f6;
            color: var(--text);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, 'Helvetica Neue', Arial;
            padding-top: 100px;
            /* account for fixed header */
        }

        /* ---- page layout (existing) ---- */
        .back-icon {
            position: fixed;
            top: 80px;
            left: 22px;
            color: #111;
            text-decoration: none
        }

        .back-icon i {
            font-size: 28px
        }

        .container {
            max-width: 1220px;
            margin: 110px auto 48px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
            padding: 18px
        }

        .header {
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px
        }

        .title {
            margin: 0;
            text-align: center;
            letter-spacing: .5px
        }

        .controls {
            display: flex;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0 20px
        }

        .filter {
            display: flex;
            gap: 10px;
            align-items: center
        }

        select.filter-month,
        .filter-year,
        .search-input {
            height: 38px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px
        }

        .filter-button {
            height: 38px;
            border: 0;
            border-radius: 8px;
            background: var(--primary-dark);
            color: #fff;
            padding: 0 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer
        }

        .search {
            display: flex;
            align-items: center;
            gap: 6px
        }

        .search-input {
            width: 460px
        }

        .export {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .export-button {
            width: 40px;
            height: 38px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            cursor: pointer
        }

        .table-container {
            overflow: auto;
            border: 1px solid #dbe1e8;
            border-radius: 10px
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0
        }

        thead th {
            position: sticky;
            top: 0;
            background: var(--primary);
            color: #fff;
            padding: 14px 12px;
            text-align: left
        }

        tbody td {
            padding: 12px;
            border-top: 1px solid #e5e7eb;
            vertical-align: middle;
            background: #fff
        }

        tbody tr:nth-child(even) td {
            background: #f7faf7
        }

        .actions-cell {
            display: flex;
            gap: 8px
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            height: 34px;
            padding: 0 10px;
            cursor: pointer
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b
        }

        .btn-release {
            background: #e6fbe9;
            color: #065f46;
            border: 1px solid #a7f3d0
        }

        .btn-view {
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe
        }

        .btn-icon i {
            pointer-events: none
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: .85rem
        }

        .status-released {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0
        }

        .status-pending {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fdba74
        }

        /* Modals */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, .24);
            max-width: 560px;
            width: 92%;
            padding: 18px 18px 14px
        }

        .modal-title {
            margin: 0 0 8px;
            text-align: center
        }

        .modal-row {
            display: flex;
            gap: 12px;
            margin-top: 12px
        }

        .modal-row .field {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px
        }

        .modal-row input,
        .modal-row textarea {
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 10px
        }

        .modal-row textarea {
            height: 72px;
            padding: 10px;
            resize: vertical
        }

        .helper {
            color: #64748b
        }

        .error-text {
            color: var(--danger);
            display: none
        }

        .input-invalid {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 2px rgb(220 38 38 / .10)
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 14px
        }

        .modal-btn {
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer
        }

        .modal-cancel {
            background: #e5e7eb
        }

        .modal-ok {
            background: var(--primary);
            color: #fff
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px
        }

        .details-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px
        }

        .details-card h4 {
            margin: 0 0 8px
        }

        .notif {
            position: fixed;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 4000;
            display: none
        }

        .notif .msg {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 10px 14px;
            border-radius: 10px;
            box-shadow: 0 10px 22px rgba(0, 0, 0, .1);
            font-weight: 600
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .86);
            backdrop-filter: saturate(1.2) blur(1.5px);
            z-index: 5000;
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
            border-top-color: var(--primary);
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

        /* ===== Header from incoming.php (icons match header color) ===== */
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
            z-index: 1000;
        }

        .logo img {
            height: 45px
        }

        .mobile-toggle {
            display: none;
            background: transparent;
            border: 0;
            color: #fff;
            font-size: 20px;
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

        /* Keep icons neutral/dark (like before) */
        .nav-icon i {
            color: #111;
        }


        /* icon color = header color */
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

        /* Notifications dropdown */
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

        @media (max-width: 992px) {
            .mobile-toggle {
                display: inline-flex;
            }

            .nav-container {
                display: none;
            }

            .nav-container.active {
                display: flex;
            }

            .dropdown-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

    <!-- ===== New Header (no Incident Reports / Seedlings Request; Seedlings Received active) ===== -->
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

    <a href="incoming.php" class="back-icon"><i class="fas fa-arrow-left"></i></a>

    <div class="notif" id="notif">
        <div class="msg" id="notif-msg"></div>
    </div>

    <!-- Loading overlay -->
    <div id="loading" class="loading-overlay" role="status" aria-live="polite" aria-busy="true">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processing…</div>
    </div>

    <div class="container">
        <div class="header">
            <h1 class="title">SEEDLINGS RECEIVED</h1>
        </div>

        <div class="controls">
            <div class="filter">
                <select class="filter-month">
                    <option value="">Select Month</option>
                    <?php foreach (range(1, 12) as $m): $mm = str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $mm ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" class="filter-year" placeholder="Enter Year">
                <button class="filter-button"><i class="fas fa-filter"></i><span>Filter</span></button>
            </div>

            <div class="search">
                <input id="search" type="text" placeholder="SEARCH HERE" class="search-input">
                <i class="fas fa-search" style="font-size:18px"></i>
            </div>

            <div class="export">
                <button class="export-button" id="export-csv" title="Export as CSV"><i class="fas fa-file-export"></i></button>
                <span class="export-label">Export as CSV</span>
            </div>
        </div>

        <div class="table-container">
            <table id="intakes-table">
                <thead>
                    <tr>
                        <th>RECEIVED ID</th>
                        <th>NAME OF AGENCY/COMPANY</th>
                        <th>SEEDLING NAME</th>
                        <th>QUANTITY</th>
                        <th>DATE RECEIVED</th>
                        <th>STATUS</th>
                        <th style="width:220px">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:18px">No records found.</td>
                        </tr>
                        <?php else: foreach ($rows as $r):
                            $id  = $r['intake_id'];
                            $canRelease = canReleaseOneMonthOld($r['date_received']);
                            $alreadyReleased = isset($relMap[$id]) && $relMap[$id] > 0;
                            $statusText = $alreadyReleased ? 'Released' : 'Not Released';
                            $statusCls  = $alreadyReleased ? 'status-released' : 'status-pending';
                        ?>
                            <tr data-id="<?= htmlspecialchars($id) ?>"
                                data-name="<?= htmlspecialchars($r['seedlings_name']) ?>"
                                data-qty="<?= (int)$r['quantity'] ?>">
                                <td><?= htmlspecialchars($id) ?></td>
                                <td><?= htmlspecialchars($r['agency_name']) ?></td>
                                <td><?= htmlspecialchars($r['seedlings_name']) ?></td>
                                <td><?= (int)$r['quantity'] ?></td>
                                <td><?= htmlspecialchars($r['date_received']) ?></td>
                                <td class="status-cell"><span class="status-badge <?= $statusCls ?>"><?= $statusText ?></span></td>
                                <td class="actions-cell">
                                    <?php if ($alreadyReleased): ?>
                                        <button class="btn-icon btn-view" title="View"><i class="fas fa-eye"></i>&nbsp;View</button>
                                    <?php elseif ($canRelease): ?>
                                        <button class="btn-icon btn-release" title="Release"><i class="fas fa-leaf"></i>&nbsp;Release</button>
                                    <?php endif; ?>
                                    <?php if (!$alreadyReleased): ?>
                                        <button class="btn-icon btn-delete" title="Delete"><i class="fas fa-trash"></i>&nbsp;Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Release Modal -->
    <div class="modal" id="release-modal" aria-hidden="true">
        <div class="modal-content">
            <h2 class="modal-title">Release Seedlings</h2>
            <div id="release-info" style="font-weight:600"></div>

            <div class="modal-row">
                <div class="field">
                    <label for="dead_qty">How many died?</label>
                    <input type="number" id="dead_qty" min="0" value="0" />
                    <small id="dead_help" class="helper"></small>
                    <small id="dead_err" class="error-text"></small>
                </div>
            </div>

            <div class="modal-row">
                <div class="field">
                    <label for="destination">Destination (optional)</label>
                    <textarea id="destination" placeholder="Where are you releasing these seedlings?"></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <button class="modal-btn modal-cancel" id="rel-cancel">Cancel</button>
                <button class="modal-btn modal-ok" id="rel-ok">Release</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal" id="delete-modal" aria-hidden="true">
        <div class="modal-content">
            <h2 class="modal-title">Delete Intake</h2>
            <p id="delete-msg" style="margin:6px 0 0">This will also remove related releases/discards.</p>
            <div class="modal-actions">
                <button class="modal-btn modal-cancel" id="del-cancel">Cancel</button>
                <button class="modal-btn modal-ok" id="del-ok">Delete</button>
            </div>
        </div>
    </div>

    <!-- Details (View) Modal -->
    <div class="modal" id="details-modal" aria-hidden="true">
        <div class="modal-content" style="max-width:760px">
            <h2 class="modal-title">Seedling Intake Details</h2>
            <div id="details-body"></div>
            <div class="modal-actions">
                <button class="modal-btn modal-ok" id="det-close">Close</button>
            </div>
        </div>
    </div>

    <script>
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

                        document.querySelectorAll('.notifications-dropdown .notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                            const icon = item.querySelector('.notification-icon i');
                            if (icon) {
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                            }
                        });
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    } catch {
                        /* silent */
                    }
                });
            }
        });

        // ===== Page scripts below =====
        const table = document.getElementById('intakes-table');
        const notif = (msg) => {
            const n = document.getElementById('notif'),
                t = document.getElementById('notif-msg');
            t.textContent = msg;
            n.style.display = 'block';
            setTimeout(() => n.style.display = 'none', 3500);
        };

        // Loading overlay helpers
        const loaderEl = document.getElementById('loading');

        function showLoading(txt = 'Processing…') {
            if (!loaderEl) return;
            loaderEl.querySelector('.loading-text').textContent = txt;
            loaderEl.style.display = 'flex';
        }

        function hideLoading() {
            if (!loaderEl) return;
            loaderEl.style.display = 'none';
        }

        // Search
        document.getElementById('search')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(tr => {
                const t = tr.innerText.toLowerCase();
                tr.style.display = t.includes(q) ? '' : 'none';
            });
        });

        // Export CSV
        document.getElementById('export-csv')?.addEventListener('click', () => {
            const rows = [...table.querySelectorAll('tr')]
                .map(tr => [...tr.children].map(td => `"${td.innerText.replace(/"/g,'""')}"`).join(','))
                .join('\n');
            const blob = new Blob([rows], {
                type: 'text/csv;charset=utf-8;'
            });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'seedling_intakes.csv';
            a.click();
        });

        // ---------- RELEASE MODAL ----------
        const releaseModal = document.getElementById('release-modal');
        const relOk = document.getElementById('rel-ok');
        const deadInput = document.getElementById('dead_qty');
        const deadHelp = document.getElementById('dead_help');
        const deadErr = document.getElementById('dead_err');
        let currentRow = null;

        function setDeadError(msg) {
            if (msg) {
                deadErr.textContent = msg;
                deadErr.style.display = 'block';
                deadInput.classList.add('input-invalid');
                relOk.disabled = true;
            } else {
                deadErr.textContent = '';
                deadErr.style.display = 'none';
                deadInput.classList.remove('input-invalid');
                relOk.disabled = false;
            }
        }

        function validateDead() {
            if (!currentRow) return false;
            const max = parseInt(currentRow.dataset.qty || '0', 10);
            const val = parseInt(deadInput.value || '0', 10);
            if (isNaN(val) || val < 0) {
                setDeadError('Dead quantity cannot be negative.');
                return false;
            }
            if (val > max) {
                setDeadError(`Dead quantity cannot exceed received (${max}).`);
                return false;
            }
            setDeadError('');
            return true;
        }
        table.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-release');
            if (!btn) return;
            currentRow = btn.closest('tr');
            const name = currentRow.dataset.name;
            const qty = parseInt(currentRow.dataset.qty || '0', 10);
            document.getElementById('release-info').textContent = `${name} — Received: ${qty}`;
            deadInput.value = 0;
            deadInput.setAttribute('max', String(qty));
            deadHelp.textContent = `Max ${qty}`;
            document.getElementById('destination').value = '';
            setDeadError('');
            validateDead();
            releaseModal.style.display = 'flex';
            deadInput.focus();
        });
        deadInput.addEventListener('input', validateDead);
        document.getElementById('rel-cancel')?.addEventListener('click', () => {
            releaseModal.style.display = 'none';
            currentRow = null;
        });
        releaseModal.addEventListener('click', (e) => {
            if (e.target === releaseModal) {
                releaseModal.style.display = 'none';
                currentRow = null;
            }
        });
        document.getElementById('rel-ok')?.addEventListener('click', async () => {
            if (!currentRow || !validateDead()) return;
            const id = currentRow.dataset.id;
            const dead = parseInt(deadInput.value || '0', 10);
            const dest = document.getElementById('destination').value.trim();

            try {
                showLoading('Releasing…');
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'release_intake',
                        intake_id: id,
                        dead_qty: String(dead),
                        destination: dest
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data?.message || 'Release failed');
                releaseModal.style.display = 'none';
                notif(`Released successfully. Discarded ${data.discarded || 0}, released ${data.released || 0}.`);

                const statusCell = currentRow.querySelector('.status-cell');
                statusCell.innerHTML = '<span class="status-badge status-released">Released</span>';

                const cell = currentRow.querySelector('.actions-cell');
                cell.innerHTML = '';
                const viewBtn = document.createElement('button');
                viewBtn.className = 'btn-icon btn-view';
                viewBtn.innerHTML = '<i class="fas fa-eye"></i>&nbsp;View';
                cell.appendChild(viewBtn);
            } catch (err) {
                notif(err.message || 'Release failed.');
            } finally {
                hideLoading();
            }
        });

        // ---------- DELETE CONFIRM MODAL ----------
        const delModal = document.getElementById('delete-modal');
        const delMsg = document.getElementById('delete-msg');
        let delRow = null;
        table.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-delete');
            if (!btn) return;
            delRow = btn.closest('tr');
            if (!delRow) return;
            const name = delRow.dataset.name || 'this intake';
            delMsg.textContent = `Delete "${name}"? This will also remove related releases/discards.`;
            delModal.style.display = 'flex';
        });
        document.getElementById('del-cancel')?.addEventListener('click', () => {
            delModal.style.display = 'none';
            delRow = null;
        });
        delModal.addEventListener('click', (e) => {
            if (e.target === delModal) {
                delModal.style.display = 'none';
                delRow = null;
            }
        });
        document.getElementById('del-ok')?.addEventListener('click', async () => {
            if (!delRow) return;
            const id = delRow.dataset.id;
            try {
                showLoading('Deleting…');
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete_intake',
                        intake_id: id
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data?.message || 'Delete failed');
                delRow.remove();
                delModal.style.display = 'none';
                delRow = null;
                notif('Intake deleted.');
            } catch (err) {
                delModal.style.display = 'none';
                delRow = null;
                notif(err.message || 'Delete failed.');
            } finally {
                hideLoading();
            }
        });

        // ---------- VIEW DETAILS MODAL ----------
        const detModal = document.getElementById('details-modal');
        const detBody = document.getElementById('details-body');
        document.getElementById('det-close')?.addEventListener('click', () => detModal.style.display = 'none');
        detModal.addEventListener('click', (e) => {
            if (e.target === detModal) detModal.style.display = 'none';
        });

        table.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-view');
            if (!btn) return;
            const tr = btn.closest('tr');
            const id = tr.dataset.id;

            try {
                showLoading('Loading details…');
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'intake_details',
                        intake_id: id
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data?.message || 'Load failed');

                const i = data.intake;
                const totals = data.totals || {
                    discarded: 0,
                    released: 0
                };
                const discards = data.discards || [];
                const releases = data.releases || [];
                const stock = (i.current_stock === null || typeof i.current_stock === 'undefined') ? '—' : i.current_stock;

                const discList = discards.length ?
                    '<ul style="margin:6px 0 0 18px">' + discards.map(d => `<li>${d.date_discarded} — ${d.discard_qty}</li>`).join('') + '</ul>' :
                    '<div style="color:#64748b">No discards</div>';

                const relList = releases.length ?
                    '<ul style="margin:6px 0 0 18px">' + releases.map(r => `<li>${r.release_date} — ${r.released_qty}${r.destination ? ' <em style="color:#64748b">('+r.destination+')</em>' : ''}</li>`).join('') + '</ul>' :
                    '<div style="color:#64748b">No releases</div>';

                detBody.innerHTML = `
          <div class="details-grid">
            <div class="details-card">
              <h4>Intake</h4>
              <div><strong>ID:</strong> ${i.intake_id}</div>
              <div><strong>Agency/Company:</strong> ${i.agency_name}</div>
              <div><strong>Seedling:</strong> ${i.seedlings_name}</div>
              <div><strong>Received Qty:</strong> ${i.quantity}</div>
              <div><strong>Date Received:</strong> ${i.date_received}</div>
              <div><strong>Status:</strong> <span class="status-badge ${totals.released>0 ? 'status-released':'status-pending'}">${totals.released>0?'Released':'Not Released'}</span></div>
            </div>
            <div class="details-card">
              <h4>Current Stock (Seedlings table)</h4>
              <div style="font-size:22px;font-weight:700">${stock}</div>
            </div>
          </div>

          <div class="details-grid" style="margin-top:10px">
            <div class="details-card">
              <h4>Discards <span style="color:#64748b">(Total: ${totals.discarded})</span></h4>
              ${discList}
            </div>
            <div class="details-card">
              <h4>Releases <span style="color:#64748b">(Total: ${totals.released})</span></h4>
              ${relList}
            </div>
          </div>
        `;
                detModal.style.display = 'flex';
            } catch (err) {
                notif(err.message || 'Unable to load details.');
            } finally {
                hideLoading();
            }
        });
    </script>
</body>

</html>