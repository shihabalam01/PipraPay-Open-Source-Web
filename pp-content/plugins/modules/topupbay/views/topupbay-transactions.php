<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Pagination and filtering settings
$per_page = 100;
$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($current_page - 1) * $per_page;

// Get filter parameters (sanitize for display, function will handle DB escaping)
$filter_status = isset($_GET['status']) ? htmlspecialchars(trim($_GET['status']), ENT_QUOTES, 'UTF-8') : 'all';
$filter_search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8') : '';

// Get transactions for display
$transactions_data = topupbay_get_transactions_admin($per_page, $offset, $filter_status, $filter_search);
$transactions = $transactions_data['transactions'];
$total_transactions = $transactions_data['total'];
$total_pages = ceil($total_transactions / $per_page);
?>

<!-- Bulk Action Bar (hidden by default) -->
<div class="row justify-content-end mb-3 bulk-manage-tab" style="display: none">
    <div class="col-lg">
        <div class="d-sm-flex justify-content-lg-end align-items-sm-center">
            <span class="d-block d-sm-inline-block fs-5 me-3 mb-2 mb-sm-0">
                <span id="bulk-manage-tab-counter">0</span> Selected
            </span>
            <a class="btn btn-outline-danger btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-delete" href="javascript:void(0)" onclick="bulkAction('btn-bulk-action-delete', 'delete')">
                <i class="bi-trash"></i> Delete
            </a>
            <a class="btn btn-success btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-verified" href="javascript:void(0)" onclick="bulkAction('btn-bulk-action-verified', 'verified')">
                <i class="bi-check-circle"></i> Verified
            </a>
            <a class="btn btn-warning btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-pending" href="javascript:void(0)" onclick="bulkAction('btn-bulk-action-pending', 'pending')">
                <i class="bi-clock"></i> Pending
            </a>
            <a class="btn btn-danger btn-sm mb-2 mb-sm-0 me-2 btn-bulk-action-canceled" href="javascript:void(0)" onclick="bulkAction('btn-bulk-action-canceled', 'canceled')">
                <i class="bi-x-circle"></i> Canceled
            </a>
        </div>
        <span class="response-bulk-action"></span>
    </div>
</div>

