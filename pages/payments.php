<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

function recalcLedger($customer_id) {
    global $conn;
    $entries = mysqli_query($conn, "SELECT id, debit_amount, credit_amount FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id ASC");
    $running = 0;
    while($e = mysqli_fetch_assoc($entries)) {
        $running = $running + $e['debit_amount'] - $e['credit_amount'];
        mysqli_query($conn, "UPDATE customer_ledger SET running_balance=$running WHERE id={$e['id']}");
    }
}

function recalcCashbook() {
    global $conn;
    $entries = mysqli_query($conn, "SELECT id, transaction_type, amount FROM cashbook ORDER BY id ASC");
    $running = 0;
    while($e = mysqli_fetch_assoc($entries)) {
        $running = ($e['transaction_type'] == 'income') ? $running + $e['amount'] : $running - $e['amount'];
        mysqli_query($conn, "UPDATE cashbook SET balance=$running WHERE id={$e['id']}");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $customer_id = intval($_POST['customer_id']);
    $amount = floatval($_POST['payment_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $datetime = date('Y-m-d H:i:s');

    // Get customer details
    $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT outstanding_balance, customer_name FROM customers WHERE id=$customer_id"));
    
    if($cust) {
        $new_outstanding = $cust['outstanding_balance'] - $amount;
        mysqli_query($conn, "UPDATE customers SET outstanding_balance = $new_outstanding WHERE id=$customer_id");

        // Insert payment record (use voucher shown on form, fallback to auto-generate)
        $voucher_no = !empty($_POST['voucher_no']) ? mysqli_real_escape_string($conn, $_POST['voucher_no']) : generate_voucher_no($conn, 'customer_payments', 'voucher_no', 'RCP-');
        $payment_query = "INSERT INTO customer_payments (voucher_no, customer_id, payment_amount, payment_type, notes, payment_datetime) 
                          VALUES ('$voucher_no', $customer_id, $amount, 'Cash', '$notes', '$datetime')";
        mysqli_query($conn, $payment_query);
        $payment_id = mysqli_insert_id($conn);

        // Ledger: Credit
        $running = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_balance FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id DESC LIMIT 1"))['running_balance'] ?? 0;
        $new_balance = $running - $amount;
        $desc = "Payment Received (Cash)";
        mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                             VALUES ($customer_id, '$datetime', '$desc', 0, $amount, $new_balance, $payment_id, 'payment')");

        // Cashbook: Income
        $cash_desc = "Customer: " . mysqli_real_escape_string($conn, $cust['customer_name']) . " - Payment received";
        if($notes) $cash_desc .= " - " . $notes;
        mysqli_query($conn, "INSERT INTO cashbook (transaction_date, transaction_type, reference_type, reference_id, description, amount, balance, created_datetime) 
                             VALUES ('$datetime', 'income', 'payment', $payment_id, '$cash_desc', $amount, 0, '$datetime')");
        recalcCashbook();

        $success = "Payment of Rs " . number_format($amount, 2) . " received from " . htmlspecialchars($cust['customer_name']) . "! Voucher: <strong>$voucher_no</strong>";
    } else {
        $error = "Customer not found!";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_payment'])) {
    $payment_id = intval($_POST['payment_id']);
    $amount = floatval($_POST['payment_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_id, payment_amount, payment_datetime FROM customer_payments WHERE id=$payment_id"));
    if($old) {
        $customer_id = $old['customer_id'];
        $old_amount = $old['payment_amount'];
        $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT outstanding_balance, customer_name FROM customers WHERE id=$customer_id"));
        $new_outstanding = $cust['outstanding_balance'] + $old_amount - $amount;

        mysqli_query($conn, "UPDATE customer_payments SET payment_amount=$amount, notes='$notes' WHERE id=$payment_id");
        mysqli_query($conn, "UPDATE customers SET outstanding_balance=$new_outstanding WHERE id=$customer_id");
        mysqli_query($conn, "UPDATE customer_ledger SET credit_amount=$amount WHERE customer_id=$customer_id AND reference_id=$payment_id AND reference_type='payment'");
        recalcLedger($customer_id);
        $cash_desc = "Customer: " . mysqli_real_escape_string($conn, $cust['customer_name']) . " - Payment received";
        if($notes) $cash_desc .= " - " . $notes;
        mysqli_query($conn, "UPDATE cashbook SET amount=$amount, description='$cash_desc' WHERE reference_id=$payment_id AND reference_type='payment'");
        recalcCashbook();
        $success = "Payment updated successfully for " . htmlspecialchars($cust['customer_name']) . "!";
    } else {
        $error = "Payment record not found!";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_payment'])) {
    $payment_id = intval($_POST['payment_id']);

    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_id, payment_amount, customer_name FROM customer_payments p JOIN customers c ON p.customer_id = c.id WHERE p.id=$payment_id"));
    if($old) {
        $customer_id = $old['customer_id'];
        $amount = $old['payment_amount'];
        $new_outstanding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT outstanding_balance FROM customers WHERE id=$customer_id"))['outstanding_balance'] + $amount;

        mysqli_query($conn, "UPDATE customers SET outstanding_balance=$new_outstanding WHERE id=$customer_id");
        mysqli_query($conn, "DELETE FROM customer_ledger WHERE customer_id=$customer_id AND reference_id=$payment_id AND reference_type='payment'");
        recalcLedger($customer_id);
        mysqli_query($conn, "DELETE FROM cashbook WHERE reference_id=$payment_id AND reference_type='payment'");
        recalcCashbook();
        mysqli_query($conn, "DELETE FROM customer_payments WHERE id=$payment_id");
        $success = "Payment of Rs " . number_format($amount, 2) . " deleted from " . htmlspecialchars($old['customer_name']) . "!";
    } else {
        $error = "Payment record not found!";
    }
}

// Get customer list with outstanding balance
$customers = mysqli_query($conn, "SELECT id, customer_code, customer_name, mobile, outstanding_balance FROM customers WHERE status='Active' ORDER BY customer_name");

// Next auto-generated payment voucher (shown on Add Payment form)
$next_rcp_voucher = generate_voucher_no($conn, 'customer_payments', 'voucher_no', 'RCP-');

// Search + date filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$filter_conditions = [];
if($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $filter_conditions[] = "(p.voucher_no LIKE '%$s%' OR c.customer_name LIKE '%$s%' OR c.customer_code LIKE '%$s%' OR c.mobile LIKE '%$s%' OR p.notes LIKE '%$s%')";
}
if($from_date !== '') {
    $fd = mysqli_real_escape_string($conn, $from_date);
    $filter_conditions[] = "DATE(p.payment_datetime) >= '$fd'";
}
if($to_date !== '') {
    $td = mysqli_real_escape_string($conn, $to_date);
    $filter_conditions[] = "DATE(p.payment_datetime) <= '$td'";
}
$filter_condition = $filter_conditions ? ('WHERE ' . implode(' AND ', $filter_conditions)) : '';

// Get recent payments
$payments = mysqli_query($conn, "SELECT p.*, c.customer_name, c.mobile FROM customer_payments p JOIN customers c ON p.customer_id = c.id $filter_condition ORDER BY p.payment_datetime DESC, p.id DESC LIMIT 100");

// Payment data for view + print
$paymentsData = [];
$payments_reset = mysqli_query($conn, "SELECT p.*, c.customer_name, c.mobile, c.address FROM customer_payments p JOIN customers c ON p.customer_id = c.id $filter_condition ORDER BY p.payment_datetime DESC, p.id DESC LIMIT 100");
while($p = mysqli_fetch_assoc($payments_reset)) {
    $paymentsData[] = [
        'id'       => intval($p['id']),
        'voucher'  => $p['voucher_no'] ?? '',
        'customer' => $p['customer_name'],
        'mobile'   => $p['mobile'],
        'address'  => $p['address'] ?? '',
        'amount'   => floatval($p['payment_amount']),
        'type'     => $p['payment_type'] ?? 'Cash',
        'notes'    => $p['notes'] ?? '',
        'datetime' => $p['payment_datetime']
    ];
}

// Get today's total payments
$today_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(payment_amount),0) as total, COUNT(*) as count FROM customer_payments WHERE DATE(payment_datetime)=CURDATE()"));

// Get this month's total
$month_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(payment_amount),0) as total FROM customer_payments WHERE MONTH(payment_datetime)=MONTH(CURDATE()) AND YEAR(payment_datetime)=YEAR(CURDATE())"));
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.payment-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.payment-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.payment-form .form-label {
    font-weight: 500;
    font-size: 13px;
    color: #555;
    margin-bottom: 5px;
}
.payment-form .form-control,
.payment-form .form-select {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
}
.payment-form .form-control:focus,
.payment-form .form-select:focus {
    border-color: #A04657;
    box-shadow: 0 0 0 0.2rem rgba(160,70,87,0.1);
}
.outstanding-badge {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
}
.payment-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}
.payment-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.payment-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
}
.summary-card {
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}
.summary-card:hover {
    transform: translateY(-3px);
}
.summary-card.today {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
    color: white;
}
.summary-card.month {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
}
.search-box {
    position: relative;
}
.search-box input {
    padding-left: 40px;
}
.search-box i {
    position: absolute;
    left: 15px;
    top: 12px;
    color: #999;
}
.customer-info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px 15px;
    margin-top: 10px;
}
.modal-content {
    border-radius: 20px;
    border: none;
}
.modal-header {
    background: #A04657;
    color: white;
    border-bottom: none;
    border-radius: 20px 20px 0 0;
    padding: 15px 20px;
}
.modal-header .btn-close {
    filter: invert(1);
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-money-bill-wave me-2" style="color: #A04657;"></i> Customer Payments
        </h2>
        <div class="d-flex flex-wrap gap-2">
            <?php if(mysqli_num_rows($payments) > 0): ?>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="printPaymentList()">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-plus me-2"></i> Receive Payment
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="summary-card today">
                <small>Today's Collection</small>
                <h4 class="mb-0">Rs <?php echo number_format($today_total['total'], 2); ?></h4>
                <small><?php echo $today_total['count']; ?> payments</small>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="summary-card month">
                <small>This Month</small>
                <h4 class="mb-0">Rs <?php echo number_format($month_total['total'], 2); ?></h4>
                <small>Total collected</small>
            </div>
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

    <!-- Search + Date Filter -->
    <div class="card payment-card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3 col-lg-3">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search by customer, mobile or voucher no..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1 d-block">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>" style="height: 46px; border-radius: 8px;">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1 d-block">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>" style="height: 46px; border-radius: 8px;">
                </div>
                <div class="col-auto align-self-end">
                    <button type="submit" class="btn btn-primary" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
                <?php if($search !== '' || $from_date !== '' || $to_date !== ''): ?>
                    <div class="col-auto align-self-end">
                        <a href="payments.php" class="btn btn-outline-secondary" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    </div>
                    <div class="col-auto align-self-end">
                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-2">
                            <?php echo mysqli_num_rows($payments); ?> result(s)
                            <?php if($search !== ''): ?> for "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card payment-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-history me-2"></i> Payment History</span>
            <span class="badge bg-white text-dark rounded-pill"><?php echo mysqli_num_rows($payments); ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table payment-table mb-0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Voucher No</th>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th>Amount (Rs)</th>
                            <th>Notes</th>
                            <th style="width:110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($payments) > 0): ?>
                            <?php while($p = mysqli_fetch_assoc($payments)): ?>
                                <tr>
                                    <td><i class="far fa-calendar-alt me-1 text-muted"></i> <?php echo date('d/m/y h:i A', strtotime($p['payment_datetime'])); ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill"><?php echo htmlspecialchars($p['voucher_no'] ?? '—'); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($p['customer_name']); ?></strong></td>
                                    <td><?php echo $p['mobile']; ?></td>
                                    <td class="fw-bold text-success">Rs <?php echo number_format($p['payment_amount'], 2); ?></td>
                                    <td><small class="text-muted"><?php echo $p['notes'] ?: '—'; ?></small></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-xs btn-outline-info viewPaymentBtn" title="View"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-voucher="<?php echo htmlspecialchars($p['voucher_no'] ?? ''); ?>"
                                            data-customer="<?php echo htmlspecialchars($p['customer_name']); ?>"
                                            data-mobile="<?php echo htmlspecialchars($p['mobile']); ?>"
                                            data-amount="<?php echo $p['payment_amount']; ?>"
                                            data-type="<?php echo htmlspecialchars($p['payment_type'] ?? 'Cash'); ?>"
                                            data-notes="<?php echo htmlspecialchars($p['notes']); ?>"
                                            data-datetime="<?php echo htmlspecialchars($p['payment_datetime']); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-dark printPaymentBtn" title="Print" data-id="<?php echo $p['id']; ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-warning editPaymentBtn"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-customer="<?php echo htmlspecialchars($p['customer_name']); ?>"
                                            data-amount="<?php echo $p['payment_amount']; ?>"
                                            data-notes="<?php echo htmlspecialchars($p['notes']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-danger deletePaymentBtn"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-customer="<?php echo htmlspecialchars($p['customer_name']); ?>"
                                            data-amount="<?php echo number_format($p['payment_amount'], 2); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3 d-block opacity-25"></i>
                                    No payments recorded yet.
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

<!-- Receive Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Receive Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" class="payment-form" id="paymentForm">
                    <!-- Voucher Number (auto-generated, shown at entry) -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-hashtag me-1"></i> Voucher No <span class="badge bg-info-subtle text-info-emphasis">Auto</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-file-invoice text-muted"></i></span>
                            <input type="text" name="voucher_no" class="form-control fw-bold" value="<?php echo htmlspecialchars($next_rcp_voucher); ?>" readonly style="color:#A04657;">
                        </div>
                        <small class="text-muted">This voucher number will be recorded with this payment</small>
                    </div>
                    <!-- Customer Selection with Search -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1"></i> Select Customer <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="customerSearch" class="form-control" placeholder="Search customer by name, ID or mobile..." autocomplete="off">
                        </div>
                        <select name="customer_id" id="customerId" class="form-select mt-2" required style="display:none;">
                            <option value="">-- Select Customer --</option>
                            <?php while($c = mysqli_fetch_assoc($customers)): ?>
                                <option value="<?php echo $c['id']; ?>" data-code="<?php echo $c['customer_code']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-mobile="<?php echo $c['mobile']; ?>" data-outstanding="<?php echo $c['outstanding_balance']; ?>">
                                    <?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo $c['customer_code']; ?>) - <?php echo $c['mobile']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="customerSuggestions" class="list-group mt-2" style="max-height: 250px; overflow-y: auto; display: none;"></div>
                        <div id="selectedCustomer" class="mt-2" style="display: none;">
                            <div class="customer-info-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-circle text-primary me-1"></i>
                                        <strong id="selectedCustomerName"></strong>
                                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill ms-1" id="selectedCustomerCode"></span>
                                        <br><small class="text-muted" id="selectedCustomerMobile"></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-warning text-dark rounded-pill">Outstanding: Rs <span id="selectedOutstanding">0</span></span>
                                        <button type="button" class="btn btn-sm btn-link text-danger ms-2" onclick="clearCustomerSelection()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Amount -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-rupee-sign me-1"></i> Payment Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="payment_amount" id="paymentAmount" class="form-control" placeholder="Enter amount" required min="1" onkeyup="validateAmount()">
                        <small class="text-muted" id="amountHint"></small>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-pen me-1"></i> Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this payment..."></textarea>
                    </div>

                    <!-- Hidden payment type as Cash -->
                    <input type="hidden" name="payment_type" value="Cash">

                    <!-- Submit Button -->
                    <button type="submit" name="add_payment" class="btn btn-primary w-100 rounded-pill py-2 mt-2" id="submitBtn" disabled>
                        <i class="fas fa-save me-2"></i> Process Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Payment Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i> Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted d-block">Customer</small>
                            <strong id="view_customer" class="fs-5"></strong>
                        </div>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill fs-6" id="view_voucher"></span>
                    </div>
                    <small class="text-muted" id="view_mobile"></small>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Date &amp; Time</td>
                        <td class="text-end fw-semibold" id="view_datetime"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payment Type</td>
                        <td class="text-end fw-semibold" id="view_type"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount</td>
                        <td class="text-end fw-bold text-success fs-5" id="view_amount"></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Notes</td>
                        <td class="text-end" id="view_notes"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="payment_id" id="edit_payment_id">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user me-1"></i> Customer</label>
                        <input type="text" id="edit_customer_name" class="form-control" readonly style="background:#f5f5f5;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-rupee-sign me-1"></i> Payment Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="payment_amount" id="edit_payment_amount" class="form-control" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-pen me-1"></i> Notes (Optional)</label>
                        <textarea name="notes" id="edit_payment_notes" class="form-control" rows="2" placeholder="Any notes about this payment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_payment" class="btn btn-primary rounded-pill px-4">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Payment Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3545;">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="payment_id" id="delete_payment_id">
                    <p class="mb-0">Are you sure you want to delete the payment of <strong id="delete_payment_amount"></strong> from <strong id="delete_payment_customer"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_payment" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Customer data storage
let customers = [];

<?php 
$customers_array = [];
$cust_reset = mysqli_query($conn, "SELECT id, customer_code, customer_name, mobile, outstanding_balance FROM customers WHERE status='Active' ORDER BY customer_name");
while($c = mysqli_fetch_assoc($cust_reset)) {
    $customers_array[] = $c;
}
?>
customers = <?php echo json_encode($customers_array); ?>;

const customerSelect = document.getElementById('customerId');
const customerSearch = document.getElementById('customerSearch');
const suggestionsDiv = document.getElementById('customerSuggestions');
const selectedCustomerDiv = document.getElementById('selectedCustomer');
const selectedCustomerName = document.getElementById('selectedCustomerName');
const selectedCustomerCode = document.getElementById('selectedCustomerCode');
const selectedCustomerMobile = document.getElementById('selectedCustomerMobile');
const selectedOutstanding = document.getElementById('selectedOutstanding');
const paymentAmount = document.getElementById('paymentAmount');
const amountHint = document.getElementById('amountHint');
const submitBtn = document.getElementById('submitBtn');

let currentOutstanding = 0;

// Filter and show suggestions
function showSuggestions(searchTerm) {
    if(searchTerm.length < 1) {
        suggestionsDiv.style.display = 'none';
        return;
    }
    
    const filtered = customers.filter(c => 
        c.customer_name.toLowerCase().includes(searchTerm.toLowerCase()) || 
        (c.customer_code && c.customer_code.toLowerCase().includes(searchTerm.toLowerCase())) ||
        c.mobile.includes(searchTerm)
    );
    
    if(filtered.length > 0) {
        suggestionsDiv.innerHTML = filtered.map(c => `
            <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${c.id}, '${escapeHtml(c.customer_name)}', '${escapeHtml(c.customer_code || '')}', '${c.mobile}', ${c.outstanding_balance}); return false;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${escapeHtml(c.customer_name)}</strong>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">${escapeHtml(c.customer_code || '')}</span>
                        <br><small class="text-muted">${c.mobile}</small>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill">Outstanding: Rs ${parseFloat(c.outstanding_balance).toFixed(2)}</span>
                </div>
            </a>
        `).join('');
        suggestionsDiv.style.display = 'block';
    } else {
        suggestionsDiv.innerHTML = '<div class="list-group-item text-muted">No customers found</div>';
        suggestionsDiv.style.display = 'block';
    }
}

function escapeHtml(text) {
    if(!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

function fmtOutstanding(n) {
    n = parseFloat(n) || 0;
    if(n < 0) return 'Advance: Rs ' + Math.abs(n).toFixed(2);
    return 'Rs ' + n.toFixed(2);
}

function selectCustomer(id, name, code, mobile, outstanding) {
    customerSelect.value = id;
    customerSearch.value = name;
    selectedCustomerName.innerText = name;
    selectedCustomerCode.innerText = code || '';
    selectedCustomerMobile.innerText = mobile ? ('Mobile: ' + mobile) : '';
    selectedOutstanding.innerText = fmtOutstanding(outstanding);
    currentOutstanding = outstanding;
    selectedCustomerDiv.style.display = 'block';
    suggestionsDiv.style.display = 'none';
    validateAmount();
}

function clearCustomerSelection() {
    customerSelect.value = '';
    customerSearch.value = '';
    selectedCustomerDiv.style.display = 'none';
    currentOutstanding = 0;
    paymentAmount.value = '';
    amountHint.innerHTML = '';
    submitBtn.disabled = true;
    suggestionsDiv.style.display = 'none';
}

function validateAmount() {
    const amount = parseFloat(paymentAmount.value) || 0;
    const hasCustomer = customerSelect.value !== '';

    if(hasCustomer && amount > 0) {
        if(amount > currentOutstanding) {
            amountHint.innerHTML = '<i class="fas fa-info-circle text-info"></i> Amount exceeds outstanding; an advance of Rs ' + (amount - currentOutstanding).toFixed(2) + ' will be recorded.';
            submitBtn.disabled = false;
        } else {
            amountHint.innerHTML = '<i class="fas fa-check-circle text-success"></i> Valid amount';
            submitBtn.disabled = false;
        }
    } else {
        amountHint.innerHTML = '';
        submitBtn.disabled = true;
    }
}

// Reset form whenever modal is closed
document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('paymentForm').reset();
    clearCustomerSelection();
});

// Edit payment
document.querySelectorAll('.editPaymentBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_payment_id').value = this.getAttribute('data-id');
        document.getElementById('edit_customer_name').value = this.getAttribute('data-customer');
        document.getElementById('edit_payment_amount').value = this.getAttribute('data-amount');
        document.getElementById('edit_payment_notes').value = this.getAttribute('data-notes');
        new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
    });
});

