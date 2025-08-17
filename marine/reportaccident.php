<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../superlogin.php');
    exit();
}
include_once __DIR__ . '/../backend/connection.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($department);
if ($stmt->fetch()) {
    if (strtolower($department) !== 'marine') {
        $stmt->close();
        $conn->close();
        header('Location: ../superlogin.php');
        exit();
    }
} else {
    $stmt->close();
    $conn->close();
    header('Location: ../superlogin.php');
    exit();
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/reportaccident.css">
    <link rel="stylesheet" href="/denr/superadmin/js/reportaccident.js">



</head>

<body>

    <header>
        <div class="logo">
            <a href="marinehome.php">
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
                <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="mpa-management.php" class="dropdown-item">
                        <i class="fas fa-water"></i>
                        <span>MPA Management</span>
                    </a>
                    <a href="habitat.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Habitat Assessment</span>
                    </a>
                    <a href="species.php" class="dropdown-item">
                        <i class="fas fa-fish"></i>
                        <span>Species Monitoring</span>
                    </a>
                    <a href="reports.php" class="dropdown-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports & Analytics</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item active-page">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>
                </div>
            </div>

            <!-- Messages Icon -->
            <div class="nav-item">
                <div class="nav-icon">
                    <a href="marinemessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-item unread">
                        <a href="marineeach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Marine Pollution Alert</div>
                                <div class="notification-message">Community member reported plastic waste dumping in Lawis Beach.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="marinenotif.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="marineprofile.php" class="dropdown-item">
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


    <div class="CHAINSAW-RECORDS">
        <div class="container">
            <div class="header-section" style="background: #ffffff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 6px 15px rgba(0,0,0,0.2); margin-bottom: 30px; color: black;">
                <h1 class="title" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 42px; font-weight: 900; color:rgb(0, 0, 0); text-align: center; margin: 0;">
                    Incident Reports
                </h1>
            </div>

            <!-- Controls Section -->
            <div class="controls" style="background-color: #ffffff !important;">
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
                    <input type="number" class="filter-year" placeholder="Year">
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
                        <i class="fas fa-filter" style="font-size: 18px; color: #005117; margin-right: 6px;"></i> Filter
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

            <!-- Centered Status Buttons -->
            <div class="status-buttons">
                <!-- Change your buttons to include data-status attribute -->
                <button class="status-btn all-btn" data-status="all">ALL</button>
                <button class="status-btn pending-btn" data-status="pending">PENDING</button>
                <button class="status-btn approved-btn" data-status="approved">APPROVED</button>
                <button class="status-btn resolved-btn" data-status="resolved">RESOLVED</button>
                <button class="status-btn rejected-btn" data-status="rejected">REJECTED</button>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <table class="accident-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 10%;">WHO</th>
                            <th style="width: 10%;">WHAT</th>
                            <th style="width: 10%;">WHERE</th>
                            <th style="width: 10%;">WHEN</th>
                            <th style="width: 10%;">WHY</th>
                            <th style="width: 10%;">STATUS</th>
                            <th style="width: 15%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, who, what, `where`, `when`, why, status 
                            FROM incident_report 
                            WHERE category = 'Marine Resource Monitoring'
                            ORDER BY created_at DESC";
                        $result = $conn->query($query);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['who']}</td>
                                <td>{$row['what']}</td>
                                <td>{$row['where']}</td>
                                <td>{$row['when']}</td>
                                <td>{$row['why']}</td>
                                <td>{$row['status']}</td>
                                <td>
                                    <button class='view-btn' data-id='{$row['id']}'>View</button>
                                    " . ($row['status'] !== 'Pending' ? "<button class='delete-btn' data-id='{$row['id']}'>Delete</button>" : "") . "
                                </td>
                            </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>No incident reports found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 900px; display: flex; background: white; border-radius: 8px; overflow: hidden;">
            <span class="close-modal" id="closeViewModal" style="position: absolute; right: 20px; top: 10px; font-size: 28px; cursor: pointer; z-index: 1;">&times;</span>

            <div style="flex: 1; padding: 20px; border-right: 1px solid #eee;">
                <h3 style="margin-top: 0; color: #005117;">Incident Report Details</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><strong>ID:</strong> <span id="modal-id"></span></div>
                    <div><strong>Who:</strong> <span id="modal-who"></span></div>
                    <div><strong>What:</strong> <span id="modal-what"></span></div>
                    <div><strong>Where:</strong> <span id="modal-where"></span></div>
                    <div><strong>When:</strong> <span id="modal-when"></span></div>
                    <div><strong>Why:</strong> <span id="modal-why"></span></div>
                    <div><strong>Contact No:</strong> <span id="modal-contact"></span></div>
                    <div><strong>Category:</strong> <span id="modal-category"></span></div>
                    <div><strong>Status:</strong>
                        <select id="modal-status" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="resolved">Resolved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div><strong>Created At:</strong> <span id="modal-created-at"></span></div>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button id="update-status-btn" style="padding: 8px 16px; background: #005117; color: white; border: none; border-radius: 4px; cursor: pointer;">Update</button>
                    <button id="reject-btn" style="padding: 8px 16px; background: #d9534f; color: white; border: none; border-radius: 4px; cursor: pointer;">Reject</button>
                </div>
            </div>

            <div style="flex: 1; padding: 20px;">
                <h3 style="margin-top: 0; color: #005117;">Description</h3>
                <div id="modal-description" style="margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 4px; min-height: 100px;"></div>

                <h3 style="color: #005117;">Photos</h3>
                <div id="modal-photos" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <!-- Photos will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Confirmation Modal -->
    <div id="confirmStatusModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeConfirmStatusModal" class="close-modal">&times;</span>
            <h3>Confirm Status Change</h3>
            <p>Are you sure you want to change the status of this incident report?</p>
            <button id="confirmStatusBtn" class="btn btn-primary" style="margin:10px 10px 0 0; padding: 8px 16px; background: #005117; color: white; border: none; border-radius: 4px; cursor: pointer;">Yes, Change</button>
            <button id="cancelStatusBtn" class="btn btn-outline" style="padding: 8px 16px; background: #f5f5f5; color: #333; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;">Cancel</button>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectReasonModal" class="modal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <span id="closeRejectReasonModal" class="close-modal">&times;</span>
            <h3>Rejection Reason</h3>
            <textarea id="rejection-reason" style="width: 100%; height: 100px; margin-bottom: 10px; padding: 8px;" placeholder="Enter rejection reason..."></textarea>
            <button id="confirmRejectBtn" class="btn btn-primary" style="margin:10px 10px 0 0; padding: 8px 16px; background: #d9534f; color: white; border: none; border-radius: 4px; cursor: pointer;">Confirm Reject</button>
            <button id="cancelRejectBtn" class="btn btn-outline" style="padding: 8px 16px; background: #f5f5f5; color: #333; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;">Cancel</button>
        </div>
    </div>

    <!-- Image View Modal -->
    <div id="imageModal" class="modal">
        <span id="closeImageModal" class="close-modal" style="position: absolute; right: 20px; top: 10px; font-size: 28px; cursor: pointer; color: white; z-index: 1001;">&times;</span>
        <img id="expandedImg" style="width: auto; height: auto; max-width: 90%; max-height: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    </div>

    <!-- Notification -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- JavaScript for Filter, Edit & Delete Functionality -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Modal elements
            const viewModal = document.getElementById("viewModal");
            const confirmStatusModal = document.getElementById("confirmStatusModal");
            const rejectReasonModal = document.getElementById("rejectReasonModal");
            const imageModal = document.getElementById("imageModal");

            // Close buttons - now each has its own ID
            const closeViewModal = document.getElementById("closeViewModal");
            const closeConfirmStatusModal = document.getElementById("closeConfirmStatusModal");
            const closeRejectReasonModal = document.getElementById("closeRejectReasonModal");
            const closeImageModal = document.getElementById("closeImageModal");

            // Status change elements
            const updateStatusBtn = document.getElementById("update-status-btn");
            const rejectBtn = document.getElementById("reject-btn");
            const confirmStatusBtn = document.getElementById("confirmStatusBtn");
            const cancelStatusBtn = document.getElementById("cancelStatusBtn");
            const confirmRejectBtn = document.getElementById("confirmRejectBtn");
            const cancelRejectBtn = document.getElementById("cancelRejectBtn");

            // Image modal elements
            const expandedImg = document.getElementById("expandedImg");

            // Current report ID for rejection
            let currentReportId = null;

            // Open view modal when view button is clicked
            document.addEventListener("click", async (e) => {
                if (e.target.classList.contains("view-btn")) {
                    const reportId = e.target.getAttribute("data-id");
                    currentReportId = reportId;

                    try {
                        const response = await fetch(`../backend/admins/marine/get_incident_report.php?id=${reportId}`);
                        const report = await response.json();

                        if (response.ok) {
                            // Populate modal with report data
                            document.getElementById("modal-id").textContent = report.id;
                            document.getElementById("modal-who").textContent = report.who;
                            document.getElementById("modal-what").textContent = report.what;
                            document.getElementById("modal-where").textContent = report.where;
                            document.getElementById("modal-when").textContent = report.when;
                            document.getElementById("modal-why").textContent = report.why;
                            document.getElementById("modal-contact").textContent = report.contact_no;
                            document.getElementById("modal-category").textContent = report.category;
                            document.getElementById("modal-description").textContent = report.description;
                            document.getElementById("modal-created-at").textContent = report.created_at;

                            // Set status dropdown to match the record's status
                            const statusSelect = document.getElementById("modal-status");
                            if (report.status) {
                                statusSelect.value = report.status.toLowerCase();
                                console.log("Setting status:", report.status, "Dropdown value set to:", statusSelect.value);
                            } else {
                                statusSelect.value = "pending";
                            }
                            console.log("Report data:", report);

                            // Clear and populate photos
                            const photosContainer = document.getElementById("modal-photos");
                            photosContainer.innerHTML = "";

                            if (report.photos) {
                                const photos = JSON.parse(report.photos);
                                photos.forEach(photo => {
                                    const img = document.createElement("img");
                                    img.src = `../upload/user/reportincidents/${photo}`;
                                    img.className = "photo-thumbnail";
                                    img.alt = "Incident photo";
                                    img.style.width = "100%";
                                    img.style.height = "auto";
                                    img.style.cursor = "pointer";
                                    img.style.borderRadius = "4px";
                                    img.style.transition = "transform 0.2s";
                                    img.addEventListener("mouseenter", () => {
                                        img.style.transform = "scale(1.02)";
                                    });
                                    img.addEventListener("mouseleave", () => {
                                        img.style.transform = "scale(1)";
                                    });
                                    img.addEventListener("click", () => {
                                        expandedImg.src = `../upload/user/reportincidents/${photo}`;
                                        imageModal.style.display = "block";
                                        document.body.style.overflow = "hidden"; // Prevent scrolling when modal is open
                                    });
                                    photosContainer.appendChild(img);
                                });
                            }

                            viewModal.style.display = "block";
                            document.body.style.overflow = "hidden"; // Prevent scrolling when modal is open
                        } else {
                            showNotification("Error loading report details");
                        }
                    } catch (error) {
                        console.error("Error:", error);
                        showNotification("Error loading report details");
                    }
                }
            });

            // Update status flow
            updateStatusBtn.addEventListener("click", () => {
                confirmStatusModal.style.display = "block";
            });

            confirmStatusBtn.addEventListener("click", async () => {
                const reportId = document.getElementById("modal-id").textContent;
                const newStatus = document.getElementById("modal-status").value;

                try {
                    const response = await fetch('../backend/admins/marine/update_incident_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: reportId,
                            status: newStatus,
                            user_id: <?php echo $user_id; ?>
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        showNotification(`Status changed to ${newStatus}`);
                        // Refresh the page to show updated status
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(result.message || "Error updating status");
                    }
                } catch (error) {
                    console.error("Error:", error);
                    showNotification("Error updating status");
                }

                confirmStatusModal.style.display = "none";
                viewModal.style.display = "none";
                document.body.style.overflow = "auto"; // Re-enable scrolling
            });

            // Reject flow
            rejectBtn.addEventListener("click", () => {
                rejectReasonModal.style.display = "block";
            });

            confirmRejectBtn.addEventListener("click", async () => {
                const rejectionReason = document.getElementById("rejection-reason").value.trim();

                if (!rejectionReason) {
                    showNotification("Please enter a rejection reason");
                    return;
                }

                try {
                    const response = await fetch('../backend/admins/marine/reject_incident_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: currentReportId,
                            rejection_reason: rejectionReason,
                            user_id: <?php echo $user_id; ?>
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        showNotification("Incident report rejected");
                        // Refresh the page to show updated status
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(result.message || "Error rejecting report");
                    }
                } catch (error) {
                    console.error("Error:", error);
                    showNotification("Error rejecting report");
                }

                rejectReasonModal.style.display = "none";
                viewModal.style.display = "none";
                document.body.style.overflow = "auto"; // Re-enable scrolling
            });

            cancelRejectBtn.addEventListener("click", () => {
                document.getElementById("rejection-reason").value = "";
                rejectReasonModal.style.display = "none";
            });

            cancelStatusBtn.addEventListener("click", () => {
                confirmStatusModal.style.display = "none";
            });

            // Separate close handlers for each modal
            closeViewModal.addEventListener("click", () => {
                viewModal.style.display = "none";
                document.body.style.overflow = "auto"; // Re-enable scrolling
            });

            closeConfirmStatusModal.addEventListener("click", () => {
                confirmStatusModal.style.display = "none";
            });

            closeRejectReasonModal.addEventListener("click", () => {
                rejectReasonModal.style.display = "none";
            });

            closeImageModal.addEventListener("click", () => {
                imageModal.style.display = "none";
                document.body.style.overflow = "auto"; // Re-enable scrolling
            });

            // Close modals when clicking outside
            window.addEventListener("click", (event) => {
                if (event.target === viewModal) {
                    viewModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
                if (event.target === confirmStatusModal) {
                    confirmStatusModal.style.display = "none";
                }
                if (event.target === rejectReasonModal) {
                    rejectReasonModal.style.display = "none";
                }
                if (event.target === imageModal) {
                    imageModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });

            // Delete button functionality
            document.addEventListener("click", async (e) => {
                if (e.target.classList.contains("delete-btn")) {
                    const reportId = e.target.getAttribute("data-id");

                    if (confirm("Are you sure you want to delete this incident report?")) {
                        try {
                            const response = await fetch('../backend/admins/marine/delete_incident_report.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    id: reportId,
                                    user_id: <?php echo $user_id; ?>
                                })
                            });

                            const result = await response.json();

                            if (response.ok && result.success) {
                                showNotification("Incident report deleted");
                                // Refresh the page to show updated list
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showNotification(result.message || "Error deleting report");
                            }
                        } catch (error) {
                            console.error("Error:", error);
                            showNotification("Error deleting report");
                        }
                    }
                }
            });

            // Notification function
            function showNotification(message) {
                const notification = document.getElementById("profile-notification");
                notification.textContent = message;
                notification.style.display = "block";

                setTimeout(() => {
                    notification.style.display = "none";
                }, 3000);
            }

            // Status filter functionality - UPDATED VERSION
            const statusButtons = document.querySelectorAll(".status-btn");
            const tableRows = document.querySelectorAll(".accident-table tbody tr");

            statusButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const status = button.textContent.trim().toUpperCase(); // Convert to uppercase for comparison

                    tableRows.forEach(row => {
                        if (status === "ALL") {
                            row.style.display = "";
                        } else {
                            const rowStatus = row.cells[6].textContent.trim().toUpperCase(); // Convert to uppercase
                            console.log("Filtering:", status, "Row status:", rowStatus); // Debug log
                            if (rowStatus === status) {
                                row.style.display = "";
                            } else {
                                row.style.display = "none";
                            }
                        }
                    });

                    // Debug: Log how many rows are visible
                    const visibleRows = document.querySelectorAll('.accident-table tbody tr[style=""]');
                    console.log(`Visible rows after filtering for ${status}:`, visibleRows.length);
                });
            });

            // Search functionality
            const searchInput = document.getElementById("search-input");
            const searchIcon = document.getElementById("search-icon");

            const performSearch = () => {
                const searchTerm = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    let rowContainsText = false;

                    for (let i = 0; i < row.cells.length - 1; i++) { // Skip actions column
                        if (row.cells[i].textContent.toLowerCase().includes(searchTerm)) {
                            rowContainsText = true;
                            break;
                        }
                    }

                    row.style.display = rowContainsText ? "" : "none";
                });
            };

            searchIcon.addEventListener("click", performSearch);
            searchInput.addEventListener("keypress", (e) => {
                if (e.key === "Enter") {
                    performSearch();
                }
            });

            // Filter functionality
            const filterButton = document.querySelector(".filter-button");
            const filterMonth = document.querySelector(".filter-month");
            const filterYear = document.querySelector(".filter-year");

            filterButton.addEventListener("click", () => {
                const selectedMonth = filterMonth.value;
                const selectedYear = filterYear.value;

                tableRows.forEach(row => {
                    const dateCell = row.cells[4]; // WHEN column
                    if (dateCell) {
                        const dateText = dateCell.textContent.trim();
                        const [year, month] = dateText.split("-");

                        const matchesMonth = selectedMonth ? month === selectedMonth : true;
                        const matchesYear = selectedYear ? year === selectedYear : true;

                        if (matchesMonth && matchesYear) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    }
                });
            });

            // Export functionality
            const exportButton = document.getElementById("export-button");

            exportButton.addEventListener("click", () => {
                // Create CSV content
                let csvContent = "data:text/csv;charset=utf-8,";

                // Add headers
                const headers = [];
                document.querySelectorAll(".accident-table th").forEach(header => {
                    headers.push(`"${header.textContent.replace(/"/g, '""')}"`);
                });
                csvContent += headers.join(",") + "\r\n";

                // Add rows
                document.querySelectorAll(".accident-table tbody tr").forEach(row => {
                    if (row.style.display !== "none") {
                        const rowData = [];
                        row.querySelectorAll("td").forEach((cell, index) => {
                            if (index !== 7) { // Skip actions column
                                rowData.push(`"${cell.textContent.replace(/"/g, '""')}"`);
                            }
                        });
                        csvContent += rowData.join(",") + "\r\n";
                    }
                });

                // Create download link
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "incident_report_" + new Date().toISOString().slice(0, 10) + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>

</html>