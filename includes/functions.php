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
function makeApiRequest($url, $endpoint, $params = [], $apiKey = '', $method = 'GET', $postData = [], $jsonData = true) {
    // Ensure URL ends with a slash
    $url = rtrim($url, '/') . '/';
    $endpoint = ltrim($endpoint, '/');
    
    // Initialize headers array
    $headers = [];
    
    // Determine where to put the API key based on the API
    if (strpos($endpoint, 'api/v3') === 0 && !empty($apiKey)) {
        // For Sonarr/Radarr API v3, use header
        $headers[] = 'X-Api-Key: ' . $apiKey;
    } else if (!empty($apiKey)) {
        // For other APIs, use as parameter
        $params['apikey'] = $apiKey;
    }
    
    // For GET requests, combine parameters and API key in URL
    if ($method === 'GET' || $method === 'DELETE') {
        // Build the complete URL with query parameters
        $fullUrl = $url . $endpoint;
        if (!empty($params)) {
            $fullUrl .= (strpos($fullUrl, '?') === false ? '?' : '&') . http_build_query($params);
        }
    } else {
        // For POST/PUT, keep URL clean and use params as data if no postData provided
        $fullUrl = $url . $endpoint;
        if (empty($postData) && !empty($params) && $method !== 'GET') {
            $postData = $params;
            $params = []; // Clear params since we're using them as postData
        }
    }
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout for larger operations
    
    // Set method and data for POST/PUT requests
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($postData)) {
            if ($jsonData) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!empty($postData)) {
            if ($jsonData) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    // Set headers if any
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // For debugging
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Check for errors
    if ($error || $httpCode >= 400) {
        // Return error info as an array
        return [
            'error' => true,
            'http_code' => $httpCode,
            'message' => $error ?: 'HTTP Error ' . $httpCode,
            'response' => $response
        ];
    }
    
    // Decode and return the response
    $decodedResponse = json_decode($response, true);
    return ($decodedResponse !== null) ? $decodedResponse : $response;
}

/**
 * Get data from Sonarr API
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of TV shows
 */
