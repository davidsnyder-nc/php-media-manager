#!/bin/bash

# Extract the version number from release_notes.md
VERSION=$(grep -m 1 "^# Release v" release_notes.md | sed 's/^# Release v//')
if [ -z "$VERSION" ]; then
  # Fallback if not found
  VERSION=$(date +%Y.%m.%d.%H%M)
  echo "Warning: Version not found in release_notes.md, using current date/time: $VERSION"
fi

TAG_NAME="v$VERSION"
GITHUB_REPO="davidsnyder-nc/php-media-manager"

# Don't overwrite release_notes.md as it's manually created now

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