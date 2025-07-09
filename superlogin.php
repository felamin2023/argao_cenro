<?php
// State variables for error display
$showPendingModal = false;
$emailError = '';
$passwordError = '';
$emailValue = '';
// Handle login POST for status check only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  session_start();
  include 'backend/connection.php';
  $email = trim($_POST['email']);
  $password = $_POST['password'] ?? '';
  $emailValue = htmlspecialchars($email);
  // Fetch id as well
  $stmt = $conn->prepare("SELECT id, password, status, department FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $stmt->bind_result($user_id, $hashedPassword, $status, $department);
    $stmt->fetch();
    if (!password_verify($password, $hashedPassword)) {
      $passwordError = 'Incorrect password';
    } else {
      if (strtolower($status) === 'pending') {
        $showPendingModal = 'pending';
      } elseif (strtolower($status) === 'rejected') {
        $showPendingModal = 'rejected';
      } elseif (strtolower($status) === 'verified') {
        // Set session user_id
        $_SESSION['user_id'] = $user_id;
        // Redirect based on department
        switch (strtolower($department)) {
          case 'wildlife':
            header("Location: wildlife/wildhome.php");
            exit();
          case 'seedling':
            header("Location: seedlings/seedlingshome.php");
            exit();
          case 'tree cutting':
            header("Location: tree/treehome.php");
            exit();
          case 'marine':
            header("Location: marine/marinehome.php");
            exit();
          case 'cenro':
            header("Location: superhome.php");
            exit();
          default:
            // fallback if department is not recognized
            echo "<script>alert('Your account is approved, but your department is not recognized.'); window.location='superlogin.php';</script>";
            exit();
        }
      }
    }
  } else {
    $emailError = 'Email not found';
    $emailValue = '';
  }
  $stmt->close();
  $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DENR Login Page</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="/denr/superadmin/css/superlogin.css" />

</head>

