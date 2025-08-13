<?php
session_start();
$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);

$success = isset($_GET['success']) && $_GET['success'] == 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/denr/superadmin/css/superregister.css">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #4caf50;
            --primary-dark: #1b5e20;
            --accent: #FFD700;
            /* Golden yellow */
            --text: #333;
            --light-bg: #f9f9f9;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('images/waterfall.jpg') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(9px);
            z-index: -1;
        }

        .form-container {
            width: 90%;
            max-width: 380px;
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 10px;
            background: var(--primary);
            color: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: var(--primary);
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 15px;
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

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--text);
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--light-bg);
            transition: all 0.3s;
            border: 1px solid var(--primary);
        }

        input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 16px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        .form-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer p {
            margin-top: -4%;
            /* Adjust margin-top of the text only */
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .success-message {
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            position: absolute;
            height: 100vh;
            width: 100%;
        }

        .message-content {
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            gap: 20px;
            z-index: 9999;
        }

        .message-content h1 {
            background-image: linear-gradient(to bottom, #78e9e9, #008031);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .message-content a {
            padding: 7px 20px;
            background: linear-gradient(to bottom, #78e9e9, #008031);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        #otpInput {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--light-bg);
        }

        #sendOtpBtn,
        #resendOtpBtn {
            padding: 10px 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        #sendOtpBtn:hover,
        #resendOtpBtn:hover {
            background: var(--primary-dark);
        }

        #otpMessage {
            margin-top: 10px;
            font-size: 13px;
            color: red;
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
            color: #008031;
            font-weight: bold;
            letter-spacing: 1px;
        }

        #loadingLogo {
            width: 60px;
            height: 60px;
            transition: width 0.5s, height 0.5s;
        }
    </style>
</head>

