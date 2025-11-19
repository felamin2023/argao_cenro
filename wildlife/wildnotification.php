<?php

declare(strict_types=1);
session_start();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Manila');

/* Adjust path if your connection file is elsewhere */
require_once __DIR__ . '/../backend/connection.php'; // provides $pdo

// Must be logged in and an Admin + wildlife department guard (try to follow wildhome behavior)
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: ../superlogin.php');
    exit();
}

// Simple helpers
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
        // DB timestamps are stored as UTC without timezone info. Parse as UTC then convert to Asia/Manila
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
            $pdo->exec("UPDATE public.notifications SET is_read = true WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false");
            $pdo->exec("UPDATE public.incident_report SET is_read = true WHERE LOWER(COALESCE(category,''))='wildlife monitoring' AND is_read=false");
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

// Fetch notifications exactly as in wildhome.php (no incident report merge)
$wildNotifs = [];
$unreadWildlife = 0;
try {
    $wildNotifs = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               n.incident_id,
               n.reqpro_id,
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

    $unreadWildlife = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications
        WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false
    ")->fetchColumn();
} catch (Throwable $e) {
    error_log('[WILDNOTIF BOOTSTRAP] ' . $e->getMessage());
    $wildNotifs = [];
    $unreadWildlife = 0;
}

// Get the current page name (for active state)
$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wildlife Monitoring | Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="/denr/superadmin/css/wildnotification.css" />
    <!-- Inline style from wildhome.php for dropdown and notification UI -->

</head>
<style>
    .nav-item .badge {
        position: absolute;
        top: -6px;
        right: -6px;
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
        width: 30%;
    }

    .notification-time {
        color: #6b7280;
        font-size: .9rem;
        padding: 0px 10px;
    }

    .notification-message {
        color: #234;
    }

    .mark-all-button {
        padding: 10px;
    }
</style>

