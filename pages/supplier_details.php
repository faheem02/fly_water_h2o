<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: suppliers.php");
    exit();
}

$supplier_id = intval($_GET['id']);

// Fetch supplier details
$query = "SELECT * FROM suppliers WHERE id = $supplier_id";
$result = mysqli_query($conn, $query);
$supplier = mysqli_fetch_assoc($result);

if(!$supplier){
    header("Location: suppliers.php");
    exit();
}

// Fetch supplier ledger
$ledger_query = "SELECT * FROM supplier_ledger WHERE supplier_id = $supplier_id ORDER BY transaction_date ASC";
$ledger_result = mysqli_query($conn, $ledger_query);

// Fetch purchases
$purchase_query = "SELECT p.*, 
                   (SELECT COUNT(*) FROM raw_material_purchase_items WHERE purchase_id = p.id) as items_count
                   FROM raw_material_purchases p 
                   WHERE p.supplier_id = $supplier_id 
                   ORDER BY p.purchase_date DESC";
$purchase_result = mysqli_query($conn, $purchase_query);

// Fetch payments
$payment_query = "SELECT * FROM supplier_payments WHERE supplier_id = $supplier_id ORDER BY payment_datetime DESC";
$payment_result = mysqli_query($conn, $payment_query);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Styles */
.info-card {
    border-left: 4px solid;
    margin-bottom: 20px;
    border-radius: 12px;
    transition: transform 0.2s;
}
.info-card:hover {
    transform: translateY(-3px);
}
.info-card.primary { border-left-color: #A04657; }
.info-card.success { border-left-color: #28a745; }
.info-card.warning { border-left-color: #ffc107; }
.info-card.danger { border-left-color: #dc3545; }
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
    border-radius: 8px 8px 0 0;
}
.nav-tabs .nav-link.active {
    color: #A04657;
    font-weight: 600;
    border-bottom: 2px solid #A04657;
}
.tab-content {
    margin-top: 20px;
}
.table th {
    background-color: #A04657;
    color: white;
}
.badge-paid { background-color: #28a745; }
.badge-credit { background-color: #dc3545; }
.badge-partial { background-color: #ffc107; color: #000; }
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-truck me-2" style="color: #A04657;"></i> Supplier Details
            </h2>
            <p class="text-muted mb-0">Complete information about <?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="suppliers.php" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a href="add_supplier_payment.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-success rounded-pill px-4">
                <i class="fas fa-money-bill me-2"></i> Make Payment
            </a>
            <a href="edit_supplier.php?id=<?php echo $supplier_id; ?>" class="btn btn-warning rounded-pill px-4">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
        </div>
    </div>

    <!-- Supplier Info Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card info-card primary shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted"><i class="fas fa-building me-1"></i> Supplier Name</h6>
                    <h4 class="mb-0"><?php echo htmlspecialchars($supplier['supplier_name']); ?></h4>
                    <?php if($supplier['contact_person']): ?>
                        <small class="text-muted">Contact: <?php echo htmlspecialchars($supplier['contact_person']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card success shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted"><i class="fas fa-phone me-1"></i> Contact Info</h6>
                    <p class="mb-0"><i class="fas fa-mobile-alt me-1"></i> <?php echo htmlspecialchars($supplier['mobile']); ?></p>
                    <?php if($supplier['email']): ?>
                        <small><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($supplier['email']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card warning shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted"><i class="fas fa-credit-card me-1"></i> Current Balance</h6>
                    <h4 class="mb-0 <?php echo $supplier['current_balance'] >= 0 ? 'text-warning' : 'text-success'; ?>">
                        Rs <?php echo number_format(abs($supplier['current_balance']), 2); ?>
                    </h4>
                    <small><?php echo $supplier['current_balance'] >= 0 ? 'Credit (Payable to Supplier)' : 'Advance (Receivable from Supplier)'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card info-card danger shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted"><i class="fas fa-chart-line me-1"></i> Opening Balance</h6>
                    <h4 class="mb-0">Rs <?php echo number_format($supplier['opening_balance'], 2); ?></h4>
                    <small>Initial balance when added</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Address Row -->
    <?php if($supplier['address']): ?>
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <i class="fas fa-map-marker-alt me-2" style="color: #A04657;"></i> 
            <strong>Address:</strong> <?php echo nl2br(htmlspecialchars($supplier['address'])); ?>
            <?php if($supplier['ntn_no']): ?>
                | <i class="fas fa-hashtag me-1"></i> <strong>NTN:</strong> <?php echo htmlspecialchars($supplier['ntn_no']); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" type="button" role="tab">
                <i class="fas fa-book me-2"></i> Ledger
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                <i class="fas fa-shopping-cart me-2"></i> Purchase History
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                <i class="fas fa-money-bill-wave me-2"></i> Payment History
            </button>
        </li>
    </ul>
    
    <div class="tab-content mt-3">
        <!-- Ledger Tab -->
        <div class="tab-pane fade show active" id="ledger" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-book me-2" style="color: #A04657;"></i> Supplier Ledger</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="ledgerTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Debit (Payment)</th>
                                    <th>Credit (Purchase)</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $running_balance = 0;
                                $ledger_records = [];
                                while($row = mysqli_fetch_assoc($ledger_result)){
                                    $ledger_records[] = $row;
                                }
                                if(count($ledger_records) > 0):
                                    foreach($ledger_records as $row):
                                        $running_balance = $row['running_balance'];
                                ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y H:i', strtotime($row['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td class="text-danger"><?php echo $row['debit_amount'] > 0 ? 'Rs ' . number_format($row['debit_amount'], 2) : '-'; ?></td>
                                        <td class="text-success"><?php echo $row['credit_amount'] > 0 ? 'Rs ' . number_format($row['credit_amount'], 2) : '-'; ?></td>
                                        <td class="<?php echo $row['running_balance'] >= 0 ? 'text-warning' : 'text-success'; ?> fw-bold">
                                            Rs <?php echo number_format(abs($row['running_balance']), 2); ?>
                                            <small>(<?php echo $row['running_balance'] >= 0 ? 'Credit' : 'Advance'; ?>)</small>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-book fa-2x mb-2 d-block"></i>
                                            No ledger entries found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if(count($ledger_records) > 0): ?>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Current Balance:</th>
                                    <th class="<?php echo $running_balance >= 0 ? 'text-warning' : 'text-success'; ?>">
                                        Rs <?php echo number_format(abs($running_balance), 2); ?>
                                        (<?php echo $running_balance >= 0 ? 'Credit' : 'Advance'; ?>)
                                    </th>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Purchases Tab -->
        <div class="tab-pane fade" id="purchases" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2" style="color: #A04657;"></i> Purchase History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="purchasesTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice #</th>
                                    <th>Subtotal</th>
                                    <th>Discount</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Credit</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($purchase_result) > 0): ?>
                                    <?php while($purchase = mysqli_fetch_assoc($purchase_result)): ?>
                                        <tr>
                                            <td><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($purchase['invoice_no'] ?? 'N/A'); ?></td>
                                            <td>Rs <?php echo number_format($purchase['subtotal'], 2); ?></td>
                                            <td>
                                                <?php if($purchase['discount_percent'] > 0): ?>
                                                    <?php echo $purchase['discount_percent']; ?>% 
                                                <?php endif; ?>
                                                <?php if($purchase['discount_amount'] > 0): ?>
                                                    (Rs <?php echo number_format($purchase['discount_amount'], 2); ?>)
                                                <?php endif; ?>
                                                <?php if($purchase['discount_percent'] == 0 && $purchase['discount_amount'] == 0): ?>-<?php endif; ?>
                                            </td>
                                            <td><strong>Rs <?php echo number_format($purchase['total_amount'], 2); ?></strong></td>
                                            <td>Rs <?php echo number_format($purchase['paid_amount'], 2); ?></td>
                                            <td class="text-danger">Rs <?php echo number_format($purchase['credit_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                if($purchase['payment_status'] == 'Paid'):
                                                    echo '<span class="badge bg-success rounded-pill">Paid</span>';
                                                elseif($purchase['payment_status'] == 'Partial'):
                                                    echo '<span class="badge bg-warning rounded-pill">Partial</span>';
                                                else:
                                                    echo '<span class="badge bg-danger rounded-pill">Credit</span>';
                                                endif;
                                                ?>
                                            </td>
                                            <td>
                                                <a href="purchase_details.php?id=<?php echo $purchase['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                             </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                                            No purchase records found.
                                         </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payments Tab -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2" style="color: #A04657;"></i> Payment History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment Type</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($payment_result) > 0): ?>
                                    <?php while($payment = mysqli_fetch_assoc($payment_result)): ?>
                                        <tr>
                                            <td><?php echo date('d-m-Y H:i', strtotime($payment['payment_datetime'])); ?></td>
                                            <td class="text-success fw-bold">Rs <?php echo number_format($payment['payment_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $icon = $payment['payment_type'] == 'Cash' ? 'fa-money-bill' : ($payment['payment_type'] == 'Bank' ? 'fa-university' : 'fa-money-check');
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo $payment['payment_type']; ?>
                                             </td>
                                            <td><?php echo $payment['cheque_no'] ?? '-'; ?></td>
                                            <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-money-bill-wave fa-2x mb-2 d-block"></i>
                                            No payment records found.
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    <?php if(mysqli_num_rows($ledger_result) > 0): ?>
    $('#ledgerTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: "Search Ledger:" }
    });
    <?php endif; ?>
    
    <?php if(mysqli_num_rows($purchase_result) > 0): ?>
    $('#purchasesTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: "Search Purchases:" }
    });
    <?php endif; ?>
    
    <?php if(mysqli_num_rows($payment_result) > 0): ?>
    $('#paymentsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { search: "Search Payments:" }
    });
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>