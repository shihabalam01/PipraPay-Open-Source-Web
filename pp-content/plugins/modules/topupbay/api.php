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
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Not found']);
    exit();
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
http_response_code(405);
echo json_encode(['status' => false, 'message' => 'Method not allowed']);
exit();

/**
 * Get all transactions (API version)
 */
function topupbay_get_all_transactions_api() {
    $conn = connectDatabase();
    global $db_prefix;
    
    $table_name = $db_prefix . 'tb_transactions';
    
    $query = "SELECT * FROM `{$table_name}` ORDER BY `id` DESC";
    $result = $conn->query($query);
    
    $transactions = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Decode metadata if it's JSON
            if (!empty($row['transaction_metadata']) && $row['transaction_metadata'] !== '--') {
                $decoded = json_decode($row['transaction_metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['transaction_metadata'] = $decoded;
                }
            }
            $transactions[] = $row;
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'status' => true,
        'count' => count($transactions),
        'data' => $transactions
    ]);
}

/**
 * Insert new transaction (API version)
 */
function topupbay_insert_transaction_api() {
    $conn = connectDatabase();
    global $db_prefix;
    
    // Get JSON input
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    
    // If JSON decode failed, try POST data
    if ($data === null && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid request data']);
        exit();
    }
    
    // Validate required fields (customer is optional)
    $required_fields = ['transaction_amount', 'transaction_currency', 'transaction_status'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ]);
        exit();
    }
    
    // Prepare data
    $payment_id = escape_string($data['payment_id'] ?? '--');
    $customer = escape_string($data['customer'] ?? '--');
    $payment_method = escape_string($data['payment_method'] ?? '--');
    $transaction_amount = escape_string($data['transaction_amount'] ?? '0');
    $transaction_currency = escape_string($data['transaction_currency'] ?? 'USD');
    $payment_sender_number = escape_string($data['payment_sender_number'] ?? '--');
    $transaction_id = escape_string($data['transaction_id'] ?? '--');
    $transaction_status = escape_string($data['transaction_status'] ?? 'pending');
    $product_name = escape_string($data['product_name'] ?? '--');
    
    // Get webhook URL from plugin settings (not from API request)
    $settings = topupbay_get_settings();
    $transaction_webhook = escape_string($settings['default_webhook'] ?? '--');
    
    // Handle metadata (should be JSON)
    $transaction_metadata = '--';
    if (isset($data['transaction_metadata'])) {
        if (is_array($data['transaction_metadata'])) {
            $transaction_metadata = escape_string(json_encode($data['transaction_metadata']));
        } else {
            $transaction_metadata = escape_string($data['transaction_metadata']);
        }
    }
    
    // Check if transaction_id already exists (prevent duplicates)
    if ($transaction_id !== '--' && !empty($transaction_id)) {
        $check_result = topupbay_check_transaction_exists($transaction_id);
        if ($check_result['exists'] === true) {
            $conn->close();
            http_response_code(400);
            echo json_encode([
                'status' => false,
                'message' => 'Transaction ID already exists. Duplicate transaction IDs are not allowed.'
            ]);
            exit();
        }
    }
    
    $table_name = $db_prefix . 'tb_transactions';
    
    $query = "INSERT INTO `{$table_name}` (
        `payment_id`,
        `customer`,
        `payment_method`,
        `transaction_amount`,
        `transaction_currency`,
        `payment_sender_number`,
        `transaction_id`,
        `transaction_status`,
        `transaction_webhook`,
        `transaction_metadata`,
        `product_name`
    ) VALUES (
        '$payment_id',
        '$customer',
        '$payment_method',
        '$transaction_amount',
        '$transaction_currency',
        '$payment_sender_number',
        '$transaction_id',
        '$transaction_status',
        '$transaction_webhook',
        '$transaction_metadata',
        '$product_name'
    )";
    
    if ($conn->query($query) === TRUE) {
        $insert_id = $conn->insert_id;
        
        // Get the inserted record
        $select_query = "SELECT * FROM `{$table_name}` WHERE `id` = $insert_id";
        $result = $conn->query($select_query);
        $transaction = $result->fetch_assoc();
        
        // Verify against SMS data immediately
        $verification = topupbay_verify_with_pp_transaction($transaction);
        
        if ($verification['verified'] === true) {
            // Update status to verified FIRST
            $update_query = "UPDATE `{$table_name}` SET `transaction_status` = 'verified' WHERE `id` = $insert_id";
            $update_result = $conn->query($update_query);
            
            if ($update_result) {
                $transaction['transaction_status'] = 'verified';
                
                // NOW mark SMS as used AFTER status is updated
                if (isset($verification['sms_data']['id'])) {
                    $sms_id = (int)$verification['sms_data']['id'];
                    $update_sms_query = "UPDATE `{$db_prefix}sms_data` SET `status` = 'used' WHERE `id` = $sms_id AND `status` = 'approved'";
                    $conn->query($update_sms_query);
                }
            } else {
                $transaction['transaction_status'] = 'pending';
            }
        } else {
            // If not verified, keep as pending
            $transaction['transaction_status'] = 'pending';
        }
        
        // Decode metadata for response
        if (!empty($transaction['transaction_metadata']) && $transaction['transaction_metadata'] !== '--') {
            $decoded = json_decode($transaction['transaction_metadata'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $transaction['transaction_metadata'] = $decoded;
            }
        }
        
        $conn->close();
        
        http_response_code(201);
        echo json_encode([
            'status' => true,
            'message' => 'Transaction created successfully',
            'data' => $transaction
        ]);
    } else {
        $conn->close();
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Failed to create transaction. Please try again.'
        ]);
    }
}

