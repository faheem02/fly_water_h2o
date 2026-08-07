<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';
$error = '';
$success = '';

// Get suppliers for dropdown
$suppliers_query = "SELECT id, supplier_name FROM suppliers WHERE status = 'Active' ORDER BY supplier_name";
$suppliers_result = mysqli_query($conn, $suppliers_query);

// Get materials with purchase prices
$materials_query = "SELECT id, material_name, unit, purchase_price FROM raw_materials WHERE status = 'Active' ORDER BY material_name";
$materials_result = mysqli_query($conn, $materials_query);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_purchase'])){
    $supplier_id = intval($_POST['supplier_id']);
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $invoice_no = mysqli_real_escape_string($conn, $_POST['invoice_no']);
    $subtotal = floatval($_POST['subtotal']);
    $discount_percent = floatval($_POST['discount_percent']);
    $discount_amount = floatval($_POST['discount_amount']);
    $total_amount = floatval($_POST['total_amount']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $paid_amount = floatval($_POST['paid_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Calculate credit amount
    $credit_amount = $total_amount - $paid_amount;
    
    // Validation
    if($supplier_id <= 0){
        $error = "Please select a supplier!";
    } elseif($subtotal <= 0){
        $error = "Please add at least one item!";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into purchases table
            $insert_query = "INSERT INTO raw_material_purchases 
                            (purchase_date, invoice_no, supplier_id, subtotal, discount_percent, discount_amount, total_amount, payment_status, paid_amount, credit_amount, notes, created_datetime) 
                            VALUES 
                            ('$purchase_date', '$invoice_no', $supplier_id, $subtotal, $discount_percent, $discount_amount, $total_amount, '$payment_status', $paid_amount, $credit_amount, '$notes', NOW())";
            
            if(!mysqli_query($conn, $insert_query)){
                throw new Exception("Error saving purchase: " . mysqli_error($conn));
            }
            
            $purchase_id = mysqli_insert_id($conn);
            
            // Get all items from POST
            $material_ids = $_POST['material_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $unit_prices = $_POST['unit_price'] ?? [];
            
            // Insert items and update stock
            for($i = 0; $i < count($material_ids); $i++){
                $material_id = intval($material_ids[$i]);
                $quantity = floatval($quantities[$i]);
                $unit_price = floatval($unit_prices[$i]);
                $total_price = $quantity * $unit_price;
                
                if($quantity > 0){
                    // Insert item
                    $item_query = "INSERT INTO raw_material_purchase_items 
                                  (purchase_id, material_id, quantity, unit_price, total_price) 
                                  VALUES 
                                  ($purchase_id, $material_id, $quantity, $unit_price, $total_price)";
                    
                    if(!mysqli_query($conn, $item_query)){
                        throw new Exception("Error saving item: " . mysqli_error($conn));
                    }
                    
                    // Update current stock
                    $update_stock = "UPDATE raw_materials SET current_stock = current_stock + $quantity WHERE id = $material_id";
                    if(!mysqli_query($conn, $update_stock)){
                        throw new Exception("Error updating stock: " . mysqli_error($conn));
                    }
                    
                    // Get running stock after update
                    $stock_query = "SELECT current_stock FROM raw_materials WHERE id = $material_id";
                    $stock_result = mysqli_query($conn, $stock_query);
                    $stock_row = mysqli_fetch_assoc($stock_result);
                    $running_stock = $stock_row['current_stock'];
                    
                    // Add to stock ledger
                    $stock_ledger = "INSERT INTO raw_material_stock_ledger 
                                    (material_id, transaction_date, transaction_type, reference_type, reference_id, quantity_in, running_stock, description, created_datetime) 
                                    VALUES 
                                    ($material_id, NOW(), 'PURCHASE', 'purchase', $purchase_id, $quantity, $running_stock, 'Purchase from supplier - Invoice: " . ($invoice_no ? $invoice_no : $purchase_id) . "', NOW())";
                    
                    if(!mysqli_query($conn, $stock_ledger)){
                        throw new Exception("Error updating stock ledger: " . mysqli_error($conn));
                    }
                }
            }
            
            // Update supplier balance and ledger
            $supplier_query = "SELECT current_balance FROM suppliers WHERE id = $supplier_id";
            $supplier_result = mysqli_query($conn, $supplier_query);
            $supplier_row = mysqli_fetch_assoc($supplier_result);
            $old_balance = $supplier_row['current_balance'];
            $new_balance = $old_balance + $credit_amount;
            
            $update_supplier = "UPDATE suppliers SET current_balance = $new_balance WHERE id = $supplier_id";
            if(!mysqli_query($conn, $update_supplier)){
                throw new Exception("Error updating supplier balance: " . mysqli_error($conn));
            }
            
            // Add to supplier ledger (Credit for purchase)
            $ledger_desc = "Purchase - Invoice: " . ($invoice_no ? $invoice_no : "PUR-$purchase_id");
            $ledger_query = "INSERT INTO supplier_ledger 
                            (supplier_id, transaction_date, description, credit_amount, running_balance, reference_id, reference_type) 
                            VALUES 
                            ($supplier_id, NOW(), '$ledger_desc', $credit_amount, $new_balance, $purchase_id, 'purchase')";
            
            if(!mysqli_query($conn, $ledger_query)){
                throw new Exception("Error updating supplier ledger: " . mysqli_error($conn));
            }
            
            // If payment made, add to payments table and ledger
            if($paid_amount > 0){
                $payment_query = "INSERT INTO supplier_payments 
                                 (supplier_id, purchase_id, payment_amount, payment_type, notes, payment_datetime, created_datetime) 
                                 VALUES 
                                 ($supplier_id, $purchase_id, $paid_amount, 'Cash', 'Payment against purchase', NOW(), NOW())";
                
                if(!mysqli_query($conn, $payment_query)){
                    throw new Exception("Error recording payment: " . mysqli_error($conn));
                }
                
                // Update supplier balance again (reduce by payment)
                $new_balance_after_payment = $new_balance - $paid_amount;
                $update_supplier_payment = "UPDATE suppliers SET current_balance = $new_balance_after_payment WHERE id = $supplier_id";
                mysqli_query($conn, $update_supplier_payment);
                
                // Add debit to ledger for payment
                $payment_ledger = "INSERT INTO supplier_ledger 
                                  (supplier_id, transaction_date, description, debit_amount, running_balance, reference_id, reference_type) 
                                  VALUES 
                                  ($supplier_id, NOW(), 'Payment against purchase - Invoice: " . ($invoice_no ? $invoice_no : "PUR-$purchase_id") . "', $paid_amount, $new_balance_after_payment, $purchase_id, 'payment')";
                mysqli_query($conn, $payment_ledger);
            }
            
            mysqli_commit($conn);
            $message = "<div class='alert alert-success'>Purchase saved successfully! Redirecting...</div>";
            echo "<script>setTimeout(function(){ window.location.href = 'purchase_details.php?id=$purchase_id'; }, 1500);</script>";
            
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
    background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%);
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
.item-row {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 3px solid #A04657;
}
.item-row .btn-remove {
    margin-top: 31px;
}
.total-card {
    background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
}
.discount-card {
    background: #fff3cd;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid #ffc107;
}
.supplier-info {
    background: #e8f4f8;
    padding: 10px 15px;
    border-radius: 10px;
    margin-top: 10px;
    display: none;
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
                <i class="fas fa-shopping-cart me-2" style="color: #A04657;"></i> Raw Material Purchase
            </h2>
            <p class="text-muted mb-0">Record new purchase from supplier with discount options</p>
        </div>
        <div>
            <a href="purchase_list.php" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-list me-2"></i> Purchase History
            </a>
        </div>
    </div>

    <?php echo $message; ?>
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" id="purchaseForm">
        <!-- Purchase Information Card -->
        <div class="card form-card shadow-sm mb-4">
            <div class="form-header">
                <h4><i class="fas fa-info-circle me-2"></i> Purchase Information</h4>
                <p>Enter purchase and supplier details</p>
            </div>
            <div class="form-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold required-field">Supplier</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php while($supplier = mysqli_fetch_assoc($suppliers_result)): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Purchase Date</label>
                        <input type="datetime-local" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Invoice Number</label>
                        <input type="text" name="invoice_no" class="form-control" placeholder="Optional - Supplier invoice #">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Purchase Items Card -->
        <div class="card form-card shadow-sm mb-4">
            <div class="form-header">
                <h4><i class="fas fa-boxes me-2"></i> Purchase Items</h4>
                <p>Add materials being purchased</p>
            </div>
            <div class="form-body">
                <div class="text-end mb-3">
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="addItemRow()">
                        <i class="fas fa-plus-circle me-2"></i> Add Item
                    </button>
                </div>
                <div id="itemsContainer">
                    <!-- Items will be added here dynamically -->
                </div>
            </div>
        </div>
        
        <!-- Totals Section -->
        <div class="row g-4">
            <div class="col-md-6">
                <!-- Discount Card -->
                <div class="discount-card">
                    <h5><i class="fas fa-tag me-2"></i> Discount Options</h5>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Discount %</label>
                            <input type="number" name="discount_percent" id="discount_percent" class="form-control" step="0.01" value="0" onkeyup="calculateTotal()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Discount Amount (Rs)</label>
                            <input type="number" name="discount_amount" id="discount_amount" class="form-control" step="0.01" value="0" onkeyup="calculateTotal()">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i> Both discount % and amount can be applied together</small>
                </div>
                
                <!-- Payment Information Card -->
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Status</label>
                            <select name="payment_status" id="payment_status" class="form-select" onchange="togglePaymentFields()">
                                <option value="Credit">Credit (Full Credit)</option>
                                <option value="Partial">Partial Payment</option>
                                <option value="Paid">Paid (Full Payment)</option>
                            </select>
                        </div>
                        
                        <div id="paymentFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Paid Amount (Rs)</label>
                                <input type="number" name="paid_amount" id="paid_amount" class="form-control" step="0.01" value="0" onkeyup="updateCredit()">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this purchase..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Invoice Summary Card -->
                <div class="total-card">
                    <h4 class="mb-3"><i class="fas fa-file-invoice me-2"></i> Invoice Summary</h4>
                    <hr style="background: white; opacity: 0.3;">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="display_subtotal">Rs 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount (%):</span>
                        <strong id="display_discount_percent">0%</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount (Amount):</span>
                        <strong id="display_discount_amount">Rs 0.00</strong>
                    </div>
                    <hr style="background: white; opacity: 0.3;">
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong>Total Amount:</strong></span>
                        <strong id="display_total">Rs 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Credit Amount:</span>
                        <strong id="display_credit">Rs 0.00</strong>
                    </div>
                </div>
                
                <input type="hidden" name="subtotal" id="subtotal" value="0">
                <input type="hidden" name="total_amount" id="total_amount" value="0">
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="text-center mt-4 mb-5">
            <button type="submit" name="save_purchase" class="btn btn-success rounded-pill px-5 py-2 me-3">
                <i class="fas fa-save me-2"></i> Save Purchase
            </button>
            <button type="button" class="btn btn-danger rounded-pill px-5 py-2" onclick="resetForm()">
                <i class="fas fa-trash-alt me-2"></i> Reset
            </button>
        </div>
    </form>
    
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let itemCount = 0;
let materials = <?php 
    $materials_array = [];
    while($material = mysqli_fetch_assoc($materials_result)){
        $materials_array[] = $material;
    }
    echo json_encode($materials_array);
?>;

function addItemRow(material_id = '', quantity = '', unit_price = '') {
    itemCount++;
    const container = document.getElementById('itemsContainer');
    const rowDiv = document.createElement('div');
    rowDiv.className = 'item-row';
    rowDiv.id = `item_${itemCount}`;
    
    let materialOptions = '<option value="">Select Material</option>';
    materials.forEach(material => {
        const selected = material.id == material_id ? 'selected' : '';
        materialOptions += `<option value="${material.id}" data-price="${material.purchase_price}" ${selected}>${material.material_name} (${material.unit}) - Purchase Price: Rs ${parseFloat(material.purchase_price).toFixed(2)}</option>`;
    });
    
    rowDiv.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-4 mb-2">
                <label class="form-label fw-semibold">Material</label>
                <select name="material_id[]" class="form-select" onchange="setUnitPrice(this)" required>
                    ${materialOptions}
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label fw-semibold">Quantity</label>
                <input type="number" name="quantity[]" class="form-control" step="0.01" value="${quantity}" onkeyup="calculateTotal()" required>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label fw-semibold">Unit Price (Rs)</label>
                <input type="number" name="unit_price[]" class="form-control" step="0.01" value="${unit_price}" onkeyup="calculateTotal()" required>
            </div>
            <div class="col-md-2 mb-2">
                <button type="button" class="btn btn-danger w-100" onclick="removeItemRow(${itemCount})">
                    <i class="fas fa-trash-alt me-1"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(rowDiv);
    calculateTotal();
}

function setUnitPrice(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const purchasePrice = selectedOption.getAttribute('data-price');
    const row = selectElement.closest('.item-row');
    const unitPriceInput = row.querySelector('input[name="unit_price[]"]');
    
    if(purchasePrice && parseFloat(purchasePrice) > 0) {
        unitPriceInput.value = purchasePrice;
    }
    calculateTotal();
}

function removeItemRow(id) {
    const row = document.getElementById(`item_${id}`);
    if(row) row.remove();
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    
    // Calculate subtotal from all items
    const quantities = document.getElementsByName('quantity[]');
    const unitPrices = document.getElementsByName('unit_price[]');
    
    for(let i = 0; i < quantities.length; i++) {
        const qty = parseFloat(quantities[i].value) || 0;
        const price = parseFloat(unitPrices[i].value) || 0;
        subtotal += qty * price;
    }
    
    // Get discounts
    const discountPercent = parseFloat(document.getElementById('discount_percent').value) || 0;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    
    // Calculate total with both discounts
    let total = subtotal;
    if(discountPercent > 0) {
        total = total - (total * discountPercent / 100);
    }
    if(discountAmount > 0) {
        total = total - discountAmount;
    }
    if(total < 0) total = 0;
    
    // Update display
    document.getElementById('display_subtotal').innerText = 'Rs ' + subtotal.toFixed(2);
    document.getElementById('display_discount_percent').innerText = discountPercent > 0 ? discountPercent + '%' : '0%';
    document.getElementById('display_discount_amount').innerText = 'Rs ' + discountAmount.toFixed(2);
    document.getElementById('display_total').innerText = 'Rs ' + total.toFixed(2);
    
    // Update hidden fields
    document.getElementById('subtotal').value = subtotal;
    document.getElementById('total_amount').value = total;
    
    updateCredit();
}

function updateCredit() {
    const total = parseFloat(document.getElementById('total_amount').value) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const credit = total - paid;
    document.getElementById('display_credit').innerText = 'Rs ' + credit.toFixed(2);
}

function togglePaymentFields() {
    const status = document.getElementById('payment_status').value;
    const paymentFields = document.getElementById('paymentFields');
    const paidAmount = document.getElementById('paid_amount');
    const total = parseFloat(document.getElementById('total_amount').value) || 0;
    
    if(status === 'Paid') {
        paymentFields.style.display = 'block';
        paidAmount.value = total;
        paidAmount.readOnly = true;
        updateCredit();
    } else if(status === 'Partial') {
        paymentFields.style.display = 'block';
        paidAmount.readOnly = false;
        paidAmount.value = 0;
        updateCredit();
    } else {
        paymentFields.style.display = 'none';
        paidAmount.value = 0;
        updateCredit();
    }
}

function resetForm() {
    document.getElementById('purchaseForm').reset();
    document.getElementById('itemsContainer').innerHTML = '';
    itemCount = 0;
    addItemRow();
    calculateTotal();
    togglePaymentFields();
}

// Add first empty row on page load
addItemRow();
</script>

<?php include '../includes/footer.php'; ?>