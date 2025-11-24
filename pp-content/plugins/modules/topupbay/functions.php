<?php
if (!defined('pp_allowed_access')) {
    if (!isset($_GET['cron'])) {
        die('Direct access not allowed');
    }
}

add_action('pp_admin_initialize', 'topupbay_create_table');
add_action('pp_admin_initialize', 'topupbay_inject_menu_item');
add_action('pp_cron', 'topupbay_auto_verify_pending_transactions');

if (!function_exists('pp_cron_topupbay')) {
    function pp_cron_topupbay() {
        topupbay_auto_verify_pending_transactions();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topupbay-action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['topupbay-action'] == 'save-api-key') {
        $api_key = escape_string($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            echo json_encode(['status' => false, 'message' => 'API key is required']);
            exit();
        }
        
        $settings = pp_get_plugin_setting('topupbay');
        $settings['api_key'] = $api_key;
        
        $success = pp_set_plugin_setting('topupbay', $settings);
        
        if ($success) {
            echo json_encode(['status' => true, 'message' => 'API key saved successfully!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to save API key']);
        }
        exit();
    }
    
    if ($_POST['topupbay-action'] == 'save-settings') {
        $api_key = escape_string($_POST['api_key'] ?? '');
        $default_webhook = escape_string($_POST['default_webhook'] ?? '');
        
        if (empty($api_key)) {
            echo json_encode(['status' => false, 'message' => 'API key is required']);
            exit();
        }
        
        // Validate webhook URL if provided
        if (!empty($default_webhook) && !filter_var($default_webhook, FILTER_VALIDATE_URL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid webhook URL format']);
            exit();
        }
        
        $settings = pp_get_plugin_setting('topupbay');
        $settings['api_key'] = $api_key;
        $settings['default_webhook'] = $default_webhook;
        
        $success = pp_set_plugin_setting('topupbay', $settings);
        
        if ($success) {
            echo json_encode(['status' => true, 'message' => 'Settings saved successfully!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to save settings']);
        }
        exit();
    }
    
    if ($_POST['topupbay-action'] == 'generate-api-key') {
        $api_key = rand().uniqid().rand().rand().uniqid().rand();
        echo json_encode(['status' => true, 'api_key' => $api_key]);
        exit();
    }
    
    if ($_POST['topupbay-action'] == 'update-status') {
        $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
        $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
        
        if (empty($transaction_id) || empty($new_status)) {
            echo json_encode(['status' => false, 'message' => 'Transaction ID and status are required']);
            exit();
        }
        
        $allowed_statuses = ['pending', 'verified', 'canceled'];
        $new_status_lower = strtolower($new_status);
        if (!in_array($new_status_lower, $allowed_statuses)) {
            echo json_encode(['status' => false, 'message' => 'Invalid status value']);
            exit();
        }
        
        global $db_prefix;
        $table_name = $db_prefix . 'tb_transactions';
        
        // Get current transaction data
        $conn = connectDatabase();
        if (!$conn) {
            echo json_encode(['status' => false, 'message' => 'Database connection failed']);
            exit();
        }
        
        $current_query = "SELECT * FROM `{$table_name}` WHERE `id` = " . $transaction_id;
        $current_result = $conn->query($current_query);
        
        if (!$current_result || $current_result->num_rows === 0) {
            $conn->close();
            echo json_encode(['status' => false, 'message' => 'Transaction not found']);
            exit();
        }
        
        $current_row = $current_result->fetch_assoc();
        $previous_status = strtolower($current_row['transaction_status'] ?? '');
        
        // Use raw SQL query (same as cron job uses - which works)
        $new_status_escaped = escape_string($new_status_lower);
        $update_query = "UPDATE `{$table_name}` SET `transaction_status` = '{$new_status_escaped}' WHERE `id` = " . $transaction_id;
        $update_result = $conn->query($update_query);
        
        if ($update_result === TRUE) {
            // If manually set to verified, verify and mark SMS as used
            if ($new_status_lower === 'verified' && $previous_status !== 'verified') {
                $conn = connectDatabase();
                $verification = topupbay_verify_with_pp_transaction($current_row);
                if ($verification['verified'] === true && isset($verification['sms_data']['id'])) {
                    $sms_id = (int)$verification['sms_data']['id'];
                    $update_sms_query = "UPDATE `{$db_prefix}sms_data` SET `status` = 'used' WHERE `id` = $sms_id AND `status` = 'approved'";
                    $conn->query($update_sms_query);
                }
                $conn->close();
            }
            
            // Send webhook if status changed
            if ($previous_status !== $new_status_lower && ($new_status_lower === 'verified' || $new_status_lower === 'canceled')) {
                topupbay_send_webhook($transaction_id, $new_status_lower);
            }
            
            echo json_encode(['status' => true, 'message' => 'Status updated successfully']);
            exit();
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to update status: ' . $conn->error]);
            exit();
        }
    }
    
    // If we reach here, the action was not recognized
    echo json_encode(['status' => false, 'message' => 'Unknown action']);
    exit();
}

function topupbay_create_table() {
    $conn = connectDatabase();
    global $db_prefix;
    
    $table_name = $db_prefix . 'tb_transactions';
    
    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payment_id` VARCHAR(255) DEFAULT '--',
        `customer` VARCHAR(255) DEFAULT '--',
        `payment_method` VARCHAR(255) DEFAULT '--',
        `transaction_amount` VARCHAR(255) DEFAULT '--',
        `transaction_currency` VARCHAR(255) DEFAULT '--',
        `payment_sender_number` VARCHAR(255) DEFAULT '--',
        `transaction_id` VARCHAR(255) DEFAULT '--',
        `transaction_status` VARCHAR(255) DEFAULT '--',
        `transaction_webhook` VARCHAR(755) DEFAULT '--',
        `transaction_metadata` VARCHAR(755) DEFAULT '--',
        `product_name` VARCHAR(255) DEFAULT '--',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        // Table created successfully
    } else {
        error_log("TopupBay: Error creating table: " . $conn->error);
    }
    
    $conn->close();
}

function topupbay_inject_menu_item() {
    // Only inject on admin pages
    if (!isset($_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], '/admin') === false) {
        return;
    }
    
    // Check if TopupBay plugin is active
    global $db_prefix;
    $conn = connectDatabase();
    $response_plugin = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_slug="topupbay" AND status="active"'), true);
    $conn->close();
    
    // Only proceed if plugin is active
    if (!isset($response_plugin['status']) || $response_plugin['status'] !== true) {
        return;
    }
    
    // Inject JavaScript to add TopupBay Transaction menu item and hide default Transaction menu
    // Output before HTML starts - will be placed in body
    ?>
    <script>
    (function() {
        function injectTopupBayMenu() {
            // Find the Transaction menu item
            const transactionMenu = document.querySelector(".nav-btn-transaction");
            if (!transactionMenu || !transactionMenu.closest(".nav-item")) {
                // Retry after a short delay if menu not loaded yet
                setTimeout(injectTopupBayMenu, 100);
                return;
            }
            
            const transactionNavItem = transactionMenu.closest(".nav-item");
            
            // Hide the default Transaction menu item
            transactionNavItem.style.display = 'none';
            
            // Check if already injected
            if (document.querySelector(".nav-btn-topupbay-transaction")) {
                return;
            }
            
            // Create TopupBay Transaction menu item
            const topupbayNavItem = document.createElement("div");
            topupbayNavItem.className = "nav-item";
            topupbayNavItem.innerHTML = '<a class="nav-link nav-btn-topupbay-transaction" href="javascript:void(0);" onclick="load_content(\'TopupBay Transaction\',\'plugin-loader?page=modules--topupbay&view=transactions\',\'nav-btn-topupbay-transaction\')"><i class="bi bi-wallet nav-icon"></i><span class="nav-link-title">TopupBay Transaction</span></a>';
            
            // Insert before Transaction menu item (which is now hidden)
            transactionNavItem.parentNode.insertBefore(topupbayNavItem, transactionNavItem);
        }
        
        // Wait for DOM to be ready
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", injectTopupBayMenu);
        } else {
            // DOM already loaded, try immediately
            injectTopupBayMenu();
        }
    })();
    </script>
    <?php
}


function topupbay_check_transaction_exists($transaction_id) {
    $conn = connectDatabase();
    global $db_prefix;
    
    $transaction_id = escape_string($transaction_id);
    $table_name = $db_prefix . 'tb_transactions';
    
    $query = "SELECT * FROM `{$table_name}` WHERE `transaction_id` = '$transaction_id' LIMIT 1";
    $result = $conn->query($query);
    
    $conn->close();
    
    if ($result && $result->num_rows > 0) {
        return [
            'status' => true,
            'exists' => true,
            'message' => 'Transaction ID already exists'
        ];
    }
    
    return [
        'status' => false,
        'exists' => false,
        'message' => 'Transaction ID does not exist'
    ];
}

function topupbay_get_settings() {
    return pp_get_plugin_setting('topupbay');
}

/**
 * Get all TopupBay transactions (for admin use)
 */
function topupbay_get_transactions_admin($limit = 50, $offset = 0) {
    $conn = connectDatabase();
    global $db_prefix;
    
    $table_name = $db_prefix . 'tb_transactions';
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    $query = "SELECT * FROM `{$table_name}` ORDER BY `id` DESC LIMIT $limit OFFSET $offset";
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
            
            // Only show verification info - DO NOT auto-verify here
            // Let cron job handle verification to avoid double verification bug
            $verification = topupbay_verify_with_pp_transaction($row);
            $row['pp_verification'] = $verification;
            
            $transactions[] = $row;
        }
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM `{$table_name}`";
    $count_result = $conn->query($count_query);
    $count_row = $count_result->fetch_assoc();
    $total = $count_row['total'];
    
    $conn->close();
    
    return [
        'transactions' => $transactions,
        'total' => $total
    ];
}

function topupbay_verify_with_pp_transaction($tb_transaction) {
    $conn = connectDatabase();
    if (!$conn) {
        return [
            'verified' => false,
            'sms_data' => null,
            'match_type' => 'none',
            'message' => 'Database connection failed'
        ];
    }
    
    global $db_prefix;
    
    $verification = [
        'verified' => false,
        'sms_data' => null,
        'match_type' => 'none',
        'message' => 'Not verified'
    ];
    
    $tb_transaction_id = !empty($tb_transaction['transaction_id']) && $tb_transaction['transaction_id'] !== '--' 
        ? escape_string($tb_transaction['transaction_id']) 
        : null;
    
    $tb_payment_method = !empty($tb_transaction['payment_method']) && $tb_transaction['payment_method'] !== '--' 
        ? strtolower(trim($tb_transaction['payment_method'])) 
        : null;
    
    $tb_mobile_number = !empty($tb_transaction['payment_sender_number']) && $tb_transaction['payment_sender_number'] !== '--' 
        ? preg_replace('/\s+/', '', $tb_transaction['payment_sender_number'])
        : null;
    
    $tb_amount = safeNumber($tb_transaction['transaction_amount']);
    $tb_amount_normalized = (float)$tb_amount;
    
    if (!$tb_transaction_id || !$tb_payment_method || !$tb_mobile_number || $tb_amount <= 0) {
        $conn->close();
        $verification['message'] = 'Missing required fields: transaction_id, payment_method, mobile_number, or amount';
        return $verification;
    }
    
    $tolerance = 0;
    try {
        $settings_data = getData($db_prefix . 'settings', 'WHERE id="1"');
        if ($settings_data) {
            $global_setting_response = json_decode($settings_data, true);
            if ($global_setting_response && isset($global_setting_response['status']) && $global_setting_response['status'] == true) {
                if (!empty($global_setting_response['response'][0]['gateway_theme'])) {
                    $theme_settings = pp_get_theme_setting($global_setting_response['response'][0]['gateway_theme']);
                    $tolerance = safeNumber($theme_settings['tolerance'] ?? 0);
                }
            }
        }
    } catch (Exception $e) {
        error_log("TopupBay: Error getting tolerance settings - " . $e->getMessage());
    }
    
    $min_amount = $tb_amount_normalized;
    $max_amount = $tb_amount_normalized + $tolerance;
    
    $where_conditions = [];
    $where_conditions[] = "status='approved'";
    $where_conditions[] = "transaction_id='" . $tb_transaction_id . "'";
    $where_conditions[] = "LOWER(TRIM(payment_method))='" . escape_string($tb_payment_method) . "'";
    $where_conditions[] = "REPLACE(REPLACE(mobile_number, ' ', ''), '-', '')='" . escape_string($tb_mobile_number) . "'";
    $where_conditions[] = "CAST(amount AS DECIMAL(10,2)) >= {$min_amount} AND CAST(amount AS DECIMAL(10,2)) <= {$max_amount}";
    
    $sms_query = 'WHERE ' . implode(' AND ', $where_conditions) . ' ORDER BY id DESC LIMIT 1';
    $sms_data_raw = getData($db_prefix . 'sms_data', $sms_query);
    
    if (!$sms_data_raw) {
        $conn->close();
        return $verification;
    }
    
    $sms_data = json_decode($sms_data_raw, true);
    if (!$sms_data || !isset($sms_data['status']) || $sms_data['status'] != true || empty($sms_data['response'])) {
        $conn->close();
        return $verification;
    }
    
    $sms_record = $sms_data['response'][0];
    $verification['verified'] = true;
    $verification['sms_data'] = [
        'id' => $sms_record['id'],
        'transaction_id' => $sms_record['transaction_id'],
        'amount' => $sms_record['amount'],
        'payment_method' => $sms_record['payment_method'],
        'mobile_number' => $sms_record['mobile_number'],
        'balance' => $sms_record['balance'],
        'status' => $sms_record['status'],
        'created_at' => $sms_record['created_at']
    ];
    $verification['match_type'] = 'full';
    $verification['message'] = 'Verified - Matches SMS data';
    $conn->close();
    return $verification;
}

function topupbay_send_webhook($transaction_id, $status = null) {
    $conn = connectDatabase();
    global $db_prefix;
    
    $table_name = $db_prefix . 'tb_transactions';
    
    // Get transaction details
    $query = "SELECT * FROM `{$table_name}` WHERE `id` = " . (int)$transaction_id;
    $result = $conn->query($query);
    
    if (!$result || $result->num_rows === 0) {
        $conn->close();
        return false;
    }
    
    $transaction = $result->fetch_assoc();
    $webhook_url = $transaction['transaction_webhook'] ?? '--';
    
    // Don't send if webhook URL is not set or invalid
    if (empty($webhook_url) || $webhook_url === '--') {
        // Try to get default webhook from settings
        $settings = topupbay_get_settings();
        $default_webhook = $settings['default_webhook'] ?? '';
        
        if (empty($default_webhook) || $default_webhook === '--') {
            $conn->close();
            return false;
        }
        
        $webhook_url = $default_webhook;
    }
    
    // Validate webhook URL format
    if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        error_log("TopupBay Webhook: Invalid URL format for transaction {$transaction_id}: {$webhook_url}");
        $conn->close();
        return false;
    }
    
    // Only allow HTTP/HTTPS protocols
    $url_parts = parse_url($webhook_url);
    if (!isset($url_parts['scheme']) || !in_array(strtolower($url_parts['scheme']), ['http', 'https'])) {
        error_log("TopupBay Webhook: Invalid protocol for transaction {$transaction_id}: {$webhook_url}");
        $conn->close();
        return false;
    }
    
    // Use provided status or get from transaction
    $transaction_status = $status ?? strtolower($transaction['transaction_status'] ?? 'pending');
    
    // Only send webhook if status is verified or canceled
    if ($transaction_status !== 'verified' && $transaction_status !== 'canceled') {
        $conn->close();
        return false;
    }
    
    // Decode metadata if it's JSON
    $metadata = [];
    if (!empty($transaction['transaction_metadata']) && $transaction['transaction_metadata'] !== '--') {
        $decoded = json_decode($transaction['transaction_metadata'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $metadata = $decoded;
        }
    }
    
    // Get TopupBay API key for webhook header
    $settings = topupbay_get_settings();
    $api_key = $settings['api_key'] ?? '';
    
    // Prepare webhook payload - Only send payment_id and status
    $payload = [
        'payment_id' => $transaction['payment_id'] ?? '--',
        'status' => $transaction_status
    ];
    
    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'User-Agent: TopupBay-Webhook/1.0',
        'Accept: application/json'
    ];
    if (!empty($api_key)) {
        $headers[] = 'mh-topupbay-api-key: ' . $api_key;
    }
    
    // Send webhook
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $conn->close();
    
    if ($error) {
        error_log("TopupBay Webhook Error for transaction {$transaction_id}: {$error}");
    }
    
    return $httpCode >= 200 && $httpCode < 300;
}

function topupbay_auto_verify_pending_transactions() {
    global $db_prefix;
    
    $conn = connectDatabase();
    if (!$conn) {
        error_log("TopupBay Cron: Failed to connect to database");
        return;
    }
    
    $table_name = $db_prefix . 'tb_transactions';
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_check || $table_check->num_rows == 0) {
        error_log("TopupBay Cron: Table {$table_name} does not exist");
        $conn->close();
        return;
    }
    
    // Get all pending transactions
    $query = "SELECT * FROM `{$table_name}` 
              WHERE LOWER(`transaction_status`) = 'pending' 
              OR `transaction_status` = '--' 
              OR `transaction_status` = '' 
              ORDER BY `id` ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("TopupBay Cron: Query failed - " . $conn->error);
        $conn->close();
        return;
    }
    
    $verified_count = 0;
    $webhook_sent_count = 0;
    $total_pending = $result->num_rows;
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $txn_id = (int)$row['id'];
            
            // Skip if manually canceled
            $current_status = strtolower($row['transaction_status'] ?? 'pending');
            if ($current_status === 'canceled') {
                continue;
            }
            
            // Verify against SMS data (does NOT mark SMS as used)
            $verification = topupbay_verify_with_pp_transaction($row);
            
            if ($verification['verified'] === true && isset($verification['sms_data']['id'])) {
                // Update status to verified FIRST
                $update_query = "UPDATE `{$table_name}` SET `transaction_status` = 'verified' WHERE `id` = {$txn_id}";
                $update_result = $conn->query($update_query);
                
                if ($update_result) {
                    $verified_count++;
                    
                    // NOW mark SMS as used AFTER status is updated
                    $sms_id = (int)$verification['sms_data']['id'];
                    $update_sms_query = "UPDATE `{$db_prefix}sms_data` SET `status` = 'used' WHERE `id` = $sms_id AND `status` = 'approved'";
                    $conn->query($update_sms_query);
                    
                    // Send webhook (status changed from pending to verified)
                    if ($current_status === 'pending' || $current_status === '--' || empty($current_status)) {
                        $webhook_sent = topupbay_send_webhook($txn_id, 'verified');
                        if ($webhook_sent) {
                            $webhook_sent_count++;
                        }
                    }
                } else {
                    error_log("TopupBay Cron: Failed to update transaction ID " . $row['id'] . " - " . $conn->error);
                }
            }
        }
    }
    
    $conn->close();
    error_log("TopupBay Cron: Completed - Checked {$total_pending} pending, Verified {$verified_count}, Sent {$webhook_sent_count} webhook(s)");
}

// Direct cron execution (runs when functions.php is loaded during cron, before auto-update can exit)
// This ensures our cron runs even if other plugins exit early
if (isset($_GET['cron']) && function_exists('topupbay_auto_verify_pending_transactions')) {
    static $cron_already_run = false;
    if (!$cron_already_run) {
        $cron_already_run = true;
        topupbay_auto_verify_pending_transactions();
    }
}

function topupbay_get_all_transactions_api() {
    $conn = connectDatabase();
    global $db_prefix;
    
    $table_name = $db_prefix . 'tb_transactions';
    
    $query = "SELECT * FROM `{$table_name}` ORDER BY `id` DESC";
    $result = $conn->query($query);
    
    $transactions = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
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

function topupbay_insert_transaction_api() {
    $conn = connectDatabase();
    global $db_prefix;
    
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    
    if ($data === null && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid request data']);
        exit();
    }
    
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
    
    $payment_id = escape_string($data['payment_id'] ?? '--');
    $customer = escape_string($data['customer'] ?? '--');
    $payment_method = escape_string($data['payment_method'] ?? '--');
    $transaction_amount = escape_string($data['transaction_amount'] ?? '0');
    $transaction_currency = escape_string($data['transaction_currency'] ?? 'USD');
    $payment_sender_number = escape_string($data['payment_sender_number'] ?? '--');
    $transaction_id = escape_string($data['transaction_id'] ?? '--');
    $transaction_status = escape_string($data['transaction_status'] ?? 'pending');
    $product_name = escape_string($data['product_name'] ?? '--');
    
    $settings = topupbay_get_settings();
    $transaction_webhook = escape_string($settings['default_webhook'] ?? '--');
    
    $transaction_metadata = '--';
    if (isset($data['transaction_metadata'])) {
        if (is_array($data['transaction_metadata'])) {
            $transaction_metadata = escape_string(json_encode($data['transaction_metadata']));
        } else {
            $transaction_metadata = escape_string($data['transaction_metadata']);
        }
    }
    
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
        
        $select_query = "SELECT * FROM `{$table_name}` WHERE `id` = $insert_id";
        $result = $conn->query($select_query);
        $transaction = $result->fetch_assoc();
        
        $verification = topupbay_verify_with_pp_transaction($transaction);
        
        if ($verification['verified'] === true) {
            $update_query = "UPDATE `{$table_name}` SET `transaction_status` = 'verified' WHERE `id` = $insert_id";
            $update_result = $conn->query($update_query);
            
            if ($update_result) {
                $transaction['transaction_status'] = 'verified';
                
                if (isset($verification['sms_data']['id'])) {
                    $sms_id = (int)$verification['sms_data']['id'];
                    $update_sms_query = "UPDATE `{$db_prefix}sms_data` SET `status` = 'used' WHERE `id` = $sms_id AND `status` = 'approved'";
                    $conn->query($update_sms_query);
                }
            } else {
                $transaction['transaction_status'] = 'pending';
            }
        } else {
            $transaction['transaction_status'] = 'pending';
        }
        
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
