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
} else {
    // Return error for unknown functions
    http_response_code(400);
    echo json_encode([
        'error' => 'Unknown function. Available functions: get_current_time'
    ]);
}
?>