<!-- Transactions Table Card -->
<div class="card">
    <div class="card-header">
        <div class="row justify-content-between align-items-center flex-grow-1">
            <div class="col-md">
                <h4 class="card-header-title">All Transactions (<?= $total_transactions ?>)</h4>
            </div>
            
            <div class="col-auto">
                <!-- Filter -->
                <div class="row align-items-sm-center">
                    <div class="col-sm-auto">
                        <div class="row align-items-center gx-0">
                            <div class="col">
                                <span class="text-secondary me-2">Status:</span>
                            </div>
                            <div class="col-auto">
                                <select class="form-select form-select-sm form-select-borderless tb-status-filter" onchange="applyFilters()">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All</option>
                                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                                    <option value="canceled" <?= $filter_status === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md">
                        <form onsubmit="applyFilters(); return false;">
                            <div class="input-group input-group-merge input-group-flush">
                                <div class="input-group-prepend input-group-text">
                                    <i class="bi-search"></i>
                                </div>
                                <input id="tbSearchInput" type="search" class="form-control" placeholder="Search" aria-label="Search" value="<?= htmlspecialchars($filter_search) ?>" onkeyup="if(event.key === 'Enter') applyFilters()" oninput="handleSearchInput(event)">
                            </div>
                        </form>
                    </div>
                </div>
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
                            <th scope="col" class="table-column-pe-0" style="width: 40px;">
                                <input type="checkbox" id="select-all-tb" class="form-check-input">
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): 
                            $view_url = 'plugin-loader?page=modules--topupbay&view=view-transaction&ref=' . $txn['id'];
                        ?>
                            <tr>
                                <td onclick="handleBulkCheckboxRowClick(event)">
                                    <input type="checkbox" class="form-check-input select-box-tb" value="<?= $txn['id'] ?>" onclick="handleBulkCheckboxClick(event)">
                                </td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;">
                                    <?php if (!empty($txn['payment_id']) && $txn['payment_id'] !== '--'): ?>
                                        <code><?= htmlspecialchars($txn['payment_id']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;"><?= htmlspecialchars($txn['customer']) ?></td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;"><?= htmlspecialchars($txn['payment_method']) ?></td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;">
                                    <?php if (!empty($txn['payment_sender_number']) && $txn['payment_sender_number'] !== '--'): ?>
                                        <code><?= htmlspecialchars($txn['payment_sender_number']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;">
                                    <?= htmlspecialchars($txn['transaction_amount']) ?> 
                                    <?= htmlspecialchars($txn['transaction_currency']) ?>
                                </td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;">
                                    <code><?= htmlspecialchars($txn['transaction_id']) ?></code>
                                </td>
                                <td onclick="event.stopPropagation();">
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
                                                   onclick="return handleTopupBayStatusClick(this)"
                                                   data-status="pending" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="warning">
                                                    <i class="bi-clock"></i> Pending
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item status-option" 
                                                   href="#"
                                                   onclick="return handleTopupBayStatusClick(this)"
                                                   data-status="verified" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="success">
                                                    <i class="bi-check-circle"></i> Verified
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item status-option" 
                                                   href="#"
                                                   onclick="return handleTopupBayStatusClick(this)"
                                                   data-status="canceled" 
                                                   data-transaction-id="<?= $txn['id'] ?>"
                                                   data-color="danger">
                                                    <i class="bi-x-circle"></i> Canceled
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;"><?= htmlspecialchars($txn['product_name']) ?></td>
                                <td onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')" style="cursor: pointer;"><?= htmlspecialchars($txn['created_at']) ?></td>
                                <td onclick="event.stopPropagation();">
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-white btn-sm" href="javascript:void(0)" onclick="load_content('View TopupBay Transaction','<?= $view_url ?>','nav-btn-topupbay-transaction')">
                                            <i class="bi-eye me-1"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php 
            // Helper function to build pagination URL with filters
            function buildPaginationUrl($page, $status, $search) {
                $params = ['page' => 'modules--topupbay', 'view' => 'transactions', 'p' => $page];
                if ($status !== 'all') $params['status'] = $status;
                if (!empty($search)) $params['search'] = $search;
                return '?' . http_build_query($params);
            }
            
            if ($total_pages > 1): ?>
            <!-- Pagination -->
            <nav aria-label="Transaction pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Previous Button -->
                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildPaginationUrl(max(1, $current_page - 1), $filter_status, $filter_search) ?>" aria-label="Previous">
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
                            <a class="page-link" href="<?= buildPaginationUrl(1, $filter_status, $filter_search) ?>">1</a>
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
                            <a class="page-link" href="<?= buildPaginationUrl($i, $filter_status, $filter_search) ?>"><?= $i ?></a>
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
                            <a class="page-link" href="<?= buildPaginationUrl($total_pages, $filter_status, $filter_search) ?>"><?= $total_pages ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildPaginationUrl(min($total_pages, $current_page + 1), $filter_status, $filter_search) ?>" aria-label="Next">
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

function handleTopupBayStatusClick(element) {
    if (!element) {
        return false;
    }

    const transactionId = element.getAttribute('data-transaction-id');
    const status = element.getAttribute('data-status');

    const dropdownElement = element.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
    if (dropdownElement) {
        const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownElement);
        if (dropdownInstance) {
            dropdownInstance.hide();
        }
    }

    updateTransactionStatus(transactionId, status);
    return false;
}

// Bulk selection functionality
(function() {
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('select-all-tb');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.select-box-tb');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionBar();
        });
    }
    
    // Individual checkbox clicks (event delegation for dynamic content)
    // Individual checkbox clicks (manual handler now)
    document.addEventListener('click', function(e) {
        const checkbox = e.target.closest('.select-box-tb');
        if (checkbox) {
            updateBulkActionBar();
            
            const selectAll = document.getElementById('select-all-tb');
            if (selectAll) {
                const allChecked = document.querySelectorAll('.select-box-tb:checked').length;
                const total = document.querySelectorAll('.select-box-tb').length;
                selectAll.checked = allChecked === total && total > 0;
                selectAll.indeterminate = allChecked > 0 && allChecked < total;
            }
        }
    });
    
    function updateBulkActionBar() {
function handleBulkCheckboxClick(event) {
    event.stopPropagation();
}

function handleBulkCheckboxRowClick(event) {
    event.stopPropagation();
    const checkbox = event.currentTarget.querySelector('.select-box-tb');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        updateBulkActionBar();
    }
}

