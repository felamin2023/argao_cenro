document.addEventListener("DOMContentLoaded", function () {
  // Mobile menu toggle
  const mobileToggle = document.querySelector(".mobile-toggle");
  const navContainer = document.querySelector(".nav-container");

  if (mobileToggle) {
    mobileToggle.addEventListener("click", () => {
      navContainer.classList.toggle("active");
    });
  }

  // Improved dropdown functionality
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".nav-icon");
    const menu = dropdown.querySelector(".dropdown-menu");

    // Show menu on hover
    dropdown.addEventListener("mouseenter", () => {
      menu.style.opacity = "1";
      menu.style.visibility = "visible";
      menu.style.transform = menu.classList.contains("center")
        ? "translateX(-50%) translateY(0)"
        : "translateY(0)";
    });

    // Hide menu when leaving both button and menu
    dropdown.addEventListener("mouseleave", (e) => {
      // Check if we're leaving the entire dropdown area
      if (!dropdown.contains(e.relatedTarget)) {
        menu.style.opacity = "0";
        menu.style.visibility = "hidden";
        menu.style.transform = menu.classList.contains("center")
          ? "translateX(-50%) translateY(10px)"
          : "translateY(10px)";
      }
    });

    // Additional check for menu mouseleave
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

  // Close dropdowns when clicking outside (for mobile)
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

  // Mobile dropdown toggle
  if (window.innerWidth <= 992) {
    dropdowns.forEach((dropdown) => {
      const toggle = dropdown.querySelector(".nav-icon");
      const menu = dropdown.querySelector(".dropdown-menu");

      toggle.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Close other dropdowns
        document.querySelectorAll(".dropdown-menu").forEach((otherMenu) => {
          if (otherMenu !== menu) {
            otherMenu.style.display = "none";
          }
        });

        // Toggle current dropdown
        if (menu.style.display === "block") {
          menu.style.display = "none";
        } else {
          menu.style.display = "block";
        }
      });
    });
  }

  // Mark all notifications as read
  const markAllRead = document.getElementById("markAllRead");
  if (markAllRead) {
    markAllRead.addEventListener("click", function (e) {
      e.preventDefault();
      fetch("marinehome.php?ajax=mark_all_read", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.ok) {
            document
              .querySelectorAll("#notifList .notification-item.unread")
              .forEach((item) => {
                item.classList.remove("unread");
                const btn = item.querySelector(".mark-read-btn");
                if (btn) btn.remove();
              });
            const badge = document.getElementById("notifBadge");
            if (badge) badge.textContent = "0";
          }
        })
        .catch((err) => console.error("Error marking all as read:", err));
    });
  }

  // Mark single notification as read
  document.getElementById("notifList")?.addEventListener(
    "click",
    function (e) {
      const btn = e.target.closest(".mark-read-btn");
      if (!btn) return;

      e.preventDefault();
      const notifId = btn.dataset.notifId;
      const item = btn.closest(".notification-item");

      fetch("marinehome.php?ajax=mark_read", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "notif_id=" + encodeURIComponent(notifId),
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.ok) {
            item.classList.remove("unread");
            btn.remove();
            const badge = document.getElementById("notifBadge");
            if (badge) {
              let count = parseInt(badge.textContent) || 0;
              count = Math.max(0, count - 1);
              badge.textContent = count;
            }
          }
        })
        .catch((err) => console.error("Error marking as read:", err));
    },
    true
  );

  // Create the performance chart
  const ctx = document.getElementById("performanceChart").getContext("2d");
  const performanceChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: [
        "PAs assessed",
        "PAs monitored",
        "PAs water quality",
        "MPA Network",
        "Habitats monitored",
      ],
      datasets: [
        {
          label: "Target",
          data: [100, 100, 100, 100, 100],
          backgroundColor: "rgba(169, 169, 169, 0.7)",
          borderColor: "rgba(169, 169, 169, 1)",
          borderWidth: 1,
        },
        {
          label: "Actual",
          data: [12, 100, 100, 100, 103],
          backgroundColor: "rgba(43, 102, 37, 0.7)",
          borderColor: "rgba(43, 102, 37, 1)",
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 120,
          title: {
            display: true,
            text: "Percentage (%)",
          },
        },
        x: {
          grid: {
            display: false,
          },
        },
      },
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return context.dataset.label + ": " + context.raw + "%";
            },
          },
        },
      },
      animation: {
        duration: 1500,
        easing: "easeInOutQuart",
      },
    },
  });

  // Custom legend
  const legendItems = performanceChart.data.datasets.map((dataset, i) => {
    return {
      label: dataset.label,
      backgroundColor: dataset.backgroundColor,
      borderColor: dataset.borderColor,
    };
  });

  const legendContainer = document.getElementById("chartLegend");
  legendItems.forEach((item) => {
    const legendItem = document.createElement("div");
    legendItem.className = "legend-item";

    const colorBox = document.createElement("div");
    colorBox.className = "legend-color";
    colorBox.style.backgroundColor = item.backgroundColor;
    colorBox.style.border = `1px solid ${item.borderColor}`;

    const text = document.createElement("span");
    text.textContent = item.label;

    legendItem.appendChild(colorBox);
    legendItem.appendChild(text);
    legendContainer.appendChild(legendItem);
  });

  // Edit button functionality
  const editBtn = document.querySelector(".btn-edit");
  if (editBtn) {
    editBtn.addEventListener("click", function () {
      // Here you would implement your edit functionality
      alert("Edit mode activated. Implement your edit functionality here.");
      // Example: Enable form fields, show editable content, etc.
    });
  }
});
