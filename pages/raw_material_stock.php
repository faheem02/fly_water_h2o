<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';

// Handle opening stock entry
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_opening_stock'])){
    $material_name = mysqli_real_escape_string($conn, $_POST['material_name']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $purchase_price = floatval($_POST['purchase_price']);
    $sale_price = floatval($_POST['sale_price']);
    $opening_stock = floatval($_POST['opening_stock']);
    $min_stock_level = floatval($_POST['min_stock_level']);
    
    $query = "INSERT INTO raw_materials (material_name, unit, purchase_price, sale_price, current_stock, opening_stock, min_stock_level, status, created_datetime) 
              VALUES ('$material_name', '$unit', $purchase_price, $sale_price, $opening_stock, $opening_stock, $min_stock_level, 'Active', NOW())";
    
    if(mysqli_query($conn, $query)){
        $material_id = mysqli_insert_id($conn);
        
        // Add to stock ledger
        $ledger_query = "INSERT INTO raw_material_stock_ledger (material_id, transaction_date, transaction_type, quantity_in, running_stock, description, created_datetime) 
                        VALUES ($material_id, NOW(), 'OPENING', $opening_stock, $opening_stock, 'Opening stock added', NOW())";
        mysqli_query($conn, $ledger_query);
        
        $message = "<div class='alert alert-success'>Raw material added with opening stock!<br>
                    Purchase Price: Rs " . number_format($purchase_price, 2) . " | 
                    Sale Price: Rs " . number_format($sale_price, 2) . "</div>";
        echo "<script>setTimeout(function(){ window.location.href = 'raw_material_stock.php'; }, 2000);</script>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
    }
}

// Handle stock adjustment
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])){
    $material_id = intval($_POST['material_id']);
    $new_stock = floatval($_POST['new_stock']);
    
    // Get current stock
    $current_query = "SELECT current_stock FROM raw_materials WHERE id = $material_id";
    $current_result = mysqli_query($conn, $current_query);
    $current_row = mysqli_fetch_assoc($current_result);
    $old_stock = $current_row['current_stock'];
    $difference = $new_stock - $old_stock;
    
    if($difference != 0){
        $update_query = "UPDATE raw_materials SET current_stock = $new_stock WHERE id = $material_id";
        if(mysqli_query($conn, $update_query)){
            $quantity_in = $difference > 0 ? $difference : 0;
            $quantity_out = $difference < 0 ? abs($difference) : 0;
            
            $ledger_query = "INSERT INTO raw_material_stock_ledger (material_id, transaction_date, transaction_type, quantity_in, quantity_out, running_stock, description, created_datetime) 
                            VALUES ($material_id, NOW(), 'ADJUSTMENT', $quantity_in, $quantity_out, $new_stock, 'Manual stock adjustment', NOW())";
            mysqli_query($conn, $ledger_query);
            
            $message = "<div class='alert alert-success'>Stock adjusted successfully!</div>";
            echo "<script>setTimeout(function(){ window.location.href = 'raw_material_stock.php'; }, 1500);</script>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating stock!</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>No change in stock value!</div>";
    }
}

// Handle price update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_prices'])){
    $material_id = intval($_POST['material_id']);
    $purchase_price = floatval($_POST['purchase_price']);
    $sale_price = floatval($_POST['sale_price']);
    
    $update_query = "UPDATE raw_materials SET purchase_price = $purchase_price, sale_price = $sale_price WHERE id = $material_id";
    if(mysqli_query($conn, $update_query)){
        $message = "<div class='alert alert-success'>Prices updated successfully!</div>";
        echo "<script>setTimeout(function(){ window.location.href = 'raw_material_stock.php'; }, 1500);</script>";
    } else {
        $message = "<div class='alert alert-danger'>Error updating prices: " . mysqli_error($conn) . "</div>";
    }
}

// Get all raw materials
$query = "SELECT * FROM raw_materials ORDER BY material_name";
$result = mysqli_query($conn, $query);

// Statistics
$total_materials = mysqli_num_rows($result);
$total_stock_query = "SELECT COALESCE(SUM(current_stock), 0) as total FROM raw_materials";
$total_stock_result = mysqli_query($conn, $total_stock_query);
$total_stock_row = mysqli_fetch_assoc($total_stock_result);
$total_stock = $total_stock_row['total'] ?? 0;

