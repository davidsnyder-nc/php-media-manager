<?php
/**
 * Functions file for Media Manager
 * Contains all helper functions and API integration logic
 */

/**
 * Makes an API request to the specified endpoint
 * 
 * @param string $url The base URL of the API
 * @param string $endpoint The API endpoint
 * @param array $params Additional query parameters
 * @param string $apiKey The API key for authentication
 * @param string $method The HTTP method (GET, POST, etc.)
 * @param array $postData Data to send in POST requests
 * @return array|bool The decoded response or false on failure
 */
function makeApiRequest($url, $endpoint, $params = [], $apiKey = '', $method = 'GET', $postData = []) {
    // Ensure URL ends with a slash
    $url = rtrim($url, '/') . '/';
    $endpoint = ltrim($endpoint, '/');
    
    // Add API key to parameters if provided
    if (!empty($apiKey)) {
        $params['apikey'] = $apiKey;
    }
    
    // Build the complete URL with query parameters
    $fullUrl = $url . $endpoint;
    if (!empty($params)) {
        $fullUrl .= (strpos($fullUrl, '?') === false ? '?' : '&') . http_build_query($params);
    }
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Set method and data for POST/PUT requests
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch) || $httpCode >= 400) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Decode and return the response
    $decodedResponse = json_decode($response, true);
    return ($decodedResponse !== null) ? $decodedResponse : $response;
}

/**
 * Get data from Sonarr API
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @return array Array of TV shows
 */
function getSonarrOverview($url, $apiKey) {
    $shows = makeApiRequest($url, 'api/v3/series', [], $apiKey);
    if (!$shows || !is_array($shows)) {
        return [];
    }
    
    // Sort shows by most recently added
    usort($shows, function($a, $b) {
        return strtotime($b['added'] ?? '0') - strtotime($a['added'] ?? '0');
    });
    
    return $shows;
}

/**
 * Get all TV shows from Sonarr
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @return array Array of TV shows
 */
function getSonarrShows($url, $apiKey) {
    return makeApiRequest($url, 'api/v3/series', [], $apiKey);
}

/**
 * Get details for a specific TV show from Sonarr
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @param int $id TV show ID
 * @return array|bool TV show details or false on failure
 */
function getSonarrShowDetails($url, $apiKey, $id) {
    $shows = getSonarrShows($url, $apiKey);
    if (!$shows || !is_array($shows)) {
        return false;
    }
    
    foreach ($shows as $show) {
        if (isset($show['id']) && $show['id'] === $id) {
            return $show;
        }
    }
    
    return false;
}

/**
 * Get all episodes for a TV show from Sonarr
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @param int $seriesId TV show ID
 * @return array Array of episodes
 */
function getSonarrEpisodes($url, $apiKey, $seriesId) {
    return makeApiRequest($url, 'api/v3/episode', ['seriesId' => $seriesId], $apiKey);
}

/**
 * Get upcoming episodes from Sonarr
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @return array Array of upcoming episodes
 */
function getUpcomingEpisodes($url, $apiKey) {
    $episodes = makeApiRequest($url, 'api/v3/calendar', [
        'start' => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime('+7 days')),
        'includeEpisodeFile' => 'true',
        'includeEpisodeImages' => 'false',
        'includeSeriesImages' => 'false'
    ], $apiKey);
    
    if (!$episodes || !is_array($episodes)) {
        return [];
    }
    
    // Get all shows for reference
    $allShows = getSonarrShows($url, $apiKey);
    $showsById = [];
    
    // Create a lookup array of shows by ID
    if (is_array($allShows)) {
        foreach ($allShows as $show) {
            if (isset($show['id'])) {
                $showsById[$show['id']] = $show;
            }
        }
    }
    
    // Add series info to each episode if missing
    foreach ($episodes as &$episode) {
        if (!isset($episode['series']) || !isset($episode['series']['title'])) {
            $seriesId = $episode['seriesId'] ?? 0;
            if (isset($showsById[$seriesId])) {
                $episode['series'] = [
                    'id' => $seriesId,
                    'title' => $showsById[$seriesId]['title'] ?? 'Unknown Show',
                    'status' => $showsById[$seriesId]['status'] ?? ''
                ];
            } else {
                $episode['series'] = [
                    'id' => $seriesId,
                    'title' => 'Unknown Show',
                    'status' => ''
                ];
            }
        }
    }
    unset($episode); // Clear reference
    
    // Sort by air date
    usort($episodes, function($a, $b) {
        return strtotime($a['airDate'] ?? '0') - strtotime($b['airDate'] ?? '0');
    });
    
    return $episodes;
}

