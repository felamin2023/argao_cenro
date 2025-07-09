<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/marinemessage.css">
     <link rel="stylesheet" href="/denr/superadmin/js/marinemessage.js">
    

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
            
       

            <!-- Messages Icon with Active Underline -->
            <div class="nav-item">
                <div class="nav-icon active">
                    <a href="marinemessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i> <!-- Messages icon -->
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

    <!-- Main Content - Message Composition -->
    <div class="main-content">
        <div class="message-container">
            <div class="message-header">
                <h2><i class="fas fa-pen-alt"></i> Compose New Message</h2>
            </div>
            
            <form class="message-form">
                <div class="form-group">
                    <label for="to">To:</label>
                    <input type="text" id="to" class="form-control" placeholder="Enter recipient">
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" class="form-control" placeholder="Enter message subject">
                </div>
                
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" class="form-control" placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>

                    <a href="marinemessagerecords.php" class="btn view-messages-btn">
                        <i class="fas fa-inbox"></i> View All Messages
                    </a>
                </div>
            </form>
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
                    menu.style.transform = menu.classList.contains('center') 
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
    </script>
</body>
</html>