<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$plugin_slug = 'topupbay';
$settings = topupbay_get_settings();
$api_key = $settings['api_key'] ?? '';
$default_webhook = $settings['default_webhook'] ?? '';

// Get transactions for display
$transactions_data = topupbay_get_transactions_admin(50, 0);
$transactions = $transactions_data['transactions'];
$total_transactions = $transactions_data['total'];

$site_url = pp_get_site_url();
$api_base_url = $site_url . '/tb-api';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
            <h1 class="page-header-title">TopupBay Settings</h1>
        </div>
    </div>
</div>

<div class="row">
    <!-- API Settings Card -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title h4">API Configuration</h2>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-sm-12">
                        <label for="api_key" class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   name="api_key" 
                                   id="api_key" 
                                   placeholder="Enter API key" 
                                   value="<?= htmlspecialchars($api_key) ?>" 
                                   readonly>
                            <span class="input-group-text" style="cursor:pointer" onclick="copyToClipboard('api_key')">
                                <i class="bi bi-clipboard"></i>
                            </span>
                            <span class="input-group-text btn-arrow-repeat" style="cursor:pointer" onclick="generateApiKey()">
                                <i class="bi bi-arrow-repeat"></i>
                            </span>
                        </div>
                        <div class="text-secondary mt-2">Your secret API key for authentication</div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-sm-12">
                        <label for="default_webhook" class="form-label">Default Webhook URL</label>
                        <div class="input-group">
                            <input type="url" 
                                   class="form-control" 
                                   name="default_webhook" 
                                   id="default_webhook" 
                                   placeholder="https://example.com/webhook" 
                                   value="<?= htmlspecialchars($default_webhook) ?>">
                        </div>
                        <div class="text-secondary mt-2">Default webhook URL for transaction notifications (optional)</div>
                    </div>
                </div>
                
                <div id="apiKeyResponse" class="mb-3"></div>
                
                <button type="button" class="btn btn-primary" onclick="saveTopupBaySettings()">
                    <i class="bi-save"></i> Save Settings
                </button>
            </div>
        </div>
    </div>
    
    <!-- API Endpoint Info Card -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title h4">API Endpoints</h2>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Base URL</label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="apiBaseUrl" 
                               value="<?= htmlspecialchars($api_base_url) ?>" 
                               readonly>
                        <span class="input-group-text" style="cursor:pointer" onclick="copyToClipboard('apiBaseUrl')">
                            <i class="bi bi-clipboard"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">GET - All Transactions</label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="getEndpoint" 
                               value="<?= htmlspecialchars($api_base_url . '/all-transaction') ?>" 
                               readonly>
                        <span class="input-group-text" style="cursor:pointer" onclick="copyToClipboard('getEndpoint')">
                            <i class="bi bi-clipboard"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">POST - Create Transaction</label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="postEndpoint" 
                               value="<?= htmlspecialchars($api_base_url . '/create-transaction') ?>" 
                               readonly>
                        <span class="input-group-text" style="cursor:pointer" onclick="copyToClipboard('postEndpoint')">
                            <i class="bi bi-clipboard"></i>
                        </span>
                    </div>
                </div>
                
                <div class="alert alert-info mb-0">
                    <small><i class="bi-info-circle"></i> Include API key in header: <code>mh-topupbay-api-key</code></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Table Card -->
