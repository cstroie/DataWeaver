<?php
// AI MCP (Model Communication Protocol) Server
// This script handles requests and routes them to appropriate functions

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
    
    // Fetch webpage content
    $content = @file_get_contents($url);
    
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
} else {
    // Return error for unknown functions
    http_response_code(400);
    echo json_encode([
        'error' => 'Unknown function. Available functions: get_current_time, get_webpage_text'
    ]);
}
?>
````

README.md
````markdown
<<<<<<< SEARCH
# PHP AI MCP Server

A minimal Model Communication Protocol server implemented in PHP with one function to return the current time.

## Usage

Send a POST request to the server with JSON data:

```json
{
  "function": "get_current_time"
}
```

The server will respond with:

```json
{
  "result": "2023-10-15 14:30:22"
}
```

## Example using curl

```bash
curl -X POST -H "Content-Type: application/json" -d '{"function":"get_current_time"}' http://localhost/
```

## Functions

- `get_current_time`: Returns the current server time in YYYY-MM-DD HH:MM:SS format
