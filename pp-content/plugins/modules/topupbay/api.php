<?php
/**
 * TopupBay API Endpoint
 * 
 * This file handles API requests for TopupBay transactions
 * Access: /pp-content/plugins/modules/topupbay/api.php?topupbay-api=1&api_key=YOUR_KEY
 */

// Load PipraPay core
// TopupBay API is in: pp-content/plugins/modules/topupbay/api.php
// Need to go up 4 levels to reach root where pp-config.php is located
if (file_exists(__DIR__ . '/../../../../pp-config.php')) {
    if (file_exists(__DIR__ . '/../../../../maintenance.lock')) {
        if (file_exists(__DIR__ . '/../../../../pp-include/pp-maintenance.php')) {
            include(__DIR__ . '/../../../../pp-include/pp-maintenance.php');
        } else {
            http_response_code(503);
            echo json_encode(['status' => false, 'message' => 'System is under maintenance. Please try again later.']);
            exit();
        }
        exit();
    }
    
    if (file_exists(__DIR__ . '/../../../../pp-include/pp-controller.php')) {
        require __DIR__ . '/../../../../pp-include/pp-controller.php';
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'System configuration not found']);
        exit();
    }
    
    if (file_exists(__DIR__ . '/../../../../pp-include/pp-model.php')) {
        require __DIR__ . '/../../../../pp-include/pp-model.php';
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'System configuration not found']);
    exit();
}

// Load TopupBay functions
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// Check if this is a TopupBay API request
if (!isset($_GET['topupbay-api']) || $_GET['topupbay-api'] != '1') {
    topupbay_json_response(['status' => false, 'message' => 'Not found'], 404);
}

// Set JSON header
header('Content-Type: application/json');

// Get API key from request (prefer header over URL parameter for security)
$provided_api_key = '';

// First, try to get from HTTP header (more secure)
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['mh-topupbay-api-key'])) {
        $provided_api_key = trim($headers['mh-topupbay-api-key']);
    }
}

// Fallback: check $_SERVER for header (for environments where getallheaders() doesn't work)
if (empty($provided_api_key)) {
    foreach ($_SERVER as $key => $value) {
        if (stripos($key, 'HTTP_MH_TOPUPBAY_API_KEY') !== false) {
            $provided_api_key = trim($value);
            break;
        }
    }
}

// Last fallback: check URL parameter (less secure, but for backward compatibility)
if (empty($provided_api_key)) {
    $provided_api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
}

if (empty($provided_api_key)) {
    topupbay_json_response([
        'status' => false,
        'message' => 'API key is required. Please provide it in the HTTP header: mh-topupbay-api-key'
    ], 401);
}

// Verify API key
$settings = pp_get_plugin_setting('topupbay');
$stored_api_key = $settings['api_key'] ?? '';

if (empty($stored_api_key) || $provided_api_key !== $stored_api_key) {
    topupbay_json_response(['status' => false, 'message' => 'Invalid API key'], 401);
}

// Handle GET request - Fetch all transactions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    topupbay_get_all_transactions_api();
    exit();
}

// Handle POST request - Insert transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    topupbay_insert_transaction_api();
    exit();
}

// Method not allowed
topupbay_json_response(['status' => false, 'message' => 'Method not allowed'], 405);