function getSonarrOverview($url, $apiKey, $demoMode = false) {
    $shows = makeApiRequest($url, 'api/v3/series', [], $apiKey);
    if ((!$shows || !is_array($shows)) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        $shows = getSampleTvShows();
    } elseif (!$shows || !is_array($shows)) {
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of TV shows
 */
function getSonarrShows($url, $apiKey, $demoMode = false) {
    $shows = makeApiRequest($url, 'api/v3/series', [], $apiKey);
    if ((!$shows || !is_array($shows)) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        return getSampleTvShows();
    }
    return $shows;
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of upcoming episodes
 */
function getUpcomingEpisodes($url, $apiKey, $demoMode = false) {
    $episodes = makeApiRequest($url, 'api/v3/calendar', [
        'start' => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime('+7 days')),
        'includeEpisodeFile' => 'true',
        'includeEpisodeImages' => 'false',
        'includeSeriesImages' => 'false'
    ], $apiKey);
    
    if ((!$episodes || !is_array($episodes)) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        return getSampleUpcomingEpisodes();
    } elseif (!$episodes || !is_array($episodes)) {
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
    
    // Process each episode to ensure it has proper series info
    foreach ($episodes as &$episode) {
        // Get the series ID from the episode
        $seriesId = $episode['seriesId'] ?? 0;
        
        // Check if series info already exists and is complete
        $hasValidSeriesInfo = isset($episode['series']) && 
                             isset($episode['series']['id']) && 
                             isset($episode['series']['title']) && 
                             $episode['series']['title'] !== 'Unknown Show';
        
        // If we don't have valid series info, try to get it from our shows lookup
        if (!$hasValidSeriesInfo && isset($showsById[$seriesId])) {
            $episode['series'] = [
                'id' => $seriesId,
                'title' => $showsById[$seriesId]['title'],
                'status' => $showsById[$seriesId]['status'] ?? ''
            ];
            
            // Also set a flat seriesTitle property for easier access
            $episode['seriesTitle'] = $showsById[$seriesId]['title'];
        } 
        // If we still don't have a series title, try to extract it from other properties
        else if (!isset($episode['seriesTitle']) || empty($episode['seriesTitle'])) {
            // Check if there's a series title in the series object
            if (isset($episode['series']['title']) && !empty($episode['series']['title'])) {
                $episode['seriesTitle'] = $episode['series']['title'];
            }
            // If all else fails, use "Unknown Show"
            else {
                $episode['seriesTitle'] = 'Unknown Show';
                
                // Create a basic series object if it doesn't exist
                if (!isset($episode['series'])) {
                    $episode['series'] = [
                        'id' => $seriesId,
                        'title' => 'Unknown Show',
                        'status' => ''
                    ];
                }
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of movies
 */
function getRadarrOverview($url, $apiKey, $demoMode = false) {
    $movies = makeApiRequest($url, 'api/v3/movie', [], $apiKey);
    if ((!$movies || !is_array($movies)) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        $movies = getSampleMovies();
    } elseif (!$movies || !is_array($movies)) {
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of movies
 */
function getRadarrMovies($url, $apiKey, $demoMode = false) {
    $movies = makeApiRequest($url, 'api/v3/movie', [], $apiKey);
    if ((!$movies || !is_array($movies)) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        return getSampleMovies();
    }
    return $movies;
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
 * Get recently completed downloads from SABnzbd
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @param int $limit Number of items to return
 * @param string $sonarrUrl Sonarr URL for TV show metadata
 * @param string $sonarrApiKey Sonarr API key
 * @param string $radarrUrl Radarr URL for movie metadata
 * @param string $radarrApiKey Radarr API key
 * @return array Recently completed downloads
 */
function getRecentlyDownloadedContent($url, $apiKey, $limit = 6, $sonarrUrl = '', $sonarrApiKey = '', $radarrUrl = '', $radarrApiKey = '') {
    $response = makeApiRequest($url, 'api', [
        'mode' => 'history',
        'output' => 'json',
        'limit' => 30 // Get more items initially so we can consolidate them
    ], $apiKey);
    
    if (isset($response['history']) && isset($response['history']['slots'])) {
        // Only return completed downloads
        $downloads = array_filter($response['history']['slots'], function($item) {
            return $item['status'] === 'Completed';
        });
        
        // Get TV shows and movies for matching
        $tvShows = [];
        $movies = [];
        
        if (!empty($sonarrUrl) && !empty($sonarrApiKey)) {
            $tvShows = getSonarrShows($sonarrUrl, $sonarrApiKey);
        }
        
        if (!empty($radarrUrl) && !empty($radarrApiKey)) {
            $movies = getRadarrMovies($radarrUrl, $radarrApiKey);
        }
        
        // Process and categorize downloads
        $processedDownloads = [];
        $tvShowsByName = [];
        $moviesByName = [];
        
        foreach ($downloads as &$download) {
            // Determine content type
            $type = 'other';
            if (isset($download['category'])) {
                $category = strtolower($download['category']);
                if (strpos($category, 'tv') !== false || strpos($category, 'show') !== false || strpos($category, 'series') !== false) {
                    $type = 'tv';
                } elseif (strpos($category, 'movie') !== false || strpos($category, 'film') !== false) {
                    $type = 'movie';
                }
            }
            
            $download['type'] = $type;
            
            // For TV shows, extract show name from the download name
            if ($type === 'tv') {
                // Extract show name using common patterns like "Show.Name.S01E01.etc"
                $name = $download['name'];
                
                // Try to match "Show.Name.S01E01" or "Show.Name.1x01"
                if (preg_match('/^(.*?)[\.|\s][sS](\d+)[eE](\d+)/', $name, $matches) || 
                    preg_match('/^(.*?)[\.|\s](\d+)x(\d+)/', $name, $matches)) {
                    $showName = trim(str_replace(['.', '_'], ' ', $matches[1]));
                    $season = intval($matches[2]);
                    $episode = intval($matches[3]);
                    
                    // Try to find the show in Sonarr data
                    foreach ($tvShows as $show) {
                        $showTitle = strtolower(trim($show['title']));
                        $matchShowName = strtolower($showName);
                        
                        if ($showTitle === $matchShowName || 
                            strpos($showTitle, $matchShowName) === 0 || 
                            strpos($matchShowName, $showTitle) === 0) {
                            
                            // If we haven't seen this show before, or this is a newer download
                            if (!isset($tvShowsByName[$showTitle]) || 
                                strtotime($download['completed']) > strtotime($tvShowsByName[$showTitle]['completed'])) {
                                
                                $tvShowsByName[$showTitle] = $download;
                                $tvShowsByName[$showTitle]['clean_name'] = $show['title'];
                                $tvShowsByName[$showTitle]['show_id'] = $show['id'];
                                $tvShowsByName[$showTitle]['season'] = $season;
                                $tvShowsByName[$showTitle]['episode'] = $episode;
                                $tvShowsByName[$showTitle]['image'] = isset($show['images']) ? getPosterUrl($show['images']) : '';
                                $tvShowsByName[$showTitle]['episodes_count'] = 1;
                            } else {
                                // Increment episode count for existing show
                                $tvShowsByName[$showTitle]['episodes_count']++;
                            }
                            break;
                        }
                    }
                }
            }
            // For movies, try to match with Radarr
            else if ($type === 'movie') {
                $name = $download['name'];
                
                // Try to clean up movie name using common patterns
                if (preg_match('/^(.*?)[\.\s]\d{4}/', $name, $matches)) {
                    $movieName = trim(str_replace(['.', '_'], ' ', $matches[1]));
                    
                    // Try to find movie in Radarr data
                    foreach ($movies as $movie) {
                        $movieTitle = strtolower(trim($movie['title']));
                        $matchMovieName = strtolower($movieName);
                        
                        if ($movieTitle === $matchMovieName || 
                            strpos($movieTitle, $matchMovieName) === 0 || 
                            strpos($matchMovieName, $movieTitle) === 0) {
                            
                            $download['clean_name'] = $movie['title'];
                            $download['movie_id'] = $movie['id'];
                            $download['image'] = isset($movie['images']) ? getPosterUrl($movie['images']) : '';
                            $download['year'] = isset($movie['year']) ? $movie['year'] : '';
                            
                            $moviesByName[$movieTitle] = $download;
                            break;
                        }
                    }
                }
                
                // If we couldn't match the movie, still add it with original name
                if (!isset($download['clean_name'])) {
                    $processedDownloads[] = $download;
                }
            } else {
                // Other content types
                $processedDownloads[] = $download;
            }
        }
        
        // Combine processed downloads
        $processedDownloads = array_merge(
            $processedDownloads, 
            array_values($tvShowsByName), 
            array_values($moviesByName)
        );
        
        // Sort by completed date (newest first)
        usort($processedDownloads, function($a, $b) {
            return strtotime($b['completed']) - strtotime($a['completed']);
        });
        
        // Apply limit to the final result
        return array_slice($processedDownloads, 0, $limit);
    }
    
    return [];
}

/**
 * Get the poster URL from an array of images
 * 
 * @param array $images Array of image data
 * @return string URL of the poster image or empty string
 */
function getPosterUrl($images) {
    if (!is_array($images)) {
        return '';
    }
    
    // Look for an image with coverType='poster'
    foreach ($images as $image) {
        if (isset($image['coverType']) && $image['coverType'] === 'poster' && isset($image['remoteUrl'])) {
            return $image['remoteUrl'];
        }
    }
    
    // If no poster found but there are images, return the first one
    if (!empty($images) && isset($images[0]['remoteUrl'])) {
        return $images[0]['remoteUrl'];
    }
    
    return '';
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
    $imageKey = '';
    
    // Find the appropriate image from the item's images array
    if (!empty($item['images']) && is_array($item['images'])) {
        foreach ($item['images'] as $image) {
            if (isset($image['coverType']) && strtolower($image['coverType']) === strtolower($type)) {
                $imageUrl = $image['remoteUrl'] ?? $image['url'] ?? '';
                
                // Create a unique key for this image based on ID and type
                if (!empty($item['id'])) {
                    $imageKey = md5($item['id'] . '_' . strtolower($type));
                }
                break;
            }
        }
        
        // If requested type not found, fallback to poster
        if (empty($imageUrl) && $type !== 'poster') {
            foreach ($item['images'] as $image) {
                if (isset($image['coverType']) && strtolower($image['coverType']) === 'poster') {
                    $imageUrl = $image['remoteUrl'] ?? $image['url'] ?? '';
                    
                    // Create a unique key for this image based on ID and fallback type
                    if (!empty($item['id'])) {
                        $imageKey = md5($item['id'] . '_poster');
                    }
                    break;
                }
            }
        }
    }
    
    // If still no image, return empty string
    if (empty($imageUrl)) {
        return '';
    }
    
    // If we have a key, check for cached version first
    if (!empty($imageKey)) {
        // Create a cache directory if it doesn't exist
        $cacheDir = 'cache/images';
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        
        $cacheFile = $cacheDir . '/' . $imageKey . '.jpg';
        
        // Check if the image is already cached and not too old (24 hours)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            // Return the direct path to the cached image
            return $cacheFile;
        }
    }
    
    // Use the proxy URL with a cache param
    return 'api.php?action=proxy_image&cache=' . (!empty($imageKey) ? '1' : '0') . 
           '&key=' . (!empty($imageKey) ? $imageKey : '') . 
           '&url=' . base64_encode($imageUrl);
}

/**
 * Generate sample TV shows data for demo mode
 * 
 * @return array Array of sample TV shows
 */
function getSampleTvShows() {
    $shows = [
        [
            'id' => 1,
            'title' => 'Stranger Things',
            'sortTitle' => 'Stranger Things',
            'status' => 'continuing',
            'overview' => 'When a young boy vanishes, a small town uncovers a mystery involving secret experiments, terrifying supernatural forces, and one strange little girl.',
            'network' => 'Netflix',
            'airTime' => '00:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 8, 'totalEpisodeCount' => 8]],
                ['seasonNumber' => 2, 'statistics' => ['episodeFileCount' => 9, 'totalEpisodeCount' => 9]],
                ['seasonNumber' => 3, 'statistics' => ['episodeFileCount' => 8, 'totalEpisodeCount' => 8]],
                ['seasonNumber' => 4, 'statistics' => ['episodeFileCount' => 9, 'totalEpisodeCount' => 9]]
            ],
            'year' => 2016,
            'path' => '/tv/Stranger Things',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/49WJfeN0moxb9IPfGn8AIqMGskD.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/56v2KjBlU4XaOv9rVYEQypROD7P.jpg']
            ],
            'added' => '2023-01-15T12:00:00Z'
        ],
        [
            'id' => 2,
            'title' => 'The Mandalorian',
            'sortTitle' => 'Mandalorian',
            'status' => 'continuing',
            'overview' => 'After the fall of the Galactic Empire, lawlessness has spread throughout the galaxy. A lone gunfighter makes his way through the outer reaches, earning his keep as a bounty hunter.',
            'network' => 'Disney+',
            'airTime' => '00:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 8, 'totalEpisodeCount' => 8]],
                ['seasonNumber' => 2, 'statistics' => ['episodeFileCount' => 8, 'totalEpisodeCount' => 8]],
                ['seasonNumber' => 3, 'statistics' => ['episodeFileCount' => 8, 'totalEpisodeCount' => 8]]
            ],
            'year' => 2019,
            'path' => '/tv/The Mandalorian',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/sWgBv7LV2PRoQgkxwlibdGXKz1S.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/o7qi2v5RnGFuJE8OeYMSKVgLOgP.jpg']
            ],
            'added' => '2023-02-20T15:30:00Z'
        ],
        [
            'id' => 3,
            'title' => 'Breaking Bad',
            'sortTitle' => 'Breaking Bad',
            'status' => 'ended',
            'overview' => 'A high school chemistry teacher diagnosed with inoperable lung cancer turns to manufacturing and selling methamphetamine in order to secure his family\'s future.',
            'network' => 'AMC',
            'airTime' => '22:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 7, 'totalEpisodeCount' => 7]],
                ['seasonNumber' => 2, 'statistics' => ['episodeFileCount' => 13, 'totalEpisodeCount' => 13]],
                ['seasonNumber' => 3, 'statistics' => ['episodeFileCount' => 13, 'totalEpisodeCount' => 13]],
                ['seasonNumber' => 4, 'statistics' => ['episodeFileCount' => 13, 'totalEpisodeCount' => 13]],
                ['seasonNumber' => 5, 'statistics' => ['episodeFileCount' => 16, 'totalEpisodeCount' => 16]]
            ],
            'year' => 2008,
            'path' => '/tv/Breaking Bad',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/ggFHVNu6YYI5L9pCfOacjizRGt.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg']
            ],
            'added' => '2022-10-05T08:45:00Z'
        ],
        [
            'id' => 4,
            'title' => 'The Office',
            'sortTitle' => 'Office',
            'status' => 'ended',
            'overview' => 'A mockumentary on a group of typical office workers, where the workday consists of ego clashes, inappropriate behavior, and tedium.',
            'network' => 'NBC',
            'airTime' => '21:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 6, 'totalEpisodeCount' => 6]],
                ['seasonNumber' => 2, 'statistics' => ['episodeFileCount' => 22, 'totalEpisodeCount' => 22]],
                ['seasonNumber' => 3, 'statistics' => ['episodeFileCount' => 25, 'totalEpisodeCount' => 25]],
                ['seasonNumber' => 4, 'statistics' => ['episodeFileCount' => 19, 'totalEpisodeCount' => 19]],
                ['seasonNumber' => 5, 'statistics' => ['episodeFileCount' => 28, 'totalEpisodeCount' => 28]],
                ['seasonNumber' => 6, 'statistics' => ['episodeFileCount' => 26, 'totalEpisodeCount' => 26]],
                ['seasonNumber' => 7, 'statistics' => ['episodeFileCount' => 26, 'totalEpisodeCount' => 26]],
                ['seasonNumber' => 8, 'statistics' => ['episodeFileCount' => 24, 'totalEpisodeCount' => 24]],
                ['seasonNumber' => 9, 'statistics' => ['episodeFileCount' => 23, 'totalEpisodeCount' => 23]]
            ],
            'year' => 2005,
            'path' => '/tv/The Office',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/qWnJzyZhyy74gjpSjIXWmuk0ifX.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/vNpuAxGTl9HsUbHqam3E9CzqCvX.jpg']
            ],
            'added' => '2023-03-12T14:20:00Z'
        ],
        [
            'id' => 5,
            'title' => 'The Last of Us',
            'sortTitle' => 'Last of Us',
            'status' => 'continuing',
            'overview' => 'Twenty years after modern civilization has been destroyed, Joel, a hardened survivor, is hired to smuggle Ellie, a 14-year-old girl, out of an oppressive quarantine zone. What starts as a small job soon becomes a brutal, heartbreaking journey.',
            'network' => 'HBO',
            'airTime' => '21:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 9, 'totalEpisodeCount' => 9]]
            ],
            'year' => 2023,
            'path' => '/tv/The Last of Us',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/uKvVjHNqB5VmOrdxqAt2F7J78ED.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/uDgy6hyPd82kOHh6I95FLtLnNIh.jpg']
            ],
            'added' => '2023-01-25T17:15:00Z'
        ],
        [
            'id' => 6,
            'title' => 'Game of Thrones',
            'sortTitle' => 'Game of Thrones',
            'status' => 'ended',
            'overview' => 'Seven noble families fight for control of the mythical land of Westeros. Friction between the houses leads to full-scale war. All while a very ancient evil awakens in the farthest north.',
            'network' => 'HBO',
            'airTime' => '21:00',
            'seasons' => [
                ['seasonNumber' => 1, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 2, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 3, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 4, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 5, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 6, 'statistics' => ['episodeFileCount' => 10, 'totalEpisodeCount' => 10]],
                ['seasonNumber' => 7, 'statistics' => ['episodeFileCount' => 7, 'totalEpisodeCount' => 7]],
                ['seasonNumber' => 8, 'statistics' => ['episodeFileCount' => 6, 'totalEpisodeCount' => 6]]
            ],
            'year' => 2011,
            'path' => '/tv/Game of Thrones',
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/u3bZgnGQ9T01sWNhyveQz0wH0Hl.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/suopoADq0k8YZr4dQXcU6pToj6s.jpg']
            ],
            'added' => '2022-05-18T09:10:00Z'
        ]
    ];
    
    return $shows;
}

