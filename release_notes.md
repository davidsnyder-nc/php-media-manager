# Release v2025.04.25.1501

## What's New

- **Performance Enhancements**: 
  - Added server-side image caching system to reduce API requests
  - Implemented browser-side caching with proper cache headers
  - Added lazy loading with IntersectionObserver to load images only when visible
  - Applied GPU acceleration hints for smoother rendering
  - Created image preloading for images that will soon enter the viewport

- **Download Improvements**:
  - Improved download package with cache directory structure
  - Added empty cache/images directory to package for immediate use

- **Bug Fixes**:
  - Fixed issues with repeated API calls for the same images

## Files

- **Full Package**: php-media-manager.zip - Complete website with all dependencies
