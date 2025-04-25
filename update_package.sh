#!/bin/bash
#
# PHP Media Manager Package Builder
# This script creates a downloadable ZIP package with all necessary files
# for the PHP Media Manager application.
#

echo "=== PHP Media Manager Package Builder ==="
echo "Creating package version $(date +'%Y.%m.%d')"

# Check for zip command
if ! command -v zip &> /dev/null; then
    echo "Error: zip command not found. Please install zip first."
    exit 1
fi

# Create a temporary directory
echo "Creating temporary directory..."
mkdir -p temp_package

# Copy all PHP files
echo "Copying PHP files..."
cp -R api.php config.php index.php movie_details.php radarr.php sabnzbd.php settings.php show_details.php phpinfo.php main.py temp_package/

# Copy directories
echo "Copying includes, CSS, and JavaScript..."
cp -R includes temp_package/
cp -R css temp_package/
cp -R js temp_package/

# Copy launcher script
echo "Copying launcher script..."
cp start.command temp_package/

# Make sure the launcher is executable
chmod +x temp_package/start.command

# Create the dist directory if it doesn't exist
echo "Setting up distribution directory..."
mkdir -p dist

# Backup existing package if it exists
if [ -f dist/php-media-manager.zip ]; then
    echo "Backing up existing package..."
    mv dist/php-media-manager.zip dist/php-media-manager.zip.bak
fi

# Create the zip file
echo "Creating zip package..."
cd temp_package
zip -r ../dist/php-media-manager.zip ./*
cd ..

# Get package size
PACKAGE_SIZE=$(du -h dist/php-media-manager.zip | cut -f1)

# Clean up
echo "Cleaning up temporary files..."
rm -rf temp_package

echo "============================================"
echo "Package created successfully!"
echo "Location: dist/php-media-manager.zip"
echo "Size: $PACKAGE_SIZE"
echo "============================================"