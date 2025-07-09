<?php
session_start();
ob_start();

// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "cenro_argao";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default values
$error = "";
$old = ['username' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $old['username'] = $username;

    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, department FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['department'] = $user['department'];

                header("Location: user_home.php");

                ob_end_flush();
                exit();
            } else {
                $error = "Incorrect password";
            }
        } else {
            $error = "Username not found";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
                    <label for="username">Username</label>
                    <div class="input-field">
                        <input
                            type="text"
                            name="username"
                            placeholder="<?= in_array($error, ['Username is required', 'Username not found']) ? htmlspecialchars($error) : 'Username' ?>"
                            class="<?= in_array($error, ['Username is required', 'Username not found']) ? 'error' : '' ?>"
                            value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                            required />
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-field">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="<?= in_array($error, ['Password is required', 'Incorrect password']) ? htmlspecialchars($error) : 'Password' ?>"
                            class="<?= in_array($error, ['Password is required', 'Incorrect password']) ? 'error' : '' ?>"
                            required />
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <button type="submit">Login</button>

                <div class="form-footer">
                    <p>Don't have an account? <a href="user_register.php">Create Account</a></p>
                </div>
            </form>
        </div>

        <!-- Forgot Password Form -->
        <div class="forgot-password-form" id="forgotPasswordForm">
            <div class="logo">
                <div class="logo-icon">🔑</div>
            </div>
            <h2>Reset Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            <form id="resetPasswordForm">
                <div class="input-group">
                    <label for="resetEmail">Email Address</label>
                    <div class="input-field">
                        <input type="email" id="resetEmail" required placeholder="Enter your email">
                    </div>
                </div>
                <button type="submit">Send Reset Link</button>
            </form>
            <div class="back-to-login">
                <a href="#" id="backToLogin"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');

            // Initialize with eye-slash icon (password hidden)
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle eye icon
                if (type === 'password') {
                    eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });

            // Forgot password functionality
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const backToLogin = document.getElementById('backToLogin');
            const loginForm = document.getElementById('loginForm');
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            const resetPasswordForm = document.getElementById('resetPasswordForm');

            forgotPasswordLink.addEventListener('click', function(e) {
                e.preventDefault();
                loginForm.classList.add('blurred');
                forgotPasswordForm.classList.add('active');
            });

            backToLogin.addEventListener('click', function(e) {
                e.preventDefault();
                loginForm.classList.remove('blurred');
                forgotPasswordForm.classList.remove('active');
            });

            resetPasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = document.getElementById('resetEmail').value;

                // Here you would normally send the email to your server
                alert('Password reset link would be sent to: ' + email);

                // Reset and hide the form
                resetPasswordForm.reset();
                loginForm.classList.remove('blurred');
                forgotPasswordForm.classList.remove('active');
            });
        });
    </script>
</body>

</html>