<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Set header to JSON response (unless we're handling image proxy)
if (!isset($_GET['action']) || $_GET['action'] !== 'proxy_image') {
    header('Content-Type: application/json');
}

// Load settings from the config file
$settings = loadSettings();

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Get action from GET or POST
$action = '';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Process the action if provided
if (!empty($action)) {
    
    // SABnzbd API actions
    if (strpos($action, 'sabnzbd_') === 0 || in_array($action, ['pause_queue', 'resume_queue', 'pause_item', 'resume_item', 'delete_item', 'clear_history', 'retry_item', 'delete_history_item'])) {
        // Check if SABnzbd settings are configured
        if (empty($settings['sabnzbd_url']) || empty($settings['sabnzbd_api_key'])) {
            $response = [
                'success' => false,
                'message' => 'SABnzbd API settings not configured'
            ];
        } else {
            switch ($action) {
                case 'pause_queue':
                    $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'pause');
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'Queue paused successfully' : 'Failed to pause queue'
                    ];
                    break;
                    
                case 'resume_queue':
                    $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'resume');
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'Queue resumed successfully' : 'Failed to resume queue'
                    ];
                    break;
                    
                case 'pause_item':
                    if (isset($_GET['nzo_id'])) {
                        $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'queue/pause', ['value' => $_GET['nzo_id']]);
                        $response = [
                            'success' => $result,
                            'message' => $result ? 'Item paused successfully' : 'Failed to pause item'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Missing nzo_id parameter'
                        ];
                    }
                    break;
                    
                case 'resume_item':
                    if (isset($_GET['nzo_id'])) {
                        $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'queue/resume', ['value' => $_GET['nzo_id']]);
                        $response = [
                            'success' => $result,
                            'message' => $result ? 'Item resumed successfully' : 'Failed to resume item'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Missing nzo_id parameter'
                        ];
                    }
                    break;
                    
                case 'delete_item':
                    if (isset($_GET['nzo_id'])) {
                        $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'queue/delete', ['value' => $_GET['nzo_id']]);
                        $response = [
                            'success' => $result,
                            'message' => $result ? 'Item deleted successfully' : 'Failed to delete item'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Missing nzo_id parameter'
                        ];
                    }
                    break;
                    
                case 'clear_history':
                    $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'history/clear');
                    $response = [
                        'success' => $result,
                        'message' => $result ? 'History cleared successfully' : 'Failed to clear history'
                    ];
                    break;
                    
                case 'retry_item':
                    if (isset($_GET['nzo_id'])) {
                        $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'retry', ['value' => $_GET['nzo_id']]);
                        $response = [
                            'success' => $result,
                            'message' => $result ? 'Item retried successfully' : 'Failed to retry item'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Missing nzo_id parameter'
                        ];
                    }
                    break;
                    
                case 'delete_history_item':
                    if (isset($_GET['nzo_id'])) {
                        $result = sabnzbdAction($settings['sabnzbd_url'], $settings['sabnzbd_api_key'], 'history/del', ['value' => $_GET['nzo_id']]);
                        $response = [
                            'success' => $result,
                            'message' => $result ? 'History item deleted successfully' : 'Failed to delete history item'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Missing nzo_id parameter'
                        ];
                    }
                    break;
                
                default:
                    $response = [
                        'success' => false,
                        'message' => 'Invalid SABnzbd action'
                    ];
                    break;
            }
        }
    }
    // Actions to add shows and movies
    else if ($action === 'add_show') {
        // Check if Sonarr settings are configured
        if (empty($settings['sonarr_url']) || empty($settings['sonarr_api_key'])) {
            $response = [
                'success' => false,
                'message' => 'Sonarr API settings not configured'
            ];
        } else if (!isset($_POST['tvdbId']) || empty($_POST['tvdbId'])) {
            $response = [
                'success' => false,
                'message' => 'Missing tvdbId parameter'
            ];
        } else {
            // Search for the show to get its details
            $searchResults = makeApiRequest(
                $settings['sonarr_url'], 
                'api/v3/series/lookup', 
                ['term' => 'tvdb:' . $_POST['tvdbId']], 
                $settings['sonarr_api_key']
            );
            
            if (!empty($searchResults) && isset($searchResults[0])) {
                $show = $searchResults[0];
                
                // Prepare the show data for adding to Sonarr
                $showData = [
                    'tvdbId' => $show['tvdbId'],
                    'title' => $show['title'],
                    'qualityProfileId' => 1, // Default quality profile
                    'languageProfileId' => 1, // Default language profile
                    'images' => $show['images'],
                    'seasons' => $show['seasons'],
                    'year' => $show['year'],
                    'rootFolderPath' => '/tv', // Default root folder
                    'monitored' => true,
                    'addOptions' => [
                        'searchForMissingEpisodes' => true,
                        'searchForCutoffUnmetEpisodes' => false
                    ]
                ];
                
                // Add the show to Sonarr
                $result = makeApiRequest(
                    $settings['sonarr_url'], 
                    'api/v3/series', 
                    $showData, 
                    $settings['sonarr_api_key'], 
                    'POST', 
                    true
                );
                
                if (isset($result['id'])) {
                    $response = [
                        'success' => true,
                        'message' => 'Show added successfully',
                        'show' => $result
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Failed to add show to Sonarr',
                        'error' => $result
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Failed to find show details'
                ];
            }
        }
    }
    else if ($action === 'add_movie') {
        // Check if Radarr settings are configured
        if (empty($settings['radarr_url']) || empty($settings['radarr_api_key'])) {
            $response = [
                'success' => false,
                'message' => 'Radarr API settings not configured'
            ];
        } else if (!isset($_POST['tmdbId']) || empty($_POST['tmdbId'])) {
            $response = [
                'success' => false,
                'message' => 'Missing tmdbId parameter'
            ];
        } else {
            // Search for the movie to get its details
            $searchResults = makeApiRequest(
                $settings['radarr_url'], 
                'api/v3/movie/lookup/tmdb', 
                ['tmdbId' => $_POST['tmdbId']], 
                $settings['radarr_api_key']
            );
            
            if (!empty($searchResults) && isset($searchResults['tmdbId'])) {
                $movie = $searchResults;
                
                // Prepare the movie data for adding to Radarr
                $movieData = [
                    'tmdbId' => $movie['tmdbId'],
                    'title' => $movie['title'],
                    'qualityProfileId' => 1, // Default quality profile
                    'images' => $movie['images'],
                    'year' => $movie['year'],
                    'rootFolderPath' => '/movies', // Default root folder
                    'monitored' => true,
                    'minimumAvailability' => 'released',
                    'addOptions' => [
                        'searchForMovie' => true
                    ]
                ];
                
                // Add the movie to Radarr
                $result = makeApiRequest(
                    $settings['radarr_url'], 
                    'api/v3/movie', 
                    $movieData, 
                    $settings['radarr_api_key'], 
                    'POST', 
                    true
                );
                
                if (isset($result['id'])) {
                    $response = [
                        'success' => true,
                        'message' => 'Movie added successfully',
                        'movie' => $result
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Failed to add movie to Radarr',
                        'error' => $result
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Failed to find movie details'
                ];
            }
        }
    }
    // Other Sonarr and Radarr actions
    else if (strpos($action, 'sonarr_') === 0) {
        // Check if Sonarr settings are configured
        if (empty($settings['sonarr_url']) || empty($settings['sonarr_api_key'])) {
            $response = [
                'success' => false,
                'message' => 'Sonarr API settings not configured'
            ];
        } else {
            // Handle Sonarr API actions here
            $response = [
                'success' => false,
                'message' => 'Sonarr action not implemented yet'
            ];
        }
    }
    else if (strpos($action, 'radarr_') === 0) {
        // Check if Radarr settings are configured
        if (empty($settings['radarr_url']) || empty($settings['radarr_api_key'])) {
            $response = [
                'success' => false,
                'message' => 'Radarr API settings not configured'
            ];
        } else {
            // Handle Radarr API actions here
            $response = [
                'success' => false,
                'message' => 'Radarr action not implemented yet'
            ];
        }
    }
    // Proxy image request action
    else if ($action === 'proxy_image' && isset($_GET['url'])) {
        $imageUrl = base64_decode($_GET['url']);
        
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            // Forward the request to the actual image
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($imageData)) {
                // Override JSON header and set the proper content type
                header('Content-Type: ' . $contentType);
                echo $imageData;
                exit;
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Failed to fetch image'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Invalid image URL'
            ];
        }
    }
} else {
    $response = [
        'success' => false,
        'message' => 'Missing action parameter'
    ];
}

// Return JSON response
echo json_encode($response);
