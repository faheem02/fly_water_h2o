<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

// Get date filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Get cashbook entries
$cashbook = mysqli_query($conn, "SELECT * FROM cashbook WHERE DATE(transaction_date) BETWEEN '$from_date' AND '$to_date' ORDER BY transaction_date ASC");

// Calculate totals
$total_income = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM cashbook WHERE transaction_type='income' AND DATE(transaction_date) BETWEEN '$from_date' AND '$to_date'"))['total'];
$total_expense = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM cashbook WHERE transaction_type='expense' AND DATE(transaction_date) BETWEEN '$from_date' AND '$to_date'"))['total'];
$net_cashflow = $total_income - $total_expense;

// Get opening and closing balance
$opening_balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook WHERE DATE(transaction_date) < '$from_date' ORDER BY id DESC LIMIT 1"))['balance'] ?? 0;
$closing_balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM cashbook WHERE DATE(transaction_date) <= '$to_date' ORDER BY id DESC LIMIT 1"))['balance'] ?? $opening_balance;

// Get recent income from payments
$recent_payments = mysqli_query($conn, "SELECT p.*, c.customer_name FROM customer_payments p JOIN customers c ON p.customer_id = c.id WHERE DATE(payment_datetime) BETWEEN '$from_date' AND '$to_date' ORDER BY payment_datetime DESC LIMIT 10");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.cashbook-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.cashbook-card .card-header {
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
.summary-card.income {
    background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
    color: white;
}
.summary-card.expense {
    background: linear-gradient(135deg, #ff7675 0%, #d63031 100%);
    color: white;
}
.summary-card.net {
    background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
    color: white;
}
.summary-card.opening {
    background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
    color: white;
}
.summary-card.closing {
    background: linear-gradient(135deg, #fdcb6e 0%, #f39c12 100%);
    color: white;
}
.cashbook-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}
.cashbook-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.income-row {
    background-color: rgba(0, 184, 148, 0.05);
}
.expense-row {
    background-color: rgba(255, 118, 117, 0.05);
}
.filter-control {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-book me-2" style="color: #A04657;"></i> Cashbook
        </h2>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="summary-card opening">
                <i class="fas fa-chart-line fa-2x mb-2 opacity-50"></i>
                <h6>Opening Balance</h6>
                <h3 class="mb-0">Rs <?php echo number_format($opening_balance, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card income">
                <i class="fas fa-arrow-down fa-2x mb-2 opacity-50"></i>
                <h6>Total Income (Selected Period)</h6>
                <h3 class="mb-0">Rs <?php echo number_format($total_income, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card expense">
                <i class="fas fa-arrow-up fa-2x mb-2 opacity-50"></i>
                <h6>Total Expense (Selected Period)</h6>
                <h3 class="mb-0">Rs <?php echo number_format($total_expense, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card net <?php echo $net_cashflow >= 0 ? 'income' : 'expense'; ?>">
                <i class="fas fa-calculator fa-2x mb-2 opacity-50"></i>
                <h6>Net Cash Flow</h6>
                <h3 class="mb-0">Rs <?php echo number_format($net_cashflow, 2); ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="summary-card closing">
                <i class="fas fa-wallet fa-2x mb-2 opacity-50"></i>
                <h6>Closing Balance</h6>
                <h2 class="mb-0">Rs <?php echo number_format($closing_balance, 2); ?></h2>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card cashbook-card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i> Quick Stats
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <small>Income Ratio</small>
                            <h4><?php echo $total_income + $total_expense > 0 ? number_format(($total_income / ($total_income + $total_expense)) * 100, 1) : 0; ?>%</h4>
                        </div>
                        <div class="col-6">
                            <small>Expense Ratio</small>
                            <h4><?php echo $total_income + $total_expense > 0 ? number_format(($total_expense / ($total_income + $total_expense)) * 100, 1) : 0; ?>%</h4>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $total_income + $total_expense > 0 ? ($total_income / ($total_income + $total_expense)) * 100 : 0; ?>%"></div>
                        <div class="progress-bar bg-danger" style="width: <?php echo $total_income + $total_expense > 0 ? ($total_expense / ($total_income + $total_expense)) * 100 : 0; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card cashbook-card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-2"></i> Filter Cashbook
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control filter-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control filter-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary-custom w-100">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cashbook Table -->
    <div class="card cashbook-card">
        <div class="card-header">
            <i class="fas fa-list-alt me-2"></i> Cashbook Entries
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table cashbook-table mb-0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Debit (Expense)</th>
                            <th class="text-end">Credit (Income)</th>
                            <th class="text-end">Balance (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($cashbook) > 0): 
                            $running_balance = $opening_balance;
                        ?>
                            <tr style="background: #f0f0f0;">
                                <td colspan="5"><strong>Opening Balance</strong></td>
                                <td class="text-end fw-bold">Rs <?php echo number_format($running_balance, 2); ?></td>
                            </tr>
                            <?php while($cb = mysqli_fetch_assoc($cashbook)): 
                                $running_balance = $cb['balance'];
                            ?>
                                <tr class="<?php echo $cb['transaction_type'] == 'income' ? 'income-row' : 'expense-row'; ?>">
                                    <td><?php echo date('d-m-Y h:i A', strtotime($cb['transaction_date'])); ?></td>
                                    <td>
                                        <?php if($cb['transaction_type'] == 'income'): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-arrow-down me-1"></i> Income</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill"><i class="fas fa-arrow-up me-1"></i> Expense</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cb['description']); ?></td>
                                    <td class="text-end text-danger"><?php echo $cb['transaction_type'] == 'expense' ? 'Rs ' . number_format($cb['amount'], 2) : '—'; ?></td>
                                    <td class="text-end text-success"><?php echo $cb['transaction_type'] == 'income' ? 'Rs ' . number_format($cb['amount'], 2) : '—'; ?></td>
                                    <td class="text-end fw-bold">Rs <?php echo number_format($running_balance, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr style="background: #A04657; color: white;">
                                <td colspan="5"><strong>Closing Balance</strong></td>
                                <td class="text-end fw-bold">Rs <?php echo number_format($running_balance, 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No transactions found for selected period<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="card cashbook-card mt-4">
        <div class="card-header">
            <i class="fas fa-money-bill-wave me-2"></i> Recent Customer Payments
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table cashbook-table mb-0">
                    <thead>
                        <tr><th>Date</th><th>Customer</th><th>Amount (Rs)</th><th>Payment Method</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recent_payments) > 0): ?>
                            <?php while($p = mysqli_fetch_assoc($recent_payments)): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($p['payment_datetime'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['customer_name']); ?></td>
                                    <td class="text-success fw-bold">Rs <?php echo number_format($p['payment_amount'], 2); ?></td>
                                    <td><?php echo $p['payment_type']; ?></td>
                                    <td><?php echo $p['notes'] ?: '—'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">No recent payments<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>