<?php
/**
 * Configuration file for the Media Manager
 * 
 * This file handles loading and saving settings and defines constants
 */

// Define application constants
define('APP_NAME', 'Media Manager');
define('APP_VERSION', '1.0.0');
define('CONFIG_FILE', __DIR__ . '/config.json');

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone (change this to your local timezone)
date_default_timezone_set('UTC');

/**
 * Load settings from the config file
 * 
 * @return array The settings array
 */
function loadSettings() {
    if (file_exists(CONFIG_FILE)) {
        $config = file_get_contents(CONFIG_FILE);
        $settings = json_decode($config, true);
        
        if (is_array($settings)) {
            return $settings;
        }
    }
    
    // Return default settings if config file doesn't exist or is invalid
    return [
        'sonarr_url' => '',
        'sonarr_api_key' => '',
        'radarr_url' => '',
        'radarr_api_key' => '',
        'sabnzbd_url' => '',
        'sabnzbd_api_key' => '',
        'theme' => 'light',
        'demo_mode' => 'enabled', // Enable demo mode by default for development
    ];
}

/**
 * Save settings to the config file
 * 
 * @param array $settings The settings to save
 * @return bool Whether the settings were saved successfully
 */
function saveSettings($settings) {
    // Debug - log the settings being saved
    error_log("saveSettings called with: " . print_r($settings, true));
    
    // Validate the settings array
    if (!is_array($settings)) {
        error_log("saveSettings failed: settings is not an array");
        return false;
    }
    
    // Make sure we have all required keys
    $requiredKeys = [
        'sonarr_url', 'sonarr_api_key',
        'radarr_url', 'radarr_api_key',
        'sabnzbd_url', 'sabnzbd_api_key',
        'theme', 'demo_mode'
    ];
    
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = '';
        }
    }
    
    // Encode the settings as JSON
    $config = json_encode($settings, JSON_PRETTY_PRINT);
    
    // Debug - log the JSON being written
    error_log("JSON to write: " . $config);
    
    // Save the settings to the config file
    $result = file_put_contents(CONFIG_FILE, $config) !== false;
    
    // Debug - log the result
    error_log("file_put_contents result: " . ($result ? "success" : "failure"));
    
    return $result;
}
