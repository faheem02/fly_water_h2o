<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: purchase_list.php");
    exit();
}

$purchase_id = intval($_GET['id']);

// Fetch purchase details
$query = "SELECT p.*, s.supplier_name, s.mobile, s.address, s.email 
          FROM raw_material_purchases p 
          LEFT JOIN suppliers s ON p.supplier_id = s.id 
          WHERE p.id = $purchase_id";
$result = mysqli_query($conn, $query);
$purchase = mysqli_fetch_assoc($result);

if(!$purchase){
    header("Location: purchase_list.php");
    exit();
}

// Fetch purchase items
$items_query = "SELECT i.*, m.material_name, m.unit, m.purchase_price, m.sale_price
                FROM raw_material_purchase_items i 
                LEFT JOIN raw_materials m ON i.material_id = m.id 
                WHERE i.purchase_id = $purchase_id";
$items_result = mysqli_query($conn, $items_query);

// Fetch payment records
$payments_query = "SELECT * FROM supplier_payments WHERE purchase_id = $purchase_id ORDER BY payment_datetime DESC";
$payments_result = mysqli_query($conn, $payments_query);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Invoice Styles */
.invoice-wrapper {
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
    padding: 20px 0;
}
.invoice-box {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}
.invoice-header {
    border-bottom: 3px solid #A04657;
    margin-bottom: 30px;
    padding-bottom: 20px;
}
.company-name {
    color: #A04657;
    font-weight: 700;
    margin-bottom: 5px;
}
.status-badge {
    font-size: 14px;
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
}
.status-paid { background: #28a745; color: white; }
.status-credit { background: #dc3545; color: white; }
.status-partial { background: #ffc107; color: #000; }
.info-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 5px;
}
.info-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.discount-row {
    background-color: #fff3cd;
}
.total-row {
    background-color: #A04657;
    color: white;
    font-weight: bold;
}
.payment-table th {
    background-color: #e9ecef;
}
@media print {
    .no-print, .fixed-header, .fixed-sidebar, .btn, .footer {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .invoice-box {
        box-shadow: none;
        padding: 20px;
    }
    .invoice-wrapper {
        background: white;
        padding: 0;
    }
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header - No Print -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-file-invoice me-2" style="color: #A04657;"></i> Purchase Details
            </h2>
            <p class="text-muted mb-0">
                Invoice #<?php echo htmlspecialchars($purchase['invoice_no'] ?? 'N/A'); ?>
            </p>
        </div>
         <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <a href="purchase_list.php" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <?php if($purchase['credit_amount'] > 0): ?>
                <a href="add_supplier_payment.php?purchase_id=<?php echo $purchase_id; ?>&supplier_id=<?php echo $purchase['supplier_id']; ?>" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-money-bill me-2"></i> Make Payment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Box -->
    <div class="invoice-wrapper">
        <div class="invoice-box">
            
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <h2 class="company-name"><?php echo isset($company_name) ? $company_name : 'Water Supply System'; ?></h2>
                        <p class="text-muted mb-0">Raw Material Purchase Invoice</p>
                        <p class="text-muted small mt-2">
                            123 Business Street, City<br>
                            Phone: +92 XXX XXXXXXX<br>
                            Email: info@company.com
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h4 class="text-primary">Purchase #<?php echo $purchase_id; ?></h4>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('d-m-Y h:i A', strtotime($purchase['purchase_date'])); ?></p>
                        <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($purchase['invoice_no'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="text-primary mb-3"><i class="fas fa-truck me-2"></i> Supplier Information</h5>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($purchase['supplier_name']); ?></strong></p>
                            <p class="mb-1"><i class="fas fa-mobile-alt me-2"></i> <?php echo htmlspecialchars($purchase['mobile']); ?></p>
                            <?php if($purchase['email']): ?>
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($purchase['email']); ?></p>
                            <?php endif; ?>
                            <?php if($purchase['address']): ?>
                                <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i> <?php echo nl2br(htmlspecialchars($purchase['address'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="text-primary mb-3"><i class="fas fa-credit-card me-2"></i> Payment Status</h5>
                            <div class="text-center">
                                <span class="status-badge status-<?php echo strtolower($purchase['payment_status']); ?>">
                                    <?php echo $purchase['payment_status']; ?>
                                </span>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="info-label">Total Amount</div>
                                    <div class="info-value">Rs <?php echo number_format($purchase['total_amount'], 2); ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label">Paid Amount</div>
                                    <div class="info-value text-success">Rs <?php echo number_format($purchase['paid_amount'], 2); ?></div>
                                </div>
                                <div class="col-6 mt-2">
                                    <div class="info-label">Credit Amount</div>
                                    <div class="info-value text-danger">Rs <?php echo number_format($purchase['credit_amount'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <h5 class="mb-3"><i class="fas fa-boxes me-2" style="color: #A04657;"></i> Purchase Items</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Material</th>
                            <th style="width:120px">Quantity</th>
                            <th style="width:150px">Unit Price (Rs)</th>
                            <th style="width:150px">Total (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        if(mysqli_num_rows($items_result) > 0):
                            while($item = mysqli_fetch_assoc($items_result)): 
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['material_name']); ?></strong>
                                    <br><small class="text-muted">Unit: <?php echo $item['unit']; ?></small>
                                </td>
                                <td><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                                <td>Rs <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>Rs <?php echo number_format($item['total_price'], 2); ?></td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr><td colspan="5" class="text-center">No items found</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Subtotal:</th>
                            <th>Rs <?php echo number_format($purchase['subtotal'], 2); ?></th>
                        </tr>
                        <?php if($purchase['discount_percent'] > 0): ?>
                        <tr>
                            <th colspan="4" class="text-end">Discount (<?php echo $purchase['discount_percent']; ?>%):</th>
                            <th>- Rs <?php echo number_format(($purchase['subtotal'] * $purchase['discount_percent'] / 100), 2); ?></th>
                        </tr>
                        <?php endif; ?>
                        <?php if($purchase['discount_amount'] > 0): ?>
                        <tr>
                            <th colspan="4" class="text-end">Discount Amount:</th>
                            <th>- Rs <?php echo number_format($purchase['discount_amount'], 2); ?></th>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <th colspan="4" class="text-end">Total Amount:</th>
                            <th>Rs <?php echo number_format($purchase['total_amount'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Payment History -->
            <?php if(mysqli_num_rows($payments_result) > 0): ?>
            <div class="mt-4">
                <h5 class="mb-3"><i class="fas fa-history me-2" style="color: #A04657;"></i> Payment History</h5>
                <div class="table-responsive">
                    <table class="table table-sm payment-table">
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
                            <?php while($payment = mysqli_fetch_assoc($payments_result)): ?>
                            <tr>
                                <td><?php echo date('d-m-Y h:i A', strtotime($payment['payment_datetime'])); ?></td>
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
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notes -->
            <?php if($purchase['notes']): ?>
            <div class="mt-4">
                <div class="alert alert-info">
                    <i class="fas fa-sticky-note me-2"></i>
                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($purchase['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Footer Note for Print -->
            <div class="mt-5 text-center text-muted small">
                <hr>
                <p>Thank you for your business!</p>
                <p>This is a computer generated invoice and does not require signature.</p>
            </div>
            
        </div>
    </div>
    
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>