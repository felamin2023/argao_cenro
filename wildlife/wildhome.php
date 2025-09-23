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
    $isMarine = $u && strtolower((string)$u['department']) === 'wildlife';
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
    <title>Wildlife Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/wildhome.css">

</head>

<body>
    <header>
        <div class="logo">
            <a href="wildhome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

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
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Record</span>
                    </a>
                    <a href="wildpermit.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
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
                    <a href="wildmessage.php">
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
                    <div class="notification-item">
                        <a href="wildeach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">Wildlife Incident Reported</div>
                                <div class="notification-message">A large monitor lizard approximately 1.2 meters in length was spotted near a residential backyard early in the morning.</div>
                                <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer">
                        <a href="wildnotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item">
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

    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-particles">
            <!-- Particles will be generated by JS -->
        </div>
        <div class="hero-content">
            <h1 class="hero-title">ECOTRACK: A RESOURCE TRACKING AND INVENTORY SYSTEM OF DEPARTMENT OF ENVIRONMENT AND NATURAL
                RESOURCES , ARGAO
            </h1>
            <p class="hero-subtitle">Preserving biodiversity through advanced monitoring and conservation</p>
            <div class="hero-quote-container">
                <p class="hero-quote">
                    "In the end, we will conserve only what we love, we will love only what we understand, and we will understand only what we are taught."
                </p>
                <div class="hero-attribution">— Baba Dioum, Senegalese Conservationist</div>
            </div>
        </div>

    </section>


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

            // Simple notification read functionality
            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelector('.badge').style.display = 'none';
                });
            }

            // Create enhanced particles for hero section
            const particles = document.querySelector('.hero-particles');
            if (particles) {
                for (let i = 0; i < 50; i++) {
                    const particle = document.createElement('span');
                    particle.classList.add('particle');

                    // Random positioning and properties
                    particle.style.left = `${Math.random() * 100}%`;
                    particle.style.top = `${Math.random() * 100}%`;
                    particle.style.width = `${Math.random() * 6 + 2}px`;
                    particle.style.height = particle.style.width;
                    particle.style.animationDelay = `${Math.random() * 15}s`;
                    particle.style.animationDuration = `${Math.random() * 10 + 10}s`;

                    particles.appendChild(particle);
                }
            }

            // Scroll down button functionality with smooth animation
            const scrollDownBtn = document.querySelector('.scroll-down');
            if (scrollDownBtn) {
                scrollDownBtn.addEventListener('click', () => {
                    window.scrollTo({
                        top: window.innerHeight,
                        behavior: 'smooth'
                    });
                });

                // Add hover effect
                scrollDownBtn.addEventListener('mouseenter', () => {
                    scrollDownBtn.style.transform = 'translateX(-50%) scale(1.1)';
                });

                scrollDownBtn.addEventListener('mouseleave', () => {
                    scrollDownBtn.style.transform = 'translateX(-50%) scale(1)';
                });
            }

            // Parallax effect for hero section
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const heroSection = document.querySelector('.hero-section');
                const heroContent = document.querySelector('.hero-content');

                if (heroSection && heroContent) {
                    heroSection.style.backgroundPositionY = `${scrolled * 0.5}px`;
                    heroContent.style.transform = `translateY(${scrolled * 0.2}px)`;
                }
            });

            // Add intersection observer for fade-in animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, {
                threshold: 0.1
            });

            // Observe all elements with animation classes
            document.querySelectorAll('.hero-title, .hero-subtitle, .hero-quote-container').forEach(el => {
                observer.observe(el);
            });

            // Add smooth scroll for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>