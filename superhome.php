<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}
include_once __DIR__ . '/backend/connection.php';
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($department);
if ($stmt->fetch()) {
    if (strtolower($department) !== 'cenro') {
        $stmt->close();
        $conn->close();
        header('Location: superlogin.php');
        exit();
    }
} else {
    $stmt->close();
    $conn->close();
    header('Location: superlogin.php');
    exit();
}
$stmt->close();

$notif_query = "
    SELECT pur.id, pur.created_at, pur.is_read, pur.department, u.first_name, u.last_name
    FROM profile_update_requests pur
    JOIN users u ON pur.user_id = u.id
    WHERE pur.status = 'pending'
    ORDER BY pur.is_read ASC, pur.created_at DESC
    LIMIT 2
";

$notif_result = $conn->query($notif_query);
$notifications = [];
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Helper for "15 minutes ago"
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second'
    ];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
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

                    <?php if (count($notifications) === 0): ?>
                        <div class="notification-item">
                            <div class="notification-content">
                                <div class="notification-title">No new notifications</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>">
                                <a href="supereach.php?id=<?= $notif['id'] ?>" class="notification-link">
                                    <div class="notification-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">Admin Profile Update</div>
                                        <div class="notification-message">
                                            <?= htmlspecialchars($notif['department']) ?> Administrator requested to update their profile.
                                        </div>
                                        <div class="notification-time">
                                            <?= time_elapsed_string($notif['created_at']) ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (count($notifications) > 1): ?>
                        <div class="notification-footer">
                            <a href="supernotif.php" class="view-all">View All Notifications</a>
                        </div>
                    <?php endif; ?>
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
        <div style="display:flex; align-items:center; justify-content: space-between; width: 100%; margin-bottom: 5px; ">

            <h1 style="margin:0 0 0 24px; display:flex; align-items:center;"><i class="fas fa-users-cog" style="margin-right:10px;"></i>ADMIN MANAGEMENT</h1>
            <form id="search-form" style="display:flex; align-items:center;  width: 50%; gap:10px;" autocomplete="off" onsubmit="return false;">
                <input type="text" id="search-input" name="search" placeholder="Search by ID or Email" style="padding:13px 12px; width: 100%; border-radius:5px; border:1px solid #ccc; min-width:180px;">
                <select id="status-filter" name="status" style="padding:13px 12px; border-radius:5px; border:1px solid #ccc;">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </form>
        </div>

        <div class="admin-table">
            <?php
            include_once __DIR__ . '/backend/connection.php';
            // Build query for admins only
            $where = "role = 'Admin' AND department != 'Cenro'";
            $params = [];
            $searchValue = '';
            if (isset($_GET['search']) && trim($_GET['search']) !== '') {
                $searchValue = trim($_GET['search']);
                $search = '%' . $searchValue . '%';
                $where .= " AND (CAST(id AS CHAR) LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR age LIKE ? OR email LIKE ? OR department LIKE ? OR status LIKE ?)";
                for ($i = 0; $i < 7; $i++) $params[] = $search;
            }
            if (isset($_GET['status']) && $_GET['status'] !== '') {
                $where .= " AND status = ?";
                $params[] = $_GET['status'];
            }
            $sql = "SELECT id, first_name, last_name, age, email, department, status FROM users WHERE $where ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            if (count($params) > 0) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            ?>
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
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['age']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><span class="status status-<?php echo strtolower($row['status']); ?>">
                                    <?php
                                    $status = strtolower($row['status']);
                                    if ($status === 'pending') {
                                        echo 'Pending';
                                    } elseif ($status === 'verified') {
                                        echo 'Verified';
                                    } elseif ($status === 'rejected') {
                                        echo 'Rejected';
                                    } else {
                                        echo htmlspecialchars($row['status']);
                                    }
                                    ?>
                                </span></td>
                            <td>
                                <?php if (strtolower($row['status']) === 'pending'): ?>
                                    <button class="verify-btn" data-id="<?php echo $row['id']; ?>" style="background:#28a745;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;margin-right:6px;"><i class="fas fa-check"></i> Verify</button>
                                    <button class="reject-btn" data-id="<?php echo $row['id']; ?>" style="background:#d9534f;color:#fff;border:none;padding:7px 16px;border-radius:5px;cursor:pointer;"><i class="fas fa-times"></i> Reject</button>
                                <?php elseif (strtolower($row['status']) === 'rejected'): ?>
                                    <button class="delete-btn"><i class="fas fa-trash-alt"></i> Delete</button>
                                <?php else: ?>
                                    <button class="edit-btn"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="delete-btn"><i class="fas fa-trash-alt"></i> Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
            <?php $stmt->close();
            $conn->close(); ?>
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
            // Mobile menu toggle and dropdowns (existing code)
            // ...existing code...

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
                        // Remove reason input if present
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
                        // Add reason input
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
                        reasonInput.style.border = '1px solid #d9534f';
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
                            reason: reason
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        confirmStatusBtn.disabled = false;
                        statusConfirmModal.style.display = 'none';
                        if (data.success) {
                            showNotification(
                                statusAction === 'Rejected' ?
                                'Admin rejected and reason logged!' :
                                'Admin status updated to ' + statusAction + '!'
                            );
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
                // Convert FormData to URLSearchParams
                const params = new URLSearchParams();
                for (const [key, value] of addFormData.entries()) {
                    params.append(key, value);
                }
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

            // Keep search and filter values after reload
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById('search-input');
            const statusFilter = document.getElementById('status-filter');
            if (searchInput && urlParams.has('search')) {
                searchInput.value = urlParams.get('search');
            }
            if (statusFilter && urlParams.has('status')) {
                statusFilter.value = urlParams.get('status');
            }

            // Live search on input
            let searchTimeout;

            function doLiveSearch() {
                const search = searchInput.value;
                const status = statusFilter.value;
                let params = [];
                if (search) params.push('search=' + encodeURIComponent(search));
                if (status) params.push('status=' + encodeURIComponent(status));
                let url = window.location.pathname;
                if (params.length > 0) url += '?' + params.join('&');
                // Use history.replaceState to update URL and reload table via AJAX, keeping focus
                history.replaceState(null, '', url);
                fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.text())
                    .then(html => {
                        // Extract only the table body from the response
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
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(doLiveSearch, 350);
                });
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', doLiveSearch);
            }

            // --- DELETE BUTTON FUNCTIONALITY ---
            let deleteId = null;
            const deleteModal = document.getElementById('delete-modal');
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
            const notification = document.getElementById('profile-notification');

            function showNotification(msg) {
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

            function attachDeleteListeners() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.onclick = function() {
                        // Get the admin id from the row
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
                        // Fetch user details
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
                                    editModal.style.display = 'flex';
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
                // Gather form data
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
                // Convert FormData to URLSearchParams
                const params = new URLSearchParams();
                for (const [key, value] of editFormData.entries()) {
                    params.append(key, value);
                }
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
                            // Optionally update the row in the table without reload
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
                            'X-Requested-With': 'XMLHttpRequest'
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