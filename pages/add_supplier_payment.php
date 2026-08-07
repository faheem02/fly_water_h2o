<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';
$error = '';
$success = '';

// Get supplier and purchase info
$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;

if($supplier_id > 0){
    $supplier_query = "SELECT * FROM suppliers WHERE id = $supplier_id";
    $supplier_result = mysqli_query($conn, $supplier_query);
    $supplier = mysqli_fetch_assoc($supplier_result);
    
    if(!$supplier){
        header("Location: suppliers.php");
        exit();
    }
}

// Get pending credit purchases if no specific purchase selected
if($purchase_id == 0 && $supplier_id > 0){
    $credit_query = "SELECT * FROM raw_material_purchases 
                    WHERE supplier_id = $supplier_id AND credit_amount > 0 
                    ORDER BY purchase_date ASC";
    $credit_result = mysqli_query($conn, $credit_query);
}

// Handle payment submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_payment'])){
    $supplier_id = intval($_POST['supplier_id']);
    $purchase_id = !empty($_POST['purchase_id']) ? intval($_POST['purchase_id']) : null;
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_type = mysqli_real_escape_string($conn, $_POST['payment_type']);
    $cheque_no = mysqli_real_escape_string($conn, $_POST['cheque_no']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    if($payment_amount <= 0){
        $error = "Payment amount must be greater than zero!";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert payment record
            $payment_query = "INSERT INTO supplier_payments 
                            (supplier_id, purchase_id, payment_amount, payment_type, cheque_no, notes, payment_datetime, created_datetime) 
                            VALUES 
                            ($supplier_id, " . ($purchase_id ? $purchase_id : "NULL") . ", $payment_amount, '$payment_type', '$cheque_no', '$notes', NOW(), NOW())";
            
            if(!mysqli_query($conn, $payment_query)){
                throw new Exception("Error saving payment: " . mysqli_error($conn));
            }
            
            // Update purchase record if linked
            if($purchase_id){
                $update_purchase = "UPDATE raw_material_purchases 
                                   SET paid_amount = paid_amount + $payment_amount,
                                       credit_amount = credit_amount - $payment_amount,
                                       payment_status = CASE 
                                           WHEN (paid_amount + $payment_amount) >= total_amount THEN 'Paid'
                                           WHEN (paid_amount + $payment_amount) > 0 THEN 'Partial'
                                           ELSE 'Credit'
                                       END
                                   WHERE id = $purchase_id";
                mysqli_query($conn, $update_purchase);
            }
            
            // Update supplier balance
            $supplier_balance_query = "SELECT current_balance FROM suppliers WHERE id = $supplier_id";
            $balance_result = mysqli_query($conn, $supplier_balance_query);
            $balance_row = mysqli_fetch_assoc($balance_result);
            $old_balance = $balance_row['current_balance'];
            $new_balance = $old_balance - $payment_amount;
            
            $update_supplier = "UPDATE suppliers SET current_balance = $new_balance WHERE id = $supplier_id";
            if(!mysqli_query($conn, $update_supplier)){
                throw new Exception("Error updating supplier balance: " . mysqli_error($conn));
            }
            
            // Add to supplier ledger (Debit for payment)
            $ledger_desc = $purchase_id ? "Payment against Purchase #$purchase_id" : "Supplier Payment";
            $ledger_query = "INSERT INTO supplier_ledger 
                            (supplier_id, transaction_date, description, debit_amount, running_balance, reference_id, reference_type) 
                            VALUES 
                            ($supplier_id, NOW(), '$ledger_desc', $payment_amount, $new_balance, " . ($purchase_id ? $purchase_id : "NULL") . ", 'payment')";
            
            if(!mysqli_query($conn, $ledger_query)){
                throw new Exception("Error updating supplier ledger: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $message = "<div class='alert alert-success'>Payment recorded successfully! Redirecting...</div>";
            echo "<script>setTimeout(function(){ window.location.href = 'supplier_details.php?id=$supplier_id'; }, 1500);</script>";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "<div class='alert alert-danger'>" . $e->getMessage() . "</div>";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Form Styles */
.form-card {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: none;
}
.form-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    padding: 20px 25px;
    color: white;
}
.form-header h4 {
    margin: 0;
    font-weight: 600;
}
.form-header p {
    margin: 5px 0 0;
    opacity: 0.9;
    font-size: 14px;
}
.form-body {
    padding: 30px;
}
.info-card {
    border-radius: 16px;
    overflow: hidden;
}
.info-card .card-header {
    background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%);
    color: white;
    padding: 15px 20px;
}
.balance-positive {
    color: #ffc107;
    font-weight: bold;
}
.balance-negative {
    color: #28a745;
    font-weight: bold;
}
.required-field::after {
    content: "*";
    color: #dc3545;
    margin-left: 4px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-money-bill-wave me-2" style="color: #28a745;"></i> Record Supplier Payment
            </h2>
            <p class="text-muted mb-0">
                Pay to: <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
            </p>
        </div>
        <div>
            <a href="supplier_details.php?id=<?php echo $supplier_id; ?>" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back to Supplier
            </a>
        </div>
    </div>

    <?php echo $message; ?>
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Supplier Info Card -->
    <div class="card info-card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Supplier Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted">Supplier Name</small>
                    <h6 class="mb-0"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h6>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Mobile Number</small>
                    <h6 class="mb-0"><?php echo htmlspecialchars($supplier['mobile']); ?></h6>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Current Balance</small>
                    <h6 class="mb-0 <?php echo $supplier['current_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                        Rs <?php echo number_format(abs($supplier['current_balance']), 2); ?>
                        <small>(<?php echo $supplier['current_balance'] >= 0 ? 'Credit - Payable' : 'Advance - Receivable'; ?>)</small>
                    </h6>
                </div>
            </div>
            <?php if($supplier['address']): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <small class="text-muted">Address</small>
                    <p class="mb-0"><?php echo htmlspecialchars($supplier['address']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Form Card -->
    <div class="card form-card shadow-sm">
        <div class="form-header">
            <h4><i class="fas fa-credit-card me-2"></i> Payment Details</h4>
            <p>Enter payment information</p>
        </div>
        <div class="form-body">
            <form method="POST">
                <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
                <input type="hidden" name="purchase_id" value="<?php echo $purchase_id; ?>">
                
                <div class="row g-4">
                    <?php if($purchase_id == 0 && isset($credit_result) && mysqli_num_rows($credit_result) > 0): ?>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Select Purchase (Optional)</label>
                        <select name="purchase_id" class="form-select">
                            <option value="">No specific purchase (General Payment)</option>
                            <?php while($credit = mysqli_fetch_assoc($credit_result)): ?>
                                <option value="<?php echo $credit['id']; ?>">
                                    Purchase #<?php echo $credit['id']; ?> - 
                                    Date: <?php echo date('d-m-Y', strtotime($credit['purchase_date'])); ?> - 
                                    Credit: Rs <?php echo number_format($credit['credit_amount'], 2); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Select a specific purchase to apply this payment against</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold required-field">Payment Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="payment_amount" class="form-control" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Type</label>
                        <select name="payment_type" class="form-select">
                            <option value="Cash">💰 Cash</option>
                            <option value="Bank">🏦 Bank Transfer</option>
                            <option value="Cheque">📝 Cheque</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cheque / Reference #</label>
                        <input type="text" name="cheque_no" class="form-control" placeholder="Optional for cheque/bank transfer">
                        <small class="text-muted">Enter cheque number or transaction reference</small>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Any additional information about this payment..."></textarea>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <hr>
                        <div class="d-flex gap-3">
                            <button type="submit" name="save_payment" class="btn btn-success rounded-pill px-5 py-2">
                                <i class="fas fa-save me-2"></i> Record Payment
                            </button>
                            <button type="reset" class="btn btn-secondary rounded-pill px-5 py-2">
                                <i class="fas fa-undo me-2"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Recent Payments -->
    <?php
    $recent_query = "SELECT * FROM supplier_payments WHERE supplier_id = $supplier_id ORDER BY payment_datetime DESC LIMIT 10";
    $recent_result = mysqli_query($conn, $recent_query);
    if(mysqli_num_rows($recent_result) > 0):
    ?>
    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-history me-2" style="color: #A04657;"></i> Recent Payments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Purchase #</th>
                            <th>Reference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($recent = mysqli_fetch_assoc($recent_result)): ?>
                        <tr>
                            <td><?php echo date('d-m-Y h:i A', strtotime($recent['payment_datetime'])); ?></td>
                            <td class="text-success fw-bold">Rs <?php echo number_format($recent['payment_amount'], 2); ?></td>
                            <td>
                                <?php 
                                $icon = $recent['payment_type'] == 'Cash' ? '💰' : ($recent['payment_type'] == 'Bank' ? '🏦' : '📝');
                                echo $icon . ' ' . $recent['payment_type'];
                                ?>
                            </td>
                            <td><?php echo $recent['purchase_id'] ? '<a href="purchase_details.php?id=' . $recent['purchase_id'] . '">#' . $recent['purchase_id'] . '</a>' : '-'; ?></td>
                            <td><?php echo $recent['cheque_no'] ?? '-'; ?></td>
                            <td><?php echo htmlspecialchars($recent['notes'] ?? '-'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>