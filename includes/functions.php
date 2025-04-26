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
 * @param int $id Show ID
 * @param bool $demoMode Whether to use demo mode
 * @return array|bool Show details or false on failure
 */
function getSonarrShowDetails($url, $apiKey, $id, $demoMode = false) {
    $shows = getSonarrShows($url, $apiKey, $demoMode);
    if (!$shows || !is_array($shows)) {
        return false;
    }
    
    foreach ($shows as $show) {
        if (isset($show['id']) && (int)$show['id'] === (int)$id) {
            return $show;
        }
    }
    
    // If we're in demo mode and couldn't find the show, return the first sample show
    if ($demoMode && !empty($shows)) {
        return $shows[0];
    }
    
    return false;
}

/**
 * Get all episodes for a TV show from Sonarr
 * 
 * @param string $url Sonarr base URL
 * @param string $apiKey Sonarr API key
 * @param int $seriesId TV show ID
 * @param bool $demoMode Whether to use demo mode
 * @return array Array of episodes
 */
function getSonarrEpisodes($url, $apiKey, $seriesId, $demoMode = false) {
    $episodes = makeApiRequest($url, 'api/v3/episode', ['seriesId' => $seriesId], $apiKey);
    
    if ((!$episodes || !is_array($episodes)) && $demoMode) {
        // Generate sample episodes for the show
        $sampleEpisodes = [];
        $show = getSonarrShowDetails($url, $apiKey, $seriesId, true);
        
        if ($show) {
            // Create episodes for each season
            if (isset($show['seasons']) && is_array($show['seasons'])) {
                foreach ($show['seasons'] as $season) {
                    $seasonNumber = $season['seasonNumber'] ?? 1;
                    $episodeCount = $season['episodeCount'] ?? 10;
                    
                    // Skip season 0 (specials)
                    if ($seasonNumber > 0) {
                        for ($i = 1; $i <= $episodeCount; $i++) {
                            $sampleEpisodes[] = [
                                'id' => 10000 + ($seasonNumber * 100) + $i,
                                'seriesId' => $seriesId,
                                'seasonNumber' => $seasonNumber,
                                'episodeNumber' => $i,
                                'title' => "Episode $i",
                                'airDate' => date('Y-m-d', strtotime("-" . (($seasonNumber - 1) * 100 + $i) . " days")),
                                'hasFile' => true,
                                'monitored' => true
                            ];
                        }
                    }
                }
            } else {
                // If no seasons info, create default seasons and episodes
                for ($season = 1; $season <= 3; $season++) {
                    for ($episode = 1; $episode <= 10; $episode++) {
                        $sampleEpisodes[] = [
                            'id' => 10000 + ($season * 100) + $episode,
                            'seriesId' => $seriesId,
                            'seasonNumber' => $season,
                            'episodeNumber' => $episode,
                            'title' => "Episode $episode",
                            'airDate' => date('Y-m-d', strtotime("-" . (($season - 1) * 100 + $episode) . " days")),
                            'hasFile' => true,
                            'monitored' => true
                        ];
                    }
                }
            }
        }
        
        return $sampleEpisodes;
    }
    
    return $episodes ?: [];
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
    // If demo mode is enabled, return sample data first
    if ($demoMode) {
        return getSampleUpcomingEpisodes();
    }
    
    // Otherwise try the real API if URL and key are provided
    if (!empty($url) && !empty($apiKey)) {
        $episodes = makeApiRequest($url, 'api/v3/calendar', [
            'start' => date('Y-m-d'),
            'end' => date('Y-m-d', strtotime('+7 days')),
            'includeEpisodeFile' => 'true',
            'includeEpisodeImages' => 'false',
            'includeSeriesImages' => 'false'
        ], $apiKey);
        
        if ($episodes && is_array($episodes)) {
            return $episodes;
        }
    }
    
    // If we get here, either API request failed or we don't have real credentials
    // Only use sample data if demo mode was explicitly requested
    if ($demoMode) {
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
 * @param bool $demoMode Whether to use demo mode
 * @return array|bool Movie details or false on failure
 */
function getRadarrMovieDetails($url, $apiKey, $id, $demoMode = false) {
    $movies = getRadarrMovies($url, $apiKey, $demoMode);
    if (!$movies || !is_array($movies)) {
        return false;
    }
    
    foreach ($movies as $movie) {
        if (isset($movie['id']) && (int)$movie['id'] === (int)$id) {
            return $movie;
        }
    }
    
    // If we're in demo mode and couldn't find the movie, return the first sample movie
    if ($demoMode && !empty($movies)) {
        return $movies[0];
    }
    
    return false;
}

/**
 * Get upcoming movies from Radarr
 * 
 * @param string $url Radarr base URL
 * @param string $apiKey Radarr API key
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Array of upcoming movies
 */
function getUpcomingMovies($url, $apiKey, $demoMode = false) {
    // If in demo mode, return sample data directly
    if ($demoMode) {
        return getSampleUpcomingMovies();
    }
    
    // Only try real API if we have credentials
    if (!empty($url) && !empty($apiKey)) {
        $movies = getRadarrMovies($url, $apiKey, false);
        if (!$movies || !is_array($movies)) {
            return [];
        }
    } else {
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Queue data
 */
function getSabnzbdQueue($url, $apiKey, $demoMode = false) {
    $response = makeApiRequest($url, 'api', [
        'mode' => 'queue',
        'output' => 'json'
    ], $apiKey);
    
    if ((!isset($response['queue']) || empty($response['queue'])) && $demoMode) {
        // Use demo data if API request failed and demo mode is enabled
        return getSampleSabnzbdQueue();
    }
    
    return isset($response['queue']) ? $response['queue'] : [];
}

/**
 * Get history data from SABnzbd with pagination
 * 
 * @param string $url SABnzbd base URL
 * @param string $apiKey SABnzbd API key
 * @param int $page Page number (starting at 1)
 * @param int $limit Items per page
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array History data with pagination info
 */
function getSabnzbdHistory($url, $apiKey, $page = 1, $limit = 10, $demoMode = false) {
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
    
    // Check if we need to use demo data
    if ((!isset($response['history']) || empty($response['history'])) && $demoMode) {
        $demoHistory = getSampleSabnzbdHistory($limit);
        $total = $demoHistory['noofslots_total'];
        $totalPages = ceil($total / $limit);
        
        // Add pagination info to demo data
        $demoHistory['pagination'] = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $limit
        ];
        
        return $demoHistory;
    }
    
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
 * @param bool $demoMode Whether to use demo data when API is unavailable
 * @return array Recently completed downloads
 */
function getRecentlyDownloadedContent($url, $apiKey, $limit = 6, $sonarrUrl = '', $sonarrApiKey = '', $radarrUrl = '', $radarrApiKey = '', $demoMode = false) {
    $response = makeApiRequest($url, 'api', [
        'mode' => 'history',
        'output' => 'json',
        'limit' => 30 // Get more items initially so we can consolidate them
    ], $apiKey);
    
    // Check if we need to use demo data
    if ((!isset($response['history']) || !isset($response['history']['slots']) || empty($response['history']['slots'])) && $demoMode) {
        // Use demo data with the sampling history function
        $demoHistory = getSampleSabnzbdHistory($limit);
        // Process the demo history slots to match the expected format
        $processedDownloads = [];
        
        foreach ($demoHistory['slots'] as $item) {
            // Extract basic information
            $processedItem = [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'category' => $item['category'],
                'completed' => $item['completed'],
                'size' => $item['size'],
                'clean_name' => $item['name']
            ];
            
            // Check if it's a TV show and add additional info
            if ($item['type'] === 'tv') {
                // Try to match a show name pattern
                if (preg_match('/^(.*?)[\.|\s][sS](\d+)[eE](\d+)/', $item['name'], $matches)) {
                    $showName = trim(str_replace(['.', '_'], ' ', $matches[1]));
                    $processedItem['clean_name'] = $showName;
                    $processedItem['season'] = intval($matches[2]);
                    $processedItem['episode'] = intval($matches[3]);
                    $processedItem['episodes_count'] = 1;
                }
                
                // Try to match with a sample TV show to get image
                foreach (getSampleTvShows() as $show) {
                    if (stripos($processedItem['clean_name'], $show['title']) !== false) {
                        $processedItem['image'] = getPosterUrl($show['images']);
                        $processedItem['show_id'] = $show['id'];
                        break;
                    }
                }
            }
            // Check if it's a movie and add additional info
            else if ($item['type'] === 'movie') {
                // Try to match a movie name pattern
                if (preg_match('/^(.*?)[\.\s]\d{4}/', $item['name'], $matches)) {
                    $movieName = trim(str_replace(['.', '_'], ' ', $matches[1]));
                    $processedItem['clean_name'] = $movieName;
                }
                
                // Try to match with a sample movie to get image
                foreach (getSampleMovies() as $movie) {
                    if (stripos($processedItem['clean_name'], $movie['title']) !== false) {
                        $processedItem['image'] = getPosterUrl($movie['images']);
                        $processedItem['movie_id'] = $movie['id'];
                        $processedItem['year'] = $movie['year'];
                        break;
                    }
                }
            }
            
            $processedDownloads[] = $processedItem;
        }
        
        // Sort by completed date (newest first)
        usort($processedDownloads, function($a, $b) {
            return strtotime($b['completed']) - strtotime($a['completed']);
        });
        
        // Return the limited result
        return array_slice($processedDownloads, 0, $limit);
    }
    else if (isset($response['history']) && isset($response['history']['slots'])) {
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
    // Add timestamp-based seed for randomness
    $timestamp = time();
    $dayOfYear = date('z', $timestamp);
    $hourOfDay = date('G', $timestamp);
    
    // Use a combination of day of year and hour to create pseudo-random content rotation
    $seed = $dayOfYear + $hourOfDay;
    srand($seed);
    
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
    
    // Shuffle the shows array to get different ordering each time
    shuffle($shows);
    
    // Return a subset of shows to create variation
    return array_slice($shows, 0, min(count($shows), 6 + ($seed % 3)));
}

/**
 * Generate sample upcoming episodes for demo mode
 * 
 * @return array Array of sample upcoming episodes
 */
/**
 * Get sample episodes for a specific show ID
 * 
 * @param int $showId The show ID
 * @return array Sample episodes
 */
function getSampleEpisodes($showId = 0) {
    // If no show ID specified, use a default one
    if ($showId <= 0) {
        $showId = 1; // Default to first show
    }
    
    // Get sample TV shows to match details
    $shows = getSampleTvShows();
    
    // Find the show by ID
    $show = null;
    foreach ($shows as $s) {
        if (isset($s['id']) && $s['id'] == $showId) {
            $show = $s;
            break;
        }
    }
    
    // If show not found, use the first one
    if (!$show && !empty($shows)) {
        $show = $shows[0];
        $showId = $show['id'];
    }
    
    // Create 2-3 seasons with 8-12 episodes each
    $episodes = [];
    $seasons = [1, 2];
    if ($showId % 3 == 0) {
        $seasons[] = 3; // Some shows have 3 seasons
    }
    
    // For each season
    foreach ($seasons as $season) {
        // Determine how many episodes in this season
        $episodeCount = rand(8, 12);
        
        // For each episode
        for ($episode = 1; $episode <= $episodeCount; $episode++) {
            // Create a unique episode ID
            $episodeId = ($showId * 1000) + ($season * 100) + $episode;
            
            // Create the episode data
            $episodeData = [
                'id' => $episodeId,
                'seriesId' => $showId,
                'seasonNumber' => $season,
                'episodeNumber' => $episode,
                'title' => "Episode " . $episode,
                'airDate' => date('Y-m-d', strtotime("-" . (365 - ($season * 30) - $episode) . " days")),
                'hasFile' => ($episode % 4 != 0), // Most episodes have files, some don't
                'monitored' => true,
                'absoluteEpisodeNumber' => (($season - 1) * $episodeCount) + $episode,
                'overview' => "This is a sample episode description for season $season episode $episode.",
                'episodeFile' => [
                    'id' => $episodeId,
                    'size' => rand(200, 800) * 1024 * 1024, // 200-800 MB
                    'quality' => [
                        'quality' => [
                            'id' => 1,
                            'name' => ($season == 1) ? "HDTV-720p" : "WEB-DL-1080p"
                        ],
                        'revision' => [
                            'version' => 1
                        ]
                    ],
                    'dateAdded' => date('Y-m-d', strtotime("-" . (300 - ($season * 30) - $episode) . " days")),
                    'mediaInfo' => [
                        'videoCodec' => 'x264',
                        'audioCodec' => 'AAC',
                        'audioChannels' => 2.0,
                        'videoResolution' => ($season == 1) ? "720p" : "1080p"
                    ]
                ]
            ];
            
            // Add the episode
            $episodes[] = $episodeData;
        }
    }
    
    return $episodes;
}

function getSampleUpcomingEpisodes() {
    $today = new DateTime();
    $episodes = [];
    
    // Sample shows with their data
    $shows = [
        [
            'id' => 1,
            'title' => 'Stranger Things',
            'season' => 5,
            'episodes' => 8,
            'image' => 'https://image.tmdb.org/t/p/original/49WJfeN0moxb9IPfGn8AIqMGskD.jpg'
        ],
        [
            'id' => 2,
            'title' => 'The Mandalorian',
            'season' => 4,
            'episodes' => 10,
            'image' => 'https://image.tmdb.org/t/p/original/sWgBv7LV2PRoQgkxwlibdGXKz1S.jpg'
        ],
        [
            'id' => 3,
            'title' => 'Ted Lasso',
            'season' => 3,
            'episodes' => 12,
            'image' => 'https://image.tmdb.org/t/p/original/oX7QdfiQEbyvIvpKgJHRCgbrLdK.jpg'
        ],
        [
            'id' => 4,
            'title' => 'The Last of Us',
            'season' => 2,
            'episodes' => 10, 
            'image' => 'https://image.tmdb.org/t/p/original/uKvVjHNqB5VmOrdxqAt2F7J78ED.jpg'
        ],
        [
            'id' => 5,
            'title' => 'The Boys',
            'season' => 4,
            'episodes' => 8,
            'image' => 'https://image.tmdb.org/t/p/original/stTEycfG9928HYGEISBFaG1ngjM.jpg'
        ],
        [
            'id' => 6,
            'title' => 'House of the Dragon',
            'season' => 2,
            'episodes' => 10,
            'image' => 'https://image.tmdb.org/t/p/original/xiB0hsxMpgvEWsABtJucmkECekZ.jpg'
        ]
    ];
    
    // Set up some friendly date labels
    $dateLabels = [
        0 => 'Today',
        1 => 'Tomorrow'
    ];
    
    // Add days of the week for the next 5 days
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    for ($i = 2; $i < 7; $i++) {
        $futureDate = clone $today;
        $futureDate->modify('+' . $i . ' days');
        $dateLabels[$i] = $daysOfWeek[$futureDate->format('w')];
    }
    
    // Add sample episodes for the next 7 days
    for ($i = 0; $i < 7; $i++) {
        $date = clone $today;
        $date->modify('+' . $i . ' days');
        
        // Get a random show from our list
        $showIndex = rand(0, count($shows) - 1);
        $show = $shows[$showIndex];
        
        // Create episode info
        $season = $show['season'];
        $episode = rand(1, $show['episodes']);
        
        $episodes[] = [
            'id' => 1000 + $i,
            'seriesId' => $show['id'],
            'episodeNumber' => $episode,
            'seasonNumber' => $season,
            'title' => 'Episode ' . $episode,
            'airDate' => $date->format('Y-m-d'),
            'airDateUtc' => $date->format('Y-m-d\TH:i:s\Z'),
            'displayDate' => $dateLabels[$i] ?? date('M d', strtotime($date->format('Y-m-d'))),
            'series' => [
                'id' => $show['id'],
                'title' => $show['title'],
                'status' => 'continuing',
                'images' => [
                    [
                        'coverType' => 'poster',
                        'remoteUrl' => $show['image']
                    ]
                ]
            ],
            'seriesTitle' => $show['title']
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
    // Add timestamp-based seed for randomness
    $timestamp = time();
    $dayOfYear = date('z', $timestamp);
    $hourOfDay = date('G', $timestamp);
    
    // Use a combination of day of year and hour to create pseudo-random content rotation
    $seed = $dayOfYear + $hourOfDay;
    srand($seed);
    
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
    
    // Shuffle the movies array to get different ordering each time
    shuffle($movies);
    
    // Return a subset of movies to create variation
    return array_slice($movies, 0, min(count($movies), 6 + ($seed % 3)));
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

/**
 * Process recent downloads to match with show/movie metadata
 * 
 * @param array $downloads The download history items
 * @param array $shows The TV shows data
 * @param array $movies The movies data 
 * @param bool $demoMode Whether to use demo mode
 * @return array Processed download items with metadata
 */
function processRecentDownloads($downloads, $shows, $movies, $demoMode = false) {
    // If demo mode is enabled and there are no downloads, generate sample data
    if ($demoMode && empty($downloads)) {
        $downloads = getSampleDownloadHistory();
    }
    
    // If still no downloads or no metadata, return empty array
    if (empty($downloads) || (empty($shows) && empty($movies))) {
        return [];
    }
    
    // Use the processor class to match downloads with their content
    $processor = new RecentDownloadsProcessor($downloads, $shows, $movies);
    return $processor->getProcessedDownloads();
}

/**
 * Class to process recent download content for demo mode
 */
class RecentDownloadsProcessor {
    private $downloads;
    private $shows;
    private $movies;
    private $processedDownloads = [];
    private $tvShowsByName = [];
    private $moviesByName = [];
    
    /**
     * Constructor
     * 
     * @param array $downloads The download history items
     * @param array $shows The TV shows data
     * @param array $movies The movies data
     */
    public function __construct($downloads, $shows, $movies) {
        $this->downloads = $downloads;
        $this->shows = $shows;
        $this->movies = $movies;
        $this->processDownloads();
    }
    
    /**
     * Process the downloads and match them with shows/movies
     */
    private function processDownloads() {
        foreach ($this->downloads as $download) {
            // Ensure download has a type
            if (!isset($download['type'])) {
                // Determine content type from category
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
            }
            
            // Process based on content type
            if ($download['type'] === 'tv') {
                $this->processTvShow($download);
            } elseif ($download['type'] === 'movie') {
                $this->processMovie($download);
            } else {
                $this->processedDownloads[] = $download;
            }
        }
        
        // Combine and sort the processed downloads
        $this->finalizeProcessing();
    }
    
    /**
     * Process a TV show download
     * 
     * @param array $download The download item
     */
    private function processTvShow($download) {
        $name = $download['name'];
        
        // Try to match "Show.Name.S01E01" or "Show.Name.1x01"
        if (preg_match('/^(.*?)[\.|\s][sS](\d+)[eE](\d+)/', $name, $matches) || 
            preg_match('/^(.*?)[\.|\s](\d+)x(\d+)/', $name, $matches)) {
            $showName = trim(str_replace(['.', '_'], ' ', $matches[1]));
            $season = intval($matches[2]);
            $episode = intval($matches[3]);
            
            // Try to find the show in sample data
            foreach ($this->shows as $show) {
                $showTitle = strtolower(trim($show['title']));
                $matchShowName = strtolower($showName);
                
                if ($showTitle === $matchShowName || 
                    strpos($showTitle, $matchShowName) === 0 || 
                    strpos($matchShowName, $showTitle) === 0) {
                    
                    // If we haven't seen this show before, or this is a newer download
                    if (!isset($this->tvShowsByName[$showTitle]) || 
                        strtotime($download['completed']) > strtotime($this->tvShowsByName[$showTitle]['completed'])) {
                        
                        $this->tvShowsByName[$showTitle] = $download;
                        $this->tvShowsByName[$showTitle]['clean_name'] = $show['title'];
                        $this->tvShowsByName[$showTitle]['show_id'] = $show['id'];
                        $this->tvShowsByName[$showTitle]['season'] = $season;
                        $this->tvShowsByName[$showTitle]['episode'] = $episode;
                        $this->tvShowsByName[$showTitle]['image'] = isset($show['images']) ? getPosterUrl($show['images']) : '';
                        $this->tvShowsByName[$showTitle]['episodes_count'] = 1;
                    } else {
                        // Increment episode count for existing show
                        $this->tvShowsByName[$showTitle]['episodes_count']++;
                    }
                    break;
                }
            }
        }
    }
    
    /**
     * Process a movie download
     * 
     * @param array $download The download item
     */
    private function processMovie($download) {
        $name = $download['name'];
        
        // Try to clean up movie name using common patterns
        if (preg_match('/^(.*?)[\.\s]\d{4}/', $name, $matches)) {
            $movieName = trim(str_replace(['.', '_'], ' ', $matches[1]));
            
            // Try to find movie in sample data
            foreach ($this->movies as $movie) {
                $movieTitle = strtolower(trim($movie['title']));
                $matchMovieName = strtolower($movieName);
                
                if ($movieTitle === $matchMovieName || 
                    strpos($movieTitle, $matchMovieName) === 0 || 
                    strpos($matchMovieName, $movieTitle) === 0) {
                    
                    $download['clean_name'] = $movie['title'];
                    $download['movie_id'] = $movie['id'];
                    $download['image'] = isset($movie['images']) ? getPosterUrl($movie['images']) : '';
                    $download['year'] = isset($movie['year']) ? $movie['year'] : '';
                    
                    $this->moviesByName[$movieTitle] = $download;
                    break;
                }
            }
        }
        
        // If we couldn't match the movie, still add it with original name
        if (!isset($download['clean_name'])) {
            $this->processedDownloads[] = $download;
        }
    }
    
    /**
     * Finalize processing by combining and sorting downloads
     */
    private function finalizeProcessing() {
        // Combine processed downloads
        $this->processedDownloads = array_merge(
            $this->processedDownloads, 
            array_values($this->tvShowsByName), 
            array_values($this->moviesByName)
        );
        
        // Sort by completed date (newest first)
        usort($this->processedDownloads, function($a, $b) {
            return strtotime($b['completed']) - strtotime($a['completed']);
        });
    }
    
    /**
     * Get the processed downloads
     * 
     * @return array The processed downloads
     */
    public function getProcessedDownloads() {
        return $this->processedDownloads;
    }
}
