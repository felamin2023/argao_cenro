<?php
// superprofile.php (TOP)
declare(strict_types=1);

session_start();

// Extra safety headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Gate: must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: superlogin.php');
    exit();
}

// DB connection (PDO -> Supabase/Postgres)
require_once __DIR__ . '/backend/connection.php'; // must expose $pdo (PDO instance)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$current_page = basename(__FILE__); // used by header markup
$user_uuid = (string)$_SESSION['user_id'];

// ---- Verify account & department (Admin + CENRO) ----
try {
    $st = $pdo->prepare("
        SELECT department, role, status
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_uuid]);
    $me = $st->fetch(PDO::FETCH_ASSOC);

    if (!$me) {
        session_unset();
        session_destroy();
        header('Location: superlogin.php');
        exit();
    }

    $roleOk = isset($me['role']) && strtolower((string)$me['role']) === 'admin';
    $deptOk = isset($me['department']) && strtolower((string)$me['department']) === 'cenro';

    if (!$roleOk || !$deptOk) {
        header('Location: superlogin.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('[SUPERPROFILE AUTH] ' . $e->getMessage());
    header('Location: superlogin.php');
    exit();
}

/* =================== NOTIFICATIONS (CENRO scope) =================== */
$notifs = [];
$unreadCount = 0;

/* AJAX: mark_all_read / mark_read (same endpoints used by your dropdown JS) */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        if ($_GET['ajax'] === 'mark_all_read') {
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
        error_log('[SUPERPROFILE NOTIFS AJAX] ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* Load latest notifications for CENRO bell dropdown */
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
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[SUPERPROFILE NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

/* (Optional) helper if you ever print time server-side */
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

/* ---- Load profile fields for display (unchanged) ---- */
try {
    $st = $pdo->prepare("
        SELECT image, first_name, last_name, age, email, role, department
        FROM public.users
        WHERE user_id = :id
        LIMIT 1
    ");
    $st->execute([':id' => $user_uuid]);
    $user = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $imgRaw     = (string)($user['image'] ?? '');
    $first_name = htmlspecialchars((string)($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $last_name  = htmlspecialchars((string)($user['last_name']  ?? ''), ENT_QUOTES, 'UTF-8');
    $age        = htmlspecialchars((string)($user['age']        ?? ''), ENT_QUOTES, 'UTF-8');
    $email      = htmlspecialchars((string)($user['email']      ?? ''), ENT_QUOTES, 'UTF-8');
    $role       = htmlspecialchars((string)($user['role']       ?? ''), ENT_QUOTES, 'UTF-8');
    $department = htmlspecialchars((string)($user['department'] ?? ''), ENT_QUOTES, 'UTF-8');

    $isUrl = preg_match('~^https?://~i', $imgRaw) === 1;
    if ($isUrl) {
        $profile_image = htmlspecialchars($imgRaw, ENT_QUOTES, 'UTF-8');
    } elseif ($imgRaw !== '' && strtolower($imgRaw) !== 'null') {
        $profile_image = 'upload/admin_profiles/' . htmlspecialchars($imgRaw, ENT_QUOTES, 'UTF-8');
    } else {
        $profile_image = 'default-profile.jpg';
    }
} catch (Throwable $e) {
    error_log('[SUPERPROFILE LOAD] ' . $e->getMessage());
    $profile_image = 'default-profile.jpg';
    $first_name = $last_name = $age = $email = $role = $department = '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/superprofile.css">

</head>
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
</style>

<body>
    <header>
        <div class="logo">
            <a href="superhome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>

        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-container">
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
                                $unread  = empty($n['is_read']);
                                $ts      = !empty($n['created_at']) ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                $fromVal = (string)($n['from'] ?? '');
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
                                            <div class="as-notif-time" data-ts="<?= htmlspecialchars((string)$ts, ENT_QUOTES) ?>">just now</div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>


                    </div>

                    <div class="as-notif-footer">
                        <a href="user_notification.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu ">
                    <a href="superprofile.php" class="dropdown-item active-page">
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

    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Admin Profile</h1>
            <p class="profile-subtitle">View and manage your account information</p>
        </div>

        <div class="profile-body-main">
            <form id="profile-form" class="profile-body" enctype="multipart/form-data">
                <div class="profile-picture-container">
                    <img src="<?php echo $profile_image; ?>" alt="Profile Picture" class="profile-picture" id="profile-picture" onerror="this.onerror=null;this.src='default-profile.jpg';">
                    <div class="profile-picture-placeholder" id="profile-placeholder" style="display:none;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-upload-icon" onclick="document.getElementById('profile-upload-input').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <input type="file" id="profile-upload-input" name="profile_image" accept="image/*" style="display:none;">

                <div class="profile-info-grid ">
                    <div class="profile-info-item">
                        <div class="profile-info-label">First Name</div>
                        <input type="text" class="profile-info-value" id="first-name" name="first_name" value="<?php echo $first_name; ?>" required>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Name</div>
                        <input type="text" class="profile-info-value" id="last-name" name="last_name" value="<?php echo $last_name; ?>" required>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Age</div>
                        <input type="number" class="profile-info-value" id="age" name="age" value="<?php echo $age; ?>" min="0">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email" value="<?php echo $email; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Role</div>
                        <input type="text" class="profile-info-value" id="role" name="role" value="<?php echo $role; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Department</div>
                        <input type="text" class="profile-info-value" id="department" name="department" value="<?php echo $department; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">New Password</div>
                        <input type="password" class="profile-info-value" id="password" name="password" placeholder="Enter new password">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Confirm Password</div>
                        <input type="password" class="profile-info-value" id="confirm-password" name="confirm_password" placeholder="Confirm new password">
                        <div id="password-error" style="color: red; font-size: 12px; display: none;">Passwords do not match</div>
                    </div>
                </div>

                <div class="profile-actions">
                    <button type="submit" class="btn btn-primary" id="update-profile-btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm-password');
            const passwordError = document.getElementById('password-error');

            function validatePasswords() {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.style.borderColor = 'red';
                    passwordError.style.display = 'block';
                    return false;
                } else {
                    confirmPasswordInput.style.borderColor = '';
                    passwordError.style.display = 'none';
                    return true;
                }
            }
            confirmPasswordInput.addEventListener('input', validatePasswords);
            passwordInput.addEventListener('input', validatePasswords);

            document.getElementById('profile-form').addEventListener('submit', function(e) {
                if ((passwordInput.value || confirmPasswordInput.value) && !validatePasswords()) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                const formData = new FormData(this);

                // IMPORTANT: point to the real handler path shown in your repo
                fetch('backend/admins/profile/update_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            const btn = document.getElementById('update-profile-btn');
                            btn.innerHTML = '<i class="fas fa-check"></i> Profile Updated!';
                            btn.style.backgroundColor = '#28a745';
                            setTimeout(() => {
                                btn.innerHTML = '<i class="fas fa-save"></i> Update Profile';
                                btn.style.backgroundColor = '';
                                const notif = document.getElementById('profile-notification');
                                notif.textContent = 'Profile updated successfully!';
                                notif.style.display = 'block';
                                notif.style.opacity = '1';
                                setTimeout(() => {
                                    notif.style.opacity = '0';
                                    setTimeout(() => {
                                        notif.style.display = 'none';
                                        location.reload();
                                    }, 400);
                                }, 1500);
                            }, 1500);
                        } else {
                            alert('Update failed: ' + ((data && data.error) ? data.error : 'Unknown error'));
                        }
                    })
                    .catch(() => alert('An error occurred while updating profile.'));
            });

            const mobileToggle = document.querySelector('.mobile-toggle');
            const navContainer = document.querySelector('.nav-container');
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navContainer.classList.toggle('active');
                });
            }

            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                dropdown.addEventListener('mouseenter', () => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') ?
                        'translateX(-50%) translateY(0)' :
                        'translateY(0)';
                });
                dropdown.addEventListener('mouseleave', (e) => {
                    if (!dropdown.contains(e.relatedTarget)) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = menu.classList.contains('center') ?
                            'translateX(-50%) translateY(10px)' :
                            'translateY(10px)';
                    }
                });
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

            if (window.innerWidth <= 992) {
                dropdowns.forEach(dropdown => {
                    const toggle = dropdown.querySelector('.nav-icon');
                    const menu = dropdown.querySelector('.dropdown-menu');
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                            if (otherMenu !== menu) otherMenu.style.display = 'none';
                        });
                        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                    });
                });
            }

            const markAllRead = document.querySelector('.mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.querySelector('.badge');
                    if (badge) badge.style.display = 'none';
                });
            }

            document.getElementById('profile-upload-input').addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const profilePic = document.getElementById('profile-picture');
                        const placeholder = document.getElementById('profile-placeholder');
                        profilePic.src = event.target.result;
                        profilePic.style.display = 'block';
                        placeholder.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            });

            const infoItems = document.querySelectorAll('.profile-info-item');
            infoItems.forEach(item => {
                const value = item.querySelector('.profile-info-value');
                item.addEventListener('mouseenter', () => {
                    value.style.transform = 'translateX(5px)';
                });
                item.addEventListener('mouseleave', () => {
                    value.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Mark all (uses the PHP ?ajax=mark_all_read handler above)
            const markAll = document.getElementById('asMarkAllRead');
            if (markAll) {
                markAll.addEventListener('click', (e) => {
                    e.preventDefault();
                    fetch('?ajax=mark_all_read', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .finally(() => location.reload());
                });
            }

            // Click a notif → mark read + route
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

                // mark read (server checks "to" = cenro)
                fetch('?ajax=mark_read&notif_id=' + encodeURIComponent(notifId), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(() => {});

                // route
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
        });
    </script>

</body>

</html>