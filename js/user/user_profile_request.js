document.addEventListener("DOMContentLoaded", function () {
  // ---------- Loader (auto-create if missing) ----------
  function ensureLoader() {
    let el = document.getElementById("global-loading");
    if (!el) {
      el = document.createElement("div");
      el.id = "global-loading";
      el.style.cssText =
        "position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:100000";
      el.innerHTML = `
        <div class="box" style="background:#fff;padding:18px 20px;border-radius:10px;min-width:220px;max-width:90vw;text-align:center;box-shadow:0 6px 24px rgba(0,0,0,.2);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif">
          <div class="spinner" style="width:28px;height:28px;border:3px solid #e5e7eb;border-top-color:#1a8cff;border-radius:50%;margin:0 auto 10px;animation:spin 1s linear infinite"></div>
          <div id="global-loading-text">Please wait…</div>
        </div>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
      `;
      document.body.appendChild(el);
    }
    return el;
  }
  const loadingEl = ensureLoader();
  const loadingTextEl = document.getElementById("global-loading-text");
  const body = document.body;

  function showLoading(text = "Please wait…") {
    if (loadingTextEl) loadingTextEl.textContent = text;
    loadingEl.style.display = "flex";
    body.classList.add("is-busy");
  }
  function hideLoading() {
    loadingEl.style.display = "none";
    body.classList.remove("is-busy");
  }
  // Make sure the loader actually paints before heavy/await work
  const flushPaint = () =>
    new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
  const MIN_VISIBLE_MS = 600; // tiny minimum so you can SEE it

  // ---------- (rest of your existing code, unchanged) ----------
  const mobileToggle = document.querySelector(".mobile-toggle");
  const navContainer = document.querySelector(".nav-container");
  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      navContainer?.classList.toggle("active");
    });
  }

  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach((dropdown) => {
    const menu = dropdown.querySelector(".dropdown-menu");
    dropdown.addEventListener("mouseenter", () => {
      if (!menu) return;
      menu.style.opacity = "1";
      menu.style.visibility = "visible";
      menu.style.transform = menu.classList.contains("center")
        ? "translateX(-50%) translateY(0)"
        : "translateY(0)";
    });
    dropdown.addEventListener("mouseleave", (e) => {
      if (!menu) return;
      if (!dropdown.contains(e.relatedTarget)) {
        menu.style.opacity = "0";
        menu.style.visibility = "hidden";
        menu.style.transform = menu.classList.contains("center")
          ? "translateX(-50%) translateY(10px)"
          : "translateY(10px)";
      }
    });
    menu?.addEventListener("mouseleave", (e) => {
      if (!menu) return;
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
      toggle?.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        document.querySelectorAll(".dropdown-menu").forEach((otherMenu) => {
          if (otherMenu !== menu) otherMenu.style.display = "none";
        });
        if (menu) {
          menu.style.display =
            menu.style.display === "block" ? "none" : "block";
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
      const badge = document.querySelector(".badge");
      if (badge) badge.style.display = "none";
    });
  }

  const uploadInput = document.getElementById("profile-upload-input");
  uploadInput?.addEventListener("change", function (e) {
    if (e.target.files.length > 0) {
      const file = e.target.files[0];
      const reader = new FileReader();
      reader.onload = function (event) {
        const profilePic = document.getElementById("profile-picture");
        const placeholder = document.getElementById("profile-placeholder");
        if (profilePic) {
          profilePic.src = event.target.result;
          profilePic.style.display = "block";
        }
        if (placeholder) placeholder.style.display = "none";
      };
      reader.readAsDataURL(file);
    }
  });

  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm-password");
  const passwordError = document.getElementById("password-error");
  function validatePasswords() {
    if ((passwordInput?.value || "") !== (confirmPasswordInput?.value || "")) {
      if (confirmPasswordInput) confirmPasswordInput.style.borderColor = "red";
      if (passwordError) passwordError.style.display = "block";
      return false;
    } else {
      if (confirmPasswordInput) confirmPasswordInput.style.borderColor = "";
      if (passwordError) passwordError.style.display = "none";
      return true;
    }
  }
  confirmPasswordInput?.addEventListener("input", validatePasswords);
  passwordInput?.addEventListener("input", validatePasswords);

  const notification = document.getElementById("profile-notification");
  function showNotification(message, type = "success") {
    if (!notification) return;
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

  const profileForm = document.getElementById("profile-form");
  function setFormDisabled(disabled) {
    if (!profileForm) return;
    Array.from(profileForm.elements).forEach((el) => {
      if (el && typeof el.disabled !== "undefined") el.disabled = disabled;
    });
  }

  const emailInput = document.getElementById("email");
  const originalEmail = (emailInput?.dataset.originalEmail || "")
    .trim()
    .toLowerCase();
  let emailChanged = false;
  emailInput?.addEventListener("input", function (e) {
    emailChanged =
      (e.target.value || "").trim().toLowerCase() !== originalEmail;
  });

  const passwordOk = () => {
    if (!passwordInput?.value && !confirmPasswordInput?.value) return true;
    return validatePasswords();
  };

  profileForm?.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!passwordOk()) return;
    document.getElementById("profile-confirm-modal").style.display = "flex";
  });

  document
    .getElementById("cancel-profile-update-btn")
    ?.addEventListener("click", function () {
      document.getElementById("profile-confirm-modal").style.display = "none";
    });

  // ---------- THE IMPORTANT FIX: ensure loader shows on Confirm ----------
  document
    .getElementById("confirm-profile-update-btn")
    ?.addEventListener("click", async function () {
      const start = performance.now();
      try {
        showLoading("Saving profile…");
        await flushPaint(); // <-- force paint so spinner appears
        setFormDisabled(true);

        const formData = new FormData(profileForm);
        if (emailChanged) formData.append("request_otp", "1");

        const response = await fetch("../backend/users/update_profile.php", {
          method: "POST",
          body: formData,
        });
        const data = await response.json();

        document.getElementById("profile-confirm-modal").style.display = "none";

        if (data.otp_required) {
          if (data.otp_debug) {
            console.log(
              "%c[DEBUG OTP] %c" + data.otp_debug,
              "color:#888",
              "color:#1a8cff;font-weight:bold"
            );
          }
          showOtpModal();
          return;
        }

        if (data.success) {
          showNotification("Profile updated successfully!", "success");
          setTimeout(() => location.reload(), 1200);
        } else {
          showNotification(data.error || "Update failed", "error");
        }
      } catch (err) {
        showNotification("Network error occurred. Please try again.", "error");
      } finally {
        const elapsed = performance.now() - start;
        const waitMore = Math.max(0, MIN_VISIBLE_MS - elapsed);
        setTimeout(() => {
          hideLoading();
          setFormDisabled(false);
        }, waitMore);
      }
    });

  // ---------- OTP modal + actions ----------
  function showOtpModal() {
    document.getElementById("otpModal").style.display = "block";
    const otpMsg = document.getElementById("otpMessage");
    if (otpMsg) {
      otpMsg.textContent = "";
      otpMsg.style.color = "red";
    }
  }
  function hideOtpModal() {
    document.getElementById("otpModal").style.display = "none";
  }
  document
    .getElementById("closeModal")
    ?.addEventListener("click", hideOtpModal);

  document.getElementById("sendOtpBtn")?.addEventListener("click", verifyOtp);
  async function verifyOtp() {
    const otpInput = document.getElementById("otpInput");
    const otpMessage = document.getElementById("otpMessage");
    const code = (otpInput?.value || "").trim();

    if (!/^\d{6}$/.test(code)) {
      otpMessage.textContent = "Please enter a valid 6-digit code";
      otpMessage.style.color = "red";
      return;
    }

    const start = performance.now();
    try {
      showLoading("Verifying code…");
      await flushPaint();
      setFormDisabled(true);

      const formData = new FormData(profileForm);
      formData.append("otp_code", code);

      const response = await fetch("../backend/users/update_profile.php", {
        method: "POST",
        body: formData,
      });
      const data = await response.json();

      if (data.success) {
        hideOtpModal();
        showNotification("Profile updated successfully!", "success");
        setTimeout(() => location.reload(), 1200);
      } else {
        otpMessage.textContent = data.error || "Invalid verification code";
        otpMessage.style.color = "red";
        if (otpInput) {
          otpInput.value = "";
          otpInput.focus();
        }
      }
    } catch (err) {
      otpMessage.textContent = "Verification failed. Please try again.";
      otpMessage.style.color = "red";
    } finally {
      const elapsed = performance.now() - start;
      const waitMore = Math.max(0, MIN_VISIBLE_MS - elapsed);
      setTimeout(() => {
        hideLoading();
        setFormDisabled(false);
      }, waitMore);
    }
  }

  document.getElementById("resendOtpBtn")?.addEventListener("click", resendOtp);
  async function resendOtp() {
    const resendBtn = document.getElementById("resendOtpBtn");
    const otpMessage = document.getElementById("otpMessage");

    resendBtn.disabled = true;

    const start = performance.now();
    try {
      showLoading("Sending verification code…");
      await flushPaint();

      const formData = new FormData(profileForm);
      formData.append("request_otp", "1");
      formData.append("resend", "true");

      const response = await fetch("../backend/users/update_profile.php", {
        method: "POST",
        body: formData,
      });
      const data = await response.json();

      if (data.otp_required) {
        otpMessage.textContent = "New verification code sent!";
        otpMessage.style.color = "green";
        const otpInput = document.getElementById("otpInput");
        if (otpInput) otpInput.value = "";
        if (data.otp_debug) {
          console.log(
            "%c[DEBUG OTP] %c" + data.otp_debug,
            "color:#888",
            "color:#1a8cff;font-weight:bold"
          );
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
      const elapsed = performance.now() - start;
      const waitMore = Math.max(0, MIN_VISIBLE_MS - elapsed);
      setTimeout(() => {
        hideLoading();
        // 30s cooldown with countdown
        let secondsLeft = 30;
        const tick = () => {
          resendBtn.innerHTML = `Resend (${secondsLeft}s)`;
          if (secondsLeft <= 0) {
            resendBtn.disabled = false;
            resendBtn.innerHTML = "Resend";
          } else {
            secondsLeft--;
            setTimeout(tick, 1000);
          }
        };
        tick();
      }, waitMore);
    }
  }

  // Legacy modal hooks (no-op if absent)
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
      if (event.target === updateModal) updateModal.style.display = "none";
    });
  }
});
