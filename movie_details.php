<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Check for demo mode
$demoMode = isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled';

// Get movie ID from URL parameter
$movieId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize movie data variable
$movie = [];

// In demo mode, get sample movies
if ($demoMode) {
    // Get sample movies
    $sampleMovies = getSampleMovies();
    
    // Find the requested movie by ID
    foreach ($sampleMovies as $sampleMovie) {
        if (isset($sampleMovie['id']) && intval($sampleMovie['id']) === $movieId) {
            $movie = $sampleMovie;
            break;
        }
    }
    
    // If movie not found but we have sample movies, use the first one
    if (empty($movie) && !empty($sampleMovies)) {
        $movie = $sampleMovies[0];
    }
}
// Otherwise try to get real movie data if we have API settings
else if (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key']) && $movieId > 0) {
    // Get movie details from API
    $movie = getRadarrMovieDetails($settings['radarr_url'], $settings['radarr_api_key'], $movieId, false);
}

$pageTitle = !empty($movie) ? htmlspecialchars($movie['title']) : "Movie Not Found";
require_once 'includes/header.php';
?>

<div class="movie-details-container">
    <?php if ((empty($settings['radarr_url']) || empty($settings['radarr_api_key'])) && !$demoMode): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your Radarr API settings to view movie details or enable Demo Mode in settings.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php elseif (empty($movie)): ?>
        <div class="alert alert-danger">
            <h4><i class="fa fa-exclamation-circle"></i> Movie Not Found</h4>
            <p>The requested movie was not found or there was an error retrieving the data.</p>
            <a href="radarr.php" class="btn btn-primary">Back to Movies</a>
        </div>
    <?php else: ?>
        <div class="back-link">
            <a href="radarr.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Back to Movies
            </a>
        </div>
        
        <div class="movie-header">
            <?php if (!empty($movie['images'])): ?>
                <div class="movie-backdrop" style="background-image: url('<?php echo getImageProxyUrl($movie, 'fanart'); ?>');"></div>
            <?php endif; ?>
            
            <div class="movie-header-content">
                <div class="movie-poster-container">
                    <?php if (!empty($movie['images'])): ?>
                        <div class="movie-poster" style="background-image: url('<?php echo getImageProxyUrl($movie); ?>');"></div>
                    <?php else: ?>
                        <div class="movie-poster no-image">
                            <i class="fa fa-film"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="movie-info">
                    <h1 class="movie-title">
                        <?php echo htmlspecialchars($movie['title']); ?>
                        <?php if (isset($movie['year'])): ?>
                            <span class="movie-year">(<?php echo $movie['year']; ?>)</span>
                        <?php endif; ?>
                    </h1>
                    
                    <div class="movie-meta">
                        <?php if (isset($movie['runtime']) && $movie['runtime'] > 0): ?>
                            <span class="movie-runtime">
                                <i class="fa fa-clock"></i> <?php echo formatRuntime($movie['runtime']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (isset($movie['certification'])): ?>
                            <span class="movie-rating"><?php echo htmlspecialchars($movie['certification']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($movie['status'])): ?>
                            <span class="movie-status"><?php echo htmlspecialchars($movie['status']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($movie['genres']) && is_array($movie['genres'])): ?>
                        <div class="movie-genres">
                            <?php foreach ($movie['genres'] as $genre): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($genre); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($movie['overview'])): ?>
                        <div class="movie-overview">
                            <p><?php echo nl2br(htmlspecialchars($movie['overview'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="movie-action-links">
                        <?php if (!empty($movie['tmdbId'])): ?>
                            <a href="https://www.themoviedb.org/movie/<?php echo $movie['tmdbId']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> TMDB
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($movie['imdbId'])): ?>
                            <a href="https://www.imdb.com/title/<?php echo $movie['imdbId']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> IMDb
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!$demoMode && !empty($settings['radarr_url'])): ?>
                        <a href="<?php echo $settings['radarr_url']; ?>/movie/<?php echo $movie['titleSlug']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-external-link-alt"></i> Open in Radarr
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="movie-details-sections">
            <div class="movie-file-info">
                <h3>Movie Information</h3>
                
                <div class="card">
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <?php if ($movie['hasFile']): ?>
                                    <span class="badge bg-success">Downloaded</span>
                                <?php elseif ($movie['monitored']): ?>
                                    <span class="badge bg-warning">Monitored</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Not Downloaded</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($movie['physicalRelease'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Release Date:</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($movie['physicalRelease'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($movie['studio'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Studio:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($movie['studio']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($movie['path'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Path:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($movie['path']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // If movie has a file, show file details
                        if ($movie['hasFile'] && !empty($movie['movieFile'])): 
                            $file = $movie['movieFile'];
                        ?>
                            <h4 class="mt-4">File Information</h4>
                            
                            <div class="detail-row">
                                <div class="detail-label">Quality:</div>
                                <div class="detail-value">
                                    <?php if (!empty($file['quality']['quality']['name'])): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($file['quality']['quality']['name']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($file['quality']['revision']['version']) && $file['quality']['revision']['version'] > 1): ?>
                                        <span class="badge bg-secondary">v<?php echo $file['quality']['revision']['version']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($file['size'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">File Size:</div>
                                    <div class="detail-value"><?php echo formatSize($file['size']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($file['dateAdded'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Date Added:</div>
                                    <div class="detail-value"><?php echo date('M d, Y H:i', strtotime($file['dateAdded'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($file['mediaInfo'])): ?>
                                <h4 class="mt-4">Media Information</h4>
                                
                                <?php if (!empty($file['mediaInfo']['videoCodec'])): ?>
                                    <div class="detail-row">
                                        <div class="detail-label">Video:</div>
                                        <div class="detail-value">
                                            <?php echo htmlspecialchars($file['mediaInfo']['videoCodec']); ?>
                                            <?php if (!empty($file['mediaInfo']['videoResolution'])): ?>
                                                (<?php echo htmlspecialchars($file['mediaInfo']['videoResolution']); ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($file['mediaInfo']['audioCodec'])): ?>
                                    <div class="detail-row">
                                        <div class="detail-label">Audio:</div>
                                        <div class="detail-value">
                                            <?php echo htmlspecialchars($file['mediaInfo']['audioCodec']); ?>
                                            <?php if (!empty($file['mediaInfo']['audioChannels'])): ?>
                                                (<?php echo htmlspecialchars($file['mediaInfo']['audioChannels']); ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($movie['ratings'])): ?>
                <div class="movie-ratings">
                    <h3>Ratings</h3>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="ratings-container">
                                <?php if (!empty($movie['ratings']['imdb']) && !empty($movie['ratings']['imdb']['value'])): ?>
                                    <div class="rating-item">
                                        <div class="rating-label">IMDb</div>
                                        <div class="rating-value">
                                            <i class="fa fa-star"></i> <?php echo $movie['ratings']['imdb']['value']; ?>
                                        </div>
                                        <?php if (!empty($movie['ratings']['imdb']['votes'])): ?>
                                            <div class="rating-votes"><?php echo number_format($movie['ratings']['imdb']['votes']); ?> votes</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($movie['ratings']['tmdb']) && !empty($movie['ratings']['tmdb']['value'])): ?>
                                    <div class="rating-item">
                                        <div class="rating-label">TMDB</div>
                                        <div class="rating-value">
                                            <i class="fa fa-star"></i> <?php echo $movie['ratings']['tmdb']['value']; ?>
                                        </div>
                                        <?php if (!empty($movie['ratings']['tmdb']['votes'])): ?>
                                            <div class="rating-votes"><?php echo number_format($movie['ratings']['tmdb']['votes']); ?> votes</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($movie['ratings']['metacritic']) && !empty($movie['ratings']['metacritic']['value'])): ?>
                                    <div class="rating-item">
                                        <div class="rating-label">Metacritic</div>
                                        <div class="rating-value">
                                            <i class="fa fa-star"></i> <?php echo $movie['ratings']['metacritic']['value']; ?>
                                        </div>
                                        <?php if (!empty($movie['ratings']['metacritic']['votes'])): ?>
                                            <div class="rating-votes"><?php echo number_format($movie['ratings']['metacritic']['votes']); ?> votes</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($movie['ratings']['rottenTomatoes']) && !empty($movie['ratings']['rottenTomatoes']['value'])): ?>
                                    <div class="rating-item">
                                        <div class="rating-label">Rotten Tomatoes</div>
                                        <div class="rating-value">
                                            <i class="fa fa-star"></i> <?php echo $movie['ratings']['rottenTomatoes']['value']; ?>%
                                        </div>
                                        <?php if (!empty($movie['ratings']['rottenTomatoes']['votes'])): ?>
                                            <div class="rating-votes"><?php echo number_format($movie['ratings']['rottenTomatoes']['votes']); ?> votes</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
