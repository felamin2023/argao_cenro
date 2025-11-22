<?php
// wildlife/reportaccident.php (Wildlife admin page)
declare(strict_types=1);
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
            $pdo->exec("
                UPDATE public.notifications
                   SET is_read = true
                 WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false
            ");
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

/* ---- data needed by your pasted UI (badge + lists) ---- */
$wildNotifs = [];
$incRows = [];
$unreadWildlife = 0;

try {
    $wildNotifs = $pdo->query("
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

    $incRows = [];
} catch (Throwable $e) {
    error_log('[NOTIF BOOTSTRAP] ' . $e->getMessage());
    $wildNotifs = [];
    $incRows = [];
    $unreadWildlife = 0;
}

// session_start();

// Must be logged in AND an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Supabase/Postgres)

$user_id = (string)$_SESSION['user_id'];

try {
    // Ensure this admin belongs to WILDLIFE
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

/** Load Wildlife incident reports (PDO) */
$incidents = [];
try {
    $q = $pdo->prepare("
        SELECT id, who, what, \"where\", \"when\", why, status, category, created_at
        FROM public.incident_report
        WHERE lower(category) = lower(:cat)
        ORDER BY created_at DESC
    ");
    // Category exactly as requested; query is case-insensitive
    $q->execute([':cat' => 'WildLife Monitoring']);
    $incidents = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[WILDLIFE LIST] ' . $e->getMessage());
    $incidents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wildlife Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/treereportincident.css">
    <!-- Fix: load JS as a script, not a stylesheet -->
    <script defer src="/denr/superadmin/js/reportaccident.js"></script>

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
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="wildhome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i><span>Wildlife Management</span>
                    </a>
                    <a href="wildpermit.php" class="dropdown-item">
                        <i class="fas fa-paw"></i><span>Wildlife Permit</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item active-page">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>



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

            <div class="nav-item dropdown">
                <div class="nav-icon">
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

       <div class="CHAINSAW-RECORDS">
        <div class="container">
           <div class="header-section">
    <h1 class="title">Incident Reports</h1>
    
    <div class="centered-controls">
        <div class="status-filter-container">
            <label for="status-filter-select" class="status-filter-label">Status</label>
            <select id="status-filter-select" class="status-filter-select">
                <option value="all">All</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        
        <div class="search">
            <input type="text" class="search-input" placeholder="Search...">
            <div class="search-icon">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>
    
    <div class="filter-export-buttons">
        <!-- Your filter and export buttons here -->
        <div class="filter">
           
        </div>
        <div class="export">
           
        </div>
    </div>
</div>

            <!-- Table -->
            <div class="table-container">
                <table class="accident-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:5%;">ID</th>
                            <th class="text-center" style="width:10%;">WHO</th>
                            <th class="text-center" style="width:10%;">WHAT</th>
                            <th class="text-center" style="width:10%;">WHERE</th>
                            <th class="text-center" style="width:10%;">WHEN</th>
                            <th class="text-center" style="width:10%;">WHY</th>
                            <th class="text-center" style="width:10%;">STATUS</th>
                            <th class="text-center" style="width:15%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['id']) ?></td>
                                <td><?= htmlspecialchars((string)$row['who']) ?></td>
                                <td><?= htmlspecialchars((string)$row['what']) ?></td>
                                <td><?= htmlspecialchars((string)$row['where']) ?></td>
                                <td><?= htmlspecialchars($row['when'] ? (new DateTime($row['when']))->format('Y-m-d H:i') : '') ?></td>
                                <td><?= htmlspecialchars((string)$row['why']) ?></td>
                                <?php
                                $statusRaw = (string)($row['status'] ?? '');
                                $statusKey = strtolower(trim($statusRaw));
                                if ($statusKey === '') {
                                    $statusClass = 'pending';
                                    $statusLabel = 'Pending';
                                } else {
                                    $statusClassSanitized = preg_replace('/[^a-z0-9]+/', '-', $statusKey);
                                    $statusClassSanitized = $statusClassSanitized !== '' ? $statusClassSanitized : 'unknown';
                                    $knownStatuses = ['pending', 'approved', 'resolved', 'rejected'];
                                    $statusClass = in_array($statusClassSanitized, $knownStatuses, true) ? $statusClassSanitized : 'unknown';
                                    $labelSource = str_replace(['-', '_'], ' ', $statusKey);
                                    $statusLabel = ucwords($labelSource);
                                }
                                ?>
                                <td>
                                    <span class="status-pill status-pill--<?= htmlspecialchars($statusClass, ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="view-btn" data-id="<?= htmlspecialchars((string)$row['id']) ?>">View</button>

                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>

                <!-- Full width placeholder shown when there are no visible records -->
                <div id="no-record-placeholder" style="text-align: center; display: <?= empty($incidents) ? 'block' : 'none' ?>; padding:12px; background:#f5f5f5; border-radius:4px; margin-top:8px;">
                    No record found
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width:900px;display:flex;background:white;border-radius:8px;overflow:hidden;">
            <span class="close-modal" id="closeViewModal" style="position:absolute;right:20px;top:10px;font-size:28px;cursor:pointer;z-index:1;">&times;</span>

            <div style="flex:1;padding:20px;border-right:1px solid #eee;">
                <h3 style="margin-top:0;color:#005117;">Incident Report Details</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div><strong>ID:</strong> <span id="modal-id"></span></div>
                    <div><strong>Who:</strong> <span id="modal-who"></span></div>
                    <div><strong>What:</strong> <span id="modal-what"></span></div>
                    <div><strong>Where:</strong> <span id="modal-where"></span></div>
                    <div><strong>When:</strong> <span id="modal-when"></span></div>
                    <div><strong>Why:</strong> <span id="modal-why"></span></div>
                    <div><strong>Contact No:</strong> <span id="modal-contact"></span></div>
                    <div><strong>Category:</strong> <span id="modal-category"></span></div>
                    <div><strong>Status:</strong>
                        <select id="modal-status" style="padding:5px;border-radius:4px;border:1px solid #ccc;">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="resolved">Resolved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div><strong>Created At:</strong> <span id="modal-created-at"></span></div>
                </div>
                <div class="modal-actions" style="margin-top:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button id="update-status-btn" style="padding:8px 16px;background:#005117;color:white;border:none;border-radius:4px;cursor:pointer;">Update</button>
                    <span id="resolved-lock-note" class="resolved-lock-note" style="display:none;">This incident is already resolved and can no longer be updated.</span>
                </div>
            </div>

            <div style="flex:1;padding:20px;">
                <h3 style="margin-top:0;color:#005117;">Description</h3>
                <div id="modal-description" style="margin-bottom:20px;padding:10px;background:#f5f5f5;border-radius:4px;min-height:100px;"></div>

                <h3 style="color:#005117;">Photos</h3>
                <div id="modal-photos" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;"></div>
            </div>
        </div>
    </div>

    <!-- Status Change Confirmation Modal -->
    <div id="confirmStatusModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmStatusModal" class="close-modal">&times;</span>
            <h3>Confirm Status Change</h3>
            <p>Are you sure you want to change the status of this incident report?</p>
            <button id="confirmStatusBtn" class="btn btn-primary" style="margin:10px 10px 0 0;padding:8px 16px;background:#005117;color:white;border:none;border-radius:4px;cursor:pointer;">Yes, Change</button>
            <button id="cancelStatusBtn" class="btn btn-outline" style="padding:8px 16px;background:#f5f5f5;color:#333;border:1px solid #ccc;border-radius:4px;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectReasonModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeRejectReasonModal" class="close-modal">&times;</span>
            <h3>Rejection Reason</h3>
            <textarea id="rejection-reason" style="width:100%;height:100px;margin-bottom:10px;padding:8px;" placeholder="Enter rejection reason..."></textarea>
            <button id="confirmRejectBtn" class="btn btn-primary" style="margin:10px 10px 0 0;padding:8px 16px;background:#d9534f;color:white;border:none;border-radius:4px;cursor:pointer;">Confirm Reject</button>
            <button id="cancelRejectBtn" class="btn btn-outline" style="padding:8px 16px;background:#f5f5f5;color:#333;border:1px solid #ccc;border-radius:4px;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <!-- Image View Modal -->
    <div id="imageModal" class="modal">
        <span id="closeImageModal" class="close-modal" style="position:absolute;right:20px;top:10px;font-size:28px;cursor:pointer;color:white;z-index:1001;">&times;</span>
        <img id="expandedImg" style="width:auto;height:auto;max-width:90%;max-height:90%;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
    </div>

    <!-- Notification -->
    <div id="profile-notification" style="display:none;position:fixed;top:5px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:16px 32px;border-radius:8px;font-size:1.1rem;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,0.15);text-align:center;min-width:220px;max-width:90vw;"></div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
                    if (!e.target.closest('#notifDropdown')) close();
                });
            }

            // Mark ALL as read
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();

                // optimistic UI
                document.querySelectorAll('#wildNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
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
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // ===== Modals & shared UI =====
            const viewModal = document.getElementById("viewModal");
            const confirmStatusModal = document.getElementById("confirmStatusModal");
            const rejectReasonModal = document.getElementById("rejectReasonModal");
            const imageModal = document.getElementById("imageModal");
            const expandedImg = document.getElementById("expandedImg");

            const closeViewModal = document.getElementById("closeViewModal");
            const closeConfirmStatusModal = document.getElementById("closeConfirmStatusModal");
            const closeRejectReasonModal = document.getElementById("closeRejectReasonModal");
            const closeImageModal = document.getElementById("closeImageModal");

            const updateStatusBtn = document.getElementById("update-status-btn");
            const confirmStatusBtn = document.getElementById("confirmStatusBtn");
            const cancelStatusBtn = document.getElementById("cancelStatusBtn");
            const confirmRejectBtn = document.getElementById("confirmRejectBtn");
            const cancelRejectBtn = document.getElementById("cancelRejectBtn");
            const statusSelect = document.getElementById("modal-status");
            const resolvedLockNote = document.getElementById("resolved-lock-note");
            const rejectionReasonInput = document.getElementById("rejection-reason");

            const notificationEl = document.getElementById("profile-notification");
            let currentReportId = null;
            let currentReportLocked = false;

            function showNotification(message) {
                notificationEl.textContent = message;
                notificationEl.style.display = "block";
                setTimeout(() => (notificationEl.style.display = "none"), 3000);
            }

            function resetStatusLock() {
                currentReportLocked = false;
                statusSelect.disabled = false;
                updateStatusBtn.disabled = false;
                updateStatusBtn.title = "";
                // Ensure update button is visible when modal resets
                updateStatusBtn.style.display = "inline-block";
                statusSelect.classList.remove("status-readonly");
                if (resolvedLockNote) {
                    resolvedLockNote.style.display = "none";
                }
            }

            // ===== View button → open details modal =====
            document.addEventListener("click", async (e) => {
                if (!e.target.classList.contains("view-btn")) return;

                const reportId = e.target.getAttribute("data-id");
                currentReportId = reportId;
                resetStatusLock();

                try {
                    const res = await fetch(`../backend/admins/incidentReport/get_incident_report.php?id=${encodeURIComponent(reportId)}`);
                    let report = null;
                    try {
                        report = await res.json();
                    } catch {
                        report = {
                            error: 'Bad JSON'
                        };
                    }

                    if (!res.ok || report.error) {
                        console.error("API error", {
                            status: res.status,
                            report
                        });
                        showNotification(report.error || "Error loading report details");
                        return;
                    }

                    // Fill basic fields
                    document.getElementById("modal-id").textContent = report.id ?? "";
                    document.getElementById("modal-who").textContent = report.who ?? "";
                    document.getElementById("modal-what").textContent = report.what ?? "";
                    document.getElementById("modal-where").textContent = report.where ?? "";
                    document.getElementById("modal-when").textContent = report.when ?? "";
                    document.getElementById("modal-why").textContent = report.why ?? "";
                    document.getElementById("modal-contact").textContent = report.contact_no ?? "";
                    document.getElementById("modal-category").textContent = report.category ?? "";
                    document.getElementById("modal-description").textContent = report.description ?? "";
                    document.getElementById("modal-created-at").textContent = report.created_at ?? "";

                    // Status dropdown
                    statusSelect.value = (report.status || "pending").toLowerCase();
                    const isResolved = (report.status || "").toLowerCase() === "resolved";
                    currentReportLocked = isResolved;
                    statusSelect.disabled = isResolved;
                    updateStatusBtn.disabled = isResolved;
                    updateStatusBtn.title = isResolved ? "Resolved incidents can no longer be updated" : "";
                    // Hide the update button entirely for resolved reports
                    updateStatusBtn.style.display = isResolved ? "none" : "inline-block";
                    statusSelect.classList.toggle("status-readonly", isResolved);
                    if (resolvedLockNote) {
                        resolvedLockNote.style.display = isResolved ? "block" : "none";
                    }

                    // Photos (Supabase public/signed URLs)
                    const photosContainer = document.getElementById("modal-photos");
                    photosContainer.innerHTML = "";
                    const urls = Array.isArray(report.photo_urls) ? report.photo_urls : [];
                    urls.forEach((url) => {
                        const img = document.createElement("img");
                        img.src = url;
                        img.className = "photo-thumbnail";
                        img.alt = "Incident photo";
                        img.style.width = "100%";
                        img.style.height = "auto";
                        img.style.cursor = "pointer";
                        img.style.borderRadius = "4px";
                        img.loading = "lazy";
                        img.onerror = () => {
                            const note = document.createElement("div");
                            note.style.fontSize = "12px";
                            note.style.color = "#b91c1c";
                            note.textContent = "Image failed → " + url;
                            photosContainer.appendChild(note);
                        };
                        img.addEventListener("click", () => {
                            expandedImg.src = url;
                            imageModal.style.display = "block";
                            document.body.style.overflow = "hidden";
                        });
                        photosContainer.appendChild(img);
                    });

                    // Show modal
                    viewModal.style.display = "block";
                    document.body.style.overflow = "hidden";
                } catch (err) {
                    console.error(err);
                    showNotification("Error loading report details");
                }
            });

            // ===== Update Status flow =====
            updateStatusBtn.addEventListener("click", () => {
                if (currentReportLocked) {
                    showNotification("Resolved incidents can no longer be updated");
                    return;
                }
                const newStatus = statusSelect.value;
                if (newStatus === "rejected") {
                    rejectReasonModal.style.display = "block";
                    rejectionReasonInput.value = "";
                } else {
                    confirmStatusModal.style.display = "block";
                }
            });

            confirmStatusBtn.addEventListener("click", async () => {
                const reportId = document.getElementById("modal-id").textContent;
                const newStatus = statusSelect.value;
                if (currentReportLocked) {
                    showNotification("Resolved incidents can no longer be updated");
                    confirmStatusModal.style.display = "none";
                    return;
                }

                try {
                    const res = await fetch("../backend/admins/incidentReport/update_incident_status.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            id: reportId,
                            status: newStatus,
                            user_id: "<?= htmlspecialchars((string)$user_id, ENT_QUOTES) ?>",
                        }),
                    });
                    const result = await res.json();

                    if (res.ok && result.success) {
                        showNotification(`Status changed to ${newStatus}`);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification(result.message || "Error updating status");
                    }
                } catch (err) {
                    console.error(err);
                    showNotification("Error updating status");
                } finally {
                    confirmStatusModal.style.display = "none";
                    closeModal(viewModal);
                }
            });

            // Reject flow (requires reason)
            confirmRejectBtn.addEventListener("click", async () => {
                const rejectionReason = rejectionReasonInput.value.trim();
                if (!rejectionReason) {
                    showNotification("Please enter a rejection reason");
                    return;
                }
                if (currentReportLocked) {
                    showNotification("Resolved incidents can no longer be updated");
                    rejectReasonModal.style.display = "none";
                    return;
                }

                try {
                    const res = await fetch("../backend/admins/incidentReport/update_incident_status.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            id: currentReportId,
                            status: "rejected",
                            rejection_reason: rejectionReason,
                            user_id: "<?= htmlspecialchars((string)$user_id, ENT_QUOTES) ?>",
                        }),
                    });
                    const result = await res.json();

                    if (res.ok && result.success) {
                        showNotification("Incident report rejected");
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification(result.message || "Error rejecting report");
                    }
                } catch (err) {
                    console.error(err);
                    showNotification("Error rejecting report");
                } finally {
                    rejectReasonModal.style.display = "none";
                    closeModal(viewModal);
                }
            });

            // ===== Delete from table =====
            document.addEventListener("click", async (e) => {
                if (!e.target.classList.contains("delete-btn")) return;
                const id = e.target.getAttribute("data-id");
                if (!confirm("Delete this incident report? This cannot be undone.")) return;

                try {
                    const res = await fetch("../backend/admins/incidentReport/delete_incident_report.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            id,
                            user_id: "<?= htmlspecialchars((string)$user_id, ENT_QUOTES) ?>"
                        }),
                    });
                    const out = await res.json();
                    if (!res.ok || !out.success) {
                        console.error("Delete failed", out);
                        showNotification(out.message || "Delete failed");
                        return;
                    }
                    const row = e.target.closest("tr");
                    row?.parentNode?.removeChild(row);
                    showNotification("Incident deleted");
                    try {
                        updateNoRecordRow();
                    } catch (_) {}
                } catch (err) {
                    console.error(err);
                    showNotification("Error deleting report");
                }
            });

            // ===== Close modal handlers =====
            const closeModal = (el) => {
                el.style.display = "none";
                document.body.style.overflow = "auto";
                if (el === viewModal) {
                    resetStatusLock();
                }
            };
            closeViewModal.addEventListener("click", () => closeModal(viewModal));
            closeConfirmStatusModal.addEventListener("click", () => (confirmStatusModal.style.display = "none"));
            closeRejectReasonModal.addEventListener("click", () => (rejectReasonModal.style.display = "none"));
            closeImageModal.addEventListener("click", () => closeModal(imageModal));

            window.addEventListener("click", (event) => {
                if (event.target === viewModal) closeModal(viewModal);
                if (event.target === confirmStatusModal) confirmStatusModal.style.display = "none";
                if (event.target === rejectReasonModal) rejectReasonModal.style.display = "none";
                if (event.target === imageModal) closeModal(imageModal);
            });

            // ===== Status filter dropdown =====
            const statusDropdown = document.getElementById("status-dropdown");

            // Update visibility of the dedicated "no record" row depending on
            // whether any data rows are currently visible.
            function updateNoRecordRow() {
                const tbody = document.querySelector('.accident-table tbody');
                if (!tbody) return;
                const dataRows = Array.from(tbody.querySelectorAll('tr'));
                const visibleCount = dataRows.filter(r => r.style.display !== 'none').length;
                const noRecordDiv = document.getElementById('no-record-placeholder');
                if (noRecordDiv) {
                    noRecordDiv.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }

            if (statusDropdown) {
                statusDropdown.addEventListener("change", () => {
                    const status = statusDropdown.value || "all";
                    const rows = document.querySelectorAll('.accident-table tbody tr');
                    rows.forEach((row) => {
                        if (status === "all") {
                            row.style.display = "";
                        } else {
                            const rowStatus = (row.cells[6]?.textContent || "").trim().toLowerCase();
                            row.style.display = rowStatus === status ? "" : "none";
                        }
                    });
                    updateNoRecordRow();
                });
            }

            // ===== Search (icon click or Enter) =====
            const searchInput = document.getElementById("search-input");
            const searchIcon = document.getElementById("search-icon");

            function performSearch() {
                const term = (searchInput.value || "").toLowerCase();
                const rows = document.querySelectorAll('.accident-table tbody tr');
                const searchableIndices = [1, 2, 3, 4, 5];
                rows.forEach((row) => {
                    let match = false;
                    for (const idx of searchableIndices) {
                        if ((row.cells[idx]?.textContent || "").toLowerCase().includes(term)) {
                            match = true;
                            break;
                        }
                    }
                    row.style.display = match ? "" : "none";
                });
                updateNoRecordRow();
            }
            if (searchIcon) searchIcon.addEventListener("click", performSearch);
            if (searchInput) {
                searchInput.addEventListener("keypress", (e) => {
                    if (e.key === "Enter") performSearch();
                });
                // Also search on input change for real-time filtering
                searchInput.addEventListener("input", performSearch);
            }

            // ===== Export CSV =====
            const exportButton = document.getElementById("export-button");
            if (exportButton) {
                exportButton.addEventListener("click", () => {
                    let csv = "data:text/csv;charset=utf-8,";
                    const headers = [];
                    document.querySelectorAll(".accident-table th").forEach((h) => {
                        headers.push(`"${h.textContent.replace(/"/g, '""')}"`);
                    });
                    csv += headers.join(",") + "\r\n";

                    document.querySelectorAll(".accident-table tbody tr").forEach((row) => {
                        if (row.style.display === "none") return;
                        const cols = [];
                        row.querySelectorAll("td").forEach((cell, idx) => {
                            if (idx !== 7) cols.push(`"${(cell.textContent || '').replace(/"/g, '""')}"`); // skip Actions col
                        });
                        csv += cols.join(",") + "\r\n";
                    });

                    const uri = encodeURI(csv);
                    const a = document.createElement("a");
                    a.href = uri;
                    a.download = "incident_report_" + new Date().toISOString().slice(0, 10) + ".csv";
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                });
            }
            // Ensure the no-record row visibility is correct on initial load
            try {
                updateNoRecordRow();
            } catch (_) {}
        });
    </script>

</body>

</html>