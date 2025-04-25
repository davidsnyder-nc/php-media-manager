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
    
    // Get current page for history pagination
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $itemsPerPage = 10; // Show 10 items per page
    
    // Get SABnzbd history data with pagination
    $historyData = getSabnzbdHistory($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], $currentPage, $itemsPerPage);
    
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
                <div class="d-flex align-items-center">
                    <div id="auto-refresh-status" class="me-3">
                        <small class="text-muted">
                            <i class="fa fa-sync-alt fa-spin me-1"></i> 
                            Auto-refreshing
                        </small>
                    </div>
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
            </div>
            
            <div id="queue-content">
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
                    
                    <?php if (isset($historyData['pagination']) && $historyData['pagination']['total_pages'] > 1): ?>
                        <nav aria-label="History pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php
                                $pagination = $historyData['pagination'];
                                $currentPage = $pagination['current_page'];
                                $totalPages = $pagination['total_pages'];
                                
                                // Previous button
                                if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?view=history&page=<?php echo ($currentPage - 1); ?>">Previous</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                <?php endif; 
                                
                                // Page numbers
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?view=history&page=1">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif;
                                endif;
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?view=history&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;
                                
                                if ($endPage < $totalPages): 
                                    if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?view=history&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                <?php endif;
                                
                                // Next button
                                if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?view=history&page=<?php echo ($currentPage + 1); ?>">Next</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center text-muted mb-3">
                            Showing <?php echo $pagination['items_per_page']; ?> items per page, 
                            <?php echo min($pagination['items_per_page'], $pagination['total_items'] - (($pagination['current_page'] - 1) * $pagination['items_per_page'])); ?> 
                            of <?php echo $pagination['total_items']; ?> total
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// Auto-update the SABnzbd queue every 10 seconds
function setupAutoUpdateSabnzbdQueue() {
    // Variable to store the auto-update interval ID
    let updateInterval;
    
    // Function to fetch and update the queue data
    function updateQueueData() {
        fetch('api.php?action=get_sabnzbd_queue')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.queue) {
                    // Update the queue content
                    updateQueueContent(data.queue);
                }
            })
            .catch(error => {
                console.error('Error updating SABnzbd queue:', error);
            });
    }
    
    // Function to update the queue content in the DOM
    function updateQueueContent(queueData) {
        const queueContent = document.getElementById('queue-content');
        
        if (!queueContent) return;
        
        // Create the queue HTML content
        let html = '';
        
        if (!queueData.slots || queueData.slots.length === 0) {
            html = `
                <div class="alert alert-info">
                    <h4><i class="fa fa-info-circle"></i> Queue Empty</h4>
                    <p>There are no active downloads in the queue.</p>
                </div>
            `;
        } else {
            html = '<div class="queue-list">';
            
            queueData.slots.forEach(slot => {
                const percentage = slot.percentage;
                const filename = escapeHtml(slot.filename);
                const size = formatSizeMB(slot.mb);
                const timeLeft = slot.timeleft || 'N/A';
                const status = slot.status;
                const statusClass = status.toLowerCase();
                
                html += `
                    <div class="queue-item">
                        <div class="item-header">
                            <h4 class="item-name">${filename}</h4>
                            <div class="item-actions">
                                <a href="api.php?action=pause_item&nzo_id=${slot.nzo_id}" class="btn btn-sm btn-outline-warning" data-action="pause-item">
                                    <i class="fa fa-pause"></i>
                                </a>
                                <a href="api.php?action=resume_item&nzo_id=${slot.nzo_id}" class="btn btn-sm btn-outline-success" data-action="resume-item">
                                    <i class="fa fa-play"></i>
                                </a>
                                <a href="api.php?action=delete_item&nzo_id=${slot.nzo_id}" class="btn btn-sm btn-outline-danger" data-action="delete-item" onclick="return confirm('Are you sure you want to delete this download?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="item-info">
                            <div class="item-stats">
                                <span><i class="fa fa-database"></i> ${size}</span>
                                <span><i class="fa fa-clock"></i> ${timeLeft}</span>
                                <span class="item-status ${statusClass}">${status}</span>
                            </div>
                        </div>
                        
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: ${percentage}%" 
                                 aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100">
                                ${percentage}%
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        queueContent.innerHTML = html;
        
        // Setup action buttons after content update
        setupActionButtons();
    }
    
    // Helper function to format size (MB to human-readable)
    function formatSizeMB(mbSize) {
        const size = parseFloat(mbSize);
        
        if (size >= 1024) {
            return (size / 1024).toFixed(2) + ' GB';
        } else {
            return size.toFixed(2) + ' MB';
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Setup action buttons to use AJAX instead of direct page reload
    function setupActionButtons() {
        document.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = this.getAttribute('href');
                const action = this.getAttribute('data-action');
                
                // If the action requires confirmation and the user cancels, do nothing
                if ((action === 'delete-item' || action === 'clear-history' || action === 'delete-history-item') && 
                    !confirm('Are you sure you want to ' + (action === 'delete-item' ? 'delete this download?' : 
                                                            action === 'delete-history-item' ? 'delete this history item?' : 
                                                            'clear the history?'))) {
                    return;
                }
                
                // Perform the action via AJAX
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.message);
                            
                            // Update queue data immediately after action
                            updateQueueData();
                        } else {
                            showAlert('danger', data.message || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error performing action:', error);
                        showAlert('danger', 'An error occurred while performing the action');
                    });
            });
        });
    }
    
    // Start the auto-update interval
    updateInterval = setInterval(updateQueueData, 10000); // Update every 10 seconds
    
    // Initial setup
    setupActionButtons();
    
    // Return a function to stop the auto-updates
    return function stopAutoUpdate() {
        clearInterval(updateInterval);
    };
}

// Initialize the auto-update feature
document.addEventListener('DOMContentLoaded', function() {
    const stopAutoUpdate = setupAutoUpdateSabnzbdQueue();
    
    // Stop auto-update when leaving the page
    window.addEventListener('beforeunload', stopAutoUpdate);
});

// Function to show alerts
function showAlert(type, message, timeout = 5000) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to the top of the content area
    const contentArea = document.querySelector('.content');
    if (contentArea) {
        contentArea.insertBefore(alertDiv, contentArea.firstChild);
        
        // Auto dismiss after timeout
        if (timeout > 0) {
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 150);
            }, timeout);
        }
    }
}
</script>
