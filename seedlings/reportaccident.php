<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Sample quantities (replace with your database values)
$quantities = [
    'total_received' => 1250,
    'plantable_seedlings' => 980,
    'total_released' => 720,
    'total_discarded' => 150,
    'total_balance' => 380,
    'all_records' => 2150
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seedlings Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
     <link rel="stylesheet" href="/denr/superadmin/css/reportaccident.css">
     <link rel="stylesheet" href="/denr/superadmin/js/reportaccident.js">
    
</head>
<body>
<header>
        <div class="logo">
            <a href="seedlingshome.php">
                <img src="seal.png" alt="Site Logo">
            </a>
        </div>
        
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="nav-container">
            <!-- Main Dropdown Menu -->
            <div class="nav-item dropdown">
            <div class="nav-icon active">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dropdown-menu center">
                  
                   
                   <a href="incoming.php" class="dropdown-item">
                        <i class="fas fa-seedling"></i>
                        <span class="item-text">Seedlings Received</span>
                        <span class="quantity-badge"><?php echo $quantities['total_received']; ?></span>
                    </a>
                    
                    <a href="releasedrecords.php" class="dropdown-item">
                        <i class="fas fa-truck"></i>
                        <span class="item-text">Seedlings Released</span>
                        <span class="quantity-badge"><?php echo $quantities['total_released']; ?></span>
                    </a>
                    <a href="discardedrecords.php" class="dropdown-item">
                        <i class="fas fa-trash-alt"></i>
                        <span class="item-text">Seedlings Discarded</span>
                        <span class="quantity-badge"><?php echo $quantities['total_discarded']; ?></span>
                    </a>
                    <a href="balancerecords.php" class="dropdown-item">
                        <i class="fas fa-calculator"></i>
                        <span class="item-text">Seedlings Left</span>
                        <span class="quantity-badge"><?php echo $quantities['total_balance']; ?></span>
                    </a>
                    
                     <a href="reportaccident.php" class="dropdown-item active-page">
                        <i class="fas fa-file-invoice"></i>
                        <span>Incident Reports</span>
                    </a>

                    <a href="user_requestseedlings.php" class="dropdown-item">
                        <i class="fas fa-paper-plane"></i>
                        <span>Seedlings Request</span>
                    </a>

                </div>
            </div>
            
           

            <!-- Messages Icon -->
            <div class="nav-item">
                <div class="nav-icon">
                    <a href="seedlingsmessage.php">
                        <i class="fas fa-envelope" style="color: black;"></i>
                    </a>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="nav-item dropdown">
                <div class="nav-icon">
                    <i class="fas fa-bell"></i>
                    <span class="badge">1</span>
                </div>
                <div class="dropdown-menu notifications-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <a href="#" class="mark-all-read">Mark all as read</a>
                    </div>
                    
                    <div class="notification-item unread">
                        <a href="seedlingseach.php?id=1" class="notification-link">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                            <div class="notification-title">Seedlings Disposal Alert</div>
                            <div class="notification-message">Report of seedlings being improperly discarded in the protected area.</div>
                            <div class="notification-time">15 minutes ago</div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="notification-footer">
                        <a href="seedlingsnotification.php" class="view-all">View All Notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- Profile Dropdown -->
            <div class="nav-item dropdown">
                <div class="nav-icon <?php echo $current_page === 'forestry-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="seedlingsprofile.php" class="dropdown-item">
                        <i class="fas fa-user-edit"></i>
                        <span class="item-text">Edit Profile</span>
                    </a>
                    <a href="../superlogin.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="item-text">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <div class="CHAINSAW-RECORDS">
        <div class="container">
<div class="header-section" style="background: #ffffff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 6px 15px rgba(0,0,0,0.2); margin-bottom: 30px; color: black;">
                <h1 class="title" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 42px; font-weight: 900; color:rgb(0, 0, 0); text-align: center; margin: 0;">
                    Incident Reports
                </h1>
            </div>

              <!-- Controls Section -->
              <div class="controls" style="background-color: #ffffff !important;">
                <div class="filter">
                    <select class="filter-month">
                        <option value="">Select Month</option>
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <input type="number" class="filter-year" placeholder="Enter Year">
                    <datalist id="year-suggestions">
                        <option value="2020">
                        <option value="2021">
                        <option value="2022">
                        <option value="2023">
                        <option value="2024">
                        <option value="2025">
                        <option value="2026">
                    </datalist>
                    <button class="filter-button" aria-label="Filter">
                        <i class="fas fa-filter" style="font-size: 18px; color: #005117; margin-right: 6px;"></i> Filter
                    </button>
                </div>

                
                <div class="search">
                    <input type="text" placeholder="SEARCH HERE" class="search-input" id="search-input">
                    <img src="https://c.animaapp.com/uJwjYGDm/img/google-web-search@2x.png" alt="Search" class="search-icon" id="search-icon">
                </div>
                <div class="export">
                    <button class="export-button" id="export-button">
                        <img src="https://c.animaapp.com/uJwjYGDm/img/vector-1.svg" alt="Export" class="export-icon">
                    </button>
                    <span class="export-label">Export as CSV</span>
                </div>
            </div>

            <!-- Centered Status Buttons -->
            <div class="status-buttons">
                <button class="status-btn all-btn">ALL</button>
                <button class="status-btn pending-btn">PENDING</button>
                <button class="status-btn resolved-btn">RESOLVED</button>
                <button class="status-btn rejected-btn">REJECTED</button>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <table class="accident-table">
                    <thead>
                        <tr>
                           <th style="width: 7%;">INCIDENT<br> ID</th>
                          
                            <th style="width: 8%;">USERNAME</th>
                            <th style="width: 8%;">FIRSTNAME</th>
                            <th style="width: 8%;">LASTNAME</th>
                           
                            <th style="width: 8%;">CONTACT NO</th>
                            <th style="width: 12%;">LOCATION OF INCIDENT</th>
                            <th style="width: 10%;">PHOTO</th>
                            <th style="width: 15%;">DESCRIPTION OF <br>INCIDENT</th>
                            <th style="width: 10%;">DATETIME</th>
                            <th style="width: 8%;">STATUS</th>
                            <th style="width: 8%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                           <td></td>
                            <td></td>
                            <td></td>
                           
                            <td></td>
                            <td></td>
                            <td>
                               
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>
                                <button class="edit-btn">✎</button>
                               
                            </td>
                        </tr>
                      
                     
                    </tbody>
                </table>
            </div>

            <!-- Save Button -->
            <div class="actions">
                <button class="add-record">SAVE</button>
            </div>
        </div>
    </div>

    <!-- JavaScript for Filter, Edit & Delete Functionality -->
    <script>
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
    </script>
</body>
</html>