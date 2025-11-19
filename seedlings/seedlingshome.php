<?php

declare(strict_types=1);

session_start();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Manila');

/* Adjust path if your connection file is elsewhere */
require_once __DIR__ . '/../backend/connection.php'; // provides $pdo

/* ---- AJAX: mark single / mark all read (handled by this same page) ---- */
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

/* ---- helpers used by the UI snippet ---- */
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
        // DB stores timestamps in UTC without timezone info, so parse as UTC then convert
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

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

/* ---- data needed by your pasted UI (badge + lists) ---- */
$seedlingNotifs = [];
$unreadSeedling = 0;

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

// Current user (UUID)
$user_id = (string)$_SESSION['user_id'];

try {
    $st = $pdo->prepare("
        SELECT role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin  = $u && strtolower((string)$u['role']) === 'admin';
    $isMarine = $u && strtolower((string)$u['department']) === 'seedling';
    // optionally require an approved/verified status:
    // $statusOk = $u && in_array(strtolower((string)$u['status']), ['verified','approved'], true);

    if (!$isAdmin || !$isMarine /* || !$statusOk */) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[MARINE-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Sample quantities (replace with your database values)
$quantities = [
    'total_received' => 1250,
    'plantable_seedlings' => 980,
    'total_released' => 720,
    'total_discarded' => 150,
    'total_balance' => 380,
    'all_records' => 2150
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seedlings Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/seedlingshome.css">
    <link rel="stylesheet" href="/denr/superadmin/js/seedlingshome.js">

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
                    <!-- New Add Seedlings option -->

                    <a href="incoming.php" class="dropdown-item">
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
                <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
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

    <!-- ADDED CONTENT ABOUT SEEDLINGS MONITORING -->
    <div class="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-title">ECOTRACK: A RESOURCE TRACKING AND INVENTORY SYSTEM OF DEPARTMENT OF ENVIRONMENT AND NATURAL <br>RESOURCES , ARGAO</h1>
            <p class="hero-subtitle">Tracking the growth of tomorrow's forests today. Our comprehensive monitoring system ensures the sustainable development and protection of seedlings from nursery to plantation.</p>
        </section>
    </div>

    <script>
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