<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: user_login.php");
    exit();
}
include_once __DIR__ . '/../backend/connection.php';


$user_id = $_SESSION['user_id'];
$reports = [];
$stmt = $conn->prepare("SELECT id, category, status FROM incident_report WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/user_reportaccident.css">
</head>

<body>
    <header>
        <div class="logo">
            <a href="user_home.php">
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
                    <a href="user_reportaccident.php" class="dropdown-item active-page">
                        <i class="fas fa-file-invoice"></i>
                        <span>Report Incident</span>
                    </a>

                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing Permit</span>
                    </a>
                    <a href="useraddchainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Permit</span>
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
                        <a href="user_each.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Chainsaw Renewal Status</div>
                                <div class="notification-message">Chainsaw Renewal has been approved.</div>
                                <div class="notification-time">10 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="user_notification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div id="confirmationModal" class="hidden-modal">
        <div class="modal-content">
            <h3>Confirm Incident Report</h3>
            <p>Are you sure you want to submit this incident report?</p>
            <div style="margin-top:20px;">
                <button id="confirmSubmit" style="background:#28a745; color:#fff; padding:8px 16px; border:none; border-radius:4px; margin-right:10px; cursor:pointer;">Yes, Submit</button>
                <button id="cancelSubmit" style="background:#dc3545; color:#fff; padding:8px 16px; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>




    <!-- Notification Popup -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%);
 background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999;
 box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;">
    </div>

    <div class="content">
        <h2 class="page-title">REPORT AN INCIDENT</h2>

        <div class="profile-form">
            <form id="incidentForm" action="../backend/users/report_incident.php" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="who">WHO</label>
                        <input type="text" id="who" name="who" required>
                    </div>
                    <div class="form-group">
                        <label for="what">WHAT</label>
                        <input type="text" id="what" name="what" required>
                    </div>
                    <div class="form-group">
                        <label for="where">WHERE</label>
                        <input type="text" id="where" name="where" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="contact">CONTACT NO:</label>
                        <input type="text" id="contact" name="contact" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="when">WHEN</label>
                        <input type="datetime-local" id="when" name="when" required>
                    </div>
                    <div class="form-group one-third">
                        <label for="why">WHY</label>
                        <input type="text" id="why" name="why" required>
                    </div>
                </div>

                <!-- Improved Photo Upload Section -->
                <div class="form-row">
                    <div class="form-group full-width">
                        <label>UPLOAD PHOTOS (Max 5):</label>
                        <button type="button" id="addPhotoBtn" style="margin-bottom:10px;">Add Photo(s)</button>
                        <input type="file" id="photos" name="photos[]" multiple accept="image/*" style="display:none;">
                        <div id="photoPreview" class="photo-preview-container"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group two-thirds">
                        <label for="categories" style="margin-top: 0;">CATEGORIES:</label>
                        <select id="categories" name="categories" required>
                            <option value="">-- Select Category --</option>
                            <option value="Tree Cutting">Tree Cutting</option>
                            <option value="Marine Resource Monitoring">Marine Resource Monitoring</option>
                            <option value="Seedlings Monitoring">Seedlings Monitoring</option>
                            <option value="WildLife Monitoring">Wildlife Monitoring</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="description">DESCRIPTION OF INCIDENT:</label>
                        <textarea id="description" name="description" style="height: 130px;" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <div class="button-group">
                            <button type="submit" name="submit" class="save-btn">SUBMIT</button>
                            <button type="button" class="view-records-btn" id="viewRecordsBtn">VIEW RECORDS</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>

        <!-- Records Container -->
        <div class="records-container" id="recordsContainer">
            <h3 class="records-title">INCIDENT REPORTS</h3>

            <table class="records-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <!-- <th>Date</th> -->
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="5" class="no-records">No incident reports found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars($report['id']) ?></td>
                                <!-- <td><?= date('Y-m-d', strtotime($report['date_time'])) ?></td> -->
                                <td><?= htmlspecialchars($report['category']) ?></td>
                                <td class="status-<?= strtolower($report['status']) ?>">
                                    <?= htmlspecialchars($report['status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photos');
            const addPhotoBtn = document.getElementById('addPhotoBtn');
            const photoPreview = document.getElementById('photoPreview');
            const maxPhotos = 5;
            let selectedFiles = [];

            addPhotoBtn.addEventListener('click', function() {
                photoInput.click();
            });

            photoInput.addEventListener('change', function(e) {
                // Add new files to selectedFiles, avoiding duplicates and max limit
                const newFiles = Array.from(photoInput.files);
                for (let file of newFiles) {
                    if (selectedFiles.length >= maxPhotos) break;
                    // Avoid duplicates by name+size
                    if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                        selectedFiles.push(file);
                    }
                }
                renderPreviews();
                // Reset input so user can select the same file again if needed
                photoInput.value = '';
            });

            function renderPreviews() {
                photoPreview.innerHTML = '';
                selectedFiles.forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewWrapper = document.createElement('div');
                        previewWrapper.className = 'photo-preview-wrapper';

                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'photo-preview';

                        const removeBtn = document.createElement('span');
                        removeBtn.className = 'remove-photo';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = function() {
                            selectedFiles.splice(idx, 1);
                            renderPreviews();
                        };

                        previewWrapper.appendChild(img);
                        previewWrapper.appendChild(removeBtn);
                        photoPreview.appendChild(previewWrapper);
                    };
                    reader.readAsDataURL(file);
                });
            }

            // Existing form submission code
            const form = document.getElementById("incidentForm");
            const confirmationModal = document.getElementById("confirmationModal");
            const confirmBtn = document.getElementById("confirmSubmit");
            const cancelBtn = document.getElementById("cancelSubmit");
            const notification = document.getElementById("profile-notification");


            form.addEventListener("submit", function(e) {
                e.preventDefault();
                if (selectedFiles.length === 0) {
                    alert('Please upload at least one photo');
                    return;
                }
                confirmationModal.classList.remove("hidden-modal");
            });

            cancelBtn.addEventListener("click", function() {
                confirmationModal.classList.add("hidden-modal");
            });

            confirmBtn.addEventListener("click", function() {
                confirmationModal.classList.add("hidden-modal");
                const formData = new FormData(form);
                // Remove any existing photos[] from formData (in case browser adds empty)
                formData.delete('photos[]');
                // Append all selected files
                selectedFiles.forEach(file => {
                    formData.append('photos[]', file);
                });

                fetch(form.action, {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        notification.textContent = data.message;
                        notification.style.backgroundColor = data.success ? "#28a745" : "#dc3545";
                        notification.style.display = "block";

                        setTimeout(() => {
                            notification.style.display = "none";
                            if (data.success) {
                                window.location.reload();
                            }
                        }, 3000);
                    })
                    .catch(error => {
                        notification.textContent = "Error submitting form: " + error.message;
                        notification.style.backgroundColor = "#dc3545";
                        notification.style.display = "block";
                        setTimeout(() => {
                            notification.style.display = "none";
                        }, 3000);
                    });
            });
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    const isActive = navContainer.classList.toggle('active');
                    document.body.style.overflow = isActive ? 'hidden' : '';
                });
            }

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.nav-container') && !e.target.closest('.mobile-toggle')) {
                    navContainer.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // View Records button functionality
            const viewRecordsBtn = document.getElementById('viewRecordsBtn');
            const recordsContainer = document.getElementById('recordsContainer');

            if (viewRecordsBtn && recordsContainer) {
                viewRecordsBtn.addEventListener('click', function() {
                    if (recordsContainer.style.display === 'none' || recordsContainer.style.display === '') {
                        recordsContainer.style.display = 'block';
                        this.textContent = 'HIDE RECORDS';
                    } else {
                        recordsContainer.style.display = 'none';
                        this.textContent = 'VIEW RECORDS';
                    }
                });
            }
        });
    </script>
</body>

</html>