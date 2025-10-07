<?php

declare(strict_types=1);
session_start();

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Postgres)

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
    $isMarine = $u && strtolower((string)$u['department']) === 'tree cutting';
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

/* ---------- Helpers used by the header ---------- */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function time_elapsed_string($datetime, $full = false): string
{
    if (!$datetime) return '';
    $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago  = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
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

/* ---------- Notifications for the header (Tree Cutting) ---------- */
$treeNotifs = [];
$incRows    = [];
$unreadTree = 0;

try {
    // Permit notifications addressed to 'Tree Cutting'
    $notifRows = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               a.approval_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name  AS client_first, c.last_name AS client_last
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'tree cutting'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ");
    $treeNotifs = $notifRows ? $notifRows->fetchAll(PDO::FETCH_ASSOC) : [];

    // Unread counts (permits + incidents)
    $unreadPermits = (int)$pdo->query("
        SELECT COUNT(*) FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", ''))='tree cutting' AND n.is_read=false
    ")->fetchColumn();

    $unreadIncidents = (int)$pdo->query("
        SELECT COUNT(*) FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='tree cutting' AND is_read=false
    ")->fetchColumn();

    $unreadTree = $unreadPermits + $unreadIncidents;

    // Incident rows (category = Tree Cutting)
    $incRows = $pdo->query("
        SELECT incident_id,
               COALESCE(NULLIF(btrim(more_description), ''), COALESCE(NULLIF(btrim(what), ''), '(no description)')) AS body_text,
               status, is_read, created_at
        FROM public.incident_report
        WHERE LOWER(COALESCE(category,''))='tree cutting'
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[TREE HEADER NOTIFS] ' . $e->getMessage());
    $treeNotifs = [];
    $incRows    = [];
    $unreadTree = 0;
}

// Used by the profile icon "active" state
$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forestry Monitoring System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/denr/superadmin/css/treehome.css">
    <!-- This was incorrectly linked as a stylesheet; use script instead -->
    <script src="/denr/superadmin/js/treehome.js" defer></script>
</head>

<body>
    <header>
        <div class="logo"><a href="treehome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <!-- App / hamburger -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <!-- ACTIVE on this page -->
                    <a href="requestpermits.php" class="dropdown-item active" aria-current="page">
                        <i class="fas fa-file-signature"></i><span>Request Permits</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Bell -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadTree ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="treeNotifList">
                        <?php
                        $combined = [];

                        // Permits
                        foreach ($treeNotifs as $nf) {
                            $combined[] = [
                                'id'      => $nf['notif_id'],
                                'is_read' => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'type'    => 'permit',
                                'message' => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a request.')),
                                'ago'     => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'    => 'requestpermits.php' // keep users on this page for permit notifs
                            ];
                        }

                        // Incidents
                        foreach ($incRows as $ir) {
                            $combined[] = [
                                'id'      => $ir['incident_id'],
                                'is_read' => ($ir['is_read'] === true || $ir['is_read'] === 't' || $ir['is_read'] === 1 || $ir['is_read'] === '1'),
                                'type'    => 'incident',
                                'message' => trim((string)$ir['body_text']),
                                'ago'     => time_elapsed_string($ir['created_at'] ?? date('c')),
                                'link'    => 'reportaccident.php?focus=' . urlencode((string)$ir['incident_id'])
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No tree cutting notifications</div>
                                </div>
                            </div>
                            <?php else:
                            // (Optional) Sort newest-first across both sets
                            usort($combined, fn($a, $b) => strcmp($b['ago'], $a['ago'])); // lightweight; server-side already orders each set
                            foreach ($combined as $item):
                                $title = $item['type'] === 'permit' ? 'Permit request' : 'Incident report';
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= $item['type'] === 'permit' ? h($item['id']) : '' ?>"
                                    data-incident-id="<?= $item['type'] === 'incident' ? h($item['id']) : '' ?>">
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

                    <div class="notification-footer"><a href="reportaccident.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <!-- Profile -->
            <div class="nav-item dropdown" data-dropdown>
                <div class="nav-icon <?php echo $current_page === 'forestry-profile' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="title-container">
            <h1>ECOTRACK: A RESOURCE TRACKING AND INVENTORY SYSTEM OF DEPARTMENT OF ENVIRONMENT AND NATURAL
                RESOURCES , ARGAO
            </h1>
            <div class="title-line"></div>
        </div>
        <div class="content">
            <img src="logo.png" alt="DENR Logo" class="denr-logo">
            <div class="text-content">
                <h2>Preserving Our Natural Heritage</h2>
                <p>Advanced monitoring and management of <span class="highlight">forest resources</span> to ensure sustainability for future generations.</p>
                <p>Empowering communities through <span class="highlight">responsible stewardship</span> and innovative conservation practices.</p>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                    document.body.style.overflow = navContainer.classList.contains('active') ? 'hidden' : '';
                });
            }

            // Dropdown functionality for desktop and mobile
            const dropdowns = document.querySelectorAll('.dropdown');
            const isMobile = window.innerWidth <= 992;

            function setupDropdowns() {
                dropdowns.forEach(dropdown => {
                    const toggle = dropdown.querySelector('.nav-icon');
                    const menu = dropdown.querySelector('.dropdown-menu');
                    if (!toggle || !menu) return;

                    if (isMobile) {
                        // Mobile behavior - click to toggle
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
                                toggle.setAttribute('aria-expanded', 'false');
                            } else {
                                menu.style.display = 'block';
                                toggle.setAttribute('aria-expanded', 'true');
                            }
                        });

                        // Close dropdown when clicking outside
                        document.addEventListener('click', (e) => {
                            if (!dropdown.contains(e.target)) {
                                menu.style.display = 'none';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });
                    } else {
                        // Desktop behavior - hover to show
                        dropdown.addEventListener('mouseenter', () => {
                            menu.style.opacity = '1';
                            menu.style.visibility = 'visible';
                            menu.style.transform = menu.classList.contains('center') ?
                                'translateX(-50%) translateY(0)' :
                                'translateY(0)';
                            toggle.setAttribute('aria-expanded', 'true');
                        });

                        dropdown.addEventListener('mouseleave', (e) => {
                            if (!dropdown.contains(e.relatedTarget)) {
                                menu.style.opacity = '0';
                                menu.style.visibility = 'hidden';
                                menu.style.transform = menu.classList.contains('center') ?
                                    'translateX(-50%) translateY(8px)' :
                                    'translateY(8px)';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });

                        menu.addEventListener('mouseleave', (e) => {
                            if (!dropdown.contains(e.relatedTarget)) {
                                menu.style.opacity = '0';
                                menu.style.visibility = 'hidden';
                                menu.style.transform = menu.classList.contains('center') ?
                                    'translateX(-50%) translateY(8px)' :
                                    'translateY(8px)';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });
                    }
                });
            }

            // Initialize dropdowns based on screen size
            setupDropdowns();

            // Mark all notifications as read (UI-only on this page)
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.querySelector('#notifDropdown .badge');
                    if (badge) {
                        badge.textContent = '0';
                        badge.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>

</html>