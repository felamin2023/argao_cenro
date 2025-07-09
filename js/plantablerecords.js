

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
            // Get the modal elements
            const releaseModal = document.getElementById("releaseModal");
            const discardModal = document.getElementById("discardModal");
            const closeBtns = document.querySelectorAll(".close");
            const cancelReleaseBtn = document.getElementById("cancelRelease");
            const cancelDiscardBtn = document.getElementById("cancelDiscard");
            const releaseForm = document.getElementById("releaseForm");
            const discardForm = document.getElementById("discardForm");
            
            // Get display elements
            const modalPlantableId = document.getElementById("modalPlantableId");
            const modalSpecies = document.getElementById("modalSpecies");
            const modalPlantableQty = document.getElementById("modalPlantableQty");
            
            const discardPlantableId = document.getElementById("discardPlantableId");
            const discardSpecies = document.getElementById("discardSpecies");
            const discardAvailableQty = document.getElementById("discardAvailableQty");

            // Release and Discard buttons functionality
            const releaseButtons = document.querySelectorAll(".release-btn");
            const discardButtons = document.querySelectorAll(".discard-btn");

            releaseButtons.forEach(button => {
                button.addEventListener("click", (e) => {
                    const row = e.target.closest('tr');
                    
                    // Get data from the row
                    const plantableId = row.getAttribute('data-plantable-id');
                    const species = row.getAttribute('data-species');
                    const plantable = row.getAttribute('data-plantable');
                    
                    // Set the modal values
                    modalPlantableId.textContent = plantableId;
                    modalSpecies.textContent = species;
                    modalPlantableQty.textContent = plantable;
                    
                    // Set max value for seedlings availed
                    document.getElementById("seedlingsAvailed").max = plantable;
                    
                    // Show the modal
                    releaseModal.style.display = "block";
                });
            });

            discardButtons.forEach(button => {
                button.addEventListener("click", (e) => {
                    const row = e.target.closest('tr');
                    
                    // Get data from the row
                    const plantableId = row.getAttribute('data-plantable-id');
                    const species = row.getAttribute('data-species');
                    const plantable = row.getAttribute('data-plantable');
                    
                    // Set the modal values
                    discardPlantableId.textContent = plantableId;
                    discardSpecies.textContent = species;
                    discardAvailableQty.textContent = plantable;
                    
                    // Set max value for discard quantity
                    document.getElementById("discardQuantity").max = plantable;
                    document.getElementById("discardQuantity").value = "";
                    document.getElementById("discardRemarks").value = "";
                    
                    // Show the modal
                    discardModal.style.display = "block";
                });
            });

            // Close modals when clicking X or cancel buttons
            closeBtns.forEach(btn => {
                btn.addEventListener("click", () => {
                    releaseModal.style.display = "none";
                    discardModal.style.display = "none";
                });
            });

            cancelReleaseBtn.addEventListener("click", () => {
                releaseModal.style.display = "none";
            });

            cancelDiscardBtn.addEventListener("click", () => {
                discardModal.style.display = "none";
            });

            // Close modals when clicking outside of them
            window.addEventListener("click", (e) => {
                if (e.target === releaseModal) {
                    releaseModal.style.display = "none";
                }
                if (e.target === discardModal) {
                    discardModal.style.display = "none";
                }
            });

            // Release form submission
            releaseForm.addEventListener("submit", (e) => {
                e.preventDefault();
                
                // Get form values
                const plantableId = modalPlantableId.textContent;
                const partnerType = document.getElementById("partnerType").value;
                const seedlingsAvailed = document.getElementById("seedlingsAvailed").value;
                const plantingSite = document.getElementById("plantingSite").value;
                const areaPlanted = document.getElementById("areaPlanted").value;
                const commodity = document.getElementById("commodity").value;
                const dateDisposed = document.getElementById("dateDisposed").value;
                
                // Validate seedlings availed doesn't exceed plantable quantity
                if (parseInt(seedlingsAvailed) > parseInt(modalPlantableQty.textContent)) {
                    alert(`Cannot release more than ${modalPlantableQty.textContent} seedlings!`);
                    return;
                }
                
                // Here you would typically send this data to the server
                console.log("Release form submitted:", {
                    plantableId,
                    species: modalSpecies.textContent,
                    plantableQuantity: modalPlantableQty.textContent,
                    partnerType,
                    seedlingsAvailed,
                    plantingSite,
                    areaPlanted,
                    commodity,
                    dateDisposed
                });
                
                // Show success message
                alert(`Seedlings ${plantableId} released successfully!`);
                
                // Close the modal
                releaseModal.style.display = "none";
                
                // You might want to update the table row here
                // For example, reduce the plantable quantity
                const row = document.querySelector(`tr[data-plantable-id="${plantableId}"]`);
                if (row) {
                    const newPlantable = parseInt(row.getAttribute('data-plantable')) - parseInt(seedlingsAvailed);
                    row.setAttribute('data-plantable', newPlantable);
                    row.cells[4].textContent = newPlantable;
                    modalPlantableQty.textContent = newPlantable;
                }
            });

            // Discard form submission
            discardForm.addEventListener("submit", (e) => {
                e.preventDefault();
                
                // Get form values
                const plantableId = discardPlantableId.textContent;
                const quantity = document.getElementById("discardQuantity").value;
                const date = document.getElementById("discardDate").value;
                const remarks = document.getElementById("discardRemarks").value;
                
                // Validate quantity doesn't exceed available quantity
                if (parseInt(quantity) > parseInt(discardAvailableQty.textContent)) {
                    alert(`Cannot discard more than ${discardAvailableQty.textContent} seedlings!`);
                    return;
                }
                
                // Validate reason is provided
                if (!remarks.trim()) {
                    alert("Please provide a reason for discard");
                    return;
                }
                
                // Here you would typically send this data to the server
                console.log("Discard form submitted:", {
                    plantableId,
                    species: discardSpecies.textContent,
                    availableQuantity: discardAvailableQty.textContent,
                    quantity,
                    date,
                    reason: remarks
                });
                
                // Show success message
                alert(`Seedlings ${plantableId} discarded successfully!`);
                
                // Close the modal
                discardModal.style.display = "none";
                
                // You might want to update the table row here
                // For example, reduce the plantable quantity
                const row = document.querySelector(`tr[data-plantable-id="${plantableId}"]`);
                if (row) {
                    const newPlantable = parseInt(row.getAttribute('data-plantable')) - parseInt(quantity);
                    row.setAttribute('data-plantable', newPlantable);
                    row.cells[4].textContent = newPlantable;
                    discardAvailableQty.textContent = newPlantable;
                    
                    // Also update the damage count
                    const currentDamage = parseInt(row.cells[3].textContent) || 0;
                    row.cells[3].textContent = currentDamage + parseInt(quantity);
                }
            });

            // Save button functionality
            const saveButton = document.querySelector(".add-record");
            saveButton.addEventListener("click", () => {
                alert("All changes saved successfully!");
                // Add code to save all changes to database here
            });
        });
   