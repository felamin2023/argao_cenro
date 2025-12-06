<?php
// seedlings/reportaccident.php (Seedlings-only page)
declare(strict_types=1);

session_start();

// Must be logged in AND an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Supabase/Postgres)

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

date_default_timezone_set('Asia/Manila');

$user_id = (string)$_SESSION['user_id'];

/* ---- Helper functions ---- */
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

try {
    // Ensure this admin belongs to SEEDLING
    $st = $pdo->prepare("
        SELECT role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin     = $u && strtolower((string)$u['role']) === 'admin';
    $isSeedling  = $u && strtolower((string)$u['department']) === 'seedling';
    if (!$isAdmin || !$isSeedling) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SEEDLING-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

/* ---- NOTIFS for header ---- */
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

/** Load Seedlings incident reports (PDO) */
$incidents = [];
try {
    $q = $pdo->prepare("
        SELECT id, who, what, \"where\", \"when\", why, status, category, created_at
        FROM public.incident_report
        WHERE lower(category) = lower(:cat)
        ORDER BY created_at DESC
    ");
    // Seedlings category
    $q->execute([':cat' => 'Seedlings Monitoring']);
    $incidents = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[SEEDLING LIST] ' . $e->getMessage());
    $incidents = [];
}

// For header badges (replace with real counts if you want)
$current_page = basename($_SERVER['PHP_SELF']);
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Seedlings Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/treereportincident.css">
    <!-- Fix: this is JS, not CSS -->
    <script defer src="/denr/superadmin/js/reportaccident.js"></script>

    <style>
      
 .nav-item .badge {
    position: absolute;
    top: -2px;
    right: 4px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    min-width: 19px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    z-index: 100;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    /* Prevent all animations/transforms on the badge */
    transition: none !important;
    animation: none !important;
    transform: none !important;
    will-change: transform;
}

/* Ensure parent doesn't affect badge position */
.nav-icon {
    position: relative;
    transform-style: flat; /* Prevent 3D transforms from affecting children */
    backface-visibility: hidden; /* Improve rendering stability */
}

/* Specifically target the notification dropdown badge */
#notifDropdown .badge {
    top: -2px !important;
    right: 4px !important;
    position: absolute;
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

        .mark-all-read {
            color: #1b5e20;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .mark-all-read:hover {
            text-decoration: underline;
        }

.accident-table th,
.accident-table td {
  border: 1px solid #e0e0e0;
  padding: 12px 8px;
  text-align: center;
  word-wrap: break-word;
  font-size: 16px;
  font-family: "calibri";
  vertical-align: middle;
  line-height: 1.4;
}
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="seedlingshome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="incoming.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i><span class="item-text">Seedlings Received</span>
                    </a>


                    <a href="reportaccident.php" class="dropdown-item active-page">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>

                    <a href="user_requestseedlings.php" class="dropdown-item">
                        <i class="fas fa-paper-plane"></i><span>Seedlings Request</span>
                    </a>
                </div>
            </div>



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

            <div class="nav-item dropdown">
                <div class="nav-icon <?= $current_page === 'forestry-profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span class="item-text">Edit Profile</span></a>
                    <a href="../superlogin.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span class="item-text">Logout</span></a>
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
            <input type="text" id="search-input" class="search-input" placeholder="Search...">
            <div class="search-icon" id="search-icon">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>


                <!-- <div class="export">
                    <button class="export-button" id="export-button">
                        <img src="https://c.animaapp.com/uJwjYGDm/img/vector-1.svg" alt="Export" class="export-icon">
                    </button>
                    <span class="export-label">Export as CSV</span>
                </div> -->
            </div>

            <!-- Status filter (select) placed above -->

            <!-- Table -->
            <div class="table-container">
                <table class="accident-table">
                    <thead>
                        <tr>
                            <th style="width:5%;">ID</th>
                            <th style="width:10%;">WHO</th>
                            <th style="width:10%;">WHAT</th>
                            <th style="width:10%;">WHERE</th>
                            <th style="width:10%;">WHEN</th>
                            <th style="width:10%;">WHY</th>
                            <th style="width:10%;">STATUS</th>
                            <th style="width:15%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$incidents): ?>
                            <tr>
                                <td colspan="8">No incident reports found</td>
                            </tr>
                        <?php else: ?>
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
                            <!-- Removed table-row placeholder. A full-width div below the table will show instead. -->
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Full-width no-results placeholder (hidden by default). Shown when no records match filters/search -->
                <div id="no-results-full" style="display:none;padding:18px;background:#f5f5f5;border-radius:6px;margin-top:10px;color:#333;text-align:center;font-weight:600;">No record found</div>
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
                <div style="margin-top:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
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

            // ===== View button → open details modal =====
            document.addEventListener("click", async (e) => {
                if (!e.target.classList.contains("view-btn")) return;

                const reportId = e.target.getAttribute("data-id");
                currentReportId = reportId;

                try {
                    // Reuse the same unified endpoint that returns photo_urls
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

                    // Photos (USE URLs FROM API)
                    const photosContainer = document.getElementById("modal-photos");
                    photosContainer.innerHTML = "";
                    const urls = Array.isArray(report.photo_urls) ? report.photo_urls : [];
                    urls.forEach((url) => {
                        const img = document.createElement("img");
                        img.src = url; // Supabase public/signed URL
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

                    // Reset UI state then show modal
                    currentReportLocked = false;
                    if (statusSelect) statusSelect.disabled = false;
                    if (updateStatusBtn) {
                        updateStatusBtn.disabled = false;
                        updateStatusBtn.style.display = 'inline-block';
                        updateStatusBtn.title = '';
                    }
                    if (resolvedLockNote) resolvedLockNote.style.display = 'none';

                    const isResolved = (report.status || "").toLowerCase() === "resolved";
                    currentReportLocked = isResolved;
                    if (statusSelect) statusSelect.disabled = isResolved;
                    if (updateStatusBtn) {
                        updateStatusBtn.disabled = isResolved;
                        updateStatusBtn.style.display = isResolved ? 'none' : 'inline-block';
                        updateStatusBtn.title = isResolved ? 'Resolved incidents can no longer be updated' : '';
                    }
                    if (resolvedLockNote) resolvedLockNote.style.display = isResolved ? 'block' : 'none';

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
                    viewModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            // Reject flow (requires reason)
            confirmRejectBtn.addEventListener("click", async () => {
                const rejectionReason = rejectionReasonInput.value.trim();
                if (!rejectionReason) {
                    showNotification("Please enter a rejection reason");
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
                    viewModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            // ===== Delete button (table) =====
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
                    // Remove row
                    const row = e.target.closest("tr");
                    row?.parentNode?.removeChild(row);
                    showNotification("Incident deleted");
                    updateNoResults();
                } catch (err) {
                    console.error(err);
                    showNotification("Error deleting report");
                }
            });

            // ===== Close modal handlers =====
            const closeModal = (el) => {
                el.style.display = "none";
                document.body.style.overflow = "auto";
                // restore Update button when closing the view modal
                if (el === viewModal) {
                    currentReportLocked = false;
                    if (statusSelect) statusSelect.disabled = false;
                    if (updateStatusBtn) {
                        updateStatusBtn.disabled = false;
                        updateStatusBtn.style.display = 'inline-block';
                        updateStatusBtn.title = '';
                    }
                    if (resolvedLockNote) resolvedLockNote.style.display = 'none';
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

            // ===== Status filter (select) =====
            const statusSelectFilter = document.getElementById('status-filter-select');
            const noResultsDiv = document.getElementById('no-results-full');

            function updateNoResults() {
                // Collect data rows inside tbody (ignore rows that are not actual data if any)
                const dataRows = Array.from(document.querySelectorAll('.accident-table tbody tr'))
                    .filter(r => !r.classList.contains('no-results-row'));
                const anyVisible = dataRows.some(r => r.style.display !== 'none');
                if (noResultsDiv) noResultsDiv.style.display = anyVisible ? 'none' : 'block';
            }

            if (statusSelectFilter) {
                statusSelectFilter.addEventListener('change', () => {
                    const status = (statusSelectFilter.value || 'all').toLowerCase();
                    const dataRows = Array.from(document.querySelectorAll('.accident-table tbody tr'))
                        .filter(r => !r.classList.contains('no-results-row'));
                    dataRows.forEach((row) => {
                        if (status === 'all') {
                            row.style.display = '';
                        } else {
                            const rowStatus = (row.cells[6]?.textContent || '').trim().toLowerCase();
                            row.style.display = rowStatus === status ? '' : 'none';
                        }
                    });
                    updateNoResults();
                });
            }

            // ===== Search (icon click or Enter) =====
            const searchInput = document.getElementById("search-input");
            const searchIcon = document.getElementById("search-icon");

            function performSearch() {
                const term = (searchInput.value || "").toLowerCase();
                const activeStatus = (statusSelectFilter && statusSelectFilter.value) ? statusSelectFilter.value.toLowerCase() : 'all';
                const rows = Array.from(document.querySelectorAll('.accident-table tbody tr'))
                    .filter(r => !r.classList.contains('no-results-row'));
                rows.forEach((row) => {
                    // Only search relevant columns: who(1), what(2), where(3), when(4), why(5)
                    let match = false;
                    const indices = [1, 2, 3, 4, 5];
                    for (let idx of indices) {
                        if ((row.cells[idx]?.textContent || '').toLowerCase().includes(term)) {
                            match = true;
                            break;
                        }
                    }

                    // Apply status filter as well
                    if (match) {
                        if (activeStatus === 'all') {
                            row.style.display = '';
                        } else {
                            const rowStatus = (row.cells[6]?.textContent || '').trim().toLowerCase();
                            row.style.display = rowStatus === activeStatus ? '' : 'none';
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });
                updateNoResults();
            }
            if (searchIcon) searchIcon.addEventListener("click", performSearch);
            if (searchInput) {
                searchInput.addEventListener("keypress", (e) => {
                    if (e.key === "Enter") performSearch();
                });
                // Update table on every keystroke
                searchInput.addEventListener("input", performSearch);
            }

            // ===== Date filter (month/year) =====
            const filterButton = document.querySelector(".filter-button");
            const filterMonth = document.querySelector(".filter-month");
            const filterYear = document.querySelector(".filter-year");

            if (filterButton) {
                filterButton.addEventListener("click", () => {
                    const m = filterMonth.value;
                    const y = filterYear.value;
                    const rows = Array.from(document.querySelectorAll('.accident-table tbody tr'))
                        .filter(r => !r.classList.contains('no-results-row'));
                    rows.forEach((row) => {
                        const cell = row.cells[4];
                        if (!cell) return;
                        const txt = (cell.textContent || "").trim(); // expects YYYY-MM-DD...
                        const parts = txt.split("-");
                        const yy = parts[0] || "";
                        const mm = parts[1] || "";
                        const monthOk = m ? mm === m : true;
                        const yearOk = y ? yy === y : true;
                        row.style.display = monthOk && yearOk ? "" : "none";
                    });
                    updateNoResults();
                });
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

            /* ===== NOTIFICATION BELL FUNCTIONALITY ===== */
            /* Dropdowns */
            const dropdowns = document.querySelectorAll('[data-dropdown]');
            const isTouch = matchMedia('(pointer: coarse)').matches;
            dropdowns.forEach(dd => {
                const trigger = dd.querySelector('.nav-icon');
                const menu = dd.querySelector('.dropdown-menu');
                if (!trigger || !menu) return;

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

                if (!isTouch) {
                    dd.addEventListener('mouseenter', open);
                    dd.addEventListener('mouseleave', close);
                } else {
                    trigger.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        if (dd.classList.contains('open')) close();
                        else open();
                    });
                }
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-dropdown]')) {
                    document.querySelectorAll('[data-dropdown].open').forEach(dd => {
                        const menu = dd.querySelector('.dropdown-menu');
                        dd.classList.remove('open');
                        if (menu) {
                            menu.style.opacity = '0';
                            menu.style.visibility = 'hidden';
                        }
                    });
                }
            });

            /* MARK ALL AS READ */
            document.getElementById('markAllRead')?.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('#seedlingNotifList .notification-item.unread').forEach(el => el.classList.remove('unread'));
                const badge = document.querySelector('#notifDropdown .badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }

                try {
                    const res = await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_all_read', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => r.json());
                    if (!res || res.ok !== true) location.reload();
                } catch (_) {
                    location.reload();
                }
            });

            /* Click any single notification → mark read */
            document.getElementById('seedlingNotifList')?.addEventListener('click', async (e) => {
                const link = e.target.closest('.notification-link');
                if (!link) return;
                const item = link.closest('.notification-item');
                if (!item) return;
                e.preventDefault();
                const href = link.getAttribute('href') || 'reportaccident.php';
                const notifId = item.getAttribute('data-notif-id') || '';

                try {
                    const form = new URLSearchParams();
                    if (notifId) form.set('notif_id', notifId);
                    await fetch('<?php echo basename(__FILE__); ?>?ajax=mark_read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: form.toString()
                    });
                } catch (_) {}

                item.classList.remove('unread');
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

</body>

</html>