<body>
    <header>
        <div class="logo">
            <a href="wildhome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>
        <button class="mobile-toggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
        <div class="nav-container">
            <!-- Main menu (DASHBOARD) -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Wildlife Management</span>
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
            <!-- Notifications -->
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

                        // Permits / notifications
                        foreach ($wildNotifs as $nf) {
                            $combined[] = [
                                'id'          => $nf['notif_id'],
                                'notif_id'    => $nf['notif_id'],
                                'approval_id' => $nf['approval_id'] ?? null,
                                'incident_id' => $nf['incident_id'] ?? null,
                                'reqpro_id'   => $nf['reqpro_id'] ?? null,
                                'is_read'     => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'message'     => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' requested a wildlife permit.')),
                                'ago'         => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'        => !empty($nf['reqpro_id']) ? 'wildprofile.php' : (!empty($nf['approval_id']) ? 'wildpermit.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'wildnotification.php'))
                            ];
                        }

                        // incident reports removed

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $hasIncident = isset($item['incident_id']) && $item['incident_id'] !== null && trim((string)$item['incident_id']) !== '';
                                $hasApproval = isset($item['approval_id']) && $item['approval_id'] !== null && trim((string)$item['approval_id']) !== '';
                                $hasReqpro   = isset($item['reqpro_id'])   && $item['reqpro_id']   !== null && trim((string)$item['reqpro_id'])   !== '';

                                if ($hasIncident) {
                                    $title = 'Incident report';
                                } elseif ($hasApproval) {
                                    $title = 'Permit request';
                                } elseif ($hasReqpro) {
                                    $title = 'Profile request';
                                } else {
                                    $title = 'Permit request';
                                }
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= h($item['id']) ?>" <?php if ($hasIncident): ?> data-incident-id="<?= h($item['incident_id']) ?>" <?php endif; ?>>
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
            <!-- Profile -->
            <div class="nav-item dropdown" data-dropdown>
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


    <!-- Notifications Content -->
    <div class="notifications-container">
        <div class="notifications-header">NOTIFICATIONS</div>

        <div class="notification-tabs">
            <div id="all-tab" class="tab active">All Notifications</div>
            <div id="unread-tab" class="tab">Unread <span class="tab-badge"><?= (int)$unreadWildlife ?></span></div>
        </div>

        <div id="all-notifications" class="notification-list">
            <?php if (empty($wildNotifs)): ?>
                <div class="notification-item">
                    <div class="notification-title">No notifications</div>
                    <div class="notification-message">You're all caught up.</div>
                </div>
                <?php else:
                foreach ($wildNotifs as $nf):
                    $isUnread = empty($nf['is_read']) ? true : false;
                    $icon = $nf['request_type'] === 'wildlife' ? 'fa-exclamation-triangle' : 'fa-bell';
                    $href = !empty($nf['reqpro_id']) ? 'wildprofile.php' : (!empty($nf['approval_id']) ? 'wildpermit.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'wildnotification.php'));
                ?>
                    <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" data-notif-id="<?= h((string)$nf['notif_id']) ?>" data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                        <div class="notification-title" style="width: 30%;"><?= h(substr((string)$nf['message'], 0, 120)) ?></div>
                        <div class="notification-content"><?= h((string)$nf['message']) ?></div>
                        <div class="notification-actions">
                            <a class="action-button view-link" href="<?= h($href) ?>">View</a>
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
            foreach ($wildNotifs as $nf) {
                if (empty($nf['is_read'])) {
                    $hasUnread = true;
                    $icon = $nf['request_type'] === 'wildlife' ? 'fa-exclamation-triangle' : 'fa-bell';
                    $href = !empty($nf['reqpro_id']) ? 'wildprofile.php' : (!empty($nf['approval_id']) ? 'wildpermit.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'wildnotification.php'));
            ?>
                    <div class="notification-item unread" data-notif-id="<?= h((string)$nf['notif_id']) ?>" data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas <?= $icon ?>"></i></div>
                            <h4><?= h(substr((string)$nf['message'], 0, 120)) ?></h4>
                        </div>
                        <div class="notification-content"><?= h((string)$nf['message']) ?></div>
                        <div class="notification-time"><?= h(time_elapsed_string($nf['created_at'])) ?></div>
                        <div class="notification-actions">
                            <a class="action-button view-link" href="<?= h($href) ?>">View</a>
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
                <h2>Wildlife Incident Details</h2>
            </div>
            <div class="modal-body">
                <p><strong>Category:</strong> Wildlife Sighting</p>
                <p><strong>Received:</strong> 15 minutes ago</p>

                <h3>Monitor Lizard Sighting - Residential Area</h3>

                <p>A resident reported a large monitor lizard near their backyard.</p>

                <p><strong>Location:</strong> Residential backyard, Barangay Poblacion</p>
                <p><strong>Reported by:</strong> John Doe (Local Resident)</p>
                <p><strong>Date of Incident:</strong> Today, early morning</p>
                <p><strong>Species:</strong> Monitor Lizard (Varanus salvator)</p>
                <p><strong>Size:</strong> Approximately 1.2 meters in length</p>
                <p><strong>Details:</strong> The lizard was seen moving slowly near the fence line. No aggressive behavior observed but residents are concerned about potential danger to pets and children.</p>
                <p><strong>Evidence:</strong> Photos available in full report</p>

                <p><strong>Recommended Action:</strong> Relocation by wildlife authorities</p>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const NOTIF_ENDPOINT = '<?php echo basename(__FILE__); ?>'; // calls THIS page for AJAX

            // UI helpers for keeping unread counts / lists in sync
            function updateBadgesBy(delta) {
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    const n = Math.max(0, (parseInt(badge.textContent || '0', 10) || 0) + delta);
                    badge.textContent = String(n);
                    badge.style.display = n > 0 ? '' : 'none';
                }
                const tabBadge = document.querySelector('.tab-badge');
                if (tabBadge) {
                    const m = Math.max(0, (parseInt(tabBadge.textContent || '0', 10) || 0) + delta);
                    tabBadge.textContent = String(m);
                }
            }

            function setAllReadUI() {
                // remove unread class everywhere
                document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }
                const tabBadge = document.querySelector('.tab-badge');
                if (tabBadge) tabBadge.textContent = '0';
                const unreadList = document.getElementById('unread-notifications');
                if (unreadList) unreadList.innerHTML = '<div class="notification-item"><div class="notification-title">No unread notifications</div></div>';
                // Hide all "mark as read" buttons
                document.querySelectorAll('.mark-read-btn').forEach(btn => btn.style.display = 'none');
            }

            function markSingleReadInUI(notifId) {
                if (!notifId) return;
                // remove unread class for any matching items (header + lists)
                const sel = `.notification-item[data-notif-id="${notifId}"]`;
                document.querySelectorAll(sel).forEach(el => el.classList.remove('unread'));
                // remove from unread list specifically
                const unreadItem = document.querySelector(`#unread-notifications ${sel}`);
                if (unreadItem) unreadItem.remove();
                // if unread list empty, show placeholder
                const unreadList = document.getElementById('unread-notifications');
                if (unreadList && unreadList.querySelectorAll('.notification-item').length === 0) {
                    unreadList.innerHTML = '<div class="notification-item"><div class="notification-title">No unread notifications</div></div>';
                }
                // decrement badges by one
                updateBadgesBy(-1);
            }
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
                // Open on hover and close when mouse leaves
                dd.addEventListener('mouseenter', () => {
                    if (!dd.classList.contains('open')) open();
                });
                dd.addEventListener('mouseleave', () => {
                    if (dd.classList.contains('open')) close();
                });
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

            // Helper to reset dropdown state
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
                        ['color', 'backgroundColor', 'borderColor'].forEach(p => {
                            try {
                                triggerEl.style.removeProperty(p);
                            } catch (_) {}
                        });
                        try {
                            triggerEl.blur();
                        } catch (_) {}
                    }

                    const menu = dd.querySelector('.dropdown-menu');
                    if (menu) {
                        ['opacity', 'visibility', 'display'].forEach(p => {
                            try {
                                menu.style.removeProperty(p);
                            } catch (_) {}
                        });
                    }
                } catch (_) {}
            }

            // Mark ALL as read
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                // optimistic UI
                setAllReadUI();

                try {
                    const res = await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                    // reset dropdown state
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
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

                // update UI across header and content lists
                markSingleReadInUI(notifId);
                window.location.href = href;
            });

            // Content-area: single "Mark as Read" button handler (delegated)
            document.addEventListener('click', async (e) => {
                const btn = e.target.closest('.mark-read-btn');
                if (!btn) return;
                // Only handle if NOT inside the notification dropdown
                if (btn.closest('#notifDropdown')) return;
                e.preventDefault();

                const item = btn.closest('.notification-item');
                if (!item) return;
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';

                // optimistic UI and update all lists/badges
                btn.remove();
                markSingleReadInUI(notifId);

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
                } catch (err) {
                    // swallow error — UI already updated optimistically
                }
            });

            // Content-area: Mark all as read main button
            document.getElementById('mark-all-read-main')?.addEventListener('click', async (e) => {
                e.preventDefault();
                // optimistic UI
                setAllReadUI();

                try {
                    const res = await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                    // reset dropdown state
                    try {
                        resetNotifDropdown();
                    } catch (_) {}
                } catch {
                    location.reload();
                }
            });

            // Tab switching: show All or Unread lists
            (function() {
                const allTab = document.getElementById('all-tab');
                const unreadTab = document.getElementById('unread-tab');
                const allList = document.getElementById('all-notifications');
                const unreadList = document.getElementById('unread-notifications');

                function showAll() {
                    allTab?.classList.add('active');
                    unreadTab?.classList.remove('active');
                    if (allList) allList.style.display = '';
                    if (unreadList) unreadList.style.display = 'none';
                }

                function showUnread() {
                    unreadTab?.classList.add('active');
                    allTab?.classList.remove('active');
                    if (unreadList) unreadList.style.display = '';
                    if (allList) allList.style.display = 'none';
                }

                allTab?.addEventListener('click', (e) => {
                    e.preventDefault();
                    showAll();
                });
                unreadTab?.addEventListener('click', (e) => {
                    e.preventDefault();
                    showUnread();
                });
            })();
        });
    </script>
</body>

</html>