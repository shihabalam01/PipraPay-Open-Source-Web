<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Get initial transactions (100 per page, page 1)
// Check both GET and POST for parameters (POST is used by load_content)
$page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
$search = isset($_GET['search']) ? trim($_GET['search']) : (isset($_POST['search']) ? trim($_POST['search']) : '');
$limit = 100;
$offset = ($page - 1) * $limit;

// Debug: Check database connection and table
global $db_prefix;
$conn = connectDatabase();
if ($conn) {
    $table_name = $db_prefix . 'tb_transactions';
    $test_query = "SELECT COUNT(*) as cnt FROM `{$table_name}`";
    $test_result = $conn->query($test_query);
    if ($test_result) {
        $test_row = $test_result->fetch_assoc();
        error_log("TopupBay Debug: Table {$table_name} exists, has " . $test_row['cnt'] . " records");
    } else {
        error_log("TopupBay Debug: Table query failed - " . $conn->error);
    }
    $conn->close();
}

try {
    error_log("TopupBay View: Calling topupbay_get_transactions_admin with limit=$limit, offset=$offset, search='$search'");
    $transactions_data = topupbay_get_transactions_admin($limit, $offset, $search);
    $transactions = $transactions_data['transactions'] ?? [];
    $total_transactions = $transactions_data['total'] ?? 0;
    $total_pages = $total_transactions > 0 ? ceil($total_transactions / $limit) : 1;
    error_log("TopupBay View: Got " . count($transactions) . " transactions, total: $total_transactions");
} catch (Exception $e) {
    $transactions = [];
    $total_transactions = 0;
    $total_pages = 1;
    error_log("TopupBay Transactions Error: " . $e->getMessage());
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
            <h1 class="page-header-title">TopupBay Transactions</h1>
            <p class="page-header-text">Manage and monitor all TopupBay transactions</p>
        </div>
    </div>
</div>

<!-- Search and Bulk Actions Bar -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="searchInput" 
                           placeholder="Search by Payment ID or Transaction ID..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="button" id="searchBtn">
                        <i class="bi-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                    <button class="btn btn-secondary" type="button" id="clearSearchBtn">
                        <i class="bi-x"></i> Clear
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div id="bulkActions" style="display: none;">
                    <span id="selectedCount" class="badge bg-primary me-2">0 selected</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success" id="bulkVerifyBtn">
                            <i class="bi-check-circle"></i> Verify
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" id="bulkPendingBtn">
                            <i class="bi-clock"></i> Pending
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="bulkCancelBtn">
                            <i class="bi-x-circle"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">
                            <i class="bi-trash"></i> Delete
                        </button>
                    </div>
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
                <h4 class="card-header-title">
                    All Transactions 
                    <span class="badge bg-secondary"><?= $total_transactions ?></span>
                    <?php if (!empty($search)): ?>
                        <span class="text-muted">(Filtered)</span>
                    <?php endif; ?>
                </h4>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <?php 
        // Debug output (remove after fixing)
        global $db_prefix;
        $debug_conn = connectDatabase();
        if ($debug_conn) {
            $debug_table = $db_prefix . 'tb_transactions';
            $debug_query = "SELECT COUNT(*) as cnt FROM `{$debug_table}`";
            $debug_result = $debug_conn->query($debug_query);
            if ($debug_result) {
                $debug_row = $debug_result->fetch_assoc();
                echo "<!-- DEBUG: Table {$debug_table} has {$debug_row['cnt']} records. Function returned " . count($transactions) . " transactions, total: {$total_transactions} -->";
            }
            $debug_conn->close();
        }
        ?>
        <?php if (empty($transactions)): ?>
            <div class="alert alert-info">
                <i class="bi-info-circle"></i> No transactions found.
                <?php if ($total_transactions > 0): ?>
                    <br><small>Note: Database shows <?= $total_transactions ?> transactions, but query returned 0. Check error logs.</small>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" title="Select All">
                            </th>
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
                    <tbody id="transactionsTableBody">
                        <?php foreach ($transactions as $txn): ?>
                            <tr data-transaction-id="<?= $txn['id'] ?>">
                                <td>
                                    <input type="checkbox" 
                                           class="transaction-checkbox" 
                                           value="<?= $txn['id'] ?>"
                                           data-transaction-id="<?= $txn['id'] ?>">
                                </td>
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
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="#" data-page="<?= $page - 1 ?>">
                                <i class="bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item active">
                            <span class="page-link">
                                Page <?= $page ?> of <?= $total_pages ?>
                            </span>
                        </li>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="#" data-page="<?= $page + 1 ?>">
                                Next <i class="bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let currentPage = <?= $page ?>;
let currentSearch = '<?= htmlspecialchars($search, ENT_QUOTES) ?>';

// Search functionality
document.getElementById('searchBtn')?.addEventListener('click', function() {
    const search = document.getElementById('searchInput').value.trim();
    loadTransactions(1, search);
});

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const search = this.value.trim();
        loadTransactions(1, search);
    }
});

