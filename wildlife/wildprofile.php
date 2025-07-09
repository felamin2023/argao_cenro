<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="/denr/superadmin/css/wildprofile.css">
</head>
<body>




<header>
    <div class="logo">
        <a href="wildhome.php">
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
                <a href="breedingreport.php" class="dropdown-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Record</span>
                </a>  

                 <a href="wildpermit.php" class="dropdown-item">
                    <i class="fas fa-paw"></i>
                    <span>Wildlife Permit</span>
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
                <a href="wildmessage.php">
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
                    <a href="wildeach.php?id=1" class="notification-link">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                        <div class="notification-title">Wildlife Incident Reported</div>
                            <div class="notification-message">A large monitor lizard approximately 1.2 meters in length was spotted near a residential backyard early in the morning.</div>
                            <div class="notification-time">15 minutes ago</div>
                        </div>
                    </a>
                </div>
                <div class="notification-footer">
                    <a href="wildnotification.php" class="view-all">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon active">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="dropdown-menu">
                <a href="wildprofile.php" class="dropdown-item active-page">
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

    <!-- Profile Content -->
    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Admin Profile</h1>
            <p class="profile-subtitle">View your account information</p>
        </div>
        
        <div class="profile-body">
            <div class="profile-picture-container">
                <img src="default-profile.jpg" alt="Profile Picture" class="profile-picture" id="profile-picture">
                <div class="profile-picture-placeholder" id="profile-placeholder">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-upload-icon" onclick="document.getElementById('profile-upload-input').click()">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            
            <input type="file" id="profile-upload-input" accept="image/*">
            
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <div class="profile-info-label">First Name:</div>
                    <div class="profile-info-value" id="first-name">Juan</div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Last Name:</div>
                    <div class="profile-info-value" id="last-name">Dela Cruz</div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Username:</div>
                    <div class="profile-info-value" id="username">admin.juan</div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Email:</div>
                    <div class="profile-info-value" id="email">admin.juan@example.com</div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Role:</div>
                    <div class="profile-info-value" id="role">Administrator</div>
                </div>
                
                <div class="profile-info-item">
                    <div class="profile-info-label">Department:</div>
                    <div class="profile-info-value" id="department">CENRO</div>
                </div>
            </div>
            
            <div class="profile-actions">
                <button class="btn btn-primary" id="update-profile-btn">
                    <i class="fas fa-save"></i> Update
                </button>
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
                menu.style.transform = menu.classList.contains('center') 
                    ? 'translateX(-50%) translateY(0)' 
                    : 'translateY(0)';
            });
            
            // Hide menu when leaving both button and menu
            dropdown.addEventListener('mouseleave', (e) => {
                // Check if we're leaving the entire dropdown area
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.class.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
            
            // Additional check for menu mouseleave
            menu.addEventListener('mouseleave', (e) => {
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
        });
        
        // Close dropdowns when clicking outside (for mobile)
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
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

        // Update profile button functionality
        document.getElementById('update-profile-btn').addEventListener('click', function() {
            alert('Profile updated successfully!');
        });
    });
    </script>
</body>
</html>