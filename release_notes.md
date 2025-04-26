# Release v2025.04.26.0919

## What's New

- **Demo Mode Toggle Support**:
  - Added disable_demo_mode.php script to fully disable demo mode directly
  - Updated demo_status.php to conditionally show demo mode toggle links
  - Fixed critical issue that was forcing demo mode to remain enabled
  - Fixed configuration handling to properly respect demo mode settings

- **Bug Fixes**:
  - Removed forced demo mode override in config.php
  - Added diagnostic logging to help troubleshoot demo mode issues
  - Fixed settings display on demo status page
  - Improved consistency in demo mode behavior

- **UI Improvements**:
  - Enhanced stats blocks alignment for better mobile display
  - Fixed footer width issues on mobile devices
  - Updated upcoming episodes display for better readability

- **Development Tools**:
  - Added disable_demo_mode.php for directly disabling demo mode
  - Improved debug output for settings status
  - Fixed issues with demo mode toggling

# Release v2025.04.26.0845

## What's New

- **Demo Mode Improvements**:
  - Enhanced demo mode to work without requiring any API configuration
  - Fixed demo data handling in TV shows, movies, and downloads sections
  - Added robust error handling for demo mode data
  - Added new demo status page for checking configuration
  - Fixed sorting functions to handle null values properly

- **Bug Fixes**:
  - Fixed issues with TV show and movie detail pages in demo mode
  - Improved error handling throughout the application
  - Fixed display issues on smaller screens
  - Corrected sorting functions for shows and movies

- **Performance Enhancements**: 
  - Added more comprehensive error checking
  - Optimized demo mode data handling
  - Fixed array handling to prevent PHP warnings

- **Development Tools**:
  - Added demo_status.php for checking demo mode configuration
  - Added enable_demo_mode.php for directly enabling demo mode
  - Enhanced error logging for easier debugging

## Files

- **Full Package**: php-media-manager.zip - Complete website with all dependencies
