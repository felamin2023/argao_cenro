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

// Prepare data for rendering the page (notifications + incidents)
$wildNotifs = [];
$unreadWildlife = 0;
try {
    // A) notifications addressed to "wildlife"
    $notifRows = $pdo->query("SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to, a.approval_id, COALESCE(NULLIF(btrim(a.permit_type), ''), 'none') AS permit_type, COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status, LOWER(COALESCE(a.request_type,'')) AS request_type, c.first_name AS client_first, c.last_name AS client_last FROM public.notifications n LEFT JOIN public.approval a ON a.approval_id = n.approval_id LEFT JOIN public.client c ON c.client_id = a.client_id WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife' ORDER BY n.is_read ASC, n.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadWildlife = (int)$pdo->query("SELECT COUNT(*) FROM public.notifications WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false")->fetchColumn();

    // B) incident reports
    $incRows = $pdo->query("SELECT incident_id, COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text, status, is_read, created_at FROM public.incident_report WHERE lower(COALESCE(category,'')) = 'wildlife monitoring' ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

    $unreadInc = (int)$pdo->query("SELECT COUNT(*) FROM public.incident_report WHERE lower(COALESCE(category,'')) = 'wildlife monitoring' AND is_read = false")->fetchColumn();
    $unreadWildlife += $unreadInc;

    // merge + sort
    $wildNotifs = array_merge($notifRows, $incidentRows);
    usort($wildNotifs, function ($a, $b) {
        $ta = strtotime((string)($a['created_at'] ?? 'now'));
        $tb = strtotime((string)($b['created_at'] ?? 'now'));
        return $tb <=> $ta;
    });
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Monitoring | Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <link rel="stylesheet" href="/denr/superadmin/css/wildnotification.css">
</head>

<body>

    <header>
        <div class="logo">
            <a href="wildhome.php">
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
                    <a href="breedingreport.php" class="dropdown-item">
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


            <!-- Notifications -->
            <div class="nav-item dropdown" id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="notifDropdownList">
                        <?php if (empty($wildNotifs)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No notifications</div>
                                    <div class="notification-message">You're all caught up.</div>
                                </div>
                            </div>
                            <?php else:
                            $count = 0;
                            foreach ($wildNotifs as $nf):
                                // limit the number shown in the header dropdown to 8
                                if ($count++ >= 8) break;
                                $isUnread = empty($nf['is_read']) ? true : false;
                                $icon = $nf['request_type'] === 'wildlife' ? 'fa-exclamation-triangle' : 'fa-bell';
                                $href = '#';
                                if (!empty($nf['incident_id'])) {
                                    $href = 'wildeach.php?id=' . urlencode((string)$nf['incident_id']);
                                } elseif (!empty($nf['approval_id'])) {
                                    $href = 'wildpermit.php?approval_id=' . urlencode((string)$nf['approval_id']);
                                }
                            ?>
                                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" data-notif-id="<?= h((string)$nf['notif_id']) ?>" data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                                    <a href="<?= h($href) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="fas <?= $icon ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h(substr((string)$nf['message'], 0, 120)) ?></div>
                                            <div class="notification-message"><?= h(substr((string)$nf['message'], 0, 240)) ?></div>
                                            <div class="notification-time"><?= h(time_elapsed_string($nf['created_at'])) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                    <div class="notification-footer"><a href="wildnotification.php" class="view-all">View All Notifications</a></div>
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
                    $href = '#';
                    if (!empty($nf['incident_id'])) {
                        $href = 'wildeach.php?id=' . urlencode((string)$nf['incident_id']);
                    } elseif (!empty($nf['approval_id'])) {
                        $href = 'wildpermit.php?approval_id=' . urlencode((string)$nf['approval_id']);
                    }
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
                    $href = !empty($nf['incident_id']) ? 'wildeach.php?id=' . urlencode((string)$nf['incident_id']) : (!empty($nf['approval_id']) ? 'wildpermit.php?approval_id=' . urlencode((string)$nf['approval_id']) : '#');
            ?>
                    <div class="notification-item unread" data-notif-id="<?= h((string)$nf['notif_id']) ?>" data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas <?= $icon ?>"></i></div><?= h(substr((string)$nf['message'], 0, 120)) ?>
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

            // Optimistic mark all as read (header)
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('#notifDropdownList .notification-item.unread').forEach(el => el.classList.remove('unread'));
                setNavBadge(0);
                setTabBadge(0);
                try {
                    const res = await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const json = await res.json();
                    if (!json || json.ok !== true) location.reload();
                } catch (_) {
                    location.reload();
                }
            });

            // Mark all from main content
            document.getElementById('mark-all-read-main')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
                setNavBadge(0);
                setTabBadge(0);
                try {
                    await fetch(`${NOTIF_ENDPOINT}?ajax=mark_all_read`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
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
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {}
                // optimistic UI update
                if (item.classList.contains('unread')) {
                    item.classList.remove('unread');
                    const navCount = parseInt(document.querySelector('#notifDropdown .badge')?.textContent || '0', 10) || 0;
                    const next = Math.max(0, navCount - 1);
                    setNavBadge(next);
                    // update tab badge
                    const tabCount = document.querySelectorAll('.notification-item.unread').length;
                    setTabBadge(tabCount);
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
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {}
                if (item.classList.contains('unread')) {
                    item.classList.remove('unread');
                    const navCount = parseInt(document.querySelector('#notifDropdown .badge')?.textContent || '0', 10) || 0;
                    const next = Math.max(0, navCount - 1);
                    setNavBadge(next);
                    const tabCount = document.querySelectorAll('.notification-item.unread').length;
                    setTabBadge(tabCount);
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
            setNavBadge(<?= (int)$unreadWildlife ?>);
            setTabBadge(<?= (int)$unreadWildlife ?>);
        });
    </script>
</body>

</html>