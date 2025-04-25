<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Get show ID from URL parameter
$showId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize show data variables
$show = [];
$seasons = [];
$episodes = [];

// Check if we have necessary settings and valid show ID
if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key']) && $showId > 0) {
    // Get show details
    $show = getSonarrShowDetails($settings['sonarr_url'], $settings['sonarr_api_key'], $showId);
    
    // Get show seasons and episodes
    if (!empty($show)) {
        $episodes = getSonarrEpisodes($settings['sonarr_url'], $settings['sonarr_api_key'], $showId);
        
        // Organize episodes by season
        $seasons = [];
        foreach ($episodes as $episode) {
            $seasonNumber = $episode['seasonNumber'];
            if (!isset($seasons[$seasonNumber])) {
                $seasons[$seasonNumber] = [
                    'seasonNumber' => $seasonNumber,
                    'episodes' => [],
                    'statistics' => [
                        'totalEpisodeCount' => 0,
                        'episodeFileCount' => 0,
                        'episodeCount' => 0,
                        'totalEpisodeCount' => 0,
                        'sizeOnDisk' => 0,
                        'percentOfEpisodes' => 0
                    ]
                ];
            }
            $seasons[$seasonNumber]['episodes'][] = $episode;
            
            // Update season statistics
            $seasons[$seasonNumber]['statistics']['totalEpisodeCount']++;
            if (isset($episode['hasFile']) && $episode['hasFile']) {
                $seasons[$seasonNumber]['statistics']['episodeFileCount']++;
                if (isset($episode['episodeFile']['size'])) {
                    $seasons[$seasonNumber]['statistics']['sizeOnDisk'] += $episode['episodeFile']['size'];
                }
            }
        }
        
        // Calculate percentage of downloaded episodes per season
        foreach ($seasons as $seasonNumber => &$season) {
            if ($season['statistics']['totalEpisodeCount'] > 0) {
                $season['statistics']['percentOfEpisodes'] = ($season['statistics']['episodeFileCount'] / $season['statistics']['totalEpisodeCount']) * 100;
            }
        }
        
        // Sort seasons in descending order
        krsort($seasons);
    }
}

$pageTitle = !empty($show) ? htmlspecialchars($show['title']) : "Show Not Found";
require_once 'includes/header.php';
?>

