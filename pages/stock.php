<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$success = '';
$error = '';

// Next auto-generated 5-digit product ID (shown on Add Product form)
$next_product_code = generate_5digit_code($conn, 'products', 'product_code');

// Handle Add New Product with Purchase Price
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $purchase_price = floatval($_POST['purchase_price']);
    $sale_price = floatval($_POST['sale_price']);
    $opening_stock = intval($_POST['opening_stock']);
    $track_empty = isset($_POST['track_empty_bottles']) ? 1 : 0;
    $datetime = date('Y-m-d H:i:s');

    // Check if a product with the same name already exists (case-insensitive)
    $existing_query = mysqli_query($conn, "SELECT id, product_name, current_stock, purchase_price, track_empty_bottles FROM products WHERE LOWER(product_name) = LOWER('$product_name') LIMIT 1");

    if($existing_query && mysqli_num_rows($existing_query) > 0) {
        $existing = mysqli_fetch_assoc($existing_query);
        $new_stock = $existing['current_stock'] + $opening_stock;
        $new_price = ($purchase_price > 0) ? $purchase_price : $existing['purchase_price'];
        $new_sale = ($sale_price > 0) ? $sale_price : $existing['sale_price'];

        mysqli_query($conn, "UPDATE products SET current_stock = $new_stock, purchase_price = $new_price, sale_price = $new_sale WHERE id={$existing['id']}");

        if($opening_stock > 0) {
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ({$existing['id']}, '$datetime', 'IN', 'stock_add', $opening_stock, $new_stock, 'Stock added to existing product ({$existing['product_name']}): $opening_stock bottles', '$datetime')");
        }

        $success = "Product \"" . htmlspecialchars($existing['product_name']) . "\" already existed. Added " . number_format($opening_stock) . " bottles to its stock. New stock level: " . number_format($new_stock) . " bottles!";
    } else {
        $product_code = !empty($_POST['product_code']) ? mysqli_real_escape_string($conn, $_POST['product_code']) : generate_5digit_code($conn, 'products', 'product_code');
        $min_level = isset($_POST['min_stock_level']) ? intval($_POST['min_stock_level']) : 10;
        $insert_query = "INSERT INTO products (product_code, product_name, purchase_price, sale_price, current_stock, min_stock_level, track_empty_bottles, status, created_datetime) 
                         VALUES ('$product_code', '$product_name', $purchase_price, $sale_price, $opening_stock, $min_level, $track_empty, 'Active', '$datetime')";
        
        if(mysqli_query($conn, $insert_query)) {
            $product_id = mysqli_insert_id($conn);
            
            // Add opening stock to stock ledger
            if($opening_stock > 0) {
                mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_in, running_stock, description, created_datetime) 
                                     VALUES ($product_id, '$datetime', 'IN', 'opening', $opening_stock, $opening_stock, 'Opening stock: $opening_stock bottles added', '$datetime')");
            }
            
            $success = "Product added successfully! Purchase Price: Rs " . number_format($purchase_price, 2) . " | Opening Stock: " . number_format($opening_stock) . " bottles!";
        } else {
            $error = "Error adding product: " . mysqli_error($conn);
        }
    }
}

