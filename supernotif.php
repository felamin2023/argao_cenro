<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
include_once __DIR__ . '/backend/connection.php';

// Fetch all notifications (profile update requests)
$notif_query = "
    SELECT pur.id, pur.user_id, pur.created_at, pur.is_read, pur.department, pur.status, 
           pur.reviewed_at, pur.reviewed_by,
           u.first_name, u.last_name
    FROM profile_update_requests pur
    JOIN users u ON pur.user_id = u.id
    ORDER BY 
        CASE WHEN pur.status = 'pending' THEN 0 ELSE 1 END ASC,
        pur.is_read ASC,
        CASE WHEN pur.status = 'pending' THEN pur.created_at ELSE pur.reviewed_at END DESC
";
$notif_result = $conn->query($notif_query);
$notifications = [];
$unread_notifications = [];
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
    if ($row['is_read'] == 0) $unread_notifications[] = $row;
}
$conn->close();

// Helper for "15 minutes ago"
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d % 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second'
    ];

    $parts = [];
    foreach ($string as $unit => $text) {
        if ($unit === 'w') {
            $value = $weeks;
        } elseif ($unit === 'd') {
            $value = $days;
        } else {
            $value = $diff->$unit;
        }
        if ($value > 0) {
            $parts[] = $value . ' ' . $text . ($value > 1 ? 's' : '');
        }
    }
    if (!$full) {
        $parts = array_slice($parts, 0, 1);
    }
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="/denr/superadmin/css/supernotif.css">



    </style>
</head>