document.getElementById('clearSearchBtn')?.addEventListener('click', function() {
    document.getElementById('searchInput').value = '';
    loadTransactions(1, '');
});

// Pagination
document.querySelectorAll('.pagination .page-link[data-page]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const page = parseInt(this.getAttribute('data-page'));
        loadTransactions(page, currentSearch);
    });
});

// Select All checkbox
document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.transaction-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

// Individual checkboxes
document.querySelectorAll('.transaction-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const selected = document.querySelectorAll('.transaction-checkbox:checked');
    const count = selected.length;
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (count > 0) {
        bulkActions.style.display = 'block';
        selectedCount.textContent = count + ' selected';
    } else {
        bulkActions.style.display = 'none';
    }
}

// Bulk operations
document.getElementById('bulkVerifyBtn')?.addEventListener('click', () => bulkUpdateStatus('verified'));
document.getElementById('bulkPendingBtn')?.addEventListener('click', () => bulkUpdateStatus('pending'));
document.getElementById('bulkCancelBtn')?.addEventListener('click', () => bulkUpdateStatus('canceled'));
document.getElementById('bulkDeleteBtn')?.addEventListener('click', bulkDelete);

function bulkUpdateStatus(status) {
    const selected = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
        .map(cb => parseInt(cb.value));
    
    if (selected.length === 0) {
        alert('Please select at least one transaction');
        return;
    }
    
    if (!confirm(`Are you sure you want to update ${selected.length} transaction(s) to ${status}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('topupbay-action', 'bulk-update-status');
    formData.append('transaction_ids', JSON.stringify(selected));
    formData.append('status', status);
    formData.append('webpage', 'plugin-loader');
    
    fetch('/admin/plugin-loader?page=modules--topupbay&view=transactions', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true) {
            alert(data.message);
            loadTransactions(currentPage, currentSearch);
        } else {
            alert('Error: ' + (data.message || 'Failed to update transactions'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating transactions');
    });
}

function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
        .map(cb => parseInt(cb.value));
    
    if (selected.length === 0) {
        alert('Please select at least one transaction');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${selected.length} transaction(s)? This action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('topupbay-action', 'bulk-delete');
    formData.append('transaction_ids', JSON.stringify(selected));
    formData.append('webpage', 'plugin-loader');
    
    fetch('/admin/plugin-loader?page=modules--topupbay&view=transactions', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true) {
            alert(data.message);
            loadTransactions(currentPage, currentSearch);
        } else {
            alert('Error: ' + (data.message || 'Failed to delete transactions'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting transactions');
    });
}

function loadTransactions(page, search) {
    currentPage = page;
    currentSearch = search;
    
    const formData = new FormData();
    formData.append('topupbay-action', 'get-transactions');
    formData.append('page', page);
    formData.append('search', search);
    formData.append('webpage', 'plugin-loader');
    
    // Show loading
    document.querySelector('.card-body').innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"></div><p class="mt-2">Loading...</p></div>';
    
    fetch('/admin/plugin-loader?page=modules--topupbay&view=transactions', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true) {
            // Reload page with new parameters
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        } else {
            alert('Error loading transactions');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading transactions');
    });
}

// Update transaction status (single)
function updateTransactionStatus(transactionId, status) {
    const formData = new FormData();
    formData.append('topupbay-action', 'update-status');
    formData.append('transaction_id', transactionId);
    formData.append('status', status);
    formData.append('webpage', 'plugin-loader');
    
    fetch('/admin/plugin-loader?page=modules--topupbay&view=transactions', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === true) {
            const badge = document.querySelector(`.status-badge-${transactionId}`);
            if (badge) {
                const statusColors = {
                    'pending': 'bg-warning text-dark',
                    'verified': 'bg-primary',
                    'canceled': 'bg-danger'
                };
                const statusTexts = {
                    'pending': 'Pending',
                    'verified': 'Verified',
                    'canceled': 'Canceled'
                };
                
                badge.className = `badge ${statusColors[status]} status-badge-${transactionId}`;
                badge.textContent = statusTexts[status];
                badge.setAttribute('data-status', status);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to update status'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating transaction status');
    });
}

// Add event listeners for status dropdown
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.status-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const transactionId = this.getAttribute('data-transaction-id');
            const status = this.getAttribute('data-status');
            updateTransactionStatus(transactionId, status);
        });
    });
});
</script>
