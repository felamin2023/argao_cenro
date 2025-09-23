
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-toggle');
        const navContainer = document.querySelector('.nav-container');
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                navContainer.classList.toggle('active');
            });
        }
        
        // Improved dropdown functionality
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.nav-icon');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // Show menu on hover
            dropdown.addEventListener('mouseenter', () => {
                menu.style.opacity = '1';
                menu.style.visibility = 'visible';
                menu.style.transform = menu.classList.contains('center') 
                    ? 'translateX(-50%) translateY(0)' 
                    : 'translateY(0)';
            });
            
            // Hide menu when leaving both button and menu
            dropdown.addEventListener('mouseleave', (e) => {
                // Check if we're leaving the entire dropdown area
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
            
            // Additional check for menu mouseleave
            menu.addEventListener('mouseleave', (e) => {
                if (!dropdown.contains(e.relatedTarget)) {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                }
            });
        });
        
        // Close dropdowns when clicking outside (for mobile)
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = menu.classList.contains('center') 
                        ? 'translateX(-50%) translateY(10px)' 
                        : 'translateY(10px)';
                });
            }
        });
        
        // Mobile dropdown toggle
        if (window.innerWidth <= 992) {
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.nav-icon');
                const menu = dropdown.querySelector('.dropdown-menu');
                
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
            });
        }

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

        // Tab switching
        const allTab = document.getElementById('all-tab');
        const unreadTab = document.getElementById('unread-tab');
        const allContent = document.getElementById('all-notifications');
        const unreadContent = document.getElementById('unread-notifications');
        
        allTab.addEventListener('click', function() {
            allTab.classList.add('active');
            unreadTab.classList.remove('active');
            allContent.style.display = 'block';
            unreadContent.style.display = 'none';
        });
        
        unreadTab.addEventListener('click', function() {
            unreadTab.classList.add('active');
            allTab.classList.remove('active');
            unreadContent.style.display = 'block';
            allContent.style.display = 'none';
        });

        // Modal functionality
        const modal = document.getElementById('notification-modal');
        const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
        const closeModal = document.querySelector('.close-modal');
        const modalCloseBtn = document.querySelector('.modal-footer .action-button:last-child');

        viewDetailsBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'flex';
            });
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        modalCloseBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Mark as read functionality
        const markReadBtns = document.querySelectorAll('.mark-read-btn');
        markReadBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const notificationItem = this.closest('.notification-item');
                notificationItem.classList.remove('unread');
                this.remove();
                updateUnreadCount();
            });
        });

        // Mark all as read functionality
        document.getElementById('mark-all-read').addEventListener('click', function() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const markAsReadButtons = item.querySelectorAll('.mark-read-btn');
                markAsReadButtons.forEach(button => button.remove());
            });
            updateUnreadCount();
        });

        // Function to update unread count
        function updateUnreadCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            const badge = document.querySelector('.tab-badge');
            const navBadge = document.querySelector('.badge');
            
            badge.textContent = unreadCount;
            navBadge.textContent = unreadCount;
            
            if (unreadCount === 0) {
                badge.style.display = 'none';
                navBadge.style.display = 'none';
            } else {
                badge.style.display = 'flex';
                navBadge.style.display = 'flex';
            }
        }
    });
    