#!/bin/bash

# Set the version number
VERSION=$(date +%Y.%m.%d.%H%M)
TAG_NAME="v$VERSION"
GITHUB_REPO="davidsnyder-nc/php-media-manager"

# Create release notes
cat > release_notes.md << EOL
# Release v$VERSION

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