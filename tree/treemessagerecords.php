<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forestry Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
     <link rel="stylesheet" href="/denr/superadmin/css/treemessagerecords.css">
     <link rel="stylesheet" href="/denr/superadmin/js/treemessagerecords.js">
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
            <div class="nav-icon active">
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
            <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="dropdown-menu">
                <a href="treeprofile.php" class="dropdown-item">
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


    <!-- Main Content - Message Records -->
    <div class="main-content">
        <div class="records-container">
            <div class="records-header">
                <h2><i class="fas fa-envelope-open-text"></i> Message Records</h2>
                <a href="treemessage.php" class="compose-btn">
                    <i class="fas fa-plus"></i> New Message
                </a>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <div class="search-box">
                    <input type="text" placeholder="Search messages...">
                </div>
                <div class="filter-dropdown">
                    <select>
                        <option>All Messages</option>
                        <option>Sent</option>
                        <option>Drafts</option>
                        <option>Read</option>
                    </select>
                </div>
            </div>

            <!-- Messages Table -->
            <table class="message-table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Preview</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>JONA</td>
                        <td>Reported Incidence Resolved</td>
                        <td class="message-preview">Please find attached the monthly environmental compliance report for June...</td>
                        <td class="message-date">Jun 28, 2023</td>
                        <td><span class="status-badge status-read">Read</span></td>
                        <td>
                            <button class="action-btn" title="View"><i class="fas fa-eye"></i></button>
                            <button class="action-btn" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <button>&laquo;</button>
                <button class="active">1</button>
                <button>2</button>
                <button>3</button>
                <button>&raquo;</button>
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
    });

       

    </script>
</body>
</html>