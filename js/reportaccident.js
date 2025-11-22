
        document.addEventListener("DOMContentLoaded", () => {
            const filterButton = document.querySelector(".filter-button");
            const filterMonth = document.querySelector(".filter-month");
            const filterYear = document.querySelector(".filter-year");
            const exportButton = document.getElementById("export-button");

            // resilient selectors (page may use id or class)
            const statusSelect = document.getElementById('status-filter-select') || document.querySelector('.status-filter-select');
            const searchInput = document.getElementById('search-input') || document.querySelector('.search-input');
            const searchIcon = document.getElementById('search-icon') || document.querySelector('.search-icon');
            const noResultsDiv = document.getElementById('no-results-full');

            // helper: get current data rows (ignore helper rows)
            const getDataRows = () => Array.from(document.querySelectorAll('.accident-table tbody tr')).filter(r => !r.classList.contains('no-results-row'));

            // debounce helper
            const debounce = (fn, wait = 200) => {
                let t = null;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...args), wait);
                };
            };

            // detect status by scanning cells for known status words
            const KNOWN_STATUSES = ['pending', 'approved', 'resolved', 'rejected'];

            function detectRowStatus(row) {
                const texts = Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim().toLowerCase());
                for (const t of texts) {
                    if (KNOWN_STATUSES.includes(t)) return t;
                }
                // fallback: try nth-child common index used previously (11)
                const maybe = row.querySelector('td:nth-child(11)');
                if (maybe) return (maybe.textContent || '').trim().toLowerCase();
                return '';
            }

            // core filter logic: term + status + optional date filter
            function applyFilters() {
                try {
                    const term = (searchInput && searchInput.value || '').toLowerCase().trim();
                    const status = (statusSelect && (statusSelect.value || '').toLowerCase().trim()) || 'all';
                    const rows = getDataRows();
                    let visibleCount = 0;

                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        const matchesTerm = term === '' || text.includes(term);

                        const rowStatus = detectRowStatus(row);
                        const matchesStatus = status === 'all' || (rowStatus && rowStatus === status);

                        // apply month/year date filter if present
                        let matchesDate = true;
                        if (filterMonth || filterYear) {
                            const dateCell = row.querySelector('td:nth-child(10)');
                            if (dateCell) {
                                const dateText = dateCell.textContent.trim();
                                const [datePart] = dateText.split(' ');
                                const parts = datePart.split('-');
                                if (parts.length >= 2) {
                                    const [y, m] = parts;
                                    if (filterMonth && filterMonth.value) matchesDate = (m === filterMonth.value);
                                    if (matchesDate && filterYear && filterYear.value) matchesDate = (y === filterYear.value);
                                }
                            }
                        }

                        const shouldShow = matchesTerm && matchesStatus && matchesDate;
                        row.style.display = shouldShow ? '' : 'none';
                        if (shouldShow) visibleCount++;
                    });

                    if (noResultsDiv) noResultsDiv.style.display = visibleCount > 0 ? 'none' : 'block';
                    console.debug('[reportaccident] applyFilters:', {term: term, status: status, visible: visibleCount});
                } catch (err) {
                    console.error('[reportaccident] filter error', err);
                }
            }

            const debouncedApply = debounce(applyFilters, 180);

            // wire events
            if (searchInput) {
                searchInput.addEventListener('input', debouncedApply);
                searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') applyFilters(); });
            }
            if (searchIcon) {
                searchIcon.style.pointerEvents = 'auto';
                searchIcon.addEventListener('click', applyFilters);
            }
            if (statusSelect) statusSelect.addEventListener('change', applyFilters);
            if (filterButton) {
                filterButton.addEventListener('click', () => { applyFilters(); });
            }

            // Export CSV - keep behavior but use current visible rows
            if (exportButton) {
                exportButton.addEventListener('click', () => {
                    let csvContent = "data:text/csv;charset=utf-8,";
                    const headers = Array.from(document.querySelectorAll('.accident-table th')).map(h => `"${h.textContent.replace(/"/g, '""')}"`);
                    csvContent += headers.join(',') + '\r\n';
                    getDataRows().forEach(row => {
                        if (row.style.display === 'none') return;
                        const rowData = Array.from(row.querySelectorAll('td')).map((cell, index) => {
                            if (index === 7 || index === 11) return null; // skip photo/action columns if present
                            return `"${(cell.textContent || '').replace(/"/g, '""')}"`;
                        }).filter(Boolean);
                        csvContent += rowData.join(',') + '\r\n';
                    });

                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', 'incident_report_' + new Date().toISOString().slice(0,10) + '.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }

            // Enhanced Edit and Delete Functionality with Hover Effects
            const editButtons = document.querySelectorAll(".edit-btn");
            const deleteButtons = document.querySelectorAll(".delete-btn");

            editButtons.forEach(button => {
                button.addEventListener("mouseenter", () => {
                    button.style.transform = "scale(1.1)";
                    button.style.boxShadow = "0 2px 5px rgba(0,0,0,0.2)";
                });
                
                button.addEventListener("mouseleave", () => {
                    button.style.transform = "scale(1)";
                    button.style.boxShadow = "none";
                });
                
                button.addEventListener("click", () => {
                    const row = button.closest("tr");
                    alert("Edit functionality would open a form for row with ID: " + row.cells[0].textContent);
                });
            });

            deleteButtons.forEach(button => {
                button.addEventListener("mouseenter", () => {
                    button.style.transform = "scale(1.1)";
                    button.style.boxShadow = "0 2px 5px rgba(0,0,0,0.2)";
                });
                
                button.addEventListener("mouseleave", () => {
                    button.style.transform = "scale(1)";
                    button.style.boxShadow = "none";
                });
                
                button.addEventListener("click", () => {
                    const row = button.closest("tr");
                    const confirmDelete = confirm("Are you sure you want to delete this record?");
                    if (confirmDelete) {
                        row.style.transition = "all 0.3s ease";
                        row.style.opacity = "0";
                        row.style.transform = "translateX(100%)";
                        setTimeout(() => {
                            row.remove();
                            alert("Record deleted successfully!");
                        }, 300);
                    }
                });
            });

            // Mobile menu toggle functionality
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
        });
   