<?php

/**
 * user_profile.php — Supabase REST (no SDK)
 * - Detects whether session contains numeric users.id or UUID users.user_id
 * - Queries the right column automatically and avoids false redirects
 */

declare(strict_types=1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit();
}

/* Include your connection.php so its constants/env vars are available */
if (is_file(__DIR__ . '/../backend/connection.php')) {
    include_once __DIR__ . '/../backend/connection.php';
}

/* ---------- Supabase credentials (accept multiple names) ---------- */
$SUPABASE_URL =
    getenv('SUPABASE_URL')
    ?: (defined('SUPABASE_URL') ? SUPABASE_URL : '');

$SUPABASE_KEY =
    (getenv('SUPABASE_SERVICE_ROLE') ?: '')
    ?: (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '')
    ?: (getenv('SUPABASE_ANON_KEY') ?: '')
    ?: (defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : '');

if (!$SUPABASE_URL || !$SUPABASE_KEY) {
    http_response_code(500);
    die('Supabase credentials are missing. Set SUPABASE_URL and a key env (SERVICE_ROLE or ANON).');
}

/* ---------- Constants ---------- */
$DEFAULT_PROFILE_URL = 'https://odbjapuchpxwzdghjfof.supabase.co/storage/v1/object/public/user_profiles/default/default.png';

/* ---------- Helpers ---------- */
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

function supabase_get(string $url, string $apiKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: {$apiKey}",
            "Authorization: Bearer {$apiKey}",
            "Accept: application/json",
            "Prefer: return=representation",
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP error contacting Supabase: ' . $err);
    }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code >= 400) {
        throw new RuntimeException("Supabase returned HTTP {$code}: {$raw}");
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
    }
    return $data;
}

/* ---------- Figure out how your session identifies the user ---------- */
$sessionVal = $_SESSION['user_id'];

/** Detect UUID v4-ish (loose): 8-4-4-4-12 hex */
$isUuid = is_string($sessionVal) && preg_match(
    '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
    $sessionVal
);

/* Build the correct filter */
$base   = rtrim($SUPABASE_URL, '/');
$select = rawurlencode('image,first_name,last_name,age,email,role,department,phone');

if ($isUuid) {
    // Session holds users.user_id (UUID)
    $filter = 'user_id=eq.' . rawurlencode($sessionVal);
} else {
    // Session holds users.id (numeric bigserial). Coerce safely.
    $filter = 'id=eq.' . rawurlencode((string)(int)$sessionVal);
}

$url = "{$base}/rest/v1/users?select={$select}&{$filter}&limit=1";

/* ---------- Fetch user ---------- */
try {
    $rows = supabase_get($url, $SUPABASE_KEY);
} catch (Throwable $ex) {
    http_response_code(500);
    die('Database error: ' . $e($ex->getMessage()));
}

$user = $rows[0] ?? null;

/* ---------- Role check (normalize) ---------- */
$roleNorm = strtolower(trim((string)($user['role'] ?? '')));
if (!$user || $roleNorm !== 'user') {
    header('Location: user_login.php');
    exit();
}

/* ---------- Image URL detection & fallback ---------- */
$imgVal = trim((string)($user['image'] ?? ''));
$profile_image = $DEFAULT_PROFILE_URL; // default first

if ($imgVal !== '' && strtolower($imgVal) !== 'null') {
    if (preg_match('~^https?://~i', $imgVal)) {
        $profile_image = $e($imgVal);
    } elseif (is_file(__DIR__ . '/../upload/user_profiles/' . $imgVal)) {
        $profile_image = '/denr/superadmin/upload/user_profiles/' . rawurlencode($imgVal);
    }
}

$notifs = [];
$unreadCount = 0;

/* AJAX endpoints used by the header JS:
   - POST ?ajax=mark_all_read
   - POST ?ajax=mark_read&notif_id=...
*/
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
                where "to" = :uid and (is_read is null or is_read = false)
            ');
            $u->execute([':uid' => $_SESSION['user_id']]);
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
                where notif_id = :nid and "to" = :uid
            ');
            $u->execute([':nid' => $nid, ':uid' => $_SESSION['user_id']]);
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

/* Load the latest notifications for the current user */
try {
    $ns = $pdo->prepare('
        select notif_id, approval_id, incident_id, message, is_read, created_at
        from public.notifications
        where "to" = :uid
        order by created_at desc
        limit 30
    ');
    $ns->execute([':uid' => $_SESSION['user_id']]);
    $notifs = $ns->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifs as $n) {
        if (empty($n['is_read'])) $unreadCount++;
    }
} catch (Throwable $e) {
    error_log('[NOTIFS LOAD] ' . $e->getMessage());
    $notifs = [];
    $unreadCount = 0;
}

