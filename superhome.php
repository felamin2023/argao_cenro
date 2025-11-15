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

$notifs = [];
$unreadCount = 0;


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
            // 🔁 Mark ALL 'Cenro' notifications as read
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where lower("to") = :to and (is_read is null or is_read = false)
            ');
            $u->execute([':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_read') {
            $nid = $_GET['notif_id'] ?? '';
            if (!$nid) {
                echo json_encode(['success' => false, 'error' => 'Missing notif_id']);
                exit;
            }
            // ✅ Only mark read if it's addressed to CENRO
            $u = $pdo->prepare('
                update public.notifications
                set is_read = true
                where notif_id = :nid and lower("to") = :to
            ');
            $u->execute([':nid' => $nid, ':to' => 'cenro']);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    } catch (Throwable $e) {
        error_log('[NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


/* Load the latest notifications for CENRO */
try {
    $ns = $pdo->prepare('
        select
            notif_id,
            "from",
            "to",
            message,
            is_read,
            created_at,
            reqpro_id
        from public.notifications
        where lower("to") = :to
        order by created_at desc
        limit 30
    ');
    $ns->execute([':to' => 'cenro']);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);

    $unreadCount = 0;
    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}


// =================== Helper ===================
// Treat naive DB timestamps as UTC, then show elapsed time in Asia/Manila.
function _to_manila_dt(string $src): DateTimeImmutable
{
    $src = trim($src);
    $hasTz = (bool)preg_match('/[zZ]|[+\-]\d{2}:\d{2}$/', $src); // already has TZ?
    $base  = new DateTimeImmutable($src, $hasTz ? null : new DateTimeZone('UTC'));
    return $base->setTimezone(new DateTimeZone('Asia/Manila'));
}

function time_elapsed_string(string $datetime, bool $full = false): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $ago = _to_manila_dt($datetime);

    $diff = $now->diff($ago);

    // Use total days for weeks calculation
    $daysTotal = is_int($diff->days) ? $diff->days : (int)$diff->d;
    $weeks     = intdiv($daysTotal, 7);
    $daysLeft  = $daysTotal - $weeks * 7;

    $units = [
        'y' => ['v' => $diff->y,        'label' => 'year'],
        'm' => ['v' => $diff->m,        'label' => 'month'],
        'w' => ['v' => $weeks,          'label' => 'week'],
        'd' => ['v' => $daysLeft,       'label' => 'day'],
        'h' => ['v' => $diff->h,        'label' => 'hour'],
        'i' => ['v' => $diff->i,        'label' => 'minute'],
        's' => ['v' => $diff->s,        'label' => 'second'],
    ];

    $parts = [];
    foreach ($units as $u) {
        if ($u['v'] > 0) {
            $parts[] = $u['v'] . ' ' . $u['label'] . ($u['v'] > 1 ? 's' : '');
        }
    }
    if (!$full) $parts = array_slice($parts, 0, 1);

    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// Optional: nice absolute Manila time for tooltips
function format_manila_abs(string $datetime): string
{
    return _to_manila_dt($datetime)->format('M j, Y g:i A');
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
    <style>
        :root {
            --as-primary: #2b6625;
            --as-primary-dark: #1e4a1a;
            --as-white: #fff;
            --as-light-gray: #f5f5f5;
            --as-radius: 8px;
            --as-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            --as-trans: all .2s ease;
        }

        /* bell icon container */
        .as-item {
            position: relative;
        }

        .as-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            background: rgb(233, 255, 242);
            color: #000;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            transition: var(--as-trans);
        }

        .as-icon:hover {
            background: rgba(255, 255, 255, .3);
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        }

        .as-icon i {
            font-size: 1.3rem;
        }

        /* dropdown shell */
        .as-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 300px;
            background: #fff;
            border-radius: var(--as-radius);
            box-shadow: var(--as-shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--as-trans);
            padding: 0;
            z-index: 1000;
        }



        .as-item:hover>.as-dropdown-menu,
        .as-dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* notifications panel */
        .as-notifications {
            min-width: 350px;
            max-height: 500px;
        }

        .as-notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .as-notif-header h3 {
            margin: 0;
            color: var(--as-primary);
            font-size: 1.1rem;
        }

        .as-mark-all {
            color: var(--as-primary);
            text-decoration: none;
            font-size: .9rem;
            transition: var(--as-trans);
        }

        .as-mark-all:hover {
            color: var(--as-primary-dark);
            transform: scale(1.05);
        }

        /* 🔽 the scrolling list wrapper (this holds the records) */
        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

        .as-notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            background: #fff;
            transition: var(--as-trans);
        }

        .as-notif-item.unread {
            background: rgba(43, 102, 37, .05);
        }

        .as-notif-item:hover {
            background: #f9f9f9;
        }

        .as-notif-link {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .as-notif-icon {
            color: var(--as-primary);
            font-size: 1.2rem;
        }

        .as-notif-title {
            font-weight: 600;
            color: var(--as-primary);
            margin-bottom: 4px;
        }

        .as-notif-message {
            color: #2b6625;
            font-size: .92rem;
            line-height: 1.35;
        }

        .as-notif-time {
            color: #999;
            font-size: .8rem;
            margin-top: 4px;
        }

        .as-notif-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fff;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .as-view-all {
            color: var(--as-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .as-view-all:hover {
            text-decoration: underline;
        }

        .as-badge {
            position: absolute;
            top: 2px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ff4757;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .edit-form-maindiv div div input,
        .edit-form-maindiv div div select {
            border-radius: 5px;
            border: 1px solid #ccc;
        }
    </style>
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
            <!-- <div class="nav-item">
                <div class="nav-icon">
                    <a href="supermessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div> -->

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge"><?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <div class="notifcontainer">
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread   = empty($n['is_read']);
                                $created  = isset($n['created_at']) ? (string)$n['created_at'] : '';
                                $fromVal  = (string)($n['from'] ?? '');
                                $reqproId = $n['reqpro_id'] ?? '';

                                // ✅ Title rules:
                                // - if from === "Register request"  -> "Registration"
                                // - if from looks like a UUID       -> "Profile update"
                                // - else                            -> "Notification"
                                $title = (function ($fv) {
                                    $fv = trim((string)$fv);
                                    if (preg_match('/^register request$/i', $fv)) return 'Registration';
                                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $fv)) return 'Profile update';
                                    return 'Notification';
                                })($fromVal);

                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\s*\(?\b(rejection\s*reason|reason)\b\s*[:\-–]\s*.*$/i', '', $t);
                                    $t = preg_replace('/\s*\b(because|due\s+to)\b\s*.*/i', '', $t);
                                    return trim(preg_replace('/\s{2,}/', ' ', $t)) ?: 'There’s an update.';
                                })($n['message'] ?? '');

                                // 🕒 Relative/absolute time (Asia/Manila), based on notifications.created_at
                                // Requires the helper functions: _to_manila_dt(), time_elapsed_string(), format_manila_abs()
                                $relTime  = $created !== '' ? time_elapsed_string($created) : 'just now';
                                $absTitle = $created !== '' ? format_manila_abs($created)
                                    : format_manila_abs((new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
                                $ts = $created !== ''
                                    ? _to_manila_dt($created)->getTimestamp()
                                    : (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->getTimestamp();
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        data-from="<?= htmlspecialchars($fromVal, ENT_QUOTES) ?>"
                                        data-reqpro-id="<?= htmlspecialchars((string)$reqproId, ENT_QUOTES) ?>">
                                        <div class="as-notif-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div class="as-notif-content">
                                            <div class="as-notif-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
                                            <div class="as-notif-message"><?= htmlspecialchars($cleanMsg, ENT_QUOTES) ?></div>
                                            <div class="as-notif-time"
                                                data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>"
                                                title="<?= htmlspecialchars($absTitle, ENT_QUOTES) ?>">
                                                <?= htmlspecialchars($relTime, ENT_QUOTES) ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>


                    <div class="as-notif-footer">
                        <a href="supernotif.php" class="as-view-all">View All Notifications</a>
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
            <form id="search-form" style="display:flex;  align-items:center; width: 50%; gap:10px;" autocomplete="off" onsubmit="return false;">
                <input type="text" id="search-input" name="search" placeholder="Search by ID, name, email, etc." style="padding:13px 12px; width: 100%; border-radius:5px; border:1px solid #ccc; min-width:180px;">
                <select id="status-filter" name="status" style="padding:13px 12px; border-radius:5px; border:1px solid #ccc;">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
                <div class="action-buttons">
                    <button class="add-btn"><i class="fas fa-plus"></i> ADD</button>
                </div>
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
                                    <div style="display:flex; gap:6px;">
                                        <button class="verify-btn" data-id="<?= (int)$row['id'] ?>" style="background:#28a745;color:#fff;border:none;padding:7px 10px;border-radius:5px;cursor:pointer;margin-right:6px;"> Verify</button>
                                        <button class="reject-btn" data-id="<?= (int)$row['id'] ?>" style="background:#d9534f;color:#fff;border:none;padding:7px 9px;border-radius:5px;cursor:pointer;">Reject</button>
                                    </div>
                                <?php elseif ($st === 'rejected'): ?>
                                    <button class="delete-btn" style="background:#dc3545;color:#fff;border:none;padding:7px 9px;border-radius:5px;cursor:pointer;"> Delete</button>
                                <?php else: ?>
                                    <div style="display:flex; gap:6px;">
                                        <button class="edit-btn" style="background:#0d6efd;color:#fff;border:none;padding:7px 15px;border-radius:5px;cursor:pointer;margin-right:6px;"> Edit</button>
                                        <button class="delete-btn" style="background:#dc3545;color:#fff;border:none;padding:7px 9px;border-radius:5px;cursor:pointer;"> Delete</button>
                                    </div>
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

            // --- ✅ NOTIFICATIONS: CLICK → ROUTE + MARK READ (CENRO scope) ---
            (function() {
                const list = document.querySelector('.notifcontainer');
                if (!list) return;

                const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

                list.addEventListener('click', (e) => {
                    const link = e.target.closest('.as-notif-link');
                    if (!link) return;

                    e.preventDefault();

                    const notifId = link.dataset.notifId || '';
                    const fromVal = (link.dataset.from || '').trim();
                    const reqproId = (link.dataset.reqproId || '').trim();

                    // mark as read (fire-and-forget)
                    fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(notifId), {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(() => {});

                    // decide target
                    let url = 'superhome.php';
                    if (/^register request$/i.test(fromVal)) {
                        url = 'superhome.php';
                    } else if (uuidRe.test(fromVal)) {
                        url = (reqproId && /^\d+$/.test(reqproId)) ?
                            'supereach.php?id=' + encodeURIComponent(reqproId) :
                            'supereach.php?user=' + encodeURIComponent(fromVal);
                    }
                    window.location.href = url;
                });

                // "Mark all as read" (CENRO-scoped on server)
                const markAll = document.getElementById('asMarkAllRead');
                if (markAll) {
                    markAll.addEventListener('click', (e) => {
                        e.preventDefault();
                        fetch('?ajax=mark_all_read', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).finally(() => location.reload());
                    });
                }
            })();

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