// Handle Stock In (Production)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock_in'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $production_date = mysqli_real_escape_string($conn, $_POST['production_date']);
    $datetime = date('Y-m-d H:i:s');
    
    // Get current stock
    $product_query = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE id=$product_id");
    if($product_query && mysqli_num_rows($product_query) > 0) {
        $product = mysqli_fetch_assoc($product_query);
        $new_stock = $product['current_stock'] + $quantity;
        
        // Insert stock in record
        $insert_query = "INSERT INTO stock_in (product_id, quantity, stock_date, created_by, created_datetime) 
                         VALUES ($product_id, $quantity, '$production_date', {$_SESSION['admin_id']}, '$datetime')";
        
        if(mysqli_query($conn, $insert_query)) {
            $stock_in_id = mysqli_insert_id($conn);
            
            // Update product stock
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            $running_stock = $new_stock;
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$production_date', 'IN', 'production', $stock_in_id, $quantity, $running_stock, 'Production: $quantity {$product['product_name']} produced on $production_date', '$datetime')");
            
            $success = "Production recorded successfully! New stock level: " . number_format($new_stock) . " bottles";
        } else {
            $error = "Error recording production: " . mysqli_error($conn);
        }
    } else {
        $error = "Product not found!";
    }
}

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['adjust_quantity']);
    $adjust_type = $_POST['adjust_type'];
    $notes = mysqli_real_escape_string($conn, $_POST['adjust_notes']);
    $datetime = date('Y-m-d H:i:s');
    
    $product_query = mysqli_query($conn, "SELECT current_stock, product_name FROM products WHERE id=$product_id");
    if($product_query && mysqli_num_rows($product_query) > 0) {
        $product = mysqli_fetch_assoc($product_query);
        
        if($adjust_type == 'remove') {
            if($quantity > $product['current_stock']) {
                $error = "Cannot remove $quantity bottles. Current stock is only " . $product['current_stock'] . " bottles.";
            } else {
                $new_stock = $product['current_stock'] - $quantity;
                mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
                
                // Add to stock ledger
                $running_stock = $new_stock;
                mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_out, running_stock, description, created_datetime) 
                                     VALUES ($product_id, '$datetime', 'OUT', 'adjustment', $quantity, $running_stock, 'Stock adjustment: Removed $quantity bottles. $notes', '$datetime')");
                $success = "Stock reduced successfully! New stock level: " . number_format($new_stock) . " bottles";
            }
        } else {
            $new_stock = $product['current_stock'] + $quantity;
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            $running_stock = $new_stock;
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, quantity_in, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$datetime', 'IN', 'adjustment', $quantity, $running_stock, 'Stock adjustment: Added $quantity bottles. $notes', '$datetime')");
            $success = "Stock increased successfully! New stock level: " . number_format($new_stock) . " bottles";
        }
    } else {
        $error = "Product not found!";
    }
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $product_id = intval($_POST['product_id']);
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $purchase_price = floatval($_POST['purchase_price']);
    $min_level = intval($_POST['min_stock_level']);
    $track_empty = isset($_POST['track_empty_bottles']) ? 1 : 0;
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $res = mysqli_query($conn, "UPDATE products SET product_name='$product_name', purchase_price=$purchase_price, min_stock_level=$min_level, track_empty_bottles=$track_empty, status='$status' WHERE id=$product_id");
    if($res) {
        $success = "Product updated successfully!";
    } else {
        $error = "Error updating product: " . mysqli_error($conn);
    }
}

// Handle Delete Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    $del_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM water_deliveries WHERE product_id=$product_id"))['cnt'];
    if($del_count > 0) {
        mysqli_query($conn, "UPDATE products SET status='Inactive' WHERE id=$product_id");
        $error = "Cannot delete this product because it has $del_count delivery record(s). It has been set to Inactive instead.";
    } else {
        mysqli_query($conn, "DELETE FROM stock_in WHERE product_id=$product_id");
        mysqli_query($conn, "DELETE FROM stock_ledger WHERE product_id=$product_id");
        $res = mysqli_query($conn, "DELETE FROM products WHERE id=$product_id");
        if($res) {
            $success = "Product deleted successfully!";
        } else {
            $error = "Error deleting product: " . mysqli_error($conn);
        }
    }
}

// Get all products
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY product_name");

// Stock summary calculations
$total_query = mysqli_query($conn, "SELECT SUM(current_stock) as total FROM products");
$total_stock = ($total_query && mysqli_num_rows($total_query) > 0) ? mysqli_fetch_assoc($total_query)['total'] : 0;

$low_query = mysqli_query($conn, "SELECT * FROM products WHERE current_stock <= min_stock_level");
$low_stock_count = ($low_query) ? mysqli_num_rows($low_query) : 0;

$month_in_query = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock_in WHERE MONTH(stock_date)=MONTH(CURDATE()) AND YEAR(stock_date)=YEAR(CURDATE())");
$month_in = ($month_in_query && mysqli_num_rows($month_in_query) > 0) ? mysqli_fetch_assoc($month_in_query)['total'] : 0;

$month_out_query = mysqli_query($conn, "SELECT SUM(bottles_delivered) as total FROM water_deliveries WHERE MONTH(delivery_datetime)=MONTH(CURDATE()) AND YEAR(delivery_datetime)=YEAR(CURDATE())");
$month_out = ($month_out_query && mysqli_num_rows($month_out_query) > 0) ? mysqli_fetch_assoc($month_out_query)['total'] : 0;

$recent_production = mysqli_query($conn, "SELECT si.*, p.product_name FROM stock_in si JOIN products p ON si.product_id = p.id ORDER BY si.stock_date DESC LIMIT 10");

