<?php
// superlogs.php (TOP)
declare(strict_types=1);

session_start();

// Optional: caching safety
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Gate: must be logged in and an Admin (CENRO)
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: superlogin.php');
    exit();
}

require_once __DIR__ . '/backend/connection.php'; // must expose $pdo (PDO instance)

$current_page = basename(__FILE__);
$user_id = (string)$_SESSION['user_id'];

// Verify department (CENRO)
try {
    $st = $pdo->prepare("
        SELECT department, role, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin = $me && strtolower((string)$me['role']) === 'admin';
    $isCenro = $me && strtolower((string)$me['department']) === 'cenro';

    if (!$isAdmin || !$isCenro) {
        header('Location: superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SUPERLOGS AUTH] ' . $e->getMessage());
    header('Location: superlogin.php');
    exit();
}

// =================== Notifications AJAX ===================
$notifs = [];
$unreadCount = 0;

if (isset($_GET['ajax']) && $_GET['ajax'] !== 'logs') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            $u = $pdo->prepare('
                UPDATE public.notifications
                SET is_read = true
                WHERE lower("to") = :to AND (is_read IS NULL OR is_read = false)
            ');
            $u->execute([':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_read') {
            $nid = $_GET['notif_id'] ?? '';
            if (!$nid) {
                echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
                exit();
            }
            $u = $pdo->prepare('
                UPDATE public.notifications
                SET is_read = true
                WHERE notif_id = :nid AND lower("to") = :to
            ');
            $u->execute([':nid' => $nid, ':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[SUPERLOGS NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =================== Load notifications for CENRO ===================
try {
    $ns = $pdo->prepare('
        SELECT
            notif_id,
            "from",
            "to",
            message,
            is_read,
            created_at,
            reqpro_id
        FROM public.notifications
        WHERE lower("to") = :to
        ORDER BY created_at DESC
        LIMIT 30
    ');
    $ns->execute([':to' => 'cenro']);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadCount = 0;
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[SUPERLOGS NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

// ========= Helper: time-ago in Asia/Manila =========
function time_elapsed_string($dbValue, $full = false): string
{
    $tz = new DateTimeZone('Asia/Manila');
    try {
        $dt = new DateTimeImmutable((string)$dbValue);
        if (!$dt->getTimezone()) {
            $dt = new DateTimeImmutable((string)$dbValue, new DateTimeZone('UTC'));
        }
    } catch (Throwable $e) {
        return 'just now';
    }

    $now = new DateTimeImmutable('now', $tz);
    $ago = $dt->setTimezone($tz);
    if ($ago > $now) return 'just now';

    $diff = $now->diff($ago);
    $weeks = intdiv($diff->d, 7);
    $days  = $diff->d - ($weeks * 7);

    $map = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = [];
    foreach ($map as $k => $label) {
        $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
    }
    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// =================== AJAX: fetch logs (HTML fragment) ===================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'logs') {
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $actionFilter = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "( (COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) ILIKE :q
                     OR l.action ILIKE :q
                     OR l.details ILIKE :q )";
        $params[':q'] = '%' . $q . '%';
    }
    if ($actionFilter !== '' && strtolower($actionFilter) !== 'all') {
        $where[] = "l.action = :action";
        $params[':action'] = $actionFilter;
    }

    $sql = '
        SELECT l.id,
               l.admin_user_id,
               l.admin_department,
               l.action,
               l.details,
               l.created_at,
               u.first_name,
               u.last_name
        FROM public.admin_activity_logs l
        LEFT JOIN public.users u ON u.user_id = l.admin_user_id
    ';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY l.created_at DESC LIMIT 100';

    try {
        $ls = $pdo->prepare($sql);
        $ls->execute($params);
        $rows = $ls->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[SUPERLOGS AJAX logs] ' . $e->getMessage());
        $rows = [];
    }

    // Return HTML fragment for <tbody>
    ob_start();
    if (!$rows) {
        echo '<tr><td colspan="5">No activity logs found.</td></tr>';
    } else {
        foreach ($rows as $lr) {
            $adminName = trim((string)($lr['first_name'] ?? '')) ?: (string)($lr['admin_user_id'] ?? '');
            $action = htmlspecialchars((string)($lr['action'] ?? ''), ENT_QUOTES);
            $details = htmlspecialchars((string)($lr['details'] ?? ''), ENT_QUOTES);
            $ts = htmlspecialchars((string)($lr['created_at'] ?? ''), ENT_QUOTES);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($adminName, ENT_QUOTES) . '</td>';
            echo '<td><span class="log-action">' . $action . '</span></td>';
            echo '<td>' . $details . '</td>';
            echo '<td>' . $ts . '</td>';
            echo '</tr>';
        }
    }
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// =================== Initial page render (supports direct link with GET) ===================
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$actionFilter = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "( (COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) ILIKE :q
                 OR l.action ILIKE :q
                 OR l.details ILIKE :q )";
    $params[':q'] = '%' . $q . '%';
}
if ($actionFilter !== '' && strtolower($actionFilter) !== 'all') {
    $where[] = "l.action = :action";
    $params[':action'] = $actionFilter;
}

$sql = '
    SELECT l.id,
           l.admin_user_id,
           l.admin_department,
           l.action,
           l.details,
           l.created_at,
           u.first_name,
           u.last_name
    FROM public.admin_activity_logs l
    LEFT JOIN public.users u ON u.user_id = l.admin_user_id
';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY l.created_at DESC LIMIT 100';

$logRows = [];
try {
    $ls = $pdo->prepare($sql);
    $ls->execute($params);
    $logRows = $ls->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[SUPERLOGS LOAD] ' . $e->getMessage());
    $logRows = [];
}

// ✅ Always populate actions from the TABLE (distinct), not from displayed rows
$allActions = [];
try {
    $as = $pdo->query("SELECT DISTINCT action FROM public.admin_activity_logs WHERE action IS NOT NULL AND action <> '' ORDER BY action ASC");
    $allActions = $as->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    error_log('[SUPERLOGS ACTIONS] ' . $e->getMessage());
    $allActions = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/superlogs.css">
    <style>
        :root {
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
        }

        .as-item {
            position: relative;
        }

        .as-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            background: rgb(233, 255, 242);
            color: #000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            transition: var(--as-trans);
        }

        .as-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .as-icon i {
            font-size: 1.3rem;
        }

        .as-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 300px;
            background: #fff;
            border-radius: var(--as-radius);
            box-shadow: var(--as-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--as-trans);
            padding: 0;
            z-index: 1000;
        }

        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .as-notifications {
            min-width: 350px;
            max-height: 500px;
        }

        .as-notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--as-primary);
            font-size: 1.1rem;
        }

        .as-mark-all {
            color: var(--as-primary);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--as-trans);
        }

        .as-mark-all:hover {
            color: var(--as-primary-dark);
            transform: scale(1.05);
        }

        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--as-trans);
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05);
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--as-primary);
            margin-bottom: 4px;
        }

        .as-notif-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .as-notif-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        .as-badge {
            position: absolute;
            top: 2px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ff4757;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .search-filter {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box i {
            position: absolute;
            left: 10px;
        }

        .search-input {
            padding: 10px 12px 10px 32px;
            border: 1px solid #ddd;
            border-radius: 8px;
            min-width: 260px;
        }

        .filter-dropdown select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th,
        .logs-table td {
            border-bottom: 1px solid #eee;
            padding: 10px;
            text-align: left;
        }

        .log-action {
            background: #eef7ee;
            color: #2b6625;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: .85rem;
        }

        .as-notif-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fff;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .as-view-all {
            color: var(--as-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .as-view-all:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">
            <a href="superhome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="superlogs.php" class="dropdown-item active-page">
                        <i class="fas fa-user-shield" style="color: white;"></i>
                        <span>Admin Logs</span>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge"><?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <div class="notifcontainer">
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread   = empty($n['is_read']);
                                try {
                                    $tsObj = new DateTime((string)($n['created_at'] ?? 'now'));
                                } catch (Throwable $e) {
                                    $tsObj = new DateTime('now');
                                }
                                $ts = $tsObj->getTimestamp();

                                $fromVal  = (string)($n['from'] ?? '');
                                $reqproId = $n['reqpro_id'] ?? '';

                                $title = (function ($fv) {
                                    $fv = trim((string)$fv);
                                    if (preg_match('/^register request$/i', $fv)) return 'Registration';
                                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fv)) return 'Profile update';
                                    return 'Notification';
                                })($fromVal);

                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There’s an update.';
                                })($n['message'] ?? '');

                                $timeAgo = time_elapsed_string($n['created_at']);
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        data-from="<?= htmlspecialchars($fromVal, ENT_QUOTES) ?>"
                                        data-reqpro-id="<?= htmlspecialchars((string)$reqproId, ENT_QUOTES) ?>">
                                        <div class="as-notif-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div class="as-notif-content">
                                            <div class="as-notif-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
                                            <div class="as-notif-message"><?= htmlspecialchars($cleanMsg, ENT_QUOTES) ?></div>
                                            <div class="as-notif-time" data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($timeAgo, ENT_QUOTES) ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="as-notif-footer">
                        <a href="supernotif.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'treeprofile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Admin Activity Logs</h1>

            <!-- 🔎 Live search (no buttons) -->
            <div class="search-filter" id="liveFilterBar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input
                        id="logSearchInput"
                        class="search-input"
                        type="text"
                        placeholder="Search logs by name/action/details…"
                        value="<?= htmlspecialchars($q, ENT_QUOTES) ?>"
                        autocomplete="off" />
                </div>

                <div class="filter-dropdown">
                    <select name="action" id="actionSelect">
                        <option value="all" <?= ($actionFilter === '' || strtolower($actionFilter) === 'all') ? 'selected' : '' ?>>All Actions</option>
                        <?php foreach ($allActions as $a): ?>
                            <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>"
                                <?= ($actionFilter === $a) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a, ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="logs-container">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody id="logsTbody">
                    <?php if (empty($logRows)): ?>
                        <tr>
                            <td colspan="5">No activity logs found.</td>
                        </tr>
                        <?php else: foreach ($logRows as $lr):
                            $adminName = trim((string)($lr['first_name'] ?? '')) ?: (string)($lr['admin_user_id'] ?? '');
                            $action = (string)($lr['action'] ?? '');
                            $details = (string)($lr['details'] ?? '');
                            $ts = (string)($lr['created_at'] ?? '');
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($adminName, ENT_QUOTES) ?></td>
                                <td><span class="log-action"><?= htmlspecialchars($action, ENT_QUOTES) ?></span></td>
                                <td><?= htmlspecialchars($details, ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($ts, ENT_QUOTES) ?></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <!-- <div class="pagination">
            <button type="button"><i class="fas fa-angle-left"></i></button>
            <button type="button" class="active">1</button>
            <button type="button">2</button>
            <button type="button">3</button>
            <button type="button"><i class="fas fa-angle-right"></i></button>
        </div> -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) mobileToggle.addEventListener('click', () => navContainer.classList.toggle('active'));

            // Dropdown show/hide
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

            // ===== Live logs filtering (no buttons) =====
            const input = document.getElementById('logSearchInput');
            const actionSelect = document.getElementById('actionSelect');
            const tbody = document.getElementById('logsTbody');

            function fetchLogs() {
                const q = (input?.value || '').trim();
                const action = actionSelect?.value || 'all';
                const url = `?ajax=logs&q=${encodeURIComponent(q)}&action=${encodeURIComponent(action)}`;
                fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(r => r.text())
                    .then(html => {
                        tbody.innerHTML = html;
                    })
                    .catch(() => {});
            }

            // Debounce typing
            let t = null;

            function onType() {
                clearTimeout(t);
                t = setTimeout(fetchLogs, 300);
            }

            if (input) {
                input.addEventListener('input', onType);
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            }
            if (actionSelect) {
                actionSelect.addEventListener('change', fetchLogs);
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Live "time ago" updater for notification dropdown
            function fmtAgoFromSeconds(diff) {
                if (diff < 1) return 'just now';
                const units = [
                    [365 * 24 * 3600, 'year'],
                    [30 * 24 * 3600, 'month'],
                    [7 * 24 * 3600, 'week'],
                    [24 * 3600, 'day'],
                    [3600, 'hour'],
                    [60, 'minute'],
                    [1, 'second']
                ];
                for (const [sec, label] of units) {
                    const v = Math.floor(diff / sec);
                    if (v >= 1) return `${v} ${label}${v > 1 ? 's' : ''} ago`;
                }
                return 'just now';
            }

            function refreshTimes() {
                const now = Math.floor(Date.now() / 1000);
                document.querySelectorAll('.as-notif-time[data-ts]').forEach(el => {
                    const ts = parseInt(el.getAttribute('data-ts') || '0', 10);
                    if (!Number.isFinite(ts) || ts <= 0) return;
                    const diff = now - ts;
                    el.textContent = fmtAgoFromSeconds(diff < 0 ? 0 : diff);
                });
            }

            refreshTimes();
            setInterval(refreshTimes, 60 * 1000);

            const markAll = document.getElementById('asMarkAllRead');
            if (markAll) {
                markAll.addEventListener('click', (e) => {
                    e.preventDefault();
                    fetch('?ajax=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).finally(() => location.reload());
                });
            }

            // Click a notif → mark read + route
            const list = document.querySelector('.notifcontainer');
            if (!list) return;
            const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

            list.addEventListener('click', (e) => {
                const link = e.target.closest('.as-notif-link');
                if (!link) return;
                e.preventDefault();

                const notifId = link.dataset.notifId || '';
                const fromVal = (link.dataset.from || '').trim();
                const reqproId = (link.dataset.reqproId || '').trim();

                fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(notifId), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(() => {});

                let url = 'superhome.php';
                if (/^register request$/i.test(fromVal)) {
                    url = 'superhome.php';
                } else if (uuidRe.test(fromVal)) {
                    url = (reqproId && /^\d+$/.test(reqproId)) ?
                        'supereach.php?id=' + encodeURIComponent(reqproId) :
                        'supereach.php?user=' + encodeURIComponent(fromVal);
                }
                window.location.href = url;
            });
        });
    </script>

</body>

</html>