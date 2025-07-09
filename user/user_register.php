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
    </style>
</head>

<body>


    <?php if ($success): ?>
        <div class="success-message">
            <div class="message-content">
                <h1>Successfully Registered</h1>
                <p>You can now log in to your account.</p>
                <a href="user_login.php">OKAY</a>
            </div>
        </div>
    <?php endif; ?>
    <div class="form-container">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-plus"></i> <!-- Yellow user-plus icon -->
            </div>
            <h2>Create Account</h2>
        </div>

        <form action="../backend/user/register.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                    placeholder="<?= !empty($errors['username']) ? $errors['username'] : 'Username' ?>"
                    value="<?= !empty($errors['username']) ? '' : htmlspecialchars($old['username'] ?? '') ?>"
                    class="<?= !empty($errors['username']) ? 'error' : '' ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email"
                    placeholder="<?= !empty($errors['email']) ? $errors['email'] : 'Email' ?>"
                    value="<?= !empty($errors['email']) ? '' : htmlspecialchars($old['email'] ?? '') ?>"
                    class="<?= !empty($errors['email']) ? 'error' : '' ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone"
                    placeholder="<?= !empty($errors['phone']) ? $errors['phone'] : 'Phone Number (e.g. 09123456789)' ?>"
                    value="<?= !empty($errors['phone']) ? '' : htmlspecialchars($old['phone'] ?? '') ?>"
                    class="<?= !empty($errors['phone']) ? 'error' : '' ?>" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password"
                        placeholder="<?= !empty($errors['password']) ? $errors['password'] : 'Password' ?>"
                        class="<?= !empty($errors['password']) ? 'error' : '' ?>" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">Register</button>

            <div class="form-footer">
                <p>Already have an account? <a href="user_login.php">Login</a></p>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });
    </script>
</body>

</html>