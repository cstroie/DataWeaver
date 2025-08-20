<?php
// DataWeaver - Model Communication Protocol Server
// This script handles requests and routes them to appropriate functions
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// Load configuration
if (file_exists('config.php')) {
    require_once 'config.php';
}

// Check if this is an SSE request
$isSSE = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/event-stream') !== false;

if ($isSSE) {
    // Set SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
} else {
    // Regular JSON response
    header('Content-Type: application/json');
    // Handle CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to log requests
// Logs all incoming requests to a file for later analysis
// Format: [timestamp] IP HTTP_METHOD: JSON_INPUT
function logRequest($input) {
    $logFile = '/tmp/dataweaver_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] %s %s: %s\n",
        $timestamp,
        $ip,
        $method,
        json_encode($input)
    );
    
    // Suppress errors in case the log directory isn't writable
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Helper function to send responses
function sendResponse($response, $isSSE) {
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
}

// Get the request data
$input = json_decode(file_get_contents('php://input'), true);

// If no input, check for GET parameters
if (!$input && !empty($_GET)) {
    $input = $_GET;
}

// Log the request
logRequest($input);

// Check if this is a JSON-RPC request
// JSON-RPC requests have a 'jsonrpc' field with value '2.0'
if (isset($input['jsonrpc'])) {
    // Handle JSON-RPC requests
    $method = isset($input['method']) ? $input['method'] : null;
    $params = isset($input['params']) ? $input['params'] : [];
    $id = isset($input['id']) ? $input['id'] : null;
    
    if ($method === 'initialize') {
        // Handle initialize method
        // This is the first method called by MCP clients to establish connection
        $response = [
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'sampling' => [],      // Supports sampling operations
                    'logging' => [],       // Supports logging operations
                    'roots' => [],         // Supports file system root operations
                    'prompts' => [],       // Supports prompt operations
                    'resources' => [],     // Supports resource operations
                    'tools' => []          // Supports tool operations
                ],
                'serverInfo' => [
                    'name' => 'DataWeaver MCP Server',
                    'version' => '1.0.0'
                ]
            ],
            'id' => $id
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    } else {
        // Handle unknown JSON-RPC methods
        // Return standard JSON-RPC error response
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,           // Standard code for method not found
                'message' => 'Method not found'
            ],
            'id' => $id
        ];
        
        http_response_code(400);
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
}

// Validate input is properly formatted
if ($input === null && json_last_error() !== JSON_ERROR_NONE && !isset($input['jsonrpc'])) {
    http_response_code(400);
    $response = [
        'error' => 'Invalid JSON format in request'
    ];
    sendResponse($response, $isSSE);
    exit();
}

// Ensure input is an array
if (!is_array($input)) {
    http_response_code(400);
    $response = [
        'error' => 'Request body must be a JSON object'
    ];
    sendResponse($response, $isSSE);
    exit();
}

// Extract function name
$function = isset($input['function']) ? $input['function'] : null;

