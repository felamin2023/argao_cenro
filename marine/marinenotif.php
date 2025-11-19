<?php

declare(strict_types=1);
session_start();

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // provides $pdo

// Helpers
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
        // DB timestamps are stored in UTC. Parse as UTC then convert to Manila
        try {
            $ago = new DateTime($datetime, new DateTimeZone('UTC'));
            $ago->setTimezone(new DateTimeZone('Asia/Manila'));
        } catch (Exception $e) {
            $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        }
        $diff = $now->diff($ago);

        $totalDays = $diff->days ?? 0;
        $weeks = intdiv($totalDays, 7);
        $days  = $totalDays % 7;

        $parts = [];
        $map = [
            'y' => $diff->y,
            'm' => $diff->m,
            'w' => $weeks,
            'd' => $days,
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s,
        ];
        foreach ($map as $label => $v) {
            if ($v > 0) {
                $name = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'][$label];
                $parts[] = $v . ' ' . $name . ($v > 1 ? 's' : '');
            }
        }
        if (!$full) $parts = array_slice($parts, 0, 1);
        return $parts ? implode(', ', $parts) . ' ago' : 'just now';
    }
}

/* ===== AJAX endpoints (mark read / mark all) ===== */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['ajax'] === 'mark_read') {
            $notifId    = $_POST['notif_id'] ?? '';
            $incidentId = $_POST['incident_id'] ?? '';
            if (!$notifId && !$incidentId) {
                echo json_encode(['ok' => false, 'error' => 'missing ids']);
                exit;
            }
            if ($notifId) {
                $st = $pdo->prepare("UPDATE public.notifications SET is_read = true WHERE notif_id = :id");
                $st->execute([':id' => $notifId]);
            }
            if ($incidentId) {
                $st = $pdo->prepare("UPDATE public.incident_report SET is_read = true WHERE incident_id = :id");
                $st->execute([':id' => $incidentId]);
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_all_read') {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE public.notifications SET is_read = true WHERE LOWER(COALESCE(\"to\", ''))='marine' AND is_read=false");
            $pdo->exec("UPDATE public.incident_report SET is_read = true WHERE LOWER(COALESCE(category,''))='marine' AND is_read=false");
            $pdo->commit();
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'unknown action']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[MARINE NOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

// Handle AJAX actions (mark read / mark all read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)$_POST['action'];
    try {
        if ($action === 'mark_read' && isset($_POST['type'], $_POST['id'])) {
            $type = $_POST['type'] === 'incident' ? 'incident' : 'permit';
            $id   = $_POST['id'];
            if ($type === 'permit') {
                $st = $pdo->prepare('UPDATE public.notifications SET is_read = true WHERE notif_id = :id');
                $st->execute([':id' => $id]);
            } else {
                $st = $pdo->prepare('UPDATE public.incident_report SET is_read = true WHERE incident_id = :id');
                $st->execute([':id' => $id]);
            }
            echo json_encode(['ok' => true]);
            exit();
        }

        if ($action === 'mark_all_read') {
            // notifications addressed to 'marine'
            $pdo->exec("UPDATE public.notifications SET is_read = true WHERE LOWER(COALESCE(\"to\", '')) = 'marine'");
            $pdo->exec("UPDATE public.incident_report SET is_read = true WHERE LOWER(COALESCE(category,''))='marine'");
            echo json_encode(['ok' => true]);
            exit();
        }

        if ($action === 'get_details' && isset($_POST['type'], $_POST['id'])) {
            $type = $_POST['type'] === 'incident' ? 'incident' : 'permit';
            $id   = $_POST['id'];
            if ($type === 'permit') {
                $st = $pdo->prepare('SELECT n.notif_id, n.message, n.created_at, a.approval_id, a.permit_type, a.approval_status, a.request_type, c.first_name, c.last_name
                    FROM public.notifications n
                    LEFT JOIN public.approval a ON a.approval_id = n.approval_id
                    LEFT JOIN public.client c ON c.client_id = a.client_id
                    WHERE n.notif_id = :id LIMIT 1');
                $st->execute([':id' => $id]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $st = $pdo->prepare('SELECT incident_id, what, more_description, created_at, status, is_read FROM public.incident_report WHERE incident_id = :id LIMIT 1');
                $st->execute([':id' => $id]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            echo json_encode(['ok' => true, 'data' => $row]);
            exit();
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Fetch notifications + incidents for display
$marineNotifs = [];
$incRows    = [];
$unreadMarine = 0;
try {
    $notifRows = $pdo->query("SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               n.incident_id,
               n.reqpro_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name  AS client_first, c.last_name AS client_last
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'marine'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 500");
    $marineNotifs = $notifRows ? $notifRows->fetchAll(PDO::FETCH_ASSOC) : [];

    $unreadPermits = (int)$pdo->query("SELECT COUNT(*) FROM public.notifications n WHERE LOWER(COALESCE(n.\"to\", ''))='marine' AND n.is_read=false")->fetchColumn();
    $unreadMarine = $unreadPermits;
} catch (Throwable $e) {
    error_log('[MARINE NOTIFS] ' . $e->getMessage());
}

$combined = [];
foreach ($marineNotifs as $nf) {
    $isRead = ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1');
    $hasIncident = !empty($nf['incident_id']);
    $hasApproval = !empty($nf['approval_id']);
    $hasReqpro   = !empty($nf['reqpro_id']);
    $titleMsg = trim((string)$nf['message'] ?: ((($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a request.'));

    // Route based on incident_id or reqpro_id
    $link = $hasIncident
        ? 'reportaccident.php?focus=' . urlencode((string)$nf['incident_id'])
        : ($hasReqpro
            ? 'marineprofile.php?reqpro_id=' . urlencode((string)$nf['reqpro_id'])
            : ($hasApproval
                ? 'mpa-management.php?approval_id=' . urlencode((string)$nf['approval_id'])
                : 'marinenotif.php'));

    $combined[] = [
        'id'         => $nf['notif_id'],
        'is_read'    => $isRead,
        'type'       => $hasIncident ? 'incident' : 'permit',
        'message'    => $titleMsg,
        'ago'        => time_elapsed_string($nf['created_at'] ?? date('c')),
        'created_at' => $nf['created_at'] ?? null,
        'raw'        => $nf,
        'link'       => $link
    ];
}

// sort newest first by created_at (if present)
usort($combined, function ($a, $b) {
    $ta = $a['created_at'] ?? '';
    $tb = $b['created_at'] ?? '';
    return strcmp($tb, $ta);
});

$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine | Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Use the same CSS as wildlife notification to match design -->
    <link rel="stylesheet" href="/denr/superadmin/css/wildnotification.css">
</head>
<style>
    .nav-item .badge {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 15px;
        height: 15px;
        background: #e74c3c;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        line-height: 1;
        padding: 0;
        box-sizing: border-box;
        text-align: center;
        /* ensures a white ring when over light icon box */
    }

    .nav-item.dropdown.open .badge {
        display: none;
    }

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
        display: flex;
        font-weight: 700;
        color: #1b5e20;
        margin-bottom: 6px;
        width: 34%;
    }

    .notification-time {
        color: #6b7280;
        font-size: .9rem;
        padding: 0px 5px;
    }

    .notification-message {
        color: #234;
        text-decoration: none;
    }

    .view-link {
        text-decoration: none;
        color: inherit;
    }

    .mark-all-button {
        padding: 10px;
    }
</style>

<body>

    <header>
        <div class="logo">
            <a href="marinehome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Mobile menu toggle -->
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">

                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadMarine ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="marineNotifList">
                        <?php
                        if (empty($combined)): ?>
                            <div class="notification-item"><span>No marine notifications</span></div>
                            <?php else: foreach ($combined as $item): ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>" data-notif-id="<?= h((string)$item['id']) ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <i class="fas <?= $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell' ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= !empty($item['raw']['incident_id']) ? 'Incident report' : (!empty($item['raw']['reqpro_id']) ? 'Profile update' : 'Marine Request') ?></div>
                                            <div class="notification-message"><?= h((string)$item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="marinenotif.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'marineprofile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="marineprofile.php" class="dropdown-item">
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

    <!-- Notifications Content -->
    <div class="notifications-container">
        <div class="notifications-header">NOTIFICATIONS</div>

        <div class="notification-tabs">
            <div id="all-tab" class="tab active">All Notifications</div>
            <div id="unread-tab" class="tab">Unread <span class="tab-badge"><?= (int)$unreadMarine ?></span></div>
        </div>

        <div id="all-notifications" class="notification-list">
            <?php if (empty($combined)): ?>
                <div class="notification-item">
                    <div class="notification-title">No notifications</div>
                    <div class="notification-message">You're all caught up.</div>
                </div>
                <?php else:
                foreach ($combined as $nf):
                    $isUnread = !$nf['is_read'];
                    $icon = $nf['type'] === 'incident' ? 'fa-exclamation-triangle' : 'fa-bell';
                    $href = $nf['link'] ?? '#';
                ?>
                    <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" data-notif-id="<?= h((string)($nf['raw']['notif_id'] ?? $nf['id'])) ?>" data-incident-id="<?= h((string)($nf['raw']['incident_id'] ?? '')) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas <?= $icon ?>"></i></div>
                            <?= h((string)$nf['message']) ?>
                        </div>
                        <div class="notification-content"><a href="<?= h($href) ?>" class="view-link"><?= h((string)$nf['message']) ?></a></div>
                        <div class="notification-time"><?= h($nf['ago']) ?></div>
                        <div class="notification-actions">
                            <a href="<?= h($href) ?>" class="action-button view-link">View Details</a>
                            <?php if ($isUnread): ?>
                                <button class="action-button mark-read-btn">Mark as Read</button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>

        <div id="unread-notifications" class="notification-list" style="display: none;">
            <?php
            $hasUnread = false;
            foreach ($combined as $nf) {
                if (!$nf['is_read']) {
                    $hasUnread = true;
                    $icon = $nf['type'] === 'incident' ? 'fa-exclamation-triangle' : 'fa-bell';
                    $href = $nf['link'] ?? '#';
            ?>
                    <div class="notification-item unread" data-notif-id="<?= h((string)($nf['raw']['notif_id'] ?? $nf['id'])) ?>" data-incident-id="<?= h((string)($nf['raw']['incident_id'] ?? '')) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas <?= $icon ?>"></i></div>
                            <?= h((string)$nf['message']) ?>
                        </div>
                        <div class="notification-content"><a href="<?= h($href) ?>" class="view-link"><?= h((string)$nf['message']) ?></a></div>
                        <div class="notification-time"><?= h($nf['ago']) ?></div>
                        <div class="notification-actions">
                            <a href="<?= h($href) ?>" class="action-button view-link">View Details</a>
                            <button class="action-button mark-read-btn">Mark as Read</button>
                        </div>
                    </div>
            <?php }
            }
            if (!$hasUnread) {
                echo '<div class="notification-item"><div class="notification-title">No unread notifications</div></div>';
            }
            ?>
        </div>

        <div class="mark-all-button">
            <button id="mark-all-read-main">✓ Mark all as read</button>
        </div>
    </div>

    <!-- Modal for Notification Details -->
    <div class="modal" id="notification-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h2>Marine Notification Details</h2>
            </div>
            <div class="modal-body">
                <p><strong>Category:</strong> Marine Notification</p>
                <p><strong>Received:</strong> Details loading...</p>
            </div>
            <div class="modal-footer">
                <button class="action-button">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const NOTIF_ENDPOINT = '<?php echo basename(__FILE__); ?>';

            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => navContainer.classList.toggle('active'));

            // Minimal dropdown open/close for header notif
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
                // also open on hover and close when mouse leaves the dropdown
                dd.addEventListener('mouseenter', () => {
                    if (!dd.classList.contains('open')) open();
                });
                dd.addEventListener('mouseleave', () => {
                    if (dd.classList.contains('open')) close();
                });
                trigger?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (dd.classList.contains('open')) close();
                    else open();
                });
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#notifDropdown')) close();
                });
            }

            // Helpers for UI updates
            function setNavBadge(n) {
                const b = document.querySelector('#notifDropdown .badge');
                if (!b) return;
                b.textContent = String(n);
                if (n <= 0) b.style.display = 'none';
                else b.style.display = 'inline-block';
            }

            function setTabBadge(n) {
                const b = document.querySelector('.tab-badge');
                if (!b) return;
                b.textContent = String(n);
                if (n <= 0) b.style.display = 'none';
                else b.style.display = 'inline-flex';
            }

            // reset dropdown & trigger state so bell icon isn't visually stuck
            function resetNotifDropdown() {
                try {
                    if (document.activeElement && typeof document.activeElement.blur === 'function') {
                        document.activeElement.blur();
                    }
                    if (!dd) return;
                    dd.classList.remove('open', 'active');
                    const navItemAncestor = dd.closest('.nav-item');
                    if (navItemAncestor) navItemAncestor.classList.remove('open', 'active');

                    const triggerEl = dd.querySelector('.nav-icon');
                    if (triggerEl) {
                        triggerEl.classList.remove('active');
                        triggerEl.setAttribute('aria-expanded', 'false');
                    }

                    const menu = dd.querySelector('.dropdown-menu');
                    if (menu) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                    }
                } catch (_) {}
            }

            // Optimistic mark all as read (header)
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('.notification-item.unread').forEach(el => {
                    el.classList.remove('unread');
                    try {
                        el.style.backgroundColor = '';
                    } catch (_) {}
                });
                document.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                const unreadList = document.getElementById('unread-notifications');
                if (unreadList) unreadList.innerHTML = '<div class="notification-item"><div class="notification-title">No unread notifications</div></div>';
                setNavBadge(0);
                setTabBadge(0);
                try {
                    const res = await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {});
                    const json = await res.json();
                    if (!json || json.ok !== true) location.reload();
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
                } catch (_) {
                    location.reload();
                }
            });

            // Mark all from main content
            document.getElementById('mark-all-read-main')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('.notification-item.unread').forEach(el => {
                    el.classList.remove('unread');
                    try {
                        el.style.backgroundColor = '';
                    } catch (_) {}
                });
                document.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                const unreadListMain = document.getElementById('unread-notifications');
                if (unreadListMain) unreadListMain.innerHTML = '<div class="notification-item"><div class="notification-title">No unread notifications</div></div>';
                setNavBadge(0);
                setTabBadge(0);
                try {
                    await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {});
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
                } catch (_) {}
            });

            // Delegate click on view links (mark read then follow)
            document.getElementById('all-notifications')?.addEventListener('click', async (ev) => {
                const link = ev.target.closest('.view-link');
                if (!link) return;
                ev.preventDefault();
                const item = link.closest('.notification-item');
                if (!item) return;
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';
                const href = link.getAttribute('href') || '#';
                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);
                    await fetch(`${NOTIF_ENDPOINT}?ajax=mark_read`, {
                        method: 'POST',
                        body: form.toString()
                    });
                } catch (_) {}
                if (notifId) {
                    const sel = `.notification-item[data-notif-id="${notifId}"]`;
                    document.querySelectorAll(sel).forEach(el => {
                        el.classList.remove('unread');
                        el.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                    });
                    document.querySelectorAll(`#unread-notifications .notification-item[data-notif-id="${notifId}"]`).forEach(el => el.remove());

                    const unreadEls = Array.from(document.querySelectorAll('.notification-item.unread[data-notif-id]'));
                    const ids = new Set(unreadEls.map(e => e.getAttribute('data-notif-id')));
                    const uniqueCount = ids.size;
                    setNavBadge(uniqueCount);
                    setTabBadge(uniqueCount);
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
                }
                window.location.href = href;
            });

            // Delegate mark-as-read button (no navigation)
            document.getElementById('all-notifications')?.addEventListener('click', async (ev) => {
                const btn = ev.target.closest('.mark-read-btn');
                if (!btn) return;
                ev.preventDefault();
                const item = btn.closest('.notification-item');
                if (!item) return;
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';
                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);
                    await fetch(`${NOTIF_ENDPOINT}?ajax=mark_read`, {
                        method: 'POST',
                        body: form.toString()
                    });
                } catch (_) {}

                try {
                    btn.remove();
                } catch (_) {}

                if (notifId) {
                    const sel = `.notification-item[data-notif-id="${notifId}"]`;
                    document.querySelectorAll(sel).forEach(el => {
                        el.classList.remove('unread');
                        el.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                    });
                    document.querySelectorAll(`#unread-notifications .notification-item[data-notif-id="${notifId}"]`).forEach(el => el.remove());

                    const unreadEls = Array.from(document.querySelectorAll('.notification-item.unread[data-notif-id]'));
                    const ids = new Set(unreadEls.map(e => e.getAttribute('data-notif-id')));
                    const uniqueCount = ids.size;
                    setNavBadge(uniqueCount);
                    setTabBadge(uniqueCount);
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
                }
            });

            // Tab switching
            const allTab = document.getElementById('all-tab');
            const unreadTab = document.getElementById('unread-tab');
            const allContent = document.getElementById('all-notifications');
            const unreadContent = document.getElementById('unread-notifications');
            allTab?.addEventListener('click', () => {
                allTab.classList.add('active');
                unreadTab.classList.remove('active');
                allContent.style.display = 'block';
                unreadContent.style.display = 'none';
            });
            unreadTab?.addEventListener('click', () => {
                unreadTab.classList.add('active');
                allTab.classList.remove('active');
                unreadContent.style.display = 'block';
                allContent.style.display = 'none';
            });

            // initialize badges
            setNavBadge(<?= (int)$unreadMarine ?>);
            setTabBadge(<?= (int)$unreadMarine ?>);
        });
    </script>
</body>

</html>