/**
 * Main JavaScript file for the Media Manager
 * 
 * Handles client-side interactivity and AJAX requests
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
    
    // Handle SABnzbd action buttons
    const sabnzbdButtons = document.querySelectorAll('[data-action]');
    sabnzbdButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.getAttribute('data-action');
            const href = this.getAttribute('href');
            
            // Some actions require confirmation
            const requiresConfirmation = [
                'delete-item', 
                'clear-history', 
                'delete-history-item'
            ];
            
            if (requiresConfirmation.includes(action) && !confirm('Are you sure you want to perform this action?')) {
                return;
            }
            
            // Perform the API call
            fetch(href)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        // Reload the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'An error occurred: ' + error.message);
                });
        });
    });
    
    // Use the global showAlert function
    
    // Theme toggle functionality
    const themeRadios = document.querySelectorAll('input[name="theme"]');
    themeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            document.body.setAttribute('data-theme', this.value);
            localStorage.setItem('theme', this.value);
        });
    });
    
    // Apply saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.body.setAttribute('data-theme', savedTheme);
        const themeRadio = document.querySelector(`input[name="theme"][value="${savedTheme}"]`);
        if (themeRadio) {
            themeRadio.checked = true;
        }
    }
    
    // Handle search forms
    const searchForms = document.querySelectorAll('.search-form');
    searchForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Only prevent default if search field is empty
            const searchInput = this.querySelector('input[type="text"]');
            if (searchInput && searchInput.value.trim() === '') {
                e.preventDefault();
            }
        });
    });
    
    // Handle mobile navigation menu toggle
    const navbarToggler = document.querySelector('.navbar-toggler');
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function() {
            const navbarCollapse = document.querySelector('.navbar-collapse');
            if (navbarCollapse) {
                navbarCollapse.classList.toggle('show');
            }
        });
    }
    
    // Handle forms for adding new content
    setupAddContentForms();
});

/**
 * Formats file size in bytes to a human-readable string
 * 
 * @param {number} bytes - The size in bytes
 * @param {number} decimals - Number of decimal places (default: 2)
 * @return {string} Formatted size with unit
 */
function formatSize(bytes, decimals = 2) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Formats a date string to a more readable format
 * 
 * @param {string} dateString - The date string to format
 * @return {string} Formatted date string
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

/**
 * Sets up form handling for adding new content (shows and movies)
 */
function setupAddContentForms() {
    const addForms = document.querySelectorAll('.add-media-form');
    
    addForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable submit button and show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
            
            // Get form data
            const formData = new FormData(this);
            
            // Send the request
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // If successful, remove the item from display or disable button
                    const mediaItem = this.closest('.media-item');
                    if (mediaItem) {
                        // Add a "success" overlay
                        const overlay = document.createElement('div');
                        overlay.className = 'position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
                        overlay.style.backgroundColor = 'rgba(40, 167, 69, 0.8)';
                        overlay.style.borderRadius = '8px';
                        overlay.style.zIndex = '15';
                        overlay.innerHTML = '<i class="fa fa-check-circle fa-3x text-white"></i>';
                        mediaItem.querySelector('.position-relative').appendChild(overlay);
                        
                        // Remove the form
                        this.remove();
                    }
                } else {
                    // Show error and restore button
                    showAlert('danger', data.message || 'Failed to add content');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                showAlert('danger', 'An error occurred while adding content');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    });
}

/**
 * Displays an alert message to the user
 * 
 * @param {string} type - Alert type ('success', 'danger', 'warning', 'info')
 * @param {string} message - The message to display
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        // Create alert container if it doesn't exist
        const container = document.createElement('div');
        container.id = 'alert-container';
        container.className = 'alert-floating-container';
        document.body.appendChild(container);
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.getElementById('alert-container').appendChild(alert);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => {
            alert.remove();
        }, 150);
    }, 5000);
}
