document.addEventListener("DOMContentLoaded", function () {
  const mobileToggle = document.querySelector(".mobile-toggle");
  const navContainer = document.querySelector(".nav-container");

  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      navContainer.classList.toggle("active");
    });
  }

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

  const updateBtn = document.getElementById("update-profile-btn");
  const profileForm = document.getElementById("profile-form");
  const confirmModal = document.getElementById("profile-confirm-modal");
  const infoModal = document.getElementById("profile-info-modal");
  const confirmBtn = document.getElementById("confirm-profile-update-btn");
  const cancelBtn = document.getElementById("cancel-profile-update-btn");
  const closeInfoBtn = document.getElementById("close-info-modal-btn");
  const notification = document.getElementById("profile-notification");
  let pendingFormData = null;

  const originalEmail = "<?php echo $email; ?>";
  let emailChanged = false;

  document.getElementById("email").addEventListener("input", function (e) {
    emailChanged =
      e.target.value.trim().toLowerCase() !==
      originalEmail.trim().toLowerCase();
  });

  profileForm.addEventListener("submit", function (e) {
    e.preventDefault();
    if (
      (passwordInput.value || confirmPasswordInput.value) &&
      !validatePasswords()
    ) {
      return false;
    }
    pendingFormData = new FormData(profileForm);
    confirmModal.style.display = "flex";
  });

  cancelBtn.onclick = function () {
    confirmModal.style.display = "none";
    pendingFormData = null;
  };

  confirmBtn.onclick = async function () {
    if (!pendingFormData) return;

    try {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing...';

      if (emailChanged) {
        pendingFormData.append("request_otp", "1");
      }

      const response = await fetch(
        "../backend/admins/admins_profile_request.php",
        {
          method: "POST",
          body: pendingFormData,
        }
      );

      const data = await response.json();

      if (data.error && data.error.includes("already have a pending")) {
        showNotification(data.error, "error");
        confirmModal.style.display = "none";
        return;
      }

      if (data.otp_required) {
        showOtpModal();
        return;
      }

      if (data.success) {
        infoModal.style.display = "flex";
      } else {
        showNotification(data.error || "Update failed", "error");
      }
    } catch (error) {
      showNotification("Network error occurred. Please try again.", "error");
    } finally {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fas fa-save"></i> Confirm Update';
      confirmModal.style.display = "none";
    }
  };

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
    const otpInput = document.getElementById("otpInput").value;
    if (!otpInput || otpInput.length !== 6) {
      document.getElementById("otpMessage").textContent =
        "Please enter a valid 6-digit OTP";
      return;
    }
    const profileForm = document.getElementById("profile-form");
    const verifyFormData = new FormData(profileForm);
    verifyFormData.append("otp_code", otpInput);

    try {
      const response = await fetch(
        "../backend/admins/admins_profile_request.php",
        {
          method: "POST",
          body: verifyFormData,
        }
      );

      const data = await response.json();

      if (data.success) {
        hideOtpModal();
        infoModal.style.display = "flex";
      } else {
        document.getElementById("otpMessage").textContent =
          data.error || "Invalid OTP";
      }
    } catch (error) {
      document.getElementById("otpMessage").textContent =
        "Verification failed. Please try again.";
    }
  }

  async function resendOtp() {
    const resendBtn = document.getElementById("resendOtpBtn");
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    try {
      const email = document.getElementById("email").value;
      const formData = new FormData();
      formData.append("email", email);
      formData.append("request_otp", "1");

      const response = await fetch(
        "../backend/admins/admins_profile_request.php",
        {
          method: "POST",
          body: formData,
        }
      );

      const data = await response.json();

      if (data.otp_required) {
        document.getElementById("otpMessage").textContent =
          "New OTP sent to your email";
      } else {
        document.getElementById("otpMessage").textContent =
          data.error || "Failed to resend OTP";
      }
    } catch (error) {
      document.getElementById("otpMessage").textContent =
        "Failed to resend OTP";
    } finally {
      resendBtn.disabled = false;
      resendBtn.innerHTML = "Resend";
    }
  }

  closeInfoBtn.onclick = function () {
    infoModal.style.display = "none";
    notification.textContent = "Profile update request sent!";
    notification.style.display = "block";
    notification.style.opacity = "1";
    setTimeout(() => {
      notification.style.opacity = "0";
      setTimeout(() => {
        notification.style.display = "none";
        location.reload();
      }, 400);
    }, 1500);
  };

  function showNotification(message, type = "success") {
    const notification = document.getElementById("profile-notification");
    notification.textContent = message;
    notification.style.display = "block";
    if (
      type === "error" ||
      (message && message.toLowerCase().includes("pending"))
    ) {
      notification.style.backgroundColor = "#ff4444";
    } else {
      notification.style.backgroundColor = "#323232";
    }
    notification.style.opacity = "1";
    setTimeout(() => {
      notification.style.opacity = "0";
      setTimeout(() => {
        notification.style.display = "none";
      }, 400);
    }, 3000);
  }
});
