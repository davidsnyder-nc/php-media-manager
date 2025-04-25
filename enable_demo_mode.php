<?php
/**
 * Quick script to directly enable demo mode
 */

// Include config functions
require_once 'config.php';

// Get current settings
$settings = loadSettings();

// Force demo mode to enabled
$settings['demo_mode'] = 'enabled';

// Save settings
$result = saveSettings($settings);

// Show result
if ($result) {
    echo "Demo mode has been enabled successfully!<br>";
    echo '<a href="index.php">Go to Dashboard</a>';
} else {
    echo "Failed to enable demo mode. Check file permissions.<br>";
    
    // Try to debug the issue
    echo "<h3>Debug Information:</h3>";
    echo "CONFIG_FILE path: " . CONFIG_FILE . "<br>";
    echo "Is writable: " . (is_writable(CONFIG_FILE) ? "Yes" : "No") . "<br>";
    echo "Current settings: <pre>" . print_r($settings, true) . "</pre>";
}
?>