<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $customer_id = intval($_POST['customer_id']);
    $amount = floatval($_POST['payment_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $datetime = date('Y-m-d H:i:s');

    // Get customer details
    $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT outstanding_balance, customer_name FROM customers WHERE id=$customer_id"));
    
    if($cust) {
        if($amount > $cust['outstanding_balance']) {
            $error = "Payment amount (Rs " . number_format($amount, 2) . ") exceeds outstanding balance (Rs " . number_format($cust['outstanding_balance'], 2) . ")!";
        } else {
            $new_outstanding = $cust['outstanding_balance'] - $amount;
            mysqli_query($conn, "UPDATE customers SET outstanding_balance = $new_outstanding WHERE id=$customer_id");
            
            // Insert payment record
            $payment_query = "INSERT INTO customer_payments (customer_id, payment_amount, payment_type, notes, payment_datetime) 
                              VALUES ($customer_id, $amount, 'Cash', '$notes', '$datetime')";
            mysqli_query($conn, $payment_query);
            $payment_id = mysqli_insert_id($conn);
            
            // Ledger: Credit
            $running = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_balance FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id DESC LIMIT 1"))['running_balance'] ?? 0;
            $new_balance = $running - $amount;
            $desc = "Payment Received (Cash)";
            mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                 VALUES ($customer_id, '$datetime', '$desc', 0, $amount, $new_balance, $payment_id, 'payment')");
            
            $success = "Payment of Rs " . number_format($amount, 2) . " received from " . htmlspecialchars($cust['customer_name']) . "!";
        }
    } else {
        $error = "Customer not found!";
    }
}

// Get customer list with outstanding balance
$customers = mysqli_query($conn, "SELECT id, customer_name, mobile, outstanding_balance FROM customers WHERE status='Active' ORDER BY customer_name");

// Get recent payments
$payments = mysqli_query($conn, "SELECT p.*, c.customer_name, c.mobile FROM customer_payments p JOIN customers c ON p.customer_id = c.id ORDER BY p.payment_datetime DESC LIMIT 100");

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
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-money-bill-wave me-2" style="color: #A04657;"></i> Customer Payments
        </h2>
        <div class="d-flex gap-2">
            <div class="summary-card today">
                <small>Today's Collection</small>
                <h4 class="mb-0">Rs <?php echo number_format($today_total['total'], 2); ?></h4>
                <small><?php echo $today_total['count']; ?> payments</small>
            </div>
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

    <div class="row g-4">
        <!-- Payment Form Column -->
        <div class="col-lg-5">
            <div class="card payment-card">
                <div class="card-header">
                    <i class="fas fa-receipt me-2"></i> Receive Payment
                </div>
                <div class="card-body p-4">
                    <form method="POST" class="payment-form" id="paymentForm">
                        <!-- Customer Selection with Search -->
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-user me-1"></i> Select Customer <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="customerSearch" class="form-control" placeholder="Search customer by name or mobile..." autocomplete="off">
                            </div>
                            <select name="customer_id" id="customerId" class="form-select mt-2" required style="display:none;">
                                <option value="">-- Select Customer --</option>
                                <?php while($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-mobile="<?php echo $c['mobile']; ?>" data-outstanding="<?php echo $c['outstanding_balance']; ?>">
                                        <?php echo htmlspecialchars($c['customer_name']); ?> - <?php echo $c['mobile']; ?>
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

        <!-- Payment History Column -->
        <div class="col-lg-7">
            <div class="card payment-card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i> Recent Payments
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table payment-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Customer</th>
                                    <th>Mobile</th>
                                    <th>Amount (Rs)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($payments) > 0): ?>
                                    <?php while($p = mysqli_fetch_assoc($payments)): ?>
                                        <tr>
                                            <td><i class="far fa-calendar-alt me-1 text-muted"></i> <?php echo date('d/m/y h:i A', strtotime($p['payment_datetime'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($p['customer_name']); ?></strong></td>
                                            <td><?php echo $p['mobile']; ?></td>
                                            <td class="fw-bold text-success">Rs <?php echo number_format($p['payment_amount'], 2); ?></td>
                                            <td><small class="text-muted"><?php echo $p['notes'] ?: '—'; ?></small></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
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
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Customer data storage
let customers = [];

<?php 
$customers_array = [];
$cust_reset = mysqli_query($conn, "SELECT id, customer_name, mobile, outstanding_balance FROM customers WHERE status='Active' ORDER BY customer_name");
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
        c.mobile.includes(searchTerm)
    );
    
    if(filtered.length > 0) {
        suggestionsDiv.innerHTML = filtered.map(c => `
            <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${c.id}, '${escapeHtml(c.customer_name)}', '${c.mobile}', ${c.outstanding_balance}); return false;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${escapeHtml(c.customer_name)}</strong>
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

function selectCustomer(id, name, mobile, outstanding) {
    customerSelect.value = id;
    customerSearch.value = name;
    selectedCustomerName.innerText = name;
    selectedCustomerMobile.innerText = mobile;
    selectedOutstanding.innerText = parseFloat(outstanding).toFixed(2);
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
            amountHint.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> Amount exceeds outstanding balance! Maximum: Rs ' + currentOutstanding.toFixed(2);
            submitBtn.disabled = true;
        } else {
            amountHint.innerHTML = '<i class="fas fa-check-circle text-success"></i> Valid amount';
            submitBtn.disabled = false;
        }
    } else {
        amountHint.innerHTML = '';
        submitBtn.disabled = true;
    }
}

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
</script>

<?php include '../includes/footer.php'; ?>