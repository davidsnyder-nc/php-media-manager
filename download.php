<?php
/**
 * PHP Media Manager - Direct Download File
 *
 * This file provides a direct download of the PHP Media Manager package
 */

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Define the path to the zip file
$zipFile = 'dist/php-media-manager.zip';

// Check if the file exists
if (file_exists($zipFile)) {
    // Get the file size
    $fileSize = filesize($zipFile);
    
    // Set the appropriate headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="php-media-manager.zip"');
    header('Content-Length: ' . $fileSize);
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Read the file in chunks to handle large files better
    $handle = fopen($zipFile, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            $buffer = fread($handle, 1024 * 1024); // Read 1MB at a time
            echo $buffer;
            flush();
        }
        fclose($handle);
    }
    exit;
} else {
    // File not found
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Error 404: File Not Found</h1>';
    echo '<p>The requested file could not be found. Please go back to the <a href="/">homepage</a> and try again.</p>';
}
?>