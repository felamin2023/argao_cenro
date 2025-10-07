<?php

declare(strict_types=1);

session_start();

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Postgres)

/* ========= AJAX: mark a single notification/incident as read ========= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_read') {
    header('Content-Type: application/json');
    $notifId    = $_POST['notif_id'] ?? '';
    $incidentId = $_POST['incident_id'] ?? '';

    if (!$notifId && !$incidentId) {
        echo json_encode(['ok' => false, 'error' => 'missing notif_id or incident_id']);
        exit();
    }

    try {
        if ($notifId) {
            $u = $pdo->prepare("UPDATE public.notifications SET is_read = true WHERE notif_id = :id");
            $u->execute([':id' => $notifId]);
        }
        if ($incidentId) {
            $u2 = $pdo->prepare("UPDATE public.incident_report SET is_read = true WHERE incident_id = :id");
            $u2->execute([':id' => $incidentId]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('[WILDHOME MARK_READ] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit();
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

    $isAdmin    = $u && strtolower((string)$u['role']) === 'admin';
    $isWildlife = $u && strtolower((string)$u['department']) === 'wildlife';

    if (!$isAdmin || !$isWildlife) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[WILDLIFE-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

// Helpers
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function time_elapsed_string($datetime, $full = false): string
{
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

/* ----- Notifications (to = 'Wildlife') + Incident Reports (category = 'WildLife Monitoring') ----- */
$wildNotifs = [];
$unreadWildlife = 0;

try {
    // A) notifications addressed to "wildlife"
    $notifRows = $pdo->query("
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
            NULL::text    AS incident_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadWildlife = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
          AND n.is_read = false
    ")->fetchColumn();

    // B) incidents list (same UI shape)
    $incRows = $pdo->query("
        SELECT
            incident_id,
            COALESCE(NULLIF(btrim(more_description), ''),
                     COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
            status,
            is_read,
            created_at
        FROM public.incident_report
        WHERE lower(COALESCE(category,'')) = 'wildlife monitoring'
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
            'approval_status' => $r['status'], // reused for icon/text styling
            'request_type'    => 'wildlife',
            'client_first'    => null,
            'client_last'     => null,
            'incident_id'     => $r['incident_id'],
        ];
    }, $incRows);

    $unreadInc = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.incident_report
        WHERE lower(COALESCE(category,'')) = 'wildlife monitoring'
          AND is_read = false
    ")->fetchColumn();
    $unreadWildlife += $unreadInc;

    // merge + sort by created_at desc (UI unchanged)
    $wildNotifs = array_merge($notifRows, $incidentRows);
    usort($wildNotifs, function ($a, $b) {
        $ta = strtotime((string)($a['created_at'] ?? 'now'));
        $tb = strtotime((string)($b['created_at'] ?? 'now'));
        return $tb <=> $ta;
    });
} catch (Throwable $e) {
    error_log('[WILDHOME NOTIFS] ' . $e->getMessage());
    $wildNotifs = [];
    $unreadWildlife = 0;
}

