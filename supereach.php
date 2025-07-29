<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}
include_once __DIR__ . '/backend/connection.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($department);
if ($stmt->fetch()) {
    if (strtolower($department) !== 'cenro') {
        $stmt->close();
        $conn->close();
        header('Location: superlogin.php');
        exit();
    }
} else {
    $stmt->close();
    $conn->close();
    header('Location: superlogin.php');
    exit();
}
$stmt->close();
// Get the request ID from URL
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch the profile update request details
$stmt = $conn->prepare("
    SELECT * FROM profile_update_requests 
    WHERE id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    // Request not found, redirect back
    header('Location: supernotif.php');
    exit();
}

// Mark the notification as read
$update_stmt = $conn->prepare("UPDATE profile_update_requests SET is_read = 1 WHERE id = ?");
$update_stmt->bind_param("i", $request_id);
$update_stmt->execute();
$update_stmt->close();

// Determine the image path
$image_path = '';
if (!empty($request['image'])) {
    $image_path = 'upload/admin_profiles/' . basename($request['image']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/supereach.css">
</head>

<body>

    <header>
        <div class="logo">
            <a href="superhome.php">
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
                <div class="nav-icon">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">

                    <a href="superlogs.php" class="dropdown-item">
                        <i class="fas fa-user-shield" style="color: white;"></i>
                        <span>Admin Logs</span>
                    </a>

                </div>
            </div>


            <!-- Messages Icon -->
            <div class="nav-item">
                <div class="nav-icon">
                    <a href="supermessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-item unread">
                        <a href="supereach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Admin Profile Update</div>
                                <div class="notification-message">Seedlings Administrator request to change the username.</div>
                                <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="supernotif.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'treeprofile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- UPDATED ACCIDENT REPORT FORM -->
    <div class="accident-report-container">
        <h1 class="accident-report-header">Profile Update Request</h1>

        <div class="accident-report-form">
            <!-- Profile Image Display -->
            <div class="accident-form-group full-width" style="grid-row: span 2;">
                <label>PROFILE IMAGE</label>
                <div class="accident-form-valueimg">
                    <?php if (!empty($image_path) && file_exists($image_path)): ?>
                        <img src="<?= htmlspecialchars($image_path) ?>" alt="Profile Image" style=" max-height: 155px;   display: block;">

                    <?php elseif (!empty($request['image'])): ?>
                        <span style="color: #dc3545;">Image not found: <?= htmlspecialchars($request['image']) ?></span>
                    <?php else: ?>
                        <span>No profile image provided</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- First row -->
            <div class="accident-form-group">
                <label>FIRST NAME</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['first_name'] ?? '') ?></div>
            </div>

            <div class="accident-form-group">
                <label>LAST NAME</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['last_name'] ?? '') ?></div>
            </div>

            <div class="accident-form-group">
                <label>AGE</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['age'] ?? '') ?></div>
            </div>

            <div class="accident-form-group">
                <label>EMAIL</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['email'] ?? '') ?></div>
            </div>

            <div class="accident-form-group">
                <label>PHONE</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['phone'] ?? '') ?></div>
            </div>

            <div class="accident-form-group">
                <label>DEPARTMENT</label>
                <div class="accident-form-value"><?= htmlspecialchars($request['department'] ?? '') ?></div>
            </div>

            <!-- Approve/Reject/Delete/Back buttons -->
            <div class="save-button-container ">
                <form id="updateRequestForm" action="backend/admin/process_update_request.php" method="post">
                    <input type="hidden" name="request_id" value="<?= $request_id ?>">
                    <input type="hidden" name="user_id" value="<?= $request['user_id'] ?>">
                    <input type="hidden" name="action" id="formAction" value="">
                    <input type="hidden" name="reviewed_by" value="<?= $user_id ?>">
                    <?php if ($request['status'] === 'pending'): ?>
                        <button type="button" id="approveBtn" class="approve-button">APPROVE</button>
                        <button type="button" id="rejectBtn" class="reject-button">REJECT</button>
                    <?php else: ?>
                        <button type="button" id="deleteBtn" class="delete-button" style="background:#dc3545;color:#fff;">DELETE</button>
                        <button type="button" id="backBtn" class="back-button" style="background:#6c757d;color:#fff;">BACK</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Confirmation Modals -->
            <div id="approveModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Are you sure you want to approve this profile update?</p>
                    <div>
                        <button id="confirmApprove" class="approve-button">Yes, Approve</button>
                        <button class="close-modal">Cancel</button>
                    </div>

                </div>
            </div>
            <div id="rejectModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Are you sure you want to reject this profile update?</p>
                    <label for="reason">Reason for rejection:</label>
                    <input type="text" id="reason" name="reason_for_rejection" style="width:100%;">
                    <div>
                        <button id="confirmReject" class="reject-button">Yes, Reject</button>
                        <button class="close-modal">Cancel</button>
                    </div>
                </div>
            </div>
            <div id="deleteModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <p>Are you sure you want to delete this profile update request?</p>
                    <div>
                        <button id="confirmDelete" class="delete-button">Yes, Delete</button>
                        <button class="close-modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="action-notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }

            // Improved dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');

            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');

                // Show menu on hover
                dropdown.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(0)' :
                        'translateY(0)';
                });

                // Hide menu when leaving both button and menu
                dropdown.addEventListener('mouseleave', (e) => {
                    // Check if we're leaving the entire dropdown area
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });

                // Additional check for menu mouseleave
                menu.addEventListener('mouseleave', (e) => {
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });
            });

            // Close dropdowns when clicking outside (for mobile)
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    });
                }
            });

            // Mobile dropdown toggle
            if (window.innerWidth <= 992) {
                dropdowns.forEach(dropdown => {
                    const toggle = dropdown.querySelector('.nav-icon');
                    const menu = dropdown.querySelector('.dropdown-menu');

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
                });
            }

            // Mark all notifications as read
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelector('.badge').style.display = 'none';
                });
            }

            // Notification popup for approve/reject
            function showActionNotification(message, type = 'success') {
                const notif = document.getElementById('action-notification');
                notif.textContent = message;
                notif.className = type === 'error' ? 'error' : 'success';
                notif.style.display = 'block';
                notif.style.opacity = '1';
                setTimeout(() => {
                    notif.style.opacity = '0';
                    setTimeout(() => {
                        notif.style.display = 'none';
                    }, 400);
                }, 1800);
            }

            // Modal logic for approve/reject/delete/back
            const approveBtn = document.getElementById('approveBtn');
            const rejectBtn = document.getElementById('rejectBtn');
            const deleteBtn = document.getElementById('deleteBtn');
            const backBtn = document.getElementById('backBtn');
            const approveModal = document.getElementById('approveModal');
            const rejectModal = document.getElementById('rejectModal');
            const deleteModal = document.getElementById('deleteModal');
            const confirmApprove = document.getElementById('confirmApprove');
            const confirmReject = document.getElementById('confirmReject');
            const confirmDelete = document.getElementById('confirmDelete');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            const updateForm = document.getElementById('updateRequestForm');
            const formAction = document.getElementById('formAction');
            const reasonInput = document.getElementById('reason');

            if (approveBtn) {
                approveBtn.addEventListener('click', function() {
                    approveModal.style.display = 'block';
                });
            }
            if (rejectBtn) {
                rejectBtn.addEventListener('click', function() {
                    rejectModal.style.display = 'block';
                });
            }
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    deleteModal.style.display = 'block';
                });
            }
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    window.location.href = "supernotif.php";
                });
            }
            if (confirmApprove) {
                confirmApprove.addEventListener('click', function() {
                    formAction.value = 'approve';
                    showActionNotification('Update request approved!', 'success');
                    setTimeout(() => {
                        updateForm.submit();
                    }, 900);
                });
            }
            if (confirmReject) {
                confirmReject.addEventListener('click', function() {
                    formAction.value = 'reject';
                    // Add reason to form if provided
                    if (reasonInput && reasonInput.value) {
                        let reasonField = updateForm.querySelector('input[name="reason_for_rejection"]');
                        if (!reasonField) {
                            reasonField = document.createElement('input');
                            reasonField.type = 'hidden';
                            reasonField.name = 'reason_for_rejection';
                            updateForm.appendChild(reasonField);
                        }
                        reasonField.value = reasonInput.value;
                    }
                    showActionNotification('Update request rejected.', 'error');
                    setTimeout(() => {
                        updateForm.submit();
                    }, 900);
                });
            }
            if (confirmDelete) {
                confirmDelete.addEventListener('click', function() {
                    formAction.value = 'delete';
                    updateForm.submit();
                });
            }
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    approveModal.style.display = 'none';
                    rejectModal.style.display = 'none';
                    deleteModal.style.display = 'none';
                });
            });
            // Optional: close modal on outside click
            window.onclick = function(event) {
                if (event.target === approveModal) approveModal.style.display = 'none';
                if (event.target === rejectModal) rejectModal.style.display = 'none';
                if (event.target === deleteModal) deleteModal.style.display = 'none';
            };
        });
    </script>
</body>

</html>