<div class="card">
    <div class="card-header">
        <div class="row justify-content-between align-items-center">
            <div class="col-md">
                <h4 class="card-header-title">Transactions (<?= $total_transactions ?>)</h4>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="alert alert-info">
                <i class="bi-info-circle"></i> No transactions found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mx-auto" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Sender Number</th>
                            <th>Amount</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Product</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($txn['payment_id']) && $txn['payment_id'] !== '--'): ?>
                                        <code><?= htmlspecialchars($txn['payment_id']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($txn['customer']) ?></td>
                                <td><?= htmlspecialchars($txn['payment_method']) ?></td>
                                <td>
                                    <?php if (!empty($txn['payment_sender_number']) && $txn['payment_sender_number'] !== '--'): ?>
                                        <code><?= htmlspecialchars($txn['payment_sender_number']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($txn['transaction_amount']) ?> 
                                    <?= htmlspecialchars($txn['transaction_currency']) ?>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($txn['transaction_id']) ?></code>
                                </td>
                                <td>
                                    <?php 
                                    $status = strtolower($txn['transaction_status'] ?? 'pending');
                                    // Map TopupBay statuses to PipraPay badge styles
                                    $badge_class = '';
                                    $badge_text = '';
                                    switch ($status) {
                                        case 'verified':
                                        case 'completed':
                                            $badge_class = 'bg-primary';
                                            $badge_text = 'Verified';
                                            break;
                                        case 'pending':
                                            $badge_class = 'bg-warning text-dark';
                                            $badge_text = 'Pending';
                                            break;
                                        case 'canceled':
                                        case 'failed':
                                            $badge_class = 'bg-danger';
                                            $badge_text = ucfirst($status);
                                            break;
                                        default:
                                            $badge_class = 'bg-dark';
                                            $badge_text = ucfirst($status);
                                    }
                                    ?>
                                    <div class="dropdown d-inline-block">
                                        <span class="badge <?= $badge_class ?> status-badge-<?= $txn['id'] ?>" 
                                              id="statusDropdown<?= $txn['id'] ?>" 
                                              data-bs-toggle="dropdown" 
                                              aria-expanded="false"
                                              role="button"
                                              tabindex="0"
                                              style="cursor: pointer;"
                                              data-status="<?= $status ?>">
                                            <?= htmlspecialchars($badge_text) ?>
                                        </span>
                                        <ul class="dropdown-menu" aria-labelledby="statusDropdown<?= $txn['id'] ?>" style="border-radius: 10px; min-width: 150px;">
                                            <li>
                                                <a class="dropdown-item status-option" 
                                                   href="#" 
                                                   data-status="pending" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="warning">
                                                    <i class="bi-clock"></i> Pending
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item status-option" 
                                                   href="#" 
                                                   data-status="verified" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="success">
                                                    <i class="bi-check-circle"></i> Verified
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item status-option" 
                                                   href="#" 
                                                   data-status="canceled" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="danger">
                                                    <i class="bi-x-circle"></i> Canceled
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($txn['product_name']) ?></td>
                                <td><?= htmlspecialchars($txn['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Copy to clipboard function (PipraPay style)
function copyToClipboard(inputId) {
    const input = document.getElementById(inputId);
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value)
        .then(() => {
            // Visual feedback could be added here
        });
}

// Generate API key and auto-save (PipraPay style)
function generateApiKey() {
    const btn = document.querySelector('.btn-arrow-repeat');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>';
    
    const formData = new FormData();
    formData.append('topupbay-action', 'generate-api-key');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalHTML;
        
        if (data.status) {
            // Update API key field
            document.getElementById('api_key').value = data.api_key;
            
            // Auto-save the API key
            saveTopupBaySettings(true);
            
            // Update API endpoint URLs
            updateApiEndpoints(data.api_key);
        } else {
            alert('Error generating API key');
        }
    })
    .catch(error => {
        btn.innerHTML = originalHTML;
        alert('An error occurred. Please try again.');
    });
}

// Save TopupBay settings
function saveTopupBaySettings(silent = false) {
    const responseDiv = document.getElementById('apiKeyResponse');
    if (!silent) {
        responseDiv.innerHTML = '<div class="alert alert-info">Saving...</div>';
    }
    
    const formData = new FormData();
    formData.append('topupbay-action', 'save-settings');
    formData.append('api_key', document.getElementById('api_key').value);
    formData.append('default_webhook', document.getElementById('default_webhook').value);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            if (!silent) {
                responseDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                setTimeout(() => {
                    responseDiv.innerHTML = '';
                }, 3000);
            }
        } else {
            responseDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            setTimeout(() => {
                responseDiv.innerHTML = '';
            }, 5000);
        }
    })
    .catch(error => {
        responseDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
        setTimeout(() => {
            responseDiv.innerHTML = '';
        }, 5000);
    });
}

// Update API endpoint URLs
function updateApiEndpoints(apiKey) {
    const baseUrl = '<?= $site_url ?>/tb-api';
    
    document.getElementById('apiBaseUrl').value = baseUrl;
    document.getElementById('getEndpoint').value = baseUrl + '/all-transaction';
    document.getElementById('postEndpoint').value = baseUrl + '/create-transaction';
}

// Handle status dropdown option click
// Use event delegation on document (works with AJAX-loaded content)
(function() {
    // Initialize dropdowns when page loads
    function initDropdowns() {
        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(element) {
            try {
                // Dispose existing instance if any
                const existing = bootstrap.Dropdown.getInstance(element);
                if (existing) {
                    existing.dispose();
                }
                new bootstrap.Dropdown(element);
            } catch (e) {
                // Ignore
            }
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDropdowns);
    } else {
        initDropdowns();
    }
    
    // Also initialize after a short delay (for AJAX-loaded content)
    setTimeout(initDropdowns, 500);
    
    // Handle status option clicks (event delegation)
    document.addEventListener('click', function(e) {
        const statusOption = e.target.closest('.status-option');
        if (statusOption) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('Status option clicked:', statusOption);
            
            const transactionId = statusOption.getAttribute('data-transaction-id');
            const newStatus = statusOption.getAttribute('data-status');
            const statusColor = statusOption.getAttribute('data-color');
            
            console.log('Transaction ID:', transactionId, 'New Status:', newStatus);
            
            if (transactionId && newStatus) {
                const dropdownElement = statusOption.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
                
                setTimeout(function() {
                    console.log('Calling updateTransactionStatus...');
                    updateTransactionStatus(transactionId, newStatus, statusColor);
                }, 100);
            } else {
                console.error('Missing transaction ID or status:', {transactionId, newStatus});
            }
        }
    });
})();

