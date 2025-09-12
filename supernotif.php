<?php
// supernotif.php (PDO/Supabase)
declare(strict_types=1);
session_start();

// Gate: must be logged in and an Admin in CENRO
if (empty($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}

require_once __DIR__ . '/backend/connection.php'; // exposes $pdo (PDO -> Supabase Postgres)

$admin_uuid = (string)$_SESSION['user_id'];

// Verify admin + cenro
try {
    $st = $pdo->prepare("
        SELECT department, role
        FROM public.users
        WHERE user_id = :uid
        LIMIT 1
    ");
    $st->execute([':uid' => $admin_uuid]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    if (!$me || strtolower((string)$me['role']) !== 'admin' || strtolower((string)$me['department']) !== 'cenro') {
        header('Location: superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SUPRENOTIF AUTH] ' . $e->getMessage());
    header('Location: superlogin.php');
    exit();
}

// Get current page (for nav highlighting)
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch notifications (profile update requests)
try {
    $sql = "
        SELECT
            pur.id,
            pur.user_id,
            pur.created_at,
            pur.is_read,
            pur.department,
            pur.status,
            pur.reviewed_at,
            pur.reviewed_by,
            u.first_name,
            u.last_name
        FROM public.profile_update_requests pur
        JOIN public.users u
          ON pur.user_id = u.user_id
        ORDER BY
            CASE WHEN lower(pur.status) = 'pending' THEN 0 ELSE 1 END ASC,
            pur.is_read ASC,
            CASE WHEN lower(pur.status) = 'pending' THEN pur.created_at ELSE pur.reviewed_at END DESC
    ";
    $notifications = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[SUPRENOTIF FETCH] ' . $e->getMessage());
    $notifications = [];
}

// Helper: normalize Postgres boolean-ish values
function pg_bool_true($v): bool
{
    if (is_bool($v)) return $v;
    $s = strtolower((string)$v);
    return in_array($s, ['t', 'true', '1', 'yes', 'on'], true);
}

$unread_notifications = array_values(array_filter($notifications, fn($n) => !pg_bool_true($n['is_read'])));

// Helper for "15 minutes ago"
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);

    $weeks = (int)floor($diff->d / 7);
    $days  = $diff->d % 7;

    $map = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = [];
    foreach ($map as $k => $label) {
        $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
    }
    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/supernotif.css">
</head>

<body>
    <header>
        <div class="logo">
            <a href="superhome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="superlogs.php" class="dropdown-item">
                        <i class="fas fa-user-shield" style="color:white;"></i><span>Admin Logs</span>
                    </a>
                </div>
            </div>

            <div class="nav-item">
                <div class="nav-icon">
                    <a href="supermessage.php"><i class="fas fa-envelope" style="color:black;"></i></a>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <!-- ✅ Unique ID so we never confuse this with status pills -->
                    <span class="badge" id="bell-badge"><?= count($unread_notifications) ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-list">
                        <?php if (count($notifications) === 0): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No profile update requests</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <!-- ✅ data-id for cross-list syncing -->
                                <div class="notification-item <?= (!pg_bool_true($notif['is_read'])) ? 'unread' : '' ?> status-<?= htmlspecialchars((string)$notif['status']) ?>" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">
                                    <a href="supereach.php?id=<?= htmlspecialchars((string)$notif['id']) ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                            <?php elseif (strtolower((string)$notif['status']) === 'approved'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                Profile Update <?= ucfirst((string)$notif['status']) ?>
                                                <span class="badge badge-<?= strtolower((string)$notif['status']) === 'pending' ? 'warning' : (strtolower((string)$notif['status']) === 'approved' ? 'success' : 'danger') ?>">
                                                    <?= ucfirst((string)$notif['status']) ?>
                                                </span>
                                            </div>
                                            <div class="notification-message">
                                                <?= htmlspecialchars((string)$notif['department']) ?> Administrator requested to update their profile.
                                            </div>
                                            <div class="notification-time">
                                                <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                                    Requested <?= time_elapsed_string((string)$notif['created_at']) ?>
                                                <?php else: ?>
                                                    <?= ucfirst((string)$notif['status']) ?> by
                                                    <?= htmlspecialchars((string)$notif['reviewed_by']) ?>
                                                    <?= time_elapsed_string((string)$notif['reviewed_at']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="notification-footer">
                        <a href="supernotif.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon <?= ($current_page === 'treeprofile.php') ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i><span>Edit Profile</span>
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
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
            <div id="unread-tab" class="tab">Unread <span class="tab-badge"><?= count($unread_notifications) ?></span></div>
        </div>

        <div id="all-notifications" class="notification-list">
            <?php if (count($notifications) === 0): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No profile update requests</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= (!pg_bool_true($notif['is_read'])) ? 'unread' : '' ?> status-<?= htmlspecialchars((string)$notif['status']) ?>" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon">
                                <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                <?php elseif (strtolower((string)$notif['status']) === 'approved'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </div>
                            Profile Update <?= ucfirst((string)$notif['status']) ?>
                            <span class="badge badge-<?= strtolower((string)$notif['status']) === 'pending' ? 'warning' : (strtolower((string)$notif['status']) === 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst((string)$notif['status']) ?>
                            </span>
                        </div>
                        <div class="notification-content">
                            <?= htmlspecialchars((string)$notif['department']) ?> Administrator requested to update their profile.
                        </div>
                        <div class="notification-time">
                            <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                Requested <?= time_elapsed_string((string)$notif['created_at']) ?>
                            <?php else: ?>
                                <?= ucfirst((string)$notif['status']) ?> by
                                <?= htmlspecialchars((string)$notif['reviewed_by']) ?>
                                <?= time_elapsed_string((string)$notif['reviewed_at']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-actions">
                            <button class="action-button view-details-btn" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">View Details</button>
                            <?php if (!pg_bool_true($notif['is_read'])): ?>
                                <button class="action-button mark-read-btn" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">Mark as Read</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="unread-notifications" class="notification-list" style="display:none;">
            <?php if (count($unread_notifications) === 0): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No unread notifications</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($unread_notifications as $notif): ?>
                    <div class="notification-item unread status-<?= htmlspecialchars((string)$notif['status']) ?>" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon">
                                <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                <?php elseif (strtolower((string)$notif['status']) === 'approved'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </div>
                            Profile Update <?= ucfirst((string)$notif['status']) ?>
                            <span class="badge badge-<?= strtolower((string)$notif['status']) === 'pending' ? 'warning' : (strtolower((string)$notif['status']) === 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst((string)$notif['status']) ?>
                            </span>
                        </div>
                        <div class="notification-content">
                            <?= htmlspecialchars((string)$notif['department']) ?> Administrator requested to update their profile.
                        </div>
                        <div class="notification-time">
                            <?php if (strtolower((string)$notif['status']) === 'pending'): ?>
                                Requested <?= time_elapsed_string((string)$notif['created_at']) ?>
                            <?php else: ?>
                                <?= ucfirst((string)$notif['status']) ?> by
                                <?= htmlspecialchars((string)$notif['reviewed_by']) ?>
                                <?= time_elapsed_string((string)$notif['reviewed_at']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-actions">
                            <button class="action-button view-details-btn" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">View Details</button>
                            <button class="action-button mark-read-btn" data-id="<?= htmlspecialchars((string)$notif['id']) ?>">Mark as Read</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mark-all-button">
            <button id="mark-all-read">✓ Mark all as read</button>
        </div>
    </div>

    <!-- Details Modal (static example – can be removed if not needed) -->
    <div class="modal" id="notification-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h2>Admin Profile Update Request</h2>
            </div>
            <div class="modal-body">
                <p><strong>Category:</strong> Administrator Request</p>
                <p><strong>Received:</strong> Today, 10:30 AM</p>
                <h3>Username Change Request</h3>
                <p>The Seedlings Administrator has requested to change their username.</p>
                <p><strong>Requested by:</strong> Seedlings Administrator</p>
                <p><strong>Request Date:</strong> <?= date('F j, Y') ?></p>
                <p><strong>Current Username:</strong> seedlings_admin</p>
                <p><strong>Requested New Username:</strong> seedlings_administrator</p>
                <p><strong>Reason for Change:</strong> Standardizing admin usernames across the system</p>
                <p><strong>Priority:</strong> Medium</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle && mobileToggle.addEventListener('click', () => navContainer.classList.toggle('active'));

            // Hover dropdowns
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dd => {
                const menu = dd.querySelector('.dropdown-menu');
                dd.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(0)' : 'translateY(0)';
                });
                dd.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
                menu.addEventListener('mouseleave', (e) => {
                    if (!dd.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    }
                });
            });

            // Close dropdowns when clicking outside (mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ? 'translateX(-50%) translateY(10px)' : 'translateY(10px)';
                    });
                }
            });

            // "Mark all as read" in header dropdown (persist to backend)
            const markAllHeader = document.querySelector('.mark-all-read');
            markAllHeader && markAllHeader.addEventListener('click', (e) => {
                e.preventDefault();
                sendMarkAll().then(ok => {
                    if (ok) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                            const b = item.querySelector('.mark-read-btn');
                            if (b) b.remove();
                        });
                        updateUnreadCounts();
                    }
                });
            });

            // Tabs
            const allTab = document.getElementById('all-tab');
            const unreadTab = document.getElementById('unread-tab');
            const allContent = document.getElementById('all-notifications');
            const unreadContent = document.getElementById('unread-notifications');

            allTab.addEventListener('click', () => {
                allTab.classList.add('active');
                unreadTab.classList.remove('active');
                allContent.style.display = 'block';
                unreadContent.style.display = 'none';
            });
            unreadTab.addEventListener('click', () => {
                unreadTab.classList.add('active');
                allTab.classList.remove('active');
                unreadContent.style.display = 'block';
                allContent.style.display = 'none';
            });

            // View Details → supereach.php
            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-id');
                    window.location.href = 'supereach.php?id=' + encodeURIComponent(id);
                });
            });

            // Mark one as read
            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    const ok = await sendMarkOne(id);
                    if (ok) {
                        // ✅ Clear "unread" from every copy (header + All + Unread)
                        const sel = `.notification-item[data-id="${CSS.escape(id)}"]`;
                        document.querySelectorAll(sel).forEach(el => {
                            el.classList.remove('unread');
                            const b = el.querySelector('.mark-read-btn');
                            if (b) b.remove();
                        });
                        updateUnreadCounts();
                    }
                });
            });

            // Mark all as read (button below lists)
            document.getElementById('mark-all-read').addEventListener('click', async () => {
                const ok = await sendMarkAll();
                if (ok) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const b = item.querySelector('.mark-read-btn');
                        if (b) b.remove();
                    });
                    updateUnreadCounts();
                }
            });

            async function sendMarkOne(id) {
                try {
                    const r = await fetch('backend/admin/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: 'id=' + encodeURIComponent(id)
                    });
                    const txt = await r.text();
                    let data;
                    try {
                        data = JSON.parse(txt);
                    } catch (e) {
                        console.error('Non-JSON response for mark one:', txt);
                        return false;
                    }
                    if (!r.ok || !data || !data.success) {
                        console.error('Mark one failed:', data);
                        return false;
                    }
                    return true;
                } catch (err) {
                    console.error('Mark one error:', err);
                    return false;
                }
            }

            async function sendMarkAll() {
                try {
                    const r = await fetch('backend/admin/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: 'mark_all=1'
                    });
                    const txt = await r.text();
                    let data;
                    try {
                        data = JSON.parse(txt);
                    } catch (e) {
                        console.error('Non-JSON response for mark all:', txt);
                        return false;
                    }
                    if (!r.ok || !data || !data.success) {
                        console.error('Mark all failed:', data);
                        return false;
                    }
                    return true;
                } catch (err) {
                    console.error('Mark all error:', err);
                    return false;
                }
            }

            function updateUnreadCounts() {
                // ✅ Count from a single source of truth to avoid duplicates
                const src = document.getElementById('all-notifications');
                const unread = src ? src.querySelectorAll('.notification-item.unread').length : 0;

                const tabBadge = document.querySelector('.tab-badge');
                const bellBadge = document.getElementById('bell-badge');

                if (tabBadge) tabBadge.textContent = unread;
                if (bellBadge) bellBadge.textContent = unread;

                if (tabBadge) tabBadge.style.display = unread === 0 ? 'none' : 'inline-block';
                if (bellBadge) bellBadge.style.display = unread === 0 ? 'none' : 'inline-block';
            }
        });
    </script>
</body>

</html>