/**
 * Generate sample upcoming episodes for demo mode
 * 
 * @return array Array of sample upcoming episodes
 */
function getSampleUpcomingEpisodes() {
    $today = new DateTime();
    $episodes = [];
    
    // Add sample episodes for the next 7 days
    for ($i = 0; $i < 10; $i++) {
        $date = clone $today;
        $date->modify('+' . rand(0, 7) . ' days');
        
        // Randomly pick a show
        $showId = rand(1, 6);
        $showTitle = '';
        
        switch ($showId) {
            case 1: $showTitle = 'Stranger Things'; break;
            case 2: $showTitle = 'The Mandalorian'; break;
            case 3: $showTitle = 'Breaking Bad'; break;
            case 4: $showTitle = 'The Office'; break;
            case 5: $showTitle = 'The Last of Us'; break;
            case 6: $showTitle = 'Game of Thrones'; break;
        }
        
        // Create episode info
        $season = rand(1, 4);
        $episode = rand(1, 10);
        
        $episodes[] = [
            'id' => 1000 + $i,
            'seriesId' => $showId,
            'episodeNumber' => $episode,
            'seasonNumber' => $season,
            'title' => 'Episode ' . $episode,
            'airDate' => $date->format('Y-m-d'),
            'airDateUtc' => $date->format('Y-m-d\TH:i:s\Z'),
            'series' => [
                'id' => $showId,
                'title' => $showTitle,
                'status' => ($showId % 2 == 0) ? 'continuing' : 'ended'
            ],
            'seriesTitle' => $showTitle
        ];
    }
    
    // Sort by air date
    usort($episodes, function($a, $b) {
        return strtotime($a['airDate']) - strtotime($b['airDate']);
    });
    
    return $episodes;
}

