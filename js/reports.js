// Chart.js Example Charts
document.addEventListener("DOMContentLoaded", function () {
    // Sample Bar Chart
    const ctx1 = document.getElementById('myBarChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                datasets: [{
                    label: 'Seedlings Planted',
                    data: [120, 150, 180, 90, 200],
                    backgroundColor: '#2b6625'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } }
            }
        });
    }

    // Sample Pie Chart
    const ctx2 = document.getElementById('myPieChart');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['Mangrove', 'Dipterocarp', 'Fruit-Bearing'],
                datasets: [{
                    data: [300, 150, 100],
                    backgroundColor: ['#4CAF50', '#8BC34A', '#CDDC39']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});

// Dropdown Menu Toggle for Mobile
document.addEventListener("DOMContentLoaded", function () {
    const mobileToggle = document.querySelector(".mobile-toggle");
    const navContainer = document.querySelector(".nav-container");
    const dropdownMenu = document.querySelector(".dropdown-menu");
    const navIcon = document.querySelector(".nav-icon");

    if (mobileToggle) {
        mobileToggle.addEventListener("click", function () {
            navContainer.classList.toggle("active");
            dropdownMenu.style.opacity = dropdownMenu.style.opacity === "1" ? "0" : "1";
            dropdownMenu.style.visibility = dropdownMenu.style.visibility === "visible" ? "hidden" : "visible";
        });
    }

    if (navIcon) {
        navIcon.addEventListener("click", function () {
            dropdownMenu.style.opacity = dropdownMenu.style.opacity === "1" ? "0" : "1";
            dropdownMenu.style.visibility = dropdownMenu.style.visibility === "visible" ? "hidden" : "visible";
        });
    }
});

// Collapsible Sections
document.addEventListener("DOMContentLoaded", function () {
    const collapsibleTitles = document.querySelectorAll(".section-title, .card-header");

    collapsibleTitles.forEach(function (title) {
        title.addEventListener("click", function () {
            const content = title.nextElementSibling;
            if (content) {
                content.style.display = content.style.display === "none" ? "block" : "none";
            }
        });
    });
});
