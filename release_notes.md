# Release v2025.04.25.1941

## What's New

- **Front Page Improvements**:
  - Fixed "Upcoming Episodes" section to fill the entire width of its container
  - Changed "Recently Added" tabs to show "Recently Downloaded" content
  - Added new functions to fetch and display recently downloaded content from SABnzbd
  - Enhanced display of downloaded TV shows and movies on front page

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

- **API Enhancements**:
  - Added new API endpoint for retrieving SABnzbd queue data
  - Added function to get recently downloaded content from SABnzbd history
  - Improved error handling across all API endpoints

## Files

- **Full Package**: php-media-manager.zip - Complete website with all dependencies