/**
 * Get data from Radarr API
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @return array Array of movies
 */
function getRadarrOverview($url, $apiKey) {
    $movies = makeApiRequest($url, 'api/v3/movie', [], $apiKey);
    if (!$movies || !is_array($movies)) {
        return [];
    }
    
    // Sort movies by most recently added
    usort($movies, function($a, $b) {
        return strtotime($b['added'] ?? '0') - strtotime($a['added'] ?? '0');
    });
    
    return $movies;
}

/**
 * Get all movies from Radarr
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @return array Array of movies
 */
function getRadarrMovies($url, $apiKey) {
    return makeApiRequest($url, 'api/v3/movie', [], $apiKey);
}

/**
 * Get details for a specific movie from Radarr
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @param int $id Movie ID
 * @return array|bool Movie details or false on failure
 */
function getRadarrMovieDetails($url, $apiKey, $id) {
    $movies = getRadarrMovies($url, $apiKey);
    if (!$movies || !is_array($movies)) {
        return false;
    }
    
    foreach ($movies as $movie) {
        if (isset($movie['id']) && $movie['id'] === $id) {
            return $movie;
        }
    }
    
    return false;
}

/**
 * Get upcoming movies from Radarr
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @return array Array of upcoming movies
 */
function getUpcomingMovies($url, $apiKey) {
    $movies = getRadarrMovies($url, $apiKey);
    if (!$movies || !is_array($movies)) {
        return [];
    }
    
    $upcomingMovies = [];
    $now = new DateTime();
    
    foreach ($movies as $movie) {
        if (isset($movie['inCinemas']) && !empty($movie['inCinemas'])) {
            $releaseDate = new DateTime($movie['inCinemas']);
            
            // If movie is coming soon (in the next 60 days) and has not been released yet
            if ($releaseDate > $now && $releaseDate < (clone $now)->modify('+60 days')) {
                $upcomingMovies[] = $movie;
            }
        }
    }
    
    // Sort by release date
    usort($upcomingMovies, function($a, $b) {
        $dateA = new DateTime($a['inCinemas'] ?? 'now');
        $dateB = new DateTime($b['inCinemas'] ?? 'now');
        return $dateA <=> $dateB; // Sort by closest release date first
    });
    
    return $upcomingMovies;
}

/**
 * Get queue data from SABnzbd
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @return array Queue data
 */
function getSabnzbdQueue($url, $apiKey) {
    $response = makeApiRequest($url, 'api', [
        'mode' => 'queue',
        'output' => 'json'
    ], $apiKey);
    
    return isset($response['queue']) ? $response['queue'] : [];
}

/**
 * Get history data from SABnzbd with pagination
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @param int $page Page number (starting at 1)
 * @param int $limit Items per page
 * @return array History data with pagination info
 */
function getSabnzbdHistory($url, $apiKey, $page = 1, $limit = 10) {
    // Calculate start position
    $start = ($page - 1) * $limit;
    
    $response = makeApiRequest($url, 'api', [
        'mode' => 'history',
        'output' => 'json',
        'start' => $start,
        'limit' => $limit
    ], $apiKey);
    
    // Get total items count for pagination
    $totalResponse = makeApiRequest($url, 'api', [
        'mode' => 'history',
        'output' => 'json',
        'limit' => 1
    ], $apiKey);
    
    $total = $totalResponse['history']['noofslots'] ?? 0;
    $totalPages = ceil($total / $limit);
    
    $history = isset($response['history']) ? $response['history'] : [];
    
    // Add pagination info
    $history['pagination'] = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $total,
        'items_per_page' => $limit
    ];
    
    return $history;
}

/**
 * Get status data from SABnzbd
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @return array Status data
 */
function getSabnzbdStatus($url, $apiKey) {
    $response = makeApiRequest($url, 'api', [
        'mode' => 'qstatus',
        'output' => 'json'
    ], $apiKey);
    
    return $response;
}

/**
 * Perform an action on SABnzbd
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @param string $mode API mode/action
 * @param array $params Additional parameters
 * @return bool Success or failure
 */
function sabnzbdAction($url, $apiKey, $mode, $params = []) {
    $params['mode'] = $mode;
    $params['output'] = 'json';
    
    $response = makeApiRequest($url, 'api', $params, $apiKey);
    
    return isset($response['status']) && $response['status'] === true;
}

/**
 * Test API connection to a service
 * 
 * @param string $url Service base URL
 * @param string $apiKey Service API key
 * @param string $service Service type (sonarr, radarr, sabnzbd)
 * @return array Connection status and details
 */
