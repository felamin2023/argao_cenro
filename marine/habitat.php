<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habitat Assessment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

      <link rel="stylesheet" href="/denr/superadmin/css/habitat.css">
        <link rel="stylesheet" href="/denr/superadmin/js/habitat.js">


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
                    <a href="habitat.php" class="dropdown-item active-page">
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
                <i class="fas fa-tree"></i> Coastal and Marine Habitat Assessment
            </h1>
            <p class="page-description">
                Comprehensive monitoring and assessment of coral reefs, mangroves, and seagrass ecosystems across protected areas in the Philippines. 
                Data collected supports the Coastal and Marine Ecosystems Management Program (CMEMP) for evidence-based conservation strategies.
            </p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Protected Areas Assessed</h3>
                <div class="stat-value">100%</div>
                <p class="stat-description">Target achievement for 2020</p>
            </div>
            <div class="stat-card">
                <h3>Protected Areas Monitored</h3>
                <div class="stat-value">100%</div>
                <p class="stat-description">Target achievement for 2020</p>
            </div>
            <div class="stat-card">
                <h3>Habitats Assessed</h3>
                <div class="stat-value">7,658 ha</div>
                <p class="stat-description">Total coastal and marine habitats</p>
            </div>
            <div class="stat-card">
                <h3>MPA Networks</h3>
                <div class="stat-value">47</div>
                <p class="stat-description">Identified for networking</p>
            </div>
        </div>

        <!-- Habitat Assessment Overview Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-water"></i> Habitat Assessment Overview</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>
                        Baseline assessment provides updated evaluation of the extent and condition of coastal and marine habitats, 
                        including factors, threats, and pressures affecting their state and health. Regular habitat assessments 
                        are conducted in established monitoring stations to gather annual conditions of coral reefs, seagrass beds, 
                        and mangroves, assess trends, and determine recovery from threats.
                    </p>
                    
                    <div class="centered-content">
                        <img src="region.png" alt="Map of assessed habitats" class="habitat-image">
                        <p class="image-caption">Figure 11.4: Map of the habitats assessed in two NIPAS MPAs in Region 10</p>
                    </div>
                    
                    <h3>Key Findings:</h3>
                    <ul class="threat-list">
                        <li class="threat-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            High wave amplitude and frequency affecting Initao-Libertad Protected Landscape and Seascape
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            Garbage accumulation within coral reef ecosystems
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            Tourism activities (bathing, boating, snorkeling) impacting sensitive areas
                        </li>
                        <li class="threat-item">
                            <i class="fas fa-exclamation-triangle"></i>
                            Decrease of coral areas due to influx of riverine sediments in Bacolod-Kauswagan PLS
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Regional Habitat Assessment Data Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-map-marked-alt"></i> Regional Habitat Assessment Data</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Protected Area</th>
                                <th>Habitat Type</th>
                                <th>Extent (ha)</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>R4A</td>
                                <td>-</td>
                                <td>Seagrass</td>
                                <td>2.20</td>
                                <td>Maragondon and Ternate</td>
                            </tr>
                            <tr>
                                <td>R4A</td>
                                <td>-</td>
                                <td>Coral reefs</td>
                                <td>510.17</td>
                                <td>Ragay Gulf (Quezon)</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Coral reefs</td>
                                <td>574.25</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Seagrass</td>
                                <td>3,790.85</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>R10</td>
                                <td>Bacolod-Kauswagan PLS</td>
                                <td>Coral reefs</td>
                                <td>262.26</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>R9</td>
                                <td>-</td>
                                <td>Mangroves, seagrass & coral reefs</td>
                                <td>1,966.20</td>
                                <td>Zamboanga City</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    
                </div>
            </div>
        </div>

        <!-- Water Quality Monitoring Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-flask"></i> Water Quality Monitoring</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>
                        Water quality monitoring within Protected Areas is vital to account for pollution load and other water-related 
                        parameters that can be attributed to causes of pollution in coastal and marine ecosystems. In 2020, water 
                        quality monitoring based on in-situ parameters has been conducted in monitoring stations of 15 PAs as targeted.
                    </p>
                    
                    <div class="centered-content">
                        <img src="water.png" alt="Water quality monitoring" class="habitat-image">
                        <p class="image-caption">Figure 11.8: Water quality monitoring of marine and river waterbodies in Regions 3 and 5</p>
                    </div>
                    
                    <p>
                        Researchers recommend that besides capacity building for water quality methods, a more comprehensive monitoring 
                        plan must be prepared and implemented for more accurate datasets on required parameters and more appropriate 
                        classification of water bodies.
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