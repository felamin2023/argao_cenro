<?php
// marine page guard (top of file)
declare(strict_types=1);

session_start();

// Must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo (PDO -> Postgres)

// Current user (UUID)
$user_id = (string)$_SESSION['user_id'];

try {
    $st = $pdo->prepare("
        SELECT role, department, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin  = $u && strtolower((string)$u['role']) === 'admin';
    $isMarine = $u && strtolower((string)$u['department']) === 'marine';
    // optionally require an approved/verified status:
    // $statusOk = $u && in_array(strtolower((string)$u['status']), ['verified','approved'], true);

    if (!$isAdmin || !$isMarine /* || !$statusOk */) {
        header('Location: ../superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[MARINE-GUARD] ' . $e->getMessage());
    header('Location: ../superlogin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/marinehome.css">
    <link rel="stylesheet" href="/denr/superadmin/js/marinehome.js">

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
        <!-- Modified content header section with Edit and Save buttons -->
        <div class="content-header">
            <div class="edit-actions">
                <button class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <h1>Coastal and Marine Ecosystems Management Program</h1>
        </div>

        <div class="program-highlight">
            <div class="content-header">
                <h2>Program Overview</h2>
            </div>
            <p>Implemented since 2017 under DAO 2016-26, CMEMP focuses on restoring coastal ecosystems through science-based approaches. Key 2020 accomplishments include:</p>
            <div class="performance-grid">
                <div class="metric-card">
                    <div class="metric-value">125%</div>
                    <div>MPA Management Efficiency</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">103%</div>
                    <div>Habitat Monitoring Coverage</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">114</div>
                    <div>BDFEs Supported</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">37.7M</div>
                    <div>Financial Assistance (PHP)</div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <a href="#" class="btn">
                    <i class="fas fa-download"></i> Download Full Report
                </a>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">MPA Management & Networking</h3>
            </div>

            <div class="figure-container">
                <div class="figure-title">Regional Performance on MPA Management</div>

                <!-- Chart container -->
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                    <div class="chart-legend-container">
                        <div class="chart-legend" id="chartLegend"></div>

                    </div>
                </div>
            </div>

        </div>

        <div class="component-section habitat-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Habitat Assessment & Monitoring</h3>
            </div>
            <div class="two-column">
                <div>
                    <div class="habitat-features">
                        <div class="habitat-feature">
                            <h4><i class="fas fa-ruler-combined"></i> Coastal Habitats Assessed</h4>
                            <p>7,658.79 hectares of coastal habitats comprehensively assessed using advanced GIS mapping techniques.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-tint"></i> Water Quality Monitoring</h4>
                            <p>100% of protected areas now have regular water quality monitoring with quarterly reports.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-map-marked-alt"></i> Mangrove Mapping</h4>
                            <p>28-hectare mangrove area in Cavite digitally mapped with species distribution analysis.</p>
                        </div>
                        <div class="habitat-feature">
                            <h4><i class="fas fa-coral"></i> Coral Reef Monitoring</h4>
                            <p>15 new coral reef sites established with baseline data collection and health indicators.</p>
                        </div>
                    </div>
                </div>
                <div class="figure-container">
                    <div class="figure-title">Mangrove Area in Noveleta, Cavite</div>
                    <img src="mangrove-map.png" alt="Mangrove Map" class="habitat-map">
                    <div class="chart-footer">
                        Latest survey conducted March 2023 showing 92% healthy mangrove coverage
                    </div>
                </div>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Capacity Building & Partnerships</h3>
            </div>
            <div class="stat-grid">
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-users"></i> Personnel Trained
                    </div>
                    <div class="stat-value">70</div>
                    <div class="stat-description">DENR personnel via ODL</div>
                </div>
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-user-tie"></i> Extension Officers
                    </div>
                    <div class="stat-value">63</div>
                    <div class="stat-description">CMEMP Officers hired</div>
                </div>
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fas fa-hand-holding-usd"></i> Financial Assistance
                    </div>
                    <div class="stat-value">37.7M</div>
                    <div class="stat-description">PHP to POs</div>
                </div>
            </div>
        </div>

        <div class="component-section">
            <div class="content-header">
                <h3 style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 24px; color: var(--primary-dark); text-align: center; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Strategic Roadmap: Ways Forward</h3>
            </div>
            <div class="roadmap">
                <div class="roadmap-item">
                    <h4><i class="fas fa-balance-scale"></i> Policy Development</h4>
                    <p>Strengthening MPAN institutionalization through comprehensive policy frameworks and legal instruments.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-flask"></i> Advanced Monitoring</h4>
                    <p>Enhancing water quality monitoring with IoT sensors and real-time data analytics platforms.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-chart-line"></i> Economic Valuation</h4>
                    <p>Developing models to quantify ecosystem services and their economic impact on coastal communities.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-map"></i> Verde Island Passage</h4>
                    <p>Expanding management initiatives in this biodiversity hotspot with international partnerships.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-users-cog"></i> Community Systems</h4>
                    <p>Implementing participatory monitoring systems with fisherfolk cooperatives and local governments.</p>
                </div>
                <div class="roadmap-item">
                    <h4><i class="fas fa-robot"></i> Technology Integration</h4>
                    <p>Deploying AI-powered monitoring tools and drone surveillance for illegal fishing detection.</p>
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

            // Create the performance chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['PAs assessed', 'PAs monitored', 'PAs water quality', 'MPA Network', 'Habitats monitored'],
                    datasets: [{
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

            // Edit button functionality
            const editBtn = document.querySelector('.btn-edit');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    // Here you would implement your edit functionality
                    alert('Edit mode activated. Implement your edit functionality here.');
                    // Example: Enable form fields, show editable content, etc.
                });
            }
        });
    </script>
</body>

</html>