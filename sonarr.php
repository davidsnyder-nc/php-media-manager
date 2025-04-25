<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Load settings from the config file
$settings = loadSettings();

// Get query parameters for filtering/sorting
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'asc';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Initialize array for TV shows
$shows = [];

// Check if we have necessary settings
if (!empty($settings['sonarr_url']) && !empty($settings['sonarr_api_key'])) {
    $shows = getSonarrShows($settings['sonarr_url'], $settings['sonarr_api_key']);
    
    // Search for shows
    $externalSearchResults = [];
    if (!empty($searchTerm)) {
        // First, filter existing shows
        $existingShows = array_filter($shows, function($show) use ($searchTerm) {
            return stripos($show['title'], $searchTerm) !== false;
        });
        
        // Then, search for new shows via API
        $searchResults = searchSonarrShows($settings['sonarr_url'], $settings['sonarr_api_key'], $searchTerm);
        
        if (!empty($searchResults) && is_array($searchResults)) {
            // Get IDs of existing shows for comparison
            $existingIds = array_map(function($show) {
                return $show['tvdbId'] ?? 0;
            }, $shows);
            
            // Filter out shows that are already in library
            $externalSearchResults = array_filter($searchResults, function($result) use ($existingIds) {
                return !in_array($result['tvdbId'] ?? 0, $existingIds);
            });
            
            // Use existing shows as the primary display list
            $shows = $existingShows;
        } else {
            $shows = $existingShows;
        }
    }
    
    // Apply status filter if provided
    if (!empty($statusFilter)) {
        $shows = array_filter($shows, function($show) use ($statusFilter) {
            return $show['status'] === $statusFilter;
        });
    }
    
    // Sort the shows
    usort($shows, function($a, $b) use ($sortBy, $sortOrder) {
        if ($sortBy === 'title') {
            $result = strcasecmp($a['title'], $b['title']);
        } elseif ($sortBy === 'year' && isset($a['year']) && isset($b['year'])) {
            $result = $a['year'] - $b['year'];
        } elseif ($sortBy === 'status') {
            $result = strcasecmp($a['status'], $b['status']);
        } else {
            $result = 0;
        }
        
        return ($sortOrder === 'asc') ? $result : -$result;
    });
}

$pageTitle = "TV Shows - Sonarr";
require_once 'includes/header.php';
?>

<div class="sonarr-container">
    <div class="page-header">
        <h1><i class="fa fa-tv"></i> TV Shows</h1>
        
        <div class="filter-bar">
            <form class="row g-3" method="get">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search shows..." name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="continuing" <?php echo $statusFilter === 'continuing' ? 'selected' : ''; ?>>Continuing</option>
                        <option value="ended" <?php echo $statusFilter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="sort" onchange="this.form.submit()">
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Sort by Title</option>
                        <option value="year" <?php echo $sortBy === 'year' ? 'selected' : ''; ?>>Sort by Year</option>
                        <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
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
    
    <?php if (empty($settings['sonarr_url']) || empty($settings['sonarr_api_key'])): ?>
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Configuration Required</h4>
            <p>Please configure your Sonarr API settings to view TV shows.</p>
            <a href="settings.php" class="btn btn-primary">Go to Settings</a>
        </div>
    <?php elseif (empty($shows) && empty($externalSearchResults)): ?>
        <div class="alert alert-info">
            <h4><i class="fa fa-info-circle"></i> No TV Shows Found</h4>
            <p>No TV shows match your criteria or there was an error connecting to Sonarr.</p>
        </div>
    <?php else: ?>
        <?php if (!empty($shows)): ?>
            <div class="show-count">
                Showing <?php echo count($shows); ?> TV shows in your library
            </div>
            
            <div class="media-grid">
                <?php foreach ($shows as $show): ?>
                    <div class="media-item">
                        <a href="show_details.php?id=<?php echo $show['id']; ?>">
                            <?php if (!empty($show['images'])): ?>
                                <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($show); ?>');" data-show-id="<?php echo $show['id']; ?>"></div>
                            <?php else: ?>
                                <div class="media-poster no-image">
                                    <i class="fa fa-tv"></i>
                                </div>
                            <?php endif; ?>
                            <div class="media-title"><?php echo htmlspecialchars($show['title']); ?></div>
                            <div class="media-info">
                                <?php if (isset($show['year'])): ?>
                                    <span class="media-year"><?php echo $show['year']; ?></span>
                                <?php endif; ?>
                                <?php if (isset($show['status'])): ?>
                                    <span class="media-status <?php echo strtolower($show['status']); ?>">
                                        <?php echo $show['status']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($externalSearchResults)): ?>
            <div class="mt-5">
                <h3>Available to Add</h3>
                <p class="text-muted">The following TV shows are not in your library but match your search.</p>
                
                <div class="media-grid">
                    <?php foreach ($externalSearchResults as $show): ?>
                        <div class="media-item">
                            <div class="position-relative">
                                <?php if (!empty($show['images'])): ?>
                                    <div class="media-poster" style="background-image: url('<?php echo getImageProxyUrl($show); ?>');" data-show-id="<?php echo $show['tvdbId'] ?? ''; ?>"></div>
                                <?php else: ?>
                                    <div class="media-poster no-image">
                                        <i class="fa fa-tv"></i>
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="api.php" class="add-media-form">
                                    <input type="hidden" name="action" value="add_show">
                                    <input type="hidden" name="tvdbId" value="<?php echo $show['tvdbId'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-sm btn-success add-media-btn">
                                        <i class="fa fa-plus"></i> Add
                                    </button>
                                </form>
                                <div class="media-title"><?php echo htmlspecialchars($show['title']); ?></div>
                                <div class="media-info">
                                    <?php if (isset($show['year'])): ?>
                                        <span class="media-year"><?php echo $show['year']; ?></span>
                                    <?php endif; ?>
                                    <?php if (isset($show['status'])): ?>
                                        <span class="media-status <?php echo strtolower($show['status']); ?>">
                                            <?php echo $show['status']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