<body>
    <!-- Loading overlay -->
    <div id="loadingScreen">
        <div class="loading-text">Loading...</div>
        <img id="loadingLogo" src="../denr.png" alt="Loading Logo">
    </div>
    <div class="form-container">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Create Account</h2>
        </div>
        <form id="registerForm" action="../backend/users/register.php" method="POST" autocomplete="off">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email"
                    placeholder="Email" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" id="phone"
                    placeholder="Phone Number (e.g. 09123456789)" required disabled>
            </div>
            <div class="form-group" style="position:relative;">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password"
                        placeholder="Password" required disabled>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group" style="position:relative;">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password"
                        placeholder="Confirm Password" required disabled>
                    <button type="button" class="password-toggle" id="toggleConfirmPassword">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <button type="button" id="verifyEmailBtn">Verify Email</button>
            <button type="submit" id="registerBtn" style="display:none;">Register</button>
            <div id="formError" style="color:red;margin-top:10px;"></div>
            <div class="form-footer">
                <p>Already have an account? <a href="user_login.php">Login</a></p>
            </div>
        </form>
    </div>

    <!-- OTP Modal -->
    <div id="otpModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>
            <h3>Email Verification</h3>
            <input type="text" id="otpInput" maxlength="6" placeholder="Enter OTP code">
            <div style="margin-top:10px; display: flex; align-items: center; gap: 10px;">
                <button type="button" id="sendOtpBtn">Send</button>
                <button type="button" id="resendOtpBtn">Resend</button>
                <span id="otpMessage" style="color:red; margin-left: 10px; font-size: 13px;"></span>
            </div>
        </div>
    </div>

    <div class="success-message" id="successMessage" style="display:none;">
        <div class="message-content">
            <h1>Successfully Registered</h1>
            <p>You can now log in to your account.</p>
            <a href="user_login.php">OKAY</a>
        </div>
    </div>

    <script>
        // Password toggle logic
        document.addEventListener('DOMContentLoaded', function() {
            function togglePassword(inputId, btnId) {
                const btn = document.getElementById(btnId);
                const input = document.getElementById(inputId);
                btn.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    btn.innerHTML = type === 'password' ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
                });
            }
            togglePassword('password', 'togglePassword');
            togglePassword('confirm_password', 'toggleConfirmPassword');

            // Email verification and OTP logic
            const verifyEmailBtn = document.getElementById('verifyEmailBtn');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const registerBtn = document.getElementById('registerBtn');
            const formError = document.getElementById('formError');
            const otpModal = document.getElementById('otpModal');
            const closeModal = document.getElementById('closeModal');
            const otpInput = document.getElementById('otpInput');
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const resendOtpBtn = document.getElementById('resendOtpBtn');
            const otpMessage = document.getElementById('otpMessage');
            const successMessage = document.getElementById('successMessage');
            const loadingScreen = document.getElementById('loadingScreen');
            let emailVerified = false;

            function showLoading() {
                loadingScreen.style.display = 'flex';
            }

            function hideLoading() {
                loadingScreen.style.display = 'none';
            }

            // Email validation function
            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            verifyEmailBtn.addEventListener('click', function() {
                formError.textContent = '';
                const email = emailInput.value.trim();

                if (!email) {
                    formError.textContent = 'Please enter your email.';
                    return;
                }

                if (!isValidEmail(email)) {
                    formError.textContent = 'Please enter a valid email address.';
                    return;
                }

                verifyEmailBtn.disabled = true;
                showLoading();

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('../backend/users/register.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        hideLoading();
                        verifyEmailBtn.disabled = false;
                        if (data.success) {
                            otpModal.style.display = 'block';
                            // For testing only - remove in production
                            console.log('OTP for testing:', data.otp);
                        } else {
                            formError.textContent = data.error || 'Email verification failed.';
                        }
                    })
                    .catch(() => {
                        hideLoading();
                        verifyEmailBtn.disabled = false;
                        formError.textContent = 'Network error.';
                    });
            });

            closeModal.addEventListener('click', function() {
                otpModal.style.display = 'none';
            });

            sendOtpBtn.addEventListener('click', function() {
                otpMessage.textContent = '';
                const otp = otpInput.value.trim();

                if (!otp || !/^\d{6}$/.test(otp)) {
                    otpMessage.textContent = 'Please enter a valid 6-digit OTP.';
                    return;
                }

                showLoading();

                const formData = new FormData();
                formData.append('action', 'verify_otp');
                formData.append('otp', otp);

                fetch('../backend/users/register.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            otpModal.style.display = 'none';
                            emailVerified = true;
                            phoneInput.disabled = false;
                            passwordInput.disabled = false;
                            confirmPasswordInput.disabled = false;
                            registerBtn.style.display = '';
                            verifyEmailBtn.style.display = 'none';
                            formError.textContent = '';
                        } else {
                            otpMessage.textContent = data.error || 'Incorrect OTP.';
                        }
                    })
                    .catch(() => {
                        hideLoading();
                        otpMessage.textContent = 'Network error.';
                    });
            });

            resendOtpBtn.addEventListener('click', function() {
                const email = emailInput.value.trim();
                showLoading();

                const formData = new FormData();
                formData.append('action', 'send_otp');
                formData.append('email', email);

                fetch('../backend/users/register.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            otpMessage.textContent = 'OTP resent! Check your email.';
                            // For testing only - remove in production
                            console.log('New OTP for testing:', data.otp);
                        } else {
                            otpMessage.textContent = data.error || 'Failed to resend OTP.';
                        }
                    })
                    .catch(() => {
                        hideLoading();
                        otpMessage.textContent = 'Network error.';
                    });
            });

            // Registration submit
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                e.preventDefault();

                if (!emailVerified) {
                    formError.textContent = 'Please verify your email first.';
                    return;
                }

                const phone = phoneInput.value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                // Additional client-side validation
                if (!phone) {
                    formError.textContent = 'Phone number is required.';
                    return;
                }

                if (!/^09\d{9}$/.test(phone)) {
                    formError.textContent = 'Please enter a valid phone number (e.g., 09123456789).';
                    return;
                }

                if (!password || !confirmPassword) {
                    formError.textContent = 'Both password fields are required.';
                    return;
                }

                if (password !== confirmPassword) {
                    formError.textContent = 'Passwords do not match.';
                    return;
                }

                if (password.length < 8) {
                    formError.textContent = 'Password must be at least 8 characters.';
                    return;
                }

                showLoading();

                const formData = new FormData(this);
                formData.append('action', 'register');

                fetch('../backend/users/register.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            window.location.href = 'user_register.php?success=1';
                        } else {
                            formError.textContent = data.error || 'Registration failed.';
                        }
                    })
                    .catch(() => {
                        hideLoading();
                        formError.textContent = 'Network error.';
                    });
            });

            // Success message logic (handled by backend redirect)
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                successMessage.style.display = 'flex';
            <?php endif; ?>
        });
    </script>
</body>

</html>