<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Initialize variables
$queueData = [];
$historyData = [];
$serverStatus = [];

// Check if we have necessary settings
if (!empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key'])) {
    // Get SABnzbd queue data
    $queueData = getSabnzbdQueue($settings['sabnzbd_url'], $settings['sabnzbd_api_key']);
    
    // Get SABnzbd history data
    $historyData = getSabnzbdHistory($settings['sabnzbd_url'], $settings['sabnzbd_api_key']);
    
    // Get SABnzbd server status
    $serverStatus = getSabnzbdStatus($settings['sabnzbd_url'], $settings['sabnzbd_api_key']);
}

$pageTitle = "Downloads - SABnzbd";
require_once 'includes/header.php';
?>

<div class="sabnzbd-container">
    <div class="page-header">
        <h1><i class="fa fa-download"></i> SABnzbd Downloads</h1>
    </div>
    
    <?php if (empty($settings['sabnzbd_url']) || empty($settings['sabnzbd_api_key'])): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your SABnzbd API settings to view downloads.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php else: ?>
        <?php if (!empty($serverStatus)): ?>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-tachometer-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo formatSpeed($queueData['kbpersec'] ?? 0); ?></div>
                        <div class="stat-label">Current Speed</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo isset($queueData['timeleft']) ? $queueData['timeleft'] : 'N/A'; ?></div>
                        <div class="stat-label">Time Remaining</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-database"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo formatSize($queueData['mb'] ?? 0); ?></div>
                        <div class="stat-label">Queue Size</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $serverStatus['status'] ?? 'Unknown'; ?></div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Queue Section -->
        <section class="sabnzbd-section">
            <div class="section-header">
                <h2>Download Queue</h2>
                <?php if (!empty($queueData) && !empty($queueData['slots'])): ?>
                    <div class="section-actions">
                        <a href="api.php?action=pause_queue" class="btn btn-sm btn-warning" data-action="pause-queue">
                            <i class="fa fa-pause"></i> Pause Queue
                        </a>
                        <a href="api.php?action=resume_queue" class="btn btn-sm btn-success" data-action="resume-queue">
                            <i class="fa fa-play"></i> Resume Queue
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($queueData) || empty($queueData['slots'])): ?>
                <div class="alert alert-info">
                    <h4><i class="fa fa-info-circle"></i> Queue Empty</h4>
                    <p>There are no active downloads in the queue.</p>
                </div>
            <?php else: ?>
                <div class="queue-list">
                    <?php foreach ($queueData['slots'] as $slot): ?>
                        <div class="queue-item">
                            <div class="item-header">
                                <h4 class="item-name"><?php echo htmlspecialchars($slot['filename']); ?></h4>
                                <div class="item-actions">
                                    <a href="api.php?action=pause_item&nzo_id=<?php echo $slot['nzo_id']; ?>" class="btn btn-sm btn-outline-warning" data-action="pause-item">
                                        <i class="fa fa-pause"></i>
                                    </a>
                                    <a href="api.php?action=resume_item&nzo_id=<?php echo $slot['nzo_id']; ?>" class="btn btn-sm btn-outline-success" data-action="resume-item">
                                        <i class="fa fa-play"></i>
                                    </a>
                                    <a href="api.php?action=delete_item&nzo_id=<?php echo $slot['nzo_id']; ?>" class="btn btn-sm btn-outline-danger" data-action="delete-item" onclick="return confirm('Are you sure you want to delete this download?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="item-info">
                                <div class="item-stats">
                                    <span><i class="fa fa-database"></i> <?php echo formatSize($slot['mb']); ?></span>
                                    <span><i class="fa fa-clock"></i> <?php echo isset($slot['timeleft']) ? $slot['timeleft'] : 'N/A'; ?></span>
                                    <span class="item-status <?php echo strtolower($slot['status']); ?>"><?php echo $slot['status']; ?></span>
                                </div>
                            </div>
                            
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $slot['percentage']; ?>%" 
                                     aria-valuenow="<?php echo $slot['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $slot['percentage']; ?>%
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- History Section -->
        <section class="sabnzbd-section">
            <div class="section-header">
                <h2>Download History</h2>
                <?php if (!empty($historyData) && !empty($historyData['slots'])): ?>
                    <div class="section-actions">
                        <a href="api.php?action=clear_history" class="btn btn-sm btn-outline-danger" data-action="clear-history" onclick="return confirm('Are you sure you want to clear the history?')">
                            <i class="fa fa-trash"></i> Clear History
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($historyData) || empty($historyData['slots'])): ?>
                <div class="alert alert-info">
                    <h4><i class="fa fa-info-circle"></i> History Empty</h4>
                    <p>There are no completed downloads in the history.</p>
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($historyData['slots'] as $slot): ?>
                        <div class="history-item">
                            <div class="item-header">
                                <h4 class="item-name"><?php echo htmlspecialchars($slot['name']); ?></h4>
                                <div class="item-actions">
                                    <a href="api.php?action=retry_item&nzo_id=<?php echo $slot['nzo_id']; ?>" class="btn btn-sm btn-outline-primary" data-action="retry-item">
                                        <i class="fa fa-redo"></i> Retry
                                    </a>
                                    <a href="api.php?action=delete_history_item&nzo_id=<?php echo $slot['nzo_id']; ?>" class="btn btn-sm btn-outline-danger" data-action="delete-history-item" onclick="return confirm('Are you sure you want to delete this history item?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="item-info">
                                <div class="item-stats">
                                    <span><i class="fa fa-database"></i> <?php echo formatSize($slot['size']); ?></span>
                                    <span><i class="fa fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($slot['completed'])); ?></span>
                                    <span class="item-status <?php echo strtolower($slot['status']); ?>"><?php echo $slot['status']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
