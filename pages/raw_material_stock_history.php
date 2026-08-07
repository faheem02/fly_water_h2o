<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: raw_material_stock.php");
    exit();
}

$material_id = intval($_GET['id']);

// Fetch material details
$material_query = "SELECT * FROM raw_materials WHERE id = $material_id";
$material_result = mysqli_query($conn, $material_query);
$material = mysqli_fetch_assoc($material_result);

if(!$material){
    header("Location: raw_material_stock.php");
    exit();
}

// Fetch stock ledger history
$ledger_query = "SELECT * FROM raw_material_stock_ledger 
                 WHERE material_id = $material_id 
                 ORDER BY transaction_date DESC";
$ledger_result = mysqli_query($conn, $ledger_query);

// Get summary statistics
$summary_query = "SELECT 
                    SUM(quantity_in) as total_in,
                    SUM(quantity_out) as total_out,
                    COUNT(*) as total_transactions
                  FROM raw_material_stock_ledger 
                  WHERE material_id = $material_id";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Table Styles */
.history-table {
    font-size: 14px;
}
.history-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.history-table td {
    padding: 12px 10px;
    vertical-align: middle;
}
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
.badge-in {
    background-color: #28a745;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-out {
    background-color: #dc3545;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-adjustment {
    background-color: #ffc107;
    color: #000;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-opening {
    background-color: #17a2b8;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-purchase {
    background-color: #6f42c1;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
.badge-issuance {
    background-color: #fd7e14;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-history me-2" style="color: #A04657;"></i> Stock History
            </h2>
            <p class="text-muted mb-0">
                Complete transaction history for: <strong><?php echo htmlspecialchars($material['material_name']); ?></strong>
                <br><small>Unit: <?php echo htmlspecialchars($material['unit']); ?> | Current Stock: <?php echo number_format($material['current_stock'], 2); ?></small>
            </p>
        </div>
        <div>
            <a href="raw_material_stock.php" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back to Stock
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card primary shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($summary['total_transactions'] ?? 0); ?></h3>
                        <p>Total Transactions</p>
                    </div>
                    <i class="fas fa-exchange-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card success shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($summary['total_in'] ?? 0, 2); ?></h3>
                        <p>Total Stock In</p>
                    </div>
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card warning shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($summary['total_out'] ?? 0, 2); ?></h3>
                        <p>Total Stock Out</p>
                    </div>
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card info shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($material['current_stock'], 2); ?></h3>
                        <p>Current Stock</p>
                    </div>
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Material Info Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <small class="text-muted">Material Name</small>
                    <h6 class="mb-0"><?php echo htmlspecialchars($material['material_name']); ?></h6>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Unit</small>
                    <h6 class="mb-0"><?php echo htmlspecialchars($material['unit']); ?></h6>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Purchase Price</small>
                    <h6 class="mb-0 text-danger">Rs <?php echo number_format($material['purchase_price'], 2); ?></h6>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Sale Price</small>
                    <h6 class="mb-0 text-success">Rs <?php echo number_format($material['sale_price'], 2); ?></h6>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Opening Stock</small>
                    <h6 class="mb-0"><?php echo number_format($material['opening_stock'], 2); ?> <?php echo $material['unit']; ?></h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock History Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover history-table mb-0" id="historyTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Transaction Type</th>
                            <th>Quantity In</th>
                            <th>Quantity Out</th>
                            <th>Running Stock</th>
                            <th>Description</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($ledger_result) > 0): ?>
                            <?php 
                            $counter = 1;
                            while($row = mysqli_fetch_assoc($ledger_result)): 
                                $badge_class = '';
                                $type_icon = '';
                                
                                switch($row['transaction_type']){
                                    case 'OPENING':
                                        $badge_class = 'badge-opening';
                                        $type_icon = 'fa-plus-circle';
                                        break;
                                    case 'PURCHASE':
                                        $badge_class = 'badge-purchase';
                                        $type_icon = 'fa-shopping-cart';
                                        break;
                                    case 'ISSUANCE':
                                        $badge_class = 'badge-issuance';
                                        $type_icon = 'fa-sign-out-alt';
                                        break;
                                    case 'ADJUSTMENT':
                                        $badge_class = 'badge-adjustment';
                                        $type_icon = 'fa-edit';
                                        break;
                                    default:
                                        $badge_class = 'badge-adjustment';
                                        $type_icon = 'fa-exchange-alt';
                                }
                            ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo date('d-m-Y H:i:s', strtotime($row['transaction_date'])); ?></td>
                                    <td>
                                        <span class="<?php echo $badge_class; ?>">
                                            <i class="fas <?php echo $type_icon; ?> me-1"></i>
                                            <?php echo $row['transaction_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($row['quantity_in'] > 0): ?>
                                            <span class="text-success fw-bold">
                                                <i class="fas fa-plus-circle me-1"></i>
                                                <?php echo number_format($row['quantity_in'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($row['quantity_out'] > 0): ?>
                                            <span class="text-danger fw-bold">
                                                <i class="fas fa-minus-circle me-1"></i>
                                                <?php echo number_format($row['quantity_out'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?php echo number_format($row['running_stock'], 2); ?> <?php echo $material['unit']; ?></td>
                                    <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php if($row['reference_type'] && $row['reference_id']): ?>
                                            <small class="text-muted">
                                                <?php echo ucfirst($row['reference_type']); ?> #<?php echo $row['reference_id']; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-history fa-3x mb-3 d-block"></i>
                                    No transaction history found for this material.
                                 </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Movement Chart Section (Optional) -->
    <div class="card shadow-sm border-0 rounded-4 mt-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2" style="color: #A04657;"></i> Stock Movement Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Opening Stock:</strong> <?php echo number_format($material['opening_stock'], 2); ?> <?php echo $material['unit']; ?><br>
                        <strong>Total Stock In:</strong> <?php echo number_format($summary['total_in'] ?? 0, 2); ?> <?php echo $material['unit']; ?><br>
                        <strong>Total Stock Out:</strong> <?php echo number_format($summary['total_out'] ?? 0, 2); ?> <?php echo $material['unit']; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <i class="fas fa-calculator me-2"></i>
                        <strong>Calculation:</strong><br>
                        Opening + In - Out = Current Stock<br>
                        <?php echo number_format($material['opening_stock'], 2); ?> + 
                        <?php echo number_format($summary['total_in'] ?? 0, 2); ?> - 
                        <?php echo number_format($summary['total_out'] ?? 0, 2); ?> = 
                        <strong><?php echo number_format($material['current_stock'], 2); ?></strong> <?php echo $material['unit']; ?>
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
    $('#historyTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search History:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ transactions"
        }
    });
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>