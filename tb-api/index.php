<?php
if (file_exists(__DIR__."/../pp-config.php")) {
    if (file_exists(__DIR__.'/../maintenance.lock')) {
        if (file_exists(__DIR__.'/../pp-include/pp-maintenance.php')) {
            include(__DIR__."/../pp-include/pp-maintenance.php");
        } else {
            http_response_code(503);
            echo json_encode(['status' => false, 'message' => 'System is under maintenance. Please try again later.']);
            exit();
        }
        exit();
    }
    
    if (file_exists(__DIR__.'/../pp-include/pp-controller.php')) {
        require __DIR__."/../pp-include/pp-controller.php";
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'System configuration not found']);
        exit();
    }
    
    if (file_exists(__DIR__.'/../pp-include/pp-model.php')) {
        require __DIR__."/../pp-include/pp-model.php";
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'System configuration not found']);
    exit();
}

// Load TopupBay functions
if (file_exists(__DIR__ . '/../pp-content/plugins/modules/topupbay/functions.php')) {
    require_once __DIR__ . '/../pp-content/plugins/modules/topupbay/functions.php';
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'TopupBay plugin not found']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, mh-topupbay-api-key");

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get API key from HTTP header
$provided_api_key = '';

// First, try to get from HTTP header
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

if (empty($provided_api_key)) {
    http_response_code(401);
    echo json_encode([
        'status' => false, 
        'message' => 'API key is required. Please provide it in the HTTP header: mh-topupbay-api-key'
    ]);
    exit();
}

// Verify API key
$settings = pp_get_plugin_setting('topupbay');
$stored_api_key = $settings['api_key'] ?? '';

if (empty($stored_api_key) || $provided_api_key !== $stored_api_key) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid API key']);
    exit();
}

// Get endpoint from URL
$endpoint = isset($_GET['endpoint']) ? escape_string($_GET['endpoint']) : '';

// Route to appropriate handler
switch ($endpoint) {
    case 'all-transaction':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            topupbay_get_all_transactions_api();
        } else {
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Method not allowed. Use GET for this endpoint.']);
        }
        break;
        
    case 'create-transaction':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            topupbay_insert_transaction_api();
        } else {
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Method not allowed. Use POST for this endpoint.']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'status' => false, 
            'message' => 'Endpoint not found. Available endpoints: /tb-api/all-transaction (GET), /tb-api/create-transaction (POST)'
        ]);
        break;
}

