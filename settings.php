<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Initialize variables
$settings = loadSettings();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug - log the POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    // Gather settings from the form
    $settings = [
        'sonarr_url' => isset($_POST['sonarr_url']) ? trim($_POST['sonarr_url']) : '',
        'sonarr_api_key' => isset($_POST['sonarr_api_key']) ? trim($_POST['sonarr_api_key']) : '',
        'radarr_url' => isset($_POST['radarr_url']) ? trim($_POST['radarr_url']) : '',
        'radarr_api_key' => isset($_POST['radarr_api_key']) ? trim($_POST['radarr_api_key']) : '',
        'sabnzbd_url' => isset($_POST['sabnzbd_url']) ? trim($_POST['sabnzbd_url']) : '',
        'sabnzbd_api_key' => isset($_POST['sabnzbd_api_key']) ? trim($_POST['sabnzbd_api_key']) : '',
        'theme' => isset($_POST['theme']) ? $_POST['theme'] : 'light',
        'demo_mode' => isset($_POST['demo_mode']) ? $_POST['demo_mode'] : 'disabled',
    ];
    
    // Debug - log the settings that will be saved
    error_log("Settings to be saved: " . print_r($settings, true));
    
    // Validate URLs
    $urls = ['sonarr_url', 'radarr_url', 'sabnzbd_url'];
    $valid = true;
    
    foreach ($urls as $urlKey) {
        if (!empty($settings[$urlKey]) && !filter_var($settings[$urlKey], FILTER_VALIDATE_URL)) {
            $valid = false;
            $message = "Invalid URL format for {$urlKey}. Please include the protocol (http:// or https://).";
            $messageType = 'danger';
            break;
        }
    }
    
    // Test connections if requested
    if (isset($_POST['test_connections']) && $valid) {
        $connectionResults = testConnections($settings);
        $allSuccessful = true;
        
        foreach ($connectionResults as $service => $result) {
            if (!$result['success']) {
                $allSuccessful = false;
                break;
            }
        }
        
        if ($allSuccessful) {
            $message = 'All connections successful!';
            $messageType = 'success';
        } else {
            $message = 'One or more connections failed. Please check the connection details below.';
            $messageType = 'warning';
        }
    }
    
    // Save settings if valid
    if ($valid) {
        if (saveSettings($settings)) {
            if (empty($message)) {
                $message = 'Settings saved successfully!';
                $messageType = 'success';
            }
        } else {
            $message = 'Failed to save settings. Please check file permissions.';
            $messageType = 'danger';
        }
    }
}

// Test connections function
function testConnections($settings) {
    $results = [];
    $demoMode = isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled';
    
    // If demo mode is enabled, return success for all connections
    if ($demoMode) {
        return [
            'sonarr' => ['success' => true, 'message' => 'Demo mode enabled', 'version' => 'Demo v1.0'],
            'radarr' => ['success' => true, 'message' => 'Demo mode enabled', 'version' => 'Demo v1.0'],
            'sabnzbd' => ['success' => true, 'message' => 'Demo mode enabled', 'version' => 'Demo v1.0']
        ];
    }
    
    // Test Sonarr
    if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key'])) {
        $sonarrStatus = testApiConnection($settings['sonarr_url'], $settings['sonarr_api_key'], 'sonarr');
        $results['sonarr'] = $sonarrStatus;
    } else {
        $results['sonarr'] = ['success' => false, 'message' => 'URL or API key not provided'];
    }
    
    // Test Radarr
    if (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key'])) {
        $radarrStatus = testApiConnection($settings['radarr_url'], $settings['radarr_api_key'], 'radarr');
        $results['radarr'] = $radarrStatus;
    } else {
        $results['radarr'] = ['success' => false, 'message' => 'URL or API key not provided'];
    }
    
    // Test SABnzbd
    if (!empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key'])) {
        $sabnzbdStatus = testApiConnection($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'sabnzbd');
        $results['sabnzbd'] = $sabnzbdStatus;
    } else {
        $results['sabnzbd'] = ['success' => false, 'message' => 'URL or API key not provided'];
    }
    
    return $results;
}

// If testing connections was requested
$connectionResults = [];
if (isset($_GET['test']) && $_GET['test'] === 'true') {
    $connectionResults = testConnections($settings);
}

$pageTitle = "Settings";
require_once 'includes/header.php';
?>

