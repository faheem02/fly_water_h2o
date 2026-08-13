<?php
// Handle AJAX request for product price and stock
if(isset($_GET['ajax_product_price']) && isset($_GET['pid'])){
    require_once '../includes/db.php';
    $pid = intval($_GET['pid']);
    $res = mysqli_query($conn, "SELECT sale_price, current_stock FROM products WHERE id=$pid AND status='Active'");
    if($row = mysqli_fetch_assoc($res)){
        echo json_encode(['price' => $row['sale_price'], 'stock' => $row['current_stock']]);
    } else {
        echo json_encode(['price' => 0, 'stock' => 0]);
    }
    exit;
}

require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$salesman_name = is_salesman() ? mysqli_real_escape_string($conn, $_SESSION['admin_name']) : '';
$salesman_only = is_salesman() ? ' AND ' . salesman_match_condition($conn) : '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_delivery'])) {
    $product_id = intval($_POST['product_id']);
    $bottles = intval($_POST['bottles_delivered']);
    $empties_returned = intval($_POST['empty_bottles_returned']);
    $rate = floatval($_POST['bottle_rate']);
    $cash_received = floatval($_POST['cash_received']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $datetime = date('Y-m-d H:i:s');

    // Handle walk-in customer (Admin only — customers are created by Admin)
    if (is_salesman() && $_POST['customer_id'] === 'new') {
        $error = "New customers can only be created by Admin!";
        $cust = null;
    } elseif ($_POST['customer_id'] === 'new') {
        $w_name = mysqli_real_escape_string($conn, $_POST['walkin_name']);
        $w_mobile = mysqli_real_escape_string($conn, $_POST['walkin_mobile']);
        $w_address = mysqli_real_escape_string($conn, $_POST['walkin_address']);
        $w_salesman = is_salesman() ? $salesman_name : mysqli_real_escape_string($conn, $_POST['walkin_salesman']);
        $w_code = generate_5digit_code($conn, 'customers', 'customer_code');
        mysqli_query($conn, "INSERT INTO customers (customer_code, customer_name, mobile, address, outstanding_balance, salesman, status, created_datetime) VALUES ('$w_code', '$w_name', '$w_mobile', '$w_address', 0, '$w_salesman', 'Active', '$datetime')");
        $customer_id = mysqli_insert_id($conn);
        $cust = ['customer_name' => $w_name, 'outstanding_balance' => 0, 'empty_bottles_balance' => 0, 'salesman' => $w_salesman];
    } else {
        $customer_id = intval($_POST['customer_id']);
        // Get customer details
        $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.outstanding_balance, c.empty_bottles_balance, c.customer_name, c.salesman FROM customers c WHERE c.id=$customer_id"));
        if (is_salesman() && $cust && !salesman_owns_customer($conn, $cust['salesman'])) {
            $error = "You can only make deliveries for your own assigned customers!";
            $cust = null;
        }
    }
    
    // Get product details
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_name, sale_price, current_stock, min_stock_level, track_empty_bottles FROM products WHERE id=$product_id AND status='Active'"));
    
    if($cust && $product) {
        $track_empties = !empty($product['track_empty_bottles']);
        if(!$track_empties) {
            $empties_returned = 0;
        }
        $total = $bottles * $rate;
        $new_outstanding = $cust['outstanding_balance'] + $total - $cash_received;
        $new_empties = $cust['empty_bottles_balance'] + ($track_empties ? $bottles : 0) - $empties_returned;
        
        // CHECK STOCK AVAILABILITY
        if($bottles > $product['current_stock']) {
            $error = "Insufficient stock! Available stock: " . $product['current_stock'] . " bottles. Required: $bottles bottles.";
        } else {
            // Insert delivery record with product_id
            $entry_salesman = isset($_SESSION['admin_name']) ? mysqli_real_escape_string($conn, $_SESSION['admin_name']) : '';
            $voucher_no = generate_voucher_no($conn, 'water_deliveries', 'voucher_no', 'SLS-');
            mysqli_query($conn, "INSERT INTO water_deliveries (voucher_no, customer_id, product_id, bottles_delivered, empty_bottles_returned, bottle_rate, total_amount, salesman, notes, delivery_datetime) 
                                 VALUES ('$voucher_no', $customer_id, $product_id, $bottles, $empties_returned, $rate, $total, '$entry_salesman', '$notes', '$datetime')");
            
            $delivery_id = mysqli_insert_id($conn);
            
            // Update customer outstanding and empty bottles (empty bottles only for tracked products)
            if($track_empties) {
                mysqli_query($conn, "UPDATE customers SET outstanding_balance = $new_outstanding, empty_bottles_balance = $new_empties WHERE id=$customer_id");
            } else {
                mysqli_query($conn, "UPDATE customers SET outstanding_balance = $new_outstanding WHERE id=$customer_id");
            }
            
            // Get current running balance
            $running = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_balance FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id DESC LIMIT 1"))['running_balance'] ?? 0;
            
            // Ledger entry: Debit (sale)
            $new_balance = $running + $total;
            mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                 VALUES ($customer_id, '$datetime', '" . mysqli_real_escape_string($conn, $product['product_name']) . "', $total, 0, $new_balance, $delivery_id, 'delivery')");
            
            // Ledger entry: Credit (cash received)
            if($cash_received > 0) {
                $new_balance2 = $new_balance - $cash_received;
                mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                     VALUES ($customer_id, '$datetime', 'Cash Received - Rs " . number_format($cash_received, 2) . "', 0, $cash_received, $new_balance2, $delivery_id, 'payment')");
            }
            
            // Bottle tracking entry (only for products with empty bottle tracking)
            if($track_empties) {
                mysqli_query($conn, "INSERT INTO bottle_tracking (customer_id, tracking_date, bottles_delivered, bottles_returned, bottles_broken, pending_empties, notes, reference_id) 
                                     VALUES ($customer_id, '$datetime', $bottles, $empties_returned, 0, $new_empties, '$notes', $delivery_id)");
            }
            
            // ========== STOCK MANAGEMENT: DEDUCT STOCK ==========
            $new_stock = $product['current_stock'] - $bottles;
            
            // Update product stock
            mysqli_query($conn, "UPDATE products SET current_stock = $new_stock WHERE id=$product_id");
            
            // Add to stock ledger
            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_out, running_stock, description, created_datetime) 
                                 VALUES ($product_id, '$datetime', 'OUT', 'delivery', $delivery_id, $bottles, $new_stock, 'Stock out: $bottles bottles of {$product['product_name']} delivered to customer: " . htmlspecialchars($cust['customer_name']) . "', '$datetime')");
            
            // Check for low stock alert
            if($new_stock <= $product['min_stock_level']) {
                $success = " <span class='text-warning'>⚠️ Low stock alert! Only $new_stock bottles remaining.</span>";
            }
            // ========== END STOCK MANAGEMENT ==========
            
            $msg = "Delivery recorded successfully for " . htmlspecialchars($cust['customer_name']) . "! Voucher: <strong>$voucher_no</strong> | Total: Rs " . number_format($total, 2);
            if($cash_received > 0) {
                $msg .= " | Cash Received: Rs " . number_format($cash_received, 2);
                $msg .= " | Outstanding: Rs " . number_format($new_outstanding, 2);
            }
            $success = $msg . $success;
        }
    } else {
        if(empty($error)) {
            $error = "Customer or Product not found!";
        }
    }
}

// Get customer list for dropdown
$customers_list = mysqli_query($conn, "SELECT c.id, c.customer_code, c.customer_name, c.mobile, c.salesman, c.outstanding_balance, c.empty_bottles_balance FROM customers c WHERE c.status='Active' $salesman_only ORDER BY c.customer_name");

// Get active products with stock
$products_list = mysqli_query($conn, "SELECT id, product_name, purchase_price, sale_price, current_stock, track_empty_bottles FROM products WHERE status='Active' AND current_stock > 0 ORDER BY product_name");

// Get today's sales summary
$today_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) as total, COALESCE(SUM(bottles_delivered),0) as bottles FROM water_deliveries WHERE DATE(delivery_datetime)=CURDATE()"));

// Get current stock info for all products
$products_stock = mysqli_query($conn, "SELECT id, product_name, current_stock, min_stock_level, sale_price FROM products WHERE status='Active' ORDER BY product_name");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
body {
    background: #f5f7fb;
}
.main-content {
    background: #f5f7fb;
}
.delivery-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.delivery-card .card-header {
    background: #A04657;
    color: white;
    padding: 16px 24px;
    font-weight: 600;
    font-size: 16px;
}
.delivery-card .card-body {
    padding: 24px;
}
.form-section-title {
    font-size: 13px;
    font-weight: 700;
    color: #A04657;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #f0f0f0;
}
.stat-card {
    border: none;
    padding: 20px;
}
.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #888;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.customer-chip {
    background: #e8f5e9;
    border: 1px solid #c8e6c9;
    border-radius: 10px;
    padding: 12px 16px;
    margin-top: 10px;
}
.customer-chip .chip-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.customer-chip .chip-value {
    font-size: 13px;
    font-weight: 600;
    color: #2e7d32;
}
.product-badge {
    display: inline-block;
    background: #fff3e0;
    color: #e65100;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.ui-autocomplete {
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
    font-size: 14px;
    border-radius: 8px;
}
.ui-menu-item {
    padding: 6px 12px;
    border-bottom: 1px solid #f0f0f0;
}
.ui-menu-item .ui-menu-item-wrapper {
    padding: 4px 8px;
}
.ui-state-active {
    background: #A04657 !important;
    border-color: #A04657 !important;
    margin: 0;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading mb-1">
                <i class="fas fa-truck me-2" style="color: #A04657;"></i> Daily Water Delivery
            </h2>
            <p class="text-muted mb-0" style="font-size: 14px;">Record new delivery for existing or walk-in customer</p>
        </div>
        <div class="d-flex gap-3">
            <div class="stat-card card shadow-sm d-flex flex-row align-items-center gap-3">
                <div class="stat-icon" style="background: #e3f2fd;">
                    <i class="fas fa-boxes text-primary"></i>
                </div>
                <div>
                    <div class="stat-value" style="color: #1565c0;">
                        <?php 
                        $total_stock = 0;
                        mysqli_data_seek($products_stock, 0);
                        while($ps = mysqli_fetch_assoc($products_stock)){
                            $total_stock += $ps['current_stock'];
                        }
                        echo number_format($total_stock);
                        ?>
                    </div>
                    <div class="stat-label">Stock (bottles)</div>
                </div>
            </div>
            <div class="stat-card card shadow-sm d-flex flex-row align-items-center gap-3">
                <div class="stat-icon" style="background: #fce4ec;">
                    <i class="fas fa-chart-line" style="color: #A04657;"></i>
                </div>
                <div>
                    <div class="stat-value" style="color: #A04657;">Rs <?php echo number_format($today_total['total'], 2); ?></div>
                    <div class="stat-label">Today Sales (<?php echo $today_total['bottles']; ?> bottles)</div>
                </div>
            </div>
        </div>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Delivery Form -->
        <div class="col-lg-7">
            <div class="card delivery-card shadow-sm">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2"></i> Record New Delivery
                </div>
                <div class="card-body">
                    <form method="POST" id="deliveryForm">
                        <!-- Customer Section -->
                        <div class="form-section-title">
                            <i class="fas fa-user me-2"></i> Customer
                        </div>

                        <!-- Walk-in Toggle (Admin only) -->
                        <?php if(!is_salesman()): ?>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="walkinToggle" onchange="toggleWalkin()" style="cursor: pointer;">
                                <label class="form-check-label fw-semibold" for="walkinToggle" style="cursor: pointer;">
                                    <i class="fas fa-walking me-1 text-warning"></i> Walk-in Customer
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Existing Customer -->
                        <div id="existingCustomerSection">
                            <label class="form-label fw-semibold">Search Customer <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="customerAutocomplete" class="form-control" placeholder="Type customer name, mobile or ID..." autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearCustomer()" title="Clear">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="hidden" name="customer_id" id="customerId">
                            <div id="selectedCustomerInfo" class="customer-chip" style="display: none;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="chip-label">Selected Customer</div>
                                        <div class="chip-value" id="displayCustomerName"></div>
                                        <small class="text-muted" id="displayCustomerMobile"></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Salesman</small>
                                        <strong id="displaySalesman" style="font-size:13px;">-</strong>
                                    </div>
                                </div>
                                <div class="row mt-2 pt-2" style="border-top: 1px dashed #c8e6c9;">
                                    <div class="col-6">
                                        <small class="text-muted">Previous Balance:</small>
                                        <strong id="displayPrevBalance" style="font-size:15px;color:#A04657;">Rs 0.00</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        <small class="text-muted">Empty Bottles:</small>
                                        <strong id="displayEmptyBottles" style="font-size:15px;color:#1565c0;">0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Walk-in Customer -->
                        <div id="walkinSection" style="display: none;">
                            <div class="card mb-3 border-warning" style="background: #fffbe6;">
                                <div class="card-body p-3">
                                    <h6 class="mb-3" style="color: #e65100;"><i class="fas fa-walking me-2"></i> New Walk-in Customer</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" name="walkin_name" id="walkinName" class="form-control" placeholder="Customer name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Mobile</label>
                                            <input type="text" name="walkin_mobile" id="walkinMobile" class="form-control" placeholder="03XXXXXXXXX">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Address</label>
                                            <input type="text" name="walkin_address" id="walkinAddress" class="form-control" placeholder="Address">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Salesman</label>
                                            <input type="text" name="walkin_salesman" id="walkinSalesman" class="form-control" placeholder="Salesman" value="<?php echo is_salesman() ? htmlspecialchars($_SESSION['admin_name']) : ''; ?>" <?php echo is_salesman() ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Section -->
                        <div class="form-section-title mt-4">
                            <i class="fas fa-wine-bottle me-2"></i> Product & Delivery
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                                <select name="product_id" id="productId" class="form-select" required onchange="updateProductInfo()">
                                    <option value="">-- Select Product --</option>
                                    <?php 
                                    mysqli_data_seek($products_list, 0);
                                    while($p = mysqli_fetch_assoc($products_list)): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                data-price="<?php echo $p['sale_price']; ?>" 
                                                data-stock="<?php echo $p['current_stock']; ?>"
                                                data-track-empty="<?php echo !empty($p['track_empty_bottles']) ? 1 : 0; ?>"
                                                data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
                                            <?php echo htmlspecialchars($p['product_name']); ?> — Cost: Rs <?php echo number_format($p['purchase_price'], 2); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Bottles Delivered <span class="text-danger">*</span></label>
                                <input type="number" name="bottles_delivered" id="bottlesDelivered" class="form-control" placeholder="0" required min="1" onkeyup="calculateTotal(); validateStock()" onchange="calculateTotal(); validateStock()">
                                <small id="stockHint" class="text-muted"></small>
                            </div>
                            <div class="col-md-3" id="emptyReturnedSection">
                                <label class="form-label fw-semibold">Empty Returned</label>
                                <input type="number" name="empty_bottles_returned" id="emptyReturned" class="form-control" placeholder="0" value="0" onkeyup="calculateTotal()">
                            </div>
                        </div>

                        <!-- Rate & Total -->
                        <div class="row g-3 mt-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Bottle Rate (Rs)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">Rs</span>
                                    <input type="number" step="0.01" name="bottle_rate" id="bottleRate" class="form-control" value="0" onkeyup="calculateTotal()" onchange="calculateTotal()">
                                </div>
                                <small class="text-muted">Auto-filled from product, editable</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Total Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">Rs</span>
                                    <input type="text" class="form-control fw-bold" id="totalDisplay" value="0.00" readonly style="color: #A04657; font-size: 18px;">
                                </div>
                                <input type="hidden" name="total_amount" id="totalAmount" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Cash Received (Rs)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">Rs</span>
                                    <input type="number" step="0.01" name="cash_received" id="cashReceived" class="form-control" value="0" min="0" onkeyup="calculateTotal()" onchange="calculateTotal()">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6 offset-md-3">
                                <div class="p-3 rounded-3 text-center" style="background: #fce4ec; border: 1px solid #f8bbd0;">
                                    <label class="form-label fw-bold mb-0" style="font-size: 13px; color: #A04657; text-transform: uppercase; letter-spacing: 0.3px;">New Outstanding Balance</label>
                                    <div class="d-flex align-items-center justify-content-center gap-2 mt-1">
                                        <span style="font-size: 14px; color: #666;">Rs</span>
                                        <input type="text" class="form-control fw-bold text-center border-0 bg-transparent" id="newOutstandingDisplay" value="0.00" readonly style="color: #A04657; font-size: 28px; width: 150px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mt-4">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this delivery..."></textarea>
                        </div>

                        <!-- Submit -->
                        <div class="mt-4">
                            <button type="submit" name="add_delivery" class="btn btn-primary w-100 py-2 fw-semibold" id="submitBtn" disabled style="border-radius: 10px;">
                                <i class="fas fa-save me-2"></i> Save Delivery
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-5">
            <!-- Today's Summary -->
            <div class="card delivery-card shadow-sm mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-simple me-2"></i> Today's Summary
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 rounded-4" style="background: #fce4ec;">
                                <div class="stat-value" style="color: #A04657;">Rs <?php echo number_format($today_total['total'], 2); ?></div>
                                <small class="text-muted">Total Sales</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 rounded-4" style="background: #e3f2fd;">
                                <div class="stat-value" style="color: #1565c0;"><?php echo $today_total['bottles']; ?></div>
                                <small class="text-muted">Bottles Delivered</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Levels -->
            <div class="card delivery-card shadow-sm">
                <div class="card-header">
                    <i class="fas fa-boxes me-2"></i> Current Stock Levels
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($products_stock, 0);
                                while($ps = mysqli_fetch_assoc($products_stock)): 
                                    $ratio = $ps['min_stock_level'] > 0 ? ($ps['current_stock'] / $ps['min_stock_level']) : 0;
                                ?>
                                <tr>
                                    <td class="ps-4"><?php echo htmlspecialchars($ps['product_name']); ?></td>
                                    <td class="text-center fw-bold"><?php echo number_format($ps['current_stock']); ?></td>
                                    <td class="text-center">
                                        <?php if($ps['current_stock'] == 0): ?>
                                            <span class="badge bg-danger rounded-pill">Out</span>
                                        <?php elseif($ratio <= 1): ?>
                                            <span class="badge bg-warning text-dark rounded-pill">Low</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
// Build customers JS array for autocomplete
$cust_js = [];
mysqli_data_seek($customers_list, 0);
while($c = mysqli_fetch_assoc($customers_list)){
    $cust_js[] = [
        'id' => $c['id'],
        'code' => $c['customer_code'],
        'name' => htmlspecialchars($c['customer_name'], ENT_QUOTES),
        'mobile' => htmlspecialchars($c['mobile'] ?? '', ENT_QUOTES),
        'salesman' => htmlspecialchars($c['salesman'] ?? '', ENT_QUOTES),
        'outstanding' => floatval($c['outstanding_balance'] ?? 0),
        'empties' => floatval($c['empty_bottles_balance'] ?? 0),
    ];
}
?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<?php include '../includes/footer.php'; ?>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
var customers = <?php echo json_encode($cust_js); ?>;

// DOM Elements
let isWalkin = false;
const customerInput = document.getElementById('customerAutocomplete');
const customerHidden = document.getElementById('customerId');
const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');
const displayCustomerName = document.getElementById('displayCustomerName');
const displayCustomerMobile = document.getElementById('displayCustomerMobile');
const productSelect = document.getElementById('productId');
const bottleRateInput = document.getElementById('bottleRate');
const bottlesInput = document.getElementById('bottlesDelivered');
const totalDisplaySpan = document.getElementById('totalDisplay');
const totalAmountHidden = document.getElementById('totalAmount');
const submitBtn = document.getElementById('submitBtn');
const stockHint = document.getElementById('stockHint');
const newOutstandingDisplay = document.getElementById('newOutstandingDisplay');
const cashReceived = document.getElementById('cashReceived');
const emptyReturned = document.getElementById('emptyReturned');
const displayPrevBalance = document.getElementById('displayPrevBalance');

let selectedCustomer = null;
let currentProductStock = 0;
let currentProductPrice = 0;
let currentProductTrackEmpty = true;
let currentCustomerOutstanding = 0;

// Autocomplete initialization
$(function() {
    $("#customerAutocomplete").autocomplete({
        source: function(request, response) {
            var term = request.term.toLowerCase();
            var results = $.grep(customers, function(c) {
                return c.name.toLowerCase().indexOf(term) !== -1 || (c.mobile && c.mobile.indexOf(term) !== -1) || (c.code && c.code.indexOf(term) !== -1);
            });
            response(results.slice(0, 20));
        },
        minLength: 1,
        select: function(event, ui) {
            selectedCustomer = ui.item;
            customerHidden.value = ui.item.id;
            customerInput.value = ui.item.name;
            updateCustomerInfo();
            return false;
        },
        search: function() {
            selectedCustomer = null;
            customerHidden.value = '';
            selectedCustomerInfo.style.display = 'none';
        }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
        return $("<li>")
            .append("<div><strong>" + item.name + "</strong> <span style='color:#A04657;font-weight:600;'>[" + item.code + "]</span><br><small style='color:#666;'>" + item.mobile + "</small></div>")
            .appendTo(ul);
    };
});

// Toggle between walk-in and existing customer
function toggleWalkin() {
    isWalkin = document.getElementById('walkinToggle').checked;
    document.getElementById('existingCustomerSection').style.display = isWalkin ? 'none' : 'block';
    document.getElementById('walkinSection').style.display = isWalkin ? 'block' : 'none';
    if (isWalkin) {
        customerHidden.value = 'new';
        selectedCustomerInfo.style.display = 'none';
        selectedCustomer = null;
    } else {
        customerHidden.value = selectedCustomer ? selectedCustomer.id : '';
    }
    enableSubmit();
}

// Update customer info when customer is selected
function updateCustomerInfo() {
    if(selectedCustomer) {
        displayCustomerName.innerText = selectedCustomer.name + ' [' + (selectedCustomer.code || '') + ']';
        displayCustomerMobile.innerText = 'Mobile: ' + selectedCustomer.mobile;
        document.getElementById('displaySalesman').innerText = selectedCustomer.salesman || '-';
        currentCustomerOutstanding = selectedCustomer.outstanding || 0;
        displayPrevBalance.innerText = 'Rs ' + currentCustomerOutstanding.toFixed(2);
        document.getElementById('displayEmptyBottles').innerText = selectedCustomer.empties || 0;
        selectedCustomerInfo.style.display = 'block';
        calculateTotal();
    } else {
        selectedCustomerInfo.style.display = 'none';
        currentCustomerOutstanding = 0;
    }
    enableSubmit();
}

// Clear customer selection
function clearCustomer() {
    selectedCustomer = null;
    customerHidden.value = '';
    customerInput.value = '';
    selectedCustomerInfo.style.display = 'none';
    enableSubmit();
}

// Update product info when product is selected
function updateProductInfo() {
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    if(productSelect.value) {
        currentProductPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        currentProductStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
        currentProductTrackEmpty = selectedOption.getAttribute('data-track-empty') === '1';
        
        bottleRateInput.value = currentProductPrice;
        
        toggleEmptyReturned();
        validateStock();
        calculateTotal();
    } else {
        currentProductStock = 0;
        currentProductPrice = 0;
        currentProductTrackEmpty = true;
        bottleRateInput.value = 0;
        toggleEmptyReturned();
        calculateTotal();
    }
    enableSubmit();
}

// Show/hide "Empty Returned" field based on product empty tracking
function toggleEmptyReturned() {
    const section = document.getElementById('emptyReturnedSection');
    if(!section) return;
    if(currentProductTrackEmpty === false) {
        section.style.display = 'none';
        emptyReturned.value = 0;
    } else {
        section.style.display = '';
    }
}

// Validate stock availability
function validateStock() {
    const bottles = parseInt(bottlesInput.value) || 0;
    if(currentProductStock === 0 && productSelect.value) {
        stockHint.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> Select a product first';
        stockHint.style.color = '#dc3545';
        return false;
    }
    if(bottles > currentProductStock) {
        stockHint.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> Only ' + currentProductStock + ' bottles available';
        stockHint.style.color = '#dc3545';
        return false;
    } else if(bottles > 0 && bottles <= currentProductStock) {
        stockHint.innerHTML = '<i class="fas fa-check-circle text-success"></i> Stock OK — ' + currentProductStock + ' available';
        stockHint.style.color = '#28a745';
        return true;
    } else {
        stockHint.innerHTML = '';
        return false;
    }
}

function calculateTotal() {
    const bottles = parseInt(bottlesInput.value) || 0;
    const rate = parseFloat(bottleRateInput.value) || 0;
    const total = bottles * rate;
    totalDisplaySpan.value = total.toFixed(2);
    totalAmountHidden.value = total.toFixed(2);

    let outstanding = parseFloat(selectedCustomer ? (selectedCustomer.outstanding || currentCustomerOutstanding) : 0);
    if (isWalkin) outstanding = 0;
    const cash = parseFloat(cashReceived.value) || 0;
    const newOutstanding = outstanding + total - cash;
    newOutstandingDisplay.value = newOutstanding.toFixed(2);

    enableSubmit();
    validateStock();
}

function enableSubmit() {
    const hasCustomer = isWalkin ? document.getElementById('walkinName').value.trim() !== '' : customerHidden.value !== '';
    const hasProduct = productSelect.value !== '';
    const hasBottles = parseInt(bottlesInput.value) > 0;
    const hasRate = parseFloat(bottleRateInput.value) > 0;
    const stockOk = (parseInt(bottlesInput.value) || 0) <= currentProductStock;
    submitBtn.disabled = !(hasCustomer && hasProduct && hasBottles && hasRate && stockOk);
}

// Event Listeners
bottlesInput.addEventListener('keyup', function() {
    calculateTotal();
    validateStock();
});
bottlesInput.addEventListener('change', function() {
    calculateTotal();
    validateStock();
});

bottleRateInput.addEventListener('keyup', calculateTotal);
bottleRateInput.addEventListener('change', calculateTotal);

document.getElementById('walkinName').addEventListener('keyup', enableSubmit);

calculateTotal();
</script>