$total_value_query = "SELECT COALESCE(SUM(current_stock * purchase_price), 0) as total FROM raw_materials";
$total_value_result = mysqli_query($conn, $total_value_query);
$total_value_row = mysqli_fetch_assoc($total_value_result);
$total_inventory_value = $total_value_row['total'] ?? 0;

$low_stock_query = "SELECT COUNT(*) as count FROM raw_materials WHERE current_stock <= min_stock_level";
$low_stock_result = mysqli_query($conn, $low_stock_query);
$low_stock_row = mysqli_fetch_assoc($low_stock_result);
$low_stock_count = $low_stock_row['count'] ?? 0;

$critical_query = "SELECT COUNT(*) as count FROM raw_materials WHERE current_stock <= min_stock_level * 0.5";
$critical_result = mysqli_query($conn, $critical_query);
$critical_row = mysqli_fetch_assoc($critical_result);
$critical_count = $critical_row['count'] ?? 0;

// Reset result pointer
mysqli_data_seek($result, 0);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
/* Custom Table Styles */
.materials-table {
    font-size: 14px;
}
.materials-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.materials-table td {
    padding: 12px 10px;
    vertical-align: middle;
    word-break: break-word;
}
.stock-low {
    background-color: #fff3cd !important;
}
.stock-critical {
    background-color: #f8d7da !important;
}
.stock-normal {
    background-color: #d1e7dd !important;
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
.progress {
    height: 8px;
    border-radius: 10px;
}
.action-buttons .btn {
    margin: 0 2px;
    padding: 5px 8px;
}
.price-cell {
    font-weight: 600;
}
.purchase-price {
    color: #dc3545;
}
.sale-price {
    color: #28a745;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-boxes me-2" style="color: #A04657;"></i> Raw Material Stock
            </h2>
            <p class="text-muted mb-0">Manage raw materials with purchase & sale prices</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                <i class="fas fa-plus-circle me-2"></i> Add Raw Material
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card primary shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($total_materials); ?></h3>
                        <p>Total Materials</p>
                    </div>
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card success shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($total_stock, 2); ?></h3>
                        <p>Total Stock Units</p>
                    </div>
                    <i class="fas fa-cubes"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card info shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3>Rs <?php echo number_format($total_inventory_value, 2); ?></h3>
                        <p>Inventory Value</p>
                    </div>
                    <i class="fas fa-rupee-sign"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card warning shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3><?php echo number_format($low_stock_count); ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Table Card -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Current Stock</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover materials-table mb-0" id="stockTable">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:160px">Material Name</th>
                            <th style="min-width:80px">Unit</th>
                            <th style="min-width:110px">Purchase Price</th>
                            <th style="min-width:110px">Sale Price</th>
                            <th style="min-width:100px">Opening Stock</th>
                            <th style="min-width:120px">Current Stock</th>
                            <th style="min-width:100px">Min Level</th>
                            <th style="min-width:150px">Status</th>
                            <th style="min-width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $stock_class = '';
                                $stock_text = '';
                                if($row['current_stock'] <= $row['min_stock_level'] * 0.5){
                                    $stock_class = 'stock-critical';
                                    $stock_text = 'Critical';
                                } elseif($row['current_stock'] <= $row['min_stock_level']){
                                    $stock_class = 'stock-low';
                                    $stock_text = 'Low';
                                } else {
                                    $stock_class = 'stock-normal';
                                    $stock_text = 'Normal';
                                }
                                $stock_percent = ($row['current_stock'] / max($row['min_stock_level'], 1)) * 100;
                                $progress_color = $stock_percent > 50 ? 'bg-success' : ($stock_percent > 25 ? 'bg-warning' : 'bg-danger');
                                $margin = $row['sale_price'] - $row['purchase_price'];
                                $margin_percent = $row['purchase_price'] > 0 ? ($margin / $row['purchase_price']) * 100 : 0;
                            ?>
                                <tr class="<?php echo $stock_class; ?>">
                                    <td class="text-center fw-semibold"><?php echo $row['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['material_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td class="purchase-price">Rs <?php echo number_format($row['purchase_price'], 2); ?></td>
                                    <td class="sale-price">Rs <?php echo number_format($row['sale_price'], 2); ?>
                                        <?php if($margin > 0): ?>
                                            <br><small class="text-muted">(+<?php echo round($margin_percent, 1); ?>%)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($row['opening_stock'], 2); ?></td>
                                    <td>
                                        <strong><?php echo number_format($row['current_stock'], 2); ?></strong>
                                        <?php if($row['current_stock'] <= $row['min_stock_level']): ?>
                                            <span class="badge bg-warning rounded-pill ms-1"><?php echo $stock_text; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($row['min_stock_level'], 2); ?></td>
                                    <td style="min-width: 150px;">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $progress_color; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo min($stock_percent, 100); ?>%;">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo round($stock_percent, 1); ?>% of minimum level</small>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="editPrices(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['material_name']); ?>', <?php echo $row['purchase_price']; ?>, <?php echo $row['sale_price']; ?>)">
                                            <i class="fas fa-tag"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="adjustStock(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['material_name']); ?>', <?php echo $row['current_stock']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="raw_material_stock_history.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fas fa-boxes fa-3x mb-3 d-block"></i>
                                    No raw materials found. Click <strong>"Add Raw Material"</strong> to get started.
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

<!-- Add Raw Material Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add Raw Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Material Name *</label>
                            <input type="text" name="material_name" class="form-control" placeholder="Enter material name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit *</label>
                            <select name="unit" class="form-select" required>
                                <option value="Pieces">Pieces</option>
                                <option value="Kg">Kilograms (Kg)</option>
                                <option value="Liters">Liters</option>
                                <option value="Meters">Meters</option>
                                <option value="Rolls">Rolls</option>
                                <option value="Boxes">Boxes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Purchase Price (Rs) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs</span>
                                <input type="number" name="purchase_price" class="form-control" step="0.01" placeholder="Cost price" required>
                            </div>
                            <small class="text-muted">The price you pay to buy this material</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sale Price (Rs) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs</span>
                                <input type="number" name="sale_price" class="form-control" step="0.01" placeholder="Selling price" required>
                            </div>
                            <small class="text-muted">The price you sell this material for</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opening Stock *</label>
                            <input type="number" name="opening_stock" class="form-control" step="0.01" placeholder="Initial quantity" required>
                            <small class="text-muted">Initial quantity in stock</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Minimum Stock Level</label>
                            <input type="number" name="min_stock_level" class="form-control" step="0.01" placeholder="Alert level" value="50">
                            <small class="text-muted">Alert when stock falls below this level</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_opening_stock" class="btn btn-primary rounded-pill px-4">Add Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Prices Modal -->
<div class="modal fade" id="editPricesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-tag me-2"></i> Update Prices</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="material_id" id="price_material_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Material</label>
                        <input type="text" id="price_material_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purchase Price (Rs)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="purchase_price" id="purchase_price" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sale Price (Rs)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="sale_price" id="sale_price" class="form-control" step="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_prices" class="btn btn-info rounded-pill px-4">Update Prices</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Adjust Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="material_id" id="adjust_material_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Material</label>
                        <input type="text" id="adjust_material_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Stock</label>
                        <input type="text" id="adjust_current_stock" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Stock *</label>
                        <input type="number" name="new_stock" id="adjust_new_stock" class="form-control" step="0.01" required>
                        <small class="text-muted">Enter the new stock quantity after adjustment</small>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-warning rounded-pill px-4">Adjust Stock</button>
                </div>
            </form>
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
    $('#stockTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ materials"
        }
    });
    <?php endif; ?>
});

function adjustStock(id, name, currentStock) {
    $('#adjust_material_id').val(id);
    $('#adjust_material_name').val(name);
    $('#adjust_current_stock').val(currentStock);
    $('#adjust_new_stock').val(currentStock);
    $('#adjustStockModal').modal('show');
}

function editPrices(id, name, purchasePrice, salePrice) {
    $('#price_material_id').val(id);
    $('#price_material_name').val(name);
    $('#purchase_price').val(purchasePrice);
    $('#sale_price').val(salePrice);
    $('#editPricesModal').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>