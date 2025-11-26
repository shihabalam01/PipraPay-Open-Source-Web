<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$perPage = 5;

$conn = connectDatabase();
global $db_prefix;
$table_name = $db_prefix . 'tb_transactions';

$stats_query = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN LOWER(transaction_status) = 'verified' THEN 1 ELSE 0 END) AS verified,
        SUM(CASE WHEN LOWER(transaction_status) IN ('canceled','failed') THEN 1 ELSE 0 END) AS canceled,
        SUM(CASE
            WHEN LOWER(transaction_status) = 'verified' AND transaction_amount REGEXP '^-?[0-9]+(\\.[0-9]+)?$'
            THEN CAST(transaction_amount AS DECIMAL(18,2))
            ELSE 0
        END) AS verified_total
    FROM `{$table_name}`
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total' => 0, 'pending' => 0, 'verified' => 0, 'canceled' => 0, 'verified_total' => 0];

$recent_query = "
    SELECT *
    FROM `{$table_name}`
    ORDER BY `created_at` DESC
    LIMIT $perPage
";
$recent_result = $conn->query($recent_query);
$recent_transactions = [];
if ($recent_result && $recent_result->num_rows > 0) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

$conn->close();

function format_status_badge($status) {
    $lower = strtolower($status);
    switch ($lower) {
        case 'verified':
            return '<span class="badge bg-primary">Verified</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'canceled':
        case 'failed':
            return '<span class="badge bg-danger">Canceled</span>';
        default:
            return '<span class="badge bg-dark h6 mb-0">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
            <h1 class="page-header-title">TopupBay Dashboard</h1>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary btn-sm" onclick="load_content('TopupBay Transactions','plugin-loader?page=modules--topupbay&view=transactions','nav-btn-topupbay-transaction')">
                <i class="bi bi-card-list me-1"></i> View Transactions
            </button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <h3 class="card-title h5 mb-1">Verified Amount</h3>
                <p class="display-6 mb-0 text-success">
                    <?= htmlspecialchars(number_format($stats['verified_total'] ?? 0, 2)) ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <h3 class="card-title h5 mb-1">Total Transactions</h3>
                <p class="display-6 mb-0"><?= htmlspecialchars($stats['total'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <h3 class="card-title h5 mb-1">Pending</h3>
                <p class="display-6 mb-0"><?= htmlspecialchars($stats['pending'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <h3 class="card-title h5 mb-1">Verified</h3>
                <p class="display-6 mb-0"><?= htmlspecialchars($stats['verified'] ?? 0) ?></p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <h3 class="card-title h5 mb-1">Canceled</h3>
                <p class="display-6 mb-0"><?= htmlspecialchars($stats['canceled'] ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="row justify-content-between align-items-center">
            <div class="col">
                <h2 class="card-title h4 mb-0">Recent Transactions</h2>
            </div>
            <div class="col-auto">
                <span class="text-muted small">Showing latest <?= count($recent_transactions) ?> records</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($recent_transactions)): ?>
            <div class="alert alert-info mb-0">
                No TopupBay transactions yet. Create one via the API to see activity here.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-borderless table-nowrap table-align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Payment ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars($txn['payment_id'] ?: '--') ?></td>
                                <td><?= htmlspecialchars($txn['customer'] ?: '--') ?></td>
                                <td><?= htmlspecialchars($txn['transaction_currency'] . ' ' . $txn['transaction_amount']) ?></td>
                                <td><?= format_status_badge($txn['transaction_status'] ?? 'pending') ?></td>
                                <td><?= htmlspecialchars($txn['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="load_content('View TopupBay Transaction','plugin-loader?page=modules--topupbay&view=view-transaction&ref=<?= htmlspecialchars($txn['id']) ?>','nav-btn-topupbay-transaction')">
                                        <i class="bi-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

