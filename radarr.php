<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Check for demo mode
$demoMode = isset($settings['demo_mode']) && $settings['demo_mode'] === 'enabled';

// Get query parameters for filtering/sorting
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'asc';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Initialize array for movies
$movies = [];

// Check if we have necessary settings or if demo mode is enabled
if ($demoMode || (!empty($settings['radarr_url']) && !empty($settings['radarr_api_key']))) {
    $movies = getRadarrMovies($settings['radarr_url'], $settings['radarr_api_key'], $demoMode);
    
    // Search for movies
    $externalSearchResults = [];
    if (!empty($searchTerm)) {
        // First, filter existing movies
        $existingMovies = array_filter($movies, function($movie) use ($searchTerm) {
            return stripos($movie['title'], $searchTerm) !== false;
        });
        
        // Then, search for new movies via API if not in demo mode
        if (!$demoMode) {
            $searchResults = searchRadarrMovies($settings['radarr_url'], $settings['radarr_api_key'], $searchTerm);
        } else {
            $searchResults = []; // In demo mode, just use the existing movies
        }
        
        if (!empty($searchResults) && is_array($searchResults)) {
            // Get IDs of existing movies for comparison
            $existingIds = array_map(function($movie) {
                return $movie['tmdbId'] ?? 0;
            }, $movies);
            
            // Filter out movies that are already in library
            $externalSearchResults = array_filter($searchResults, function($result) use ($existingIds) {
                return !in_array($result['tmdbId'] ?? 0, $existingIds);
            });
            
            // Use existing movies as the primary display list
            $movies = $existingMovies;
        } else {
            $movies = $existingMovies;
        }
    }
    
    // Apply status filter if provided
    if (!empty($statusFilter)) {
        $movies = array_filter($movies, function($movie) use ($statusFilter) {
            switch ($statusFilter) {
                case 'downloaded':
                    return $movie['downloaded'];
                case 'available':
                    return $movie['hasFile'];
                case 'missing':
                    return !$movie['hasFile'];
                default:
                    return true;
            }
        });
    }
    
    // Sort the movies if we have any
    if (!empty($movies) && is_array($movies)) {
        usort($movies, function($a, $b) use ($sortBy, $sortOrder) {
            // Safety check that we have arrays
            if (!is_array($a) || !is_array($b)) {
                return 0;
            }
            
            // Sort by title with proper null checks
            if ($sortBy === 'title') {
                $titleA = isset($a['title']) && is_string($a['title']) ? $a['title'] : '';
                $titleB = isset($b['title']) && is_string($b['title']) ? $b['title'] : '';
                $result = strcasecmp($titleA, $titleB);
            } 
            // Sort by year with proper null checks
            elseif ($sortBy === 'year' && isset($a['year']) && isset($b['year']) 
                    && is_numeric($a['year']) && is_numeric($b['year'])) {
                $result = $a['year'] - $b['year'];
            } 
            // Sort by date added with proper null checks
            elseif ($sortBy === 'added' && isset($a['added']) && isset($b['added']) 
                    && is_string($a['added']) && is_string($b['added'])) {
                $result = strtotime($a['added']) - strtotime($b['added']);
            } 
            // Default - no sorting
            else {
                $result = 0;
            }
            
            return ($sortOrder === 'asc') ? $result : -$result;
        });
    }
}

$pageTitle = "Movies - Radarr";
require_once 'includes/header.php';
?>

<div class="radarr-container">
    <div class="page-header">
        <h1><i class="fa fa-film"></i> Movies</h1>
        
        <div class="filter-bar">
            <form class="row g-3" method="get">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search movies..." name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Movies</option>
                        <option value="downloaded" <?php echo $statusFilter === 'downloaded' ? 'selected' : ''; ?>>Downloaded</option>
                        <option value="missing" <?php echo $statusFilter === 'missing' ? 'selected' : ''; ?>>Missing</option>
                        <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="sort" onchange="this.form.submit()">
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Sort by Title</option>
                        <option value="year" <?php echo $sortBy === 'year' ? 'selected' : ''; ?>>Sort by Year</option>
                        <option value="added" <?php echo $sortBy === 'added' ? 'selected' : ''; ?>>Sort by Date Added</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select class="form-select" name="order" onchange="this.form.submit()">
                        <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (!$demoMode && (empty($settings['radarr_url']) || empty($settings['radarr_api_key']))): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your Radarr API settings to view movies, or enable Demo Mode in settings.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php elseif (empty($movies) && empty($externalSearchResults)): ?>
        <div class="alert alert-info">
            <h4><i class="fa fa-info-circle"></i> No Movies Found</h4>
            <p>No movies match your criteria or there was an error connecting to Radarr.</p>
        </div>
    <?php else: ?>
        <?php if (!empty($movies)): ?>
            <div class="movie-count">
                Showing <?php echo count($movies); ?> movies in your library
            </div>
            
            <div class="media-grid">
                <?php foreach ($movies as $movie): ?>
                    <div class="media-item">
                        <a href="movie_details.php?id=<?php echo $movie['id']; ?>">
                            <?php if (!empty($movie['images'])): ?>
                                <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($movie); ?>');" data-movie-id="<?php echo $movie['id']; ?>"></div>
                            <?php else: ?>
                                <div class="media-poster no-image">
                                    <i class="fa fa-film"></i>
                                </div>
                            <?php endif; ?>
                            <div class="media-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                            <div class="media-info">
                                <?php if (isset($movie['year'])): ?>
                                    <span class="media-year"><?php echo $movie['year']; ?></span>
                                <?php endif; ?>
                                <span class="media-status <?php echo $movie['hasFile'] ? 'downloaded' : 'missing'; ?>">
                                    <?php echo $movie['hasFile'] ? 'Downloaded' : 'Missing'; ?>
                                </span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($externalSearchResults)): ?>
            <div class="mt-5">
                <h3>Available to Add</h3>
                <p class="text-muted">The following movies are not in your library but match your search.</p>
                
                <div class="media-grid">
                    <?php foreach ($externalSearchResults as $movie): ?>
                        <div class="media-item">
                            <div class="position-relative">
                                <?php if (!empty($movie['images'])): ?>
                                    <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($movie); ?>');" data-movie-id="<?php echo $movie['tmdbId'] ?? ''; ?>"></div>
                                <?php else: ?>
                                    <div class="media-poster no-image">
                                        <i class="fa fa-film"></i>
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="api.php" class="add-media-form">
                                    <input type="hidden" name="action" value="add_movie">
                                    <input type="hidden" name="tmdbId" value="<?php echo $movie['tmdbId'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-sm btn-success add-media-btn">
                                        <i class="fa fa-plus"></i> Add
                                    </button>
                                </form>
                                <div class="media-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                <div class="media-info">
                                    <?php if (isset($movie['year'])): ?>
                                        <span class="media-year"><?php echo $movie['year']; ?></span>
                                    <?php endif; ?>
                                    <?php if (isset($movie['status'])): ?>
                                        <span class="media-status <?php echo strtolower($movie['status']); ?>">
                                            <?php echo $movie['status']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
