
        document.addEventListener("DOMContentLoaded", () => {
            const filterButton = document.querySelector(".filter-button");
            const filterMonth = document.querySelector(".filter-month");
            const filterYear = document.querySelector(".filter-year");
            const tableRows = document.querySelectorAll(".accident-table tbody tr");
            const searchIcon = document.getElementById("search-icon");
            const searchInput = document.getElementById("search-input");
            const exportButton = document.getElementById("export-button");
            const statusButtons = document.querySelectorAll(".status-btn");

            // Enhanced Search Functionality
            const performSearch = () => {
                const searchTerm = searchInput.value.toLowerCase();
                tableRows.forEach(row => {
                    let rowContainsText = false;
                    const cells = row.querySelectorAll("td");
                    
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().includes(searchTerm)) {
                            rowContainsText = true;
                        }
                    });
                    
                    row.style.display = rowContainsText ? "" : "none";
                });
            };

            // Click event for search icon
            searchIcon.addEventListener("click", performSearch);

            // Ensure the search icon triggers the search functionality
            searchIcon.style.pointerEvents = "auto";

            // Enter key event for search input
            searchInput.addEventListener("keypress", (e) => {
                if (e.key === "Enter") {
                    performSearch();
                }
            });

            // Filter Functionality
            filterButton.addEventListener("click", () => {
                const selectedMonth = filterMonth.value;
                const selectedYear = filterYear.value;

                tableRows.forEach(row => {
                    const dateCell = row.querySelector("td:nth-child(10)");
                    if (dateCell) {
                        const dateText = dateCell.textContent.trim();
                        const [datePart] = dateText.split(" ");
                        const [year, month] = datePart.split("-");

                        const matchesMonth = selectedMonth ? month === selectedMonth : true;
                        const matchesYear = selectedYear ? year === selectedYear : true;

                        if (matchesMonth && matchesYear) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    }
                });
            });

            // Export Functionality - CSV Download
            exportButton.addEventListener("click", () => {
                // Create CSV content
                let csvContent = "data:text/csv;charset=utf-8,";
                
                // Add headers
                const headers = [];
                document.querySelectorAll(".accident-table th").forEach(header => {
                    headers.push(`"${header.textContent}"`);
                });
                csvContent += headers.join(",") + "\r\n";
                
                // Add rows
                document.querySelectorAll(".accident-table tbody tr").forEach(row => {
                    if (row.style.display !== "none") {
                        const rowData = [];
                        row.querySelectorAll("td").forEach((cell, index) => {
                            // Skip the photo column (index 7) and action buttons (index 11)
                            if (index !== 7 && index !== 11) {
                                rowData.push(`"${cell.textContent.replace(/"/g, '""')}"`);
                            }
                        });
                        csvContent += rowData.join(",") + "\r\n";
                    }
                });
                
                // Create download link
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "incident_report_" + new Date().toISOString().slice(0,10) + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });

            // Status Filter Functionality
            statusButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const status = button.textContent.trim().toLowerCase();
                    
                    tableRows.forEach(row => {
                        const statusCell = row.querySelector("td:nth-child(11)");
                        if (statusCell) {
                            const rowStatus = statusCell.textContent.trim().toLowerCase();
                            
                            if (status === "all" || rowStatus === status) {
                                row.style.display = "";
                            } else {
                                row.style.display = "none";
                            }
                        }
                    });
                });
            });

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
   