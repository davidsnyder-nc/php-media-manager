#!/bin/bash

# Set the version number
VERSION=$(date +%Y.%m.%d.%H%M)
TAG_NAME="v$VERSION"
GITHUB_REPO="davidsnyder-nc/php-media-manager"

# Create release notes
cat > release_notes.md << EOL
# Release v$VERSION

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
EOL

# Create a GitHub release
echo "Creating GitHub release for v$VERSION..."
curl -X POST \
  -H "Authorization: token $GITHUB_TOKEN" \
  -H "Accept: application/vnd.github.v3+json" \
  -d "{
    \"tag_name\": \"$TAG_NAME\",
    \"target_commitish\": \"main\",
    \"name\": \"Release v$VERSION\",
    \"body\": $(cat release_notes.md | jq -Rs .),
    \"draft\": false,
    \"prerelease\": false
  }" \
  "https://api.github.com/repos/$GITHUB_REPO/releases" > release_response.json

# Extract the release ID from the response
RELEASE_ID=$(cat release_response.json | jq -r '.id')

if [ -z "$RELEASE_ID" ] || [ "$RELEASE_ID" == "null" ]; then
  echo "Failed to create release. Response:"
  cat release_response.json
  exit 1
fi

echo "Release created with ID: $RELEASE_ID"

# Upload the zip file as an asset
echo "Uploading package to release..."
curl -X POST \
  -H "Authorization: token $GITHUB_TOKEN" \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Content-Type: application/zip" \
  --data-binary @dist/php-media-manager.zip \
  "https://uploads.github.com/repos/$GITHUB_REPO/releases/$RELEASE_ID/assets?name=php-media-manager.zip"

echo "Release complete!"