<?php
/**
 * Disable Demo Mode
 * 
 * Direct script to disable demo mode without going to settings page
 */

// Include config functions
require_once 'config.php';

// Get current settings
$settings = loadSettings();

// Set demo mode to disabled
$settings['demo_mode'] = 'disabled';

// Save settings
$result = saveSettings($settings);

// Prepare the response message
if ($result) {
    $message = [
        'type' => 'success',
        'title' => 'Demo Mode Disabled',
        'text' => 'Demo mode has been successfully disabled. The system will now use only real API connections.'
    ];
} else {
    $message = [
        'type' => 'danger',
        'title' => 'Error',
        'text' => 'There was a problem disabling demo mode. Please check file permissions.'
    ];
}

// Redirect with message in query string for alert display
header('Location: index.php?message_type=' . $message['type'] . '&message_title=' . urlencode($message['title']) . '&message_text=' . urlencode($message['text']));
exit;
?>