// Delete payment
document.querySelectorAll('.deletePaymentBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('delete_payment_id').value = this.getAttribute('data-id');
        document.getElementById('delete_payment_customer').textContent = this.getAttribute('data-customer');
        document.getElementById('delete_payment_amount').textContent = 'Rs ' + parseFloat(this.getAttribute('data-amount')).toFixed(2);
        new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
    });
});

// Search input event
customerSearch.addEventListener('input', function(e) {
    showSuggestions(e.target.value);
});

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if(!customerSearch.contains(e.target) && !suggestionsDiv.contains(e.target)) {
        suggestionsDiv.style.display = 'none';
    }
});

paymentAmount.addEventListener('keyup', validateAmount);
paymentAmount.addEventListener('change', validateAmount);

// ---------- Print + View ----------
const companyName  = <?php echo json_encode($company_name); ?>;
const ownerName    = <?php echo json_encode($owner_name); ?>;
const ownerAddress = <?php echo json_encode($owner_address); ?>;
const ownerPhone   = <?php echo json_encode($owner_phone); ?>;

let paymentsData = <?php echo json_encode($paymentsData); ?>;

function fmt(n) { return parseFloat(n || 0).toFixed(2); }
function fmtDate(dt) {
    if(!dt) return '—';
    const d = new Date(dt.replace(' ', 'T'));
    return d.toLocaleString('en-GB', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
}
function printHeaderHtml() {
    return `
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle"><i class="fas fa-tint"></i></div>
                <div class="print-brand-text">
                    <div class="print-owner-name">${escapeHtml(ownerName)}</div>
                    <div class="print-company">${escapeHtml(companyName)}</div>
                    <div class="print-address">${escapeHtml(ownerAddress)}</div>
                    <div class="print-phone">${escapeHtml(ownerPhone)}</div>
                </div>
            </div>
            <div class="print-divider"></div>
        </div>`;
}
function capturePrint(title) {
    const overlay = document.getElementById('print-overlay');
    const area = document.getElementById('print-area');
    overlay.style.display = 'block';
    setTimeout(function() {
        html2canvas(area, {
            scale: 3,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: area.scrollWidth,
            height: area.scrollHeight
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const w = window.open('', '_blank');
            w.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${title}</title>
                    <style>
                        @page { margin: 0; size: A4; }
                        body { margin: 0; display: flex; justify-content: center; padding: 20px; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src="${imgData}" />
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 300);
                        }
                    <\/script>
                </body>
                </html>
            `);
            w.document.close();
            overlay.style.display = 'none';
        }).catch(err => {
            console.error(err);
            alert('Print failed. Please try again.');
            overlay.style.display = 'none';
        });
    }, 200);
}
function printPaymentList() {
    const area = document.getElementById('print-area');
    let rows = '';
    let total = 0;
    let sno = 1;
    paymentsData.forEach(function(p) {
        total += p.amount;
        rows += `
            <tr>
                <td>${sno++}</td>
                <td>${fmtDate(p.datetime)}</td>
                <td><strong>${escapeHtml(p.voucher)}</strong></td>
                <td>${escapeHtml(p.customer)}</td>
                <td>${escapeHtml(p.mobile)}</td>
                <td class="text-end">${fmt(p.amount)}</td>
            </tr>`;
    });
    area.innerHTML = `
        ${printHeaderHtml()}
        <div class="print-thin-divider"></div>
        <div class="print-title-row">
            <span class="print-doc-title">Customer Payment List</span>
            <span class="print-date-range">Generated: ${new Date().toLocaleString('en-GB')}</span>
        </div>
        <div class="print-thin-divider"></div>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:120px;">Date</th>
                    <th style="width:120px;">Voucher No</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th style="width:110px;" class="text-end">Amount (Rs)</th>
                </tr>
            </thead>
            <tbody>${rows || '<tr><td colspan="6" style="padding:40px;text-align:center;color:#999;">No payments found.</td></tr>'}</tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end"><strong>Total (${paymentsData.length} payments)</strong></td>
                    <td class="text-end"><strong>${fmt(total)}</strong></td>
                </tr>
            </tfoot>
        </table>
        <div class="print-footer">Generated on: ${new Date().toLocaleString('en-GB')}</div>`;
    capturePrint('Customer Payment List');
}
function printSinglePayment(id) {
    const p = paymentsData.find(function(x){ return x.id === id; });
    if(!p) return;
    const area = document.getElementById('print-area');
    area.innerHTML = `
        ${printHeaderHtml()}
        <div class="print-title-row">
            <span class="print-doc-title">Payment Voucher</span>
            <span class="print-date-range">Voucher: ${escapeHtml(p.voucher) || '—'}</span>
        </div>
        <div class="print-thin-divider"></div>
        <table class="print-receipt-info">
            <tr><td>Customer Name:</td><td><strong>${escapeHtml(p.customer)}</strong></td></tr>
            <tr><td>Phone:</td><td>${escapeHtml(p.mobile)}</td></tr>
            ${p.address ? '<tr><td>Address:</td><td>' + escapeHtml(p.address) + '</td></tr>' : ''}
            <tr><td>Date &amp; Time:</td><td>${fmtDate(p.datetime)}</td></tr>
            <tr><td>Payment Type:</td><td>${escapeHtml(p.type)}</td></tr>
            ${p.notes ? '<tr><td>Notes:</td><td>' + escapeHtml(p.notes) + '</td></tr>' : ''}
            <tr>
                <td>Amount Received:</td>
                <td class="amount-cell">Rs ${fmt(p.amount)}</td>
            </tr>
        </table>
        <div class="print-sign-row">
            <div class="print-sign-box"><div class="line">Received By</div></div>
            <div class="print-sign-box"><div class="line">Authorized Signature</div></div>
        </div>
        <div class="print-footer">Generated on: ${new Date().toLocaleString('en-GB')}</div>`;
    capturePrint('Payment Voucher - ' + (p.voucher || p.id));
}

// View payment
document.querySelectorAll('.viewPaymentBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('view_voucher').textContent = this.getAttribute('data-voucher') || '—';
        document.getElementById('view_customer').textContent = this.getAttribute('data-customer');
        document.getElementById('view_mobile').textContent = this.getAttribute('data-mobile');
        document.getElementById('view_datetime').textContent = fmtDate(this.getAttribute('data-datetime'));
        const type = this.getAttribute('data-type') || 'Cash';
        document.getElementById('view_type').innerHTML = '<i class="fas fa-money-bill-wave me-1"></i>' + type;
        document.getElementById('view_amount').textContent = 'Rs ' + fmt(this.getAttribute('data-amount'));
        document.getElementById('view_notes').textContent = this.getAttribute('data-notes') || '—';
        new bootstrap.Modal(document.getElementById('viewPaymentModal')).show();
    });
});

// Print single payment
document.querySelectorAll('.printPaymentBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        printSinglePayment(parseInt(this.getAttribute('data-id'), 10));
    });
});
</script>

<!-- Print Overlay -->
<div id="print-overlay">
    <div id="print-area"></div>
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
.print-logo-circle {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #A04657, #c96b7e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    flex-shrink: 0;
}
.print-brand-text { display: flex; flex-direction: column; gap: 2px; }
.print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-address { font-size: 13px; color: #666; }
.print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
.print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
.print-thin-divider { height: 1px; background: #ddd; margin: 10px 0; }
.print-title-row { display: flex; justify-content: space-between; align-items: center; }
.print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tfoot tr { background: #f0f0f0; }
.print-table tfoot td { padding: 10px 12px; border-top: 2px solid #A04657; color: #222; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-receipt-info { width: 100%; border-collapse: collapse; font-size: 13px; }
.print-receipt-info td { padding: 7px 10px; }
.print-receipt-info tr td:first-child { color: #777; width: 160px; }
.print-receipt-info .amount-cell { font-size: 20px; font-weight: 800; color: #A04657; }
.print-sign-row { display: flex; justify-content: space-between; margin-top: 46px; }
.print-sign-box { text-align: center; width: 200px; }
.print-sign-box .line { border-top: 1px solid #999; margin-top: 34px; padding-top: 6px; font-size: 12px; color: #555; }
.print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<?php include '../includes/footer.php'; ?>
