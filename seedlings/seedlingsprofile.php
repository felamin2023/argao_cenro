<?php
// seedlings/seedlingsprofile.php
declare(strict_types=1);
session_start();

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
    error_log('[SEEDLINGS PROFILE READ] ' . $e->getMessage());
    $user = null;
}

if (!$user || strtolower((string)$user['department']) !== 'seedling') {
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
    <title>Seedling Admin Profile</title>
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

        #loadingScreen {
            display: none;
            position: fixed;
            z-index: 2000;
            top: 0;
            left: 0;
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
    <div id="loadingScreen">
        <div class="loading-text">Loading...</div>
        <img id="loadingLogo" src="../denr.png" alt="Loading Logo">
    </div>

    <header>
        <div class="logo"><a href="seedlingshome.php"><img src="seal.png" alt="Site Logo"></a></div>
        <button class="mobile-toggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
        <div class="nav-container">
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <div class="dropdown-menu center">
                    <a href="treecutting.php" class="dropdown-item"><i class="fas fa-tree"></i><span>Tree Cutting</span></a>
                    <a href="lumber.php" class="dropdown-item"><i class="fas fa-store"></i><span>Lumber Dealers</span></a>
                    <a href="chainsaw.php" class="dropdown-item"><i class="fas fa-tools"></i><span>Registered Chainsaw</span></a>
                    <a href="woodprocessing.php" class="dropdown-item"><i class="fas fa-industry"></i><span>Wood Processing</span></a>
                    <a href="reportaccident.php" class="dropdown-item"><i class="fas fa-file-invoice"></i><span>Incident Reports</span></a>
                </div>
            </div>
            <div class="nav-item">
                <div class="nav-icon"><a href="treemessage.php" aria-label="Messages"><i class="fas fa-envelope" style="color:black;"></i></a></div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3><a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    <div class="notification-item unread">
                        <a href="treeeach.php?id=1" class="notification-link">
                            <div class="notification-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="notification-content">
                                <div class="notification-title">Illegal Logging Alert</div>
                                <div class="notification-message">Report of unauthorized tree cutting activity in protected area.</div>
                                <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    <div class="notification-footer"><a href="treenotification.php" class="view-all">View All Notifications</a></div>
                </div>
            </div>
            <div class="nav-item dropdown">
                <div class="nav-icon active"><i class="fas fa-user-circle"></i></div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item active-page"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
                    <a href="../logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                </div>
            </div>
        </div>
    </header>

    <!-- Toast -->
    <div id="toast" class="toast"></div>

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

                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <div class="profile-info-label">First Name</div>
                        <input type="text" class="profile-info-value" id="first-name" name="first_name" value="<?php echo $first_name; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Last Name</div>
                        <input type="text" class="profile-info-value" id="last-name" name="last_name" value="<?php echo $last_name; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Age</div>
                        <input type="number" class="profile-info-value" id="age" name="age" value="<?php echo $age; ?>" min="0">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email</div>
                        <input type="email" class="profile-info-value" id="email" name="email" value="<?php echo $email; ?>">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Role</div>
                        <input type="text" class="profile-info-value" id="role" name="role" value="<?php echo $role; ?>" disabled>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Phone</div>
                        <input type="text" class="profile-info-value" id="phone" name="phone" value="<?php echo $phone; ?>">
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
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">New Password</div>
                        <input type="password" class="profile-info-value" id="password" name="password" placeholder="Enter new password">
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Confirm Password</div>
                        <input type="password" class="profile-info-value" id="confirm-password" name="confirm_password" placeholder="Confirm new password">
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

            // Use ABSOLUTE path to avoid relative confusion
            const API_URL = '/denr/superadmin/backend/admins/profile/update_profile.php';

            // Loading helpers
            const loading = $('loadingScreen');
            const showLoading = () => {
                if (loading) loading.style.display = 'flex';
            };
            const hideLoading = () => {
                if (loading) loading.style.display = 'none';
            };

            // Toast
            const toastEl = $('toast');

            function toast(msg) {
                if (!toastEl) return;
                toastEl.textContent = msg;
                toastEl.style.display = 'block';
                toastEl.style.opacity = '1';
                setTimeout(() => {
                    toastEl.style.opacity = '0';
                    setTimeout(() => toastEl.style.display = 'none', 400);
                }, 2000);
            }

            // Elements
            const form = $('profile-form');
            const imgEl = $('profile-picture');
            const fileInput = $('profile-upload-input');
            const confirmModal = $('profile-confirm-modal');
            const confirmBtn = $('confirm-profile-update-btn');
            const cancelConfirmBtn = $('cancel-profile-update-btn');
            const infoModal = $('profile-info-modal');
            const infoOkBtn = $('close-info-modal-btn');

            const hiddenEmailEl = $('current-email');
            let currentEmail = (hiddenEmailEl?.value || '').trim();
            const emailInput = $('email');

            // OTP Modal
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

            // Track original values to restore after success
            const originalImageSrc = imgEl?.src || '/denr/superadmin/default-profile.jpg';
            const original = {
                first_name: ($('first-name')?.value || ''),
                last_name: ($('last-name')?.value || ''),
                age: ($('age')?.value || ''),
                email: (emailInput?.value || ''),
                phone: ($('phone')?.value || ''),
                department: ($('department')?.value || '')
            };

            // Preview
            fileInput && fileInput.addEventListener('change', (e) => {
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                const reader = new FileReader();
                reader.onload = () => {
                    if (imgEl) imgEl.src = reader.result;
                };
                reader.readAsDataURL(f);
            });

            // Safe JSON fetch helper
            async function fetchJSON(url, options) {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        ...(options?.headers || {})
                    },
                    ...options
                });
                const ctype = res.headers.get('Content-Type') || '';
                if (!ctype.includes('application/json')) {
                    const txt = await res.text().catch(() => '');
                    throw new Error(`Non-JSON (${res.status}): ${txt.slice(0, 200)}`);
                }
                const data = await res.json();
                if (!res.ok) {
                    const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
                    throw new Error(msg);
                }
                return data;
            }

            // Validate → Confirm modal
            form && form.addEventListener('submit', (e) => {
                e.preventDefault();
                const pw = $('password')?.value || '';
                const cpw = $('confirm-password')?.value || '';
                const err = $('password-error');
                if (pw !== cpw) {
                    if (err) err.style.display = 'block';
                    return;
                }
                if (err) err.style.display = 'none';
                confirmModal.style.display = 'flex';
            });

            cancelConfirmBtn && cancelConfirmBtn.addEventListener('click', () => {
                confirmModal.style.display = 'none';
            });
            infoOkBtn && infoOkBtn.addEventListener('click', () => {
                infoModal.style.display = 'none';
            });

            // POST helper to update_profile.php with action
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

            // Real submit (insert request)
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
                        toast('Please verify your new email first.');
                        return;
                    }
                    if (data.code === 'EMAIL_IN_USE') {
                        toast('That email is already in use.');
                        return;
                    }
                    if (!data.success) {
                        toast(data.error || 'Update failed.');
                        return;
                    }

                    // Success — reset to original values
                    form.reset();
                    if (fileInput) fileInput.value = '';
                    if (imgEl) imgEl.src = originalImageSrc;
                    if (hiddenEmailEl) hiddenEmailEl.value = original.email;
                    currentEmail = original.email;
                    const pwEl = $('password');
                    if (pwEl) pwEl.value = '';
                    const cpwEl = $('confirm-password');
                    if (cpwEl) cpwEl.value = '';
                    infoModal.style.display = 'flex';
                } catch (e) {
                    toast(e?.message || 'Network error.');
                } finally {
                    hideLoading();
                }
            }

            // Confirm → possibly OTP → submit
            let verifyBound = false;
            confirmBtn && confirmBtn.addEventListener('click', async () => {
                confirmBtn.disabled = true;
                const newEmail = (emailInput?.value || '').trim();
                const changed = newEmail && newEmail.toLowerCase() !== currentEmail.toLowerCase();

                if (changed) {
                    showLoading();
                    try {
                        const sendRes = await postProfileAction('send_email_otp', {
                            email: newEmail
                        });
                        if (!sendRes.success) {
                            toast(sendRes.error || 'Failed to send code.');
                            confirmModal.style.display = 'none';
                            return;
                        }
                        confirmModal.style.display = 'none';
                        showOtp();

                        if (!verifyBound) {
                            verifyBound = true;

                            otpSendBtn.addEventListener('click', async () => {
                                const code = (otpInput.value || '').trim();
                                if (!/^\d{6}$/.test(code)) {
                                    otpMsg.textContent = 'Enter the 6-digit code.';
                                    return;
                                }
                                showLoading();
                                try {
                                    const verRes = await postProfileAction('verify_email_otp', {
                                        otp: code
                                    });
                                    if (!verRes.success) {
                                        otpMsg.textContent = verRes.error || 'Invalid or expired code.';
                                        return;
                                    }
                                    hideOtp();
                                    await doSubmitRequest();
                                } catch (err) {
                                    otpMsg.textContent = (err && err.message) ? err.message : 'Network error.';
                                } finally {
                                    hideLoading();
                                }
                            });

                            otpResendBtn.addEventListener('click', async () => {
                                otpMsg.textContent = '';
                                showLoading();
                                try {
                                    const r = await postProfileAction('send_email_otp', {
                                        email: newEmail
                                    });
                                    otpMsg.textContent = r.success ? 'OTP resent!' : (r.error || 'Failed to resend.');
                                } catch (err) {
                                    otpMsg.textContent = (err && err.message) ? err.message : 'Network error.';
                                } finally {
                                    hideLoading();
                                }
                            });

                            otpClose && otpClose.addEventListener('click', hideOtp);
                        }
                    } catch (e) {
                        toast(e?.message || 'Network error.');
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