/**
 * Generate sample movies data for demo mode
 * 
 * @return array Array of sample movies
 */
function getSampleMovies() {
    $movies = [
        [
            'id' => 101,
            'title' => 'The Shawshank Redemption',
            'originalTitle' => 'The Shawshank Redemption',
            'year' => 1994,
            'overview' => 'Framed in the 1940s for the double murder of his wife and her lover, upstanding banker Andy Dufresne begins a new life at the Shawshank prison, where he puts his accounting skills to work for an amoral warden.',
            'runtime' => 142,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/kXfqcdQKsToO0OUXHcrrNCHDBzO.jpg']
            ],
            'genres' => ['Drama', 'Crime'],
            'studio' => 'Columbia Pictures',
            'path' => '/movies/The Shawshank Redemption (1994)',
            'added' => '2023-01-10T08:30:00Z'
        ],
        [
            'id' => 102,
            'title' => 'The Dark Knight',
            'originalTitle' => 'The Dark Knight',
            'year' => 2008,
            'overview' => 'Batman raises the stakes in his war on crime. With the help of Lt. Jim Gordon and District Attorney Harvey Dent, Batman sets out to dismantle the remaining criminal organizations that plague the streets.',
            'runtime' => 152,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/qJ2tW6WMUDux911r6m7haRef0WH.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/hkBaDkMWbLaf8B1lsWsKX7Ew3Xq.jpg']
            ],
            'genres' => ['Action', 'Crime', 'Drama', 'Thriller'],
            'studio' => 'Warner Bros. Pictures',
            'path' => '/movies/The Dark Knight (2008)',
            'added' => '2023-02-15T12:45:00Z'
        ],
        [
            'id' => 103,
            'title' => 'Pulp Fiction',
            'originalTitle' => 'Pulp Fiction',
            'year' => 1994,
            'overview' => 'A burger-loving hit man, his philosophical partner, a drug-addled gangster\'s moll and a washed-up boxer converge in this sprawling, comedic crime caper.',
            'runtime' => 154,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/d5iIlFn5s0ImszYzBPb8JPIfbXD.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/suaEOtk1N1sgg2QM528GlJLNN9o.jpg']
            ],
            'genres' => ['Thriller', 'Crime'],
            'studio' => 'Miramax Films',
            'path' => '/movies/Pulp Fiction (1994)',
            'added' => '2022-11-20T10:15:00Z'
        ],
        [
            'id' => 104,
            'title' => 'The Matrix',
            'originalTitle' => 'The Matrix',
            'year' => 1999,
            'overview' => 'Set in the 22nd century, The Matrix tells the story of a computer hacker who joins a group of underground insurgents fighting the vast and powerful computers who now rule the earth.',
            'runtime' => 136,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/fNG7i7RqMErkcqhohV2a6cV1Ehy.jpg']
            ],
            'genres' => ['Action', 'Science Fiction'],
            'studio' => 'Warner Bros. Pictures',
            'path' => '/movies/The Matrix (1999)',
            'added' => '2023-03-05T16:20:00Z'
        ],
        [
            'id' => 105,
            'title' => 'Inception',
            'originalTitle' => 'Inception',
            'year' => 2010,
            'overview' => 'Cobb, a skilled thief who commits corporate espionage by infiltrating the subconscious of his targets is offered a chance to regain his old life as payment for a task considered to be impossible: "inception", the implantation of another person\'s idea into a target\'s subconscious.',
            'runtime' => 148,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/s3TBrRGB1iav7gFOCNx3H31MoES.jpg']
            ],
            'genres' => ['Action', 'Science Fiction', 'Adventure'],
            'studio' => 'Warner Bros. Pictures',
            'path' => '/movies/Inception (2010)',
            'added' => '2022-12-28T14:10:00Z'
        ],
        [
            'id' => 106,
            'title' => 'Interstellar',
            'originalTitle' => 'Interstellar',
            'year' => 2014,
            'overview' => 'The adventures of a group of explorers who make use of a newly discovered wormhole to surpass the limitations on human space travel and conquer the vast distances involved in an interstellar voyage.',
            'runtime' => 169,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/rAiYTfKGqDCRIIqo664sY9XZIvQ.jpg']
            ],
            'genres' => ['Adventure', 'Drama', 'Science Fiction'],
            'studio' => 'Paramount Pictures',
            'path' => '/movies/Interstellar (2014)',
            'added' => '2023-01-05T11:30:00Z'
        ],
        [
            'id' => 107,
            'title' => 'Avengers: Endgame',
            'originalTitle' => 'Avengers: Endgame',
            'year' => 2019,
            'overview' => 'After the devastating events of Avengers: Infinity War, the universe is in ruins due to the efforts of the Mad Titan, Thanos. With the help of remaining allies, the Avengers must assemble once more in order to undo Thanos\'s actions and restore order to the universe once and for all, no matter what consequences may be in store.',
            'runtime' => 181,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/or06FN3Dka5tukK1e9sl16pB3iy.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg']
            ],
            'genres' => ['Adventure', 'Science Fiction', 'Action'],
            'studio' => 'Marvel Studios',
            'path' => '/movies/Avengers Endgame (2019)',
            'added' => '2023-02-28T09:15:00Z'
        ],
        [
            'id' => 108,
            'title' => 'The Godfather',
            'originalTitle' => 'The Godfather',
            'year' => 1972,
            'overview' => 'Spanning the years 1945 to 1955, a chronicle of the fictional Italian-American Corleone crime family. When organized crime family patriarch, Vito Corleone barely survives an attempt on his life, his youngest son, Michael steps in to take care of the would-be killers, launching a campaign of bloody revenge.',
            'runtime' => 175,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/3bhkrj58Vtu7enYsRolD1fZdja1.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/rSPw7tgCH9c6NqICZef4kZjFOQ5.jpg']
            ],
            'genres' => ['Drama', 'Crime'],
            'studio' => 'Paramount Pictures',
            'path' => '/movies/The Godfather (1972)',
            'added' => '2022-10-14T07:45:00Z'
        ]
    ];
    
    return $movies;
}