function updateBulkActionBar() {
    const selectedCount = document.querySelectorAll('.select-box-tb:checked').length;
    const counter = document.getElementById('bulk-manage-tab-counter');
    const actionBar = document.querySelector('.bulk-manage-tab');
    
    if (counter) counter.textContent = selectedCount;
    if (actionBar) {
        actionBar.style.display = selectedCount > 0 ? 'flex' : 'none';
    }
}
})();

// Handle search input with debounce, but immediate trigger on clear
let searchTimeout = null;
function handleSearchInput(event) {
    const searchValue = event.target.value;
    
    // Clear existing timeout
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // If search is cleared (empty), trigger immediately
    if (searchValue === '') {
        applyFilters();
    } else {
        // Otherwise, debounce for 500ms
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    }
}

// Apply filters function
function applyFilters() {
    const status = document.querySelector('.tb-status-filter')?.value || 'all';
    const search = document.getElementById('tbSearchInput')?.value || '';
    
    const params = new URLSearchParams({
        page: 'modules--topupbay',
        view: 'transactions',
        p: '1'
    });
    
    if (status !== 'all') params.set('status', status);
    if (search) params.set('search', search);
    
    load_content('TopupBay Transaction', 'plugin-loader?' + params.toString(), 'nav-btn-topupbay-transaction');
}

// Bulk action function
function bulkAction(buttonClass, action) {
    const button = document.querySelector('.' + buttonClass);
    if (!button) return;
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>';
    button.disabled = true;
    
    const ids = [];
    document.querySelectorAll('.select-box-tb:checked').forEach(cb => {
        ids.push(cb.value);
    });
    
    if (ids.length === 0) {
        document.querySelector('.response-bulk-action').innerHTML = '<div class="alert alert-danger mt-2" role="alert">Please select at least one transaction.</div>';
        button.innerHTML = originalHTML;
        button.disabled = false;
        return;
    }
    
    // Confirm delete action
    if (action === 'delete') {
        if (!confirm('Are you sure you want to delete ' + ids.length + ' transaction(s)? This action cannot be undone.')) {
            button.innerHTML = originalHTML;
            button.disabled = false;
            return;
        }
    }
    
    const formData = new FormData();
    
    if (action === 'delete') {
        formData.append('topupbay-action', 'bulk-delete');
    } else {
        formData.append('topupbay-action', 'bulk-update-status');
        formData.append('status', action);
    }
    
    formData.append('transaction_ids', JSON.stringify(ids));
    formData.append('webpage', 'plugin-loader');
    
    fetch('/admin/plugin-loader?page=modules--topupbay&view=transactions', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        button.innerHTML = originalHTML;
        button.disabled = false;
        
        if (data.status === true) {
            document.querySelector('.response-bulk-action').innerHTML = '<div class="alert alert-success mt-2" role="alert">' + data.message + '</div>';
            // Reload page after 1 second
            setTimeout(() => {
                applyFilters();
            }, 1000);
        } else {
            document.querySelector('.response-bulk-action').innerHTML = '<div class="alert alert-danger mt-2" role="alert">' + (data.message || 'Failed to process bulk action') + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        document.querySelector('.response-bulk-action').innerHTML = '<div class="alert alert-danger mt-2" role="alert">An error occurred. Please try again.</div>';
    });
}
</script>

