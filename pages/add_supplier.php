<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $ntn_no = mysqli_real_escape_string($conn, $_POST['ntn_no']);
    $opening_balance = floatval($_POST['opening_balance']);
    $status = $_POST['status'];
    $datetime = date('Y-m-d H:i:s');
    
    if (empty($supplier_name) || empty($mobile)) {
        $message = "<div class='alert alert-danger'>Supplier Name and Mobile are required!</div>";
    } else {
        $query = "INSERT INTO suppliers (supplier_name, contact_person, mobile, phone, email, address, ntn_no, opening_balance, current_balance, status, created_datetime) 
                  VALUES ('$supplier_name', '$contact_person', '$mobile', '$phone', '$email', '$address', '$ntn_no', $opening_balance, $opening_balance, '$status', '$datetime')";
        
        if (mysqli_query($conn, $query)) {
            $supplier_id = mysqli_insert_id($conn);
            
            if ($opening_balance != 0) {
                $description = "Opening Balance";
                $debit = $opening_balance < 0 ? abs($opening_balance) : 0;
                $credit = $opening_balance > 0 ? $opening_balance : 0;
                mysqli_query($conn, "INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_type) 
                                    VALUES ($supplier_id, '$datetime', '$description', $debit, $credit, $opening_balance, 'opening')");
            }
            
            $message = "<div class='alert alert-success'>Supplier added successfully! Redirecting...</div>";
            echo "<script>setTimeout(function(){ window.location.href = 'suppliers.php'; }, 1500);</script>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="main-wrapper">
<div class="container-fluid p-4">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-user-plus me-2" style="color: #A04657;"></i> Add New Supplier
        </h2>
        <a href="suppliers.php" class="btn btn-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Back to Suppliers
        </a>
    </div>

    <?php echo $message; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2" style="color: #A04657;"></i> Supplier Information</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="Enter supplier name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Contact person name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Mobile Number *</label>
                        <input type="text" name="mobile" class="form-control" placeholder="e.g., 03XXXXXXXXX" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control" placeholder="Landline number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Full address"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">NTN Number</label>
                        <input type="text" name="ntn_no" class="form-control" placeholder="NTN number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Opening Balance</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs</span>
                            <input type="number" name="opening_balance" class="form-control" step="0.01" value="0">
                        </div>
                        <small class="text-muted">Positive = Credit (Supplier gave us credit) | Negative = Advance (We paid advance)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <hr>
                        <button type="submit" name="add_supplier" class="btn btn-primary rounded-pill px-5">
                            <i class="fas fa-save me-2"></i> Save Supplier
                        </button>
                        <button type="reset" class="btn btn-secondary rounded-pill px-5">
                            <i class="fas fa-undo me-2"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>