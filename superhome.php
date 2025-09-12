<?php
// superhome.php
declare(strict_types=1);

session_start();

// Gate: must be logged in and an Admin
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'admin') {
    header('Location: superlogin.php');
    exit();
}

// Use the PDO connection (Supabase/Postgres) from your backend
require_once __DIR__ . '/backend/connection.php'; // must expose $pdo (PDO instance)

// Current user (UUID string)
$user_id = (string)$_SESSION['user_id'];

try {
    // Verify this admin belongs to CENRO
    $st = $pdo->prepare("
        SELECT department, role, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_id]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    $isAdmin = $me && strtolower((string)$me['role']) === 'admin';
    $isCenro = $me && strtolower((string)$me['department']) === 'cenro';

    if (!$isAdmin || !$isCenro) {
        header('Location: superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SUPERHOME AUTH] ' . $e->getMessage());
    header('Location: superlogin.php');
    exit();
}

// =================== Notifications (PDO) — UNCHANGED ===================
$notifications = [];
try {
    // If your table is public.profile_update_requests and it references users.user_id (uuid):
    $notif_sql = "
        SELECT
            pur.id,
            pur.user_id,
            pur.created_at,
            pur.is_read,
            pur.department,
            pur.status,
            pur.reviewed_at,
            pur.reviewed_by,
            u.first_name,
            u.last_name
        FROM public.profile_update_requests pur
        JOIN public.users u
          ON pur.user_id = u.user_id
        ORDER BY
            CASE WHEN lower(pur.status) = 'pending' THEN 0 ELSE 1 END ASC,
            pur.is_read ASC,
            CASE WHEN lower(pur.status) = 'pending' THEN pur.created_at ELSE pur.reviewed_at END DESC
    ";

    $notifications = $pdo->query($notif_sql)->fetchAll(PDO::FETCH_ASSOC); // returns [] if none
} catch (Throwable $e) {
    error_log('[SUPERHOME NOTIFS] ' . $e->getMessage());
    $notifications = [];
}

// =================== Helper ===================
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);

    $weeks = (int)floor($diff->d / 7);
    $days  = $diff->d % 7;

    $map = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = [];
    foreach ($map as $k => $label) {
        $v = ($k === 'w') ? $weeks : (($k === 'd') ? $days : $diff->$k);
        if ($v > 0) $parts[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
    }
    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// =================== Admin table: fetch with PDO/Postgres ===================
$searchValue = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$statusValue = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

$where = "lower(role) = 'admin' AND lower(department) <> 'cenro'";
$params = [];

if ($searchValue !== '') {
    // Use ILIKE (case-insensitive) + cast non-text to text for pattern search
    $like = '%' . $searchValue . '%';
    $where .= " AND (
        CAST(id AS text) ILIKE :s_id
        OR first_name ILIKE :s_fn
        OR last_name ILIKE :s_ln
        OR CAST(age AS text) ILIKE :s_age
        OR email ILIKE :s_em
        OR department ILIKE :s_dep
        OR status ILIKE :s_st
    )";
    $params[':s_id']  = $like;
    $params[':s_fn']  = $like;
    $params[':s_ln']  = $like;
    $params[':s_age'] = $like;
    $params[':s_em']  = $like;
    $params[':s_dep'] = $like;
    $params[':s_st']  = $like;
}

if ($statusValue !== '') {
    // Normalize to lower for exact match
    $where .= " AND lower(status) = :status_exact";
    $params[':status_exact'] = strtolower($statusValue);
}

$sql = "
    SELECT id, user_id, first_name, last_name, age, email, department, status
    FROM public.users
    WHERE $where
    ORDER BY id DESC
";

$stmtAdmins = $pdo->prepare($sql);
$stmtAdmins->execute($params);
$admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/superhome.css">
</head>

