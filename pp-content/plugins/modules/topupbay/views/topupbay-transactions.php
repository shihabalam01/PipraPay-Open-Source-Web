<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Get transactions for display
$transactions_data = topupbay_get_transactions_admin(50, 0);
$transactions = $transactions_data['transactions'];
$total_transactions = $transactions_data['total'];
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

