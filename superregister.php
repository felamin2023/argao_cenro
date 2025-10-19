<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DENR Registration Page</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="/denr/superadmin/css/superregister.css">
  <style>
    #loadingScreen {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(0, 0, 0, .1);
      backdrop-filter: blur(3px);
      -webkit-backdrop-filter: blur(3px);
      align-items: center;
      justify-content: center;
      gap: 10px
    }

    #loadingScreen .loading-text {
      font-size: 1.2rem;
      color: #008031;
      font-weight: bold
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 10000;
      inset: 0;
      background: rgba(0, 0, 0, .4);
      padding-top: 60px
    }

    .modal-content {
      background: #fff;
      margin: 5% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 90%;
      max-width: 380px;
      border-radius: 8px
    }

    .close {
      float: right;
      font-size: 24px;
      cursor: pointer
    }

    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: 0;
      color: #666
    }

    .success-message {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .3);
      z-index: 9999;
      align-items: center;
      justify-content: center
    }

    .message-content {
      background: rgba(255, 255, 255, .95);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, .2);
      text-align: center
    }
  </style>
</head>

<body>
  <div id="loadingScreen">
    <div class="loading-text">Loading...</div>
    <img id="loadingLogo" src="denr.png" alt="Loading Logo" style="width:60px;height:60px">
  </div>

  <div class="main-container">
    <div class="sidebar">
      <img src="denr.png" alt="DENR Logo" class="logo" />
      <h1>CENRO ARGAO</h1>
    </div>

    <div class="login-container">
      <h2>REGISTER</h2>
      <form id="registerForm" action="backend/admin/register.php" method="post" autocomplete="off">
        <div class="input-group">
          <input type="email" name="email" id="email" placeholder="Email" required>
        </div>
        <div class="input-group">
          <input type="text" name="phone" id="phone" placeholder="Phone Number (e.g. 09123456789)" required disabled>
        </div>
        <div class="input-group">
          <select name="department" id="department" required disabled>
            <option value="" disabled selected>Select Department</option>
            <option value="Wildlife">Wildlife</option>
            <option value="Seedling">Seedling</option>
            <option value="Tree Cutting">Tree Cutting</option>
            <option value="Marine">Marine</option>
          </select>
        </div>
        <div class="input-group" style="position:relative;">
          <input type="password" name="password" id="password" placeholder="Password" required disabled>
          <button type="button" class="toggle-password" id="togglePassword" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div class="input-group" style="position:relative;">
          <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required disabled>
          <button type="button" class="toggle-password" id="toggleConfirmPassword" tabindex="-1"><i class="fas fa-eye-slash"></i></button>
        </div>
        <button type="button" id="verifyEmailBtn">Verify Email</button>
        <button type="submit" id="registerBtn" style="display:none;">Register</button>
      </form>
      <div id="formError" style="color:red;margin-top:10px;"></div>
      <p class="register-link">Already have an account? <a href="superlogin.php">Login</a></p>
    </div>
  </div>

  <!-- OTP Modal -->
  <div id="otpModal" class="modal">
    <div class="modal-content">
      <span class="close" id="closeModal">&times;</span>
      <h3>Email Verification</h3>
      <input type="text" id="otpInput" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="Enter OTP code">
      <div style="margin-top:10px; display:flex; align-items:center; gap:10px;">
        <button type="button" id="sendOtpBtn">Send</button>
        <button type="button" id="resendOtpBtn">Resend</button>
        <span id="otpMessage" style="color:red; margin-left:10px; font-size:13px;"></span>
      </div>
    </div>
  </div>

  <div class="success-message" id="successMessage">
    <div class="message-content">
      <h1>Form Submitted!</h1>
      <p>
        You will not be able to log in until your registration is approved.<br>
        We’ll email you updates—please check your inbox regularly.
      </p>
      <a href="superregister.php" style="display:inline-block;padding:7px 20px;background:#008031;color:#fff;border-radius:6px;text-decoration:none;">OKAY</a>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const loading = $('loadingScreen');
    const showLoading = () => loading.style.display = 'flex';
    const hideLoading = () => loading.style.display = 'none';

    const emailInput = $('email');
    const phoneInput = $('phone');
    const deptInput = $('department');
    const passInput = $('password');
    const cpassInput = $('confirm_password');
    const registerBtn = $('registerBtn');
    const verifyEmailBtn = $('verifyEmailBtn');
    const formError = $('formError');

    const otpModal = $('otpModal');
    const closeModal = $('closeModal');
    const otpInput = $('otpInput');
    const sendOtpBtn = $('sendOtpBtn');
    const resendOtpBtn = $('resendOtpBtn');
    const otpMessage = $('otpMessage');
    const successMessage = $('successMessage');

    function togglePassword(inputId, btnId) {
      const input = $(inputId),
        btn = $(btnId);
      btn.addEventListener('click', () => {
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        btn.innerHTML = type === 'password' ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
      });
    }
    togglePassword('password', 'togglePassword');
    togglePassword('confirm_password', 'toggleConfirmPassword');

    const isValidEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);

    verifyEmailBtn.addEventListener('click', async () => {
      formError.textContent = '';
      const email = emailInput.value.trim();
      if (!email) return formError.textContent = 'Please enter your email.';
      if (!isValidEmail(email)) return formError.textContent = 'Please enter a valid email address.';

      verifyEmailBtn.disabled = true;
      showLoading();
      try {
        const fd = new FormData();
        fd.append('action', 'send_otp');
        fd.append('email', email);
        const res = await fetch('backend/admin/register.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await res.json();
        hideLoading();
        verifyEmailBtn.disabled = false;

        if (data.pending) {
          formError.textContent = `Your registration is pending (Department: ${data.department}).`;
          return;
        }
        if (!data.success) {
          formError.textContent = data.error || 'Email verification failed.';
          return;
        }
        if (data.otp) console.log('OTP (testing):', data.otp);

        otpModal.style.display = 'block';
        otpInput.focus();
      } catch {
        hideLoading();
        verifyEmailBtn.disabled = false;
        formError.textContent = 'Network error.';
      }
    });

    closeModal.addEventListener('click', () => otpModal.style.display = 'none');

    sendOtpBtn.addEventListener('click', async () => {
      otpMessage.textContent = '';
      const code = otpInput.value.trim();
      if (!/^\d{6}$/.test(code)) {
        otpMessage.textContent = 'Please enter a valid 6-digit OTP.';
        return;
      }
      showLoading();
      try {
        const fd = new FormData();
        fd.append('action', 'verify_otp');
        fd.append('otp', code);
        const res = await fetch('backend/admin/register.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
          otpModal.style.display = 'none';
          phoneInput.disabled = false;
          deptInput.disabled = false;
          passInput.disabled = false;
          cpassInput.disabled = false;
          registerBtn.style.display = '';
          verifyEmailBtn.style.display = 'none';
          formError.textContent = '';
        } else {
          otpMessage.textContent = data.error || 'Incorrect OTP.';
        }
      } catch {
        hideLoading();
        otpMessage.textContent = 'Network error.';
      }
    });

    resendOtpBtn.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      if (!email) return;
      showLoading();
      try {
        const fd = new FormData();
        fd.append('action', 'send_otp');
        fd.append('email', email);
        const res = await fetch('backend/admin/register.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await res.json();
        hideLoading();
        if (data.success) {
          otpMessage.textContent = 'OTP resent! Check your email.';
          if (data.otp) console.log('Resent OTP (testing):', data.otp);
          otpInput.value = '';
          otpInput.focus();
        } else {
          otpMessage.textContent = data.error || 'Failed to resend OTP.';
        }
      } catch {
        hideLoading();
        otpMessage.textContent = 'Network error.';
      }
    });

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      formError.textContent = '';

      const email = emailInput.value.trim();
      const phone = phoneInput.value.trim();
      const dept = deptInput.value;
      const pass = passInput.value;
      const cpass = cpassInput.value;

      if (!email || !phone || !dept || !pass || !cpass) {
        formError.textContent = 'Please fill out all fields.';
        return;
      }
      if (!/^09\d{9}$/.test(phone)) return formError.textContent = 'Please enter a valid phone number (e.g., 09123456789).';
      if (pass !== cpass) return formError.textContent = 'Passwords do not match.';
      if (pass.length < 8) return formError.textContent = 'Password must be at least 8 characters.';

      showLoading();
      try {
        const fd = new FormData(e.target);
        fd.append('action', 'register');
        const res = await fetch('backend/admin/register.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await res.json();
        hideLoading();

        if (data.success) {
          successMessage.style.display = 'flex';
        } else {
          if (data.reason === 'phone') formError.textContent = 'Phone number already exists.';
          else if (data.reason === 'email') formError.textContent = 'Email already exist';
          else formError.textContent = data.error || 'Registration failed.';
          if (data.debug_dup) console.log('dup-check:', data.debug_dup);
        }
      } catch {
        hideLoading();
        formError.textContent = 'Network error.';
      }
    });
  </script>
</body>

</html>