// Process the request
if ($function === 'get_current_time') {
    // Validate no extra parameters for this function
    $allowedParams = ['function'];
    $extraParams = array_diff(array_keys($input), $allowedParams);
    if (!empty($extraParams)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid parameters for get_current_time function: ' . implode(', ', $extraParams)
        ];
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Return current time
    $response = [
        'result' => date('Y-m-d H:i:s')
    ];
    
    sendResponse($response, $isSSE);
} else if ($function === 'get_webpage_text') {
    // Get webpage content and convert to plain text
    // Validate required parameters
    if (!isset($input['url'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing url parameter'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Validate no extra parameters except function and url
    $allowedParams = ['function', 'url'];
    $extraParams = array_diff(array_keys($input), $allowedParams);
    if (!empty($extraParams)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid parameters for get_webpage_text function: ' . implode(', ', $extraParams)
        ];
        sendResponse($response, $isSSE);
        exit();
    }
    
    $url = $input['url'];
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid URL provided'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Create context with user agent to identify our requests
    // Set timeout to prevent hanging on slow websites
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/cstroie/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // Fetch webpage content using file_get_contents with context
    // The @ suppresses warnings which we handle with the false check
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        http_response_code(500);
        $response = [
            'error' => 'Failed to fetch webpage content'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Convert HTML to plain text by removing all HTML tags
    $plainText = strip_tags($content);
    
    // Clean up whitespace - replace multiple whitespace characters with single space
    // Then trim leading/trailing whitespace
    $plainText = preg_replace('/\s+/', ' ', $plainText);
    $plainText = trim($plainText);
    
    $response = [
        'result' => $plainText
    ];
    
    sendResponse($response, $isSSE);
} else if ($function === 'get_metar') {
    // Get METAR data for an ICAO airport
    // Validate required parameters
    if (!isset($input['icao'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing ICAO parameter'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Validate no extra parameters except function and icao
    $allowedParams = ['function', 'icao'];
    $extraParams = array_diff(array_keys($input), $allowedParams);
    if (!empty($extraParams)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid parameters for get_metar function: ' . implode(', ', $extraParams)
        ];
        sendResponse($response, $isSSE);
        exit();
    }
    
    $icao = strtoupper($input['icao']);
    
    // Validate ICAO code format (4 letters)
    if (!preg_match('/^[A-Z]{4}$/', $icao)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid ICAO code. Must be 4 letters.'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/cstroie/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // Use aviationweather.gov API to get METAR data
    $metarUrl = "https://aviationweather.gov/api/data/metar?ids=" . $icao;
    $metarData = @file_get_contents($metarUrl, false, $context);
    
    if ($metarData === false) {
        http_response_code(500);
        $response = [
            'error' => 'Failed to fetch METAR data'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Check if we got valid data
    if (empty(trim($metarData))) {
        http_response_code(404);
        $response = [
            'error' => 'METAR data not available for this airport'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Format response
    $response = [
        'result' => [
            'icao' => $icao,
            'metar' => trim($metarData)
        ]
    ];
    
    sendResponse($response, $isSSE);
} else if ($function === 'get_weather') {
    // Get weather for a city
    // Validate required parameters
    if (!isset($input['city'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing city parameter'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Validate no extra parameters except function and city
    $allowedParams = ['function', 'city'];
    $extraParams = array_diff(array_keys($input), $allowedParams);
    if (!empty($extraParams)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid parameters for get_weather function: ' . implode(', ', $extraParams)
        ];
        sendResponse($response, $isSSE);
        exit();
    }
    
    $city = $input['city'];
    
    // OpenWeatherMap API configuration
    $apiKey = defined('OPENWEATHER_API_KEY') ? OPENWEATHER_API_KEY : getenv('OPENWEATHER_API_KEY');
    if (!$apiKey) {
        http_response_code(500);
        $response = [
            'error' => 'Weather API key not configured'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/cstroie/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // First, get coordinates using Geocoding API
    $geocodeUrl = "http://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($city) . "&limit=1&appid=" . $apiKey;
    $geocodeData = @file_get_contents($geocodeUrl, false, $context);
    
    if ($geocodeData === false) {
        http_response_code(500);
        $response = [
            'error' => 'Failed to fetch geocoding data',
            'response' => $geocodeData
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    $locations = json_decode($geocodeData, true);
    
    if (empty($locations)) {
        http_response_code(404);
        $response = [
            'error' => 'City not found'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    $lat = $locations[0]['lat'];
    $lon = $locations[0]['lon'];
    $resolvedCity = $locations[0]['name'];
    $country = $locations[0]['country'];
    
    // Then, get weather data using coordinates with API version 3
    $weatherUrl = "http://api.openweathermap.org/data/3.0/onecall?lat=" . $lat . "&lon=" . $lon . "&appid=" . $apiKey . "&units=metric&exclude=minutely,hourly,daily,alerts";
    $weatherData = @file_get_contents($weatherUrl, false, $context);
    
    if ($weatherData === false) {
        http_response_code(500);
        $response = [
            'error' => 'Failed to fetch weather data',
            'response' => $weatherData
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    $weather = json_decode($weatherData, true);
    
    if (!isset($weather['current'])) {
        http_response_code(500);
        $response = [
            'error' => 'Invalid weather data received'
        ];
        
        sendResponse($response, $isSSE);
        exit();
    }
    
    // Format response
    $response = [
        'result' => [
            'city' => $resolvedCity,
            'country' => $country,
            'temperature' => $weather['current']['temp'],
            'description' => $weather['current']['weather'][0]['description'],
            'humidity' => $weather['current']['humidity'],
            'pressure' => $weather['current']['pressure']
        ]
    ];
    
    sendResponse($response, $isSSE);
} else {
    // Return error for unknown functions
    http_response_code(400);
    $response = [
        'error' => 'Unknown function. Available functions: get_current_time, get_webpage_text, get_weather, get_metar'
    ];
    
    sendResponse($response, $isSSE);
}
?>
