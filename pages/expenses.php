<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $category_id = intval($_POST['category_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = floatval($_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $receipt_no = mysqli_real_escape_string($conn, $_POST['receipt_no']);
    $datetime = date('Y-m-d H:i:s');
    
    // Get category name
    $cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_name FROM expense_categories WHERE id=$category_id"));
    $category_name = $cat['category_name'];
    
    // Insert expense
    $query = "INSERT INTO expenses (expense_date, expense_category, description, amount, payment_method, receipt_no, created_by, created_datetime) 
              VALUES ('$expense_date', '$category_name', '$description', $amount, '$payment_method', '$receipt_no', {$_SESSION['admin_id']}, '$datetime')";
    
    if(mysqli_query($conn, $query)) {
        $expense_id = mysqli_insert_id($conn);
        
        // Get last cashbook balance
        $last_balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook ORDER BY id DESC LIMIT 1"))['balance'] ?? 0;
        $new_balance = $last_balance - $amount;
        
        // Add to cashbook
        mysqli_query($conn, "INSERT INTO cashbook (transaction_date, transaction_type, reference_type, reference_id, description, amount, balance, created_datetime) 
                             VALUES ('$expense_date', 'expense', 'expense', $expense_id, 'Expense: $category_name - $description', $amount, $new_balance, '$datetime')");
        
        $success = "Expense of Rs " . number_format($amount, 2) . " recorded successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle Delete Expense
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM expenses WHERE id=$id");
    mysqli_query($conn, "DELETE FROM cashbook WHERE reference_type='expense' AND reference_id=$id");
    header("Location: expenses.php?msg=deleted");
    exit();
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['cat_description']);
    $datetime = date('Y-m-d H:i:s');
    mysqli_query($conn, "INSERT INTO expense_categories (category_name, description, created_datetime) VALUES ('$category_name', '$description', '$datetime')");
    $success = "Category added successfully!";
}

// Get expenses with filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$where = "WHERE DATE(expense_date) BETWEEN '$from_date' AND '$to_date'";
if($category_filter > 0) {
    $cat_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_name FROM expense_categories WHERE id=$category_filter"))['category_name'];
    $where .= " AND expense_category = '$cat_name'";
}

$expenses = mysqli_query($conn, "SELECT * FROM expenses $where ORDER BY expense_date DESC");
$categories = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY category_name");

// Calculate totals
$total_expenses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses $where"))['total'];
$monthly_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE MONTH(expense_date)=MONTH(CURDATE())"))['total'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.expense-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.expense-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.summary-card {
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: transform 0.2s;
}
.summary-card:hover {
    transform: translateY(-3px);
}
.summary-card.total {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
}
.summary-card.monthly {
    background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
    color: white;
}
.filter-control {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}
.btn-primary-custom {
    background: #A04657;
    color: white;
    border-radius: 12px;
    padding: 10px 25px;
    border: none;
}
.expense-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}
.expense-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-money-bill-wave me-2" style="color: #A04657;"></i> Expense Management
        </h2>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus-circle me-2"></i> Add Expense
        </button>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="summary-card total">
                <i class="fas fa-chart-line fa-2x mb-2 opacity-50"></i>
                <h5>Total Expenses (Selected Period)</h5>
                <h2 class="mb-0">Rs <?php echo number_format($total_expenses, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-6">
            <div class="summary-card monthly">
                <i class="fas fa-calendar-alt fa-2x mb-2 opacity-50"></i>
                <h5>This Month Expenses</h5>
                <h2 class="mb-0">Rs <?php echo number_format($monthly_total, 2); ?></h2>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card expense-card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-2"></i> Filter Expenses
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control filter-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control filter-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select filter-control">
                        <option value="0">All Categories</option>
                        <?php 
                        $cats = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY category_name");
                        while($cat = mysqli_fetch_assoc($cats)): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary-custom w-100">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card expense-card">
        <div class="card-header">
            <i class="fas fa-list-alt me-2"></i> Expense History
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
                        <?php if(mysqli_num_rows($expenses) > 0): ?>
                            <?php while($e = mysqli_fetch_assoc($expenses)): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($e['expense_date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($e['expense_category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($e['description']); ?></td>
                                    <td><?php echo $e['payment_method']; ?></td>
                                    <td><?php echo $e['receipt_no'] ?: '—'; ?></td>
                                    <td class="text-end text-danger fw-bold">Rs <?php echo number_format($e['amount'], 2); ?></td>
                                    <td class="text-center">
                                        <a href="?delete=<?php echo $e['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('Delete this expense?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No expenses found. Click "Add Expense" to record.<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Management -->
    <div class="card expense-card mt-4">
        <div class="card-header">
            <i class="fas fa-tags me-2"></i> Expense Categories
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <form method="POST" class="d-flex gap-2">
                        <input type="text" name="category_name" class="form-control" placeholder="New Category Name" required>
                        <input type="text" name="cat_description" class="form-control" placeholder="Description">
                        <button type="submit" name="add_category" class="btn btn-primary-custom">Add</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Category</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php 
                                $all_cats = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY category_name");
                                while($cat = mysqli_fetch_assoc($all_cats)): ?>
                                    <tr><td><?php echo htmlspecialchars($cat['category_name']); ?></td><td><?php echo htmlspecialchars($cat['description']); ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Expense Date *</label>
                        <input type="datetime-local" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php 
                            $cat_options = mysqli_query($conn, "SELECT * FROM expense_categories ORDER BY category_name");
                            while($cat = mysqli_fetch_assoc($cat_options)): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="2" required placeholder="Describe the expense..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (Rs) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>