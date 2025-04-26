<?php
/**
 * Enable Demo Mode
 * 
 * Direct script to enable demo mode without going to settings page
 */

// Include config functions
require_once 'config.php';

// Get current settings
$settings = loadSettings();

// Set demo mode to enabled
$settings['demo_mode'] = 'enabled';

// Save settings
$result = saveSettings($settings);

// Prepare the response message
if ($result) {
    $message = [
        'type' => 'success',
        'title' => 'Demo Mode Enabled',
        'text' => 'Demo mode has been successfully enabled. The system will now use sample data when API connections are not available.'
    ];
} else {
    $message = [
        'type' => 'danger',
        'title' => 'Error',
        'text' => 'There was a problem enabling demo mode. Please check file permissions.'
    ];
}

// Redirect with message in query string for alert display
header('Location: index.php?message_type=' . $message['type'] . '&message_title=' . urlencode($message['title']) . '&message_text=' . urlencode($message['text']));
exit;
?>