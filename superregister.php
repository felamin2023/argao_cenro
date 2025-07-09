<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

if (isset($_POST['action']) && $_POST['action'] === 'send_otp' && isset($_POST['email'])) {
  $email = $_POST['email'];

  include 'backend/connection.php';
  $stmt = $conn->prepare("SELECT id, status, department FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($existing_id, $existing_status, $existing_department);
  if ($stmt->num_rows > 0) {
    $stmt->fetch();
    $status = strtolower($existing_status);
    if ($status === 'pending') {
      // Show modal for pending registration
      echo json_encode([
        'success' => false,
        'pending' => true,
        'department' => $existing_department
      ]);
      exit();
    } elseif ($status === 'verified') {
      // Show error for verified
      echo json_encode([
        'success' => false,
        'error' => 'Email already exists.'
      ]);
      exit();
    } elseif ($status === 'rejected') {
      // Allow to continue registration (send OTP)
    } else {
      // Unknown status, treat as exists
      echo json_encode([
        'success' => false,
        'error' => 'Email already exists.'
      ]);
      exit();
    }
  }
  $stmt->close();

  $otp = rand(100000, 999999);
  $_SESSION['email_otp'] = $otp;
  $_SESSION['email_otp_to'] = $email;

  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'argaocenro@gmail.com';
    $mail->Password   = 'rlqh eihc lyoa etbl';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('argaocenro@gmail.com', 'DENR System');
    $mail->addAddress($email);

    $mail->isHTML(false);
    $mail->Subject = 'Your DENR Registration OTP Code';
    $mail->Body    = "Your OTP code is: $otp";

    $mail->send();
    // Send notification to Cenro after successful registration
    // Find Cenro user(s)
    $cenroStmt = $conn->prepare("SELECT email FROM users WHERE LOWER(department) = 'cenro'");
    $cenroStmt->execute();
    $cenroStmt->bind_result($cenroEmail);
    $cenroEmails = [];
    while ($cenroStmt->fetch()) {
      $cenroEmails[] = $cenroEmail;
    }
    $cenroStmt->close();

    if (!empty($cenroEmails)) {
      $notifyMail = new PHPMailer(true);
      try {
        $notifyMail->isSMTP();
        $notifyMail->Host       = 'smtp.gmail.com';
        $notifyMail->SMTPAuth   = true;
        $notifyMail->Username   = 'argaocenro@gmail.com';
        $notifyMail->Password   = 'rlqh eihc lyoa etbl';
        $notifyMail->SMTPSecure = 'tls';
        $notifyMail->Port       = 587;
        $notifyMail->setFrom('argaocenro@gmail.com', 'DENR System');
        foreach ($cenroEmails as $cEmail) {
          $notifyMail->addAddress($cEmail);
        }
        $notifyMail->isHTML(true);
        $notifyMail->Subject = 'New Registration Pending Verification';
        $notifyMail->Body    = '<p>Hello CENRO Admin,</p>'
          . '<p>There is a new registration that needs your verification and approval.</p>'
          . '<p>Please log in to the DENR system to review and verify this registration.</p>'
          . '<p><a href="http://' . $_SERVER['HTTP_HOST'] . '/denr/superadmin/superlogin.php">Open DENR Admin Site</a></p>';
        $notifyMail->send();
      } catch (Exception $e) {
        // Optionally log error, but do not block registration
      }
    }
    echo json_encode(['success' => true]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
  }
  exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'verify_otp' && isset($_POST['otp'])) {
  if ($_POST['otp'] == ($_SESSION['email_otp'] ?? '')) {
    $_SESSION['email_verified'] = true;
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false]);
  }
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DENR Registration Page</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="/denr/superadmin/css/superregister.css">


  <style>

  </style>
</head>

<body>
  <div id="loadingScreen">
    <div class="loading-text">Loading...</div>
    <img id="loadingLogo" src="denr.png" alt="Loading Logo">
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
          <button type="button" class="toggle-password" id="togglePassword" tabindex="-1">
            <i class="fas fa-eye-slash"></i>
          </button>
        </div>
        <div class="input-group" style="position:relative;">
          <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required disabled>
          <button type="button" class="toggle-password" id="toggleConfirmPassword" tabindex="-1">
            <i class="fas fa-eye-slash"></i>
          </button>
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
      <h1>Form Submitted!</h1>
      <p>
        You will not be able to log in until your registration is approved. We will <br> send you an update via email, please make sure to check your email <br> regularly for updates.
      </p>
      <a href="superregister.php">OKAY</a>
    </div>
  </div>
  <script src="/denr/superadmin/js/superregister.js"></script>
</body>

</html>