<div class="show-details-container">
    <?php if (empty($settings['sonarr_url']) || empty($settings['sonarr_api_key'])): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your Sonarr API settings to view show details.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php elseif (empty($show)): ?>
        <div class="alert alert-danger">
            <h4><i class="fa fa-exclamation-circle"></i> Show Not Found</h4>
            <p>The requested TV show was not found or there was an error retrieving the data.</p>
            <a href="sonarr.php" class="btn btn-primary">Back to TV Shows</a>
        </div>
    <?php else: ?>
        <div class="back-link">
            <a href="sonarr.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Back to TV Shows
            </a>
        </div>
        
        <div class="show-header">
            <?php if (!empty($show['images'])): ?>
                <div class="show-backdrop" style="background-image: url('<?php echo getImageProxyUrl($show, 'fanart'); ?>');"></div>
            <?php endif; ?>
            
            <div class="show-header-content">
                <div class="show-poster-container">
                    <?php if (!empty($show['images'])): ?>
                        <div class="show-poster" style="background-image: url('<?php echo getImageProxyUrl($show); ?>');"></div>
                    <?php else: ?>
                        <div class="show-poster no-image">
                            <i class="fa fa-tv"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="show-info">
                    <h1 class="show-title"><?php echo htmlspecialchars($show['title']); ?></h1>
                    
                    <div class="show-meta">
                        <?php if (isset($show['year'])): ?>
                            <span class="show-year"><?php echo $show['year']; ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($show['network'])): ?>
                            <span class="show-network"><?php echo htmlspecialchars($show['network']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($show['status'])): ?>
                            <span class="show-status <?php echo strtolower($show['status']); ?>">
                                <?php echo $show['status']; ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (isset($show['runtime'])): ?>
                            <span class="show-runtime"><?php echo $show['runtime']; ?> mins</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($show['genres']) && is_array($show['genres'])): ?>
                        <div class="show-genres">
                            <?php foreach ($show['genres'] as $genre): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($genre); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($show['overview'])): ?>
                        <div class="show-overview">
                            <p><?php echo nl2br(htmlspecialchars($show['overview'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="show-action-links">
                        <?php if (!empty($show['tvdbId'])): ?>
                            <a href="https://www.thetvdb.com/?id=<?php echo $show['tvdbId']; ?>&tab=series" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> TVDB
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($show['imdbId'])): ?>
                            <a href="https://www.imdb.com/title/<?php echo $show['imdbId']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> IMDb
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo $settings['sonarr_url']; ?>/series/<?php echo $show['titleSlug']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-external-link-alt"></i> Open in Sonarr
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="show-statistics">
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $episodeCount = 0;
                    $downloadedCount = 0;
                    
                    foreach ($episodes as $episode) {
                        if ($episode['seasonNumber'] > 0) {
                            $episodeCount++;
                            if (isset($episode['hasFile']) && $episode['hasFile']) {
                                $downloadedCount++;
                            }
                        }
                    }
                    
                    echo $downloadedCount . '/' . $episodeCount; 
                    ?>
                </div>
                <div class="stat-label">Episodes Downloaded</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">
                    <?php
                    $totalSize = 0;
                    foreach ($episodes as $episode) {
                        if (isset($episode['hasFile']) && $episode['hasFile'] && isset($episode['episodeFile']['size'])) {
                            $totalSize += $episode['episodeFile']['size'];
                        }
                    }
                    echo formatSize($totalSize);
                    ?>
                </div>
                <div class="stat-label">Size on Disk</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo count($seasons); ?></div>
                <div class="stat-label">Seasons</div>
            </div>
            
            <?php if (isset($show['seasonCount'])): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $show['seasonCount']; ?></div>
                    <div class="stat-label">Total Seasons</div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="seasons-container">
            <h2>Seasons</h2>
            
            <div class="accordion" id="seasonsAccordion">
                <?php foreach ($seasons as $seasonNumber => $season): ?>
                    <?php if ($seasonNumber == 0) continue; // Skip specials for now ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-season-<?php echo $seasonNumber; ?>">
                            <button class="accordion-button <?php echo ($seasonNumber !== array_key_first($seasons)) ? 'collapsed' : ''; ?>" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapse-season-<?php echo $seasonNumber; ?>" 
                                    aria-expanded="<?php echo ($seasonNumber === array_key_first($seasons)) ? 'true' : 'false'; ?>" 
                                    aria-controls="collapse-season-<?php echo $seasonNumber; ?>">
                                
                                <div class="season-header-content">
                                    <div class="season-title">Season <?php echo $seasonNumber; ?></div>
                                    <div class="season-stats">
                                        <span><?php echo $season['statistics']['episodeFileCount']; ?>/<?php echo $season['statistics']['totalEpisodeCount']; ?> Episodes</span>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $season['statistics']['percentOfEpisodes']; ?>%" 
                                                 aria-valuenow="<?php echo $season['statistics']['percentOfEpisodes']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse-season-<?php echo $seasonNumber; ?>" 
                             class="accordion-collapse collapse <?php echo ($seasonNumber === array_key_first($seasons)) ? 'show' : ''; ?>" 
                             aria-labelledby="heading-season-<?php echo $seasonNumber; ?>" 
                             data-bs-parent="#seasonsAccordion">
                            <div class="accordion-body">
                                <div class="episode-list">
                                    <div class="episode-list-header">
                                        <div class="episode-number">Episode</div>
                                        <div class="episode-title">Title</div>
                                        <div class="episode-air-date">Air Date</div>
                                        <div class="episode-status">Status</div>
                                    </div>
                                    
                                    <?php 
                                    // Sort episodes by episode number
                                    usort($season['episodes'], function($a, $b) {
                                        return $a['episodeNumber'] - $b['episodeNumber'];
                                    });
                                    
                                    foreach ($season['episodes'] as $episode): 
                                    ?>
                                        <div class="episode-item">
                                            <div class="episode-number">
                                                S<?php echo str_pad($episode['seasonNumber'], 2, '0', STR_PAD_LEFT); ?>E<?php echo str_pad($episode['episodeNumber'], 2, '0', STR_PAD_LEFT); ?>
                                            </div>
                                            <div class="episode-title">
                                                <?php echo htmlspecialchars($episode['title']); ?>
                                            </div>
                                            <div class="episode-air-date">
                                                <?php echo !empty($episode['airDate']) ? date('M d, Y', strtotime($episode['airDate'])) : 'Unknown'; ?>
                                            </div>
                                            <div class="episode-status">
                                                <?php if (isset($episode['hasFile']) && $episode['hasFile']): ?>
                                                    <span class="badge bg-success">Downloaded</span>
                                                    <?php if (isset($episode['episodeFile']['quality']['quality']['name'])): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($episode['episodeFile']['quality']['quality']['name']); ?></span>
                                                    <?php endif; ?>
                                                <?php elseif (!empty($episode['airDate']) && strtotime($episode['airDate']) > time()): ?>
                                                    <span class="badge bg-warning">Not Aired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Missing</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
