<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : 'all';

// Build query with purchase and sale prices
$query = "SELECT m.*, 
          COALESCE((SELECT AVG(unit_price) FROM raw_material_purchase_items WHERE material_id = m.id), m.purchase_price) as avg_cost
          FROM raw_materials m 
          WHERE m.status = 'Active'";

if($search){
    $query .= " AND m.material_name LIKE '%$search%'";
}
if($stock_status == 'low'){
    $query .= " AND m.current_stock <= m.min_stock_level";
} elseif($stock_status == 'critical'){
    $query .= " AND m.current_stock <= m.min_stock_level * 0.5";
} elseif($stock_status == 'normal'){
    $query .= " AND m.current_stock > m.min_stock_level";
}

$query .= " ORDER BY (m.current_stock / NULLIF(m.min_stock_level, 0)) ASC";
$result = mysqli_query($conn, $query);

// Get summary statistics with purchase price value
$summary_query = "SELECT 
                    COUNT(*) as total_materials,
                    SUM(current_stock) as total_stock,
                    SUM(current_stock * purchase_price) as total_inventory_value,
                    SUM(CASE WHEN current_stock <= min_stock_level THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN current_stock <= min_stock_level * 0.5 THEN 1 ELSE 0 END) as critical_count
                  FROM raw_materials 
                  WHERE status = 'Active'";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Report Styles */
.report-card {
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    transition: transform 0.2s;
}
.report-card:hover {
    transform: translateY(-5px);
}
.report-card.primary { background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%); }
.report-card.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.report-card.warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
.report-card.info { background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%); }
.report-card h2 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}
.report-card p {
    margin: 0;
    opacity: 0.9;
}
.report-card i {
    font-size: 40px;
    opacity: 0.3;
    float: right;
}

