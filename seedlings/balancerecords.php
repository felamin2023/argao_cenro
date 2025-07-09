<?php
function isDashboardActive() {
    $dashboardPages = ['incoming.php', 'receivedrecords.php', 'plantablerecords.php', 
                      'releasedrecords.php', 'discardedrecords.php', 'balancerecords.php', 
                      'allrecords.php'];
    $currentPage = basename($_SERVER['PHP_SELF']);
    return in_array($currentPage, $dashboardPages) ? 'active' : '';
}

// Add a specific function to check for balance records page
function isBalanceActive() {
    return (basename($_SERVER['PHP_SELF']) === 'balancerecords.php') ? 'active' : '';
}

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

     <link rel="stylesheet" href="/denr/superadmin/css/balancerecords.css">
     <link rel="stylesheet" href="/denr/superadmin/js/balancerecords.js">
    
    


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
                    <!-- New Add Seedlings option -->
                    
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
                    <a href="balancerecords.php" class="dropdown-item active-page">
                        <i class="fas fa-calculator"></i>
                        <span class="item-text">Seedlings Left</span>
                        <span class="quantity-badge"><?php echo $quantities['total_balance']; ?></span>
                    </a>

                    
                     <a href="reportaccident.php" class="dropdown-item">
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
            <div class="header" style="background: #ffffff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 6px 15px rgba(0,0,0,0.2); margin-bottom: 30px; color: black;">
           
                <h1 class="title">SEEDLINGS LEFT</h1>
            </div>

              <!-- Controls Section -->
              <div class="controls">
                <div class="filter" style="display: flex; align-items: center; gap: 10px; width: 30%;">
                    <input type="text" class="filter-month" placeholder="FILTER SPECIES" >
                   
                    <button class="filter-button">
                        <i class="fas fa-filter filter-icon"></i>
                        <span class="filter-label">Filter</span>
                    </button>
                </div>



                
                <div class="search">
                    <input type="text" placeholder="SEARCH HERE" class="search-input">
                    <img src="https://c.animaapp.com/uJwjYGDm/img/google-web-search@2x.png" alt="Search" class="search-icon">
                </div>



                <div class="export"> 
    <button class="export-button">  
        <img src="https://c.animaapp.com/uJwjYGDm/img/vector-1.svg" alt="Export" class="export-icon"> 
    </button> 
    <span class="export-label">Export as CSV</span>
</div> 

            </div>

            <!-- Table Section -->
            <div class="table-container">
                <table class="accident-table">
                    <thead>
                        <tr>
                            <th>BALANCE <br>ID</th>
                           
                            <th>SEEDLING <br>NAME</th>
                            <th>TOTAL <br>RECEIVED</th>
                            <th>TOTAL<br> RELEASED</th>
                            <th>TOTAL <br>DISCARDED</th>
                            <th>SEEDLINGS LEFT</th>
                            
                            <th>ACTIONS</th>
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
                                <button class="edit-btn">✎</button>
                              
                            </td>
                    

                        
                      
                        <!-- Repeat for other rows -->
                    </tbody>
                </table>
            </div>

            <!-- Save Button -->
            <div class="actions">
                <button class="add-record">SAVE</button>
            </div>
        </div>
    </div>

    <!-- JavaScript for Edit & Delete Functionality -->
    <script>

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
            const editButtons = document.querySelectorAll(".edit-btn");
            const deleteButtons = document.querySelectorAll(".delete-btn");

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
        });
    </script>
</body>
</html>