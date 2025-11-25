<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Check if ref parameter is set
if (!isset($_GET['ref'])) {
    echo '<script>load_content("TopupBay Transaction", "plugin-loader?page=modules--topupbay&view=transactions", "nav-btn-topupbay-transaction");</script>';
    exit();
}

$transaction_id = escape_string($_GET['ref']);
$conn = connectDatabase();
global $db_prefix;

$table_name = $db_prefix . 'tb_transactions';
$query = "SELECT * FROM `{$table_name}` WHERE `id` = '$transaction_id' LIMIT 1";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    $conn->close();
    echo '<script>load_content("TopupBay Transaction", "plugin-loader?page=modules--topupbay&view=transactions", "nav-btn-topupbay-transaction");</script>';
    exit();
}

$transaction = $result->fetch_assoc();
$conn->close();

// Decode metadata if it's JSON
if (!empty($transaction['transaction_metadata']) && $transaction['transaction_metadata'] !== '--') {
    $decoded = json_decode($transaction['transaction_metadata'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $transaction['transaction_metadata'] = $decoded;
    }
}

// Get verification info
$verification = topupbay_verify_with_pp_transaction($transaction);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
            <h1 class="page-header-title">View TopupBay Transaction</h1>
        </div>
        <!-- End Col -->
        
        <div class="col-auto">
            <div class="d-sm-flex justify-content-lg-end align-items-sm-center">
                <a class="btn btn-outline-primary btn-sm mb-2 mb-sm-0 me-2" href="javascript:void(0)" onclick="load_content('TopupBay Transaction','plugin-loader?page=modules--topupbay&view=transactions','nav-btn-topupbay-transaction')">
                    <i class="bi-arrow-left"></i> Back to Transactions
                </a>
            </div>
        </div>
    </div>
    <!-- End Row -->
</div>
<!-- End Page Header -->

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-grid gap-3 gap-lg-5">
            <!-- Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h4">Transaction Status</h2>
                </div>
                <!-- Body -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4 mb-3">
                            <label class="form-label fw-medium text-dark">Payment ID</label>
                            <div class="fw-bold text-dark">
                                <?php if (!empty($transaction['payment_id']) && $transaction['payment_id'] !== '--'): ?>
                                    <code><?= htmlspecialchars($transaction['payment_id']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">--</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-sm-4 mb-3">
                            <label class="form-label fw-medium text-dark">Date</label>
                            <div class="fw-bold text-dark">
                                <?php 
                                if (!empty($transaction['created_at']) && $transaction['created_at'] !== '--') {
                                    echo htmlspecialchars($transaction['created_at']);
                                } else {
                                    echo '<span class="text-muted">--</span>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <label class="form-label fw-medium text-dark">Status</label>
                            <div>
                                <?php
                                $status = strtolower($transaction['transaction_status'] ?? 'pending');
                                switch ($status) {
                                    case 'verified':
                                    case 'completed':
                                        echo '<span class="badge bg-primary">Verified</span>';
                                        break;
                                    case 'pending':
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                        break;
                                    case 'canceled':
                                    case 'failed':
                                        echo '<span class="badge bg-danger">' . ucfirst($status) . '</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-dark">' . ucfirst($status) . '</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Body -->
            </div>
            <!-- End Card -->

            <!-- Card -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-pills justify-content-left" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="nav-one-transaction-details-tab" data-bs-toggle="pill" href="#nav-one-transaction-details" role="tab" aria-controls="nav-one-transaction-details" aria-selected="true" style="padding: 10px;">
                                <div class="d-flex align-items-center">
                                    <i class="bi-receipt" style="margin-right: 5px;"></i> Transaction Details
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nav-two-customer-tab" data-bs-toggle="pill" href="#nav-two-customer" role="tab" aria-controls="nav-two-customer" aria-selected="false" style="padding: 10px;">
                                <div class="d-flex align-items-center">
                                    <i class="bi-people" style="margin-right: 5px;"></i> Customer
                                </div>
                            </a>
                        </li>
                        <?php if (!empty($transaction['product_name']) && $transaction['product_name'] !== '--'): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="nav-two-product-tab" data-bs-toggle="pill" href="#nav-two-product" role="tab" aria-controls="nav-two-product" aria-selected="false" style="padding: 10px;">
                                    <div class="d-flex align-items-center">
                                        <i class="bi-handbag-fill" style="margin-right: 5px;"></i> Product
                                    </div>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <!-- Body -->
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Transaction Details Tab -->
                        <div class="tab-pane fade show active" id="nav-one-transaction-details" role="tabpanel" aria-labelledby="nav-one-transaction-details-tab">
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title h4">Transaction Information</h2>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (!empty($transaction['payment_method']) && $transaction['payment_method'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Payment Method</label>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($transaction['payment_method']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($transaction['transaction_currency']) && $transaction['transaction_currency'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Payment Currency</label>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($transaction['transaction_currency']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($transaction['payment_sender_number']) && $transaction['payment_sender_number'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Sender Number</label>
                                                <div class="fw-bold text-dark"><code><?= htmlspecialchars($transaction['payment_sender_number']) ?></code></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($transaction['transaction_id']) && $transaction['transaction_id'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Transaction ID</label>
                                                <div class="fw-bold text-dark"><code><?= htmlspecialchars($transaction['transaction_id']) ?></code></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($transaction['transaction_webhook']) && $transaction['transaction_webhook'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Webhook URL</label>
                                                <div class="fw-bold text-dark">
                                                    <a href="<?= htmlspecialchars($transaction['transaction_webhook']) ?>" target="_blank" class="text-primary">
                                                        <?= htmlspecialchars($transaction['transaction_webhook']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($transaction['payment_receipt']) && $transaction['payment_receipt'] !== '--'): ?>
                                            <div class="col-sm-12 mb-3">
                                                <label class="form-label fw-medium text-dark">Payment Receipt</label>
                                                <div class="fw-bold text-dark">
                                                    <?php
                                                    $receipt = $transaction['payment_receipt'];
                                                    // Check if it's a URL (uploaded images will be URLs)
                                                    if (filter_var($receipt, FILTER_VALIDATE_URL)) {
                                                        // Check if URL points to an image
                                                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                                                        $url_parts = parse_url($receipt);
                                                        $path = $url_parts['path'] ?? '';
                                                        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                        
                                                        // If it's an image file extension, display as image
                                                        if (in_array($extension, $image_extensions) || strpos($path, '/pp-external/media/') !== false) {
                                                            echo '<div class="mt-2">';
                                                            echo '<a href="' . htmlspecialchars($receipt) . '" target="_blank" class="d-inline-block">';
                                                            echo '<img src="' . htmlspecialchars($receipt) . '" alt="Payment Receipt" class="img-thumbnail" style="max-width: 400px; max-height: 400px; cursor: pointer;">';
                                                            echo '</a>';
                                                            echo '<br><small class="text-muted mt-1 d-block">Click image to view full size</small>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<a href="' . htmlspecialchars($receipt) . '" target="_blank" class="text-primary"><i class="bi-link-45deg"></i> View Receipt</a>';
                                                        }
                                                    } else {
                                                        // Plain text
                                                        echo htmlspecialchars($receipt);
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header">
                                    <h2 class="card-title h4">Financial Details</h2>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php if (!empty($transaction['transaction_amount']) && $transaction['transaction_amount'] !== '--'): ?>
                                            <div class="col-sm-4 mb-3">
                                                <label class="form-label fw-medium text-dark">Amount</label>
                                                <div class="fw-bold text-dark">
                                                    <?php
                                                    $amount = $transaction['transaction_amount'];
                                                    $currency = !empty($transaction['transaction_currency']) && $transaction['transaction_currency'] !== '--' 
                                                        ? $transaction['transaction_currency'] 
                                                        : '';
                                                    echo htmlspecialchars($currency . ' ' . number_format((float)$amount, 2));
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Verification Info -->
                            <?php if (isset($verification) && is_array($verification)): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h2 class="card-title h4">PipraPay Verification</h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-12 mb-3">
                                                <?php if ($verification['verified']): ?>
                                                    <div class="alert alert-success">
                                                        <i class="bi-check-circle"></i> This transaction has been verified with PipraPay transaction #<?= htmlspecialchars($verification['pp_id'] ?? 'N/A') ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="bi-info-circle"></i> <?= htmlspecialchars($verification['message'] ?? 'Not verified with PipraPay transaction') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Metadata -->
                            <?php if (!empty($transaction['transaction_metadata']) && $transaction['transaction_metadata'] !== '--' && is_array($transaction['transaction_metadata'])): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h2 class="card-title h4">Metadata</h2>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($transaction['transaction_metadata'] as $key => $value): ?>
                                                <div class="col-sm-6 mb-3">
                                                    <label class="form-label fw-medium text-dark"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                                    <div class="fw-bold text-dark">
                                                        <?php
                                                        if (is_array($value)) {
                                                            echo htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT));
                                                        } else {
                                                            echo htmlspecialchars($value);
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Customer Tab -->
                        <div class="tab-pane fade" id="nav-two-customer" role="tabpanel" aria-labelledby="nav-two-customer-tab">
                            <div class="row">
                                <?php if (!empty($transaction['customer']) && $transaction['customer'] !== '--'): ?>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label fw-medium text-dark">Customer Name</label>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($transaction['customer']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Product Tab -->
                        <?php if (!empty($transaction['product_name']) && $transaction['product_name'] !== '--'): ?>
                            <div class="tab-pane fade" id="nav-two-product" role="tabpanel" aria-labelledby="nav-two-product-tab">
                                <div class="row">
                                    <div class="col-sm-12 mb-3">
                                        <label class="form-label fw-medium text-dark">Product Name</label>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($transaction['product_name']) ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- End Card -->
            
            <div id="stickyBlockEndPoint"></div>
        </div>
    </div>
</div>
<!-- End Row -->
