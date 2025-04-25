<?php
/**
 * PHP Media Manager - Direct Download File
 *
 * This file provides a direct download of the PHP Media Manager package
 */

// Define the path to the zip file
$zipFile = 'dist/php-media-manager.zip';

// Check if the file exists
if (file_exists($zipFile)) {
    // Get the file size
    $fileSize = filesize($zipFile);
    
    // Set the appropriate headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="php-media-manager.zip"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache');
    header('Pragma: public');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Output the file
    readfile($zipFile);
    exit;
} else {
    // File not found
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Error 404: File Not Found</h1>';
    echo '<p>The requested file could not be found. Please go back to the <a href="/">homepage</a> and try again.</p>';
}
?>