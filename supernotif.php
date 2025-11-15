<?php
// supernotif.php (PDO/Supabase) — UPDATED to match superhome.php bell UI + logic
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

// =================== AJAX (same endpoints as superhome.php) ===================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where lower("to") = :to and (is_read is null or is_read = false)
            ');
            $u->execute([':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_read') {
            $nid = $_GET['notif_id'] ?? '';
            if (!$nid) {
                echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
                exit;
            }
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where notif_id = :nid and lower("to") = :to
            ');
            $u->execute([':nid' => $nid, ':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[SUPRENOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =================== Load notifications (identical query to superhome.php) ===================
$notifs = [];
$unreadCount = 0;

try {
    $ns = $pdo->prepare('
        select
            notif_id,
            "from",
            "to",
            message,
            is_read,
            created_at,
            reqpro_id
        from public.notifications
        where lower("to") = :to
        order by created_at desc
        limit 100
    ');
    $ns->execute([':to' => 'cenro']);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[SUPRENOTIF LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

// =================== Helpers (exact same as superhome.php) ===================
// Treat naive DB timestamps as UTC, then show elapsed time in Asia/Manila.
function _to_manila_dt(string $src): DateTimeImmutable
{
    $src = trim($src);
    $hasTz = (bool)preg_match('/[zZ]|[+\-]\d{2}:\d{2}$/', $src); // already has TZ?
    $base  = new DateTimeImmutable($src, $hasTz ? null : new DateTimeZone('UTC'));
    return $base->setTimezone(new DateTimeZone('Asia/Manila'));
}

function time_elapsed_string(string $datetime, bool $full = false): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $ago = _to_manila_dt($datetime);

    $diff = $now->diff($ago);

    // Use total days for weeks calculation
    $daysTotal = is_int($diff->days) ? $diff->days : (int)$diff->d;
    $weeks     = intdiv($daysTotal, 7);
    $daysLeft  = $daysTotal - $weeks * 7;

    $units = [
        'y' => ['v' => $diff->y,        'label' => 'year'],
        'm' => ['v' => $diff->m,        'label' => 'month'],
        'w' => ['v' => $weeks,          'label' => 'week'],
        'd' => ['v' => $daysLeft,       'label' => 'day'],
        'h' => ['v' => $diff->h,        'label' => 'hour'],
        'i' => ['v' => $diff->i,        'label' => 'minute'],
        's' => ['v' => $diff->s,        'label' => 'second'],
    ];

    $parts = [];
    foreach ($units as $u) {
        if ($u['v'] > 0) {
            $parts[] = $u['v'] . ' ' . $u['label'] . ($u['v'] > 1 ? 's' : '');
        }
    }
    if (!$full) $parts = array_slice($parts, 0, 1);

    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// Optional: nice absolute Manila time for tooltips
function format_manila_abs(string $datetime): string
{
    return _to_manila_dt($datetime)->format('M j, Y g:i A');
}

// For nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Build filtered arrays for All/Unread tabs
$unreadOnly = array_values(array_filter($notifs, fn($n) => empty($n['is_read'])));

// Little helpers for title/message, same rules as superhome.php
function notif_title_from($fromVal): string
{
    $fv = trim((string)$fromVal);
    if (preg_match('/^register request$/i', $fv)) return 'Registration';
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fv)) return 'Profile update';
    return 'Notification';
}
function clean_notif_msg($m): string
{
    $t = trim((string)$m);
    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
    $t = trim(preg_replace('/\s{2,}/', ' ', $t));
    return $t !== '' ? $t : 'There’s an update.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Page (list) styles -->
    <link rel="stylesheet" href="/denr/superadmin/css/supernotif.css">

    <!-- Bell dropdown styles (copied from superhome.php) -->
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

        .as-notif-item:hover {
            background: #f9f9f9;
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .as-notif-icon {
            color: var(--as-primary);
            font-size: 1.2rem;
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
    </style>
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

            <!-- === Bell dropdown (IDENTICAL to superhome.php) === -->
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
                                $created  = isset($n['created_at']) ? (string)$n['created_at'] : '';
                                $fromVal  = (string)($n['from'] ?? '');
                                $reqproId = $n['reqpro_id'] ?? '';

                                $title = notif_title_from($fromVal);

                                $cleanMsg = clean_notif_msg($n['message'] ?? '');

                                // Relative/absolute times (Asia/Manila)
                                $relTime  = $created !== '' ? time_elapsed_string($created) : 'just now';
                                $absTitle = $created !== '' ? format_manila_abs($created)
                                    : format_manila_abs((new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
                                $ts = $created !== ''
                                    ? _to_manila_dt($created)->getTimestamp()
                                    : (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->getTimestamp();
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
                                            <div class="as-notif-time"
                                                data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>"
                                                title="<?= htmlspecialchars($absTitle, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($relTime, ENT_QUOTES) ?>
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

    <!-- =================== MAIN LIST (All / Unread tabs) =================== -->
    <div class="notifications-container">
        <div class="notifications-header">NOTIFICATIONS</div>

        <div class="notification-tabs">
            <div id="all-tab" class="tab active">All Notifications</div>
            <div id="unread-tab" class="tab">Unread <span class="tab-badge"><?= (int)$unreadCount ?></span></div>
        </div>

        <!-- ALL -->
        <div id="all-notifications" class="notification-list">
            <?php if (!$notifs): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No notifications for CENRO</div>
                    </div>
                </div>
                <?php else: foreach ($notifs as $n):
                    $unread   = empty($n['is_read']);
                    $created  = isset($n['created_at']) ? (string)$n['created_at'] : '';
                    $fromVal  = (string)($n['from'] ?? '');
                    $reqproId = $n['reqpro_id'] ?? '';

                    $title    = notif_title_from($fromVal);
                    $cleanMsg = clean_notif_msg($n['message'] ?? '');
                    $relTime  = $created !== '' ? time_elapsed_string($created) : 'just now';
                ?>
                    <div class="notification-item <?= $unread ? 'unread' : '' ?>" data-id="<?= htmlspecialchars((string)$n['notif_id']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas fa-info-circle"></i></div>
                            <?= htmlspecialchars($title) ?>
                        </div>
                        <div class="notification-content"><?= htmlspecialchars($cleanMsg) ?></div>
                        <div class="notification-time"><?= htmlspecialchars($relTime) ?></div>
                        <div class="notification-actions">
                            <button
                                class="action-button view-details-btn"
                                data-notif-id="<?= htmlspecialchars((string)$n['notif_id']) ?>"
                                data-from="<?= htmlspecialchars($fromVal) ?>"
                                data-reqpro-id="<?= htmlspecialchars((string)$reqproId) ?>">View Details</button>
                            <?php if ($unread): ?>
                                <button class="action-button mark-read-btn" data-id="<?= htmlspecialchars((string)$n['notif_id']) ?>">Mark as Read</button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>

        <!-- UNREAD -->
        <div id="unread-notifications" class="notification-list" style="display:none;">
            <?php if (!$unreadOnly): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No unread notifications</div>
                    </div>
                </div>
                <?php else: foreach ($unreadOnly as $n):
                    $created  = isset($n['created_at']) ? (string)$n['created_at'] : '';
                    $fromVal  = (string)($n['from'] ?? '');
                    $reqproId = $n['reqpro_id'] ?? '';

                    $title    = notif_title_from($fromVal);
                    $cleanMsg = clean_notif_msg($n['message'] ?? '');
                    $relTime  = $created !== '' ? time_elapsed_string($created) : 'just now';
                ?>
                    <div class="notification-item unread" data-id="<?= htmlspecialchars((string)$n['notif_id']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon"><i class="fas fa-info-circle"></i></div>
                            <?= htmlspecialchars($title) ?>
                        </div>
                        <div class="notification-content"><?= htmlspecialchars($cleanMsg) ?></div>
                        <div class="notification-time"><?= htmlspecialchars($relTime) ?></div>
                        <div class="notification-actions">
                            <button
                                class="action-button view-details-btn"
                                data-notif-id="<?= htmlspecialchars((string)$n['notif_id']) ?>"
                                data-from="<?= htmlspecialchars($fromVal) ?>"
                                data-reqpro-id="<?= htmlspecialchars((string)$reqproId) ?>">View Details</button>
                            <button class="action-button mark-read-btn" data-id="<?= htmlspecialchars((string)$n['notif_id']) ?>">Mark as Read</button>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>

        <div class="mark-all-button">
            <button id="mark-all-read">✓ Mark all as read</button>
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

            // === Header dropdown: same deep-link + mark-read pattern as superhome.php ===
            (function() {
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

                    // mark as read (fire-and-forget) via this page's ajax
                    fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(notifId), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(() => {});

                    // decide target (exact rules as superhome.php)
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

                // "Mark all as read" in header
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
            })();

            // ===== Tabs =====
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

            // ===== Deep-link routing for the big lists =====
            const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

            function computeTargetURL({
                fromVal,
                reqproId,
                notifId
            }) {
                const f = (fromVal || '').trim();
                if (/^register request$/i.test(f)) {
                    return 'superhome.php?notif_id=' + encodeURIComponent(notifId);
                }
                if (uuidRe.test(f)) {
                    if (reqproId && /^\d+$/.test(reqproId)) {
                        return 'supereach.php?id=' + encodeURIComponent(reqproId) +
                            '&notif_id=' + encodeURIComponent(notifId);
                    }
                    return 'supereach.php?user=' + encodeURIComponent(f) +
                        '&notif_id=' + encodeURIComponent(notifId);
                }
                return 'supernotif.php?notif_id=' + encodeURIComponent(notifId);
            }

            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const notifId = btn.dataset.notifId || '';
                    const fromVal = btn.dataset.from || '';
                    const reqproId = btn.dataset.reqproId || '';
                    const url = computeTargetURL({
                        fromVal,
                        reqproId,
                        notifId
                    });
                    // Do not mark read here; destination page marks on load
                    window.location.href = url;
                });
            });

            // ===== Mark one / all as read (using this page's ajax, same as superhome.php) =====
            document.querySelectorAll('.mark-read-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    const ok = await markOne(id);
                    if (ok) {
                        // remove 'unread' state from all duplicates of this notif
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

            document.getElementById('mark-all-read').addEventListener('click', async () => {
                const ok = await markAll();
                if (ok) {
                    document.querySelectorAll('.notification-item.unread').forEach(el => {
                        el.classList.remove('unread');
                        const b = el.querySelector('.mark-read-btn');
                        if (b) b.remove();
                    });
                    updateUnreadCounts();
                }
            });

            async function markOne(id) {
                try {
                    const r = await fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(id), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await r.json().catch(() => null);
                    return !!(r.ok && data && data.success);
                } catch {
                    return false;
                }
            }
            async function markAll() {
                try {
                    const r = await fetch('?ajax=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await r.json().catch(() => null);
                    return !!(r.ok && data && data.success);
                } catch {
                    return false;
                }
            }

            function updateUnreadCounts() {
                const unread = document.querySelectorAll('#all-notifications .notification-item.unread').length;
                const tabBadge = document.querySelector('.tab-badge');
                const bellBadge = document.getElementById('asNotifBadge');
                if (tabBadge) {
                    tabBadge.textContent = unread;
                    tabBadge.style.display = unread ? 'inline-block' : 'none';
                }
                if (bellBadge) {
                    bellBadge.textContent = unread;
                    bellBadge.style.display = unread ? 'inline-flex' : 'none';
                }
            }
        });
    </script>
</body>

</html>