<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$supplier_filter = isset($_GET['supplier_filter']) ? intval($_GET['supplier_filter']) : 0;
$status_filter = isset($_GET['status_filter']) ? mysqli_real_escape_string($conn, $_GET['status_filter']) : '';

// Build query
$query = "SELECT p.*, s.supplier_name 
          FROM raw_material_purchases p 
          LEFT JOIN suppliers s ON p.supplier_id = s.id 
          WHERE 1=1";

if($search){
    $query .= " AND (s.supplier_name LIKE '%$search%' OR p.invoice_no LIKE '%$search%')";
}
if($supplier_filter > 0){
    $query .= " AND p.supplier_id = $supplier_filter";
}
if($status_filter){
    $query .= " AND p.payment_status = '$status_filter'";
}

$query .= " ORDER BY p.purchase_date DESC";
$result = mysqli_query($conn, $query);

// Get suppliers for filter
$suppliers_query = "SELECT id, supplier_name FROM suppliers WHERE status = 'Active' ORDER BY supplier_name";
$suppliers_result = mysqli_query($conn, $suppliers_query);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_purchases,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(credit_amount), 0) as total_credit,
                SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'Credit' THEN 1 ELSE 0 END) as credit_count,
                SUM(CASE WHEN payment_status = 'Partial' THEN 1 ELSE 0 END) as partial_count
                FROM raw_material_purchases";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Table Styles matching customers.php */
.purchase-table {
    font-size: 14px;
}
.purchase-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.purchase-table td {
    padding: 12px 10px;
    vertical-align: middle;
}
.purchase-table td:first-child,
.purchase-table th:first-child {
    text-align: center;
}
.action-buttons {
    white-space: nowrap;
}
.action-buttons .btn {
    margin: 0 2px;
    padding: 5px 8px;
}
.table-responsive {
    overflow-x: auto;
}
@media (max-width: 768px) {
    .purchase-table {
        font-size: 12px;
    }
    .purchase-table td, 
    .purchase-table th {
        padding: 8px 6px;
    }
}

/* Statistics Cards */
.stat-card {
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-card.primary { background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%); }
.stat-card.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.stat-card.warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
.stat-card.info { background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%); }
.stat-card h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}
.stat-card p {
    margin: 0;
    opacity: 0.9;
}
.stat-card i {
    font-size: 40px;
    opacity: 0.5;
}

/* Badge Styles */
.badge-paid {
    background-color: #28a745;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
}
.badge-credit {
    background-color: #dc3545;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
}
.badge-partial {
    background-color: #ffc107;
    color: #000;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
}

/* Filter Card */
.filter-card {
    border-radius: 20px;
    overflow: hidden;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-history me-2" style="color: #A04657;"></i> Purchase History
            </h2>
            <p class="text-muted mb-0">View all raw material purchases</p>
        </div>
        <div>
            <a href="raw_material_purchase.php" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus-circle me-2"></i> New Purchase
            </a>
        </div>
    </div>

    <!-- Statistics Cards Row -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card primary shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($stats['total_purchases'] ?? 0); ?></h3>
                        <p>Total Purchases</p>
                    </div>
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card success shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Rs <?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
                        <p>Total Paid</p>
                    </div>
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card warning shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Rs <?php echo number_format($stats['total_credit'] ?? 0, 2); ?></h3>
                        <p>Total Credit</p>
                    </div>
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card info shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Rs <?php echo number_format($stats['total_amount'] ?? 0, 2); ?></h3>
                        <p>Total Amount</p>
                    </div>
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Supplier name or invoice..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-truck me-1"></i> Supplier</label>
                    <select name="supplier_filter" class="form-select">
                        <option value="0">All Suppliers</option>
                        <?php 
                        mysqli_data_seek($suppliers_result, 0);
                        while($sup = mysqli_fetch_assoc($suppliers_result)): ?>
                            <option value="<?php echo $sup['id']; ?>" <?php echo $supplier_filter == $sup['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sup['supplier_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-credit-card me-1"></i> Payment Status</label>
                    <select name="status_filter" class="form-select">
                        <option value="">All</option>
                        <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Credit" <?php echo $status_filter == 'Credit' ? 'selected' : ''; ?>>Credit</option>
                        <option value="Partial" <?php echo $status_filter == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">
                        <i class="fas fa-search me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Purchases Table Card -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Purchase Records</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover purchase-table mb-0" id="purchasesTable">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:100px">Date</th>
                            <th style="min-width:100px">Invoice #</th>
                            <th style="min-width:160px">Supplier</th>
                            <th style="min-width:100px">Subtotal</th>
                            <th style="min-width:100px">Discount</th>
                            <th style="min-width:100px">Total</th>
                            <th style="min-width:100px">Paid</th>
                            <th style="min-width:100px">Credit</th>
                            <th style="min-width:90px">Status</th>
                            <th style="min-width:100px; text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $status_class = '';
                                $status_text = '';
                                if($row['payment_status'] == 'Paid'){
                                    $status_class = 'badge-paid';
                                    $status_text = 'Paid';
                                } elseif($row['payment_status'] == 'Credit'){
                                    $status_class = 'badge-credit';
                                    $status_text = 'Credit';
                                } else {
                                    $status_class = 'badge-partial';
                                    $status_text = 'Partial';
                                }
                            ?>
                                <tr>
                                    <td class="text-center fw-semibold"><?php echo $row['id']; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['purchase_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['invoice_no'] ?? 'N/A'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['supplier_name']); ?></strong></td>
                                    <td>Rs <?php echo number_format($row['subtotal'], 2); ?></td>
                                    <td>
                                        <?php if($row['discount_percent'] > 0): ?>
                                            <span class="text-warning"><?php echo $row['discount_percent']; ?>%</span>
                                        <?php endif; ?>
                                        <?php if($row['discount_amount'] > 0): ?>
                                            <br><small>(-Rs <?php echo number_format($row['discount_amount'], 2); ?>)</small>
                                        <?php endif; ?>
                                        <?php if($row['discount_percent'] == 0 && $row['discount_amount'] == 0): ?>-<?php endif; ?>
                                    </td>
                                    <td><strong>Rs <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                    <td>Rs <?php echo number_format($row['paid_amount'], 2); ?></td>
                                    <td class="text-danger">Rs <?php echo number_format($row['credit_amount'], 2); ?></td>
                                    <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td class="action-buttons text-center">
                                        <a href="purchase_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if($row['credit_amount'] > 0): ?>
                                            <a href="add_supplier_payment.php?purchase_id=<?php echo $row['id']; ?>&supplier_id=<?php echo $row['supplier_id']; ?>" class="btn btn-sm btn-outline-success" title="Make Payment">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                                    No purchase records found. Click <strong>"New Purchase"</strong> to get started.
                                  </td
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    <?php if(mysqli_num_rows($result) > 0): ?>
    $('#purchasesTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ purchases"
        }
    });
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>