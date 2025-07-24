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
if (!$user || strtolower($user['department']) !== 'seedling') {
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
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/treeprofile.css">
    <!-- Remove or fix this line: -->
    <!-- <link rel="stylesheet" href="/denr/superadmin/js/treeprofile.js"> -->
    <!-- If you have a JS file, use: -->
    <!-- <script src="/denr/superadmin/js/treeprofile.js"></script> -->
</head>

<body>


    <header>
        <div class="logo">
            <a href="treehome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <!-- Mobile menu toggle -->
        <button class="mobile-toggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation on the right -->
        <div class="nav-container">
            <!-- Dashboard Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                    <a href="treecutting.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting</span>
                    </a>
                    <a href="lumber.php" class="dropdown-item">
                        <i class="fas fa-store"></i>
                        <span>Lumber Dealers</span>
                    </a>
                    <a href="chainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Registered Chainsaw</span>
                    </a>
                    <a href="woodprocessing.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing</span>
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
                    <a href="treemessage.php" aria-label="Messages">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-item unread">
                        <a href="treeeach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Illegal Logging Alert</div>
                                <div class="notification-message">Report of unauthorized tree cutting activity in protected area.</div>
                                <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>

                    <div class="notification-footer">
                        <a href="treenotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="treeprofile.php" class="dropdown-item active-page">
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
                    <img src="<?php echo $profile_image; ?>" alt="Profile Picture" class="profile-picture" id="profile-picture"
                        onerror="this.onerror=null;this.src='/denr/superadmin/default-profile.jpg';">

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
                        <input type="text" class="profile-info-value" id="first-name" name="first_name" value="<?php echo $first_name; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Name</div>
                        <input type="text" class="profile-info-value" id="last-name" name="last_name" value="<?php echo $last_name; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Age</div>
                        <input type="number" class="profile-info-value" id="age" name="age" value="<?php echo $age; ?>" min="0">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email" value="<?php echo $email; ?>">
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

    <div id="otpModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>
            <h3>Email Verification</h3>
            <p>We've sent a 6-digit code to your new email address.</p>
            <input type="text" id="otpInput" maxlength="6" placeholder="Enter OTP code">
            <div style="margin-top:10px; display: flex; align-items: center; gap: 10px;">
                <button type="button" id="sendOtpBtn">Verify</button>
                <button type="button" id="resendOtpBtn">Resend</button>
                <span id="otpMessage" style="color:red; margin-left: 10px; font-size: 13px;"></span>
            </div>
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

            // Store original email for OTP check
            const originalEmail = "<?php echo $email; ?>";
            let emailChanged = false;

            document.getElementById('email').addEventListener('input', function(e) {
                emailChanged = (e.target.value.trim().toLowerCase() !== originalEmail.trim().toLowerCase());
            });

            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                pendingFormData = new FormData(profileForm);
                confirmModal.style.display = 'flex';
            });

            cancelBtn.onclick = function() {
                confirmModal.style.display = 'none';
                pendingFormData = null;
            };

            confirmBtn.onclick = async function() {
                if (!pendingFormData) return;

                try {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                    // If email changed, request OTP first
                    if (emailChanged) {
                        pendingFormData.append('request_otp', '1');
                    }

                    const response = await fetch('../backend/tree/update_tree_profile.php', {
                        method: 'POST',
                        body: pendingFormData
                    });

                    const data = await response.json();

                    if (data.error && data.error.includes('already have a pending')) {
                        showNotification(data.error, 'error');
                        confirmModal.style.display = 'none';
                        return;
                    }

                    if (data.otp_required) {
                        showOtpModal();
                        return;
                    }

                    if (data.success) {
                        infoModal.style.display = 'flex';
                    } else {
                        showNotification(data.error || 'Update failed', 'error');
                    }
                } catch (error) {
                    showNotification('Network error occurred. Please try again.', 'error');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-save"></i> Confirm Update';
                    confirmModal.style.display = 'none';
                }
            };

            function showOtpModal() {
                document.getElementById('otpModal').style.display = 'block';
                document.getElementById('otpMessage').textContent = '';
            }

            function hideOtpModal() {
                document.getElementById('otpModal').style.display = 'none';
            }

            document.getElementById('sendOtpBtn').addEventListener('click', verifyOtp);
            document.getElementById('resendOtpBtn').addEventListener('click', resendOtp);
            document.getElementById('closeModal').addEventListener('click', hideOtpModal);

            async function verifyOtp() {
                const otpInput = document.getElementById('otpInput').value;
                if (!otpInput || otpInput.length !== 6) {
                    document.getElementById('otpMessage').textContent = 'Please enter a valid 6-digit OTP';
                    return;
                }
                // Always create a fresh FormData for OTP verification
                const profileForm = document.getElementById('profile-form');
                const verifyFormData = new FormData(profileForm);
                verifyFormData.append('otp_code', otpInput);

                try {
                    const response = await fetch('../backend/tree/update_tree_profile.php', {
                        method: 'POST',
                        body: verifyFormData
                    });

                    const data = await response.json();

                    if (data.success) {
                        hideOtpModal();
                        infoModal.style.display = 'flex';
                    } else {
                        document.getElementById('otpMessage').textContent = data.error || 'Invalid OTP';
                    }
                } catch (error) {
                    document.getElementById('otpMessage').textContent = 'Verification failed. Please try again.';
                }
            }

            async function resendOtp() {
                const resendBtn = document.getElementById('resendOtpBtn');
                resendBtn.disabled = true;
                resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                try {
                    // Get the current email value from the input
                    const email = document.getElementById('email').value;
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('request_otp', '1');

                    const response = await fetch('../backend/tree/update_tree_profile.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.otp_required) {
                        document.getElementById('otpMessage').textContent = 'New OTP sent to your email';
                    } else {
                        document.getElementById('otpMessage').textContent = data.error || 'Failed to resend OTP';
                    }
                } catch (error) {
                    document.getElementById('otpMessage').textContent = 'Failed to resend OTP';
                } finally {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = 'Resend';
                }
            }

            closeInfoBtn.onclick = function() {
                infoModal.style.display = 'none';
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

            // Notification function
            function showNotification(message, type = 'success') {
                const notification = document.getElementById('profile-notification');
                notification.textContent = message;
                notification.style.display = 'block';
                // Only error or pending gets red, success gets black
                if (type === 'error' || (message && message.toLowerCase().includes('pending'))) {
                    notification.style.backgroundColor = '#ff4444';
                } else {
                    notification.style.backgroundColor = '#323232';
                }
                notification.style.opacity = '1';
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 400);
                }, 3000);
            }
        });
    </script>
</body>

</html>