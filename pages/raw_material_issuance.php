<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';
$error = '';
$success = '';

// Get all raw materials with sufficient stock
$materials_query = "SELECT id, material_name, unit, current_stock, purchase_price, sale_price 
                    FROM raw_materials 
                    WHERE status = 'Active' AND current_stock > 0 
                    ORDER BY material_name";
$materials_result = mysqli_query($conn, $materials_query);

$materials_array = [];
while($material = mysqli_fetch_assoc($materials_result)){
    $materials_array[] = $material;
}

// Handle multi-product issuance form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['issue_materials'])){
    $material_ids = $_POST['material_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $purposes = $_POST['purpose'] ?? [];
    $notes = mysqli_real_escape_string($conn, $_POST['global_notes'] ?? '');
    $total_items = 0;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    $has_error = false;
    
    try {
        for($i = 0; $i < count($material_ids); $i++){
            $material_id = intval($material_ids[$i]);
            $quantity = floatval($quantities[$i]);
            $purpose = mysqli_real_escape_string($conn, $purposes[$i]);
            
            if($quantity <= 0) continue;
            
            // Get current stock
            $stock_query = "SELECT current_stock, material_name, unit FROM raw_materials WHERE id = $material_id";
            $stock_result = mysqli_query($conn, $stock_query);
            $stock_row = mysqli_fetch_assoc($stock_result);
            
            if(!$stock_row){
                throw new Exception("Material not found for ID: $material_id");
            }
            
            if($quantity > $stock_row['current_stock']){
                throw new Exception("Insufficient stock for " . $stock_row['material_name'] . "! Available: " . $stock_row['current_stock'] . " " . $stock_row['unit']);
            }
            
            // Insert issuance record
            $insert_query = "INSERT INTO raw_material_issuance 
                            (issuance_date, material_id, quantity, purpose, notes, created_datetime) 
                            VALUES 
                            (NOW(), $material_id, $quantity, '$purpose', '$notes', NOW())";
            
            if(!mysqli_query($conn, $insert_query)){
                throw new Exception("Error recording issuance: " . mysqli_error($conn));
            }
            
            $issuance_id = mysqli_insert_id($conn);
            
            // Update current stock
            $new_stock = $stock_row['current_stock'] - $quantity;
            $update_stock = "UPDATE raw_materials SET current_stock = $new_stock WHERE id = $material_id";
            
            if(!mysqli_query($conn, $update_stock)){
                throw new Exception("Error updating stock: " . mysqli_error($conn));
            }
            
            // Add to stock ledger
            $ledger_query = "INSERT INTO raw_material_stock_ledger 
                            (material_id, transaction_date, transaction_type, reference_type, reference_id, quantity_out, running_stock, description, created_datetime) 
                            VALUES 
                            ($material_id, NOW(), 'ISSUANCE', 'issuance', $issuance_id, $quantity, $new_stock, 'Issued for: $purpose', NOW())";
            
            if(!mysqli_query($conn, $ledger_query)){
                throw new Exception("Error updating stock ledger: " . mysqli_error($conn));
            }
            
            $total_items++;
        }
        
        mysqli_commit($conn);
        
        if($total_items > 0){
            $message = "<div class='alert alert-success'>" . $total_items . " material(s) issued successfully! Stock updated.</div>";
            echo "<script>setTimeout(function(){ window.location.href = 'raw_material_issuance.php'; }, 2000);</script>";
        } else {
            $message = "<div class='alert alert-warning'>No items to issue. Please add at least one material.</div>";
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "<div class='alert alert-danger'>" . $e->getMessage() . "</div>";
    }
}

// Get issuance history
$history_query = "SELECT i.*, m.material_name, m.unit, m.purchase_price, m.sale_price
                  FROM raw_material_issuance i 
                  JOIN raw_materials m ON i.material_id = m.id 
                  ORDER BY i.issuance_date DESC 
                  LIMIT 100";
$history_result = mysqli_query($conn, $history_query);
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
    background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
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
.quantity-issued {
    color: #dc3545;
    font-weight: bold;
}
.item-row {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 3px solid #fd7e14;
    position: relative;
}
.item-row .btn-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
}
.required-field::after {
    content: "*";
    color: #dc3545;
    margin-left: 4px;
}
.total-summary {
    background: #e8f4f8;
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-heading">
                <i class="fas fa-sign-out-alt me-2" style="color: #fd7e14;"></i> Raw Material Issuance
            </h2>
            <p class="text-muted mb-0">Issue multiple raw materials for production or other purposes</p>
        </div>
    </div>

    <?php echo $message; ?>
    
    <div class="row g-4">
        <!-- Multi-Product Issuance Form Card -->
        <div class="col-md-6">
            <div class="card form-card shadow-sm">
                <div class="form-header">
                    <h4><i class="fas fa-pen-alt me-2"></i> Issue Materials</h4>
                    <p>Add multiple materials to issue at once</p>
                </div>
                <div class="form-body">
                    <form method="POST" id="issuanceForm">
                        <div id="itemsContainer">
                            <!-- Items will be added here dynamically -->
                        </div>
                        
                        <div class="text-center my-3">
                            <button type="button" class="btn btn-primary rounded-pill px-4" onclick="addItemRow()">
                                <i class="fas fa-plus-circle me-2"></i> Add Another Material
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">General Notes (Optional)</label>
                            <textarea name="global_notes" class="form-control" rows="2" placeholder="Any additional information about this issuance..."></textarea>
                        </div>
                        
                        <div id="summarySection" class="total-summary" style="display: none;">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-boxes me-2"></i> Total Items:</span>
                                <strong id="totalItemsCount">0</strong>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span><i class="fas fa-cubes me-2"></i> Total Quantity:</span>
                                <strong id="totalQuantityCount">0</strong>
                            </div>
                        </div>
                        
                        <button type="submit" name="issue_materials" class="btn btn-warning w-100 rounded-pill py-2 mt-3">
                            <i class="fas fa-sign-out-alt me-2"></i> Issue All Materials
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Issuance History Card -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0"><i class="fas fa-history me-2" style="color: #A04657;"></i> Recent Issuance History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover history-table mb-0" id="historyTable">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Material</th>
                                    <th>Quantity</th>
                                    <th>Purpose</th>
                                    <th>Value (Rs)</th>
                                </tr>
                            </thead>
                        
                            <tbody>
                                <?php if(mysqli_num_rows($history_result) > 0): ?>
                        
                                    <?php while($history = mysqli_fetch_assoc($history_result)): 
                                        $value = $history['quantity'] * $history['purchase_price'];
                                    ?>
                        
                                        <tr>
                                            <!-- Date -->
                                            <td class="text-nowrap">
                                                <?php echo date('d-m-Y h:i A', strtotime($history['issuance_date'])); ?>
                                            </td>
                        
                                            <!-- Material -->
                                            <td>
                                                <strong>
                                                    <?php echo htmlspecialchars($history['material_name']); ?>
                                                </strong>
                                            </td>
                        
                                            <!-- Quantity -->
                                            <td class="quantity-issued">
                                                <i class="fas fa-minus-circle me-1 text-danger"></i>
                                                <?php echo number_format($history['quantity'], 2); ?>
                                                <?php echo htmlspecialchars($history['unit']); ?>
                                            </td>
                        
                                            <!-- Purpose -->
                                            <td>
                                                <?php 
                                                $purpose_icon = '';
                        
                                                switch($history['purpose']) {
                                                    case 'Production':
                                                        $purpose_icon = '🏭';
                                                        break;
                        
                                                    case 'Wastage':
                                                        $purpose_icon = '🗑️';
                                                        break;
                        
                                                    case 'Sample':
                                                        $purpose_icon = '🧪';
                                                        break;
                        
                                                    case 'Maintenance':
                                                        $purpose_icon = '🔧';
                                                        break;
                        
                                                    default:
                                                        $purpose_icon = '📋';
                                                }
                        
                                                echo $purpose_icon . ' ' . htmlspecialchars($history['purpose']);
                                                ?>
                                            </td>
                        
                                            <!-- Value -->
                                            <td class="text-danger fw-bold">
                                                Rs <?php echo number_format($value, 2); ?>
                                            </td>
                                        </tr>
                        
                                    <?php endwhile; ?>
                        
                                <?php else: ?>
                        
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-sign-out-alt fa-3x mb-3 d-block"></i>
                                            No issuance records found.
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
let itemCount = 0;
let materials = <?php echo json_encode($materials_array); ?>;

$(document).ready(function() {
    <?php if(mysqli_num_rows($history_result) > 0): ?>
    $('#historyTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search History:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ issuances"
        }
    });
    <?php endif; ?>
    
    // Add first empty row on page load
    addItemRow();
});