<body>


    <header>
        <div class="logo">
            <a href="superhome.php">
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

                    <a href="superlogs.php" class="dropdown-item">
                        <i class="fas fa-user-shield" style="color: white;"></i>
                        <span>Admin Logs</span>
                    </a>

                </div>
            </div>


            <!-- Messages Icon -->
            <div class="nav-item">
                <div class="nav-icon">
                    <a href="supermessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge">
                        <?= count(array_filter($notifications, fn($n) => $n['is_read'] == 0)) ?>
                    </span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>

                    <div class="notification-list">
                        <?php if (count($notifications) === 0): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No profile update requests</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item 
                         <?= $notif['is_read'] == 0 ? 'unread' : '' ?> 
                         status-<?= htmlspecialchars($notif['status']) ?>">
                                    <a href="supereach.php?id=<?= $notif['id'] ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <?php if ($notif['status'] == 'pending'): ?>
                                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                            <?php elseif ($notif['status'] == 'approved'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                Profile Update <?= ucfirst($notif['status']) ?>
                                                <span class="badge badge-<?=
                                                                            $notif['status'] == 'pending' ? 'warning' : ($notif['status'] == 'approved' ? 'success' : 'danger')
                                                                            ?>">
                                                    <?= ucfirst($notif['status']) ?>
                                                </span>
                                            </div>
                                            <div class="notification-message">
                                                <?= htmlspecialchars($notif['department']) ?> Administrator requested to update their profile.
                                            </div>
                                            <div class="notification-time">
                                                <?php if ($notif['status'] == 'pending'): ?>
                                                    Requested <?= time_elapsed_string($notif['created_at']) ?>
                                                <?php else: ?>
                                                    <?= ucfirst($notif['status']) ?> by
                                                    <?= htmlspecialchars($notif['reviewed_by']) ?>
                                                    <?= time_elapsed_string($notif['reviewed_at']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="notification-footer">
                        <a href="supernotif.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'treeprofile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="superlogin.php" class="dropdown-item">
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
            <div id="unread-tab" class="tab">Unread <span class="tab-badge"><?= count($unread_notifications) ?></span></div>
        </div>
        <div id="all-notifications" class="notification-list">
            <?php if (count($notifications) === 0): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No profile update requests</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?> status-<?= htmlspecialchars($notif['status']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon">
                                <?php if ($notif['status'] == 'pending'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                <?php elseif ($notif['status'] == 'approved'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </div>
                            Profile Update <?= ucfirst($notif['status']) ?>
                            <span class="badge badge-<?= $notif['status'] == 'pending' ? 'warning' : ($notif['status'] == 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst($notif['status']) ?>
                            </span>
                        </div>
                        <div class="notification-content">
                            <?= htmlspecialchars($notif['department']) ?> Administrator requested to update their profile.
                        </div>
                        <div class="notification-time">
                            <?php if ($notif['status'] == 'pending'): ?>
                                Requested <?= time_elapsed_string($notif['created_at']) ?>
                            <?php else: ?>
                                <?= ucfirst($notif['status']) ?> by
                                <?= htmlspecialchars($notif['reviewed_by']) ?>
                                <?= time_elapsed_string($notif['reviewed_at']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-actions">
                            <button class="action-button view-details-btn" data-id="<?= $notif['id'] ?>">View Details</button>
                            <?php if ($notif['is_read'] == 0): ?>
                                <button class="action-button mark-read-btn" data-id="<?= $notif['id'] ?>">Mark as Read</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div id="unread-notifications" class="notification-list" style="display: none;">
            <?php if (count($unread_notifications) === 0): ?>
                <div class="notification-item">
                    <div class="notification-content">
                        <div class="notification-title">No unread notifications</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($unread_notifications as $notif): ?>
                    <div class="notification-item unread status-<?= htmlspecialchars($notif['status']) ?>">
                        <div class="notification-title">
                            <div class="notification-icon">
                                <?php if ($notif['status'] == 'pending'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                <?php elseif ($notif['status'] == 'approved'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger"></i>
                                <?php endif; ?>
                            </div>
                            Profile Update <?= ucfirst($notif['status']) ?>
                            <span class="badge badge-<?= $notif['status'] == 'pending' ? 'warning' : ($notif['status'] == 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst($notif['status']) ?>
                            </span>
                        </div>
                        <div class="notification-content">
                            <?= htmlspecialchars($notif['department']) ?> Administrator requested to update their profile.
                        </div>
                        <div class="notification-time">
                            <?php if ($notif['status'] == 'pending'): ?>
                                Requested <?= time_elapsed_string($notif['created_at']) ?>
                            <?php else: ?>
                                <?= ucfirst($notif['status']) ?> by
                                <?= htmlspecialchars($notif['reviewed_by']) ?>
                                <?= time_elapsed_string($notif['reviewed_at']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-actions">
                            <button class="action-button view-details-btn" data-id="<?= $notif['id'] ?>">View Details</button>
                            <button class="action-button mark-read-btn" data-id="<?= $notif['id'] ?>">Mark as Read</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                <h2>Admin Profile Update Request</h2>
            </div>
            <div class="modal-body">
                <p><strong>Category:</strong> Administrator Request</p>
                <p><strong>Received:</strong> Today, 10:30 AM</p>

                <h3>Username Change Request</h3>

                <p>The Seedlings Administrator has requested to change their username.</p>

                <p><strong>Requested by:</strong> Seedlings Administrator</p>
                <p><strong>Request Date:</strong> <?php echo date('F j, Y'); ?></p>
                <p><strong>Current Username:</strong> seedlings_admin</p>
                <p><strong>Requested New Username:</strong> seedlings_administrator</p>
                <p><strong>Reason for Change:</strong> Standardizing admin usernames across the system</p>
                <p><strong>Priority:</strong> Medium</p>
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
        });



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

        // View Details button: open supereach.php for the selected record
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = btn.getAttribute('data-id');
                window.location.href = 'supereach.php?id=' + id;
            });
        });

        // Mark as Read button: AJAX to mark as read, update UI
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = btn.getAttribute('data-id');
                fetch('backend/admin/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + encodeURIComponent(id)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            btn.closest('.notification-item').classList.remove('unread');
                            btn.remove();
                            updateUnreadCount();
                        }
                    });
            });
        });

        // Mark all as read button: AJAX to mark all as read, update UI
        document.getElementById('mark-all-read').addEventListener('click', function() {
            fetch('backend/admin/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'mark_all=1'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                            const btn = item.querySelector('.mark-read-btn');
                            if (btn) btn.remove();
                        });
                        updateUnreadCount();
                    }
                });
        });

        // Update unread count badge
        function updateUnreadCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            document.querySelector('.tab-badge').textContent = unreadCount;
            document.querySelector('.badge').textContent = unreadCount;
            document.querySelector('.tab-badge').style.display = unreadCount === 0 ? 'none' : 'inline-block';
            document.querySelector('.badge').style.display = unreadCount === 0 ? 'none' : 'inline-block';
        }
    </script>
</body>

</html>