<?php

declare(strict_types=1);
session_start();

$error = '';
$email = '';
$password = '';

// Use your Supabase Postgres PDO connection
require_once __DIR__ . '/../backend/connection.php'; // must expose $pdo (PDO)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        try {
            // Look up the account in your app table
            $sql = "select user_id, email, password, role, status
                    from public.users
                    where lower(email) = lower(:e)
                    limit 1";
            $st = $pdo->prepare($sql);
            $st->execute([':e' => $email]);
            $user = $st->fetch(PDO::FETCH_ASSOC);

            // Hide non-user roles (e.g., Admin) behind a generic message
            if (!$user) {
                $error = "Can't find account.";
            } elseif (strtolower((string)$user['role']) !== 'user') {
                $error = "Can't find account.";
            } elseif (strtolower((string)$user['status']) !== 'verified') {
                // You can keep this generic too if you want zero enumeration
                $error = 'Your account is not active.';
            } elseif (!password_verify($password, (string)$user['password'])) {
                // Keep or make generic if you want stricter anti-enumeration
                $error = 'Incorrect password.';
            } else {
                // Success
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];     // UUID from auth.users
                $_SESSION['role']    = $user['role'] ?? 'User';

                header('Location: user_home.php');
                exit();
            }
        } catch (Throwable $e) {
            error_log('[USER-LOGIN] ' . $e->getMessage());
            $error = 'System error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: #4caf50;
            --primary-hover: #1b5e20;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --border-radius: 10px;
            --box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('images/waterfall.jpg') no-repeat center center/cover;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(9px);
            z-index: -1;
        }

        .container {
            width: 100%;
            max-width: 420px;
            padding: 25px;
            position: relative;
        }

        .form-box {
            background-color: rgba(255, 255, 255, 0.96);
            padding: 40px 35px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-box.blurred {
            filter: blur(4px);
            pointer-events: none;
        }

        .form-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            margin-top: -1%;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-size: 28px;
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
        }

        h2 {
            margin-bottom: 25px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 26px;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 22px;
            position: relative;
        }

        label {
            display: block;
            text-align: left;
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--text-color);
            font-weight: 500;
        }

        .input-field {
            position: relative;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 14px 42px 14px 15px;
            border: 1px solid var(--primary-hover);
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
            background-color: #f9f9f9;
            color: #444;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
            background-color: white;
        }

        .password-toggle {
            position: absolute;
            right: -41%;
            margin-top: 5%;
            cursor: pointer;
            color: #777;
            z-index: 10;
            background: none !important;
            border: none;
            font-size: 16px;
            padding: 0;
            outline: none;
            transition: none !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .password-toggle:hover {
            background: none !important;
            color: #777 !important;
            box-shadow: none !important;
            transition: none !important;
            transform: none !important;
        }

        /* Hide ALL browser password toggle icons */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear,
        input[type="password"]::-webkit-credentials-auto-fill-button,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-strong-password-auto-fill-button,
        input[type="password"]::-webkit-textfield-decoration-container {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            position: absolute !important;
            right: 0 !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }

        .forgot-password:hover {
            color: var(--primary-hover);
        }

        button {
            background-color: var(--primary-color);
            color: white;
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            margin-top: -3%;
            letter-spacing: 0.5px;
        }

        button:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .form-footer {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
            color: var(--text-color);
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .form-footer p {
            margin-top: -3%;
            /* Adjust margin-top of the text only */
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Forgot Password Form */
        .forgot-password-form {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            width: 90%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.98);
            padding: 40px 35px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .forgot-password-form.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .forgot-password-form h2 {
            margin-bottom: 25px;
            color: var(--primary-color);
        }

        .forgot-password-form p {
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--text-color);
            text-align: center;
        }

        .back-to-login {
            text-align: center;
            margin-top: 15px;
        }

        .back-to-login a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }

        .back-to-login a i {
            margin-right: 5px;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .otp-input-group {
            margin-bottom: 15px;
        }

        .reset-password-form input[type="password"] {
            width: 100%;
            padding: 14px 42px 14px 15px;
            border: 1px solid var(--primary-hover);
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
            background-color: #f9f9f9;
            color: #444;
            position: relative;
        }

        .reset-password-form input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
            background-color: white;
        }

        .password-toggle-reset {
            position: absolute;
            right: 10px;
            top: 55%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            width: fit-content;
        }

        .reset-password-field {
            position: relative;
            margin-bottom: 15px;
        }

        .success-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .success-toast.show {
            opacity: 1;
        }

        .error-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #e53935;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .error-toast.show {
            opacity: 1;
        }

        /* Loading overlay styles */
        #loadingScreen {
            display: none;
            position: fixed;
            z-index: 2000;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.1);
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-direction: row;
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
        }

        #loadingScreen .loading-text {
            font-size: 1.5rem;
            color: var(--primary-color);
            font-weight: bold;
            letter-spacing: 1px;
        }

        .error-message {
            color: #e53935;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        /* For smaller devices like phones */
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            .form-box {
                padding: 30px 25px;
            }

            .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            h2 {
                font-size: 22px;
            }

            .forgot-password-form {
                padding: 30px 25px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="form-box" id="loginForm">
            <div class="logo">
                <div class="logo-icon">🔒</div>
            </div>
            <h2>Welcome Back</h2>

            <?php if (!empty($error)): ?>
                <p class="error-message"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="email">Email</label>
                    <div class="input-field">
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Enter your email" required>

                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-field">
                        <input type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <button type="submit">Login</button>

                <div class="form-footer">
                    <p>Don't have an account? <a href="../index.php">Create Account</a></p>
                </div>
            </form>
        </div>

        <!-- Forgot Password Form with OTP -->
        <div class="forgot-password-form" id="forgotPasswordForm">
            <div class="logo">
                <div class="logo-icon">🔑</div>
            </div>
            <h2>Reset Password</h2>

            <!-- Step 1: Enter Email -->
            <div class="form-section active" id="emailSection">
                <p>Enter your email address and we'll send you an OTP code.</p>
                <div class="input-group">
                    <label for="resetEmail">Email Address</label>
                    <div class="input-field">
                        <input type="email" id="resetEmail" placeholder="Enter your email" required>
                    </div>
                </div>
                <button type="button" id="sendOtpBtn">Send OTP</button>
                <div id="emailError" style="color: #e53935; margin-top: 10px; font-size: 14px;"></div>
            </div>

            <!-- Step 2: Verify OTP -->
            <div class="form-section" id="otpSection">
                <p>Enter the 6-digit code sent to your email.</p>
                <div class="otp-input-group">
                    <label for="resetOtp">Verification Code</label>
                    <input type="text" id="resetOtp" maxlength="6" placeholder="Enter OTP code" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" id="verifyOtpBtn" style="flex: 1;">Verify OTP</button>
                    <button type="button" id="resendOtpBtn" style="flex: 1; background-color: #999;">Resend</button>
                </div>
                <div id="otpError" style="color: #e53935; margin-top: 10px; font-size: 14px;"></div>
            </div>

            <!-- Step 3: Reset Password -->
            <div class="form-section reset-password-form" id="resetPasswordSection">
                <p>Enter your new password.</p>
                <div class="input-group">
                    <label for="newPassword">New Password</label>
                    <div class="reset-password-field">
                        <input type="password" id="newPassword" placeholder="Enter new password" required>
                        <button type="button" class="password-toggle-reset" id="toggleNewPassword">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="input-group">
                    <label for="confirmNewPassword">Confirm Password</label>
                    <div class="reset-password-field">
                        <input type="password" id="confirmNewPassword" placeholder="Confirm password" required>
                        <button type="button" class="password-toggle-reset" id="toggleConfirmNewPassword">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <button type="button" id="resetPasswordBtn">Reset Password</button>
                <div id="resetPasswordError" style="color: #e53935; margin-top: 10px; font-size: 14px;"></div>
            </div>

            <div class="back-to-login">
                <a href="#" id="backToLogin"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <div id="loadingScreen">
        <div class="loading-text">Loading...</div>
    </div>

    <script>
        // Helper function for AJAX calls
        async function postForm(url, fd) {
            const res = await fetch(url, {
                method: 'POST',
                body: fd
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Bad JSON from server:', text);
                throw new Error('Bad JSON');
            }
            return data;
        }

        // Show/hide loading overlay
        function showLoading() {
            document.getElementById('loadingScreen').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingScreen').style.display = 'none';
        }

        // Show toast messages
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = type === 'success' ? 'success-toast show' : 'error-toast show';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const backToLogin = document.getElementById('backToLogin');
            const loginForm = document.getElementById('loginForm');
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');

            // Reset form elements
            const resetEmail = document.getElementById('resetEmail');
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const resetOtp = document.getElementById('resetOtp');
            const verifyOtpBtn = document.getElementById('verifyOtpBtn');
            const resendOtpBtn = document.getElementById('resendOtpBtn');
            const newPassword = document.getElementById('newPassword');
            const confirmNewPassword = document.getElementById('confirmNewPassword');
            const resetPasswordBtn = document.getElementById('resetPasswordBtn');
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const toggleConfirmNewPassword = document.getElementById('toggleConfirmNewPassword');

            const emailSection = document.getElementById('emailSection');
            const otpSection = document.getElementById('otpSection');
            const resetPasswordSection = document.getElementById('resetPasswordSection');

            // Password toggle for login
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                    this.querySelector('i').classList.toggle('fa-eye');
                });
            }

            // Password toggles for reset form
            const setupPasswordToggle = (btn, input) => {
                if (btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                        this.querySelector('i').classList.toggle('fa-eye');
                    });
                }
            };
            setupPasswordToggle(toggleNewPassword, newPassword);
            setupPasswordToggle(toggleConfirmNewPassword, confirmNewPassword);

            // Show forgot password form
            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                loginForm.classList.add('blurred');
                forgotPasswordForm.classList.add('active');
                resetForm();
            });

            // Back to login
            backToLogin.addEventListener('click', function(e) {
                e.preventDefault();
                loginForm.classList.remove('blurred');
                forgotPasswordForm.classList.remove('active');
                resetForm();
            });

            // Reset form to email step
            function resetForm() {
                resetEmail.value = '';
                resetOtp.value = '';
                newPassword.value = '';
                confirmNewPassword.value = '';
                document.getElementById('emailError').textContent = '';
                document.getElementById('otpError').textContent = '';
                document.getElementById('resetPasswordError').textContent = '';

                emailSection.classList.add('active');
                otpSection.classList.remove('active');
                resetPasswordSection.classList.remove('active');
            }

            // Step 1: Send OTP
            sendOtpBtn.addEventListener('click', async () => {
                const email = resetEmail.value.trim();
                const errorDiv = document.getElementById('emailError');
                errorDiv.textContent = '';

                if (!email) {
                    errorDiv.textContent = 'Please enter your email.';
                    return;
                }

                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    errorDiv.textContent = 'Please enter a valid email address.';
                    return;
                }

                sendOtpBtn.disabled = true;
                showLoading();

                try {
                    const fd = new FormData();
                    fd.append('action', 'send_otp');
                    fd.append('email', email);

                    const data = await postForm('../backend/users/reset_password.php', fd);
                    hideLoading();
                    sendOtpBtn.disabled = false;

                    if (!data.success) {
                        errorDiv.textContent = data.error || 'Failed to send OTP.';
                        return;
                    }

                    // Dev only: log OTP for testing
                    if (data.otp) console.log('OTP (testing):', data.otp);

                    // Move to OTP step
                    emailSection.classList.remove('active');
                    otpSection.classList.add('active');
                    resetOtp.focus();
                } catch (err) {
                    hideLoading();
                    sendOtpBtn.disabled = false;
                    errorDiv.textContent = 'Network error. Please try again.';
                }
            });

            // Step 2: Verify OTP
            verifyOtpBtn.addEventListener('click', async () => {
                const otp = resetOtp.value.trim();
                const errorDiv = document.getElementById('otpError');
                errorDiv.textContent = '';

                if (!/^\d{6}$/.test(otp)) {
                    errorDiv.textContent = 'Please enter a valid 6-digit code.';
                    return;
                }

                verifyOtpBtn.disabled = true;
                showLoading();

                try {
                    const fd = new FormData();
                    fd.append('action', 'verify_otp');
                    fd.append('otp', otp);

                    const data = await postForm('../backend/users/reset_password.php', fd);
                    hideLoading();
                    verifyOtpBtn.disabled = false;

                    if (!data.success) {
                        errorDiv.textContent = data.error || 'Incorrect OTP.';
                        return;
                    }

                    // Move to password reset step
                    otpSection.classList.remove('active');
                    resetPasswordSection.classList.add('active');
                    newPassword.focus();
                } catch (err) {
                    hideLoading();
                    verifyOtpBtn.disabled = false;
                    errorDiv.textContent = 'Network error. Please try again.';
                }
            });

            // Resend OTP
            resendOtpBtn.addEventListener('click', async () => {
                const email = resetEmail.value.trim();
                const errorDiv = document.getElementById('otpError');
                errorDiv.textContent = '';

                resendOtpBtn.disabled = true;
                showLoading();

                try {
                    const fd = new FormData();
                    fd.append('action', 'send_otp');
                    fd.append('email', email);

                    const data = await postForm('../backend/users/reset_password.php', fd);
                    hideLoading();
                    resendOtpBtn.disabled = false;

                    if (data.success) {
                        errorDiv.textContent = 'OTP resent! Check your email.';
                        errorDiv.style.color = '#4caf50';
                        if (data.otp) console.log('Resent OTP (testing):', data.otp);
                        resetOtp.value = '';
                        resetOtp.focus();
                    } else {
                        errorDiv.textContent = data.error || 'Failed to resend OTP.';
                        errorDiv.style.color = '#e53935';
                    }
                } catch (err) {
                    hideLoading();
                    resendOtpBtn.disabled = false;
                    errorDiv.textContent = 'Network error. Please try again.';
                    errorDiv.style.color = '#e53935';
                }
            });

            // Step 3: Reset Password
            resetPasswordBtn.addEventListener('click', async () => {
                const password = newPassword.value;
                const confirmPassword = confirmNewPassword.value;
                const errorDiv = document.getElementById('resetPasswordError');
                errorDiv.textContent = '';

                if (!password || !confirmPassword) {
                    errorDiv.textContent = 'Please enter both passwords.';
                    return;
                }

                if (password.length < 8) {
                    errorDiv.textContent = 'Password must be at least 8 characters.';
                    return;
                }

                if (password !== confirmPassword) {
                    errorDiv.textContent = 'Passwords do not match.';
                    return;
                }

                resetPasswordBtn.disabled = true;
                showLoading();

                try {
                    const fd = new FormData();
                    fd.append('action', 'reset_password');
                    fd.append('password', password);
                    fd.append('confirm_password', confirmPassword);

                    const data = await postForm('../backend/users/reset_password.php', fd);
                    hideLoading();
                    resetPasswordBtn.disabled = false;

                    if (!data.success) {
                        errorDiv.textContent = data.error || 'Password reset failed.';
                        return;
                    }

                    // Success - show toast and redirect
                    showToast('Password reset successful! Redirecting to login...', 'success');
                    setTimeout(() => {
                        loginForm.classList.remove('blurred');
                        forgotPasswordForm.classList.remove('active');
                        resetForm();
                    }, 2000);
                } catch (err) {
                    hideLoading();
                    resetPasswordBtn.disabled = false;
                    errorDiv.textContent = 'Network error. Please try again.';
                }
            });
        });
    </script>
</body>

</html>