/**
 * Generate sample upcoming movies for demo mode
 * 
 * @return array Array of sample upcoming movies
 */
function getSampleUpcomingMovies() {
    $upcoming = [
        [
            'id' => 201,
            'title' => 'Dune: Part Two',
            'originalTitle' => 'Dune: Part Two',
            'year' => 2024,
            'inCinemas' => '2024-03-01T00:00:00Z',
            'physicalRelease' => '2024-05-30T00:00:00Z',
            'overview' => 'Follow the mythic journey of Paul Atreides as he unites with Chani and the Fremen while on a warpath of revenge against the conspirators who destroyed his family.',
            'runtime' => 166,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/4fLZUr1e65hKPPM39AZWZiRKElo.jpg']
            ],
            'genres' => ['Adventure', 'Science Fiction']
        ],
        [
            'id' => 202,
            'title' => 'Deadpool & Wolverine',
            'originalTitle' => 'Deadpool & Wolverine',
            'year' => 2024,
            'inCinemas' => '2024-07-26T00:00:00Z',
            'physicalRelease' => '2024-10-15T00:00:00Z',
            'overview' => 'Wolverine joins the "merc with a mouth" for an adventure across the multiverse.',
            'runtime' => 120,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/8hJqXjHGFnNJvBoJ2VgxAVdXPmX.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/697j9EYwFw4XJ6NyQUPQN9q8piX.jpg']
            ],
            'genres' => ['Action', 'Comedy', 'Science Fiction']
        ],
        [
            'id' => 203,
            'title' => 'Kingdom of the Planet of the Apes',
            'originalTitle' => 'Kingdom of the Planet of the Apes',
            'year' => 2024,
            'inCinemas' => '2024-05-10T00:00:00Z',
            'physicalRelease' => '2024-08-20T00:00:00Z',
            'overview' => 'Several generations in the future following Caesar\'s reign, apes are now the dominant species and humans have been reduced to living in the shadows.',
            'runtime' => 145,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/zvOfoRZH8fgRiQHb6OkwQO9Uie5.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/mDeUmPe4MF35xk6wKWG8K3OPwh7.jpg']
            ],
            'genres' => ['Science Fiction', 'Adventure', 'Action']
        ],
        [
            'id' => 204,
            'title' => 'Twisters',
            'originalTitle' => 'Twisters',
            'year' => 2024,
            'inCinemas' => '2024-07-19T00:00:00Z',
            'physicalRelease' => '2024-10-01T00:00:00Z',
            'overview' => 'A sequel to the 1996 film Twister, focusing on a new generation of storm chasers.',
            'runtime' => 130,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/yRm3F2G8NzK5Y6gQIWP8cT2V3rw.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/4i1t5YJQu5mEg231YWrOoBdxo4y.jpg']
            ],
            'genres' => ['Action', 'Adventure', 'Drama']
        ],
        [
            'id' => 205,
            'title' => 'Furiosa: A Mad Max Saga',
            'originalTitle' => 'Furiosa: A Mad Max Saga',
            'year' => 2024,
            'inCinemas' => '2024-05-24T00:00:00Z',
            'physicalRelease' => '2024-08-27T00:00:00Z',
            'overview' => 'As the world fell, young Furiosa is snatched from the Green Place of Many Mothers and falls into the hands of a great Biker Horde led by the Warlord Dementus. Sweeping through the Wasteland, they come across the Citadel presided over by The Immortan Joe.',
            'runtime' => 150,
            'images' => [
                ['coverType' => 'poster', 'remoteUrl' => 'https://image.tmdb.org/t/p/w500/kdYg1wEURdKa5K9NSRGnCmWMKNu.jpg'],
                ['coverType' => 'fanart', 'remoteUrl' => 'https://image.tmdb.org/t/p/original/rz8s4SJcnYIFBnDR69y725a0QJG.jpg']
            ],
            'genres' => ['Action', 'Adventure', 'Science Fiction']
        ]
    ];
    
    return $upcoming;
}

