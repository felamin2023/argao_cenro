<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wildlife Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="/denr/superadmin/css/wildrecord.css">
</head>
<body>

<header>
    <div class="logo">
        <a href="wildhome.php">
            <img src="seal.png" alt="Site Logo">
        </a>
    </div>
    
    <!-- Mobile menu toggle -->
    <button class="mobile-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Navigation on the right -->
    <div class="nav-container">
        <!-- Dashboard Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon active" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </div>
            <div class="dropdown-menu center">
                <a href="breedingreport.php" class="dropdown-item active-page">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Record</span>
                </a>  

                 <a href="wildpermit.php" class="dropdown-item">
                    <i class="fas fa-paw"></i>
                    <span>Wildlife Permit</span>
                </a>


                <a href="reportaccident.php" class="dropdown-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Incident Reports</span>
                </a>
            </div>
        </div>

        <!-- Messages Icon -->
        <div class="nav-item">
            <div class="nav-icon">
                <a href="wildmessage.php" aria-label="Messages">
                    <i class="fas fa-envelope" style="color: black;"></i>
                </a>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="nav-item dropdown">
            <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="badge">1</span>
            </div>
            <div class="dropdown-menu notifications-dropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <a href="#" class="mark-all-read">Mark all as read</a>
                </div>
                <div class="notification-item unread">
                    <a href="wildeach.php?id=1" class="notification-link">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Wildlife Incident Reported</div>
                            <div class="notification-message">A large monitor lizard approximately 1.2 meters in length was spotted near a residential backyard early in the morning.</div>
                            <div class="notification-time">15 minutes ago</div>
                        </div>
                    </a>
                </div>
                <div class="notification-footer">
                    <a href="wildnotification.php" class="view-all">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="dropdown-menu">
                <a href="wildprofile.php" class="dropdown-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Edit Profile</span>
                </a>
                <a href="../superlogin.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Back Icon -->
<a href="breedingreport.php" class="back-icon">
    <i class="fas fa-arrow-left"></i>
</a>

<!-- Main Content -->
<div class="wildlife-container">
    <div class="container">
        <div class="header">
            <h1 class="title">WILDLIFE MONITORING RECORDS</h1>
        </div>


          <!-- Controls Section -->
            <div class="controls">
            <div class="filter" style="display: flex; align-items: center; gap: 10px; width: 40%;">
                    <select class="filter-month">
                        <option value="">Start Month</option>
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

                     <select class="filter-month">
                        <option value="">End Month</option>
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
                    <button class="filter-button" style="background-color: var(--primary-dark); color: var(--white); display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; transition: background-color 0.3s ease;">
                        <i class="fas fa-filter" style="font-size: 18px; color: var(--white);"></i>
                        <span style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; font-weight: 600;">Filter</span>
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
            <table class="wildlife-table">
                <thead>
                    <tr>
                        <th>WILDLIFE ID</th>
                        <th>OWNER NAME</th>
                        <th>SPECIES NAME</th>
                        <th>STOCK NO</th>
                        <th>PREV BALANCE</th>
                        
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>WL-001</td>
                        <td>Juan Cruz</td>
                        <td>Agapornis</td>
                        <td>SN-001</td>
                        <td>2</td>
                       
                        <td>
                            <button class="view-btn" onclick="openModal('1', 'view')">👁️</button>
                            <button class="edit-btn" onclick="openModal('1', 'edit')">✎</button>
                           
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

<!-- Modal for detailed view -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">WILDLIFE MONITORING DETAILS</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Details will be populated here by JavaScript -->
        </div>
        <div class="modal-actions" id="modalActions">
            <!-- Action buttons will be populated here by JavaScript -->
        </div>
    </div>
</div>

