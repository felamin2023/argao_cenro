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

    <link rel="stylesheet" href="/denr/superadmin/css/marinenotif.css">
     <link rel="stylesheet" href="/denr/superadmin/js/marinenotif.js">
    
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
            <i class="fas fa-envelope" style="color: black;"></i> <!-- Messages icon -->
        </a>
    </div>
</div>
            
            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'marinenotif.php' ? 'active' : ''; ?>">
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

    <!-- Notifications Content -->
    <div class="notifications-container">
        <div class="notifications-header">NOTIFICATIONS</div>

        <div class="notification-tabs">
            <div id="all-tab" class="tab active">All Notifications</div>
            <div id="unread-tab" class="tab">Unread <span class="tab-badge">1</span></div>
        </div>

        <div id="all-notifications" class="notification-list">
            <!-- Single Tree Cutting Notification -->
            <div class="notification-item unread" id="tree-cutting-notification">
                <div class="notification-title">
                    <div class="notification-icon"><i class="fas fa-tree"></i></div>
                    Marine Pollution Alert
                </div>
                <div class="notification-content">
                Community member reported plastic waste dumping in Lawis Beach.
                </div>
                <div class="notification-time">Today, 10:30 AM</div>
                <div class="notification-actions">
                    <button class="action-button view-details-btn">View Details</button>
                    <button class="action-button mark-read-btn">Mark as Read</button>
                </div>
            </div>
        </div>

        <div id="unread-notifications" class="notification-list" style="display: none;">
            <!-- Same notification appears in unread tab -->
            <div class="notification-item unread" id="tree-cutting-notification-unread">
                <div class="notification-title">
                    <div class="notification-icon"><i class="fas fa-tree"></i></div>
                    Marine Pollution Alert
                </div>
                <div class="notification-content">
                Community member reported plastic waste dumping in Lawis Beach.
                </div>
                <div class="notification-time">Today, 10:30 AM</div>
                <div class="notification-actions">
                    <button class="action-button view-details-btn">View Details</button>
                    <button class="action-button mark-read-btn">Mark as Read</button>
                </div>
            </div>
        </div>
        
        <div class="mark-all-button">
            <button id="mark-all-read">✓ Mark all as read</button>
        </div>
    </div>

    <!-- Modal for Notification Details -->
<div class="modal" id="notification-modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="modal-header">
            <h2>Marine Pollution Incident Details</h2>
        </div>
        <div class="modal-body">
            <p><strong>Category:</strong> Marine Pollution</p>
            <p><strong>Received:</strong> 10 minutes ago</p>
            
            <h3>Plastic Waste Dumping - Lawis Beach</h3>
            
            <p>A community member reported illegal dumping of plastic waste along the shoreline.</p>
            
            <p><strong>Location:</strong> Lawis Beach, Barangay Poblacion</p>
            <p><strong>Reported by:</strong> Local Fisherman</p>
            <p><strong>Date of Incident:</strong> Today, early morning</p>
            <p><strong>Details:</strong> Large quantities of plastic bags, bottles, and packaging materials were observed being dumped near the coastal area. The waste is beginning to spread into the water.</p>
            <p><strong>Evidence:</strong> Photos available showing the waste materials and potential source</p>
           
            <p><strong>Affected Area:</strong> Approximately 50 meters of shoreline</p>
            <p><strong>Potential Source:</strong> Nearby market waste disposal</p>
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

        // Tab switching
        const allTab = document.getElementById('all-tab');
        const unreadTab = document.getElementById('unread-tab');
        const allContent = document.getElementById('all-notifications');
        const unreadContent = document.getElementById('unread-notifications');
        
        allTab.addEventListener('click', function() {
            allTab.classList.add('active');
            unreadTab.classList.remove('active');
            allContent.style.display = 'block';
            unreadContent.style.display = 'none';
        });
        
        unreadTab.addEventListener('click', function() {
            unreadTab.classList.add('active');
            allTab.classList.remove('active');
            unreadContent.style.display = 'block';
            allContent.style.display = 'none';
        });

        // Modal functionality
        const modal = document.getElementById('notification-modal');
        const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
        const closeModal = document.querySelector('.close-modal');
        const modalCloseBtn = document.querySelector('.modal-footer .action-button:last-child');

        viewDetailsBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'flex';
            });
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        modalCloseBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Mark as read functionality
        const markReadBtns = document.querySelectorAll('.mark-read-btn');
        markReadBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const notificationItem = this.closest('.notification-item');
                notificationItem.classList.remove('unread');
                this.remove();
                updateUnreadCount();
            });
        });

        // Mark all as read functionality
        document.getElementById('mark-all-read').addEventListener('click', function() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const markAsReadButtons = item.querySelectorAll('.mark-read-btn');
                markAsReadButtons.forEach(button => button.remove());
            });
            updateUnreadCount();
        });

        // Function to update unread count
        function updateUnreadCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            const badge = document.querySelector('.tab-badge');
            const navBadge = document.querySelector('.badge');
            
            badge.textContent = unreadCount;
            navBadge.textContent = unreadCount;
            
            if (unreadCount === 0) {
                badge.style.display = 'none';
                navBadge.style.display = 'none';
            } else {
                badge.style.display = 'flex';
                navBadge.style.display = 'flex';
            }
        }
    });
    </script>
</body>
</html>