<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

// Get supplier ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: suppliers.php");
    exit();
}

$supplier_id = $_GET['id'];

// Fetch supplier data
$query = "SELECT * FROM suppliers WHERE id = $supplier_id";
$result = mysqli_query($conn, $query);
$supplier = mysqli_fetch_assoc($result);

if(!$supplier){
    header("Location: suppliers.php");
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $ntn_no = mysqli_real_escape_string($conn, $_POST['ntn_no']);
    $status = $_POST['status'];
    
    if(empty($supplier_name) || empty($mobile)){
        $error = "Supplier Name and Mobile are required!";
    } else {
        $update_query = "UPDATE suppliers SET 
                        supplier_name = '$supplier_name',
                        contact_person = '$contact_person',
                        mobile = '$mobile',
                        phone = '$phone',
                        email = '$email',
                        address = '$address',
                        ntn_no = '$ntn_no',
                        status = '$status'
                        WHERE id = $supplier_id";
        
        if(mysqli_query($conn, $update_query)){
            $success = "Supplier updated successfully!";
            echo "<script>setTimeout(function(){ window.location.href = 'suppliers.php'; }, 1500);</script>";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.form-card {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: none;
}
.form-header {
    background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
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
.info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid #17a2b8;
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
                <i class="fas fa-edit me-2" style="color: #17a2b8;"></i> Edit Supplier
            </h2>
            <p class="text-muted mb-0">Update supplier information</p>
        </div>
        <div>
            <a href="suppliers.php" class="btn btn-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back to Suppliers
            </a>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Financial Info Card -->
    <div class="card info-card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted">Opening Balance</small>
                    <h5 class="mb-0">Rs <?php echo number_format($supplier['opening_balance'], 2); ?></h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Current Balance</small>
                    <h5 class="mb-0 <?php echo $supplier['current_balance'] >= 0 ? 'text-warning' : 'text-success'; ?>">
                        Rs <?php echo number_format(abs($supplier['current_balance']), 2); ?>
                        <small>(<?php echo $supplier['current_balance'] >= 0 ? 'Credit' : 'Advance'; ?>)</small>
                    </h5>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Note</small>
                    <p class="mb-0 text-muted small"><i class="fas fa-info-circle me-1"></i> Opening balance cannot be edited here. Use ledger adjustments if needed.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Supplier Form Card -->
    <div class="card form-card shadow-sm">
        <div class="form-header">
            <h4><i class="fas fa-pen-alt me-2"></i> Edit Supplier Information</h4>
            <p>Update the supplier details below</p>
        </div>
        <div class="form-body">
            <form method="POST" action="">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label required-field">Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control" value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label required-field">Mobile Number</label>
                        <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($supplier['mobile']); ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Phone (Optional)</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">NTN Number</label>
                        <input type="text" name="ntn_no" class="form-control" value="<?php echo htmlspecialchars($supplier['ntn_no'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?php echo $supplier['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $supplier['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <hr>
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2">
                                <i class="fas fa-save me-2"></i> Update Supplier
                            </button>
                            <a href="suppliers.php" class="btn btn-secondary rounded-pill px-5 py-2">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
</div>
</div>

<?php include '../includes/footer.php'; ?>