// Current page (for profile menu active state)
$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wildlife Monitoring</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="/denr/superadmin/css/wildhome.css" />

    <!-- keep UI identical; only behavior/data changed -->
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
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="wildhome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <button class="mobile-toggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-container">
            <!-- Main menu -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i><span>Add Record</span>
                    </a>
                    <a href="wildpermit.php" class="dropdown-item">
                        <i class="fas fa-paw"></i><span>Wildlife Permit</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <!-- <div class="nav-item">
                <div class="nav-icon">
                    <a href="wildmessage.php" aria-label="Messages">
                        <i class="fas fa-envelope" style="color:black;"></i>
                    </a>
                </div>
            </div> -->

            <!-- Notifications -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>

                <div class="dropdown-menu notifications-dropdown">
                    <!-- Sticky header -->
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>

                    <!-- Scrollable list -->
                    <div class="notification-list" id="wildNotifList">
                        <?php if (empty($wildNotifs)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($wildNotifs as $nf):
                                $st = strtolower((string)($nf['approval_status'] ?? 'pending'));
                                $isIncident = !empty($nf['incident_id']);

                                // ICON + TITLE: incidents show status; permits always "New wildlife permit request"
                                if ($isIncident) {
                                    $icon = $st === 'approved' ? 'fa-check-circle'
                                        : ($st === 'rejected' ? 'fa-times-circle' : 'fa-exclamation-triangle');
                                    $title = 'Incident ' . ucfirst($st);
                                } else {
                                    $icon = 'fa-file'; // neutral icon for permit requests
                                    $title = 'New wildlife permit request';
                                }

                                $permit = strtolower((string)($nf['permit_type'] ?? ''));
                                $who = h((string)($nf['client_first'] ?? 'A client'));
                                $msg = trim((string)($nf['message'] ?? ''));
                                if ($msg === '') {
                                    $msg = $who . ' requested a wildlife ' . ($permit ?: 'new') . ' permit.';
                                }

                                $ago = time_elapsed_string($nf['created_at'] ?? date('c'));
                                if (!empty($nf['incident_id'])) {
                                    $href = 'reportaccident.php?focus=' . urlencode((string)$nf['incident_id']);
                                } elseif (!empty($nf['approval_id'])) {
                                    $href = 'wildeach.php?id=' . urlencode((string)$nf['approval_id']);
                                } else {
                                    $href = 'wildnotification.php';
                                }

                                $isRead = ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1');
                            ?>
                                <div class="notification-item <?= $isRead ? '' : 'unread' ?> status-<?= h($st) ?>"
                                    data-notif-id="<?= h((string)($nf['notif_id'] ?? '')) ?>"
                                    data-incident-id="<?= h((string)($nf['incident_id'] ?? '')) ?>">
                                    <a href="<?= h($href) ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <i class="fas <?= h($icon) ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($msg) ?></div>
                                            <div class="notification-time"><?= h($ago) ?></div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Sticky footer -->
                    <div class="notification-footer">
                        <a href="wildnotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon <?php echo $current_page === 'forestry-profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i><span>Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-particles"></div>
        <div class="hero-content">
            <h1 class="hero-title">
                ECOTRACK: A RESOURCE TRACKING AND INVENTORY SYSTEM OF DEPARTMENT OF ENVIRONMENT AND NATURAL RESOURCES, ARGAO
            </h1>
            <p class="hero-subtitle">Preserving biodiversity through advanced monitoring and conservation</p>

            <div class="hero-quote-container">
                <p class="hero-quote">
                    "In the end, we will conserve only what we love, we will love only what we understand, and we will understand only what we are taught."
                </p>
                <div class="hero-attribution">— Baba Dioum, Senegalese Conservationist</div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile hamburger
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            mobileToggle?.addEventListener('click', () => {
                navContainer.classList.toggle('active');
            });

            // Dropdown behavior (hover desktop / click mobile)
            const dropdowns = document.querySelectorAll('[data-dropdown]');
            const isTouch = matchMedia('(pointer: coarse)').matches;

            dropdowns.forEach(dd => {
                const trigger = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');
                if (!trigger || !menu) return;

                const open = () => {
                    dd.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(0)' :
                        'translateY(0)';
                };
                const close = () => {
                    dd.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(10px)' :
                        'translateY(10px)';
                    if (isTouch) menu.style.display = 'none';
                };

                if (!isTouch) {
                    dd.addEventListener('mouseenter', open);
                    dd.addEventListener('mouseleave', (e) => {
                        if (!dd.contains(e.relatedTarget)) close();
                    });
                } else {
                    trigger.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const openNow = dd.classList.contains('open');
                        document.querySelectorAll('[data-dropdown].open')
                            .forEach(o => {
                                if (o !== dd) o.classList.remove('open');
                            });
                        if (openNow) {
                            close();
                        } else {
                            menu.style.display = 'block';
                            open();
                        }
                    });
                }
            });

            // Close menus when clicking outside (mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-dropdown]')) {
                    document.querySelectorAll('[data-dropdown].open').forEach(dd => {
                        const menu = dd.querySelector('.dropdown-menu');
                        dd.classList.remove('open');
                        if (menu) {
                            menu.style.opacity = '0';
                            menu.style.visibility = 'hidden';
                            menu.style.transform = menu.classList.contains('center') ?
                                'translateX(-50%) translateY(10px)' :
                                'translateY(10px)';
                            if (matchMedia('(pointer: coarse)').matches) {
                                menu.style.display = 'none';
                            }
                        }
                    });
                }
            });

            // Mark all as read (optimistic; optional server call already exists)
            const markAll = document.getElementById('markAllRead');
            markAll?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('#wildNotifList .notification-item.unread')
                    .forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }

                try {
                    await fetch('../backend/notifications/mark_all_read.php?type=wildlife', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                } catch (_) {
                    /* ignore */
                }
            });

            // CLICK: mark a single notification/incident as read, then navigate
            document.getElementById('wildNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                const item = link.closest('.notification-item');
                if (!item) return;

                e.preventDefault();
                const href = link.getAttribute('href') || 'wildnotification.php';
                const notifId = item.getAttribute('data-notif-id') || '';
                const incidentId = item.getAttribute('data-incident-id') || '';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    if (incidentId) form.set('incident_id', incidentId);

                    await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {
                    /* ignore */
                }

                // Optimistic UI (remove unread highlight, decrement badge)
                item.classList.remove('unread');
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    const n = parseInt(badge.textContent || '0', 10) || 0;
                    const next = Math.max(0, n - 1);
                    badge.textContent = String(next);
                    if (next <= 0) badge.style.display = 'none';
                }

                // Navigate afterward
                window.location.href = href;
            });

            // Decorative particles
            const particles = document.querySelector('.hero-particles');
            if (particles) {
                for (let i = 0; i < 50; i++) {
                    const s = document.createElement('span');
                    s.className = 'particle';
                    s.style.left = (Math.random() * 100) + '%';
                    s.style.top = (Math.random() * 100) + '%';
                    const size = Math.random() * 6 + 2;
                    s.style.width = s.style.height = size + 'px';
                    s.style.animationDelay = (Math.random() * 15) + 's';
                    s.style.animationDuration = (Math.random() * 10 + 10) + 's';
                    particles.appendChild(s);
                }
            }

            // Parallax-ish hero
            window.addEventListener('scroll', () => {
                const sc = window.pageYOffset;
                const hero = document.querySelector('.hero-section');
                const content = document.querySelector('.hero-content');
                if (hero && content) {
                    hero.style.backgroundPositionY = `${sc * 0.5}px`;
                    content.style.transform = `translateY(${sc * 0.2}px)`;
                }
            });
        });
    </script>
</body>

</html>