/**
 * Generate sample SABnzbd queue data for demo mode
 * 
 * @return array Sample queue data
 */
function getSampleSabnzbdQueue() {
    $slotCount = rand(2, 5);
    $slots = [];
    
    $totalMb = 0;
    for ($i = 0; $i < $slotCount; $i++) {
        $mb = rand(500, 5000);
        $totalMb += $mb;
        $mbleft = $mb * (1 - (rand(10, 90) / 100));
        $percentage = round(100 - (($mbleft / $mb) * 100));
        
        $timeRemaining = gmdate("H:i:s", rand(300, 7200));
        
        switch ($i) {
            case 0:
                $filename = 'The.Last.of.Us.S02E02.1080p.WEB.H264-EXPLOIT';
                break;
            case 1:
                $filename = 'Interstellar.2014.UHD.BluRay.2160p.HEVC.TrueHD.7.1.Atmos-HDBEE';
                break;
            case 2:
                $filename = 'Dune.Part.Two.2024.1080p.WEBRip.x264-RARBG';
                break;
            case 3:
                $filename = 'Breaking.Bad.Complete.Series.1080p.BluRay.x264';
                break;
            case 4:
                $filename = 'Stranger.Things.S04.COMPLETE.1080p.NF.WEBRip.x264-GalaxyTV';
                break;
            default:
                $filename = 'Random.Download.' . rand(1000, 9999);
        }
        
        $slots[] = [
            'id' => 'nzb_' . rand(1000000, 9999999),
            'filename' => $filename,
            'index' => $i,
            'eta' => $timeRemaining,
            'status' => 'Downloading',
            'mb' => $mb,
            'mbleft' => $mbleft,
            'percentage' => $percentage,
            'timeleft' => $timeRemaining,
            'category' => ($i % 2 == 0) ? 'tv' : 'movies',
        ];
    }
    
    return [
        'slots' => $slots,
        'paused' => false,
        'timeleft' => gmdate("H:i:s", rand(1800, 14400)),
        'mb' => $totalMb,
        'mbleft' => $totalMb * 0.4,
        'kbpersec' => rand(2000, 20000),
        'speedlimit' => '0',
        'status' => 'Downloading',
    ];
}

