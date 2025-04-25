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
    
    // Initialize lazy loading for images
    setupLazyLoading();
    
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

/**
 * Set up lazy loading for media posters and other images
 * This dramatically improves page load performance by loading images only when they're visible
 */
function setupLazyLoading() {
    // Check if IntersectionObserver is supported
    if ('IntersectionObserver' in window) {
        const mediaPosters = document.querySelectorAll('.media-poster');
        const imageLoadingObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const poster = entry.target;
                    const backgroundImage = getComputedStyle(poster).backgroundImage;
                    
                    // Only process elements with background images that use URL
                    if (backgroundImage && backgroundImage.includes('url("api.php?action=proxy_image')) {
                        const match = backgroundImage.match(/url\("([^"]+)"\)/);
                        if (match && match[1]) {
                            // We need to keep the original URL, but load the high quality version in advance
                            const imageUrl = match[1];
                            
                            // Pre-fetch the image
                            const img = new Image();
                            img.onload = function() {
                                // Once loaded, apply a transition effect
                                poster.style.opacity = '0.6';
                                poster.style.transition = 'opacity 0.3s ease-in';
                                
                                // Apply the cached image
                                setTimeout(() => {
                                    poster.style.backgroundImage = `url("${imageUrl}")`;
                                    poster.style.opacity = '1';
                                }, 50);
                                
                                // Add a loaded data attribute to avoid reprocessing
                                poster.setAttribute('data-loaded', 'true');
                                
                                // Stop observing this element
                                observer.unobserve(poster);
                            };
                            img.src = imageUrl;
                        }
                    }
                }
            });
        }, {
            rootMargin: '100px', // Load images that are within 100px of the viewport
            threshold: 0.1 // Trigger when at least 10% of the item is visible
        });
        
        // Start observing all poster elements
        mediaPosters.forEach(poster => {
            if (!poster.hasAttribute('data-loaded')) {
                // Set minimal opacity for better transition
                poster.style.opacity = '0.6';
                imageLoadingObserver.observe(poster);
            }
        });
    }
    
    // Create a placeholder image cache for future page loads
    cacheCommonImages();
}

/**
 * Pre-cache frequently used images for better performance
 */
function cacheCommonImages() {
    // Track recently loaded images to avoid redundant caching
    const recentlyLoaded = JSON.parse(localStorage.getItem('recentlyLoadedImages') || '[]');
    const maxTrackedImages = 50; // Maximum number of recently viewed images to track
    
    // Get all currently visible posters
    const visiblePosters = Array.from(document.querySelectorAll('.media-poster'))
        .filter(poster => {
            const rect = poster.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        });
        
    // Extract image URLs from the posters
    const imageUrls = visiblePosters
        .map(poster => {
            const backgroundImage = getComputedStyle(poster).backgroundImage;
            const match = backgroundImage.match(/url\("([^"]+)"\)/);
            return match ? match[1] : null;
        })
        .filter(url => url && !recentlyLoaded.includes(url));
    
    // Cache new images
    if (imageUrls.length > 0) {
        // Add to recently loaded list
        const updatedRecent = [...imageUrls, ...recentlyLoaded].slice(0, maxTrackedImages);
        localStorage.setItem('recentlyLoadedImages', JSON.stringify(updatedRecent));
        
        // Prefetch these images
        imageUrls.forEach(url => {
            const img = new Image();
            img.src = url;
        });
    }
}
