<?php
// wildlife/wildprofile.php
declare(strict_types=1);
session_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Manila');

/* Adjust path if your connection file is elsewhere */
require_once __DIR__ . '/../backend/connection.php'; // provides $pdo

/* ---- AJAX: mark single / mark all read (handled by this same page) ---- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['ajax'] === 'mark_read') {
            $notifId = $_POST['notif_id'] ?? '';
            if (!$notifId) {
                echo json_encode(['ok' => false, 'error' => 'missing notif_id']);
                exit;
            }

            $st = $pdo->prepare("UPDATE public.notifications SET is_read=true WHERE notif_id=:id");
            $st->execute([':id' => $notifId]);

            echo json_encode(['ok' => true]);
            exit;
        }

        if ($_GET['ajax'] === 'mark_all_read') {
            $pdo->beginTransaction();
            $pdo->exec("
                UPDATE public.notifications
                   SET is_read = true
                 WHERE LOWER(COALESCE(\"to\", ''))='wildlife' AND is_read=false
            ");
            $pdo->commit();
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'unknown action']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[WILD NOTIF AJAX] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'server error']);
    }
    exit;
}

/* ---- helpers used by the UI snippet ---- */
if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false): string
    {
        if (!$datetime) return '';
        $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
        // DB stores timestamps in UTC without timezone info, so parse as UTC then convert
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

/* ---- data needed by your pasted UI (badge + lists) ---- */
$wildNotifs = [];
$incRows = [];
$unreadWildlife = 0;

try {
    $wildNotifs = $pdo->query("
        SELECT
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
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $unreadWildlife = (int)$pdo->query("
        SELECT COUNT(*)
        FROM public.notifications n
        WHERE LOWER(COALESCE(n.\"to\", '')) = 'wildlife'
          AND n.is_read = false
    ")->fetchColumn();

    $incRows = [];
} catch (Throwable $e) {
    error_log('[NOTIF BOOTSTRAP] ' . $e->getMessage());
    $wildNotifs = [];
    $incRows = [];
    $unreadWildlife = 0;
}



if (!isset($_SESSION['user_id'])) {
    header('Location: ../superlogin.php');
    exit();
}

require_once __DIR__ . '/../backend/connection.php'; // must expose $pdo (PDO to Supabase Postgres)

$user_uuid = (string)$_SESSION['user_id'];

try {
    $st = $pdo->prepare("
        SELECT image, first_name, last_name, age, email, role, department, phone
        FROM public.users
        WHERE user_id = :uid
        LIMIT 1
    ");
    $st->execute([':uid' => $user_uuid]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[WILDLIFE PROFILE READ] ' . $e->getMessage());
    $user = null;
}

if (!$user || strtolower((string)$user['department']) !== 'wildlife') {
    header('Location: ../superlogin.php');
    exit();
}

// If the stored image is a URL, use it; else fallback to default.
$imgVal = trim((string)($user['image'] ?? ''));
$isUrl  = (bool)preg_match('~^https?://~i', $imgVal);
$profile_image = $isUrl && $imgVal !== '' ? htmlspecialchars($imgVal, ENT_QUOTES, 'UTF-8')
    : '/denr/superadmin/default-profile.jpg';

$first_name = htmlspecialchars((string)($user['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$last_name  = htmlspecialchars((string)($user['last_name'] ?? ''),  ENT_QUOTES, 'UTF-8');
$age        = htmlspecialchars((string)($user['age'] ?? ''),        ENT_QUOTES, 'UTF-8');
$email      = htmlspecialchars((string)($user['email'] ?? ''),      ENT_QUOTES, 'UTF-8');
$role       = htmlspecialchars((string)($user['role'] ?? ''),       ENT_QUOTES, 'UTF-8');
$department = htmlspecialchars((string)($user['department'] ?? ''), ENT_QUOTES, 'UTF-8');
$phone      = htmlspecialchars((string)($user['phone'] ?? ''),      ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wildlife Admin Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="/denr/superadmin/css/treeprofile.css" />
    <style>
        .toast {
            display: none;
            position: fixed;
            top: 5px;
            left: 50%;
            transform: translateX(-50%);
            background: #323232;
            color: #fff;
            padding: 14px 22px;
            border-radius: 8px;
            font-size: 1rem;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15)
        }

        /* Loading overlay */
        #loadingScreen {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, .1);
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-direction: row;
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px)
        }

        #loadingScreen .loading-text {
            font-size: 1.5rem;
            color: #008031;
            font-weight: bold;
            letter-spacing: 1px
        }

        #loadingLogo {
            width: 60px;
            height: 60px;
            transition: width .5s, height .5s
        }
    </style>
</head>

<body>
    <!-- Loading overlay -->
    <div id="loadingScreen" style="display:none;position:fixed;z-index:2000;inset:0;width:100vw;height:100vh;background:rgba(0,0,0,.1);align-items:center;justify-content:center;gap:10px;flex-direction:row;backdrop-filter:blur(3px)">
        <div class="loading-text" style="font-size:1.5rem;color:#008031;font-weight:bold;letter-spacing:1px">Loading...</div>
        <img id="loadingLogo" src="../denr.png" alt="Loading Logo" style="width:60px;height:60px;transition:width .5s,height .5s">
    </div>


    <header>
        <div class="logo">
            <a href="wildhome.php">
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
                    <a href="breedingreport.php" class="dropdown-item">
                        <i class="fas fa-plus-circle"></i><span>Add Record</span>
                    </a>
                    <a href="wildpermit.php" class="dropdown-item">
                        <i class="fas fa-paw"></i><span>Wildlife Permit</span>
                    </a>
                    <a href="reportaccident.php" class="dropdown-item">
                        <i class="fas fa-file-invoice"></i><span>Incident Reports</span>
                    </a>
                </div>
            </div>



            <div class="nav-item dropdown" data-dropdown id="notifDropdown" style="position:relative;">
                <div class="nav-icon" aria-haspopup="true" aria-expanded="false" style="position:relative;">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?= (int)$unreadWildlife ?></span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3 style="margin:0;">Notifications</h3>
                        <a href="#" class="mark-all-read" id="markAllRead">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="wildNotifList">
                        <?php
                        $combined = [];

                        // Permits / notifications
                        foreach ($wildNotifs as $nf) {
                            $combined[] = [
                                'id'          => $nf['notif_id'],
                                'notif_id'    => $nf['notif_id'],
                                'approval_id' => $nf['approval_id'] ?? null,
                                'incident_id' => $nf['incident_id'] ?? null,
                                'reqpro_id'   => $nf['reqpro_id'] ?? null,
                                'is_read'     => ($nf['is_read'] === true || $nf['is_read'] === 't' || $nf['is_read'] === 1 || $nf['is_read'] === '1'),
                                'message'     => trim((string)$nf['message'] ?: (h(($nf['client_first'] ?? '') . ' ' . ($nf['client_last'] ?? '')) . ' requested a wildlife permit.')),
                                'ago'         => time_elapsed_string($nf['created_at'] ?? date('c')),
                                'link'        => !empty($nf['reqpro_id']) ? 'wildprofile.php' : (!empty($nf['approval_id']) ? 'wildpermit.php' : (!empty($nf['incident_id']) ? 'reportaccident.php' : 'wildnotification.php'))
                            ];
                        }

                        if (empty($combined)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-title">No wildlife notifications</div>
                                </div>
                            </div>
                            <?php else:
                            foreach ($combined as $item):
                                $hasIncident = isset($item['incident_id']) && $item['incident_id'] !== null && trim((string)$item['incident_id']) !== '';
                                $hasApproval = isset($item['approval_id']) && $item['approval_id'] !== null && trim((string)$item['approval_id']) !== '';
                                $hasReqpro   = isset($item['reqpro_id'])   && $item['reqpro_id']   !== null && trim((string)$item['reqpro_id'])   !== '';

                                if ($hasIncident) {
                                    $title = 'Incident report';
                                } elseif ($hasApproval) {
                                    $title = 'Permit request';
                                } elseif ($hasReqpro) {
                                    $title = 'Profile request';
                                } else {
                                    $title = 'Permit request';
                                }
                                $iconClass = $item['is_read'] ? 'fa-regular fa-bell' : 'fa-solid fa-bell';
                            ?>
                                <div class="notification-item <?= $item['is_read'] ? '' : 'unread' ?>"
                                    data-notif-id="<?= h($item['id']) ?>" <?php if ($hasIncident): ?> data-incident-id="<?= h($item['incident_id']) ?>" <?php endif; ?>>
                                    <a href="<?= h($item['link']) ?>" class="notification-link">
                                        <div class="notification-icon"><i class="<?= $iconClass ?>"></i></div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= h($title) ?></div>
                                            <div class="notification-message"><?= h($item['message']) ?></div>
                                            <div class="notification-time"><?= h($item['ago']) ?></div>
                                        </div>
                                    </a>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div class="notification-footer"><a href="wildnotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <div class="nav-icon active">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="wildprofile.php" class="dropdown-item active-page">
                        <i class="fas fa-user-edit"></i><span>Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Toast -->
    <div id="toast" class="toast" style="display:none;position:fixed;top:5px;left:50%;transform:translateX(-50%);background:#323232;color:#fff;padding:14px 22px;border-radius:8px;font-size:1rem;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.15)"></div>

    <div class="profile-container">
        <div class="profile-header">
            <h1 class="profile-title">Admin Profile</h1>
            <p class="profile-subtitle">View and manage your account information</p>
        </div>

        <div class="profile-body-main">
            <form id="profile-form" class="profile-body" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" id="current-email" value="<?php echo $email; ?>">

                <div class="profile-picture-container">
                    <img src="<?php echo $profile_image; ?>" alt="Profile Picture" class="profile-picture" id="profile-picture"
                        onerror="this.onerror=null;this.src='/denr/superadmin/default-profile.jpg';">
                    <div class="profile-picture-placeholder" id="profile-placeholder" style="display:none;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-upload-icon" onclick="document.getElementById('profile-upload-input').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>

                <input type="file" id="profile-upload-input" name="profile_image" accept="image/*" style="display:none;">
                <div id="image-error" style="color:red;font-size:12px;display:none;"></div>

                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <div class="profile-info-label">First Name</div>
                        <input type="text" class="profile-info-value" id="first-name" name="first_name"
                            value="<?php echo $first_name; ?>" maxlength="60" required>
                        <div id="first-name-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Name</div>
                        <input type="text" class="profile-info-value" id="last-name" name="last_name"
                            value="<?php echo $last_name; ?>" maxlength="60" required>
                        <div id="last-name-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Age</div>
                        <input type="number" class="profile-info-value" id="age" name="age"
                            value="<?php echo $age; ?>" min="0" max="120">
                        <div id="age-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email"
                            value="<?php echo $email; ?>" maxlength="254">
                        <div id="email-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Role</div>
                        <input type="text" class="profile-info-value" id="role" name="role" value="<?php echo $role; ?>" disabled>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Phone</div>
                        <input type="text" class="profile-info-value" id="phone" name="phone"
                            value="<?php echo $phone; ?>" maxlength="20">
                        <div id="phone-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Department</div>
                        <select class="profile-info-value" id="department" name="department" required>
                            <option value="Marine" <?php if ($department === "Marine") echo 'selected'; ?>>Marine</option>
                            <option value="Wildlife" <?php if ($department === "Wildlife") echo 'selected'; ?>>Wildlife</option>
                            <option value="Seedling" <?php if ($department === "Seedling") echo 'selected'; ?>>Seedling</option>
                            <option value="Tree Cutting" <?php if ($department === "Tree Cutting") echo 'selected'; ?>>Tree Cutting</option>
                            <option value="Cenro" <?php if ($department === "Cenro") echo 'selected'; ?>>Cenro</option>
                        </select>
                        <div id="department-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">New Password</div>
                        <input type="password" class="profile-info-value" id="password" name="password"
                            placeholder="Enter new password" minlength="8" maxlength="72">
                        <div id="password-rule-error" style="color:red;font-size:12px;display:none;"></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">Confirm Password</div>
                        <input type="password" class="profile-info-value" id="confirm-password" name="confirm_password"
                            placeholder="Confirm new password" minlength="8" maxlength="72">
                        <div id="password-error" style="color:red;font-size:12px;display:none;">Passwords do not match</div>
                    </div>
                </div>

                <div class="profile-actions">
                    <button type="submit" class="btn btn-primary" id="update-profile-btn">
                        <i class="fas fa-save"></i> Submit Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm modal -->
    <div id="profile-confirm-modal" style="display:none;position:fixed;z-index:3;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,.35);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:10px;padding:32px 28px;min-width:320px;max-width:90vw;box-shadow:0 2px 16px rgba(0,0,0,.18);text-align:center;">
            <h2 style="margin-bottom:18px;font-size:1.3rem;color:#1a3d5d;">Confirm Profile Update</h2>
            <p style="margin-bottom:24px;color:#444;">Are you sure you want to submit these changes for admin review?</p>
            <button id="confirm-profile-update-btn" style="background:#1a8cff;color:#fff;border:none;padding:10px 28px;border-radius:5px;font-size:1rem;margin-right:10px;cursor:pointer;">Yes, Submit</button>
            <button id="cancel-profile-update-btn" style="background:#eee;color:#333;border:none;padding:10px 22px;border-radius:5px;font-size:1rem;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <!-- Info modal -->
    <div id="profile-info-modal" style="display:none;position:fixed;z-index:10000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,.35);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:10px;padding:32px 28px;min-width:320px;max-width:90vw;box-shadow:0 2px 16px rgba(0,0,0,.18);text-align:center;">
            <h2 style="margin-bottom:18px;font-size:1.3rem;color:#1a3d5d;">Request Sent</h2>
            <p style="margin-bottom:24px;color:#444;">Your profile update request has been submitted and is pending admin approval.</p>
            <button id="close-info-modal-btn" style="background:#1a8cff;color:#fff;border:none;padding:10px 28px;border-radius:5px;font-size:1rem;cursor:pointer;">OK</button>
        </div>
    </div>

    <!-- OTP Modal -->
    <div id="otpModal" class="modal" style="display:none;position:fixed;z-index:3;inset:0;background:rgba(0,0,0,.4);padding-top:60px;">
        <div class="modal-content" style="background:#fff;margin:5% auto;padding:20px;border:1px solid #888;width:90%;max-width:380px;border-radius:8px;">
            <span class="close" id="closeModal" style="float:right;font-size:24px;cursor:pointer">&times;</span>
            <h3>Email Verification</h3>
            <p>We sent a 6-digit code to your new email address.</p>
            <input type="text" id="otpInput" maxlength="6" placeholder="Enter OTP code">
            <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
                <button type="button" id="sendOtpBtn">Verify</button>
                <button type="button" id="resendOtpBtn">Resend</button>
                <span id="otpMessage" style="color:red;margin-left:10px;font-size:13px;"></span>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const $ = (id) => document.getElementById(id);

            const API_URL = '/denr/superadmin/backend/admins/profile/update_profile.php';

            // Loading
            const loading = $('loadingScreen');
            const showLoading = () => {
                if (loading) loading.style.display = 'flex';
            };
            const hideLoading = () => {
                if (loading) loading.style.display = 'none';
            };

            // Toast
            const toastEl = $('toast');
            const toast = (msg) => {
                if (!toastEl) return;
                toastEl.textContent = msg;
                toastEl.style.display = 'block';
                toastEl.style.opacity = '1';
                setTimeout(() => {
                    toastEl.style.opacity = '0';
                    setTimeout(() => toastEl.style.display = 'none', 400);
                }, 2000);
            };

            // Elements
            const form = $('profile-form');
            const imgEl = $('profile-picture');
            const fileInput = $('profile-upload-input');
            const imgErr = $('image-error');

            const confirmModal = $('profile-confirm-modal');
            const confirmBtn = $('confirm-profile-update-btn');
            const cancelConfirmBtn = $('cancel-profile-update-btn');
            const infoModal = $('profile-info-modal');
            const infoOkBtn = $('close-info-modal-btn');

            const hiddenEmailEl = $('current-email');
            let currentEmail = (hiddenEmailEl?.value || '').trim();

            // Inputs
            const firstNameEl = $('first-name');
            const lastNameEl = $('last-name');
            const ageEl = $('age');
            const emailEl = $('email');
            const phoneEl = $('phone');
            const deptEl = $('department');
            const pwdEl = $('password');
            const cpwdEl = $('confirm-password');

            // Errors
            const fnErr = $('first-name-error');
            const lnErr = $('last-name-error');
            const ageErr = $('age-error');
            const emErr = $('email-error');
            const phErr = $('phone-error');
            const depErr = $('department-error');
            const pwRuleErr = $('password-rule-error');
            const pwMismatchErr = $('password-error');

            // OTP
            const otpModal = $('otpModal');
            const otpInput = $('otpInput');
            const otpSendBtn = $('sendOtpBtn');
            const otpResendBtn = $('resendOtpBtn');
            const otpMsg = $('otpMessage');
            const otpClose = $('closeModal');

            function showOtp() {
                otpMsg.textContent = '';
                otpInput.value = '';
                otpModal.style.display = 'block';
            }

            function hideOtp() {
                otpModal.style.display = 'none';
            }

            // Error helpers
            function setErr(inputEl, errEl, msg) {
                if (errEl) {
                    errEl.textContent = msg;
                    errEl.style.display = 'block';
                }
                if (inputEl) {
                    inputEl.classList.add('invalid');
                    inputEl.style.borderColor = 'red';
                }
            }

            function clearErr(inputEl, errEl) {
                if (errEl) errEl.style.display = 'none';
                if (inputEl) {
                    inputEl.classList.remove('invalid');
                    inputEl.style.borderColor = '';
                }
            }

            function clearAllErrors() {
                [
                    [firstNameEl, fnErr],
                    [lastNameEl, lnErr],
                    [ageEl, ageErr],
                    [emailEl, emErr],
                    [phoneEl, phErr],
                    [deptEl, depErr],
                    [fileInput, imgErr]
                ]
                .forEach(([i, e]) => clearErr(i, e));
                if (pwRuleErr) pwRuleErr.style.display = 'none';
                if (pwMismatchErr) pwMismatchErr.style.display = 'none';
                pwdEl?.classList.remove('invalid');
                cpwdEl?.classList.remove('invalid');
            }

            // Patterns
            const nameRe = /^[A-Za-zÀ-ÖØ-öø-ÿ\s.'-]{1,60}$/;
            const phoneRe = /^[0-9+()\-\s]{6,20}$/;
            const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            // Field validators
            function validateFirst() {
                const v = (firstNameEl?.value || '').trim();
                if (!v) {
                    setErr(firstNameEl, fnErr, 'First name is required.');
                    return false;
                }
                if (!nameRe.test(v)) {
                    setErr(firstNameEl, fnErr, 'Use letters/spaces/.’- only (max 60).');
                    return false;
                }
                clearErr(firstNameEl, fnErr);
                return true;
            }

            function validateLast() {
                const v = (lastNameEl?.value || '').trim();
                if (!v) {
                    setErr(lastNameEl, lnErr, 'Last name is required.');
                    return false;
                }
                if (!nameRe.test(v)) {
                    setErr(lastNameEl, lnErr, 'Use letters/spaces/.’- only (max 60).');
                    return false;
                }
                clearErr(lastNameEl, lnErr);
                return true;
            }

            function validateAge() {
                const s = (ageEl?.value || '').trim();
                if (s === '') {
                    clearErr(ageEl, ageErr);
                    return true;
                }
                const n = Number(s);
                if (!Number.isInteger(n) || n < 0 || n > 120 || s.length > 3) {
                    setErr(ageEl, ageErr, 'Age must be a whole number 0–120.');
                    return false;
                }
                clearErr(ageEl, ageErr);
                return true;
            }

            function validateEmail() {
                const v = (emailEl?.value || '').trim();
                if (v && !emailRe.test(v)) {
                    setErr(emailEl, emErr, 'Enter a valid email address.');
                    return false;
                }
                clearErr(emailEl, emErr);
                return true;
            }

            function validatePhone() {
                const v = (phoneEl?.value || '').trim();
                if (v && !phoneRe.test(v)) {
                    setErr(phoneEl, phErr, 'Use digits and + ( ) - (6–20 chars).');
                    return false;
                }
                clearErr(phoneEl, phErr);
                return true;
            }

            function validateDept() {
                const v = (deptEl?.value || '').trim();
                if (!v) {
                    setErr(deptEl, depErr, 'Please select a department.');
                    return false;
                }
                clearErr(deptEl, depErr);
                return true;
            }

            function validatePasswords() {
                const pw = pwdEl?.value || '';
                const cpw = cpwdEl?.value || '';
                let ok = true;
                if (pw && pw.length < 8) {
                    setErr(pwdEl, pwRuleErr, 'Password must be at least 8 characters.');
                    ok = false;
                } else {
                    clearErr(pwdEl, pwRuleErr);
                }
                if ((pw || cpw) && pw !== cpw) {
                    setErr(cpwdEl, pwMismatchErr, 'Passwords do not match');
                    ok = false;
                } else {
                    clearErr(cpwdEl, pwMismatchErr);
                }
                return ok;
            }

            // Live validation
            firstNameEl?.addEventListener('input', validateFirst);
            lastNameEl?.addEventListener('input', validateLast);
            ageEl?.addEventListener('input', validateAge);
            emailEl?.addEventListener('input', validateEmail);
            phoneEl?.addEventListener('input', validatePhone);
            deptEl?.addEventListener('change', validateDept);
            pwdEl?.addEventListener('input', validatePasswords);
            cpwdEl?.addEventListener('input', validatePasswords);

            // Image preview + clear error
            fileInput?.addEventListener('change', (e) => {
                clearErr(fileInput, imgErr);
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                const reader = new FileReader();
                reader.onload = () => {
                    if (imgEl) imgEl.src = reader.result;
                };
                reader.readAsDataURL(f);
            });

            // JSON fetch
            async function fetchJSON(url, options) {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        ...(options?.headers || {})
                    },
                    ...options
                });
                const ct = res.headers.get('Content-Type') || '';
                if (!ct.includes('application/json')) {
                    const txt = await res.text().catch(() => '');
                    throw new Error(`Non-JSON (${res.status}): ${txt.slice(0,200)}`);
                }
                const data = await res.json();
                if (!res.ok) {
                    const err = new Error(data?.error || data?.message || `HTTP ${res.status}`);
                    err.__server = data || {};
                    throw err;
                }
                return data;
            }

            // Validate → open confirm
            form?.addEventListener('submit', (e) => {
                e.preventDefault();
                const ok =
                    validateFirst() &
                    validateLast() &
                    validateAge() &
                    validateEmail() &
                    validatePhone() &
                    validateDept() &
                    validatePasswords();
                if (!ok) return;
                confirmModal.style.display = 'flex';
            });

            cancelConfirmBtn?.addEventListener('click', () => {
                confirmModal.style.display = 'none';
            });
            infoOkBtn?.addEventListener('click', () => {
                infoModal.style.display = 'none';
            });

            // POST helper
            async function postProfileAction(action, payload) {
                const fd = new FormData();
                fd.append('action', action);
                Object.entries(payload || {}).forEach(([k, v]) => fd.append(k, v));
                return fetchJSON(API_URL, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            }

            // Final submit
            async function doSubmitRequest() {
                const fd = new FormData(form);
                showLoading();
                try {
                    const data = await fetchJSON(API_URL, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (data.code === 'PENDING_EXISTS') {
                        toast('You already have a pending request.');
                        return;
                    }
                    if (data.code === 'OTP_REQUIRED') {
                        setErr(emailEl, emErr, 'Please verify your new email first (check your inbox).');
                        return;
                    }
                    if (data.code === 'EMAIL_IN_USE') {
                        setErr(emailEl, emErr, 'That email is already in use.');
                        return;
                    }
                    if (!data.success) {
                        toast(data.error || 'Update failed.');
                        return;
                    }

                    form.reset();
                    if (imgEl) imgEl.src = '<?php echo $profile_image; ?>';
                    clearAllErrors();
                    if (hiddenEmailEl) hiddenEmailEl.value = currentEmail;
                    infoModal.style.display = 'flex';
                } catch (e) {
                    const raw = String(e?.message || '');
                    if (/out of range for type integer|invalid input syntax for type integer/i.test(raw)) {
                        setErr(ageEl, ageErr, 'Age is invalid or too large. Use 0–120.');
                        return;
                    }
                    if (/invalid email/i.test(raw)) {
                        setErr(emailEl, emErr, 'Enter a valid email address.');
                        return;
                    }
                    if (/upload.*failed|curl|invalid image type|failed reading uploaded file/i.test(raw)) {
                        setErr(fileInput, imgErr, 'Image upload failed or file type is not allowed.');
                        return;
                    }
                    toast(raw || 'Server error.');
                } finally {
                    hideLoading();
                }
            }

            // Confirm → possibly OTP → submit
            let verifyBound = false;
            confirmBtn?.addEventListener('click', async () => {
                confirmBtn.disabled = true;
                const newEmail = (emailEl?.value || '').trim();
                const changed = newEmail && newEmail.toLowerCase() !== currentEmail.toLowerCase();

                if (changed) {
                    showLoading();
                    try {
                        const r = await postProfileAction('send_email_otp', {
                            email: newEmail
                        });
                        if (!r.success) {
                            setErr(emailEl, emErr, r.error || 'Failed to send code.');
                            confirmModal.style.display = 'none';
                            return;
                        }
                        confirmModal.style.display = 'none';
                        showOtp();

                        if (!verifyBound) {
                            verifyBound = true;

                            otpSendBtn?.addEventListener('click', async () => {
                                const code = (otpInput.value || '').trim();
                                if (!/^\d{6}$/.test(code)) {
                                    otpMsg.textContent = 'Enter the 6-digit code.';
                                    return;
                                }
                                showLoading();
                                try {
                                    const v = await postProfileAction('verify_email_otp', {
                                        otp: code
                                    });
                                    if (!v.success) {
                                        otpMsg.textContent = v.error || 'Invalid/expired code.';
                                        return;
                                    }
                                    hideOtp();
                                    await doSubmitRequest();
                                } catch (err) {
                                    otpMsg.textContent = String(err?.message || 'Network error.');
                                } finally {
                                    hideLoading();
                                }
                            });

                            otpResendBtn?.addEventListener('click', async () => {
                                otpMsg.textContent = '';
                                showLoading();
                                try {
                                    const r2 = await postProfileAction('send_email_otp', {
                                        email: newEmail
                                    });
                                    otpMsg.textContent = r2.success ? 'OTP resent!' : (r2.error || 'Failed to resend.');
                                } catch (err) {
                                    otpMsg.textContent = String(err?.message || 'Network error.');
                                } finally {
                                    hideLoading();
                                }
                            });

                            otpClose?.addEventListener('click', hideOtp);
                        }
                    } catch (e) {
                        const raw = String(e?.message || 'Network error.');
                        if (/email.*in use|email already exists/i.test(raw)) setErr(emailEl, emErr, 'That email is already in use.');
                        else toast(raw);
                    } finally {
                        hideLoading();
                        confirmBtn.disabled = false;
                    }
                    return;
                }

                // No email change → submit directly
                await doSubmitRequest();
                confirmBtn.disabled = false;
                confirmModal.style.display = 'none';
            });
        })();
    </script>
</body>


</html>