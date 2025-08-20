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

header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    echo json_encode([
        'result' => date('Y-m-d H:i:s')
    ]);
} else if (isset($input['function']) && $input['function'] === 'get_webpage_text') {
    // Get webpage content and convert to plain text
    if (!isset($input['url'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing url parameter'
        ]);
        exit();
    }
    
    $url = $input['url'];
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid URL provided'
        ]);
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // Fetch webpage content
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to fetch webpage content'
        ]);
        exit();
    }
    
    // Convert HTML to plain text
    $plainText = strip_tags($content);
    
    // Clean up whitespace
    $plainText = preg_replace('/\s+/', ' ', $plainText);
    $plainText = trim($plainText);
    
    echo json_encode([
        'result' => $plainText
    ]);
} else if (isset($input['function']) && $input['function'] === 'get_metar') {
    // Get METAR data for an ICAO airport
    if (!isset($input['icao'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing ICAO parameter'
        ]);
        exit();
    }
    
    $icao = strtoupper($input['icao']);
    
    // Validate ICAO code format (4 letters)
    if (!preg_match('/^[A-Z]{4}$/', $icao)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid ICAO code. Must be 4 letters.'
        ]);
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // Use aviationweather.gov API to get METAR data
    $metarUrl = "https://aviationweather.gov/api/data/metar?ids=" . $icao;
    $metarData = @file_get_contents($metarUrl, false, $context);
    
    if ($metarData === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to fetch METAR data'
        ]);
        exit();
    }
    
    // Check if we got valid data
    if (empty(trim($metarData))) {
        http_response_code(404);
        echo json_encode([
            'error' => 'METAR data not available for this airport'
        ]);
        exit();
    }
    
    // Format response
    echo json_encode([
        'result' => [
            'icao' => $icao,
            'metar' => trim($metarData)
        ]
    ]);
} else if (isset($input['function']) && $input['function'] === 'get_weather') {
    // Get weather for a city
    if (!isset($input['city'])) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing city parameter'
        ]);
        exit();
    }
    
    $city = $input['city'];
    
    // OpenWeatherMap API configuration
    $apiKey = defined('OPENWEATHER_API_KEY') ? OPENWEATHER_API_KEY : getenv('OPENWEATHER_API_KEY');
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Weather API key not configured'
        ]);
        exit();
    }
    
    // Create context with user agent
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'DataWeaver/1.0 (https://github.com/DataWeaver)',
            'timeout' => 30
        ]
    ]);
    
    // First, get coordinates using Geocoding API
    $geocodeUrl = "http://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($city) . "&limit=1&appid=" . $apiKey;
    $geocodeData = @file_get_contents($geocodeUrl, false, $context);
    
    if ($geocodeData === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to fetch geocoding data',
            'response' => $geocodeData
        ]);
        exit();
    }
    
    $locations = json_decode($geocodeData, true);
    
    if (empty($locations)) {
        http_response_code(404);
        echo json_encode([
            'error' => 'City not found'
        ]);
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
        echo json_encode([
            'error' => 'Failed to fetch weather data',
            'response' => $weatherData
        ]);
        exit();
    }
    
    $weather = json_decode($weatherData, true);
    
    if (!isset($weather['current'])) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Invalid weather data received'
        ]);
        exit();
    }
    
    // Format response
    echo json_encode([
        'result' => [
            'city' => $resolvedCity,
            'country' => $country,
            'temperature' => $weather['current']['temp'],
            'description' => $weather['current']['weather'][0]['description'],
            'humidity' => $weather['current']['humidity'],
            'pressure' => $weather['current']['pressure']
        ]
    ]);
} else {
    // Return error for unknown functions
    http_response_code(400);
    echo json_encode([
        'error' => 'Unknown function. Available functions: get_current_time, get_webpage_text, get_weather, get_metar'
    ]);
}
?>