<body>
  <div class="main-container">
    <div class="sidebar">
      <img src="denr.png" alt="DENR Logo" class="logo" />
      <h1>CENRO ARGAO</h1>
    </div>



    <div class="login-container" id="loginContainer">
      <h2>LOGIN</h2>


      <form method="POST" action="">
        <div class="input-container">
          <input
            type="email"
            name="email"
            placeholder="<?php echo $emailError ? $emailError : 'Email'; ?>"
            value="<?php echo $emailValue; ?>"
            class="<?php echo $emailError ? 'error' : ''; ?>"
            required />
        </div>

        <div class="input-container">
          <div class="password-wrapper">
            <input
              type="password"
              name="password"
              id="password"
              placeholder="<?php echo $passwordError ? $passwordError : 'Password'; ?>"
              class="<?php echo $passwordError ? 'error' : ''; ?>"
              required />
            <button type="button" class="toggle-password" id="togglePassword">
              <i class="fas fa-eye-slash"></i>
            </button>
          </div>
        </div>

        <p class="forgot-password"><a href="#" id="forgotPasswordLink">Forgot Password?</a></p>
        <button type="submit">LOG IN</button>
      </form>
      <!-- Pending Modal -->


      <p class="register-link">Don't have an account? <a href="superregister.php">Register</a></p>
    </div>

    <div class="forgot-password-form" id="forgotPasswordForm">
      <h2>Reset Password</h2>
      <p>Enter your email address and we'll send you a link to reset your password.</p>
      <form id="resetPasswordForm">
        <div class="input-container">
          <input type="email" id="resetEmail" placeholder="Enter your email" required />
        </div>
        <button type="submit">Reset Password</button>
      </form>
      <div class="back-to-login">
        <a href="#" id="backToLogin"><i class="fas fa-arrow-left"></i> Back to Login</a>
      </div>
    </div>

    <div id="otpModalCustom" class="modal-custom">
      <div class="modal-content-custom">
        <span class="close-custom" id="closeModalCustom">&times;</span>
        <h3>Email Verification</h3>
        <input type="text" id="otpInputCustom" maxlength="6" placeholder="Enter OTP code">
        <div style="margin-top:10px; display: flex; align-items: center; gap: 10px;">
          <button type="button" id="sendOtpBtnCustom">Send</button>
          <button type="button" id="resendOtpBtnCustom">Resend</button>
          <span id="otpMessageCustom" style="color:red; margin-left: 10px; font-size: 13px;"></span>
        </div>
      </div>
    </div>
  </div>

  <div id="pendingModal" class="modal" style="display: <?php echo $showPendingModal ? 'block' : 'none'; ?>;">
    <div class="modal-content">
      <?php if ($showPendingModal === 'pending'): ?>
        <h3>Account Pending Approval</h3>
        <p>Your account is not yet verified. You cannot log in until your registration is approved.<br>
          Please check your email regularly for updates. The system will send you a message about your account's status.</p>
      <?php elseif ($showPendingModal === 'rejected'): ?>
        <h3>Account Rejected</h3>
        <p>Your account has been rejected. You cannot log in.<br>
          Please register again to re-apply for access. If you believe this is a mistake, contact the administrator through email.</p>
      <?php endif; ?>
      <button id="closePendingModal">OK</button>
    </div>
  </div>
  <div id="resetPasswordModalCustom" class="modal-custom">
    <div class="modal-content-reset">
      <span class="close-custom" id="closeResetPasswordModalCustom">&times;</span>
      <h3>Reset Your Password</h3>
      <form id="setNewPasswordFormCustom">
        <div class="input-container">
          <input type="password" id="newPasswordCustom" placeholder="New Password" required />
        </div>
        <div class="input-container">
          <input type="password" id="confirmPasswordCustom" placeholder="Confirm Password" required />
        </div>
        <button type="submit">Reset password</button>
      </form>
      <span id="resetPasswordMessageCustom" style="color:red; margin-top:10px; text-align: center; display:block; font-size:13px;"></span>
    </div>
  </div>
  <div id="loadingScreen">
    <div class="loading-text">Loading...</div>
    <img id="loadingLogo" src="denr.png" alt="Loading Logo">
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const eyeIcon = togglePassword.querySelector('i');

      togglePassword.addEventListener('click', function() {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        eyeIcon.classList.toggle('fa-eye');
        eyeIcon.classList.toggle('fa-eye-slash');
      });

      const forgotPasswordLink = document.getElementById('forgotPasswordLink');
      const backToLogin = document.getElementById('backToLogin');
      const loginContainer = document.getElementById('loginContainer');
      const forgotPasswordForm = document.getElementById('forgotPasswordForm');
      const resetPasswordForm = document.getElementById('resetPasswordForm');
      const sidebar = document.querySelector('.sidebar');

      forgotPasswordLink.addEventListener('click', function(e) {
        e.preventDefault();
        loginContainer.classList.add('blurred');
        sidebar.classList.add('blurred');
        forgotPasswordForm.classList.add('active');
      });

      backToLogin.addEventListener('click', function(e) {
        e.preventDefault();
        loginContainer.classList.remove('blurred');
        sidebar.classList.remove('blurred');
        forgotPasswordForm.classList.remove('active');
      });

      // OTP Modal logic
      const otpModalCustom = document.getElementById('otpModalCustom');
      const closeModalCustom = document.getElementById('closeModalCustom');
      const sendOtpBtnCustom = document.getElementById('sendOtpBtnCustom');

      // Reset Password Modal logic
      const resetPasswordModalCustom = document.getElementById('resetPasswordModalCustom');
      const closeResetPasswordModalCustom = document.getElementById('closeResetPasswordModalCustom');
      const setNewPasswordFormCustom = document.getElementById('setNewPasswordFormCustom');
      const resetPasswordMessageCustom = document.getElementById('resetPasswordMessageCustom');

      resetPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('resetEmail').value;
        // Show loading screen
        document.getElementById('loadingScreen').style.display = 'block';
        fetch('reset_password_process.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=request_otp&email=${encodeURIComponent(email)}`
          })
          .then(res => res.json())
          .then(data => {
            document.getElementById('loadingScreen').style.display = 'none';
            if (data.success) {
              forgotPasswordForm.classList.remove('active');
              otpModalCustom.style.display = 'block';
              resetPasswordForm.reset();
            } else {
              alert(data.message || 'Failed to send OTP.');
            }
          })
          .catch(() => {
            document.getElementById('loadingScreen').style.display = 'none';
            alert('Network error.');
          });
      });

      closeModalCustom.addEventListener('click', function() {
        otpModalCustom.style.display = 'none';
        // Optionally, return to login or reset state
        loginContainer.classList.remove('blurred');
        sidebar.classList.remove('blurred');
      });

      // OTP validation and open reset password modal
      sendOtpBtnCustom.addEventListener('click', function() {
        const otp = document.getElementById('otpInputCustom').value;
        if (!otp || otp.length !== 6) {
          document.getElementById('otpMessageCustom').textContent = 'Enter a valid 6-digit OTP.';
          return;
        }
        document.getElementById('otpMessageCustom').textContent = '';
        document.getElementById('loadingScreen').style.display = 'block';
        fetch('reset_password_process.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=validate_otp&otp=${encodeURIComponent(otp)}`
          })
          .then(res => res.json())
          .then(data => {
            document.getElementById('loadingScreen').style.display = 'none';
            if (data.success) {
              otpModalCustom.style.display = 'none';
              resetPasswordModalCustom.style.display = 'block';
              document.getElementById('otpInputCustom').value = '';
              document.getElementById('otpMessageCustom').textContent = '';
            } else {
              document.getElementById('otpMessageCustom').textContent = data.message || 'Invalid OTP.';
            }
          })
          .catch(() => {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('otpMessageCustom').textContent = 'Network error.';
          });
      });

      closeResetPasswordModalCustom.addEventListener('click', function() {
        resetPasswordModalCustom.style.display = 'none';
        loginContainer.classList.remove('blurred');
        sidebar.classList.remove('blurred');
      });

      setNewPasswordFormCustom.addEventListener('submit', function(e) {
        e.preventDefault();
        const newPassword = document.getElementById('newPasswordCustom').value;
        const confirmPassword = document.getElementById('confirmPasswordCustom').value;
        if (newPassword.length < 6) {
          resetPasswordMessageCustom.textContent = 'Password must be at least 6 characters.';
          return;
        }
        if (newPassword !== confirmPassword) {
          resetPasswordMessageCustom.textContent = 'Passwords do not match!';
          return;
        }
        resetPasswordMessageCustom.textContent = '';
        document.getElementById('loadingScreen').style.display = 'block';
        fetch('reset_password_process.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=reset_password&newPassword=${encodeURIComponent(newPassword)}&confirmPassword=${encodeURIComponent(confirmPassword)}`
          })
          .then(res => res.json())
          .then(data => {
            document.getElementById('loadingScreen').style.display = 'none';
            if (data.success) {
              resetPasswordModalCustom.style.display = 'none';
              loginContainer.classList.remove('blurred');
              sidebar.classList.remove('blurred');
              alert('Password reset successful!');
            } else {
              resetPasswordMessageCustom.textContent = data.message || 'Failed to reset password.';
            }
          })
          .catch(() => {
            document.getElementById('loadingScreen').style.display = 'none';
            resetPasswordMessageCustom.textContent = 'Network error.';
          });
      });

      // Pending modal close logic
      const pendingModal = document.getElementById('pendingModal');
      const closePendingModal = document.getElementById('closePendingModal');
      if (pendingModal && closePendingModal) {
        closePendingModal.onclick = function() {
          pendingModal.style.display = 'none';
        };
      }
    });
  </script>
</body>

</html>