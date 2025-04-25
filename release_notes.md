# Release v2025.04.25.1931

## What's New

- **User Interface Improvements**:
  - Added real-time SABnzbd queue auto-update functionality (refreshes every 10 seconds)
  - Fixed button alignment on small displays
  - Improved width handling for the 'Upcoming Episodes' section
  - Enhanced AJAX-based queue management (pause, resume, delete) without page reloads

- **Performance Enhancements**: 
  - Added server-side image caching system to reduce API requests
  - Implemented browser-side caching with proper cache headers
  - Added lazy loading with IntersectionObserver to load images only when visible
  - Applied GPU acceleration hints for smoother rendering
  - Created image preloading for images that will soon enter the viewport

- **Download Improvements**:
  - Improved download package with cache directory structure
  - Added empty cache/images directory to package for immediate use

- **API Enhancements**:
  - Added new API endpoint for retrieving SABnzbd queue data
  - Improved error handling across all API endpoints

## Files

- **Full Package**: php-media-manager.zip - Complete website with all dependencies
