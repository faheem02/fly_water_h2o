<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

function recalcCashbook() {
    global $conn;
    $entries = mysqli_query($conn, "SELECT id, transaction_type, amount FROM cashbook ORDER BY id ASC");
    $running = 0;
    while($e = mysqli_fetch_assoc($entries)) {
        $running = ($e['transaction_type'] == 'income') ? $running + $e['amount'] : $running - $e['amount'];
        mysqli_query($conn, "UPDATE cashbook SET balance=$running WHERE id={$e['id']}");
    }
}

function cashbook_source_label($ref_type) {
    switch($ref_type) {
        case 'manual':            return 'Manual Entry';
        case 'payment':           return 'Customer Payment';
        case 'expense':           return 'Expense';
        case 'supplier_payment':  return 'Supplier Payment';
        default:                  return 'General';
    }
}

// Handle Add Manual Cash Entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_cash_entry'])) {
    $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
    $transaction_type = $_POST['transaction_type'] == 'expense' ? 'expense' : 'income';
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = floatval($_POST['amount']);
    $datetime = date('Y-m-d H:i:s');

    if($amount <= 0) {
        $error = "Amount must be greater than zero!";
    } elseif(empty($transaction_date)) {
        $error = "Please select a date and time!";
    } else {
        $last_balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook ORDER BY id DESC LIMIT 1"))['balance'] ?? 0;
        $new_balance = ($transaction_type == 'income') ? $last_balance + $amount : $last_balance - $amount;

        $query = "INSERT INTO cashbook (transaction_date, transaction_type, reference_type, reference_id, description, amount, balance, created_datetime) 
                  VALUES ('$transaction_date', '$transaction_type', 'manual', NULL, '$description', $amount, $new_balance, '$datetime')";
        if(mysqli_query($conn, $query)) {
            recalcCashbook();
            $success = ($transaction_type == 'income') ? "Cash inflow of Rs " . number_format($amount, 2) . " recorded successfully!" : "Cash outflow of Rs " . number_format($amount, 2) . " recorded successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Edit Manual Cash Entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_cash_entry'])) {
    $id = intval($_POST['entry_id']);
    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cashbook WHERE id=$id AND reference_type='manual'"))['cnt'];
    if(!$check) {
        $error = "This entry is linked to a transaction and cannot be edited here.";
    } else {
        $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
        $transaction_type = $_POST['transaction_type'] == 'expense' ? 'expense' : 'income';
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $amount = floatval($_POST['amount']);

        if($amount <= 0) {
            $error = "Amount must be greater than zero!";
        } elseif(empty($transaction_date)) {
            $error = "Please select a date and time!";
        } else {
            mysqli_query($conn, "UPDATE cashbook SET transaction_date='$transaction_date', transaction_type='$transaction_type', description='$description', amount=$amount WHERE id=$id");
            recalcCashbook();
            $success = "Cash entry updated successfully!";
        }
    }
}

// Handle Delete Manual Cash Entry
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cashbook WHERE id=$id AND reference_type='manual'"))['cnt'];
    if($check) {
        mysqli_query($conn, "DELETE FROM cashbook WHERE id=$id");
        recalcCashbook();
        header("Location: cashbook.php?msg=deleted");
        exit();
    }
    header("Location: cashbook.php?msg=error");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success = "Cash entry deleted successfully!";
if (isset($_GET['msg']) && $_GET['msg'] == 'error') $error = "Only manual cash entries can be deleted.";

// Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$from_dt = $from_date ? $from_date . ' 00:00:00' : '';
$to_dt = $to_date ? $to_date . ' 23:59:59' : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if($from_dt && $to_dt) $where .= " AND transaction_date >= '$from_dt' AND transaction_date <= '$to_dt'";

$where_list = $where;
if($search) {
    $where_list .= " AND (cb.description LIKE '%$search%' OR cb.transaction_type LIKE '%$search%' OR cp.voucher_no LIKE '%$search%' OR sp.voucher_no LIKE '%$search%' OR ex.voucher_no LIKE '%$search%')";
}

$cashbook = mysqli_query($conn, "SELECT cb.*, 
    CASE 
        WHEN cb.reference_type='payment' THEN cp.voucher_no
        WHEN cb.reference_type='supplier_payment' THEN sp.voucher_no
        WHEN cb.reference_type='expense' THEN ex.voucher_no
        ELSE NULL
    END AS voucher_no
    FROM cashbook cb
    LEFT JOIN customer_payments cp ON cp.id = cb.reference_id AND cb.reference_type = 'payment'
    LEFT JOIN supplier_payments sp ON sp.id = cb.reference_id AND cb.reference_type = 'supplier_payment'
    LEFT JOIN expenses ex ON ex.id = cb.reference_id AND cb.reference_type = 'expense'
    $where_list ORDER BY cb.transaction_date ASC, cb.id ASC");

// Summary
$total_inflow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM cashbook $where AND transaction_type='income'"))['total'];
$total_outflow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM cashbook $where AND transaction_type='expense'"))['total'];
$opening_balance = 0;
if($from_dt) {
    $op = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook WHERE transaction_date < '$from_dt' ORDER BY transaction_date DESC, id DESC LIMIT 1"));
    $opening_balance = $op['balance'] ?? 0;
}
$cl = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook $where ORDER BY transaction_date DESC, id DESC LIMIT 1"));
$closing_balance = $cl['balance'] ?? $opening_balance;
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.cashbook-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.cashbook-table td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}
.cashbook-table tr:hover {
    background: #f8f9fa;
}
.cashbook-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
    white-space: nowrap;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-book me-2" style="color: #A04657;"></i> Cashbook
        </h2>
        <div class="d-flex gap-2 no-print">
            <button onclick="printCashbook()" class="btn btn-outline-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCashEntryModal">
                <i class="fas fa-plus me-2"></i> Add Cash Entry
            </button>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="d-flex align-items-center gap-3 bg-white rounded-4 shadow-sm p-3" style="border-left: 5px solid #6c757d;">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:#eef0f2;">
                    <i class="fas fa-door-open" style="color:#6c757d;"></i>
                </div>
                <div>
                    <div class="text-muted small">Opening Balance</div>
                    <div class="fs-5 fw-bold">Rs <?php echo number_format($opening_balance, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="d-flex align-items-center gap-3 bg-white rounded-4 shadow-sm p-3" style="border-left: 5px solid #28a745;">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:#e8f5e9;">
                    <i class="fas fa-arrow-down" style="color:#28a745;"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Inflow</div>
                    <div class="fs-5 fw-bold text-success">Rs <?php echo number_format($total_inflow, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="d-flex align-items-center gap-3 bg-white rounded-4 shadow-sm p-3" style="border-left: 5px solid #dc3545;">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:#fdecec;">
                    <i class="fas fa-arrow-up" style="color:#dc3545;"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Outflow</div>
                    <div class="fs-5 fw-bold text-danger">Rs <?php echo number_format($total_outflow, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="d-flex align-items-center gap-3 bg-white rounded-4 shadow-sm p-3" style="border-left: 5px solid #A04657;">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:#f8e3e7;">
                    <i class="fas fa-wallet" style="color:#A04657;"></i>
                </div>
                <div>
                    <div class="text-muted small">Closing Balance</div>
                    <div class="fs-5 fw-bold" style="color:#A04657;">Rs <?php echo number_format($closing_balance, 2); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar-alt me-1"></i> From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar-alt me-1"></i> To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Voucher #, description, type..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-filter me-2"></i> Apply
                    </button>
                    <a href="cashbook.php" class="btn btn-outline-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cashbook Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 rounded-4 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list-alt me-2" style="color: #A04657;"></i> Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table cashbook-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Source</th>
                            <th>Voucher #</th>
                            <th>Description</th>
                            <th class="text-end">Amount (Rs)</th>
                            <th class="text-end">Balance (Rs)</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($cashbook && mysqli_num_rows($cashbook) > 0):
                            while($cb = mysqli_fetch_assoc($cashbook)):
                                $is_manual = ($cb['reference_type'] == 'manual');
                                $source_label = cashbook_source_label($cb['reference_type']);
                            ?>
                            <tr>
                                <td class="text-nowrap"><?php echo date('d-m-Y h:i A', strtotime($cb['transaction_date'])); ?></td>
                                <td>
                                    <?php if($cb['transaction_type'] == 'income'): ?>
                                        <span class="badge bg-success rounded-pill"><i class="fas fa-arrow-down me-1"></i> Inflow</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill"><i class="fas fa-arrow-up me-1"></i> Outflow</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?php echo htmlspecialchars($source_label); ?></span></td>
                                <td>
                                    <?php if(!empty($cb['voucher_no'])): ?>
                                        <span class="badge bg-dark-subtle text-dark-emphasis rounded-pill"><?php echo htmlspecialchars($cb['voucher_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cb['description']); ?></td>
                                <td class="text-end fw-bold <?php echo $cb['transaction_type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $cb['transaction_type'] == 'income' ? '+' : '-'; ?> Rs <?php echo number_format($cb['amount'], 2); ?>
                                </td>
                                <td class="text-end fw-bold">Rs <?php echo number_format($cb['balance'], 2); ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-xs btn-outline-info viewCashBtn" title="View"
                                            data-id="<?php echo $cb['id']; ?>"
                                            data-date="<?php echo date('d-m-Y h:i A', strtotime($cb['transaction_date'])); ?>"
                                            data-type="<?php echo $cb['transaction_type']; ?>"
                                            data-source="<?php echo htmlspecialchars($source_label); ?>"
                                            data-voucher="<?php echo htmlspecialchars($cb['voucher_no'] ?? ''); ?>"
                                            data-desc="<?php echo htmlspecialchars($cb['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo number_format($cb['amount'], 2); ?>"
                                            data-balance="<?php echo number_format($cb['balance'], 2); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-dark printCashBtn" title="Print"
                                            data-id="<?php echo $cb['id']; ?>"
                                            data-date="<?php echo date('d-m-Y h:i A', strtotime($cb['transaction_date'])); ?>"
                                            data-type="<?php echo $cb['transaction_type']; ?>"
                                            data-source="<?php echo htmlspecialchars($source_label); ?>"
                                            data-voucher="<?php echo htmlspecialchars($cb['voucher_no'] ?? ''); ?>"
                                            data-desc="<?php echo htmlspecialchars($cb['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo number_format($cb['amount'], 2); ?>"
                                            data-balance="<?php echo number_format($cb['balance'], 2); ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-warning editCashBtn" title="Edit"
                                            data-id="<?php echo $cb['id']; ?>"
                                            data-manual="<?php echo $is_manual ? '1' : '0'; ?>"
                                            data-ref-type="<?php echo htmlspecialchars($cb['reference_type']); ?>"
                                            data-date="<?php echo date('Y-m-d\TH:i', strtotime($cb['transaction_date'])); ?>"
                                            data-type="<?php echo $cb['transaction_type']; ?>"
                                            data-desc="<?php echo htmlspecialchars($cb['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo $cb['amount']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-danger deleteCashBtn" title="Delete"
                                            data-id="<?php echo $cb['id']; ?>"
                                            data-manual="<?php echo $is_manual ? '1' : '0'; ?>"
                                            data-ref-type="<?php echo htmlspecialchars($cb['reference_type']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-book fa-3x mb-2 d-block opacity-25"></i>
                                    No transactions found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Cash Entry Modal -->
<div class="modal fade" id="addCashEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add Cash Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="transaction_type" class="form-select" required>
                            <option value="income">Inflow (Cash Received)</option>
                            <option value="expense">Outflow (Cash Paid)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. Payment from customer, supplier payment, invoice..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required min="0.01">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_cash_entry" class="btn btn-primary rounded-pill px-4">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cash Entry Modal -->
<div class="modal fade" id="editCashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Cash Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="entry_id" id="edit_cash_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="transaction_date" id="edit_cash_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="transaction_type" id="edit_cash_type" class="form-select" required>
                            <option value="income">Inflow (Cash Received)</option>
                            <option value="expense">Outflow (Cash Paid)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" id="edit_cash_desc" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="edit_cash_amount" class="form-control" required min="0.01">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_cash_entry" class="btn btn-primary rounded-pill px-4">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Cash Entry Modal -->
<div class="modal fade" id="viewCashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Entry Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted d-block">Type</small>
                            <strong id="view_cash_type" class="fs-5"></strong>
                        </div>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill fs-6">#<span id="view_cash_id"></span></span>
                    </div>
                    <small class="text-muted" id="view_cash_date"></small>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr><td class="text-muted">Source</td><td class="text-end fw-semibold" id="view_cash_source"></td></tr>
                        <tr><td class="text-muted">Voucher No</td><td class="text-end fw-semibold" id="view_cash_voucher"></td></tr>
                        <tr><td class="text-muted">Description</td><td class="text-end fw-semibold" id="view_cash_desc"></td></tr>
                        <tr><td class="text-muted">Amount</td><td class="text-end fw-bold" id="view_cash_amount"></td></tr>
                        <tr><td class="text-muted">Balance</td><td class="text-end fw-bold" id="view_cash_balance"></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Print Report Overlay -->
<div id="print-overlay" style="display:none;">
    <div id="print-area">
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle"><i class="fas fa-tint"></i></div>
                <div class="print-brand-text">
                    <div class="print-owner-name"><?php echo htmlspecialchars($owner_name); ?></div>
                    <div class="print-company"><?php echo htmlspecialchars($company_name); ?></div>
                    <div class="print-address"><?php echo htmlspecialchars($owner_address); ?></div>
                    <div class="print-phone"><?php echo htmlspecialchars($owner_phone); ?></div>
                </div>
            </div>
            <div class="print-divider"></div>
            <div class="print-title-row">
                <span class="print-doc-title">Cashbook Report</span>
                <span class="print-date-range"><?php echo $from_date ? date('d-m-Y', strtotime($from_date)) : 'Start'; ?> to <?php echo $to_date ? date('d-m-Y', strtotime($to_date)) : 'Today'; ?></span>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Voucher</th>
                    <th>Description</th>
                    <th class="text-end">Amount (Rs)</th>
                    <th class="text-end">Balance (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_rows = mysqli_query($conn, "SELECT cb.*, 
                    CASE 
                        WHEN cb.reference_type='payment' THEN cp.voucher_no
                        WHEN cb.reference_type='supplier_payment' THEN sp.voucher_no
                        WHEN cb.reference_type='expense' THEN ex.voucher_no
                        ELSE NULL
                    END AS voucher_no
                    FROM cashbook cb
                    LEFT JOIN customer_payments cp ON cp.id = cb.reference_id AND cb.reference_type = 'payment'
                    LEFT JOIN supplier_payments sp ON sp.id = cb.reference_id AND cb.reference_type = 'supplier_payment'
                    LEFT JOIN expenses ex ON ex.id = cb.reference_id AND cb.reference_type = 'expense'
                    $where ORDER BY cb.transaction_date ASC, cb.id ASC");
                $sno = 1;
                $print_inflow = 0;
                $print_outflow = 0;
                if($print_rows && mysqli_num_rows($print_rows) > 0):
                    while($pc = mysqli_fetch_assoc($print_rows)):
                        if($pc['transaction_type'] == 'income') { $print_inflow += $pc['amount']; } else { $print_outflow += $pc['amount']; }
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo date('d-m-Y h:i A', strtotime($pc['transaction_date'])); ?></td>
                        <td><?php echo ucfirst($pc['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars(cashbook_source_label($pc['reference_type'])); ?></td>
                        <td><?php echo !empty($pc['voucher_no']) ? htmlspecialchars($pc['voucher_no']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($pc['description']); ?></td>
                        <td class="text-end"><?php echo ($pc['transaction_type'] == 'income' ? '+' : '-') . ' ' . number_format($pc['amount'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($pc['balance'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="font-weight:700;background:#f0f0f0;">
                        <td colspan="6" class="text-end">Inflow: Rs <?php echo number_format($print_inflow, 2); ?> &nbsp;|&nbsp; Outflow: Rs <?php echo number_format($print_outflow, 2); ?></td>
                        <td class="text-end">Net: Rs <?php echo number_format($print_inflow - $print_outflow, 2); ?></td>
                        <td class="text-end">Closing: Rs <?php echo number_format($closing_balance, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center" style="padding:40px;color:#999;">No transactions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
</div>

<style>
#print-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #fff;
    z-index: 999999;
    overflow: auto;
}
#print-area {
    width: 794px;
    margin: 0 auto;
    padding: 35px 40px;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.print-header { margin-bottom: 22px; }
.print-brand-row { display: flex; align-items: center; gap: 18px; }
.print-logo-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; flex-shrink: 0; }
.print-brand-text { display: flex; flex-direction: column; gap: 2px; }
.print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-address { font-size: 13px; color: #666; }
.print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
.print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
.print-title-row { display: flex; justify-content: space-between; align-items: center; }
.print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
</style>

<script>
const ownerName = <?php echo json_encode($owner_name); ?>;
const companyName = <?php echo json_encode($company_name); ?>;
const ownerAddress = <?php echo json_encode($owner_address); ?>;
const ownerPhone = <?php echo json_encode($owner_phone); ?>;

function printCashbook() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cashbook Report</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f0f0f0; }
                @page { size: A4 landscape; margin: 8mm; }
                .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 2px solid #A04657; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .toolbar button { padding: 8px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
                .btn-print { background: #A04657; color: #fff; }
                .btn-print:hover { background: #8a3a4a; }
                .btn-close { background: #6c757d; color: #fff; margin-left: auto; }
                .btn-close:hover { background: #5a6268; }
                .report-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
                .print-header { margin-bottom: 22px; }
                .print-brand-row { display: flex; align-items: center; gap: 18px; }
                .print-logo-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; flex-shrink: 0; }
                .print-brand-text { display: flex; flex-direction: column; gap: 2px; }
                .print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: Arial, sans-serif; }
                .print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: Arial, sans-serif; }
                .print-address { font-size: 13px; color: #666; }
                .print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
                .print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
                .print-title-row { display: flex; justify-content: space-between; align-items: center; }
                .print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: Arial, sans-serif; }
                .print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
                table.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
                .print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; }
                .print-table th.text-end, .print-table td.text-end { text-align: right; }
                .print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
                .print-table tbody tr:nth-child(even) { background: #f9f9f9; }
                .print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
                .print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Cashbook Report</strong>
                <button class="btn-print" onclick="window.print()">Print</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <div class="report-wrap">
                ${content}
            </div>
        </body>
        </html>
    `);
    w.document.close();
}

function printCashEntry(id) {
    const btn = document.querySelector('.printCashBtn[data-id="' + id + '"]');
    if (!btn) return;
    const d = {
        id: btn.getAttribute('data-id'),
        date: btn.getAttribute('data-date'),
        type: btn.getAttribute('data-type'),
        source: btn.getAttribute('data-source'),
        voucher: btn.getAttribute('data-voucher'),
        desc: btn.getAttribute('data-desc'),
        amount: btn.getAttribute('data-amount'),
        balance: btn.getAttribute('data-balance')
    };
    const sign = d.type === 'income' ? '+' : '-';
    const slip = `
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle"><i class="fas fa-tint"></i></div>
                <div class="print-brand-text">
                    <div class="print-owner-name">${ownerName}</div>
                    <div class="print-company">${companyName}</div>
                    <div class="print-address">${ownerAddress}</div>
                    <div class="print-phone">${ownerPhone}</div>
                </div>
            </div>
            <div class="print-divider"></div>
        </div>
        <div class="print-title-row">
            <span class="print-doc-title">Cash ${d.type === 'income' ? 'Inflow' : 'Outflow'} Voucher</span>
            <span class="print-date-range">Voucher #: ${d.voucher || d.id}</span>
        </div>
        <div class="print-thin-divider"></div>
        <table class="print-table">
            <tbody>
                <tr><th>Date & Time</th><td>${d.date}</td></tr>
                <tr><th>Type</th><td>${d.type === 'income' ? 'Inflow (Cash Received)' : 'Outflow (Cash Paid)'}</td></tr>
                <tr><th>Source</th><td>${d.source}</td></tr>
                <tr><th>Voucher No</th><td>${d.voucher || '—'}</td></tr>
                <tr><th>Description</th><td>${d.desc}</td></tr>
                <tr><th>Amount</th><td><strong>${sign} Rs ${d.amount}</strong></td></tr>
                <tr><th>Balance After</th><td>Rs ${d.balance}</td></tr>
            </tbody>
        </table>
        <div class="print-sign-row">
            <div class="print-sign-box"><div class="print-sign-line">Prepared By</div></div>
            <div class="print-sign-box"><div class="print-sign-line">Authorized Signature</div></div>
        </div>
        <div class="print-footer">Generated on: ${new Date().toLocaleString('en-GB')}</div>`;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cash Voucher ${d.id}</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f0f0f0; }
                @page { size: A4 portrait; margin: 10mm; }
                .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 2px solid #A04657; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .toolbar button { padding: 8px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
                .btn-print { background: #A04657; color: #fff; }
                .btn-print:hover { background: #8a3a4a; }
                .btn-close { background: #6c757d; color: #fff; margin-left: auto; }
                .btn-close:hover { background: #5a6268; }
                .voucher-wrap { max-width: 720px; margin: 0 auto; padding: 30px; background: #fff; min-height: 900px; }
                .print-header { margin-bottom: 22px; }
                .print-brand-row { display: flex; align-items: center; gap: 18px; }
                .print-logo-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; flex-shrink: 0; }
                .print-brand-text { display: flex; flex-direction: column; gap: 2px; }
                .print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: Arial, sans-serif; }
                .print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: Arial, sans-serif; }
                .print-address { font-size: 13px; color: #666; }
                .print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
                .print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
                .print-title-row { display: flex; justify-content: space-between; align-items: center; }
                .print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: Arial, sans-serif; }
                .print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
                .print-thin-divider { border-top: 1px dashed #ccc; margin: 14px 0; }
                .print-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 8px; }
                .print-table th { text-align: left; color: #888; padding: 10px 12px; font-weight: 600; width: 220px; border-bottom: 1px solid #eee; }
                .print-table td { padding: 10px 12px; border-bottom: 1px solid #eee; color: #222; }
                .print-sign-row { display: flex; justify-content: space-between; gap: 40px; margin-top: 100px; }
                .print-sign-box { flex: 1; text-align: center; }
                .print-sign-line { border-top: 1px solid #999; padding-top: 8px; font-size: 12px; color: #555; }
                .print-footer { margin-top: 40px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } .voucher-wrap { box-shadow: none; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Cash Voucher ${d.id}</strong>
                <button class="btn-print" onclick="window.print()">Print</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <div class="voucher-wrap">
                ${slip}
            </div>
        </body>
        </html>
    `);
    w.document.close();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.viewCashBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('view_cash_id').textContent = this.getAttribute('data-id');
            document.getElementById('view_cash_type').textContent = this.getAttribute('data-type') === 'income' ? 'Inflow (Cash Received)' : 'Outflow (Cash Paid)';
            document.getElementById('view_cash_type').className = this.getAttribute('data-type') === 'income' ? 'text-success fs-5' : 'text-danger fs-5';
            document.getElementById('view_cash_date').textContent = 'Date: ' + this.getAttribute('data-date');
            document.getElementById('view_cash_source').textContent = this.getAttribute('data-source');
            document.getElementById('view_cash_voucher').textContent = this.getAttribute('data-voucher') || '—';
            document.getElementById('view_cash_desc').textContent = this.getAttribute('data-desc');
            document.getElementById('view_cash_amount').textContent = (this.getAttribute('data-type') === 'income' ? '+' : '-') + ' Rs ' + this.getAttribute('data-amount');
            document.getElementById('view_cash_amount').className = this.getAttribute('data-type') === 'income' ? 'text-end fw-bold text-success' : 'text-end fw-bold text-danger';
            document.getElementById('view_cash_balance').textContent = 'Rs ' + this.getAttribute('data-balance');
            var viewModal = new bootstrap.Modal(document.getElementById('viewCashModal'));
            viewModal.show();
        });
    });
    document.querySelectorAll('.printCashBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            printCashEntry(this.getAttribute('data-id'));
        });
    });
    document.querySelectorAll('.editCashBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (this.getAttribute('data-manual') !== '1') {
                cashbookLinkedMessage(this.getAttribute('data-ref-type'), 'edited');
                return;
            }
            document.getElementById('edit_cash_id').value = this.getAttribute('data-id');
            document.getElementById('edit_cash_date').value = this.getAttribute('data-date');
            document.getElementById('edit_cash_type').value = this.getAttribute('data-type');
            document.getElementById('edit_cash_desc').value = this.getAttribute('data-desc');
            document.getElementById('edit_cash_amount').value = this.getAttribute('data-amount');
            var editModal = new bootstrap.Modal(document.getElementById('editCashModal'));
            editModal.show();
        });
    });
    document.querySelectorAll('.deleteCashBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (this.getAttribute('data-manual') !== '1') {
                cashbookLinkedMessage(this.getAttribute('data-ref-type'), 'deleted');
                return;
            }
            if (confirm('Delete this cash entry?')) {
                window.location.href = 'cashbook.php?delete=' + this.getAttribute('data-id');
            }
        });
    });
    function cashbookLinkedMessage(refType, action) {
        let source = 'the source page';
        if (refType === 'payment') source = 'the Customer Payments page';
        else if (refType === 'expense') source = 'the Expenses page';
        else if (refType === 'supplier_payment') source = 'the Supplier Payments page';
        alert('This entry is linked to a transaction. Please ' + action + ' it from ' + source + '.');
    }
});

document.getElementById('addCashEntryModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('addCashEntryModal').querySelector('form').reset();
});
</script>

<?php include '../includes/footer.php'; ?>
