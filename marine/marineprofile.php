<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../superlogin.php');
    exit();
}
include_once __DIR__ . '/../backend/connection.php';
$user_id = $_SESSION['user_id'];
$sql = "SELECT image, first_name, last_name, age, email, role, department, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
if (!$user || strtolower($user['department']) !== 'marine') {
    header('Location: ../superlogin.php');
    exit();
}
$profile_image = (!empty($user['image']) && strtolower($user['image']) !== 'null' && file_exists(__DIR__ . '/../upload/admin_profiles/' . $user['image']))
    ? '/denr/superadmin/upload/admin_profiles/' . htmlspecialchars($user['image'])
    : '/denr/superadmin/default-profile.jpg';
$first_name = htmlspecialchars($user['first_name'] ?? '');
$last_name = htmlspecialchars($user['last_name'] ?? '');
$age = htmlspecialchars($user['age'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$role = htmlspecialchars($user['role'] ?? '');
$department = htmlspecialchars($user['department'] ?? '');
$phone = htmlspecialchars($user['phone'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/marineprofile.css">
    <link rel="stylesheet" href="/denr/superadmin/js/marineprofile.js">


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
                <div class="nav-icon">
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

                    <a href="reportaccident.php" class="dropdown-item">
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
                <div class="nav-icon <?php echo $current_page === 'marineprofile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="marineprofile.php" class="dropdown-item active-page">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="../logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <!-- Notification Popup -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Admin Profile</h1>
            <p class="profile-subtitle">View and manage your account information</p>
        </div>

        <div class="profile-body-main">
            <form id="profile-form" class="profile-body" enctype="multipart/form-data">
                <div class="profile-picture-container">
                    <img src="<?php echo $profile_image; ?>" alt="Profile Picture" class="profile-picture" id="profile-picture" onerror="this.onerror=null;this.src='../default-profile.jpg';">
                    <div class="profile-picture-placeholder" id="profile-placeholder" style="display:none;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-upload-icon" onclick="document.getElementById('profile-upload-input').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <input type="file" id="profile-upload-input" name="profile_image" accept="image/*" style="display:none;">
                <div class="profile-info-grid ">
                    <div class="profile-info-item">
                        <div class="profile-info-label">First Name</div>
                        <input type="text" class="profile-info-value" id="first-name" name="first_name" value="<?php echo $first_name; ?>" required>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Name</div>
                        <input type="text" class="profile-info-value" id="last-name" name="last_name" value="<?php echo $last_name; ?>" required>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Age</div>
                        <input type="number" class="profile-info-value" id="age" name="age" value="<?php echo $age; ?>" min="0">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email" value="<?php echo $email; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Role</div>
                        <input type="text" class="profile-info-value" id="role" name="role" value="<?php echo $role; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Phone</div>
                        <input type="text" class="profile-info-value" id="phone" name="phone" value="<?php echo $phone; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Department</div>
                        <select class="profile-info-value" id="department" name="department" required>
                            <option value="Marine" <?php if ($department === "Marine") echo 'selected'; ?>>Marine</option>
                            <option value="Wildlife" <?php if ($department === "Wildlife") echo 'selected'; ?>>Wildlife</option>
                            <option value="Seedling" <?php if ($department === "Seedling") echo 'selected'; ?>>Seedling</option>
                            <option value="Tree Cutting" <?php if ($department === "Tree Cutting") echo 'selected'; ?>>Tree Cutting</option>
                            <option value="Cenro" <?php if ($department === "Cenro") echo 'selected'; ?>>Cenro</option>
                        </select>
                    </div>
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn btn-primary" id="update-profile-btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- Profile Update Confirmation Modal -->
    <div id="profile-confirm-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:90vw; box-shadow:0 2px 16px rgba(0,0,0,0.18); text-align:center;">
            <h2 style="margin-bottom:18px; font-size:1.3rem; color:#1a3d5d;">Confirm Profile Update</h2>
            <p style="margin-bottom:24px; color:#444;">Are you sure you want to submit a profile update request? Changes will be reviewed by an admin.</p>
            <button id="confirm-profile-update-btn" style="background:#1a8cff; color:#fff; border:none; padding:10px 28px; border-radius:5px; font-size:1rem; margin-right:10px; cursor:pointer;">Yes, Submit</button>
            <button id="cancel-profile-update-btn" style="background:#eee; color:#333; border:none; padding:10px 22px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
        </div>
    </div>

    <!-- Profile Update Info Modal -->
    <div id="profile-info-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:90vw; box-shadow:0 2px 16px rgba(0,0,0,0.18); text-align:center;">
            <h2 style="margin-bottom:18px; font-size:1.3rem; color:#1a3d5d;">Request Sent</h2>
            <p style="margin-bottom:24px; color:#444;">Your profile update request has been submitted and is pending admin approval.</p>
            <button id="close-info-modal-btn" style="background:#1a8cff; color:#fff; border:none; padding:10px 28px; border-radius:5px; font-size:1rem; cursor:pointer;">OK</button>
        </div>
    </div>

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
                        menu.style.transform = menu.class.contains('center') ?
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

            // Profile picture upload functionality
            document.getElementById('profile-upload-input').addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    const reader = new FileReader();

                    reader.onload = function(event) {
                        const profilePic = document.getElementById('profile-picture');
                        const placeholder = document.getElementById('profile-placeholder');

                        profilePic.src = event.target.result;
                        profilePic.style.display = 'block';
                        placeholder.style.display = 'none';
                    };

                    reader.readAsDataURL(file);
                }
            });

            // Profile update request with confirmation and info modals
            const updateBtn = document.getElementById('update-profile-btn');
            const profileForm = document.getElementById('profile-form');
            const confirmModal = document.getElementById('profile-confirm-modal');
            const infoModal = document.getElementById('profile-info-modal');
            const confirmBtn = document.getElementById('confirm-profile-update-btn');
            const cancelBtn = document.getElementById('cancel-profile-update-btn');
            const closeInfoBtn = document.getElementById('close-info-modal-btn');
            const notification = document.getElementById('profile-notification');
            let pendingFormData = null;
            // Intercept form submit to show confirmation modal
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                pendingFormData = new FormData(profileForm);
                confirmModal.style.display = 'flex';
            });

            cancelBtn.onclick = function() {
                confirmModal.style.display = 'none';
                pendingFormData = null;
            };
            confirmBtn.onclick = function() {
                if (!pendingFormData) return;
                confirmBtn.disabled = true;

                fetch('../backend/marine/update_marine_profile.php', {
                        method: 'POST',
                        body: pendingFormData
                    })
                    .then(response => response.json())
                    .then(data => {
                        confirmBtn.disabled = false;
                        confirmModal.style.display = 'none';

                        if (data.success) {
                            infoModal.style.display = 'flex';
                        } else {
                            notification.textContent = data.error || 'Update failed.';
                            notification.style.display = 'block';
                            notification.style.opacity = '1';
                            setTimeout(() => {
                                notification.style.opacity = '0';
                                setTimeout(() => {
                                    notification.style.display = 'none';
                                }, 400);
                            }, 3000);
                        }

                        pendingFormData = null;
                    })
                    .catch(() => {
                        confirmBtn.disabled = false;
                        confirmModal.style.display = 'none';
                        alert('An error occurred while updating profile.');
                        pendingFormData = null;
                    });
            };

            closeInfoBtn.onclick = function() {
                infoModal.style.display = 'none';
                // Show notification
                notification.textContent = 'Profile update request sent!';
                notification.style.display = 'block';
                notification.style.opacity = '1';
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                        location.reload();
                    }, 400);
                }, 1500);
            };
        });
    </script>
</body>

</html>