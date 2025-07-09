        // Get the input element
        const treesCutInput = document.getElementById('trees-cut');
        
        // Increment function
        function incrementValue() {
            let value = parseInt(treesCutInput.value);
            if (isNaN(value)) value = 0;
            if (value < 1000) {
                treesCutInput.value = value + 1;
                calculateSeedlings();
            }
        }
        
        // Decrement function
        function decrementValue() {
            let value = parseInt(treesCutInput.value);
            if (isNaN(value)) value = 1;
            if (value > 1) {
                treesCutInput.value = value - 1;
                calculateSeedlings();
            }
        }
        
        // Seedlings increment/decrement functions
        function seedlingsIncrementValue() {
            const input = document.getElementById('seedlings-delivered');
            let value = parseInt(input.value) || 0;
            input.value = value + 1;
        }
        
        function seedlingsDecrementValue() {
            const input = document.getElementById('seedlings-delivered');
            let value = parseInt(input.value) || 0;
            if (value > 0) {
                input.value = value - 1;
            }
        }
        
        // Calculate required seedlings
        function calculateSeedlings() {
            const treesCut = parseInt(treesCutInput.value) || 0;
            const requiredSeedlings = treesCut * 100;
            document.getElementById('required-seedlings').textContent = requiredSeedlings;
        }
        
        // Initialize calculation
        calculateSeedlings();
        
        // Listen for input changes
        treesCutInput.addEventListener('input', calculateSeedlings);
        
        // Initialize when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const modal = document.getElementById('species-modal');
            const addBtn = document.getElementById('add-species-btn');
            const cancelBtn = document.getElementById('cancel-species');
            const saveBtn = document.getElementById('save-species');
            const speciesList = document.getElementById('species-list-items');
            const speciesDataContainer = document.getElementById('species-data-container');

            // Open modal when Add Another Species is clicked
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    console.log('Add button clicked'); // Debug log
                    if (modal) {
                        // Reset form values
                        const speciesSelect = document.getElementById('modal-species');
                        const seedlingsInput = document.getElementById('modal-seedlings');
                        
                        if (speciesSelect) speciesSelect.value = '';
                        if (seedlingsInput) seedlingsInput.value = '0';
                        
                        // Show modal
                        modal.style.display = 'block';
                    }
                });
            }

            // Close modal when Cancel is clicked
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    console.log('Cancel button clicked'); // Debug log
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Save species data
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    console.log('Save button clicked'); // Debug log
                    const species = document.getElementById('modal-species')?.value;
                    const seedlings = document.getElementById('modal-seedlings')?.value;
                    
                    if (species && seedlings > 0) {
                        // Add to the visible list
                        const speciesItem = document.createElement('div');
                        speciesItem.className = 'species-item';
                        speciesItem.innerHTML = `
                            <div class="species-info">
                                <span class="species-name">${species}</span>
                                <span class="species-quantity">${seedlings} seedlings</span>
                            </div>
                            <button type="button" class="remove-species" onclick="removeSpecies(this)">Remove</button>
                            <input type="hidden" name="species[]" value="${species}">
                            <input type="hidden" name="seedlings_delivered[]" value="${seedlings}">
                        `;
                        
                        if (speciesList) {
                            speciesList.appendChild(speciesItem);
                        }
                        
                        // Add to hidden container for form submission
                        if (speciesDataContainer) {
                            const hiddenInput1 = document.createElement('input');
                            hiddenInput1.type = 'hidden';
                            hiddenInput1.name = 'species[]';
                            hiddenInput1.value = species;
                            
                            const hiddenInput2 = document.createElement('input');
                            hiddenInput2.type = 'hidden';
                            hiddenInput2.name = 'seedlings_delivered[]';
                            hiddenInput2.value = seedlings;
                            
                            speciesDataContainer.appendChild(hiddenInput1);
                            speciesDataContainer.appendChild(hiddenInput2);
                        }
                        
                        // Clear form and close modal
                        const speciesSelect = document.getElementById('modal-species');
                        const seedlingsInput = document.getElementById('modal-seedlings');
                        
                        if (speciesSelect) speciesSelect.value = '';
                        if (seedlingsInput) seedlingsInput.value = '0';
                        if (modal) modal.style.display = 'none';
                        
                    } else {
                        alert('Please select a species and enter a valid number of seedlings delivered');
                    }
                });
            }
        });
        
        // Function to remove species
        function removeSpecies(button) {
            const item = button.parentElement;
            const speciesList = document.getElementById('species-list-items');
            const speciesDataContainer = document.getElementById('species-data-container');
            
            if (speciesList && speciesDataContainer) {
                speciesList.removeChild(item);
                
                // Remove from hidden container
                const species = item.querySelector('input[name="species[]"]')?.value;
                const seedlings = item.querySelector('input[name="seedlings_delivered[]"]')?.value;
                
                if (species && seedlings) {
                    const hiddenInputs = speciesDataContainer.querySelectorAll('input');
                    hiddenInputs.forEach(input => {
                        if ((input.name === "species[]" && input.value === species) || 
                            (input.name === "seedlings_delivered[]" && input.value === seedlings)) {
                            speciesDataContainer.removeChild(input);
                        }
                    });
                }
            }
        }

        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
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

        // Modal number input functions
        function modalIncrementValue() {
            const input = document.getElementById('modal-seedlings');
            if (input) {
                let value = parseInt(input.value) || 0;
                input.value = value + 1;
            }
        }

        function modalDecrementValue() {
            const input = document.getElementById('modal-seedlings');
            if (input) {
                let value = parseInt(input.value) || 0;
                if (value > 0) {
                    input.value = value - 1;
                }
            }
        }