/**
 * Generate sample SABnzbd history data for demo mode
 * 
 * @param int $limit Number of history items to generate
 * @return array Sample history data
 */
function getSampleSabnzbdHistory($limit = 10) {
    $slots = [];
    $now = new DateTime();
    
    for ($i = 0; $i < $limit; $i++) {
        $completed = clone $now;
        $completed->modify('-' . rand(1, 14) . ' days');
        $completed->modify('-' . rand(0, 23) . ' hours');
        $completed->modify('-' . rand(0, 59) . ' minutes');
        
        $sizeGb = rand(1, 30);
        
        // Determine type and name
        $type = ($i % 3 == 0) ? 'movie' : 'tv';
        $name = '';
        $category = $type;
        
        if ($type === 'tv') {
            $shows = ['Stranger Things', 'Breaking Bad', 'The Mandalorian', 'The Last of Us', 'Game of Thrones'];
            $showName = $shows[array_rand($shows)];
            $season = rand(1, 4);
            $episode = rand(1, 12);
            $name = str_replace(' ', '.', $showName) . '.S' . str_pad($season, 2, '0', STR_PAD_LEFT) . 'E' . str_pad($episode, 2, '0', STR_PAD_LEFT) . '.1080p.WEB.x264';
        } else {
            $movies = ['The Shawshank Redemption', 'The Dark Knight', 'Inception', 'Interstellar', 'Pulp Fiction', 'The Matrix'];
            $movieName = $movies[array_rand($movies)];
            $year = rand(1990, 2023);
            $name = str_replace(' ', '.', $movieName) . '.' . $year . '.1080p.BluRay.x264';
        }
        
        $slots[] = [
            'id' => 'history_' . rand(1000000, 9999999),
            'name' => $name,
            'status' => 'Completed',
            'bytes' => $sizeGb * 1024 * 1024 * 1024,
            'size' => $sizeGb . ' GB',
            'category' => $category,
            'completed' => $completed->format('Y-m-d\TH:i:s\Z'),
            'nzo_id' => 'nzo_' . rand(10000000, 99999999),
            'download_time' => rand(1800, 14400),
            'type' => $type,
            'size_float' => $sizeGb
        ];
    }
    
    // Sort by completed time (newest first)
    usort($slots, function($a, $b) {
        return strtotime($b['completed']) - strtotime($a['completed']);
    });
    
    return [
        'slots' => $slots,
        'noofslots' => count($slots),
        'noofslots_total' => $limit,
    ];
}