function addItemRow(material_id = '', quantity = '', purpose = '') {
    itemCount++;
    const container = document.getElementById('itemsContainer');
    const rowDiv = document.createElement('div');
    rowDiv.className = 'item-row';
    rowDiv.id = `item_${itemCount}`;
    
    let materialOptions = '<option value="">-- Select Material --</option>';
    materials.forEach(material => {
        const selected = material.id == material_id ? 'selected' : '';
        const disabled = material.current_stock <= 0 ? 'disabled' : '';
        materialOptions += `<option value="${material.id}" data-stock="${material.current_stock}" data-unit="${material.unit}" data-price="${material.purchase_price}" ${selected} ${disabled}>
                                ${material.material_name} (${material.unit}) - Stock: ${material.current_stock}
                            </option>`;
    });
    
    let purposeOptions = `
        <option value="">-- Select Purpose --</option>
        <option value="Production" ${purpose == 'Production' ? 'selected' : ''}>🏭 Production</option>
        <option value="Wastage" ${purpose == 'Wastage' ? 'selected' : ''}>🗑️ Wastage</option>
        <option value="Sample" ${purpose == 'Sample' ? 'selected' : ''}>🧪 Sample / Testing</option>
        <option value="Maintenance" ${purpose == 'Maintenance' ? 'selected' : ''}>🔧 Maintenance</option>
        <option value="Other" ${purpose == 'Other' ? 'selected' : ''}>📋 Other</option>
    `;
    
    rowDiv.innerHTML = `
        <button type="button" class="btn btn-sm btn-danger btn-remove" onclick="removeItemRow(${itemCount})">
            <i class="fas fa-trash-alt"></i>
        </button>
        <div class="row">
            <div class="col-md-5 mb-2">
                <label class="form-label fw-semibold small">Material</label>
                <select name="material_id[]" class="form-select form-select-sm" onchange="updateStockInfo(this, ${itemCount})" required>
                    ${materialOptions}
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label fw-semibold small">Quantity</label>
                <input type="number" name="quantity[]" class="form-control form-control-sm" step="0.01" value="${quantity}" onkeyup="validateItemQuantity(this, ${itemCount})" required>
                <small class="text-danger" id="error_${itemCount}"></small>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label fw-semibold small">Purpose</label>
                <select name="purpose[]" class="form-select form-select-sm" required>
                    ${purposeOptions}
                </select>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <small class="text-muted" id="stock_info_${itemCount}"></small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-info" id="value_info_${itemCount}"></small>
            </div>
        </div>
    `;
    
    container.appendChild(rowDiv);
    updateSummary();
}