$stock_ledger = mysqli_query($conn, "SELECT sl.*, p.product_name FROM stock_ledger sl JOIN products p ON sl.product_id = p.id ORDER BY sl.transaction_date DESC LIMIT 20");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.stock-table th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    border-bottom: 2px solid #dee2e6;
}
.stock-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}
.stock-table tbody tr:hover {
    background: #fafafa;
}
.low-stock {
    background-color: #fff3cd;
}
.price-cell {
    font-weight: 600;
    color: #28a745;
}
.stock-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
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
.dataTables_wrapper .dataTables_filter {
    text-align: right;
    margin-bottom: 10px;
}
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-boxes me-2" style="color: #A04657;"></i> Stock Management
        </h2>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-dark rounded-pill px-4 py-2" onclick="printStock()">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addStockInModal">
                <i class="fas fa-arrow-down me-2"></i> Production
            </button>
            <button type="button" class="btn btn-warning rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                <i class="fas fa-sliders-h me-2"></i> Adjust Stock
            </button>
            <button type="button" class="btn btn-success rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus-circle me-2"></i> New Product
            </button>
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

    <!-- Summary -->
    <div class="d-flex flex-wrap gap-3 mb-4">
        <div class="bg-white border rounded-3 px-4 py-2 shadow-sm d-flex align-items-center gap-3">
            <i class="fas fa-boxes fa-lg text-primary opacity-75"></i>
            <div>
                <small class="text-muted d-block">Total Stock</small>
                <strong><?php echo number_format($total_stock); ?> bottles</strong>
            </div>
        </div>
        <div class="bg-white border rounded-3 px-4 py-2 shadow-sm d-flex align-items-center gap-3">
            <i class="fas fa-exclamation-triangle fa-lg <?php echo $low_stock_count > 0 ? 'text-danger' : 'text-success'; ?>"></i>
            <div>
                <small class="text-muted d-block">Low Stock Items</small>
                <strong class="<?php echo $low_stock_count > 0 ? 'text-danger' : ''; ?>"><?php echo $low_stock_count; ?></strong>
            </div>
        </div>
        <div class="bg-white border rounded-3 px-4 py-2 shadow-sm d-flex align-items-center gap-3">
            <i class="fas fa-industry fa-lg text-info"></i>
            <div>
                <small class="text-muted d-block">This Month Produced</small>
                <strong><?php echo number_format($month_in); ?> bottles</strong>
            </div>
        </div>
        <div class="bg-white border rounded-3 px-4 py-2 shadow-sm d-flex align-items-center gap-3">
            <i class="fas fa-truck fa-lg text-warning"></i>
            <div>
                <small class="text-muted d-block">This Month Delivered</small>
                <strong><?php echo number_format($month_out); ?> bottles</strong>
            </div>
        </div>
    </div>

    <!-- Products Stock Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Product Stock Levels</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover stock-table mb-0" id="stockTable">
                    <thead>
                        <tr>
                            <th style="width:70px">ID</th>
                            <th>Product Name</th>
                            <th class="text-center">Purchase Price (Rs)</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Min Level</th>
                            <th class="text-center">Track Empties</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($products && mysqli_num_rows($products) > 0): ?>
                            <?php while($p = mysqli_fetch_assoc($products)):
                                $is_low = $p['current_stock'] <= $p['min_stock_level'];
                            ?>
                                <tr class="<?php echo $is_low ? 'low-stock' : ''; ?>">
                                    <td class="fw-semibold"><?php echo $p['product_code']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                                    <td class="text-center price-cell">Rs <?php echo number_format($p['purchase_price'], 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $is_low ? 'bg-danger' : 'bg-success'; ?> rounded-pill px-3">
                                            <?php echo number_format($p['current_stock']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo number_format($p['min_stock_level']); ?></td>
                                    <td class="text-center">
                                        <?php if(!empty($p['track_empty_bottles'])): ?>
                                            <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($p['status'] == 'Inactive'): ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php elseif($is_low): ?>
                                            <span class="badge bg-warning text-dark">Low Stock!</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <a href="stock_ledger.php?product_id=<?php echo $p['id']; ?>" class="btn btn-xs btn-info" title="View Ledger">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-xs btn-warning editProductBtn"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($p['product_name']); ?>"
                                            data-price="<?php echo $p['purchase_price']; ?>"
                                            data-min="<?php echo $p['min_stock_level']; ?>"
                                            data-track="<?php echo $p['track_empty_bottles']; ?>"
                                            data-status="<?php echo htmlspecialchars($p['status']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-danger deleteProductBtn"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-boxes fa-3x mb-3 d-block opacity-25"></i>
                                    No products found. Click "New Product" to add.
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

<!-- Add Product Modal with Sale Price -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product ID <span class="badge bg-info-subtle text-info-emphasis ms-1">Auto</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-id-badge text-muted"></i></span>
                            <input type="text" name="product_code" class="form-control" value="<?php echo htmlspecialchars($next_product_code); ?>" readonly>
                        </div>
                        <small class="text-muted">This 5-digit ID is auto-generated for this product</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" class="form-control" required placeholder="e.g., 20 Liter Water Bottle">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase Price (Rs)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="purchase_price" class="form-control" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sale Price (Rs) *</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="sale_price" class="form-control" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opening Stock</label>
                        <input type="number" name="opening_stock" class="form-control" value="" placeholder="Initial stock quantity">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Min Stock Level (Alert)</label>
                        <input type="number" name="min_stock_level" class="form-control" value="10" placeholder="Low stock alert level">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="track_empty_bottles" class="form-check-input" id="trackEmptyBottles" value="1" checked>
                            <label class="form-check-label" for="trackEmptyBottles">
                                <i class="fas fa-recycle me-1" style="color: #28a745;"></i> Track Empty Bottles
                                <span class="text-muted small">(19L returnable bottles — keep ON)</span>
                            </label>
                        </div>
                        <div class="form-text text-muted">Untick for 1.5L / 0.5L bottles — no empty bottle stock tracking.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary rounded-pill px-4">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Production Modal -->
<div class="modal fade" id="addStockInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-industry me-2"></i> Add Production</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php 
                            $prod_query = mysqli_query($conn, "SELECT * FROM products WHERE status='Active' ORDER BY product_name");
                            if($prod_query && mysqli_num_rows($prod_query) > 0):
                                while($p = mysqli_fetch_assoc($prod_query)): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> [<?php echo htmlspecialchars($p['product_code']); ?>] (Purchase: Rs <?php echo number_format($p['purchase_price'], 2); ?> | Stock: <?php echo number_format($p['current_stock']); ?> bottles)</option>
                            <?php 
                                endwhile;
                            else: ?>
                                <option value="" disabled>No products found. Please add a product first.</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Production Date *</label>
                        <input type="datetime-local" name="production_date" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Produced *</label>
                        <input type="number" name="quantity" class="form-control" required placeholder="Number of bottles produced">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_stock_in" class="btn btn-primary rounded-pill px-4">Add Production</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h me-2"></i> Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php 
                            $prod_query2 = mysqli_query($conn, "SELECT * FROM products WHERE status='Active' ORDER BY product_name");
                            if($prod_query2 && mysqli_num_rows($prod_query2) > 0):
                                while($p = mysqli_fetch_assoc($prod_query2)): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> [<?php echo htmlspecialchars($p['product_code']); ?>] (Purchase: Rs <?php echo number_format($p['purchase_price'], 2); ?> | Stock: <?php echo number_format($p['current_stock']); ?> bottles)</option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type *</label>
                        <select name="adjust_type" class="form-select" required>
                            <option value="add">Add Stock (+) Increase</option>
                            <option value="remove">Remove Stock (-) Decrease</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" name="adjust_quantity" class="form-control" required placeholder="Number of bottles">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason / Notes *</label>
                        <textarea name="adjust_notes" class="form-control" rows="2" required placeholder="Why is this adjustment needed?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-warning rounded-pill px-4">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase Price (Rs)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="purchase_price" id="edit_product_price" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Min Stock Level (Alert)</label>
                        <input type="number" name="min_stock_level" id="edit_product_min" class="form-control">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="track_empty_bottles" class="form-check-input" id="edit_track_empty" value="1">
                            <label class="form-check-label" for="edit_track_empty">
                                <i class="fas fa-recycle me-1" style="color: #28a745;"></i> Track Empty Bottles
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_product_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_product" class="btn btn-primary rounded-pill px-4">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3545; color: white; border-bottom: none; border-radius: 20px 20px 0 0; padding: 15px 20px;">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <p class="mb-0">Are you sure you want to delete <strong id="delete_product_name"></strong>? This will remove its stock history and cannot be undone.</p>
                    <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i> Products with delivery records cannot be deleted and will be set to Inactive instead.</p>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_product" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Print Overlay -->
<div id="print-overlay">
    <div id="print-area">
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle">
                    <i class="fas fa-tint"></i>
                </div>
                <div class="print-brand-text">
                    <div class="print-owner-name"><?php echo htmlspecialchars($owner_name); ?></div>
                    <div class="print-company"><?php echo htmlspecialchars($company_name); ?></div>
                    <div class="print-address"><?php echo htmlspecialchars($owner_address); ?></div>
                    <div class="print-phone"><?php echo htmlspecialchars($owner_phone); ?></div>
                </div>
            </div>
            <div class="print-divider"></div>
            <div class="print-title-row">
                <span class="print-doc-title">Stock Management Report</span>
            </div>
        </div>

        <div class="print-sub-title">Current Stock Levels</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th style="width:50px;">ID</th>
                    <th>Product Name</th>
                    <th style="width:80px;" class="text-end">Price (Rs)</th>
                    <th style="width:70px;" class="text-end">Stock</th>
                    <th style="width:50px;" class="text-end">Min</th>
                    <th style="width:65px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_products = mysqli_query($conn, "SELECT * FROM products ORDER BY product_name");
                $sno = 1;
                if($print_products && mysqli_num_rows($print_products) > 0):
                    while($p = mysqli_fetch_assoc($print_products)):
                        $is_low = $p['current_stock'] <= $p['min_stock_level'];
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo $p['product_code']; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                        <td class="text-end"><?php echo number_format($p['purchase_price'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($p['current_stock']); ?></td>
                        <td class="text-end"><?php echo number_format($p['min_stock_level']); ?></td>
                        <td><?php echo $is_low ? 'Low Stock!' : 'In Stock'; ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:#999;">No products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-sub-title">Recent Production Records</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th style="width:90px;">Production Date</th>
                    <th>Product</th>
                    <th style="width:90px;" class="text-end">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_production = mysqli_query($conn, "SELECT si.*, p.product_name FROM stock_in si JOIN products p ON si.product_id = p.id ORDER BY si.stock_date DESC LIMIT 10");
                $sno = 1;
                if($print_production && mysqli_num_rows($print_production) > 0):
                    while($si = mysqli_fetch_assoc($print_production)):
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($si['stock_date'])); ?></td>
                        <td><?php echo htmlspecialchars($si['product_name']); ?></td>
                        <td class="text-end"><?php echo number_format($si['quantity']); ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center" style="padding:40px;color:#999;">No production records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-sub-title">Stock Ledger (Transaction History)</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th style="width:100px;">Date & Time</th>
                    <th>Product</th>
                    <th style="width:50px;">Type</th>
                    <th style="width:40px;" class="text-end">IN</th>
                    <th style="width:40px;" class="text-end">OUT</th>
                    <th style="width:55px;" class="text-end">Running</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_ledger = mysqli_query($conn, "SELECT sl.*, p.product_name FROM stock_ledger sl JOIN products p ON sl.product_id = p.id ORDER BY sl.transaction_date DESC LIMIT 20");
                $sno = 1;
                if($print_ledger && mysqli_num_rows($print_ledger) > 0):
                    while($sl = mysqli_fetch_assoc($print_ledger)):
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo date('d-m-Y h:i A', strtotime($sl['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($sl['product_name']); ?></td>
                        <td><?php echo $sl['transaction_type'] == 'IN' ? 'Stock In' : 'Stock Out'; ?></td>
                        <td class="text-end"><?php echo $sl['quantity_in'] > 0 ? number_format($sl['quantity_in']) : '-'; ?></td>
                        <td class="text-end"><?php echo $sl['quantity_out'] > 0 ? number_format($sl['quantity_out']) : '-'; ?></td>
                        <td class="text-end"><?php echo number_format($sl['running_stock']); ?></td>
                        <td><small><?php echo htmlspecialchars($sl['description']); ?></small></td>
                    </tr>
                <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center" style="padding:40px;color:#999;">No stock transactions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
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
    width: 960px;
    margin: 0 auto;
    padding: 20px 25px;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.print-header { margin-bottom: 14px; }
.print-brand-row { display: flex; align-items: center; gap: 14px; }
.print-logo-circle { width: 50px; height: 50px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; flex-shrink: 0; }
.print-brand-text { display: flex; flex-direction: column; gap: 1px; }
.print-company { font-size: 16px; font-weight: 700; color: #A04657; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-owner-name { font-size: 20px; font-weight: 800; color: #222; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-address { font-size: 12px; color: #666; }
.print-phone { font-size: 12px; font-weight: 600; color: #A04657; }
.print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 10px 0 8px; border-radius: 2px; }
.print-title-row { display: flex; justify-content: space-between; align-items: center; }
.print-doc-title { font-size: 14px; font-weight: 700; color: #444; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-sub-title { font-size: 11px; font-weight: 700; color: #A04657; margin: 10px 0 4px; }
.print-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 4px; }
.print-table th { background: #A04657; color: #fff; padding: 5px 7px; font-weight: 600; font-size: 10px; text-align: left; white-space: nowrap; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 4px 7px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-footer { margin-top: 10px; text-align: center; font-size: 10px; color: #aaa; padding-top: 8px; border-top: 1px solid #eee; }
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
function printStock() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Stock Management Report</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f0f0f0; }
                .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 2px solid #A04657; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .toolbar button { padding: 8px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
                .btn-print { background: #A04657; color: #fff; }
                .btn-print:hover { background: #8a3a4a; }
                .btn-download { background: #28a745; color: #fff; }
                .btn-download:hover { background: #218838; }
                .btn-close { background: #6c757d; color: #fff; margin-left: auto; }
                .btn-close:hover { background: #5a6268; }
                .report-wrap { max-width: 1000px; margin: 0 auto; padding: 20px; }
                .print-header { margin-bottom: 12px; }
                .print-brand-row { display: flex; align-items: center; gap: 14px; }
                .print-logo-circle { width: 50px; height: 50px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; flex-shrink: 0; }
                .print-brand-text { display: flex; flex-direction: column; gap: 1px; }
                .print-company { font-size: 15px; font-weight: 700; color: #A04657; }
                .print-owner-name { font-size: 18px; font-weight: 800; color: #222; }
                .print-address { font-size: 11px; color: #666; }
                .print-phone { font-size: 11px; font-weight: 600; color: #A04657; }
                .print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 8px 0 6px; border-radius: 2px; }
                .print-title-row { display: flex; justify-content: space-between; align-items: center; }
                .print-doc-title { font-size: 13px; font-weight: 700; color: #444; }
                .print-sub-title { font-size: 11px; font-weight: 700; color: #A04657; margin: 8px 0 3px; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 4px; }
                th { background: #A04657; color: #fff; padding: 5px 7px; font-weight: 600; font-size: 10px; text-align: left; white-space: nowrap; }
                th.text-end, td.text-end { text-align: right; }
                td { padding: 4px 7px; border-bottom: 1px solid #e6e6e6; color: #333; }
                tr:nth-child(even) td { background: #f9f9f9; }
                tr:last-child td { border-bottom: 2px solid #A04657; }
                .print-footer { margin-top: 8px; text-align: center; font-size: 10px; color: #aaa; padding-top: 6px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Stock Management Report</strong>
                <button class="btn-print" onclick="window.print()">Print</button>
                <button class="btn-download" onclick="downloadStockReport()">Download PNG</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <div class="report-wrap">
                ${content}
            </div>
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js">
            <\/script>
            <script>
                function downloadStockReport() {
                    const el = document.querySelector('.report-wrap');
                    html2canvas(el, { scale: 3, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
                        const a = document.createElement('a');
                        a.href = canvas.toDataURL('image/png');
                        a.download = 'stock_report_' + new Date().toISOString().slice(0, 10) + '.png';
                        a.click();
                    });
                }
            <\/script>
        </body>
        </html>
    `);
    w.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    <?php if($products && mysqli_num_rows($products) > 0): ?>
    $('#stockTable').DataTable({
        pageLength: -1,
        order: [[1, 'asc']],
        columnDefs: [{ targets: -1, orderable: false }],
        dom: 'frtip',
        language: {
            search: "Search:",
            info: "",
            paginate: { previous: "", next: "" }
        }
    });
    $('.dataTables_length').hide();
    $('.dataTables_info').hide();
    $('.dataTables_paginate').hide();
    <?php endif; ?>

    $(document).on('click', '.editProductBtn', function() {
        document.getElementById('edit_product_id').value = this.getAttribute('data-id');
        document.getElementById('edit_product_name').value = this.getAttribute('data-name');
        document.getElementById('edit_product_price').value = this.getAttribute('data-price');
        document.getElementById('edit_product_min').value = this.getAttribute('data-min');
        document.getElementById('edit_track_empty').checked = this.getAttribute('data-track') == '1';
        document.getElementById('edit_product_status').value = this.getAttribute('data-status');
        var editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
        editModal.show();
    });

    $(document).on('click', '.deleteProductBtn', function() {
        document.getElementById('delete_product_id').value = this.getAttribute('data-id');
        document.getElementById('delete_product_name').textContent = this.getAttribute('data-name');
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
        deleteModal.show();
    });
});
</script>