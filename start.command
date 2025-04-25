#!/bin/bash

# Get the directory where this script is located
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Please install PHP first."
    echo "You can install PHP using Homebrew: brew install php"
    echo "Press any key to exit..."
    read -n 1
    exit 1
fi

echo "Starting PHP Media Manager..."
echo "Once the server starts, access the application at: http://localhost:8000"
echo "Press Ctrl+C to stop the server"

# Start the PHP server with router
php -S localhost:8000 router.php

# This will only execute if the PHP server is stopped
echo "PHP server stopped. Press any key to exit..."
read -n 1