function removeItemRow(id) {
    const row = document.getElementById(`item_${id}`);
    if(row) row.remove();
    updateSummary();
}

function updateStockInfo(selectElement, itemId) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock');
    const unit = selectedOption.getAttribute('data-unit');
    const price = selectedOption.getAttribute('data-price');
    
    const stockInfoSpan = document.getElementById(`stock_info_${itemId}`);
    const valueInfoSpan = document.getElementById(`value_info_${itemId}`);
    const quantityInput = document.querySelector(`#item_${itemId} input[name="quantity[]"]`);
    
    if(stock && unit) {
        stockInfoSpan.innerHTML = `<i class="fas fa-boxes me-1"></i> Available: ${stock} ${unit}`;
        
        if(parseFloat(stock) <= 10) {
            stockInfoSpan.style.color = '#dc3545';
        } else if(parseFloat(stock) <= 50) {
            stockInfoSpan.style.color = '#ffc107';
        } else {
            stockInfoSpan.style.color = '#28a745';
        }
        
        // Update value info when quantity changes
        if(quantityInput && price) {
            quantityInput.addEventListener('keyup', function() {
                const qty = parseFloat(this.value) || 0;
                const totalValue = qty * parseFloat(price);
                if(totalValue > 0) {
                    valueInfoSpan.innerHTML = `<i class="fas fa-rupee-sign me-1"></i> Value: Rs ${totalValue.toFixed(2)}`;
                } else {
                    valueInfoSpan.innerHTML = '';
                }
                updateSummary();
            });
            
            const currentQty = parseFloat(quantityInput.value) || 0;
            if(currentQty > 0 && price) {
                valueInfoSpan.innerHTML = `<i class="fas fa-rupee-sign me-1"></i> Value: Rs ${(currentQty * parseFloat(price)).toFixed(2)}`;
            }
        }
    }
    
    validateItemQuantity(quantityInput, itemId);
}

