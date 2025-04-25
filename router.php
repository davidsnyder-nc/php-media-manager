<?php
/**
 * PHP Media Manager - Router
 * 
 * This simple router file allows the application to handle URLs properly
 * when running in the bundled server or locally.
 */

// Get the requested path
$uri = $_SERVER['REQUEST_URI'];

// Parse query parameters
$query = '';
if (strpos($uri, '?') !== false) {
    list($uri, $query) = explode('?', $uri, 2);
}

// Remove trailing slashes
$uri = rtrim($uri, '/');

// Default to index if empty
if (empty($uri)) {
    $uri = '/index.php';
} elseif ($uri === '/') {
    $uri = '/index.php';
}

// Get the file extension
$ext = pathinfo($uri, PATHINFO_EXTENSION);

// For PHP files, include them
if ($ext === 'php') {
    // Remove leading slash
    $filename = substr($uri, 1);
    
    // Check if the file exists
    if (file_exists($filename)) {
        // Pass the query string if it exists
        if (!empty($query)) {
            $_SERVER['QUERY_STRING'] = $query;
            parse_str($query, $_GET);
        }
        
        // Include the PHP file
        include $filename;
    } else {
        // 404 Not Found
        header("HTTP/1.0 404 Not Found");
        echo "File not found: $filename";
    }
}
// For static files, serve them directly
else {
    // Remove leading slash
    $filename = substr($uri, 1);
    
    if (file_exists($filename)) {
        // Set the appropriate content type
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $contentTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'html' => 'text/html',
            'txt' => 'text/plain'
        ];
        
        $contentType = isset($contentTypes[$ext]) ? $contentTypes[$ext] : 'application/octet-stream';
        header("Content-Type: $contentType");
        
        // Output the file
        readfile($filename);
    } else {
        // 404 Not Found
        header("HTTP/1.0 404 Not Found");
        echo "File not found: $filename";
    }
}