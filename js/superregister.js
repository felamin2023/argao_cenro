document.addEventListener("DOMContentLoaded", function () {
  const verifyEmailBtn = document.getElementById("verifyEmailBtn");
  const otpModal = document.getElementById("otpModal");
  const closeModal = document.getElementById("closeModal");
  const sendOtpBtn = document.getElementById("sendOtpBtn");
  const resendOtpBtn = document.getElementById("resendOtpBtn");
  const otpInput = document.getElementById("otpInput");
  const otpMessage = document.getElementById("otpMessage");
  const emailInput = document.getElementById("email");
  const phoneInput = document.getElementById("phone");
  const departmentInput = document.getElementById("department");
  const passwordInput = document.getElementById("password");
  const togglePassword = document.getElementById("togglePassword");
  const registerBtn = document.getElementById("registerBtn");
  const confirmPasswordInput = document.getElementById("confirm_password");
  const formError = document.getElementById("formError");
  const registerForm = document.getElementById("registerForm");
  const successMessage = document.getElementById("successMessage");
  const toggleConfirmPassword = document.getElementById(
    "toggleConfirmPassword"
  );

  // Password toggle
  togglePassword.addEventListener("click", function () {
    const type = passwordInput.type === "password" ? "text" : "password";
    passwordInput.type = type;
    togglePassword.querySelector("i").classList.toggle("fa-eye");
    togglePassword.querySelector("i").classList.toggle("fa-eye-slash");
  });

  // Confirm password toggle
  toggleConfirmPassword.addEventListener("click", function () {
    const type = confirmPasswordInput.type === "password" ? "text" : "password";
    confirmPasswordInput.type = type;
    toggleConfirmPassword.querySelector("i").classList.toggle("fa-eye");
    toggleConfirmPassword.querySelector("i").classList.toggle("fa-eye-slash");
  });

  function showModal() {
    otpModal.style.display = "block";
    otpInput.value = "";
    otpMessage.textContent = "";
  }

  function hideModal() {
    otpModal.style.display = "none";
  }
  verifyEmailBtn.addEventListener("click", function () {
    if (!emailInput.value) {
      emailInput.value = "";
      emailInput.placeholder = "Please enter your email first.";
      emailInput.classList.add("error");
      // Hide loading screen if shown
      if (typeof loadingScreen !== "undefined")
        loadingScreen.style.display = "none";
      return;
    }
    // Show loading screen only after passing initial validation
    if (typeof loadingScreen !== "undefined")
      loadingScreen.style.display = "flex";
    let size = 25;
    let growing = true;
    if (typeof loadingLogo !== "undefined") {
      loadingLogo.style.width = size + "px";
      loadingLogo.style.height = size + "px";
    }
    if (typeof loadingInterval !== "undefined") clearInterval(loadingInterval);
    loadingInterval = setInterval(function () {
      if (growing) {
        size += 1;
        if (size >= 40) growing = false;
      } else {
        size -= 1;
        if (size <= 25) growing = true;
      }
      if (typeof loadingLogo !== "undefined") {
        loadingLogo.style.width = size + "px";
        loadingLogo.style.height = size + "px";
      }
    }, 40);
    fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=send_otp&email=" + encodeURIComponent(emailInput.value),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          showModal();
          // Hide loading screen when modal is shown
          if (typeof loadingScreen !== "undefined")
            loadingScreen.style.display = "none";
          if (typeof loadingInterval !== "undefined")
            clearInterval(loadingInterval);
        } else if (data.pending) {
          showPendingModal(data.department);
          if (typeof loadingScreen !== "undefined")
            loadingScreen.style.display = "none";
          if (typeof loadingInterval !== "undefined")
            clearInterval(loadingInterval);
        } else if (data.error && data.error === "Email already exists.") {
          emailInput.value = "";
          emailInput.placeholder = "Email already exists.";
          emailInput.classList.add("error");
          if (typeof loadingScreen !== "undefined")
            loadingScreen.style.display = "none";
          if (typeof loadingInterval !== "undefined")
            clearInterval(loadingInterval);
        } else {
          emailInput.value = "";
          emailInput.placeholder = data.error || "Failed to send OTP.";
          emailInput.classList.add("error");
          if (typeof loadingScreen !== "undefined")
            loadingScreen.style.display = "none";
          if (typeof loadingInterval !== "undefined")
            clearInterval(loadingInterval);
        }
        // --- Pending Registration Modal ---
        function showPendingModal(department) {
          let modal = document.getElementById("pendingModal");
          if (!modal) {
            modal = document.createElement("div");
            modal.id = "pendingModal";
            modal.style.position = "fixed";
            modal.style.top = "0";
            modal.style.left = "0";
            modal.style.width = "100vw";
            modal.style.height = "100vh";
            modal.style.background = "rgba(0,0,0,0.25)";
            modal.style.zIndex = "10010";
            modal.style.display = "flex";
            modal.style.alignItems = "center";
            modal.style.justifyContent = "center";
            modal.innerHTML = `
        <div style="background:#fff; padding:32px 24px; border-radius:10px; box-shadow:0 2px 16px rgba(0,0,0,0.18); min-width:320px; max-width:90vw; text-align:center;">
          <div style="font-size:1.2rem; margin-bottom:18px; color:#222;">
            You have already registered as admin <b>${department}</b>.<br>
            We will send you an update to your email once your registration <br> is processed.
            Please keep checking your email for updates.
          </div>
          <button id="pending-ok-btn" style="background:#007bff; color:#fff; border:none; padding:10px 24px; border-radius:5px; font-size:1rem; cursor:pointer;">Okay</button>
        </div>
      `;
            document.body.appendChild(modal);
          } else {
            modal.style.display = "flex";
          }
          document.getElementById("pending-ok-btn").onclick = function () {
            modal.style.display = "none";
          };
        }
      })
      .catch(() => {
        if (typeof loadingScreen !== "undefined")
          loadingScreen.style.display = "none";
        if (typeof loadingInterval !== "undefined")
          clearInterval(loadingInterval);
      });
  });
  closeModal.addEventListener("click", hideModal);
  resendOtpBtn.addEventListener("click", function () {
    if (!emailInput.value) {
      otpMessage.textContent = "Please enter your email first.";
      return;
    }
    fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=send_otp&email=" + encodeURIComponent(emailInput.value),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          otpMessage.textContent = "OTP resent!";
        } else {
          otpMessage.textContent = "Failed to resend OTP.";
        }
      });
  });
  sendOtpBtn.addEventListener("click", function () {
    fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "action=verify_otp&otp=" + encodeURIComponent(otpInput.value),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          hideModal();
          phoneInput.disabled = false;
          departmentInput.disabled = false;
          passwordInput.disabled = false;
          confirmPasswordInput.disabled = false;
          togglePassword.disabled = false;
          toggleConfirmPassword.disabled = false;
          registerBtn.style.display = "";
          verifyEmailBtn.style.display = "none";
          otpMessage.textContent = "";
        } else {
          otpMessage.textContent = "Invalid OTP. Try again.";
        }
      });
  });
  // Removed duplicate password toggle event listener

  // AJAX registration
  registerForm.addEventListener("submit", function (e) {
    e.preventDefault();
    // Remove previous errors
    [
      emailInput,
      phoneInput,
      departmentInput,
      passwordInput,
      confirmPasswordInput,
    ].forEach((input) => {
      input.classList.remove("error");
    });
    formError.textContent = "";
    const formData = new FormData(registerForm);
    fetch("backend/admin/register.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          document.querySelector(".main-container").style.display = "none";
          successMessage.style.display = "flex";
        } else if (data.errors) {
          if (data.errors.form) {
            formError.textContent = data.errors.form;
          }
          if (data.errors.email) {
            emailInput.value = "";
            emailInput.placeholder = data.errors.email;
            emailInput.classList.add("error");
          }
          if (data.errors.phone) {
            phoneInput.value = "";
            phoneInput.placeholder = data.errors.phone;
            phoneInput.classList.add("error");
          }
          if (data.errors.department) {
            departmentInput.classList.add("error");
            departmentInput.selectedIndex = 0;
            departmentInput.options[0].text = data.errors.department;
          }
          if (data.errors.password) {
            passwordInput.value = "";
            passwordInput.placeholder = data.errors.password;
            passwordInput.classList.add("error");
          }
          if (data.errors.confirm_password) {
            confirmPasswordInput.value = "";
            confirmPasswordInput.placeholder = data.errors.confirm_password;
            confirmPasswordInput.classList.add("error");
          }
        }
      });
  });
  // Tooltip for disabled fields
  function addDisabledTooltipListeners() {
    const disabledInputs = document.querySelectorAll(
      "input:disabled, select:disabled"
    );
    disabledInputs.forEach(function (el) {
      el.addEventListener("mouseenter", function (e) {
        let tooltip = document.createElement("div");
        tooltip.textContent = "Verify your email first";
        tooltip.style.position = "fixed";
        tooltip.style.background = "rgba(0,0,0,0.85)";
        tooltip.style.color = "#fff";
        tooltip.style.padding = "6px 12px";
        tooltip.style.borderRadius = "6px";
        tooltip.style.fontSize = "13px";
        tooltip.style.zIndex = 9999;
        tooltip.style.pointerEvents = "none";
        tooltip.className = "disabled-tooltip";
        document.body.appendChild(tooltip);

        function moveTooltip(ev) {
          tooltip.style.left = ev.clientX + 12 + "px";
          tooltip.style.top = ev.clientY + 12 + "px";
        }
        moveTooltip(e);
        el.addEventListener("mousemove", moveTooltip);
        el._tooltip = tooltip;
        el._moveTooltip = moveTooltip;
      });
      el.addEventListener("mouseleave", function () {
        if (el._tooltip) {
          document.body.removeChild(el._tooltip);
          el.removeEventListener("mousemove", el._moveTooltip);
          el._tooltip = null;
          el._moveTooltip = null;
        }
      });
    });
  }
  addDisabledTooltipListeners();

  // Re-apply tooltip listeners when fields are enabled/disabled
  const observer = new MutationObserver(function () {
    addDisabledTooltipListeners();
  });
  observer.observe(document.getElementById("registerForm"), {
    subtree: true,
    attributes: true,
    attributeFilter: ["disabled"],
  });
});

// Loading animation for Verify Email is now handled in the main DOMContentLoaded block above
