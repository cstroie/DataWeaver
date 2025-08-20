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

// Get the request data
$input = json_decode(file_get_contents('php://input'), true);

// If no input, check for GET parameters
if (!$input && !empty($_GET)) {
    $input = $_GET;
}

// Process the request
if (isset($input['function']) && $input['function'] === 'get_current_time') {
    // Return current time
    $response = [
        'result' => date('Y-m-d H:i:s')
    ];
    
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
} else if (isset($input['function']) && $input['function'] === 'get_webpage_text') {
    // Get webpage content and convert to plain text
    if (!isset($input['url'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing url parameter'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    $url = $input['url'];
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid URL provided'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/cstroie/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // Fetch webpage content
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        http_response_code(500);
        $response = [
            'error' => 'Failed to fetch webpage content'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    // Convert HTML to plain text
    $plainText = strip_tags($content);
    
    // Clean up whitespace
    $plainText = preg_replace('/\s+/', ' ', $plainText);
    $plainText = trim($plainText);
    
    $response = [
        'result' => $plainText
    ];
    
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
} else if (isset($input['function']) && $input['function'] === 'get_metar') {
    // Get METAR data for an ICAO airport
    if (!isset($input['icao'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing ICAO parameter'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    $icao = strtoupper($input['icao']);
    
    // Validate ICAO code format (4 letters)
    if (!preg_match('/^[A-Z]{4}$/', $icao)) {
        http_response_code(400);
        $response = [
            'error' => 'Invalid ICAO code. Must be 4 letters.'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
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
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    // Check if we got valid data
    if (empty(trim($metarData))) {
        http_response_code(404);
        $response = [
            'error' => 'METAR data not available for this airport'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    // Format response
    $response = [
        'result' => [
            'icao' => $icao,
            'metar' => trim($metarData)
        ]
    ];
    
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
} else if (isset($input['function']) && $input['function'] === 'get_weather') {
    // Get weather for a city
    if (!isset($input['city'])) {
        http_response_code(400);
        $response = [
            'error' => 'Missing city parameter'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
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
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
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
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    $locations = json_decode($geocodeData, true);
    
    if (empty($locations)) {
        http_response_code(404);
        $response = [
            'error' => 'City not found'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
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
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    $weather = json_decode($weatherData, true);
    
    if (!isset($weather['current'])) {
        http_response_code(500);
        $response = [
            'error' => 'Invalid weather data received'
        ];
        
        if ($isSSE) {
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        } else {
            echo json_encode($response);
        }
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
    
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
} else {
    // Return error for unknown functions
    http_response_code(400);
    $response = [
        'error' => 'Unknown function. Available functions: get_current_time, get_webpage_text, get_weather, get_metar'
    ];
    
    if ($isSSE) {
        echo "data: " . json_encode($response) . "\n\n";
        flush();
    } else {
        echo json_encode($response);
    }
}
?>
