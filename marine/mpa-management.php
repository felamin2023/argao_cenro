<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/mpa-management.css">
     <link rel="stylesheet" href="/denr/superadmin/js/mpa-management.js">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
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
                    <a href="mpa-management.php" class="dropdown-item  active-page">
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
    
    <div class="main-content">
        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">MPA Management & Networking</h3>
            </div>
            
            <div class="figure-container">
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                    <div class="chart-legend-container">
                        <div class="chart-legend" id="chartLegend"></div>
                    </div>
                </div>
            </div>
           
            <div class="dashboard-cards">
                <div class="stat-card">
                    <h3>Established</h3>
                    <div class="stat-value">2017</div>
                    <p class="stat-description">Year CMEMP was first implemented</p>
                </div>
                <div class="stat-card">
                    <h3>Coverage</h3>
                    <div class="stat-value">Nationwide</div>
                    <p class="stat-description">All coastal regions of the Philippines</p>
                </div>
                <div class="stat-card">
                    <h3>Approach</h3>
                    <div class="stat-value">Science-Based</div>
                    <p class="stat-description">Community-involved ecosystem management</p>
                </div>
            </div>
        </div>

        <!-- 2020 Regional Performance Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> 2020 Regional Performance</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>Despite challenges from the COVID-19 pandemic, CMEMP achieved 100% or higher completion of targets in its fourth year of implementation.</p>
                    
                    <div class="dashboard-cards">
                        <div class="stat-card">
                            <h3>Protected Areas</h3>
                            <div class="stat-value">100%</div>
                            <p class="stat-description">of targeted PAs assessed and monitored</p>
                        </div>
                        <div class="stat-card">
                            <h3>Habitat Monitoring</h3>
                            <div class="stat-value">103%</div>
                            <p class="stat-description">of targeted MPA habitats regularly monitored</p>
                        </div>
                        <div class="stat-card">
                            <h3>Stakeholder Training</h3>
                            <div class="stat-value">115%</div>
                            <p class="stat-description">of targeted stakeholders capacitated</p>
                        </div>
                        <div class="stat-card">
                            <h3>Database Updates</h3>
                            <div class="stat-value">135%</div>
                            <p class="stat-description">of targeted database updates completed</p>
                        </div>
                    </div>
                    
                    <p>Activities included baseline assessments of corals, mangroves, and seagrass in protected areas, providing updated data on habitat extent and conditions.</p>
                </div>
            </div>
        </div>

        <!-- MPA Networking Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-network-wired"></i> MPA Networking</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>An MPA Network (MPAN) is a collection of individual MPAs operating cooperatively at various spatial scales to achieve objectives that a single reserve cannot achieve.</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-graduation-cap"></i> MPAN Training</h4>
                            <p>70 DENR personnel trained through Open Distance Learning on MPA Networking from May-August 2020</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-clipboard-check"></i> MPAN Assessment</h4>
                            <p>23 MPANs reassessed in 2020 with new threshold criteria</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-layer-group"></i> MPAN Levels</h4>
                            <p>44 at establishment level, 2 for strengthening, 1 for sustaining</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-file-signature"></i> Policy Development</h4>
                            <p>Joint DA-DENR-DILG Memorandum Circular finalized to guide MPAN establishment</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance & Protection Activities Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-shield-alt"></i> Maintenance & Protection Activities</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>38 NIPAS MPAs conducted maintenance and protection activities including patrolling, habitat surveillance, and facility repairs.</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-map-marked-alt"></i> Region 1</h4>
                            <p>Coral reef monitoring using photo transect method in BBBIDA MPAN</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-star-of-life"></i> Region 7</h4>
                            <p>COTS (Crown-of-Thorns Starfish) removal in Olango Island Wildlife Sanctuary</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-ship"></i> Region 8</h4>
                            <p>Regular seaborne patrolling in Biri-Larosa Protected Landscape</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-turtle"></i> Marine Wildlife</h4>
                            <p>Marine turtles voluntarily turned over by communities were released to protected areas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Future Directions Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-road"></i> Future Directions</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <p>The CMEMP program has identified several key priorities for future implementation:</p>
                    
                    <div class="performance-grid">
                        <div class="performance-item">
                            <h4><i class="fas fa-handshake"></i> Convergence</h4>
                            <p>Strengthening initiatives with other agencies</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-book"></i> Policy Development</h4>
                            <p>Developing supplemental guidance for CMEMP implementation</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-flask"></i> Water Quality</h4>
                            <p>Mainstreaming water quality monitoring in all NIPAS MPAs</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-business-time"></i> BDFE</h4>
                            <p>Expanding Biodiversity-Friendly Enterprise Development</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-graduation-cap"></i> Capacity Building</h4>
                            <p>Developing plans for DENR personnel training</p>
                        </div>
                        <div class="performance-item">
                            <h4><i class="fas fa-chart-pie"></i> Economic Valuation</h4>
                            <p>Assessing the economic value of coastal and marine ecosystem services</p>
                        </div>
                    </div>
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

            // Create the performance chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['PAs assessed', 'PAs monitored', 'PAs water quality', 'MPA Network', 'Habitats monitored'],
                    datasets: [
                        {
                            label: 'Target',
                            data: [100, 100, 100, 100, 100],
                            backgroundColor: 'rgba(169, 169, 169, 0.7)',
                            borderColor: 'rgba(169, 169, 169, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Actual',
                            data: [12, 100, 100, 100, 103],
                            backgroundColor: 'rgba(43, 102, 37, 0.7)',
                            borderColor: 'rgba(43, 102, 37, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 120,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    }
                }
            });
            
            // Custom legend
            const legendItems = performanceChart.data.datasets.map((dataset, i) => {
                return {
                    label: dataset.label,
                    backgroundColor: dataset.backgroundColor,
                    borderColor: dataset.borderColor
                };
            });
            
            const legendContainer = document.getElementById('chartLegend');
            legendItems.forEach(item => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                
                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = item.backgroundColor;
                colorBox.style.border = `1px solid ${item.borderColor}`;
                
                const text = document.createElement('span');
                text.textContent = item.label;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(text);
                legendContainer.appendChild(legendItem);
            });
        });
    </script>
</body>
</html>