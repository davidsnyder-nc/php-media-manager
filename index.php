<?php
/**
 * PHP Media Manager - Main Entry Point
 * 
 * This file serves as the main entry point for the PHP Media Manager application.
 * It loads necessary configurations and displays the dashboard.
 */

require_once 'config.php';
require_once 'includes/functions.php';

// Determine which page to load
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Get the route path
$basePath = dirname($scriptName);
if ($basePath == '/') $basePath = '';  // Adjust for root installs
$path = str_replace($basePath, '', $requestUri);
$path = preg_replace('/\?.*$/', '', $path);  // Remove query string

// Load settings from the config file
$settings = loadSettings();

// Initialize API data containers
$sonarrData = [];
$radarrData = [];
$sabnzbdData = [];

// Check for demo mode
$demoMode = isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled';

// Debug output for troubleshooting
error_log("Demo mode check - Raw value: " . ($settings['demo_mode'] ?? 'not set'));
error_log("Demo mode evaluation result: " . ($demoMode ? 'true' : 'false'));

// Check if we have all necessary settings
$hasAllSettings = (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key']) &&
                  !empty($settings['radarr_url']) && !empty($settings['radarr_api_key']) &&
                  !empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key'])) || $demoMode;

// Always fetch data if demo mode is enabled or if we have settings
// First, check if we have actual API settings or if demo mode is enabled
if ($demoMode || $hasAllSettings) {
    // Get Sonarr data
    if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key'])) {
        $sonarrData = getSonarrOverview($settings['sonarr_url'], $settings['sonarr_api_key'], $demoMode);
    } elseif ($demoMode) {
        // In demo mode, use sample data
        $sonarrData = getSampleTvShows();
    } else {
        $sonarrData = [];
    }

    // Get Radarr data
    if (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key'])) {
        $radarrData = getRadarrOverview($settings['radarr_url'], $settings['radarr_api_key'], $demoMode);
        $upcomingMovies = getUpcomingMovies($settings['radarr_url'], $settings['radarr_api_key'], $demoMode);
    } elseif ($demoMode) {
        // In demo mode, get sample movie data
        $radarrData = getSampleMovies();
        $upcomingMovies = getSampleUpcomingMovies();
    } else {
        $radarrData = [];
        $upcomingMovies = [];
    }

    // Get SABnzbd data
    // Get download queue if SABnzbd settings are available
    if (!empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key'])) {
        $sabnzbdData = getSabnzbdQueue($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], $demoMode);
    } elseif ($demoMode) {
        $sabnzbdData = getSampleSabnzbdQueue();
    } else {
        $sabnzbdData = [];
    }
    
    // Get recently downloaded TV shows directly from Sonarr
    if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key'])) {
        $recentTvShows = getRecentlyDownloadedTvShows($settings['sonarr_url'], $settings['sonarr_api_key'], 6, $demoMode);
    } elseif ($demoMode) {
        $recentTvShows = getRecentlyDownloadedTvShows('', '', 6, true);
    } else {
        $recentTvShows = [];
    }
    
    // Get recently downloaded movies directly from Radarr
    if (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key'])) {
        $recentMovies = getRecentlyDownloadedMovies($settings['radarr_url'], $settings['radarr_api_key'], 6, $demoMode);
    } elseif ($demoMode) {
        $recentMovies = getRecentlyDownloadedMovies('', '', 6, true);
    } else {
        $recentMovies = [];
    }
    
    // Combine the recent downloads for any code still expecting the combined array
    $recentDownloads = array_merge($recentTvShows, $recentMovies);
    
    // In demo mode, make sure we have all necessary data
    if ($demoMode) {
        // Ensure we have Sonarr data
        if (empty($sonarrData)) {
            $sonarrData = getSampleTvShows();
        }
        
        // Ensure we have Radarr data
        if (empty($radarrData)) {
            $radarrData = getSampleMovies();
        }
        
        // Ensure we have upcoming movies
        if (empty($upcomingMovies)) {
            $upcomingMovies = getSampleUpcomingMovies();
        }
        
        // Ensure we have SABnzbd data
        if (empty($sabnzbdData)) {
            $sabnzbdData = getSampleSabnzbdQueue();
        }
        
        // Ensure we have recent downloads
        if (empty($recentDownloads)) {
            $demoHistory = getSampleSabnzbdHistory(12);
            $historySlots = isset($demoHistory['slots']) ? $demoHistory['slots'] : [];
            $recentDownloads = processRecentDownloads(
                $historySlots,
                $sonarrData,
                $radarrData,
                true // force demo mode
            );
        }
    }
}