<!-- JavaScript for functionality -->
<script>
    // Sample data - in a real app, you would fetch this from your database
    const recordData = {
        '1': {
          
          
            'START': '2024-01-06',
            'END': '2025-02-07',
           
            'Address': 'Lamacan, Argao, Cebu',
            'WFP No': 'WFP-2024-001',
            'LOCATION OF FARM': 'Poblacion, Argao, Cebu',
            'UPLOADED IMAGE': 'Agapornis',
            'SPECIES NAME': 'Agapornis',
            'STATUS': 'ALIVE',
           
        
        
        },
       
    };

    let currentRecordId = null;
    let currentMode = 'view';
    let originalData = {};

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

        // Delete buttons functionality
        const deleteButtons = document.querySelectorAll(".delete-btn");

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
    
    // Open modal in either view or edit mode
    function openModal(id, mode) {
        currentRecordId = id;
        currentMode = mode;
        const modal = document.getElementById('detailModal');
        const modalContent = modal.querySelector('.modal-content');
        const modalBody = document.getElementById('modalBody');
        const modalTitle = document.getElementById('modalTitle');
        const modalActions = document.getElementById('modalActions');
        
        // Get the data for the selected record
        const record = recordData[id] || {};
        originalData = {...record};
        
        // Set modal title based on mode
        modalTitle.textContent = mode === 'edit' ? 
            'EDIT WILDLIFE MONITORING DETAILS' : 
            'WILDLIFE MONITORING DETAILS';
        
        // Toggle edit mode class on modal content
        if (mode === 'edit') {
            modalContent.classList.add('edit-mode');
        } else {
            modalContent.classList.remove('edit-mode');
        }
        
        // Build the HTML for the modal content
        let html = '';
        for (const [key, value] of Object.entries(record)) {
            // Determine input type based on field
            let inputType = 'text';
            if (key.includes('DATE')) inputType = 'date';
            if (key.includes('NO') || key.includes('BALANCE') || key.includes('STOCKS')) inputType = 'number';
            if (key.includes('REMARKS')) inputType = 'textarea';
            
            // Special handling for UPLOADED IMAGE field
            if (key === 'UPLOADED IMAGE') {
                html += `
                    <div class="detail-row">
                        <div class="detail-label">${key}:</div>
                        <div class="detail-value">
                            <img src="${value}" alt="Species Image" style="max-width: 200px;">
                        </div>
                        <div class="detail-value-edit">
                            <div class="image-upload-container" onclick="document.getElementById('species_image').click()">
                                <input type="file" name="species_image" id="species_image" accept="image/*" class="image-upload-input" style="display: none;">
                                <div class="image-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload species image</span>
                                    <small style="display: block; margin-top: 5px; color: var(--text-light);">(JPEG, PNG, max 5MB)</small>
                                </div>
                                <div class="image-preview" id="image-preview">
                                    <img src="${value}" alt="Species Image">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                continue;
            }
            
            html += `
                <div class="detail-row">
                    <div class="detail-label">${key}:</div>
                    <div class="detail-value">${value}</div>
                    <div class="detail-value-edit">
                        ${inputType === 'textarea' ? `
                            <textarea id="${key}" rows="4">${value}</textarea>
                        ` : `
                            <input type="${inputType}" id="${key}" value="${value}">
                        `}
                    </div>
                </div>
            `;
        }
        
        modalBody.innerHTML = html;

        // Add image preview functionality
        const imageInput = document.getElementById('species_image');
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imagePreview = document.getElementById('image-preview');
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Species Image">`;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Set up action buttons based on mode
        let actionButtons = '';
        if (mode === 'view') {
            actionButtons = `
            `;
            modalBody.classList.add('view-mode');
            modalBody.classList.remove('edit-mode');
        } else {
            actionButtons = `
                <button class="modal-btn modal-btn-save" onclick="saveChanges()">Save Changes</button>
                <button class="modal-btn modal-btn-cancel" onclick="cancelEdit()">Cancel</button>
            `;
            modalBody.classList.add('edit-mode');
            modalBody.classList.remove('view-mode');
        }
        
        modalActions.innerHTML = actionButtons;
        modal.style.display = 'block';
    }
    
    function saveChanges() {
        // Collect all the edited values
        const inputs = document.querySelectorAll('.detail-value-edit input, .detail-value-edit textarea, .detail-value-edit select');
        const updatedData = {};
        
        // Handle image upload
        const imageInput = document.getElementById('species_image');
        if (imageInput && imageInput.files.length > 0) {
            // In a real app, you would handle the image upload here
            console.log('Image file:', imageInput.files[0]);
            updatedData['UPLOADED IMAGE'] = URL.createObjectURL(imageInput.files[0]); // Temporary URL for preview
        }
        
        inputs.forEach(input => {
            updatedData[input.id] = input.value;
        });
        
        // In a real app, you would send this data to the server
        console.log('Updated data:', updatedData);
        
        // Update the table with the new data (in a real app, you would refresh from server)
        Object.assign(recordData[currentRecordId], updatedData);
        
        // Update the table row
        const tableRow = document.querySelector(`.view-btn[onclick*="'${currentRecordId}'"]`).closest('tr');
        tableRow.cells[0].textContent = updatedData['Wildlife ID'] || recordData[currentRecordId]['Wildlife ID'];
        tableRow.cells[1].textContent = updatedData['Name'] || recordData[currentRecordId]['Name'];
        tableRow.cells[2].textContent = updatedData['Species Name'] || recordData[currentRecordId]['Species Name'];
        tableRow.cells[3].textContent = updatedData['Stock No'] || recordData[currentRecordId]['Stock No'];
        tableRow.cells[4].textContent = updatedData['Prev Balance'] || recordData[currentRecordId]['Prev Balance'];
        tableRow.cells[5].textContent = updatedData['Date'] || recordData[currentRecordId]['Date'];
        
        alert('Changes saved successfully!');
        closeModal();
    }
    
    function cancelEdit() {
        // Restore original data
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            Object.assign(recordData[currentRecordId], originalData);
            closeModal();
        }
    }
    
    function closeModal() {
        const modal = document.getElementById('detailModal');
        modal.style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (event.target == modal) {
            if (currentMode === 'edit') {
                cancelEdit();
            } else {
                closeModal();
            }
        }
    }
</script>
</body>
</html>