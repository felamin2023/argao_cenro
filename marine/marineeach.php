<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <link rel="stylesheet" href="/denr/superadmin/css/marineeach.css">
     <link rel="stylesheet" href="/denr/superadmin/js/marineeach.js">
   
</head>
<body>
    <!-- Header remains unchanged -->
    <header>
        <div class="logo">
            <a href="marinehome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>
        
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-container">
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
        <a href="messages.php">
            <i class="fas fa-envelope" style="color: black;"></i> <!-- Messages icon -->
        </a>
    </div>
</div>
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

    <!-- UPDATED ACCIDENT REPORT FORM -->
    <div class="accident-report-container">
        <h1 class="accident-report-header">Incident Report Details</h1>
        
        <div class="accident-report-form">
            <!-- First row -->
            <div class="accident-form-group">
                <label>FIRST NAME</label>
                <div class="accident-form-value">John</div>
            </div>
            
            <div class="accident-form-group">
                <label>LAST NAME</label>
                <div class="accident-form-value">Doe</div>
            </div>
            
            <div class="accident-form-group">
                <label>AGE</label>
                <div class="accident-form-value">35</div>
            </div>
            
            <div class="accident-form-group">
                <label>CONTACT NO</label>
                <div class="accident-form-value">+639123456789</div>
            </div>
            
            <!-- Second row - Location and Date/Time -->
            <div class="accident-form-group">
                <label>LOCATION</label>
                <div class="accident-form-value">Argao, Cebu</div>
            </div>
            
            <div class="accident-form-group">
                <label>DATE & TIME</label>
                <div class="accident-form-value">12/04/2025 08:07</div>
            </div>
            
            <!-- Photo in the right corner -->
            <div class="accident-form-group accident-photo-group">
                <label>PHOTO</label>
                <div class="accident-photo-display">
                    <i class="fas fa-camera"></i>
                    <p>accident-photo.jpg</p>
                </div>
            </div>
            
            <!-- Description field below location and date, aligned with photo -->
            <div class="accident-form-group" style="grid-column: span 2;">
                <label>DESCRIPTION OF ACCIDENT</label>
                <div class="accident-form-value" style="min-height: 140px;">
                An environmental violation involving the illegal dumping of plastic waste along the shoreline of Lawis Beach. 
                </div>
            </div>
            
            <!-- Status group with radio buttons - now spans 2 columns and is centered -->
            <div class="accident-form-group accident-status-group">
                <label>STATUS</label>
                <div class="accident-status-values">
                    <div class="radio-option">
                        <input type="radio" id="status-pending" name="status" checked>
                        <label for="status-pending">Pending</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="status-resolved" name="status">
                        <label for="status-resolved">Resolved</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="status-rejected" name="status">
                        <label for="status-rejected">Rejected</label>
                    </div>
                </div>
            </div>
            
            <!-- NEW: Save button container -->
            <div class="save-button-container">
                <button class="save-button">SAVE</button>
            </div>
        </div>
    </div>

    <!-- JavaScript remains unchanged -->
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

        // NEW: Save button functionality
        const saveButton = document.querySelector('.save-button');
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                // Get the selected status
                const selectedStatus = document.querySelector('input[name="status"]:checked').nextElementSibling.textContent;
                
                // Here you would typically send the data to the server
                alert(`Report saved with status: ${selectedStatus}`);
                
               
            });
        }
    });
    </script>
</body>
</html>