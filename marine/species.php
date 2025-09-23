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

    <link rel="stylesheet" href="/denr/superadmin/css/species.css">
      <link rel="stylesheet" href="/denr/superadmin/js/species.js">
   
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
                <div class="nav-icon active">
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
                    <a href="species.php" class="dropdown-item active-page">
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
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-fish"></i> Coastal and Marine Species Monitoring
            </h1>
            <p class="page-description">
                Tracking and conservation of key marine species across protected areas in the Philippines. 
                Data collected supports the Coastal and Marine Ecosystems Management Program (CMEMP) for biodiversity conservation.
            </p>
        </div>

        <!-- Species Monitoring Overview Section - Now contains the stats grid inside -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-clipboard-check"></i> Species Monitoring Overview</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>
                        The CMEMP includes regular monitoring of marine species to assess population health, identify threats, 
                        and implement conservation measures. Monitoring activities are conducted through scientific surveys, 
                        community reporting, and technological tools.
                    </p>
                    
                    <!-- Stats Grid moved inside the Species Monitoring Overview section -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Protected Areas Monitored</h3>
                            <div class="stat-value">18/18</div>
                            <p class="stat-description">Target achievement for 2020</p>
                        </div>
                        <div class="stat-card">
                            <h3>Water Quality Sites</h3>
                            <div class="stat-value">15</div>
                            <p class="stat-description">Monitoring stations assessed</p>
                        </div>
                        <div class="stat-card">
                            <h3>MPA Networks</h3>
                            <div class="stat-value">47</div>
                            <p class="stat-description">Identified for species protection</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Species Monitoring Activities Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-water"></i> Key Species Monitoring Activities</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="species-grid">
                        <!-- Giant Clams Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Giant Clams <span>(Tridacna gigas)</span></h3>
                                <span class="species-status">Vulnerable</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Twin Rocks Sanctuary, Mabini, Batangas (Region 4A)</p>
                                <p><strong>Activities:</strong> Restocking program with survival rate and growth pattern monitoring</p>
                                <p><strong>Threats:</strong> Poaching observed in Northern Sierra Madre Natural Park (Region 2)</p>
                            </div>
                        </div>
                        
                        <!-- Marine Turtles Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Marine Turtles <span>(Cheloniidae/Dermochelyidae)</span></h3>
                                <span class="species-status endangered">Endangered</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Cuatro Islas Protected Landscape and Seascape (Region 8)</p>
                                <p><strong>Activities:</strong> Voluntary turnover and release by Land of Paradise Farmers Association</p>
                                <p><strong>Conservation:</strong> Community-based protection initiatives</p>
                            </div>
                        </div>
                        
                        <!-- COTS Card -->
                        <div class="species-card">
                            <div class="species-header">
                                <h3 class="species-name">Crown-of-Thorns Starfish <span>(Acanthaster planci)</span></h3>
                                <span class="species-status threat">Outbreak Control</span>
                            </div>
                            <div class="species-body">
                                <p><strong>Location:</strong> Olango Island Wildlife Sanctuary (Region 7)</p>
                                <p><strong>Activities:</strong> Removal program with boundary delineation and marker installation</p>
                                <p><strong>Impact:</strong> Prevents rapid destruction of coral reefs</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Threats and Conservation Challenges Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Threats and Conservation Challenges</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <ul class="threat-list">
                        <li class="threat-item">
                            <i class="fas fa-trash"></i>
                            <strong>Poaching:</strong> Empty shells of giant clams found scattered in Northern Sierra Madre Natural Park waters (Region 2)
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-thermometer-full"></i>
                            <strong>Coral Bleaching:</strong> Completely bleached bubble-tip sea anemone found during validation in BBBIDA MPAN waters (Region 1)
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-ship"></i>
                            <strong>Tourism Pressure:</strong> Activities like bathing, boating, and snorkeling impacting sensitive species habitats
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-water"></i>
                            <strong>Sedimentation:</strong> Influx of riverine sediments affecting coral areas in Bacolod-Kauswagan PLS
                        </li>
                    </ul>
                    
                    <img src="coral.png" alt="Coral reef monitoring" class="species-image">
                    <p class="image-caption">Figure 11.9: Coral reef monitoring using photo transect method in BBBIDA MPAN, Region 1</p>
                </div>
            </div>
        </div>

        <!-- Protection and Conservation Measures Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-shield-alt"></i> Protection and Conservation Measures</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>MPAs Maintained</h3>
                            <div class="stat-value">38</div>
                            <p class="stat-description">NIPAS MPAs with protection activities</p>
                        </div>
                        <div class="stat-card">
                            <h3>Patrolling</h3>
                            <div class="stat-value">100%</div>
                            <p class="stat-description">Regular surveillance conducted</p>
                        </div>
                        <div class="stat-card">
                            <h3>Community Engagement</h3>
                            <div class="stat-value">114 POs</div>
                            <p class="stat-description">People's Organizations involved</p>
                        </div>
                    </div>
                    
                    <p>
                        Maintenance and protection activities in 2020 included patrolling, habitat surveillance, 
                        direct conservation activities, and repair of signages/equipment across 38 NIPAS MPAs 
                        (32 Legislated, 4 Proclaimed and 2 Initial Components).
                    </p>
                    
                   
                </div>
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

            // Collapsible sections functionality
            const sectionHeaders = document.querySelectorAll('.section-header');
            sectionHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const section = header.parentElement;
                    section.classList.toggle('active');
                });
            });
        });
    </script>
</body>
</html>