<?php
declare(strict_types=1);
session_start();
// Basic guard: must be logged in and belong to marine admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // exposes $pdo

if (!function_exists('h')) {
    function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false): string {
        if (!$datetime) return '';
        $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $ago  = new DateTime($datetime, new DateTimeZone('UTC'));
        $ago->setTimezone(new DateTimeZone('Asia/Manila'));
        $diff = $now->diff($ago);
        $weeks = (int)floor($diff->d / 7);
        $days  = $diff->d % 7;
        $map   = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
        $parts = [];
        foreach ($map as $k => $label) {
            $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
            if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
        }
        if (!$full) $parts = array_slice($parts, 0, 1);
        return $parts ? implode(', ', $parts) . ' ago' : 'just now';
    }
}

$marineNotifs = [];
$unreadMarine = 0;
try {
    $marineNotifs = $pdo->query("SELECT
            n.notif_id,
            n.message,
            n.is_read,
            n.created_at,
            n.\"from\" AS notif_from,
            n.\"to\"   AS notif_to,
            a.approval_id,
            COALESCE(NULLIF(btrim(a.permit_type), ''), 'none')        AS permit_type,
            COALESCE(NULLIF(btrim(a.approval_status), ''), 'pending') AS approval_status,
            LOWER(COALESCE(a.request_type,''))                        AS request_type,
            c.first_name  AS client_first,
            c.last_name   AS client_last,
            n.incident_id,
            n.reqpro_id
        FROM public.notifications n
        LEFT JOIN public.approval a ON a.approval_id = n.approval_id
        LEFT JOIN public.client   c ON c.client_id = a.client_id
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'marine'
        ORDER BY n.created_at DESC
        LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadMarine = (int)$pdo->query("SELECT COUNT(*) FROM public.notifications n WHERE LOWER(COALESCE(n.\"to\", '')) = 'marine' AND n.is_read = false")->fetchColumn();
} catch (Throwable $e) {
    error_log('[REPORTS NOTIFS] ' . $e->getMessage());
    $marineNotifs = [];
    $unreadMarine = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marine and Coastal Informations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/reports.css">
    <link rel="stylesheet" href="/denr/superadmin/js/reports.js">


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
                 
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>
                </div>
            </div>



            <!-- Notifications -->
            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadMarine ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="marineNotifList">
                        <?php
                        $combined = [];

                        // Permits / notifications
                        foreach ($marineNotifs as $nf) {
                            $combined[] = [
                                'id'          => $nf['notif_id'],
                                'notif_id'    => $nf['notif_id'],
                                'approval_id' => $nf['approval_id'] ?? null,
                                'incident_id' => $nf['incident_id'] ?? null,
                                'reqpro_id'   => $nf['reqpro_id'] ?? null,
                                'is_read'     => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'message'     => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' submitted a marine request.')),
                                'ago'         => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'        => !empty($nf['reqpro_id']) ? 'marineprofile.php' : (!empty($nf['approval_id']) ? 'mpa-management.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'marinenotif.php'))
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No marine notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                                $notifTitle = !empty($item['incident_id']) ? 'Incident report' : (!empty($item['reqpro_id']) ? 'Profile update' : 'Marine Request');
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= h($item['id']) ?>">
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= $notifTitle ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="marinenotif.php" class="view-all">View All Notifications</a></div>
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
 <div class="main-content">
        <!-- Filter dropdown container above the title -->
        <div class="filter-container">
            <div class="filter-group">
                <div class="filter-dropdown">
                    <button class="filter-btn">
                        <i class="fas fa-chart-pie"></i> Filter by Category
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-content">
                        <a href="marinehome.php" class="filter-item">All Categories</a>
                        <a href="mpa-management.php" class="filter-item">MPA Management</a>
                        <a href="habitat.php" class="filter-item">Habitat Assessment</a>
                        <a href="species.php" class="filter-item">Species Monitoring</a>
                        <a href="reports.php" class="filter-item active">Reports & Analytics</a>

                    </div>
                </div>

              
            </div>
        </div>
        
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-bar"></i> CMEMP Reports & Analytics
            </h1>
            <p class="page-description">
                Comprehensive reports and analytics dashboard for the Coastal and Marine Ecosystems Management Program (CMEMP).
                Track program performance, habitat assessments, MPA networking, and biodiversity-friendly enterprises.
            </p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Program Performance</h3>
                <div class="stat-value">100%+</div>
                <p class="stat-description">Targets Achieved</p>
            </div>
            <div class="stat-card">
                <h3>Protected Areas</h3>
                <div class="stat-value">18/18</div>
                <p class="stat-description">PAs Monitored</p>
            </div>
            <div class="stat-card">
                <h3>Water Quality</h3>
                <div class="stat-value">15</div>
                <p class="stat-description">PAs Monitored</p>
            </div>
            <div class="stat-card">
                <h3>MPA Networks</h3>
                <div class="stat-value">47</div>
                <p class="stat-description">Identified</p>
            </div>
        </div>


        <!-- Habitat Assessment Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-tree"></i> Habitat Assessment</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="chart-container">
                        <canvas id="habitatExtentChart"></canvas>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Protected Area</th>
                                <th>Habitat</th>
                                <th>Extent (ha)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>R4A</td>
                                <td>Maragondon and Ternate</td>
                                <td>Seagrass</td>
                                <td>2.20</td>
                            </tr>
                            <tr>
                                <td>R4A</td>
                                <td>Ragay Gulf</td>
                                <td>Coral Reefs</td>
                                <td>510.17</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Coral Reefs</td>
                                <td>574.25</td>
                            </tr>
                            <tr>
                                <td>R7</td>
                                <td>Olango Is Wildlife Sanctuary</td>
                                <td>Seagrass</td>
                                <td>3,790.85</td>
                            </tr>
                            <tr>
                                <td>R10</td>
                                <td>Bacolod-Kauswagan PLS</td>
                                <td>Coral Reefs</td>
                                <td>262.26</td>
                            </tr>
                            <tr>
                                <td>R10</td>
                                <td>Initao-Libertad PLS</td>
                                <td>Seagrass</td>
                                <td>524.87</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="progress-container">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Mangrove Expansion</span>
                                <span>57.4 ha</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
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
                    <div class="chart-container">
                        <canvas id="mpaNetworkChart"></canvas>
                    </div>

                    <div class="progress-container">
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Establishment Level</span>
                                <span>44 MPANs</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 94%"></div>
                            </div>
                        </div>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Strengthening</span>
                                <span>2 MPANs</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 4%"></div>
                            </div>
                        </div>
                        <div class="progress-item">
                            <div class="progress-label">
                                <span>Sustaining</span>
                                <span>1 MPAN</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 2%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biodiversity-Friendly Enterprises Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-leaf"></i> Biodiversity-Friendly Enterprises</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-value">114/111</div>
                            <div class="stat-label">POs Assisted</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">₱37.8M</div>
                            <div class="stat-label">Financial Assistance</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">6</div>
                            <div class="stat-label">Training Sessions</div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="bdfeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Marketing & Public Awareness Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-bullhorn"></i> Social Marketing & Public Awareness</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="chart-container">
                        <canvas id="awarenessChart"></canvas>
                    </div>

                    <div class="chart-container">
                        <canvas id="communicationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capacity Building Section -->
        <div class="collapsible-section">
            <div class="section-header">
                <h2><i class="fas fa-graduation-cap"></i> Capacity Building</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="section-content">
                <div class="section-content-inner">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-value">70</div>
                            <div class="stat-label">Personnel Trained</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">63</div>
                            <div class="stat-label">Extension Officers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">4</div>
                            <div class="stat-label">Webinar Episodes</div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="webinarChart"></canvas>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <script>
        // Collapsible sections functionality
        const sectionHeaders = document.querySelectorAll('.section-header');
        sectionHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const section = header.parentElement;
                section.classList.toggle('active');
            });
        });





        // Habitat Extent Chart
        const habitatExtentCtx = document.getElementById('habitatExtentChart').getContext('2d');
        const habitatExtentChart = new Chart(habitatExtentCtx, {
            type: 'pie',
            data: {
                labels: ['Coral Reefs', 'Seagrass', 'Mangroves'],
                datasets: [{
                    data: [1346.68, 4821.92, 28],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw.toLocaleString() + ' ha';
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // MPA Network Chart
        const mpaNetworkCtx = document.getElementById('mpaNetworkChart').getContext('2d');
        const mpaNetworkChart = new Chart(mpaNetworkCtx, {
            type: 'doughnut',
            data: {
                labels: ['Establishment', 'Strengthening', 'Sustaining'],
                datasets: [{
                    data: [44, 2, 1],
                    backgroundColor: [
                        'rgba(43, 102, 37, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(54, 162, 235, 0.7)'
                    ],
                    borderColor: [
                        'rgba(43, 102, 37, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // BDFE Chart
        const bdfeCtx = document.getElementById('bdfeChart').getContext('2d');
        const bdfeChart = new Chart(bdfeCtx, {
            type: 'bar',
            data: {
                labels: ['Sea Cucumber Ranching', 'Ecotourism', 'Sustainable Fisheries', 'Mangrove Products'],
                datasets: [{
                    label: 'Number of Enterprises',
                    data: [15, 8, 12, 5],
                    backgroundColor: 'rgba(43, 102, 37, 0.7)',
                    borderColor: 'rgba(43, 102, 37, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Enterprises'
                        }
                    }
                }
            }
        });

        // Awareness Chart
        const awarenessCtx = document.getElementById('awarenessChart').getContext('2d');
        const awarenessChart = new Chart(awarenessCtx, {
            type: 'bar',
            data: {
                labels: ['Aware of PA Status', 'Feel Need to Protect'],
                datasets: [{
                    label: 'Percentage of Respondents',
                    data: [96.7, 39.2],
                    backgroundColor: [
                        'rgba(43, 102, 37, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(43, 102, 37, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                }
            }
        });

        // Communication Chart
        const communicationCtx = document.getElementById('communicationChart').getContext('2d');
        const communicationChart = new Chart(communicationCtx, {
            type: 'polarArea',
            data: {
                labels: ['Television', 'Radio', 'Social Media', 'DENR/LGU Officials'],
                datasets: [{
                    data: [40, 30, 20, 10],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Webinar Chart
        const webinarCtx = document.getElementById('webinarChart').getContext('2d');
        const webinarChart = new Chart(webinarCtx, {
            type: 'line',
            data: {
                labels: ['Episode 1', 'Episode 2', 'Episode 3', 'Episode 4'],
                datasets: [{
                    label: 'Average Attendance',
                    data: [383, 383, 383, 383],
                    borderColor: 'rgba(43, 102, 37, 1)',
                    backgroundColor: 'rgba(43, 102, 37, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Engagement (Comments/Reacts)',
                    data: [1300, 1300, 1300, 1300],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Reach',
                    data: [17225, 17225, 17225, 17225],
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    }
                }
            }
        });
    </script>

    <script>
        // Collapsible sections functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Section toggle functionality
            const sectionTitles = document.querySelectorAll('.section-title');

            sectionTitles.forEach(title => {
                title.addEventListener('click', () => {
                    const content = title.nextElementSibling;
                    title.classList.toggle('collapsed');

                    if (title.classList.contains('collapsed')) {
                        content.classList.add('collapsed');
                    } else {
                        content.classList.remove('collapsed');
                    }
                });
            });

            // Card toggle functionality
            const cardHeaders = document.querySelectorAll('.card-header');

            cardHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const card = header.parentElement;
                    const content = Array.from(card.children).filter(child => child !== header);

                    card.classList.toggle('collapsed');

                    content.forEach(item => {
                        if (card.classList.contains('collapsed')) {
                            item.style.display = 'none';
                        } else {
                            item.style.display = '';
                        }
                    });
                });
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
        });
    </script>
</body>

</html>