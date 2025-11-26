<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$plugin_slug = 'topupbay';
$settings = topupbay_get_settings();
$api_key = $settings['api_key'] ?? '';
$default_webhook = $settings['default_webhook'] ?? '';

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

<!-- API Examples Card -->
<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title h4">API Request & Response Examples</h2>
            </div>
            <div class="card-body">
                <!-- GET Request Example -->
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="bi-arrow-right-circle text-primary"></i> GET - Fetch All Transactions
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Request (cURL)</label>
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; margin: 0;"><code class="text-light">curl -X GET "<?= htmlspecialchars($api_base_url) ?>/all-transaction" \
  -H "mh-topupbay-api-key: <?= htmlspecialchars($api_key ?: 'YOUR_API_KEY') ?>"</code></pre>
                            <button class="btn btn-sm btn-white position-absolute" style="top: 0.5rem; right: 0.5rem;" onclick="copyCodeToClipboard(this)">
                                <i class="bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label fw-medium">Response (JSON)</label>
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; margin: 0; max-height: 400px; overflow-y: auto;"><code class="text-light">{
  "status": true,
  "count": 2,
  "data": [
    {
      "id": 1,
      "payment_id": "12345",
      "customer": "John Doe",
      "payment_method": "bKash",
      "transaction_amount": "100.00",
      "transaction_currency": "BDT",
      "payment_sender_number": "01712345678",
      "transaction_id": "TXN123456",
      "transaction_status": "verified",
      "transaction_webhook": "--",
      "transaction_metadata": "{}",
      "product_name": "Product Name",
      "payment_receipt": "--",
      "created_at": "2024-01-15 10:30:00"
    },
    {
      "id": 2,
      "payment_id": "12346",
      "customer": "Jane Smith",
      "payment_method": "Nagad",
      "transaction_amount": "250.00",
      "transaction_currency": "BDT",
      "payment_sender_number": "01787654321",
      "transaction_id": "TXN789012",
      "transaction_status": "pending",
      "transaction_webhook": "--",
      "transaction_metadata": "{}",
      "product_name": "--",
      "payment_receipt": "--",
      "created_at": "2024-01-15 11:00:00"
    }
  ]
}</code></pre>
                            <button class="btn btn-sm btn-white position-absolute" style="top: 0.5rem; right: 0.5rem;" onclick="copyCodeToClipboard(this)">
                                <i class="bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <!-- POST Request Example -->
                <div>
                    <h5 class="mb-3">
                        <i class="bi-arrow-right-circle text-primary"></i> POST - Create Transaction
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Request (cURL - JSON)</label>
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; margin: 0; max-height: 400px; overflow-y: auto;"><code class="text-light">curl -X POST "<?= htmlspecialchars($api_base_url) ?>/create-transaction" \
  -H "Content-Type: application/json" \
  -H "mh-topupbay-api-key: <?= htmlspecialchars($api_key ?: 'YOUR_API_KEY') ?>" \
  -d '{
    "payment_id": "12345",
    "customer": "John Doe",
    "payment_method": "bKash",
    "transaction_amount": "100.00",
    "transaction_currency": "BDT",
    "payment_sender_number": "01712345678",
    "transaction_id": "TXN123456",
    "transaction_status": "pending",
    "product_name": "Product Name",
    "payment_receipt": "https://example.com/receipt.jpg",
    "transaction_metadata": {
      "order_id": "ORD-001",
      "user_id": "123"
    }
  }'</code></pre>
                            <button class="btn btn-sm btn-white position-absolute" style="top: 0.5rem; right: 0.5rem;" onclick="copyCodeToClipboard(this)">
                                <i class="bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Request (cURL - Multipart Form Data with Image Upload)</label>
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; margin: 0; max-height: 400px; overflow-y: auto;"><code class="text-light">curl -X POST "<?= htmlspecialchars($api_base_url) ?>/create-transaction" \
  -H "mh-topupbay-api-key: <?= htmlspecialchars($api_key ?: 'YOUR_API_KEY') ?>" \
  -F "customer=John Doe" \
  -F "payment_method=bKash" \
  -F "transaction_amount=100.00" \
  -F "transaction_currency=BDT" \
  -F "payment_sender_number=01712345678" \
  -F "transaction_id=TXN123456" \
  -F "transaction_status=pending" \
  -F "product_name=Product Name" \
  -F "payment_receipt=@/path/to/receipt.jpg"</code></pre>
                            <button class="btn btn-sm btn-white position-absolute" style="top: 0.5rem; right: 0.5rem;" onclick="copyCodeToClipboard(this)">
                                <i class="bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label fw-medium">Response (JSON)</label>
                        <div class="position-relative">
                            <pre class="bg-dark text-light p-3 rounded" style="overflow-x: auto; margin: 0; max-height: 400px; overflow-y: auto;"><code class="text-light">{
  "status": true,
  "message": "Transaction created successfully",
  "data": {
    "id": 1,
    "payment_id": "12345",
    "customer": "John Doe",
    "payment_method": "bKash",
    "transaction_amount": "100.00",
    "transaction_currency": "BDT",
    "payment_sender_number": "01712345678",
    "transaction_id": "TXN123456",
    "transaction_status": "pending",
    "transaction_webhook": "--",
    "transaction_metadata": "{\"order_id\":\"ORD-001\",\"user_id\":\"123\"}",
    "product_name": "Product Name",
    "payment_receipt": "https://<?= $_SERVER['HTTP_HOST'] ?? 'example.com' ?>/pp-external/media/abc123xyz.jpg",
    "created_at": "2024-01-15 10:30:00"
  }
}</code></pre>
                            <button class="btn btn-sm btn-white position-absolute" style="top: 0.5rem; right: 0.5rem;" onclick="copyCodeToClipboard(this)">
                                <i class="bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <small>
                            <strong>Required Fields:</strong> transaction_amount, transaction_currency, transaction_status<br>
                            <strong>Optional Fields:</strong> payment_id, customer, payment_method, payment_sender_number, transaction_id, product_name, payment_receipt (file upload or URL), transaction_metadata<br>
                            <strong>Note:</strong> payment_receipt can be uploaded as an image file (multipart/form-data) or provided as a URL string (JSON)
                        </small>
                    </div>
                </div>
            </div>
        </div>
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
    document.getElementById('getEndpoint').value = 'GET ' + baseUrl + '/all-transaction';
    document.getElementById('postEndpoint').value = 'POST ' + baseUrl + '/create-transaction';
}

// Copy code block to clipboard
function copyCodeToClipboard(button) {
    const pre = button.parentElement.querySelector('pre code');
    const text = pre.textContent || pre.innerText;
    
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="bi-check"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-white');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-white');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    });
}
</script>