.stock-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.stock-table td {
    padding: 12px 10px;
    vertical-align: middle;
}
.stock-critical {
    background-color: #f8d7da !important;
}
.stock-low {
    background-color: #fff3cd !important;
}
.stock-normal {
    background-color: #d1e7dd !important;
}
.progress {
    height: 6px;
    border-radius: 10px;
}
.price-purchase {
    color: #dc3545;
    font-weight: 500;
}
.price-sale {
    color: #28a745;
    font-weight: 500;
}
@media print {
    .no-print, .fixed-header, .fixed-sidebar, .btn, .footer {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-chart-bar me-2" style="color: #A04657;"></i> Raw Material Stock Report
            </h2>
            <p class="text-muted mb-0">Complete inventory status and stock analysis with pricing</p>
        </div>
        <div class="no-print">
            <button onclick="window.print()" class="btn btn-secondary rounded-pill px-4 me-2">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <button onclick="exportToExcel()" class="btn btn-success rounded-pill px-4">
                <i class="fas fa-file-excel me-2"></i> Export
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="report-card primary shadow-sm">
                <div>
                    <h2><?php echo number_format($summary['total_materials'] ?? 0); ?></h2>
                    <p>Total Materials</p>
                </div>
                <i class="fas fa-boxes"></i>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="report-card success shadow-sm">
                <div>
                    <h2><?php echo number_format($summary['total_stock'] ?? 0, 2); ?></h2>
                    <p>Total Stock Units</p>
                </div>
                <i class="fas fa-cubes"></i>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="report-card warning shadow-sm">
                <div>
                    <h2>Rs <?php echo number_format($summary['total_inventory_value'] ?? 0, 2); ?></h2>
                    <p>Inventory Value</p>
                </div>
                <i class="fas fa-rupee-sign"></i>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="report-card info shadow-sm">
                <div>
                    <h2><?php echo number_format($summary['low_stock_count'] ?? 0); ?></h2>
                    <p>Low Stock Items</p>
                </div>
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search Material</label>
                    <input type="text" name="search" class="form-control" placeholder="Material name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-chart-line me-1"></i> Stock Status</label>
                    <select name="stock_status" class="form-select">
                        <option value="all" <?php echo $stock_status == 'all' ? 'selected' : ''; ?>>All Items</option>
                        <option value="low" <?php echo $stock_status == 'low' ? 'selected' : ''; ?>>Low Stock (Below Min)</option>
                        <option value="critical" <?php echo $stock_status == 'critical' ? 'selected' : ''; ?>>Critical Stock</option>
                        <option value="normal" <?php echo $stock_status == 'normal' ? 'selected' : ''; ?>>Normal Stock</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 me-2">
                        <i class="fas fa-filter me-2"></i> Apply Filter
                    </button>
                    <a href="raw_material_stock_report.php" class="btn btn-secondary rounded-pill px-4">
                        <i class="fas fa-undo me-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Table Card -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Current Stock Status</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover stock-table mb-0" id="stockReportTable">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th style="min-width:160px">Material Name</th>
                            <th style="min-width:80px">Unit</th>
                            <th style="min-width:110px">Purchase Price</th>
                            <th style="min-width:110px">Sale Price</th>
                            <th style="min-width:100px">Opening Stock</th>
                            <th style="min-width:120px">Current Stock</th>
                            <th style="min-width:100px">Min Level</th>
                            <th style="min-width:100px">Stock Value</th>
                            <th style="min-width:100px">Status</th>
                            <th style="min-width:140px">Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        if(mysqli_num_rows($result) > 0):
                            while($row = mysqli_fetch_assoc($result)): 
                                $stock_percent = ($row['current_stock'] / max($row['min_stock_level'], 1)) * 100;
                                $stock_class = '';
                                $status_text = '';
                                $action_text = '';
                                $progress_color = '';
                                
                                if($row['current_stock'] <= $row['min_stock_level'] * 0.5){
                                    $stock_class = 'stock-critical';
                                    $status_text = '<span class="badge bg-danger rounded-pill">Critical</span>';
                                    $action_text = '🚨 URGENT: Reorder immediately!';
                                    $progress_color = 'bg-danger';
                                } elseif($row['current_stock'] <= $row['min_stock_level']){
                                    $stock_class = 'stock-low';
                                    $status_text = '<span class="badge bg-warning rounded-pill">Low</span>';
                                    $action_text = '⚠️ Plan to reorder soon';
                                    $progress_color = 'bg-warning';
                                } else {
                                    $stock_class = 'stock-normal';
                                    $status_text = '<span class="badge bg-success rounded-pill">Normal</span>';
                                    $action_text = '✓ Stock sufficient';
                                    $progress_color = 'bg-success';
                                }
                                
                                // Calculate stock value
                                $stock_value = $row['current_stock'] * $row['purchase_price'];
                                $margin = $row['sale_price'] - $row['purchase_price'];
                                $margin_percent = $row['purchase_price'] > 0 ? ($margin / $row['purchase_price']) * 100 : 0;
                        ?>
                            <tr class="<?php echo $stock_class; ?>">
                                <td class="text-center fw-semibold"><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['material_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td class="price-purchase">Rs <?php echo number_format($row['purchase_price'], 2); ?></td>
                                <td class="price-sale">
                                    Rs <?php echo number_format($row['sale_price'], 2); ?>
                                    <?php if($margin > 0): ?>
                                        <br><small class="text-muted">(+<?php echo round($margin_percent, 1); ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($row['opening_stock'], 2); ?></td>
                                <td>
                                    <strong><?php echo number_format($row['current_stock'], 2); ?></strong>
                                    <div class="progress mt-1">
                                        <div class="progress-bar <?php echo $progress_color; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min($stock_percent, 100); ?>%;">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo round($stock_percent, 1); ?>% of min level</small>
                                </td>
                                <td><?php echo number_format($row['min_stock_level'], 2); ?></td>
                                <td class="text-info fw-bold">Rs <?php echo number_format($stock_value, 2); ?></td>
                                <td><?php echo $status_text; ?></td>
                                <td><?php echo $action_text; ?></td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fas fa-boxes fa-3x mb-3 d-block"></i>
                                    No materials found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="8" class="text-end">Total Inventory Value:</th>
                            <th colspan="3">
                                <?php 
                                $total_value_query = "SELECT SUM(current_stock * purchase_price) as total FROM raw_materials WHERE status = 'Active'";
                                $total_value_result = mysqli_query($conn, $total_value_query);
                                $total_value_row = mysqli_fetch_assoc($total_value_result);
                                echo 'Rs ' . number_format($total_value_row['total'] ?? 0, 2);
                                ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Reorder Suggestions Card -->
    <div class="card shadow-sm border-0 rounded-4 mt-4 no-print">
        <div class="card-header bg-warning border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Reorder Suggestions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Material</th>
                            <th>Current Stock</th>
                            <th>Min Level</th>
                            <th>Purchase Price</th>
                            <th>Recommended Qty</th>
                            <th>Est. Cost</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $reorder_query = "SELECT * FROM raw_materials 
                                         WHERE current_stock <= min_stock_level 
                                         AND status = 'Active'
                                         ORDER BY (current_stock / min_stock_level) ASC";
                        $reorder_result = mysqli_query($conn, $reorder_query);
                        
                        if(mysqli_num_rows($reorder_result) == 0){
                            echo '<tr><td colspan="7" class="text-center py-4 text-success">
                                    <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                                    ✓ All stock levels are healthy! No reorder needed.
                                  </td></tr>';
                        }
                        
                        while($reorder = mysqli_fetch_assoc($reorder_result)):
                            $recommended = ($reorder['min_stock_level'] * 2) - $reorder['current_stock'];
                            $recommended = max($recommended, $reorder['min_stock_level']);
                            $estimated_cost = $recommended * $reorder['purchase_price'];
                            $priority = $reorder['current_stock'] <= $reorder['min_stock_level'] * 0.5 ? 'High' : 'Medium';
                            $priority_class = $priority == 'High' ? 'text-danger fw-bold' : 'text-warning fw-bold';
                            $priority_icon = $priority == 'High' ? '🔴' : '🟡';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($reorder['material_name']); ?></strong></td>
                                <td class="text-danger"><?php echo number_format($reorder['current_stock'], 2); ?> <?php echo $reorder['unit']; ?></td>
                                <td><?php echo number_format($reorder['min_stock_level'], 2); ?> <?php echo $reorder['unit']; ?></td>
                                <td>Rs <?php echo number_format($reorder['purchase_price'], 2); ?></td>
                                <td class="text-info fw-bold"><?php echo number_format($recommended, 2); ?> <?php echo $reorder['unit']; ?></td>
                                <td>Rs <?php echo number_format($estimated_cost, 2); ?></td>
                                <td class="<?php echo $priority_class; ?>"><?php echo $priority_icon; ?> <?php echo $priority; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="row g-4 mt-2 no-print">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2" style="color: #A04657;"></i> Stock Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center p-3">
                                <h3 class="text-success"><?php 
                                    $normal_count = $summary['total_materials'] - $summary['low_stock_count'];
                                    echo number_format($normal_count);
                                ?></h3>
                                <p class="text-muted mb-0">Normal Stock</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3">
                                <h3 class="text-warning"><?php 
                                    $critical_count = $summary['critical_count'] ?? 0;
                                    $low_only = $summary['low_stock_count'] - $critical_count;
                                    echo number_format($low_only);
                                ?></h3>
                                <p class="text-muted mb-0">Low Stock (Not Critical)</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3">
                                <h3 class="text-danger"><?php echo number_format($summary['critical_count'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Critical Stock</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3">
                                <h3 class="text-info"><?php 
                                    $total_value = $summary['total_inventory_value'] ?? 0;
                                    echo 'Rs ' . number_format($total_value, 2);
                                ?></h3>
                                <p class="text-muted mb-0">Inventory Value</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2" style="color: #A04657;"></i> Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex justify-content-between align-items-center p-2">
                                <span>Total Materials:</span>
                                <strong><?php echo number_format($summary['total_materials'] ?? 0); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2">
                                <span>Total Stock Units:</span>
                                <strong><?php echo number_format($summary['total_stock'] ?? 0, 2); ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between align-items-center p-2">
                                <span>Low Stock %:</span>
                                <strong class="text-warning">
                                    <?php 
                                    $low_percent = ($summary['low_stock_count'] / max($summary['total_materials'], 1)) * 100;
                                    echo round($low_percent, 1) . '%';
                                    ?>
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2">
                                <span>Critical %:</span>
                                <strong class="text-danger">
                                    <?php 
                                    $critical_percent = ($summary['critical_count'] / max($summary['total_materials'], 1)) * 100;
                                    echo round($critical_percent, 1) . '%';
                                    ?>
                                </strong>
                            </div>
                        </div>
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
    <?php if(mysqli_num_rows($result) > 0): ?>
    $('#stockReportTable').DataTable({
        pageLength: 25,
        order: [[6, 'asc']], // Sort by current stock ascending (lowest first)
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ materials"
        }
    });
    <?php endif; ?>
});

function exportToExcel() {
    // Simple export - redirect to same page with export parameter
    window.location.href = 'raw_material_stock_report.php?export=excel&' + window.location.search.substring(1);
}
</script>

<?php include '../includes/footer.php'; ?>