/* ---------- Safe values for HTML ---------- */
$first_name = $e($user['first_name'] ?? '');
$last_name  = $e($user['last_name'] ?? '');
$age        = $e($user['age'] ?? '');
$email      = $e($user['email'] ?? '');
$role       = $e($user['role'] ?? '');
$department = $e($user['department'] ?? '');
$phone      = $e($user['phone'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/userprofile.css">
    <!-- Lightweight loader styles -->
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

        .as-item {
            position: relative;
        }

        /* Bell button */
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

        /* Base dropdown */
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

        /* Notifications-specific sizing */
        .as-notifications {
            min-width: 350px;
            max-height: 500px;
        }

        /* Sticky header */
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

        /* Scroll body */
        .notifcontainer {
            height: 380px;
            overflow-y: auto;
            padding: 5px;
            background: #fff;
        }

        /* Rows */
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

        /* Sticky footer */
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

        /* Red badge on bell */
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

        #global-loading {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100000;
        }

        #global-loading .box {
            background: #fff;
            padding: 18px 20px;
            border-radius: 10px;
            min-width: 220px;
            max-width: 90vw;
            text-align: center;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .2);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }

        #global-loading .spinner {
            width: 28px;
            height: 28px;
            border: 3px solid #e5e7eb;
            border-top-color: #1a8cff;
            border-radius: 50%;
            margin: 0 auto 10px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .is-busy {
            pointer-events: none;
            user-select: none;
        }

        .is-busy button,
        .is-busy input,
        .is-busy select {
            opacity: .7;
        }

        /* Input field styles */
        .profile-info-value {
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }

        .profile-info-value:focus {
            border-color: #1a8cff;
            outline: none;
        }

        .profile-info-value.error {
            border-color: #ff4757;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">
            <a href="user_home.php">
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
                    <a href="user_reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Report Incident</span>
                    </a>
                    <a href="useraddseed.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span>Request Seedlings</span>
                    </a>
                    <a href="useraddwild.php" class="dropdown-item">
                        <i class="fas fa-paw"></i>
                        <span>Wildlife Permit</span>
                    </a>
                    <a href="useraddtreecut.php" class="dropdown-item">
                        <i class="fas fa-tree"></i>
                        <span>Tree Cutting Permit</span>
                    </a>
                    <a href="useraddlumber.php" class="dropdown-item">
                        <i class="fas fa-boxes"></i>
                        <span>Lumber Dealers Permit</span>
                    </a>
                    <a href="useraddwood.php" class="dropdown-item">
                        <i class="fas fa-industry"></i>
                        <span>Wood Processing Permit</span>
                    </a>
                    <a href="useraddchainsaw.php" class="dropdown-item">
                        <i class="fas fa-tools"></i>
                        <span>Chainsaw Permit</span>
                    </a>
                    <a href="applicationstatus.php" class="dropdown-item"><i class="fas fa-clipboard-check"></i><span>Application Status</span></a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="as-item">
                <div class="as-icon">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)) : ?>
                        <span class="as-badge" id="asNotifBadge">
                            <?= htmlspecialchars((string)$unreadCount, ENT_QUOTES) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="as-dropdown-menu as-notifications">
                    <!-- sticky header -->
                    <div class="as-notif-header">
                        <h3>Notifications</h3>
                        <a href="#" class="as-mark-all" id="asMarkAllRead">Mark all as read</a>
                    </div>

                    <!-- scrollable body -->
                    <div class="notifcontainer"><!-- this holds the records -->
                        <?php if (!$notifs): ?>
                            <div class="as-notif-item">
                                <div class="as-notif-content">
                                    <div class="as-notif-title">No record found</div>
                                    <div class="as-notif-message">There are no notifications.</div>
                                </div>
                            </div>
                            <?php else: foreach ($notifs as $n):
                                $unread = empty($n['is_read']);
                                $ts     = $n['created_at'] ? (new DateTime((string)$n['created_at']))->getTimestamp() : time();
                                $title  = $n['approval_id'] ? 'Permit Update' : ($n['incident_id'] ? 'Incident Update' : 'Notification');
                                $cleanMsg = (function ($m) {
                                    $t = trim((string)$m);
                                    $t = preg_replace('/\\s*\\(?\\b(rejection\\s*reason|reason)\\b\\s*[:\\-–]\\s*.*$/i', '', $t);
                                    $t = preg_replace('/\\s*\\b(because|due\\s+to)\\b\\s*.*/i', '', $t);
                                    return trim(preg_replace('/\\s{2,}/', ' ', $t)) ?: 'There\'s an update.';
                                })($n['message'] ?? '');
                            ?>
                                <div class="as-notif-item <?= $unread ? 'unread' : '' ?>">
                                    <a href="#" class="as-notif-link"
                                        data-notif-id="<?= htmlspecialchars((string)$n['notif_id'], ENT_QUOTES) ?>"
                                        <?= !empty($n['approval_id']) ? 'data-approval-id="' . htmlspecialchars((string)$n['approval_id'], ENT_QUOTES) . '"' : '' ?>
                                        <?= !empty($n['incident_id']) ? 'data-incident-id="' . htmlspecialchars((string)$n['incident_id'], ENT_QUOTES) . '"' : '' ?>>
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

                    <!-- sticky footer -->
                    <div class="as-notif-footer">
                        <a href="user_notification.php" class="as-view-all">View All Notifications</a>
                    </div>
                </div>
            </div>


            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="user_profile.php" class="dropdown-item active-page">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="user_login.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Notification Popup -->
    <div id="profile-notification" style="display:none; position:fixed; top:5px; left:50%; transform:translateX(-50%); background:#323232; color:#fff; padding:16px 32px; border-radius:8px; font-size:1.1rem; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,0.15); text-align:center; min-width:220px; max-width:90vw;"></div>

    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">User Profile</h1>
            <p class="profile-subtitle">Manage your account information</p>
        </div>
        <div class="profile-body-main">
            <form id="profile-form" class="profile-body" enctype="multipart/form-data">
                <div class="profile-picture-container">
                    <img
                        src="<?php echo $profile_image; ?>"
                        alt="Profile Picture"
                        class="profile-picture"
                        id="profile-picture"
                        onerror="this.onerror=null;this.src='<?php echo $e($DEFAULT_PROFILE_URL); ?>';">
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
                        <input type="number" class="profile-info-value" id="age" name="age" value="<?php echo $age; ?>" min="1" max="150" required>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email" value="<?php echo $email; ?>" required data-original-email="<?php echo $email; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Role</div>
                        <input type="text" class="profile-info-value" id="role" name="role" value="<?php echo $role; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Phone</div>
                        <input type="text" class="profile-info-value" id="phone" name="phone" value="<?php echo $phone; ?>" required>
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

    <!-- Profile Update Confirmation Modal -->
    <div id="profile-confirm-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.35); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:90vw; box-shadow:0 2px 16px rgba(0,0,0,0.18); text-align:center;">
            <h2 style="margin-bottom:18px; font-size:1.3rem; color:#1a3d5d;">Confirm Profile Update</h2>
            <p style="margin-bottom:24px; color:#444;">Are you sure you want to update your profile?</p>
            <button id="confirm-profile-update-btn" style="background:#1a8cff; color:#fff; border:none; padding:10px 28px; border-radius:5px; font-size:1rem; margin-right:10px; cursor:pointer;">Confirm</button>
            <button id="cancel-profile-update-btn" style="background:#eee; color:#333; border:none; padding:10px 22px; border-radius:5px; font-size:1rem; cursor:pointer;">Cancel</button>
        </div>
    </div>

    <!-- Global loading overlay -->
    <div id="global-loading" role="alert" aria-live="polite" aria-busy="true">
        <div class="box">
            <div class="spinner" aria-hidden="true"></div>
            <div id="global-loading-text">Please wait…</div>
        </div>
    </div>

    <script>
        // Fixed JavaScript validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profile-form');
            const updateBtn = document.getElementById('update-profile-btn');
            const confirmModal = document.getElementById('profile-confirm-modal');
            const confirmBtn = document.getElementById('confirm-profile-update-btn');
            const cancelBtn = document.getElementById('cancel-profile-update-btn');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm-password');
            const passwordError = document.getElementById('password-error');
            const profileUploadInput = document.getElementById('profile-upload-input');
            const profilePicture = document.getElementById('profile-picture');
            const globalLoading = document.getElementById('global-loading');
            const loadingText = document.getElementById('global-loading-text');

            // Password matching validation
            function validatePasswords() {
                const password = passwordInput.value.trim();
                const confirmPassword = confirmPasswordInput.value.trim();

                if (password !== '' && confirmPassword !== '' && password !== confirmPassword) {
                    passwordError.style.display = 'block';
                    passwordInput.style.borderColor = '#ff4757';
                    confirmPasswordInput.style.borderColor = '#ff4757';
                    return false;
                } else {
                    passwordError.style.display = 'none';
                    passwordInput.style.borderColor = '';
                    confirmPasswordInput.style.borderColor = '';
                    return true;
                }
            }

            // Validate all required fields
            function validateForm() {
                const requiredFields = [{
                        element: document.getElementById('first-name'),
                        name: 'First Name'
                    },
                    {
                        element: document.getElementById('last-name'),
                        name: 'Last Name'
                    },
                    {
                        element: document.getElementById('age'),
                        name: 'Age'
                    },
                    {
                        element: document.getElementById('email'),
                        name: 'Email'
                    },
                    {
                        element: document.getElementById('phone'),
                        name: 'Phone'
                    }
                ];

                let isValid = true;
                let missingFields = [];

                // Check each required field
                requiredFields.forEach(field => {
                    const value = field.element.value.trim();
                    if (!value) {
                        isValid = false;
                        missingFields.push(field.name);
                        field.element.style.borderColor = '#ff4757';
                    } else {
                        field.element.style.borderColor = '';
                    }
                });

                // Validate age range
                const age = parseInt(document.getElementById('age').value);
                if (age < 1 || age > 150) {
                    isValid = false;
                    missingFields.push('Valid age (1-150)');
                    document.getElementById('age').style.borderColor = '#ff4757';
                }

                // Validate passwords if provided
                const password = passwordInput.value.trim();
                const confirmPassword = confirmPasswordInput.value.trim();

                if (password || confirmPassword) {
                    if (!validatePasswords()) {
                        isValid = false;
                        missingFields.push('Password confirmation');
                    }
                }

                // Validate email format
                const email = document.getElementById('email').value.trim();
                if (email && !isValidEmail(email)) {
                    isValid = false;
                    missingFields.push('Valid email address');
                    document.getElementById('email').style.borderColor = '#ff4757';
                }

                if (!isValid) {
                    showNotification(`Please fill in: ${missingFields.join(', ')}`, 'error');
                    return false;
                }

                return true;
            }

            // Email validation
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            // Show notification
            function showNotification(message, type = 'info') {
                const notification = document.getElementById('profile-notification');
                notification.textContent = message;
                notification.style.display = 'block';
                notification.style.background = type === 'error' ? '#ff4757' : type === 'success' ? '#2ed573' : '#1a8cff';

                setTimeout(() => {
                    notification.style.display = 'none';
                }, 5000);
            }

            // Show loading
            function showLoading(message = 'Please wait...') {
                loadingText.textContent = message;
                globalLoading.style.display = 'flex';
                document.body.classList.add('is-busy');
            }

            // Hide loading
            function hideLoading() {
                globalLoading.style.display = 'none';
                document.body.classList.remove('is-busy');
            }

            // Handle profile picture upload preview
            profileUploadInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (!file.type.startsWith('image/')) {
                        showNotification('Please select a valid image file', 'error');
                        return;
                    }

                    // Check file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        showNotification('Image size should be less than 5MB', 'error');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicture.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Password validation on input
            [passwordInput, confirmPasswordInput].forEach(input => {
                input.addEventListener('input', validatePasswords);
            });

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (!validateForm()) {
                    return;
                }

                // Show confirmation modal
                confirmModal.style.display = 'flex';
            });

            // Confirm update
            confirmBtn.addEventListener('click', function() {
                confirmModal.style.display = 'none';
                submitForm();
            });

            // Cancel update
            cancelBtn.addEventListener('click', function() {
                confirmModal.style.display = 'none';
            });

            // Submit form via AJAX
            function submitForm() {
                showLoading('Updating profile...');

                const formData = new FormData(form);

                // Add session user_id to identify the user
                formData.append('user_id', '<?php echo $_SESSION["user_id"]; ?>');

                fetch('user_profile_update.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        hideLoading();

                        if (data.success) {
                            showNotification('Profile updated successfully!', 'success');

                            // Update the original email data attribute if email was changed
                            const emailInput = document.getElementById('email');
                            emailInput.setAttribute('data-original-email', emailInput.value);

                            // Clear password fields
                            passwordInput.value = '';
                            confirmPasswordInput.value = '';

                        } else {
                            showNotification(data.error || 'Failed to update profile', 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        showNotification('Network error. Please try again.', 'error');
                    });
            }

            // Close modal when clicking outside
            confirmModal.addEventListener('click', function(e) {
                if (e.target === confirmModal) {
                    confirmModal.style.display = 'none';
                }
            });

            // Remove error styling when user starts typing
            const inputs = document.querySelectorAll('.profile-info-value');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.style.borderColor = '';
                });
            });
        });
    </script>
</body>

</html>