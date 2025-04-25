# Media Manager

A comprehensive media management dashboard for Mac that integrates with Sonarr, Radarr, and SABnzbd.

## Features

- Clean, modern responsive design that works on all devices
- Integration with Sonarr, Radarr, and SABnzbd via their APIs
- Dedicated pages for each show and movie displaying detailed information
- Dashboard showing basic information from all services
- Settings page for API keys and service URLs
- Downloadable and hostable on a Mac without additional services/modules

## Requirements

- PHP 7.0 or higher (comes pre-installed on Mac)
- Active internet connection
- Access to Sonarr, Radarr, and SABnzbd servers

## Installation

1. Download the Media Manager package
2. Extract the contents to a location of your choice
3. Start the PHP built-in web server:

```bash
cd /path/to/media-manager
php -S 0.0.0.0:5000
