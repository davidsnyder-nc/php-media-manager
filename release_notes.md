# Release v2025.04.26.0955

## What's New

- **UI Improvements**:
  - Completely removed footer for cleaner interface
  - Improved layout for better viewing experience

- **API Integration Enhancements**:
  - Modified to get recently downloaded TV shows directly from Sonarr API (not SABnzbd)
  - Modified to get recently downloaded movies directly from Radarr API (not SABnzbd)
  - Consistent display of 6 items per section in TV shows and movies
  - Enhanced error handling for API connections

- **Bug Fixes**:
  - Fixed "Unknown Show" issue in upcoming episodes display
  - Improved error handling to prevent missing show information
  - Better fallback handling when API connections are unavailable

- **Code Quality**:
  - Enhanced error checking throughout the application
  - Optimized API data retrieval functions
  - More robust handling of empty or error responses

## Files

- **Full Package**: php-media-manager.zip - Complete website with all dependencies
