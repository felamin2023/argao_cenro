

document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-toggle');
        const navContainer = document.querySelector('.nav-container');
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                navContainer.classList.toggle('active');
            });
        }
        
        // Dropdown functionality
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.nav-icon');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // Show menu on hover (desktop)
            dropdown.addEventListener('mouseenter', () => {
                if (window.innerWidth > 992) {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(0)' 
                        : 'translateY(0)';
                }
            });
            
            // Hide menu when leaving (desktop)
            dropdown.addEventListener('mouseleave', (e) => {
                if (window.innerWidth > 992 && !dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
            
            // Toggle menu on click (mobile)
            if (window.innerWidth <= 992) {
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.style.display = 'none';
                        }
                    });
                    
                    // Toggle current dropdown
                    if (menu.style.display === 'block') {
                        menu.style.display = 'none';
                    } else {
                        menu.style.display = 'block';
                    }
                });
            }
        });
        
        // Close dropdowns when clicking outside (mobile)
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown') && window.innerWidth <= 992) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });

        // Mark all notifications as read
        const markAllRead = document.querySelector('.mark-all-read');
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                document.querySelector('.badge').style.display = 'none';
            });
        }
    });

        document.addEventListener("DOMContentLoaded", () => {
            // Edit and Delete buttons functionality
            const editButtons = document.querySelectorAll(".edit-btn");
            const deleteButtons = document.querySelectorAll(".delete-btn");
            const releasedButtons = document.querySelectorAll(".released-btn");
            const discardedButtons = document.querySelectorAll(".discarded-btn");

            editButtons.forEach(button => {
                button.addEventListener("click", () => {
                    alert("Edit functionality not yet implemented.");
                });
            });

            deleteButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const confirmDelete = confirm("Are you sure you want to delete this record?");
                    if (confirmDelete) {
                        alert("Record deleted successfully!");
                        // Add code to delete the row from database here
                    }
                });
            });

            // Task buttons functionality
            releasedButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const row = button.closest('tr');
                    row.style.backgroundColor = "#e8f5e9"; // Light green
                    alert("Seedlings marked as Released!");
                });
            });

            discardedButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const row = button.closest('tr');
                    row.style.backgroundColor = "#ffebee"; // Light red
                    alert("Seedlings marked as Discarded!");
                });
            });
        });
   
        // Make search icon clickable to focus the search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchIcon = document.querySelector('.search-icon');
            const searchInput = document.querySelector('.search-input');
            if (searchIcon && searchInput) {
                searchIcon.style.cursor = 'pointer';
                searchIcon.addEventListener('click', function() {
                    searchInput.focus();
                });
            }
        });
 