$pageTitle = "Media Manager Dashboard";
require_once 'includes/header.php';
?>

<div class="dashboard-container">
    <?php if (!$demoMode && !$hasAllSettings): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your API settings to connect to Sonarr, Radarr, and SABnzbd, or enable Demo Mode in settings.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php else: ?>
        <!-- SABnzbd Section -->
        <section class="card">
            <div class="card-header">
                <h2><i class="fa fa-download"></i> Downloads</h2>
                <div class="btn-toolbar">
                    <div class="btn-group">
                        <a href="sabnzbd.php?view=history" class="btn btn-sm btn-secondary">View History</a>
                        <a href="sabnzbd.php" class="btn btn-sm btn-primary">View Queue</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($sabnzbdData)): ?>
                    <div class="alert alert-info">No active downloads</div>
                <?php else: ?>
                    <div class="queue-stats">
                        <div class="stat-box">
                            <span class="stat-value"><?php echo formatSize($sabnzbdData['mb']); ?></span>
                            <span class="stat-label">Queue Size</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo formatSpeed($sabnzbdData['kbpersec']); ?></span>
                            <span class="stat-label">Download Speed</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><?php echo isset($sabnzbdData['timeleft']) ? $sabnzbdData['timeleft'] : 'N/A'; ?></span>
                            <span class="stat-label">Time Left</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($sabnzbdData['slots'])): ?>
                        <h4>Current Downloads</h4>
                        <div class="queue-items">
                            <?php foreach (array_slice($sabnzbdData['slots'], 0, 3) as $slot): ?>
                                <div class="queue-item">
                                    <div class="item-name"><?php echo htmlspecialchars($slot['filename']); ?></div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $slot['percentage']; ?>%" 
                                             aria-valuenow="<?php echo $slot['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $slot['percentage']; ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($sabnzbdData['slots']) > 3): ?>
                                <div class="more-link">
                                    <a href="sabnzbd.php">View all <?php echo count($sabnzbdData['slots']); ?> downloads</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Sonarr Section -->
        <section class="card">
            <div class="card-header">
                <h2><i class="fa fa-tv"></i> TV Shows</h2>
                <a href="sonarr.php" class="btn btn-sm btn-primary">View All Shows</a>
            </div>
            <div class="card-body">
                <?php if (empty($sonarrData)): ?>
                    <div class="alert alert-info">No TV shows found or unable to connect to Sonarr</div>
                <?php else: ?>
                    <ul class="nav nav-tabs mb-3" id="showTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="episodes-tab" data-bs-toggle="tab" data-bs-target="#episodes" type="button" role="tab" aria-controls="episodes" aria-selected="true">Upcoming Episodes</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shows-tab" data-bs-toggle="tab" data-bs-target="#tvshows" type="button" role="tab" aria-controls="tvshows" aria-selected="false">Recently Downloaded</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="showTabsContent">
                        <div class="tab-pane fade show active" id="episodes" role="tabpanel" aria-labelledby="episodes-tab">
                            <?php
                            $upcomingEpisodes = getUpcomingEpisodes($settings['sonarr_url'], $settings['sonarr_api_key'], $demoMode);
                            
                            // Debug output - remove after fixing
                            if (!empty($upcomingEpisodes)) {
                                echo "<!-- Debug: " . count($upcomingEpisodes) . " upcoming episodes found -->";
                                echo "<!-- First episode: ";
                                echo "Date: " . ($upcomingEpisodes[0]['displayDate'] ?? 'N/A') . ", ";
                                echo "Show: " . ($upcomingEpisodes[0]['series']['title'] ?? $upcomingEpisodes[0]['seriesTitle'] ?? 'N/A');
                                echo " -->";
                            } else {
                                echo "<!-- Debug: No upcoming episodes found -->";
                            }
                            
                            if (empty($upcomingEpisodes)): 
                            ?>
                                <div class="alert alert-info">No upcoming episodes</div>
                            <?php else: ?>
                                <div class="episode-list">
                                    <?php foreach (array_slice($upcomingEpisodes, 0, 5) as $episode): ?>
                                        <div class="episode-item">
                                            <!-- Display date (Today, Tomorrow, day of week) -->
                                            <div class="episode-date">
                                                <?php 
                                                if (isset($episode['displayDate'])) {
                                                    echo $episode['displayDate'];
                                                } else {
                                                    $airDate = strtotime($episode['airDate']);
                                                    $today = strtotime('today');
                                                    $tomorrow = strtotime('tomorrow');
                                                    
                                                    if ($airDate == $today) {
                                                        echo 'Today';
                                                    } else if ($airDate == $tomorrow) {
                                                        echo 'Tomorrow';
                                                    } else {
                                                        echo date('l', $airDate); // Weekday name
                                                    }
                                                }
                                                ?>
                                            </div>
                                            
                                            <!-- Display show name and episode number on new line -->
                                            <div class="episode-info">
                                                <?php if (isset($episode['series']['id'])): ?>
                                                <a href="show_details.php?id=<?php echo $episode['series']['id']; ?>" class="episode-show-link">
                                                    <span class="episode-show"><?php echo isset($episode['series']['title']) && $episode['series']['title'] !== 'Unknown Show' ? htmlspecialchars($episode['series']['title']) : htmlspecialchars($episode['seriesTitle'] ?? 'Unknown Show'); ?></span>
                                                </a>
                                                <?php else: ?>
                                                <span class="episode-show"><?php echo isset($episode['series']['title']) && $episode['series']['title'] !== 'Unknown Show' ? htmlspecialchars($episode['series']['title']) : htmlspecialchars($episode['seriesTitle'] ?? 'Unknown Show'); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($episode['seasonNumber']) && isset($episode['episodeNumber'])): ?>
                                                <span class="episode-number text-muted ms-1">S<?php echo str_pad($episode['seasonNumber'], 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($episode['episodeNumber'], 2, '0', STR_PAD_LEFT); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="tvshows" role="tabpanel" aria-labelledby="shows-tab">
                            <div class="media-grid">
                            <?php 
                            if (empty($recentTvShows)):
                            ?>
                                <div class="alert alert-info">No recently downloaded TV shows found</div>
                            <?php 
                            else:
                                foreach ($recentTvShows as $download): 
                                    $hasImage = !empty($download['image']);
                                    $showTitle = !empty($download['clean_name']) ? $download['clean_name'] : $download['name'];
                                    
                                    // If there are multiple episodes, add count to the title
                                    $episodeInfo = '';
                                    if (isset($download['episodes_count']) && $download['episodes_count'] > 1) {
                                        $episodeInfo = ' <span class="badge bg-primary">' . $download['episodes_count'] . ' episodes</span>';
                                    } elseif (isset($download['season']) && isset($download['episode'])) {
                                        $episodeInfo = ' <span class="text-muted">S' . str_pad($download['season'], 2, '0', STR_PAD_LEFT) . 
                                            'E' . str_pad($download['episode'], 2, '0', STR_PAD_LEFT) . '</span>';
                                    }
                            ?>
                                <div class="media-item">
                                    <div class="download-item">
                                        <?php if (isset($download['show_id'])): ?>
                                        <a href="show_details.php?id=<?php echo $download['show_id']; ?>" class="text-decoration-none">
                                        <?php endif; ?>
                                        
                                        <?php if ($hasImage): ?>
                                            <div class="media-poster" style="background-image: url('<?php echo htmlspecialchars($download['image']); ?>')"></div>
                                        <?php else: ?>
                                            <div class="media-poster no-image">
                                                <i class="fa fa-tv"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="media-title">
                                            <?php echo htmlspecialchars($showTitle); ?>
                                            <?php echo $episodeInfo; ?>
                                        </div>
                                        
                                        <?php if (isset($download['show_id'])): ?>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <div class="media-year"><?php echo date('M j, Y', strtotime($download['completed'])); ?></div>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Radarr Section -->
        <section class="card">
            <div class="card-header">
                <h2><i class="fa fa-film"></i> Movies</h2>
                <a href="radarr.php" class="btn btn-sm btn-primary">View All Movies</a>
            </div>
            <div class="card-body">
                <?php if (empty($radarrData)): ?>
                    <div class="alert alert-info">No movies found or unable to connect to Radarr</div>
                <?php else: ?>
                    <ul class="nav nav-tabs mb-3" id="movieTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="recent-movies-tab" data-bs-toggle="tab" data-bs-target="#recent-movies" type="button" role="tab" aria-controls="recent-movies" aria-selected="true">Recently Downloaded</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="upcoming-movies-tab" data-bs-toggle="tab" data-bs-target="#upcoming-movies" type="button" role="tab" aria-controls="upcoming-movies" aria-selected="false">Upcoming</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="movieTabsContent">
                        <div class="tab-pane fade show active" id="recent-movies" role="tabpanel" aria-labelledby="recent-movies-tab">
                            <div class="media-grid">
                                <?php 
                                if (empty($recentMovies)):
                                ?>
                                    <div class="alert alert-info">No recently downloaded movies found</div>
                                <?php 
                                else:
                                    foreach ($recentMovies as $download): 
                                        $hasImage = !empty($download['image']);
                                        $movieTitle = !empty($download['clean_name']) ? $download['clean_name'] : $download['name'];
                                        $movieYear = isset($download['year']) ? ' (' . $download['year'] . ')' : '';
                                ?>
                                    <div class="media-item">
                                        <div class="download-item">
                                            <?php if (isset($download['movie_id'])): ?>
                                            <a href="movie_details.php?id=<?php echo $download['movie_id']; ?>" class="text-decoration-none">
                                            <?php endif; ?>
                                            
                                            <?php if ($hasImage): ?>
                                                <div class="media-poster" style="background-image: url('<?php echo htmlspecialchars($download['image']); ?>')"></div>
                                            <?php else: ?>
                                                <div class="media-poster no-image">
                                                    <i class="fa fa-film"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="media-title">
                                                <?php echo htmlspecialchars($movieTitle); ?>
                                                <?php echo $movieYear; ?>
                                            </div>
                                            
                                            <?php if (isset($download['movie_id'])): ?>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <div class="media-year"><?php echo date('M j, Y', strtotime($download['completed'])); ?></div>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="upcoming-movies" role="tabpanel" aria-labelledby="upcoming-movies-tab">
                            <?php if (empty($upcomingMovies)): ?>
                                <div class="alert alert-info">No upcoming movies found</div>
                            <?php else: ?>
                                <div class="media-grid">
                                    <?php foreach ($upcomingMovies as $movie): ?>
                                        <div class="media-item">
                                            <a href="movie_details.php?id=<?php echo $movie['id']; ?>">
                                                <?php if (!empty($movie['images'])): ?>
                                                    <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($movie); ?>');"></div>
                                                <?php else: ?>
                                                    <div class="media-poster no-image">
                                                        <i class="fa fa-film"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="media-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                                <div class="media-year">
                                                    <?php if (isset($movie['inCinemas']) && !empty($movie['inCinemas'])): ?>
                                                        <?php echo date('M j, Y', strtotime($movie['inCinemas'])); ?>
                                                    <?php elseif (isset($movie['year'])): ?>
                                                        <?php echo $movie['year']; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>