function testApiConnection($url, $apiKey, $service) {
    $result = [
        'success' => false,
        'message' => 'Unknown error',
        'version' => null
    ];
    
    switch ($service) {
        case 'sonarr':
            $response = makeApiRequest($url, 'api/v3/system/status', [], $apiKey);
            if ($response && isset($response['version'])) {
                $result['success'] = true;
                $result['message'] = 'Connected successfully';
                $result['version'] = $response['version'];
            } else {
                $result['message'] = 'Could not connect to Sonarr';
            }
            break;
            
        case 'radarr':
            $response = makeApiRequest($url, 'api/v3/system/status', [], $apiKey);
            if ($response && isset($response['version'])) {
                $result['success'] = true;
                $result['message'] = 'Connected successfully';
                $result['version'] = $response['version'];
            } else {
                $result['message'] = 'Could not connect to Radarr';
            }
            break;
            
        case 'sabnzbd':
            $response = makeApiRequest($url, 'api', [
                'mode' => 'version',
                'output' => 'json'
            ], $apiKey);
            
            if ($response && isset($response['version'])) {
                $result['success'] = true;
                $result['message'] = 'Connected successfully';
                $result['version'] = $response['version'];
            } else {
                $result['message'] = 'Could not connect to SABnzbd';
            }
            break;
            
        default:
            $result['message'] = 'Unknown service type';
            break;
    }
    
    return $result;
}

/**
 * Format file size to human-readable format
 * 
 * @param float|string $bytes Size in bytes or megabytes
 * @param bool $isMB Whether the input is already in megabytes
 * @return string Formatted size
 */
function formatSize($bytes, $isMB = false) {
    // Handle non-numeric values
    if (!is_numeric($bytes)) {
        return '0 B';
    }
    
    // Convert to float
    $bytes = (float) $bytes;
    
    if ($isMB) {
        $bytes = $bytes * 1024 * 1024;
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

/**
 * Format download speed to human-readable format
 * 
 * @param float $kbps Speed in kilobytes per second
 * @return string Formatted speed
 */
function formatSpeed($kbps) {
    if ($kbps > 1024) {
        return round($kbps / 1024, 2) . ' MB/s';
    } else {
        return round($kbps, 2) . ' KB/s';
    }
}

/**
 * Format runtime minutes to hours and minutes
 * 
 * @param int $minutes Runtime in minutes
 * @return string Formatted runtime
 */
function formatRuntime($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    $result = '';
    if ($hours > 0) {
        $result .= $hours . 'h ';
    }
    
    $result .= $mins . 'm';
    
    return $result;
}

/**
 * Search for TV shows using the Sonarr API
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @param string $query The search query
 * @return array Array of TV shows matching the search
 */
function searchSonarrShows($url, $apiKey, $query) {
    if (empty($query)) {
        return [];
    }
    
    return makeApiRequest($url, 'api/v3/series/lookup', ['term' => $query], $apiKey);
}

/**
 * Search for movies using the Radarr API
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @param string $query The search query
 * @return array Array of movies matching the search
 */
function searchRadarrMovies($url, $apiKey, $query) {
    if (empty($query)) {
        return [];
    }
    
    return makeApiRequest($url, 'api/v3/movie/lookup', ['term' => $query], $apiKey);
}

/**
 * Get a proxied image URL to avoid CORS issues
 * 
 * @param array $item The movie or show data containing images
 * @param string $type The type of image to get (poster, fanart, etc.)
 * @return string The image URL
 */
function getImageProxyUrl($item, $type = 'poster') {
    // Default fallback for no image
    $imageUrl = '';
    
    // Find the appropriate image from the item's images array
    if (!empty($item['images']) && is_array($item['images'])) {
        foreach ($item['images'] as $image) {
            if (isset($image['coverType']) && strtolower($image['coverType']) === strtolower($type)) {
                $imageUrl = $image['remoteUrl'] ?? $image['url'] ?? '';
                break;
            }
        }
        
        // If requested type not found, fallback to poster
        if (empty($imageUrl) && $type !== 'poster') {
            foreach ($item['images'] as $image) {
                if (isset($image['coverType']) && strtolower($image['coverType']) === 'poster') {
                    $imageUrl = $image['remoteUrl'] ?? $image['url'] ?? '';
                    break;
                }
            }
        }
    }
    
    // If still no image, return empty string
    if (empty($imageUrl)) {
        return '';
    }
    
    // Return the proxied URL
    return 'api.php?action=proxy_image&url=' . base64_encode($imageUrl);
}