// Update transaction status
function updateTransactionStatus(transactionId, newStatus, statusColor) {
    console.log('updateTransactionStatus called with:', {transactionId, newStatus, statusColor});
    
    const badge = document.querySelector('.status-badge-' + transactionId);
    if (!badge) {
        console.error('Badge not found for transaction ID:', transactionId);
        alert('Error: Status badge not found for transaction ID: ' + transactionId);
        return;
    }
    
    const originalHTML = badge.innerHTML;
    const originalClasses = badge.className;
    
    // Show loading state
    badge.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
    badge.style.pointerEvents = 'none';
    
    // Send to standalone endpoint file (update-status.php)
    const formData = new FormData();
    formData.append('transaction_id', transactionId);
    formData.append('status', newStatus);
    
    // Use the existing handler in functions.php via plugin page
    // This works because functions.php checks for topupbay-action on POST
    const formDataForPlugin = new FormData();
    formDataForPlugin.append('webpage', 'plugin-loader');
    formDataForPlugin.append('topupbay-action', 'update-status');
    formDataForPlugin.append('transaction_id', transactionId);
    formDataForPlugin.append('status', newStatus);
    
    // Get current page parameter
    const urlParams = new URLSearchParams(window.location.search);
    const pageParam = urlParams.get('page') || 'modules--topupbay';
    const endpointUrl = window.location.origin + '/admin/plugin-loader?page=' + encodeURIComponent(pageParam);
    
    console.log('Sending request to:', endpointUrl);
    console.log('FormData:', {topupbay_action: 'update-status', transaction_id: transactionId, status: newStatus});
    
    fetch(endpointUrl, {
        method: 'POST',
        body: formDataForPlugin,
        credentials: 'same-origin' // Include cookies for authentication
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Error response:', text);
                throw new Error('Network response was not ok: ' + response.status + ' - ' + text);
            });
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Response is not JSON: ' + text);
            });
        }
    })
    .then(data => {
        if (data && data.status) {
            let badgeClass = '';
            let badgeText = '';
            
            switch (newStatus) {
                case 'verified':
                case 'completed':
                    badgeClass = 'bg-primary';
                    badgeText = 'Verified';
                    break;
                case 'pending':
                    badgeClass = 'bg-warning text-dark';
                    badgeText = 'Pending';
                    break;
                case 'canceled':
                case 'failed':
                    badgeClass = 'bg-danger';
                    badgeText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    break;
                default:
                    badgeClass = 'bg-dark';
                    badgeText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }
            
            badge.classList.remove('bg-primary', 'bg-warning', 'bg-danger', 'bg-dark', 'bg-success', 'text-dark');
            badge.className = 'badge ' + badgeClass + ' status-badge-' + transactionId;
            badge.innerHTML = badgeText;
            badge.setAttribute('data-status-color', statusColor);
            badge.setAttribute('data-status', newStatus);
            badge.setAttribute('data-bs-toggle', 'dropdown');
            badge.setAttribute('aria-expanded', 'false');
            badge.setAttribute('id', 'statusDropdown' + transactionId);
            badge.setAttribute('role', 'button');
            badge.setAttribute('tabindex', '0');
            badge.style.cursor = 'pointer';
            badge.style.pointerEvents = 'auto';
            
            const existingDropdown = bootstrap.Dropdown.getInstance(badge);
            if (existingDropdown) {
                existingDropdown.dispose();
            }
            
            try {
                new bootstrap.Dropdown(badge);
            } catch (e) {
                // Ignore
            }
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alert.style.zIndex = '9999';
            alert.innerHTML = '<i class="bi-check-circle"></i> ' + data.message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            document.body.insertBefore(alert, document.body.firstChild);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        } else {
            alert('Error: ' + (data.message || 'Failed to update status'));
            badge.innerHTML = originalHTML;
            badge.className = originalClasses;
            badge.style.pointerEvents = 'auto';
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
        badge.innerHTML = originalHTML;
        badge.className = originalClasses;
        badge.style.pointerEvents = 'auto';
    });
}
</script>


