document.addEventListener("DOMContentLoaded", function () {
  // Mobile menu toggle
  const mobileToggle = document.querySelector(".mobile-toggle");
  const navContainer = document.querySelector(".nav-container");

  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      navContainer.classList.toggle("active");
    });
  }

  // Dropdown menus
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".nav-icon");
    const menu = dropdown.querySelector(".dropdown-menu");

    dropdown.addEventListener("mouseenter", () => {
      menu.style.opacity = "1";
      menu.style.visibility = "visible";
      menu.style.transform = menu.classList.contains("center")
        ? "translateX(-50%) translateY(0)"
        : "translateY(0)";
    });

    dropdown.addEventListener("mouseleave", (e) => {
      if (!dropdown.contains(e.relatedTarget)) {
        menu.style.opacity = "0";
        menu.style.visibility = "hidden";
        menu.style.transform = menu.classList.contains("center")
          ? "translateX(-50%) translateY(10px)"
          : "translateY(10px)";
      }
    });

    menu.addEventListener("mouseleave", (e) => {
      if (!dropdown.contains(e.relatedTarget)) {
        menu.style.opacity = "0";
        menu.style.visibility = "hidden";
        menu.style.transform = menu.classList.contains("center")
          ? "translateX(-50%) translateY(10px)"
          : "translateY(10px)";
      }
    });
  });

  // Click outside dropdowns
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".dropdown")) {
      document.querySelectorAll(".dropdown-menu").forEach((menu) => {
        menu.style.opacity = "0";
        menu.style.visibility = "hidden";
        menu.style.transform = menu.classList.contains("center")
          ? "translateX(-50%) translateY(10px)"
          : "translateY(10px)";
      });
    }
  });

  // Mobile dropdowns
  if (window.innerWidth <= 992) {
    dropdowns.forEach((dropdown) => {
      const toggle = dropdown.querySelector(".nav-icon");
      const menu = dropdown.querySelector(".dropdown-menu");

      toggle.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        document.querySelectorAll(".dropdown-menu").forEach((otherMenu) => {
          if (otherMenu !== menu) {
            otherMenu.style.display = "none";
          }
        });

        if (menu.style.display === "block") {
          menu.style.display = "none";
        } else {
          menu.style.display = "block";
        }
      });
    });
  }

  // Mark all notifications as read
  const markAllRead = document.querySelector(".mark-all-read");
  if (markAllRead) {
    markAllRead.addEventListener("click", function (e) {
      e.preventDefault();
      document.querySelectorAll(".notification-item.unread").forEach((item) => {
        item.classList.remove("unread");
      });
      document.querySelector(".badge").style.display = "none";
    });
  }

  // Profile image upload
  document
    .getElementById("profile-upload-input")
    .addEventListener("change", function (e) {
      if (e.target.files.length > 0) {
        const file = e.target.files[0];
        const reader = new FileReader();

        reader.onload = function (event) {
          const profilePic = document.getElementById("profile-picture");
          const placeholder = document.getElementById("profile-placeholder");

          profilePic.src = event.target.result;
          profilePic.style.display = "block";
          placeholder.style.display = "none";
        };

        reader.readAsDataURL(file);
      }
    });

  // Password validation
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm-password");
  const passwordError = document.getElementById("password-error");

  function validatePasswords() {
    if (passwordInput.value !== confirmPasswordInput.value) {
      confirmPasswordInput.style.borderColor = "red";
      passwordError.style.display = "block";
      return false;
    } else {
      confirmPasswordInput.style.borderColor = "";
      passwordError.style.display = "none";
      return true;
    }
  }

  confirmPasswordInput.addEventListener("input", validatePasswords);
  passwordInput.addEventListener("input", validatePasswords);

  // Profile form submission
  const profileForm = document.getElementById("profile-form");
  const notification = document.getElementById("profile-notification");
  const originalEmail = "<?php echo $email; ?>";
  let emailChanged = false;

  document.getElementById("email").addEventListener("input", function (e) {
    emailChanged =
      e.target.value.trim().toLowerCase() !==
      originalEmail.trim().toLowerCase();
  });

  profileForm.addEventListener("submit", function (e) {
    e.preventDefault();

    // Validate passwords if they're being changed
    if (
      (passwordInput.value || confirmPasswordInput.value) &&
      !validatePasswords()
    ) {
      return false;
    }

    // Show confirmation modal instead of submitting
    document.getElementById("profile-confirm-modal").style.display = "flex";
  });

  // Add event listeners for the confirmation modal buttons
  document
    .getElementById("confirm-profile-update-btn")
    .addEventListener("click", async function () {
      try {
        const formData = new FormData(profileForm);

        if (emailChanged) {
          formData.append("request_otp", "1");
        }

        const response = await fetch("../backend/users/update_profile.php", {
          method: "POST",
          body: formData,
        });

        const data = await response.json();

        // Hide the confirmation modal
        document.getElementById("profile-confirm-modal").style.display = "none";

        if (data.otp_required) {
          showOtpModal();
          return;
        }

        if (data.success) {
          showNotification("Profile updated successfully!", "success");
          setTimeout(() => location.reload(), 1500);
        } else {
          showNotification(data.error || "Update failed", "error");
        }
      } catch (error) {
        showNotification("Network error occurred. Please try again.", "error");
      }
    });

  document
    .getElementById("cancel-profile-update-btn")
    .addEventListener("click", function () {
      document.getElementById("profile-confirm-modal").style.display = "none";
    });

  // OTP Modal functions
  function showOtpModal() {
    document.getElementById("otpModal").style.display = "block";
    document.getElementById("otpMessage").textContent = "";
  }

  function hideOtpModal() {
    document.getElementById("otpModal").style.display = "none";
  }

  document.getElementById("sendOtpBtn").addEventListener("click", verifyOtp);
  document.getElementById("resendOtpBtn").addEventListener("click", resendOtp);
  document.getElementById("closeModal").addEventListener("click", hideOtpModal);

  async function verifyOtp() {
    const otpInput = document.getElementById("otpInput");
    const otpMessage = document.getElementById("otpMessage");
    const otpValue = otpInput.value.trim();

    if (!otpValue || otpValue.length !== 6 || !/^\d+$/.test(otpValue)) {
      otpMessage.textContent = "Please enter a valid 6-digit code";
      otpMessage.style.color = "red";
      return;
    }

    try {
      const formData = new FormData(document.getElementById("profile-form"));
      formData.append("otp_code", otpValue);

      const response = await fetch("../backend/users/update_profile.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        hideOtpModal();
        showNotification("Profile updated successfully!", "success");
        setTimeout(() => location.reload(), 1500);
      } else {
        otpMessage.textContent = data.error || "Invalid verification code";
        otpMessage.style.color = "red";
        otpInput.value = ""; // Clear the input
        otpInput.focus(); // Focus back to input
      }
    } catch (error) {
      otpMessage.textContent = "Verification failed. Please try again.";
      otpMessage.style.color = "red";
    }
  }

  async function resendOtp() {
    const resendBtn = document.getElementById("resendOtpBtn");
    const otpMessage = document.getElementById("otpMessage");

    // Disable button during processing
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
      // Get current form data
      const formData = new FormData(document.getElementById("profile-form"));

      // Add resend flag
      formData.append("request_otp", "1");
      formData.append("resend", "true"); // Add resend flag

      const response = await fetch("../backend/users/update_profile.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.otp_required) {
        otpMessage.textContent = "New verification code sent!";
        otpMessage.style.color = "green";

        // Reset the OTP input field
        document.getElementById("otpInput").value = "";

        // Update the OTP sent time in session
        if (data.new_otp_sent) {
          // If backend returns new timestamp (you'll need to modify backend to return this)
          // This assumes your backend returns the new timestamp
          // If not, you can just use the current time
          const newSentTime =
            data.new_otp_sent || Math.floor(Date.now() / 1000);
          // You would need to store this in your session
        }
      } else {
        otpMessage.textContent = data.error || "Failed to resend code";
        otpMessage.style.color = "red";
      }
    } catch (error) {
      otpMessage.textContent = "Failed to resend. Please try again.";
      otpMessage.style.color = "red";
      console.error("Resend OTP error:", error);
    } finally {
      // Re-enable button after 30 seconds to prevent spamming
      setTimeout(() => {
        resendBtn.disabled = false;
        resendBtn.innerHTML = "Resend";
      }, 30000); // 30 second cooldown

      // Show remaining time
      let secondsLeft = 30;
      const countdown = setInterval(() => {
        secondsLeft--;
        resendBtn.innerHTML = `Resend (${secondsLeft}s)`;
        if (secondsLeft <= 0) {
          clearInterval(countdown);
          resendBtn.innerHTML = "Resend";
        }
      }, 1000);
    }
  }

  // Notification function
  function showNotification(message, type = "success") {
    notification.textContent = message;
    notification.style.display = "block";
    notification.style.backgroundColor =
      type === "error" ? "#ff4444" : "#323232";
    notification.style.opacity = "1";

    setTimeout(() => {
      notification.style.opacity = "0";
      setTimeout(() => {
        notification.style.display = "none";
      }, 400);
    }, 3000);
  }

  const updateBtn = document.getElementById("updateBtn");
  const updateModal = document.getElementById("updateModal");
  const closeBtn = document.querySelector(".close");

  if (updateBtn && updateModal && closeBtn) {
    updateBtn.addEventListener("click", function () {
      updateModal.style.display = "block";
    });

    closeBtn.addEventListener("click", function () {
      updateModal.style.display = "none";
    });

    window.addEventListener("click", function (event) {
      if (event.target === updateModal) {
        updateModal.style.display = "none";
      }
    });
  }
});
