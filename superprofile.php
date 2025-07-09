<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/superprofile.css">
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
                <div class="nav-icon active">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu ">
                    <a href="superprofile.php" class="dropdown-item active-page">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <?php
    include __DIR__ . '/backend/connection.php';
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT image, first_name, last_name, age, email, role, department FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    $profile_image = (!empty($user['image']) && strtolower($user['image']) !== 'null') ? 'upload/admin_profiles/' . htmlspecialchars($user['image']) : 'default-profile.jpg';
    $first_name = htmlspecialchars($user['first_name'] ?? '');
    $last_name = htmlspecialchars($user['last_name'] ?? '');
    $age = htmlspecialchars($user['age'] ?? '');
    $email = htmlspecialchars($user['email'] ?? '');
    $role = htmlspecialchars($user['role'] ?? '');
    $department = htmlspecialchars($user['department'] ?? '');
    ?>
    <!-- Profile Content -->
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
                    <img src="<?php echo $profile_image; ?>" alt="Profile Picture" class="profile-picture" id="profile-picture" onerror="this.onerror=null;this.src='default-profile.jpg';">
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
                        <div class="profile-info-label">Department</div>
                        <input type="text" class="profile-info-value" id="department" name="department" value="<?php echo $department; ?>" disabled>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile update via AJAX
            document.getElementById('profile-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('backend/admin/update_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('update-profile-btn').innerHTML = '<i class="fas fa-check"></i> Profile Updated!';
                            document.getElementById('update-profile-btn').style.backgroundColor = '#28a745';
                            setTimeout(() => {
                                document.getElementById('update-profile-btn').innerHTML = '<i class="fas fa-save"></i> Update Profile';
                                document.getElementById('update-profile-btn').style.backgroundColor = '';
                                // Show notification popup
                                const notif = document.getElementById('profile-notification');
                                notif.textContent = 'Profile updated successfully!';
                                notif.style.display = 'block';
                                notif.style.opacity = '1';
                                setTimeout(() => {
                                    notif.style.opacity = '0';
                                    setTimeout(() => {
                                        notif.style.display = 'none';
                                        location.reload();
                                    }, 400);
                                }, 1500);
                            }, 1500);
                        } else {
                            alert('Update failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(() => alert('An error occurred while updating profile.'));
            });
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


            // Add hover effect to info items
            const infoItems = document.querySelectorAll('.profile-info-item');
            infoItems.forEach(item => {
                const value = item.querySelector('.profile-info-value');
                item.addEventListener('mouseenter', () => {
                    value.style.transform = 'translateX(5px)';
                });
                item.addEventListener('mouseleave', () => {
                    value.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
</body>

</html>