<div class="settings-container">
    <div class="page-header">
        <h1><i class="fa fa-cog"></i> Settings</h1>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="post" action="settings.php">
                <div class="settings-section">
                    <h3><i class="fa fa-tv"></i> Sonarr Settings</h3>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="sonarr_url" class="form-label">Sonarr URL</label>
                            <input type="text" class="form-control" id="sonarr_url" name="sonarr_url" 
                                   placeholder="http://localhost:8989" 
                                   value="<?php echo htmlspecialchars($settings['sonarr_url'] ?? ''); ?>">
                            <div class="form-text">Include the protocol (http:// or https://) and port number if required</div>
                        </div>
                        <div class="col-md-6">
                            <label for="sonarr_api_key" class="form-label">Sonarr API Key</label>
                            <input type="text" class="form-control" id="sonarr_api_key" name="sonarr_api_key" 
                                   placeholder="Your API key" 
                                   value="<?php echo htmlspecialchars($settings['sonarr_api_key'] ?? ''); ?>">
                            <div class="form-text">Found in Sonarr under Settings > General</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($connectionResults['sonarr'])): ?>
                        <div class="connection-status">
                            <?php if ($connectionResults['sonarr']['success']): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> Sonarr connection successful!
                                    <?php if (!empty($connectionResults['sonarr']['version'])): ?>
                                        <div class="version-info">Version: <?php echo $connectionResults['sonarr']['version']; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fa fa-times-circle"></i> Sonarr connection failed: 
                                    <?php echo htmlspecialchars($connectionResults['sonarr']['message']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="settings-section">
                    <h3><i class="fa fa-film"></i> Radarr Settings</h3>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="radarr_url" class="form-label">Radarr URL</label>
                            <input type="text" class="form-control" id="radarr_url" name="radarr_url" 
                                   placeholder="http://localhost:7878" 
                                   value="<?php echo htmlspecialchars($settings['radarr_url'] ?? ''); ?>">
                            <div class="form-text">Include the protocol (http:// or https://) and port number if required</div>
                        </div>
                        <div class="col-md-6">
                            <label for="radarr_api_key" class="form-label">Radarr API Key</label>
                            <input type="text" class="form-control" id="radarr_api_key" name="radarr_api_key" 
                                   placeholder="Your API key" 
                                   value="<?php echo htmlspecialchars($settings['radarr_api_key'] ?? ''); ?>">
                            <div class="form-text">Found in Radarr under Settings > General</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($connectionResults['radarr'])): ?>
                        <div class="connection-status">
                            <?php if ($connectionResults['radarr']['success']): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> Radarr connection successful!
                                    <?php if (!empty($connectionResults['radarr']['version'])): ?>
                                        <div class="version-info">Version: <?php echo $connectionResults['radarr']['version']; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fa fa-times-circle"></i> Radarr connection failed: 
                                    <?php echo htmlspecialchars($connectionResults['radarr']['message']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="settings-section">
                    <h3><i class="fa fa-download"></i> SABnzbd Settings</h3>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="sabnzbd_url" class="form-label">SABnzbd URL</label>
                            <input type="text" class="form-control" id="sabnzbd_url" name="sabnzbd_url" 
                                   placeholder="http://localhost:8080" 
                                   value="<?php echo htmlspecialchars($settings['sabnzbd_url'] ?? ''); ?>">
                            <div class="form-text">Include the protocol (http:// or https://) and port number if required</div>
                        </div>
                        <div class="col-md-6">
                            <label for="sabnzbd_api_key" class="form-label">SABnzbd API Key</label>
                            <input type="text" class="form-control" id="sabnzbd_api_key" name="sabnzbd_api_key" 
                                   placeholder="Your API key" 
                                   value="<?php echo htmlspecialchars($settings['sabnzbd_api_key'] ?? ''); ?>">
                            <div class="form-text">Found in SABnzbd under Config > General</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($connectionResults['sabnzbd'])): ?>
                        <div class="connection-status">
                            <?php if ($connectionResults['sabnzbd']['success']): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> SABnzbd connection successful!
                                    <?php if (!empty($connectionResults['sabnzbd']['version'])): ?>
                                        <div class="version-info">Version: <?php echo $connectionResults['sabnzbd']['version']; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fa fa-times-circle"></i> SABnzbd connection failed: 
                                    <?php echo htmlspecialchars($connectionResults['sabnzbd']['message']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="settings-section">
                    <h3><i class="fa fa-paint-brush"></i> Appearance</h3>
                    <div class="mb-3">
                        <label class="form-label">Theme</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="theme_light" value="light" 
                                  <?php echo (!isset($settings['theme']) || $settings['theme'] === 'light') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="theme_light">
                                Light
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="theme_dark" value="dark" 
                                  <?php echo (isset($settings['theme']) && $settings['theme'] === 'dark') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="theme_dark">
                                Dark
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3><i class="fa fa-cubes"></i> Development Options</h3>
                    <div class="mb-3">
                        <label class="form-label">Demo Mode</label>
                        <div class="form-text mb-2">Demo mode displays sample content when API connections are not available or configured.</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="demo_mode" id="demo_enabled" value="enabled" 
                                  <?php echo (isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="demo_enabled">
                                <strong>Enabled</strong> - Show sample data for development and testing
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="demo_mode" id="demo_disabled" value="disabled" 
                                  <?php echo (!isset($settings['demo_mode']) || $settings['demo_mode'] === 'disabled') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="demo_disabled">
                                <strong>Disabled</strong> - Only show real data from API connections (recommended for production)
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="settings-actions">
                    <div class="checkbox mb-3">
                        <input type="checkbox" id="test_connections" name="test_connections" value="1" checked>
                        <label for="test_connections">Test connections when saving</label>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <a href="settings.php?test=true" class="btn btn-secondary">Test Connections</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
