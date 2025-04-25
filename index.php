<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Initialize API data containers
$sonarrData = [];
$radarrData = [];
$sabnzbdData = [];

// Check if we have all necessary settings
$hasAllSettings = !empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key']) &&
                  !empty($settings['radarr_url']) && !empty($settings['radarr_api_key']) &&
                  !empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key']);

// If we have settings, fetch data from the APIs
if ($hasAllSettings) {
    // Get Sonarr data
    if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key'])) {
        $sonarrData = getSonarrOverview($settings['sonarr_url'], $settings['sonarr_api_key']);
    }

    // Get Radarr data
    if (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key'])) {
        $radarrData = getRadarrOverview($settings['radarr_url'], $settings['radarr_api_key']);
        $upcomingMovies = getUpcomingMovies($settings['radarr_url'], $settings['radarr_api_key']);
    }

    // Get SABnzbd data
    if (!empty($settings['sabnzbd_url']) && !empty($settings['sabnzbd_api_key'])) {
        $sabnzbdData = getSabnzbdQueue($settings['sabnzbd_url'], $settings['sabnzbd_api_key']);
    }
}

$pageTitle = "Media Manager Dashboard";
require_once 'includes/header.php';
?>

<div class="dashboard-container">
    <?php if (!$hasAllSettings): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your API settings to connect to Sonarr, Radarr, and SABnzbd.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php else: ?>
        <!-- SABnzbd Section -->
        <section class="card">
            <div class="card-header">
                <h2><i class="fa fa-download"></i> SABnzbd Queue</h2>
                <div>
                    <a href="sabnzbd.php?view=history" class="btn btn-sm btn-secondary me-2">View History</a>
                    <a href="sabnzbd.php" class="btn btn-sm btn-primary">View Queue</a>
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
                            <button class="nav-link" id="shows-tab" data-bs-toggle="tab" data-bs-target="#shows" type="button" role="tab" aria-controls="shows" aria-selected="false">Recently Added</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="showTabsContent">
                        <div class="tab-pane fade show active" id="episodes" role="tabpanel" aria-labelledby="episodes-tab">
                            <?php
                            $upcomingEpisodes = getUpcomingEpisodes($settings['sonarr_url'], $settings['sonarr_api_key']);
                            if (empty($upcomingEpisodes)): 
                            ?>
                                <div class="alert alert-info">No upcoming episodes</div>
                            <?php else: ?>
                                <div class="episode-list">
                                    <?php foreach (array_slice($upcomingEpisodes, 0, 5) as $episode): ?>
                                        <div class="episode-item">
                                            <div class="episode-date"><?php echo date('M d', strtotime($episode['airDate'])); ?></div>
                                            <div class="episode-info">
                                                <?php if (isset($episode['series']['id'])): ?>
                                                <a href="show_details.php?id=<?php echo $episode['series']['id']; ?>" class="episode-show-link">
                                                    <div class="episode-show"><?php echo isset($episode['series']['title']) && $episode['series']['title'] !== 'Unknown Show' ? htmlspecialchars($episode['series']['title']) : htmlspecialchars($episode['seriesTitle'] ?? 'Unknown Show'); ?></div>
                                                </a>
                                                <?php else: ?>
                                                <div class="episode-show"><?php echo isset($episode['series']['title']) && $episode['series']['title'] !== 'Unknown Show' ? htmlspecialchars($episode['series']['title']) : htmlspecialchars($episode['seriesTitle'] ?? 'Unknown Show'); ?></div>
                                                <?php endif; ?>
                                                <div class="episode-title">S<?php echo str_pad($episode['seasonNumber'], 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($episode['episodeNumber'], 2, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($episode['title']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="shows" role="tabpanel" aria-labelledby="shows-tab">
                            <div class="media-grid">
                                <?php 
                                $recentShows = array_slice($sonarrData, 0, 6);
                                foreach ($recentShows as $show): 
                                ?>
                                    <div class="media-item">
                                        <a href="show_details.php?id=<?php echo $show['id']; ?>">
                                            <?php if (!empty($show['images'])): ?>
                                                <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($show); ?>');"></div>
                                            <?php else: ?>
                                                <div class="media-poster no-image">
                                                    <i class="fa fa-tv"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="media-title"><?php echo htmlspecialchars($show['title']); ?></div>
                                            <?php if (isset($show['year'])): ?>
                                                <div class="media-year"><?php echo $show['year']; ?></div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
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
                            <button class="nav-link active" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button" role="tab" aria-controls="recent" aria-selected="true">Recent</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="false">Upcoming</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="movieTabsContent">
                        <div class="tab-pane fade show active" id="recent" role="tabpanel" aria-labelledby="recent-tab">
                            <div class="media-grid">
                                <?php 
                                $recentMovies = array_slice($radarrData, 0, 12);
                                foreach ($recentMovies as $movie): 
                                ?>
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
                                            <?php if (isset($movie['year'])): ?>
                                                <div class="media-year"><?php echo $movie['year']; ?></div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
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
