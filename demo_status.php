<?php
/**
 * Demo Mode Status Check
 */

// Include config functions
require_once 'config.php';

// Get current settings
$settings = loadSettings();

// Check demo mode
$demoMode = isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled';

// Output HTML
echo "<!DOCTYPE html>
<html>
<head>
  <title>Demo Mode Status</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
    .enabled { background-color: #d4edda; color: #155724; }
    .disabled { background-color: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <h1>Demo Mode Status</h1>
  
  <div class='status ".($demoMode ? 'enabled' : 'disabled')."'>
    Demo Mode is currently <strong>".($demoMode ? 'ENABLED' : 'DISABLED')."</strong>
  </div>
  
  <h2>Settings Details</h2>
  <pre>".print_r($settings, true)."</pre>
  
  <p>
    <a href='index.php'>Back to Dashboard</a> | 
    <a href='enable_demo_mode.php'>Enable Demo Mode</a> | 
    <a href='settings.php'>Settings Page</a>
  </p>
</body>
</html>";
?>