<body>
    <!-- Header Section -->
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
                                                <span class="badge badge-<?= $notif['status'] == 'pending' ? 'warning' : ($notif['status'] == 'approved' ? 'success' : 'danger') ?>">
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
                <div class="nav-icon <?php echo (isset($current_page) && $current_page === 'treeprofile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="superprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="main-content">
        <div style="display:flex; align-items:center; justify-content: space-between; width: 100%; margin-bottom: 5px;">
            <h1 style="margin:0 0 0 24px; display:flex; align-items:center;">
                <i class="fas fa-users-cog" style="margin-right:10px;"></i>ADMIN MANAGEMENT
            </h1>
            <form id="search-form" style="display:flex; align-items:center; width: 50%; gap:10px;" autocomplete="off" onsubmit="return false;">
                <input type="text" id="search-input" name="search" placeholder="Search by ID, name, email, etc." style="padding:13px 12px; width: 100%; border-radius:5px; border:1px solid #ccc; min-width:180px;">
                <select id="status-filter" name="status" style="padding:13px 12px; border-radius:5px; border:1px solid #ccc;">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </form>
        </div>

        <div class="admin-table">
            <script>
                // Keep search and filter values after reload
                document.addEventListener('DOMContentLoaded', function() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const searchInput = document.getElementById('search-input');
                    const statusFilter = document.getElementById('status-filter');
                    if (searchInput && urlParams.has('search')) {
                        searchInput.value = urlParams.get('search');
                    }
                    if (statusFilter && urlParams.has('status')) {
                        statusFilter.value = urlParams.get('status');
                    }
                });
            </script>

            <table class="table-titles">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Age</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>

            <table class="table-record">
                <tbody id="admin-table-body">
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$row['id']) ?></td>
                            <td><?= htmlspecialchars((string)$row['first_name']) ?></td>
                            <td><?= htmlspecialchars((string)$row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['age'] !== null ? (string)$row['age'] : '') ?></td>
                            <td><?= htmlspecialchars((string)$row['email']) ?></td>
                            <td><?= htmlspecialchars((string)$row['department']) ?></td>
                            <td>
                                <?php $st = strtolower((string)$row['status']); ?>
                                <span class="status status-<?= $st ?>">
                                    <?= $st === 'pending' ? 'Pending' : ($st === 'verified' ? 'Verified' : ($st === 'rejected' ? 'Rejected' : htmlspecialchars((string)$row['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($st === 'pending'): ?>
                                    <button class="verify-btn" data-id="<?= (int)$row['id'] ?>" style="background:#28a745;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;margin-right:6px;"> Verify</button>
                                    <button class="reject-btn" data-id="<?= (int)$row['id'] ?>" style="background:#d9534f;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;">Reject</button>
                                <?php elseif ($st === 'rejected'): ?>
                                    <button class="delete-btn" style="background:#dc3545;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;"> Delete</button>
                                <?php else: ?>
                                    <button class="edit-btn" style="background:#0d6efd;color:#fff;border:none;padding:7px 20px;border-radius:5px;cursor:pointer;margin-right:6px;"> Edit</button>
                                    <button class="delete-btn" style="background:#dc3545;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;"> Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Status Confirmation Modal -->
                    <div id="status-confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10001; align-items:center; justify-content:center;">
                        <div style="background:#fff; padding:32px 24px; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,0.18); min-width:320px; max-width:90vw; text-align:center;">
                            <div id="status-confirm-message" style="font-size:1.2rem; margin-bottom:18px; color:#222;"></div>
                            <div style="display:flex; gap:16px; justify-content:center;">
                                <button id="confirm-status-btn" style="background:#007bff; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Confirm</button>
                                <button id="cancel-status-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
                            </div>
                        </div>
                    </div>
                </tbody>
            </table>
        </div>
    </div>

    <div class="action-buttons">
        <button class="add-btn"><i class="fas fa-plus"></i> ADD</button>
    </div>

    <!-- Add Modal -->
    <div id="add-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10000; align-items:center; justify-content:center;">
        <form id="add-form" style="background-color: #fff; padding: 20px;">
            <h2 style="margin-top:0; text-align:center;">Add Admin</h2>
            <div class="edit-form-maindiv">
                <div>
                    <div style="margin-bottom:12px;">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="add-first-name" required style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="add-last-name" required style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Age</label>
                        <input type="number" name="age" id="add-age" min="0" style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Email</label>
                        <input type="email" name="email" id="add-email" required style="width:100%;padding:8px;">
                    </div>
                </div>
                <div>
                    <div style="margin-bottom:13px;">
                        <label>Department</label>
                        <select name="department" id="add-department" required style="width:100%;padding:8px;">
                            <option value="Wildlife">Wildlife</option>
                            <option value="Seedling">Seedling</option>
                            <option value="Tree Cutting">Tree Cutting</option>
                            <option value="Marine">Marine</option>
                        </select>
                    </div>
                    <div style="margin-bottom:13px;">
                        <label>Password</label>
                        <input type="password" name="password" id="add-password" required style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:13px;">
                        <label>Phone</label>
                        <input type="text" name="phone" id="add-phone" style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:33px;">
                        <label>Status</label>
                        <select name="status" id="add-status" required style="width:100%;padding:8px;">
                            <option value="Pending">Pending</option>
                            <option value="Verified">Verified</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:16px; justify-content:center;">
                        <button type="button" id="add-admin-btn" style="background:#28a745; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Add</button>
                        <button type="button" id="cancel-add-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Add Confirmation Modal -->
    <div id="add-confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10001; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:32px 24px; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,0.18); min-width:320px; max-width:90vw; text-align:center;">
            <div style="font-size:1.2rem; margin-bottom:18px; color:#222;">Are you sure you want to add this admin?</div>
            <div style="display:flex; gap:16px; justify-content:center;">
                <button id="confirm-add-btn" style="background:#007bff; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Confirm</button>
                <button id="cancel-confirm-add-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Notification Popup -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <!-- Edit Modal -->
    <div id="edit-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10000; align-items:center; justify-content:center;">
        <form id="edit-form">
            <h2 style="margin-top:0; text-align:center;">Edit Admin</h2>
            <div class="edit-form-maindiv">
                <div>
                    <input type="hidden" name="id" id="edit-id">
                    <div style="margin-bottom:12px;">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="edit-first-name" required style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="edit-last-name" required style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Age</label>
                        <input type="number" name="age" id="edit-age" min="0" style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label>Email</label>
                        <input type="email" name="email" id="edit-email" required style="width:100%;padding:8px;">
                    </div>
                </div>
                <div>
                    <div style="margin-bottom:13px;">
                        <label>Department</label>
                        <select name="department" id="edit-department" required style="width:100%;padding:8px;">
                            <option value="Wildlife">Wildlife</option>
                            <option value="Seedling">Seedling</option>
                            <option value="Tree Cutting">Tree Cutting</option>
                            <option value="Marine">Marine</option>
                        </select>
                    </div>
                    <div style="margin-bottom:13px;">
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit-phone" style="width:100%;padding:8px;">
                    </div>
                    <div style="margin-bottom:33px;">
                        <label>Status</label>
                        <select name="status" id="edit-status" required style="width:100%;padding:8px;">
                            <option value="Pending">Pending</option>
                            <option value="Verified">Verified</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:16px; justify-content:center;">
                        <button type="button" id="save-edit-btn" style="background:#007bff; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Save</button>
                        <button type="button" id="cancel-edit-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Save Confirmation Modal -->
    <div id="save-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10001; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:32px 24px; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,0.18); min-width:320px; max-width:90vw; text-align:center;">
            <div style="font-size:1.2rem; margin-bottom:18px; color:#222;">Are you sure you want to save changes?</div>
            <div style="display:flex; gap:16px; justify-content:center;">
                <button id="confirm-save-btn" style="background:#28a745; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Confirm</button>
                <button id="cancel-save-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); z-index:10000; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:32px 24px; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,0.18); min-width:320px; max-width:90vw; text-align:center;">
            <div style="font-size:1.2rem; margin-bottom:18px; color:#222;">Are you sure you want to delete this admin?</div>
            <div style="display:flex; gap:16px; justify-content:center;">
                <button id="confirm-delete-btn" style="background:#d9534f; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Confirm</button>
                <button id="cancel-delete-btn" style="background:#6c757d; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- NOTIFICATION TOAST ---
            const notification = document.getElementById('profile-notification');

            function showNotification(msg) {
                if (!notification) return;
                notification.textContent = msg;
                notification.style.display = 'block';
                notification.style.opacity = '1';
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 400);
                }, 1500);
            }

            // --- VERIFY/REJECT BUTTON FUNCTIONALITY ---
            let statusAction = null;
            let statusId = null;
            const statusConfirmModal = document.getElementById('status-confirm-modal');
            const statusConfirmMessage = document.getElementById('status-confirm-message');
            const confirmStatusBtn = document.getElementById('confirm-status-btn');
            const cancelStatusBtn = document.getElementById('cancel-status-btn');

            function attachStatusListeners() {
                document.querySelectorAll('.verify-btn').forEach(btn => {
                    btn.onclick = function() {
                        statusId = btn.getAttribute('data-id');
                        statusAction = 'Verified';
                        statusConfirmMessage.innerHTML = 'Are you sure you want to verify this admin?';
                        const oldReason = document.getElementById('reject-reason-input');
                        if (oldReason) oldReason.remove();
                        statusConfirmModal.style.display = 'flex';
                    };
                });
                document.querySelectorAll('.reject-btn').forEach(btn => {
                    btn.onclick = function() {
                        statusId = btn.getAttribute('data-id');
                        statusAction = 'Rejected';
                        statusConfirmMessage.innerHTML = `Are you sure you want to reject this admin?<br><span style="font-size:1rem;color:#b00;">If yes, please provide a reason below:</span><br>`;
                        setTimeout(() => {
                            if (!document.getElementById('reject-reason-input')) {
                                const input = document.createElement('input');
                                input.type = 'text';
                                input.id = 'reject-reason-input';
                                input.placeholder = 'Reason for rejection';
                                input.style = 'margin-top:10px;width:90%;padding:8px;border-radius:5px;border:1px solid #ccc;';
                                statusConfirmMessage.appendChild(input);
                            }
                        }, 10);
                        statusConfirmModal.style.display = 'flex';
                    };
                });
            }
            attachStatusListeners();

            cancelStatusBtn.onclick = function() {
                statusConfirmModal.style.display = 'none';
                statusId = null;
                statusAction = null;
            };

            confirmStatusBtn.onclick = function() {
                if (!statusId || !statusAction) return;
                confirmStatusBtn.disabled = true;
                let reason = '';
                if (statusAction === 'Rejected') {
                    const reasonInput = document.getElementById('reject-reason-input');
                    reason = reasonInput ? reasonInput.value.trim() : '';
                    if (!reason) {
                        if (reasonInput) reasonInput.style.border = '1px solid #d9534f';
                        confirmStatusBtn.disabled = false;
                        return;
                    }
                }
                fetch('backend/admin/update_status.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id: statusId,
                            status: statusAction,
                            reason
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        confirmStatusBtn.disabled = false;
                        statusConfirmModal.style.display = 'none';
                        if (data.success) {
                            showNotification(statusAction === 'Rejected' ? 'Admin rejected!' : 'Admin verified!');
                            doLiveSearch();
                        } else {
                            showNotification('Status update failed: ' + (data.error || 'Unknown error'));
                        }
                        statusId = null;
                        statusAction = null;
                    })
                    .catch(() => {
                        confirmStatusBtn.disabled = false;
                        statusConfirmModal.style.display = 'none';
                        showNotification('An error occurred while updating status.');
                        statusId = null;
                        statusAction = null;
                    });
            };

            // --- ADD MODAL FUNCTIONALITY ---
            const addBtn = document.querySelector('.add-btn');
            const addModal = document.getElementById('add-modal');
            const addForm = document.getElementById('add-form');
            const addAdminBtn = document.getElementById('add-admin-btn');
            const cancelAddBtn = document.getElementById('cancel-add-btn');
            const addConfirmModal = document.getElementById('add-confirm-modal');
            const confirmAddBtn = document.getElementById('confirm-add-btn');
            const cancelConfirmAddBtn = document.getElementById('cancel-confirm-add-btn');
            let addFormData = null;

            addBtn.onclick = function() {
                addModal.style.display = 'flex';
            };
            cancelAddBtn.onclick = function() {
                addModal.style.display = 'none';
                addForm.reset();
            };
            addAdminBtn.onclick = function(e) {
                e.preventDefault();
                addFormData = new FormData(addForm);
                addConfirmModal.style.display = 'flex';
            };
            cancelConfirmAddBtn.onclick = function() {
                addConfirmModal.style.display = 'none';
                addFormData = null;
            };
            confirmAddBtn.onclick = function() {
                if (!addFormData) return;
                confirmAddBtn.disabled = true;
                const params = new URLSearchParams();
                for (const [k, v] of addFormData.entries()) params.append(k, v);
                fetch('backend/admin/add_admin.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params
                    })
                    .then(res => res.json())
                    .then(data => {
                        confirmAddBtn.disabled = false;
                        addConfirmModal.style.display = 'none';
                        addModal.style.display = 'none';
                        addForm.reset();
                        if (data.success) {
                            showNotification('Admin added successfully!');
                            doLiveSearch();
                        } else {
                            showNotification('Add failed: ' + (data.error || 'Unknown error'));
                        }
                        addFormData = null;
                    })
                    .catch(() => {
                        confirmAddBtn.disabled = false;
                        addConfirmModal.style.display = 'none';
                        addModal.style.display = 'none';
                        showNotification('An error occurred while adding.');
                        addFormData = null;
                    });
            };

            // --- URL state + Live search ---
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('search-input');
            const statusFilter = document.getElementById('status-filter');
            if (searchInput && urlParams.has('search')) searchInput.value = urlParams.get('search');
            if (statusFilter && urlParams.has('status')) statusFilter.value = urlParams.get('status');

            let searchTimeout;

            function doLiveSearch() {
                const search = searchInput.value;
                const status = statusFilter.value;
                let params = [];
                if (search) params.push('search=' + encodeURIComponent(search));
                if (status) params.push('status=' + encodeURIComponent(status));
                let url = window.location.pathname;
                if (params.length > 0) url += '?' + params.join('&');

                history.replaceState(null, '', url);
                fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTbody = doc.getElementById('admin-table-body');
                        if (newTbody) {
                            document.getElementById('admin-table-body').innerHTML = newTbody.innerHTML;
                        }
                        attachDeleteListeners();
                        attachStatusListeners();
                        attachEditListeners();
                    });
            }
            if (searchInput) searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(doLiveSearch, 350);
            });
            if (statusFilter) statusFilter.addEventListener('change', doLiveSearch);

            // --- DELETE BUTTON FUNCTIONALITY ---
            let deleteId = null;
            const deleteModal = document.getElementById('delete-modal');
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            const cancelDeleteBtn = document.getElementById('cancel-delete-btn');

            function attachDeleteListeners() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.onclick = function() {
                        const row = btn.closest('tr');
                        if (!row) return;
                        const idCell = row.querySelector('td');
                        if (!idCell) return;
                        deleteId = idCell.textContent.trim();
                        deleteModal.style.display = 'flex';
                    };
                });
            }

            function attachEditListeners() {
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.onclick = function() {
                        const row = btn.closest('tr');
                        if (!row) return;
                        const idCell = row.querySelector('td');
                        if (!idCell) return;
                        const editId = idCell.textContent.trim();
                        fetch('backend/admin/get_admin.php', {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    id: editId
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('edit-id').value = editId;
                                    document.getElementById('edit-first-name').value = data.data.first_name || '';
                                    document.getElementById('edit-last-name').value = data.data.last_name || '';
                                    document.getElementById('edit-age').value = data.data.age || '';
                                    document.getElementById('edit-email').value = data.data.email || '';
                                    document.getElementById('edit-department').value = data.data.department || '';
                                    document.getElementById('edit-phone').value = data.data.phone || '';
                                    document.getElementById('edit-status').value = data.data.status || '';
                                    document.getElementById('edit-modal').style.display = 'flex';
                                } else {
                                    showNotification('Failed to fetch user details.');
                                }
                            })
                            .catch(() => showNotification('An error occurred while fetching user details.'));
                    };
                });
            }
            attachDeleteListeners();
            attachEditListeners();

            // --- EDIT MODAL FUNCTIONALITY ---
            const editModal = document.getElementById('edit-modal');
            const editForm = document.getElementById('edit-form');
            const saveEditBtn = document.getElementById('save-edit-btn');
            const cancelEditBtn = document.getElementById('cancel-edit-btn');
            const saveModal = document.getElementById('save-modal');
            const confirmSaveBtn = document.getElementById('confirm-save-btn');
            const cancelSaveBtn = document.getElementById('cancel-save-btn');
            let editFormData = null;

            cancelEditBtn.onclick = function() {
                editModal.style.display = 'none';
                editForm.reset();
            };

            saveEditBtn.onclick = function(e) {
                e.preventDefault();
                editFormData = new FormData(editForm);
                saveModal.style.display = 'flex';
            };

            cancelSaveBtn.onclick = function() {
                saveModal.style.display = 'none';
                editFormData = null;
            };

            confirmSaveBtn.onclick = function() {
                if (!editFormData) return;
                confirmSaveBtn.disabled = true;
                const params = new URLSearchParams();
                for (const [key, value] of editFormData.entries()) params.append(key, value);

                fetch('backend/admin/update_admin.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params
                    })
                    .then(res => res.json())
                    .then(data => {
                        confirmSaveBtn.disabled = false;
                        saveModal.style.display = 'none';
                        editModal.style.display = 'none';
                        editForm.reset();
                        if (data.success) {
                            showNotification('Admin updated successfully!');
                            doLiveSearch();
                        } else {
                            showNotification('Update failed: ' + (data.error || 'Unknown error'));
                        }
                        editFormData = null;
                    })
                    .catch(() => {
                        confirmSaveBtn.disabled = false;
                        saveModal.style.display = 'none';
                        editModal.style.display = 'none';
                        showNotification('An error occurred while updating.');
                        editFormData = null;
                    });
            };

            cancelDeleteBtn.onclick = function() {
                deleteModal.style.display = 'none';
                deleteId = null;
            };

            confirmDeleteBtn.onclick = function() {
                if (!deleteId) return;
                confirmDeleteBtn.disabled = true;
                fetch('backend/admin/delete_admin.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id: deleteId
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        confirmDeleteBtn.disabled = false;
                        deleteModal.style.display = 'none';
                        if (data.success) {
                            // Remove the row from the table
                            const row = Array.from(document.querySelectorAll('#admin-table-body tr')).find(tr => tr.querySelector('td') && tr.querySelector('td').textContent.trim() === deleteId);
                            if (row) row.remove();
                            showNotification('Admin deleted successfully!');
                        } else {
                            showNotification('Delete failed: ' + (data.error || 'Unknown error'));
                        }
                        deleteId = null;
                    })
                    .catch(() => {
                        confirmDeleteBtn.disabled = false;
                        deleteModal.style.display = 'none';
                        showNotification('An error occurred while deleting.');
                        deleteId = null;
                    });
            };
        });
    </script>
</body>

</html>