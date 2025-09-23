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

            <div class="nav-item">
                <div class="nav-icon"><a href="marinemessage.php"><i class="fas fa-envelope" style="color:black;"></i></a></div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="marineeach.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Marine Pollution Alert</div>
                                <div class="notification-message">Community member reported plastic waste dumping in Lawis Beach.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
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

            <!-- Controls -->
            <div class="controls" style="background-color:#ffffff !important;">
                <div class="filter">
                    <select class="filter-month">
                        <option value="">All months</option>
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <input type="number" class="filter-year" placeholder="Year" list="year-suggestions">
                    <datalist id="year-suggestions">
                        <option value="2020">
                        <option value="2021">
                        <option value="2022">
                        <option value="2023">
                        <option value="2024">
                        <option value="2025">
                        <option value="2026">
                    </datalist>
                    <button class="filter-button" aria-label="Filter">
                        <i class="fas fa-filter" style="font-size:18px;color:#005117;margin-right:6px;"></i> Filter
                    </button>
                </div>

                <div class="search">
                    <input type="text" placeholder="SEARCH HERE" class="search-input" id="search-input">
                    <img src="https://c.animaapp.com/uJwjYGDm/img/google-web-search@2x.png" alt="Search" class="search-icon" id="search-icon">
                </div>

                <div class="export">
                    <button class="export-button" id="export-button">
                        <img src="https://c.animaapp.com/uJwjYGDm/img/vector-1.svg" alt="Export" class="export-icon">
                    </button>
                    <span class="export-label">Export as CSV</span>
                </div>
            </div>

            <!-- Status buttons -->
            <div class="status-buttons">
                <button class="status-btn all-btn" data-status="all">ALL</button>
                <button class="status-btn pending-btn" data-status="pending">PENDING</button>
                <button class="status-btn approved-btn" data-status="approved">APPROVED</button>
                <button class="status-btn resolved-btn" data-status="resolved">RESOLVED</button>
                <button class="status-btn rejected-btn" data-status="rejected">REJECTED</button>
            </div>

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
                                    <td><?= htmlspecialchars((string)$row['status']) ?></td>
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
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button id="update-status-btn" style="padding:8px 16px;background:#005117;color:white;border:none;border-radius:4px;cursor:pointer;">Update</button>
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

            const notificationEl = document.getElementById("profile-notification");

            // Keep the current report id handy across actions
            let currentReportId = null;

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
                }
                if (event.target === confirmStatusModal) confirmStatusModal.style.display = "none";
                if (event.target === rejectReasonModal) rejectReasonModal.style.display = "none";
                if (event.target === imageModal) {
                    imageModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            // ===== Status filter buttons =====
            const statusButtons = document.querySelectorAll(".status-btn");
            const tableRows = document.querySelectorAll(".accident-table tbody tr");
            statusButtons.forEach((button) => {
                button.addEventListener("click", () => {
                    const status = button.dataset.status || "all"; // uses data-status
                    tableRows.forEach((row) => {
                        if (status === "all") {
                            row.style.display = "";
                        } else {
                            const rowStatus = (row.cells[6]?.textContent || "").trim().toLowerCase();
                            row.style.display = rowStatus === status ? "" : "none";
                        }
                    });
                });
            });

            // ===== Search (icon click or Enter) =====
            const searchInput = document.getElementById("search-input");
            const searchIcon = document.getElementById("search-icon");

            function performSearch() {
                const term = (searchInput.value || "").toLowerCase();
                tableRows.forEach((row) => {
                    let match = false;
                    for (let i = 0; i < row.cells.length - 1; i++) {
                        if (row.cells[i].textContent.toLowerCase().includes(term)) {
                            match = true;
                            break;
                        }
                    }
                    row.style.display = match ? "" : "none";
                });
            }
            if (searchIcon) searchIcon.addEventListener("click", performSearch);
            if (searchInput) {
                searchInput.addEventListener("keypress", (e) => {
                    if (e.key === "Enter") performSearch();
                });
            }

            // ===== Date filter (month/year) =====
            const filterButton = document.querySelector(".filter-button");
            const filterMonth = document.querySelector(".filter-month");
            const filterYear = document.querySelector(".filter-year");

            if (filterButton) {
                filterButton.addEventListener("click", () => {
                    const m = filterMonth.value;
                    const y = filterYear.value;
                    tableRows.forEach((row) => {
                        const cell = row.cells[4];
                        if (!cell) return;
                        const txt = cell.textContent.trim(); // expects YYYY-MM-DD...
                        const parts = txt.split("-");
                        const yy = parts[0] || "";
                        const mm = parts[1] || "";
                        const monthOk = m ? mm === m : true;
                        const yearOk = y ? yy === y : true;
                        row.style.display = monthOk && yearOk ? "" : "none";
                    });
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