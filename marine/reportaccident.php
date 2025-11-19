<?php
// marine/reportaccident.php (Marine-only page)
declare(strict_types=1);

session_start();

// Must be logged in AND an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Supabase/Postgres)

$user_id = (string)$_SESSION['user_id'];

try {
    // Ensure this admin belongs to MARINE
    $st = $pdo->prepare("
        SELECT role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin  = $u && strtolower((string)$u['role']) === 'admin';
    $isMarine = $u && strtolower((string)$u['department']) === 'marine';
    if (!$isAdmin || !$isMarine) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[MARINE-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}

// Simple helpers (used by header)
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
        // Parse DB timestamps as UTC then convert to Asia/Manila
        try {
            $ago = new DateTime($datetime, new DateTimeZone('UTC'));
            $ago->setTimezone(new DateTimeZone('Asia/Manila'));
        } catch (Exception $e) {
            $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        }
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

// Fetch notifications for header (marine)
$marineNotifs = [];
$unreadMarine = 0;

try {
    $notifRows = $pdo->query("
        SELECT n.notif_id, n.message, n.is_read, n.created_at, n.\"from\" AS notif_from, n.\"to\" AS notif_to,
               n.incident_id, n.reqpro_id,
               a.approval_id,
               COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
               COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
               LOWER(COALESCE(a.request_type,'')) AS request_type,
               c.first_name  AS client_first, c.last_name AS client_last
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'marine'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ");
    $marineNotifs = $notifRows ? $notifRows->fetchAll(PDO::FETCH_ASSOC) : [];

    $unreadMarine = (int)$pdo->query("SELECT COUNT(*) FROM public.notifications n WHERE LOWER(COALESCE(n.\"to\", ''))='marine' AND n.is_read=false")->fetchColumn();
} catch (Throwable $e) {
    error_log('[MARINE REPORTACCIDENT NOTIFS] ' . $e->getMessage());
    $marineNotifs = [];
    $unreadMarine = 0;
}

// Used by the profile icon "active" state
$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''), '.php');

/** Load Marine incident reports (PDO) */
$incidents = [];
try {
    $q = $pdo->prepare("
        SELECT id, who, what, \"where\", \"when\", why, status, category, created_at
        FROM public.incident_report
        WHERE lower(category) = lower(:cat)
        ORDER BY created_at DESC
    ");
    $q->execute([':cat' => 'Marine Resource Monitoring']);
    $incidents = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[MARINE LIST] ' . $e->getMessage());
    $incidents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/reportaccident.css">
    <!-- (You had a JS file linked as CSS; fix to script) -->
    <script defer src="/denr/superadmin/js/reportaccident.js"></script>
</head>

<body>
    <header>
        <div class="logo">
            <a href="marinehome.php"><img src="seal.png" alt="Site Logo"></a>
        </div>

        <button class="mobile-toggle"><i class="fas fa-bars"></i></button>

        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="mpa-management.php" class="dropdown-item"><i class="fas fa-water"></i><span>MPA Management</span></a>
                    <a href="habitat.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Habitat Assessment</span></a>
                    <a href="species.php" class="dropdown-item"><i class="fas fa-fish"></i><span>Species Monitoring</span></a>
                    <a href="reports.php" class="dropdown-item"><i class="fas fa-chart-bar"></i><span>Reports & Analytics</span></a>
                    <a href="reportaccident.php" class="dropdown-item active-page"><i class="fas fa-file-invoice"></i><span>Incident Reports</span></a>
                </div>
            </div>



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
                        $combined = [];

                        // Permits / notifications
                        foreach ($marineNotifs as $nf) {
                            $combined[] = [
                                'id'          => $nf['notif_id'],
                                'notif_id'    => $nf['notif_id'],
                                'approval_id' => $nf['approval_id'] ?? null,
                                'incident_id' => $nf['incident_id'] ?? null,
                                'reqpro_id'   => $nf['reqpro_id'] ?? null,
                                'is_read'     => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'message'     => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a marine request.')),
                                'ago'         => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'        => !empty($nf['reqpro_id']) ? 'marineprofile.php' : (!empty($nf['approval_id']) ? 'mpa-management.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'marinenotif.php'))
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No marine notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                                $notifTitle = !empty($item['incident_id']) ? 'Incident report' : (!empty($item['reqpro_id']) ? 'Profile update' : 'Marine Request');
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

                    <div class="notification-footer"><a href="marinenotif.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="marineprofile.php" class="dropdown-item"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <div class="CHAINSAW-RECORDS">
        <div class="container">
            <div class="header-section" style="background:#ffffff;border-radius:12px;padding:18px 20px;box-shadow:0 6px 15px rgba(0,0,0,0.2);margin-bottom:30px;color:black;">
                <h1 class="title" style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-size:42px;font-weight:900;color:#000;text-align:center;margin:0;">
                    Incident Reports
                </h1>
            </div>

            <!-- Controls: status dropdown + search + export -->
            <div class="controls" style="background-color:#ffffff !important;display:flex;align-items:center;gap:12px; justify-content: flex-start;">
                <div class="status-filter">
                    <label for="status-filter-select" style="margin-right:6px;font-weight:600;color:#005117;">Status</label>
                    <select id="status-filter-select" style="padding:6px;border-radius:4px;border:1px solid #ccc;">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="resolved">Resolved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="search">
                    <input type="text" placeholder="SEARCH HERE" class="search-input" id="search-input">
                    <img src="https://c.animaapp.com/uJwjYGDm/img/google-web-search@2x.png" alt="Search" class="search-icon" id="search-icon">
                </div>

            </div>

            <!-- Status filter moved to the controls as a dropdown -->

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
                                    <td><button class="view-btn" data-id="<?= htmlspecialchars((string)$row['id']) ?>">View</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            const rejectionReasonInput = document.getElementById("rejection-reason");
            const resolvedLockNote = document.getElementById("resolved-lock-note");

            const notificationEl = document.getElementById("profile-notification");

            // Keep the current report id handy across actions
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
                    const res = await fetch(`../backend/admins/incidentReport/get_incident_report.php?id=${encodeURIComponent(reportId)}`);
                    const report = await res.json();

                    if (!res.ok || report.error) {
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
                            // IMPORTANT: quote the PHP value so it becomes a JS string
                            user_id: "<?= htmlspecialchars((string)$user_id, ENT_QUOTES) ?>",
                        }),
                    });
                    const result = await res.json();

                    if (res.ok && result.success) {
                        showNotification(`Status changed to ${newStatus}`);
                        setTimeout(() => location.reload(), 1500);
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
                        setTimeout(() => location.reload(), 1500);
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

            cancelStatusBtn.addEventListener("click", () => {
                confirmStatusModal.style.display = "none";
            });
            cancelRejectBtn.addEventListener("click", () => {
                rejectionReasonInput.value = "";
                rejectReasonModal.style.display = "none";
            });

            // ===== Close modal handlers =====
            closeViewModal.addEventListener("click", () => {
                viewModal.style.display = "none";
                document.body.style.overflow = "auto";
            });
            closeConfirmStatusModal.addEventListener("click", () => (confirmStatusModal.style.display = "none"));
            closeRejectReasonModal.addEventListener("click", () => (rejectReasonModal.style.display = "none"));
            closeImageModal.addEventListener("click", () => {
                imageModal.style.display = "none";
                document.body.style.overflow = "auto";
            });

            window.addEventListener("click", (event) => {
                if (event.target === viewModal) {
                    viewModal.style.display = "none";
                    document.body.style.overflow = "auto";
                    // restore Update button and select when modal closed
                    currentReportLocked = false;
                    if (statusSelect) statusSelect.disabled = false;
                    if (updateStatusBtn) {
                        updateStatusBtn.disabled = false;
                        updateStatusBtn.style.display = 'inline-block';
                        updateStatusBtn.title = '';
                    }
                    if (resolvedLockNote) resolvedLockNote.style.display = 'none';
                }
                if (event.target === confirmStatusModal) confirmStatusModal.style.display = "none";
                if (event.target === rejectReasonModal) rejectReasonModal.style.display = "none";
                if (event.target === imageModal) {
                    imageModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            // ===== Status filter (dropdown) =====
            const statusSelectFilter = document.getElementById('status-filter-select');

            function applyStatusFilter() {
                const status = (statusSelectFilter && statusSelectFilter.value) ? statusSelectFilter.value.toLowerCase() : 'all';
                const rows = Array.from(document.querySelectorAll('.accident-table tbody tr'));
                rows.forEach((row) => {
                    // Skip server-side 'no incident reports' single-col rows
                    if (row.querySelectorAll('td').length <= 1) return;
                    if (status === 'all') {
                        row.style.display = '';
                    } else {
                        const rowStatus = (row.cells[6]?.textContent || '').trim().toLowerCase();
                        row.style.display = rowStatus === status ? '' : 'none';
                    }
                });
            }
            if (statusSelectFilter) {
                // When status changes, re-run the unified search which also applies status
                statusSelectFilter.addEventListener('change', performSearch);
            }

            // ===== Search (icon click, Enter or live input) =====
            const searchInput = document.getElementById("search-input");
            const searchIcon = document.getElementById("search-icon");

            function performSearch() {
                const term = (searchInput.value || "").toLowerCase();
                const activeStatus = (statusSelectFilter && statusSelectFilter.value) ? statusSelectFilter.value.toLowerCase() : 'all';
                const rows = Array.from(document.querySelectorAll('.accident-table tbody tr'));
                // Only search relevant columns: who(1), what(2), where(3), when(4), why(5)
                const indices = [1, 2, 3, 4, 5];
                rows.forEach((row) => {
                    // Skip server-side 'no incident reports' single-col rows
                    if (row.querySelectorAll('td').length <= 1) return;

                    let match = false;
                    for (let idx of indices) {
                        if ((row.cells[idx]?.textContent || "").toLowerCase().includes(term)) {
                            match = true;
                            break;
                        }
                    }

                    // Apply status dropdown filter
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
            }

            if (searchIcon) searchIcon.addEventListener("click", performSearch);
            if (searchInput) {
                searchInput.addEventListener("keypress", (e) => {
                    if (e.key === "Enter") performSearch();
                });
                // Live update on every keystroke
                searchInput.addEventListener("input", performSearch);
            }

            // (Removed month/year date filter — replaced by status dropdown)

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
                            if (idx !== 7) cols.push(`"${cell.textContent.replace(/"/g, '""')}"`); // skip Actions col
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
        });
    </script>

</body>

</html>