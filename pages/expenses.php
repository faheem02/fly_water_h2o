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

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $category_id = intval($_POST['category_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = floatval($_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $receipt_no = mysqli_real_escape_string($conn, $_POST['receipt_no'] ?? '');
    $datetime = date('Y-m-d H:i:s');

    $cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_name FROM expense_categories WHERE id=$category_id"));
    $category_name = $cat['category_name'] ?? '';

    $query = "INSERT INTO expenses (expense_date, expense_category, description, amount, payment_method, receipt_no, created_by, created_datetime) 
              VALUES ('$expense_date', $category_id, '$description', $amount, '$payment_method', '$receipt_no', {$_SESSION['admin_id']}, '$datetime')";

    if(mysqli_query($conn, $query)) {
        $expense_id = mysqli_insert_id($conn);

        $last_balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook ORDER BY id DESC LIMIT 1"))['balance'] ?? 0;
        $new_balance = $last_balance - $amount;

        mysqli_query($conn, "INSERT INTO cashbook (transaction_date, transaction_type, reference_type, reference_id, description, amount, balance, created_datetime) 
                             VALUES ('$expense_date', 'expense', 'expense', $expense_id, 'Expense: $category_name - $description', $amount, $new_balance, '$datetime')");
        recalcCashbook();

        $success = "Expense of Rs " . number_format($amount, 2) . " recorded successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle Edit Expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_expense'])) {
    $id = intval($_POST['expense_id']);
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $category_id = intval($_POST['category_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = floatval($_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $receipt_no = mysqli_real_escape_string($conn, $_POST['receipt_no'] ?? '');
    $datetime = date('Y-m-d H:i:s');

    $cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_name FROM expense_categories WHERE id=$category_id"));
    $category_name = $cat['category_name'] ?? '';

    $sql = "UPDATE expenses SET expense_date='$expense_date', expense_category=$category_id, description='$description', amount=$amount, payment_method='$payment_method', receipt_no='$receipt_no' WHERE id=$id";
    if(mysqli_query($conn, $sql)) {
        mysqli_query($conn, "UPDATE cashbook SET transaction_date='$expense_date', description='Expense: $category_name - $description', amount=$amount WHERE reference_type='expense' AND reference_id=$id");
        recalcCashbook();
        $success = "Expense updated successfully!";
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
}

// Handle Delete Expense
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM expenses WHERE id=$id");
    mysqli_query($conn, "DELETE FROM cashbook WHERE reference_type='expense' AND reference_id=$id");
    recalcCashbook();
    header("Location: expenses.php?msg=deleted");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success = "Expense deleted successfully!";

// Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$where = "WHERE DATE(e.expense_date) BETWEEN '$from_date' AND '$to_date'";
if($category_filter > 0) $where .= " AND e.expense_category = $category_filter";

$expenses = mysqli_query($conn, "SELECT e.*, c.category_name FROM expenses e LEFT JOIN expense_categories c ON e.expense_category = c.id $where ORDER BY e.expense_date DESC, e.id DESC");
$categories = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY category_name");
$total_expenses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(e.amount),0) as total FROM expenses e $where"))['total'];
$expense_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM expenses e $where"))['cnt'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.expense-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.expense-table td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}
.expense-table tr:hover {
    background: #f8f9fa;
}
.expense-table .btn-xs {
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
            <i class="fas fa-money-bill-wave me-2" style="color: #A04657;"></i> Expense Management
        </h2>
        <div class="d-flex gap-2 no-print">
            <button onclick="printExpenses()" class="btn btn-outline-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus-circle me-2"></i> Add Expense
            </button>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Summary Card (date-filter driven) -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-3 bg-white rounded-4 shadow-sm p-4" style="border-left: 5px solid #A04657;">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#f8e3e7;">
                    <i class="fas fa-money-bill-wave fa-lg" style="color:#A04657;"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Expenses (Selected Period)</div>
                    <div class="fs-3 fw-bold" style="color:#A04657;">Rs <?php echo number_format($total_expenses, 2); ?></div>
                    <div class="text-muted small">
                        <?php echo $expense_count; ?> expense(s) &bull; <?php echo date('d-m-Y', strtotime($from_date)); ?> to <?php echo date('d-m-Y', strtotime($to_date)); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
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
                    <label class="form-label fw-semibold"><i class="fas fa-tags me-1"></i> Category</label>
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-filter me-2"></i> Apply
                    </button>
                    <a href="expenses.php" class="btn btn-outline-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 rounded-4 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list-alt me-2" style="color: #A04657;"></i> Expense History</h5>
            <a href="expense_categories.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-tags me-1"></i> Manage Categories
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table expense-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Payment Method</th>
                            <th>Receipt #</th>
                            <th class="text-end">Amount (Rs)</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($expenses && mysqli_num_rows($expenses) > 0):
                            while($e = mysqli_fetch_assoc($expenses)): ?>
                            <tr>
                                <td><?php echo date('d-m-Y', strtotime($e['expense_date'])); ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3"><?php echo htmlspecialchars($e['category_name'] ?? '—'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($e['description']); ?></td>
                                <td><?php echo htmlspecialchars($e['payment_method']); ?></td>
                                <td><?php echo $e['receipt_no'] ? htmlspecialchars($e['receipt_no']) : '—'; ?></td>
                                <td class="text-end text-danger fw-bold">Rs <?php echo number_format($e['amount'], 2); ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-xs btn-outline-info viewExpenseBtn" title="View"
                                            data-id="<?php echo $e['id']; ?>"
                                            data-date="<?php echo date('d-m-Y', strtotime($e['expense_date'])); ?>"
                                            data-category="<?php echo htmlspecialchars($e['category_name'] ?? '—'); ?>"
                                            data-description="<?php echo htmlspecialchars($e['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo number_format($e['amount'], 2); ?>"
                                            data-method="<?php echo htmlspecialchars($e['payment_method']); ?>"
                                            data-receipt="<?php echo htmlspecialchars($e['receipt_no'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-dark printExpenseBtn" title="Print"
                                            data-id="<?php echo $e['id']; ?>"
                                            data-date="<?php echo date('d-m-Y', strtotime($e['expense_date'])); ?>"
                                            data-category="<?php echo htmlspecialchars($e['category_name'] ?? '—'); ?>"
                                            data-description="<?php echo htmlspecialchars($e['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo number_format($e['amount'], 2); ?>"
                                            data-method="<?php echo htmlspecialchars($e['payment_method']); ?>"
                                            data-receipt="<?php echo htmlspecialchars($e['receipt_no'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-warning editExpenseBtn" title="Edit"
                                            data-id="<?php echo $e['id']; ?>"
                                            data-date="<?php echo $e['expense_date']; ?>T00:00"
                                            data-cat-id="<?php echo $e['expense_category']; ?>"
                                            data-description="<?php echo htmlspecialchars($e['description'], ENT_QUOTES); ?>"
                                            data-amount="<?php echo $e['amount']; ?>"
                                            data-method="<?php echo htmlspecialchars($e['payment_method']); ?>"
                                            data-receipt="<?php echo htmlspecialchars($e['receipt_no'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $e['id']; ?>" class="btn btn-xs btn-danger" title="Delete" onclick="return confirm('Delete this expense? The cashbook entry will also be removed.')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-2 d-block opacity-25"></i>
                                    No expenses found for the selected period.
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expense Date *</label>
                        <input type="datetime-local" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description *</label>
                        <textarea name="description" class="form-control" rows="2" required placeholder="Describe the expense..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount (Rs) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Receipt No</label>
                        <input type="text" name="receipt_no" class="form-control" placeholder="Optional receipt / voucher no">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary rounded-pill px-4">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="expense_id" id="edit_expense_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expense Date *</label>
                        <input type="datetime-local" name="expense_date" id="edit_expense_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category *</label>
                        <select name="category_id" id="edit_category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description *</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount (Rs) *</label>
                            <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" id="edit_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank">Bank</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Receipt No</label>
                        <input type="text" name="receipt_no" id="edit_receipt" class="form-control" placeholder="Optional receipt / voucher no">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_expense" class="btn btn-primary rounded-pill px-4">Update Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Expense Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Expense Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted d-block">Category</small>
                            <strong id="view_category" class="fs-5"></strong>
                        </div>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill fs-6">#<span id="view_id"></span></span>
                    </div>
                    <small class="text-muted" id="view_date"></small>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr><td class="text-muted">Description</td><td class="text-end fw-semibold" id="view_description"></td></tr>
                        <tr><td class="text-muted">Payment Method</td><td class="text-end fw-semibold" id="view_method"></td></tr>
                        <tr><td class="text-muted">Receipt No</td><td class="text-end fw-semibold" id="view_receipt"></td></tr>
                        <tr><td class="text-muted">Amount</td><td class="text-end fw-bold text-danger fs-5" id="view_amount"></td></tr>
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
                <span class="print-doc-title">Expense Report</span>
                <span class="print-date-range"><?php echo date('d-m-Y', strtotime($from_date)); ?> to <?php echo date('d-m-Y', strtotime($to_date)); ?></span>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Method</th>
                    <th>Receipt #</th>
                    <th class="text-end">Amount (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_expenses = mysqli_query($conn, "SELECT e.*, c.category_name FROM expenses e LEFT JOIN expense_categories c ON e.expense_category = c.id $where ORDER BY e.expense_date DESC, e.id DESC");
                $sno = 1;
                $print_total = 0;
                if($print_expenses && mysqli_num_rows($print_expenses) > 0):
                    while($pe = mysqli_fetch_assoc($print_expenses)):
                        $print_total += $pe['amount'];
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($pe['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($pe['category_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($pe['description']); ?></td>
                        <td><?php echo htmlspecialchars($pe['payment_method']); ?></td>
                        <td><?php echo $pe['receipt_no'] ? htmlspecialchars($pe['receipt_no']) : '—'; ?></td>
                        <td class="text-end"><?php echo number_format($pe['amount'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="font-weight:700;background:#f0f0f0;">
                        <td colspan="6" class="text-end">Total</td>
                        <td class="text-end">Rs <?php echo number_format($print_total, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:#999;">No expenses found.</td></tr>
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

function printExpenses() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Expense Report</title>
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
                <strong style="color:#A04657;">Expense Report</strong>
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

function printExpense(id) {
    const btn = document.querySelector('.printExpenseBtn[data-id="' + id + '"]');
    if (!btn) return;
    const d = {
        id: btn.getAttribute('data-id'),
        date: btn.getAttribute('data-date'),
        category: btn.getAttribute('data-category'),
        description: btn.getAttribute('data-description'),
        amount: btn.getAttribute('data-amount'),
        method: btn.getAttribute('data-method'),
        receipt: btn.getAttribute('data-receipt')
    };
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
            <span class="print-doc-title">Expense Voucher</span>
            <span class="print-date-range">Receipt #: ${d.id}</span>
        </div>
        <div class="print-thin-divider"></div>
        <table class="print-table">
            <tbody>
                <tr><th>Date</th><td>${d.date}</td></tr>
                <tr><th>Category</th><td>${d.category}</td></tr>
                <tr><th>Description</th><td>${d.description}</td></tr>
                <tr><th>Payment Method</th><td>${d.method}</td></tr>
                <tr><th>Receipt / Voucher No</th><td>${d.receipt || '—'}</td></tr>
                <tr><th>Amount</th><td><strong>Rs ${d.amount}</strong></td></tr>
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
            <title>Expense Voucher ${d.id}</title>
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
                <strong style="color:#A04657;">Expense Voucher ${d.id}</strong>
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
    document.querySelectorAll('.viewExpenseBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('view_id').textContent = this.getAttribute('data-id');
            document.getElementById('view_date').textContent = 'Date: ' + this.getAttribute('data-date');
            document.getElementById('view_category').textContent = this.getAttribute('data-category');
            document.getElementById('view_description').textContent = this.getAttribute('data-description');
            document.getElementById('view_method').textContent = this.getAttribute('data-method');
            document.getElementById('view_receipt').textContent = this.getAttribute('data-receipt') || '—';
            document.getElementById('view_amount').textContent = 'Rs ' + this.getAttribute('data-amount');
            var viewModal = new bootstrap.Modal(document.getElementById('viewExpenseModal'));
            viewModal.show();
        });
    });
    document.querySelectorAll('.printExpenseBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            printExpense(this.getAttribute('data-id'));
        });
    });
    document.querySelectorAll('.editExpenseBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_expense_id').value = this.getAttribute('data-id');
            document.getElementById('edit_expense_date').value = this.getAttribute('data-date').slice(0, 16);
            document.getElementById('edit_category_id').value = this.getAttribute('data-cat-id');
            document.getElementById('edit_description').value = this.getAttribute('data-description');
            document.getElementById('edit_amount').value = this.getAttribute('data-amount');
            document.getElementById('edit_method').value = this.getAttribute('data-method');
            document.getElementById('edit_receipt').value = this.getAttribute('data-receipt');
            var editModal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
            editModal.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
