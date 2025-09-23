<?php

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
    $isMarine = $u && strtolower((string)$u['department']) === 'tree cutting';
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
// Get the current page name

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forestry Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/treehome.css">
    <link rel="stylesheet" href="/denr/superadmin/js/treehome.js">
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
                <div class="nav-icon">
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

    <main>
        <div class="title-container">
            <h1>ECOTRACK: A RESOURCE TRACKING AND INVENTORY SYSTEM OF DEPARTMENT OF ENVIRONMENT AND NATURAL
                RESOURCES , ARGAO
            </h1>
            <div class="title-line"></div>
        </div>
        <div class="content">
            <img src="logo.png" alt="DENR Logo" class="denr-logo">
            <div class="text-content">
                <h2>Preserving Our Natural Heritage</h2>
                <p>Advanced monitoring and management of <span class="highlight">forest resources</span> to ensure sustainability for future generations.</p>
                <p>Empowering communities through <span class="highlight">responsible stewardship</span> and innovative conservation practices.</p>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                    document.body.style.overflow = navContainer.classList.contains('active') ? 'hidden' : '';
                });
            }

            // Dropdown functionality for desktop and mobile
            const dropdowns = document.querySelectorAll('.dropdown');
            const isMobile = window.innerWidth <= 992;

            function setupDropdowns() {
                dropdowns.forEach(dropdown => {
                    const toggle = dropdown.querySelector('.nav-icon');
                    const menu = dropdown.querySelector('.dropdown-menu');

                    if (isMobile) {
                        // Mobile behavior - click to toggle
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
                                toggle.setAttribute('aria-expanded', 'false');
                            } else {
                                menu.style.display = 'block';
                                toggle.setAttribute('aria-expanded', 'true');
                            }
                        });

                        // Close dropdown when clicking outside
                        document.addEventListener('click', (e) => {
                            if (!dropdown.contains(e.target)) {
                                menu.style.display = 'none';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });
                    } else {
                        // Desktop behavior - hover to show
                        dropdown.addEventListener('mouseenter', () => {
                            menu.style.opacity = '1';
                            menu.style.visibility = 'visible';
                            menu.style.transform = menu.classList.contains('center') ?
                                'translateX(-50%) translateY(0)' :
                                'translateY(0)';
                            toggle.setAttribute('aria-expanded', 'true');
                        });

                        dropdown.addEventListener('mouseleave', (e) => {
                            if (!dropdown.contains(e.relatedTarget)) {
                                menu.style.opacity = '0';
                                menu.style.visibility = 'hidden';
                                menu.style.transform = menu.classList.contains('center') ?
                                    'translateX(-50%) translateY(8px)' :
                                    'translateY(8px)';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });

                        menu.addEventListener('mouseleave', (e) => {
                            if (!dropdown.contains(e.relatedTarget)) {
                                menu.style.opacity = '0';
                                menu.style.visibility = 'hidden';
                                menu.style.transform = menu.classList.contains('center') ?
                                    'translateX(-50%) translateY(8px)' :
                                    'translateY(8px)';
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                        });
                    }
                });
            }

            // Initialize dropdowns based on screen size
            setupDropdowns();

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