function validateItemQuantity(inputElement, itemId) {
    const row = document.getElementById(`item_${itemId}`);
    const selectElement = row.querySelector('select[name="material_id[]"]');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const availableStock = parseFloat(selectedOption.getAttribute('data-stock')) || 0;
    const quantity = parseFloat(inputElement.value) || 0;
    const errorSpan = document.getElementById(`error_${itemId}`);
    
    if(quantity > availableStock) {
        errorSpan.innerHTML = '<i class="fas fa-times-circle me-1"></i> Exceeds available stock!';
        inputElement.classList.add('is-invalid');
        return false;
    } else if(quantity <= 0) {
        errorSpan.innerHTML = '';
        inputElement.classList.remove('is-invalid');
        return false;
    } else {
        errorSpan.innerHTML = '<i class="fas fa-check-circle me-1" style="color:#28a745"></i> Valid';
        inputElement.classList.remove('is-invalid');
        inputElement.classList.add('is-valid');
        return true;
    }
}

function updateSummary() {
    let totalItems = 0;
    let totalQuantity = 0;
    
    const quantities = document.getElementsByName('quantity[]');
    for(let i = 0; i < quantities.length; i++) {
        const qty = parseFloat(quantities[i].value) || 0;
        if(qty > 0) {
            totalItems++;
            totalQuantity += qty;
        }
    }
    
    const summarySection = document.getElementById('summarySection');
    if(totalItems > 0) {
        summarySection.style.display = 'block';
        document.getElementById('totalItemsCount').innerText = totalItems;
        document.getElementById('totalQuantityCount').innerText = totalQuantity.toFixed(2);
    } else {
        summarySection.style.display = 'none';
    }
}

// Validate all items before form submission
document.getElementById('issuanceForm').addEventListener('submit', function(e) {
    let hasError = false;
    const quantities = document.getElementsByName('quantity[]');
    const materialSelects = document.getElementsByName('material_id[]');
    
    for(let i = 0; i < quantities.length; i++) {
        const qty = parseFloat(quantities[i].value) || 0;
        const materialId = materialSelects[i].value;
        
        if(materialId && qty <= 0) {
            alert('Please enter valid quantity for all selected materials');
            e.preventDefault();
            return false;
        }
        
        if(materialId && qty > 0) {
            const selectedOption = materialSelects[i].options[materialSelects[i].selectedIndex];
            const availableStock = parseFloat(selectedOption.getAttribute('data-stock')) || 0;
            if(qty > availableStock) {
                alert('Quantity exceeds available stock for one or more items!');
                e.preventDefault();
                return false;
            }
        }
    }
    
    if(document.querySelectorAll('select[name="material_id[]"]:valid').length === 0) {
        alert('Please add at least one material to issue');
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>