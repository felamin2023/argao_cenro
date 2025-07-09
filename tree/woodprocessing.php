<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wood Processing Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
     <link rel="stylesheet" href="/denr/superadmin/css/woodprocessing.css">
   
</head>
<body>

<header>
    <div class="logo">
        <a href="treehome.php">
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
            <div  class="nav-icon active" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </div>
            <div class="dropdown-menu center">
                <a href="treecutting.php" class="dropdown-item">
                    <i class="fas fa-tree"></i>
                    <span>Tree Cutting</span>
                </a>
                <a href="lumber.php" class="dropdown-item">
                    <i class="fas fa-store"></i>
                    <span>Lumber Dealers</span>
                </a>
                <a href="chainsaw.php" class="dropdown-item">
                    <i class="fas fa-tools"></i>
                    <span>Registered Chainsaw</span>
                </a>
                <a href="woodprocessing.php" class="dropdown-item active-page">
                    <i class="fas fa-industry"></i>
                    <span>Wood Processing</span>
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
                <a href="treemessage.php" aria-label="Messages">
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
                    <a href="treeeach.php?id=1" class="notification-link">
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Illegal Logging Alert</div>
                            <div class="notification-message">Report of unauthorized tree cutting activity in protected area.</div>
                            <div class="notification-time">15 minutes ago</div>
                        </div>
                    </a>
                </div>
                
                <div class="notification-footer">
                    <a href="treenotification.php" class="view-all">View All Notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="nav-item dropdown">
            <div class="nav-icon" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="dropdown-menu">
                <a href="treeprofile.php" class="dropdown-item">
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

    <div class="wood-processing-records">
        <div class="container">
            <div class="header">
                <h1 class="title">WOOD PROCESSING PERMIT RECORDS</h1>
            </div>

            <!-- Controls Section -->
            <div class="controls">
               
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
                <table class="wood-table">
                    <thead>
                        <tr>
                            <th>WPP ID</th>
                            <th>FIRST NAME</th>
                            <th>LAST NAME</th>
                            <th>TYPE</th>
                            <th>DATE APPLIED</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Juan</td>
                            <td>RCruz</td>
                            <td>New Permit</td>
                            <td>2023-08-18</td>
                            <td>Approved</td>
                           
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
                <h2 id="modalTitle">WOOD PROCESSING PLANT PERMIT - REQUIREMENTS</h2>
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

    <!-- File Preview Modal -->
    <div id="filePreviewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3 id="modal-title">File Preview</h3>
            <iframe id="filePreviewFrame" class="file-preview" src="about:blank"></iframe>
        </div>
    </div>

        <!-- JavaScript for functionality -->
        <script>
        // Sample data - in a real app, you would fetch this from your database
        const recordData = {
            '1': {
                'WPP ID': '1',
                'NAME': 'Escalon Export Incorporated rep. by Ms. Rosalie Desvauz',
                'TYPE': 'Resawmill-renewal',
                'INTEGRATED': 'not applicable',
                'WPP NUMBER': 'RVII-01-2023',
                'LOCATION BUSINESS': 'Suba, Samboan, Cebu',
                'LOCATION PLANT': 'Suba, Samboan, Cebu',
                'DRC': '0.35',
                'ALR': '81.2',
                'LONGITUDE': '123.305953',
                'LATITUDE': '9.531177',
                'NAME AND ADDRESS OF SUPPLIER': 'CTPO',
                'VOLUME LOCAL': '117.924',
                'VOLUME IMPORT': '34.5',
                'AREA': '24.5',
                'DATE APPLIED': '2023-08-18',
                'DATE APPROVED': '2023-08-24',
                'APPROVED BY': 'Paquito D.Melicor, Jr, Regional Executive Director',
                'STATUS': 'active',
                'requirements': {
                    'application_form': { uploaded: true, file: 'application_form.pdf' },
                    'application_fee': { uploaded: true, file: 'fee_receipt.pdf' },
                    'certificate_registration': { uploaded: true, file: 'registration.pdf' },
                    'authorization': { uploaded: true, file: 'authorization.pdf' },
                    'business_plan': { uploaded: true, file: 'business_plan.pdf' },
                    'business_permit': { uploaded: true, file: 'permit.pdf' },
                    'ecc': { uploaded: true, file: 'ecc.pdf' },
                    'citizenship_proof': { uploaded: true, file: 'birth_certificate.pdf' },
                    'machine_ownership': { uploaded: true, file: 'ownership.pdf' },
                    'gis_map': { uploaded: true, file: 'gis_map.pdf' },
                    'hotspot_certification': { uploaded: true, file: 'hotspot_cert.pdf' },
                    'previous_permit': { uploaded: true, file: 'previous_permit.pdf' },
                    'good_standing': { uploaded: true, file: 'good_standing.pdf' },
                    'cctv_certificate': { uploaded: true, file: 'cctv_cert.pdf' },
                    'supply_contracts': { uploaded: true, file: 'contracts.pdf' },
                    'tree_inventory': { uploaded: true, file: 'inventory.pdf' },
                    'electronic_data': { uploaded: true, file: 'data.zip' },
                    'validation_report': { uploaded: true, file: 'validation.pdf' },
                    'tenure_instrument': { uploaded: true, file: 'tenure.pdf' },
                    'ctpo_ptpr': { uploaded: true, file: 'ctpo.pdf' },
                    'production_report': { uploaded: true, file: 'production.pdf' }
                }
            }
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
                'EDIT WOOD PROCESSING PLANT PERMIT' : 
                'WOOD PROCESSING PLANT PERMIT - SUBMITTED REQUIREMENTS';
            
            // Toggle edit mode class on modal content
            if (mode === 'edit') {
                modalContent.classList.add('edit-mode');
            } else {
                modalContent.classList.remove('edit-mode');
            }
            
            // Build the HTML for the modal content
            let html = '';
            
            if (mode === 'view') {
                // View mode - show requirements list
                html = `
                    <div class="requirements-list">
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">a</span>
                                    Duly accomplished application form
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.application_form?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.application_form?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">b</span>
                                    Application fee/permit fee (OR as proof of payment)
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.application_fee?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.application_fee?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">c</span>
                                    Copy of Certificate of Registration, Articles of Incorporation, Partnership or Cooperation
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.certificate_registration?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.certificate_registration?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">d</span>
                                    Authorization issued by the Corporation, Partnership or Association in favor of the person signing the application
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.authorization?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.authorization?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">e</span>
                                    Feasibility Study/Business Plan
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.business_plan?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.business_plan?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">f</span>
                                    Business Permit
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.business_permit?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.business_permit?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">g</span>
                                    Environmental Compliance Certificate (ECC)
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.ecc?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.ecc?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">h</span>
                                    For individual persons, document reflecting proof of Filipino citizenship such as Birth Certificate or Certificate of Naturalization
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.citizenship_proof?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.citizenship_proof?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">i</span>
                                    Evidence of ownership of machines
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.machine_ownership?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.machine_ownership?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">j</span>
                                    GIS generated map with corresponding geo-tagged photos showing the location of WPP
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.gis_map?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.gis_map?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">k</span>
                                    Certification from the Regional Office that the WPP is not within the illegal logging hotspot area
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.hotspot_certification?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.hotspot_certification?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">l</span>
                                    Previously approved WPP Permit
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.previous_permit?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.previous_permit?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">m</span>
                                    Certificate of Good Standing
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.good_standing?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.good_standing?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">n</span>
                                    Certificate that the WPP Holder has already installed CCTV Camera
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="uploaded-files">
                                    <div class="file-item">
                                        <div class="file-info">
                                            <i class="fas fa-file-pdf file-icon"></i>
                                            <span>${record.requirements?.cctv_certificate?.file || 'Not uploaded'}</span>
                                        </div>
                                        <div class="file-actions">
                                            <button class="file-action-btn view-file" data-file="${record.requirements?.cctv_certificate?.file || ''}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="file-action-btn" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">o</span>
                                    Proof of sustainable sources of legally cut logs for a period of at least 5 years
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="sub-requirement">
                                    <p style="margin-bottom: 10px; font-weight: 500;">1. Original copy of Log/Veneer/Lumber Supply Contracts</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.supply_contracts?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.supply_contracts?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">2. 5% Tree Inventory</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.tree_inventory?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.tree_inventory?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">3. Electronic Copy of Inventory Data</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.electronic_data?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.electronic_data?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">4. Validation Report</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.validation_report?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.validation_report?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">5. Copy of Tenure Instrument and Harvesting Permit (if source of raw materials is forest plantations)</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.tenure_instrument?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.tenure_instrument?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">6. Copy of CTPO/PTPR and map (if source of raw materials is private tree plantations)</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.ctpo_ptpr?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.ctpo_ptpr?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">7. Monthly Production and Disposition Report</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>${record.requirements?.production_report?.file || 'Not uploaded'}</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="${record.requirements?.production_report?.file || ''}" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="requirement-item">
                            <div class="requirement-header">
                                <div class="requirement-title">
                                    <span class="requirement-number">q</span>
                                    For Importers:
                                </div>
                            </div>
                            <div class="file-upload">
                                <div class="sub-requirement">
                                    <p style="margin-bottom: 10px; font-weight: 500;">1. Certificate of Registration as Log/Veneer/Lumber Importer</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>Not uploaded</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">2. Original Copy of Log/Veneer/Lumber Supply Contracts</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>Not uploaded</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">3. Proof of importation</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>Not uploaded</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sub-requirement" style="margin-top: 15px;">
                                    <p style="margin-bottom: 10px; font-weight: 500;">4. Monthly Production and Disposition Report</p>
                                    <div class="uploaded-files">
                                        <div class="file-item">
                                            <div class="file-info">
                                                <i class="fas fa-file-pdf file-icon"></i>
                                                <span>Not uploaded</span>
                                            </div>
                                            <div class="file-actions">
                                                <button class="file-action-btn view-file" data-file="" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="file-action-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fee Information -->
                        <div class="fee-info">
                            <p><strong>Application fee for WPP:</strong> ₱600.00</p>
                            <p><strong>Annual/Permit fee:</strong> ₱900.00</p>
                            <p><strong>Performance bond:</strong> ₱6,000.00</p>
                            <p><strong>Total Fee:</strong> ₱7,500.00</p>
                        </div>
                    </div>
                `;
            } else {
                // Edit mode - show editable fields
                html = `
                    <div class="edit-fields">
                        <div class="detail-row">
                            <div class="detail-label">STATUS:</div>
                            <select id="STATUS" class="detail-value-edit">
                                <option value="pending" ${record.STATUS === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="approved" ${record.STATUS === 'approved' ? 'selected' : ''}>Approved</option>
                                <option value="rejected" ${record.STATUS === 'rejected' ? 'selected' : ''}>Rejected</option>
                                <option value="expired" ${record.STATUS === 'expired' ? 'selected' : ''}>Expired</option>
                            </select>
                        </div>
                    </div>
                `;
            }
            
            modalBody.innerHTML = html;
            
            // Set up action buttons based on mode
            let actionButtons = '';
            if (mode === 'view') {
                actionButtons = ``;
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
            
            // Add event listeners for file preview
            document.querySelectorAll('.view-file').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileName = this.getAttribute('data-file');
                    if (!fileName) return;
                    
                    const modal = document.getElementById('filePreviewModal');
                    const modalFrame = document.getElementById('filePreviewFrame');
                    
                    document.getElementById('modal-title').textContent = `Preview: ${fileName}`;
                    modalFrame.srcdoc = `
                        <html>
                            <head>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        display: flex; 
                                        justify-content: center; 
                                        align-items: center; 
                                        height: 100vh; 
                                        margin: 0; 
                                        background-color: #f5f5f5;
                                    }
                                    .preview-content {
                                        text-align: center;
                                        padding: 20px;
                                    }
                                    .file-icon {
                                        font-size: 48px;
                                        color: #2b6625;
                                        margin-bottom: 20px;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="preview-content">
                                    <div class="file-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <h2>${fileName}</h2>
                                    <p>This is a preview of the uploaded file.</p>
                                </div>
                            </body>
                        </html>
                    `;
                    modal.style.display = "block";
                });
            });
        }
        
        function saveChanges() {
            // Collect all the edited values
            const status = document.getElementById('STATUS').value;
            
            const updatedData = {
                'STATUS': status
            };
            
            // In a real app, you would send this data to the server
            console.log('Updated data:', updatedData);
            
            // Update the table with the new data (in a real app, you would refresh from server)
            Object.assign(recordData[currentRecordId], updatedData);
            
            // Update the table row
            const tableRow = document.querySelector(`.view-btn[onclick*="'${currentRecordId}'"]`).closest('tr');
            tableRow.cells[5].textContent = updatedData['STATUS'] || recordData[currentRecordId]['STATUS'];
            
            alert('Changes saved successfully!');
            closeModal();
        }
        
        function cancelEdit() {
            // Restore original data
            if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
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
        
        // Close file preview modal
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('filePreviewModal').style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == document.getElementById('filePreviewModal')) {
                document.getElementById('filePreviewModal').style.display = 'none';
            }
        });
    </script>
</body>
</html>