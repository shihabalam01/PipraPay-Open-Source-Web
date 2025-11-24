<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Pagination settings
$per_page = 100;
$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($current_page - 1) * $per_page;

// Get transactions for display
$transactions_data = topupbay_get_transactions_admin($per_page, $offset);
$transactions = $transactions_data['transactions'];
$total_transactions = $transactions_data['total'];
$total_pages = ceil($total_transactions / $per_page);
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

<!-- Transactions Table Card -->
<div class="card">
    <div class="card-header">
        <div class="row justify-content-between align-items-center">
            <div class="col-md">
                <h4 class="card-header-title">All Transactions (<?= $total_transactions ?>)</h4>
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
            
            <?php if ($total_pages > 1): ?>
            <!-- Pagination -->
            <nav aria-label="Transaction pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Previous Button -->
                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=modules--topupbay&view=transactions&p=<?= max(1, $current_page - 1) ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php
                    // Show page numbers (max 7 pages visible)
                    $start_page = max(1, $current_page - 3);
                    $end_page = min($total_pages, $current_page + 3);
                    
                    // Adjust if we're near the start
                    if ($current_page <= 4) {
                        $start_page = 1;
                        $end_page = min(7, $total_pages);
                    }
                    
                    // Adjust if we're near the end
                    if ($current_page >= $total_pages - 3) {
                        $start_page = max(1, $total_pages - 6);
                        $end_page = $total_pages;
                    }
                    
                    // First page
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=modules--topupbay&view=transactions&p=1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=modules--topupbay&view=transactions&p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Last page -->
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=modules--topupbay&view=transactions&p=<?= $total_pages ?>"><?= $total_pages ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=modules--topupbay&view=transactions&p=<?= min($total_pages, $current_page + 1) ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="text-center mt-2 text-muted">
                <small>Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_transactions) ?> of <?= $total_transactions ?> transactions</small>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Update transaction status
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
            // Update badge
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

// Add event listeners for status dropdown using event delegation
// This works with dynamically loaded content (AJAX)
(function() {
    // Use event delegation on document (works with AJAX-loaded content)
    document.addEventListener('click', function(e) {
        const statusOption = e.target.closest('.status-option');
        if (statusOption) {
            e.preventDefault();
            e.stopPropagation();
            
            const transactionId = statusOption.getAttribute('data-transaction-id');
            const status = statusOption.getAttribute('data-status');
            
            if (transactionId && status) {
                // Close dropdown
                const dropdownElement = statusOption.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
                
                updateTransactionStatus(transactionId, status);
            }
        }
    });
})();
</script>

