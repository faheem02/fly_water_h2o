<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$is_salesman_user = is_salesman();
$salesman_display = $is_salesman_user ? htmlspecialchars($_SESSION['admin_name']) : '';
$salesman_name = $is_salesman_user ? mysqli_real_escape_string($conn, $_SESSION['admin_name']) : '';
$salesman_only = $is_salesman_user ? ' AND ' . salesman_match_condition($conn) : '';

$message = '';

// ---- DELETE Customer ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($is_salesman_user) {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM customers c WHERE id=$id AND " . salesman_match_condition($conn)));
        if (!$check['cnt']) {
            header("Location: customer_view.php");
            exit();
        }
    }
    mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
    header("Location: customer_view.php?msg=deleted");
    exit();
}

// ---- ADD / EDIT Customer ----
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $salesman = $is_salesman_user ? $salesman_name : mysqli_real_escape_string($conn, $_POST['customer_salesman']);
        $deposit = floatval($_POST['security_deposit']);
        $opening = floatval($_POST['opening_balance']);
        $empties = intval($_POST['empty_bottles_balance']);
        $status = $_POST['status'];
        $datetime = date('Y-m-d H:i:s');

        $query = "INSERT INTO customers (customer_name, mobile, address, security_deposit, opening_balance, empty_bottles_balance, outstanding_balance, salesman, status, created_datetime) 
                  VALUES ('$name', '$mobile', '$address', $deposit, $opening, $empties, $opening, '$salesman', '$status', '$datetime')";
        if (mysqli_query($conn, $query)) {
            $cid = mysqli_insert_id($conn);
            if ($opening != 0) {
                $desc = "Opening Balance";
                $debit = ($opening > 0) ? $opening : 0;
                $credit = ($opening < 0) ? abs($opening) : 0;
                $balance = $opening;
                mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance) 
                                     VALUES ($cid, '$datetime', '$desc', $debit, $credit, $balance)");
            }
            $message = "<div class='alert alert-success'>Customer added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
    elseif (isset($_POST['edit_customer'])) {
        $id = intval($_POST['customer_id']);
        $can_edit = true;
        if ($is_salesman_user) {
            $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM customers c WHERE id=$id AND " . salesman_match_condition($conn)));
            if (!$check['cnt']) $can_edit = false;
        }
        if ($can_edit) {
            $name = mysqli_real_escape_string($conn, $_POST['customer_name']);
            $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $salesman = $is_salesman_user ? $salesman_name : mysqli_real_escape_string($conn, $_POST['customer_salesman']);
            $deposit = floatval($_POST['security_deposit']);
            $empties = intval($_POST['empty_bottles_balance']);
            $status = $_POST['status'];
            $sql = "UPDATE customers SET customer_name='$name', mobile='$mobile', address='$address', security_deposit=$deposit, empty_bottles_balance=$empties, salesman='$salesman', status='$status' WHERE id=$id";
            if (mysqli_query($conn, $sql)) {
                $message = "<div class='alert alert-success'>Customer updated!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Update failed: " . mysqli_error($conn) . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>You can only edit your own customers.</div>";
        }
    }
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "<div class='alert alert-success'>Customer deleted.</div>";

// ---- Fetch customers with search and date filter ----
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$where = " WHERE 1=1";
if ($search) $where .= " AND (c.customer_name LIKE '%$search%' OR c.mobile LIKE '%$search%' OR c.id LIKE '%$search%' OR c.salesman LIKE '%$search%')";
if ($from_date) $where .= " AND DATE(c.created_datetime) >= '$from_date'";
if ($to_date) $where .= " AND DATE(c.created_datetime) <= '$to_date'";
$where .= $salesman_only;

$customers_query = "SELECT c.* FROM customers c $where ORDER BY c.customer_name";
$customers = mysqli_query($conn, $customers_query);

// ---- Stats for cards ----
$stats_query = mysqli_query($conn, "SELECT 
    COUNT(*) as total_customers,
    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active_customers,
    COALESCE(SUM(outstanding_balance),0) as total_outstanding,
    COALESCE(SUM(empty_bottles_balance),0) as total_empty_bottles
    FROM customers c" . ($is_salesman_user ? " WHERE " . salesman_match_condition($conn) : ""));
$stats = mysqli_fetch_assoc($stats_query);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.customers-table {
    font-size: 14px;
}
.customers-table th {
    background-color: #A04657;
    color: white;
    padding: 12px 10px;
    font-weight: 600;
    white-space: nowrap;
}
.customers-table td {
    padding: 12px 10px;
    vertical-align: middle;
    word-break: break-word;
}
.customers-table td:first-child,
.customers-table th:first-child {
    text-align: center;
}
.address-cell {
    max-width: 200px;
    min-width: 150px;
}
.date-cell {
    white-space: nowrap;
    font-size: 12px;
}
.badge-status {
    font-size: 11px;
    padding: 5px 10px;
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
    .customers-table {
        font-size: 12px;
    }
    .customers-table td, 
    .customers-table th {
        padding: 8px 6px;
    }
    .address-cell {
        max-width: 120px;
    }
}

</style>

<div class="main-wrapper">
<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-users me-2" style="color: #A04657;"></i> View Customers
        </h2>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-plus-circle me-2"></i> Add New Customer
            </button>
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="printCustomers()">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-users fa-2x mb-2" style="color: #A04657;"></i>
                    <h5 class="text-muted mb-1">Total Customers</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_customers']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-user-check fa-2x mb-2" style="color: #28a745;"></i>
                    <h5 class="text-muted mb-1">Active Customers</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['active_customers']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-rupee-sign fa-2x mb-2" style="color: #ffc107;"></i>
                    <h5 class="text-muted mb-1">Total Outstanding</h5>
                    <h2 class="mb-0">Rs <?php echo number_format($stats['total_outstanding'], 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card dash-card text-center p-3">
                <div class="card-body">
                    <i class="fas fa-cube fa-2x mb-2" style="color: #17a2b8;"></i>
                    <h5 class="text-muted mb-1">Empty Bottles</h5>
                    <h2 class="mb-0"><?php echo number_format($stats['total_empty_bottles']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, ID, mobile, salesman..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-4 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                    <a href="customer_view.php" class="btn btn-outline-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover customers-table mb-0" id="customersTable">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:140px">Name</th>
                            <th style="min-width:110px">Mobile</th>
                            <th style="min-width:160px">Address</th>
                            <th style="min-width:120px">Salesman</th>
                            <th style="min-width:110px">Security Deposit (Rs)</th>
                            <th style="min-width:110px">Opening Balance (Rs)</th>
                            <th style="min-width:100px">Outstanding (Rs)</th>
                            <th style="min-width:90px">Empty Bottles</th>
                            <th style="min-width:120px">Created Date</th>
                            <th style="min-width:80px">Status</th>
                            <th style="min-width:90px; text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers && mysqli_num_rows($customers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($customers)): ?>
                                <tr>
                                    <td class="text-center fw-semibold"><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                    <td class="address-cell"><?php echo nl2br(htmlspecialchars($row['address'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($row['salesman'] ?? '-'); ?></td>
                                    <td>Rs <?php echo number_format($row['security_deposit'], 2); ?></td>
                                    <td>Rs <?php echo number_format($row['opening_balance'], 2); ?></td>
                                    <td class="text-danger fw-bold">Rs <?php echo number_format($row['outstanding_balance'], 2); ?></td>
                                    <td><?php echo number_format($row['empty_bottles_balance']); ?></td>
                                    <td class="date-cell"><?php echo date('d-m-Y H:i', strtotime($row['created_datetime'])); ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Active'): ?>
                                            <span class="badge bg-success rounded-pill badge-status">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill badge-status">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary viewCustomerBtn"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                            data-mobile="<?php echo $row['mobile']; ?>"
                                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                            data-salesman="<?php echo htmlspecialchars($row['salesman'] ?? ''); ?>"
                                            data-deposit="<?php echo $row['security_deposit']; ?>"
                                            data-opening="<?php echo $row['opening_balance']; ?>"
                                            data-outstanding="<?php echo $row['outstanding_balance']; ?>"
                                            data-empties="<?php echo $row['empty_bottles_balance']; ?>"
                                            data-status="<?php echo $row['status']; ?>"
                                            data-created="<?php echo $row['created_datetime']; ?>"
                                            title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning editCustomerBtn" 
                                            data-id="<?php echo $row['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                            data-mobile="<?php echo $row['mobile']; ?>"
                                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                            data-salesman="<?php echo htmlspecialchars($row['salesman'] ?? ''); ?>"
                                            data-deposit="<?php echo $row['security_deposit']; ?>"
                                            data-opening="<?php echo $row['opening_balance']; ?>"
                                            data-empties="<?php echo $row['empty_bottles_balance']; ?>"
                                            data-status="<?php echo $row['status']; ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete customer? This will remove all related deliveries, payments, and ledger entries.')" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3 d-block"></i>
                                    No customers found.
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" class="form-control" placeholder="e.g., 9876543210">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Salesman</label>
                            <input type="text" name="customer_salesman" id="add_customer_salesman" class="form-control" placeholder="Salesman" value="<?php echo $salesman_display; ?>" <?php echo $is_salesman_user ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (Rs)</label>
                            <input type="number" step="0.01" name="security_deposit" class="form-control" placeholder="Deposit amount" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opening Balance (Rs)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" placeholder="If any" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empty Bottles Balance</label>
                            <input type="number" name="empty_bottles_balance" class="form-control" placeholder="Number of empty bottles" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_customer" class="btn btn-primary rounded-pill px-4">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Edit Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="customer_id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name *</label>
                            <input type="text" name="customer_name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" id="edit_mobile" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Salesman</label>
                            <input type="text" name="customer_salesman" id="edit_customer_salesman" class="form-control" placeholder="Salesman" <?php echo $is_salesman_user ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Security Deposit (Rs)</label>
                            <input type="number" step="0.01" name="security_deposit" id="edit_deposit" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empty Bottles Balance</label>
                            <input type="number" name="empty_bottles_balance" id="edit_empties" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-primary rounded-pill px-4">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Customer Modal -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-success text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i> Customer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="me-3">
                        <i class="fas fa-user-circle fa-3x text-success"></i>
                    </div>
                    <div>
                        <h4 class="mb-0" id="view_name"></h4>
                        <small class="text-muted" id="view_mobile"></small>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="summary-box-view text-center">
                            <h6>Opening Balance</h6>
                            <h4 style="color: #17a2b8;" id="view_opening">Rs 0.00</h4>
                            <small class="text-muted">(jab add kiya tha)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box-view text-center">
                            <h6>Current Outstanding</h6>
                            <h4 style="color: #dc3545;" id="view_outstanding">Rs 0.00</h4>
                            <small class="text-muted">(ab kitna baaki hai)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box-view text-center">
                            <h6>Empty Bottles</h6>
                            <h4 style="color: #28a745;" id="view_empties">0</h4>
                            <small class="text-muted">(bottles)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box-view text-center">
                            <h6>Security Deposit</h6>
                            <h4 style="color: #6f42c1;" id="view_deposit">Rs 0.00</h4>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="view-detail"><span class="view-label">Address</span><span class="view-value" id="view_address">-</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="view-detail"><span class="view-label">Salesman</span><span class="view-value" id="view_salesman">-</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="view-detail"><span class="view-label">Created Date</span><span class="view-value" id="view_created">-</span></div>
                    </div>
                    <div class="col-md-4">
                        <div class="view-detail"><span class="view-label">Status</span><span class="view-value" id="view_status">-</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <a href="#" class="btn btn-outline-primary rounded-pill px-4" id="view_ledger_link">
                    <i class="fas fa-book me-1"></i> View Ledger
                </a>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.summary-box-view {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px 10px;
    border-left: 4px solid #A04657;
}
.summary-box-view h6 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #666;
    margin-bottom: 6px;
}
.summary-box-view h4 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 2px;
}
.view-detail {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 10px 14px;
    height: 100%;
}
.view-detail .view-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #888;
    margin-bottom: 3px;
}
.view-detail .view-value {
    font-size: 14px;
    font-weight: 600;
    color: #1a2c3e;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.editCustomerBtn');
    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_mobile').value = this.getAttribute('data-mobile');
            document.getElementById('edit_address').value = this.getAttribute('data-address');
            document.getElementById('edit_customer_salesman').value = this.getAttribute('data-salesman');
            document.getElementById('edit_deposit').value = this.getAttribute('data-deposit');
            document.getElementById('edit_empties').value = this.getAttribute('data-empties');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            
            var editModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
            editModal.show();
        });
    });

    const viewButtons = document.querySelectorAll('.viewCustomerBtn');
    viewButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('view_name').textContent = btn.getAttribute('data-name');
            var mobile = btn.getAttribute('data-mobile') || '';
            document.getElementById('view_mobile').textContent = mobile ? 'Mobile: ' + mobile : 'No mobile';
            document.getElementById('view_address').textContent = btn.getAttribute('data-address') || '-';
            document.getElementById('view_salesman').textContent = btn.getAttribute('data-salesman') || '-';
            document.getElementById('view_opening').textContent = 'Rs ' + parseFloat(btn.getAttribute('data-opening') || 0).toFixed(2);
            document.getElementById('view_outstanding').textContent = 'Rs ' + parseFloat(btn.getAttribute('data-outstanding') || 0).toFixed(2);
            document.getElementById('view_empties').textContent = parseInt(btn.getAttribute('data-empties') || 0);
            document.getElementById('view_deposit').textContent = 'Rs ' + parseFloat(btn.getAttribute('data-deposit') || 0).toFixed(2);
            var created = btn.getAttribute('data-created');
            document.getElementById('view_created').textContent = created ? new Date(created.replace(' ', 'T')).toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
            var st = btn.getAttribute('data-status');
            document.getElementById('view_status').innerHTML = st == 'Active' ? '<span class="badge bg-success rounded-pill">Active</span>' : '<span class="badge bg-secondary rounded-pill">Inactive</span>';
            document.getElementById('view_ledger_link').setAttribute('href', 'ledger.php?customer_id=' + btn.getAttribute('data-id'));

            var viewModal = new bootstrap.Modal(document.getElementById('viewCustomerModal'));
            viewModal.show();
        });
    });
});

if (typeof jQuery !== 'undefined') {
    $(document).ready(function() {
        $('.editCustomerBtn').off('click').on('click', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_mobile').val($(this).data('mobile'));
            $('#edit_address').val($(this).data('address'));
            $('#edit_customer_salesman').val($(this).data('salesman'));
            $('#edit_deposit').val($(this).data('deposit'));
            $('#edit_empties').val($(this).data('empties'));
            $('#edit_status').val($(this).data('status'));
            $('#editCustomerModal').modal('show');
        });

        $('.viewCustomerBtn').off('click').on('click', function() {
            var d = $(this).data();
            $('#view_name').text(d.name);
            $('#view_mobile').text(d.mobile ? 'Mobile: ' + d.mobile : 'No mobile');
            $('#view_address').text(d.address || '-');
            $('#view_salesman').text(d.salesman || '-');
            $('#view_opening').text('Rs ' + parseFloat(d.opening || 0).toFixed(2));
            $('#view_outstanding').text('Rs ' + parseFloat(d.outstanding || 0).toFixed(2));
            $('#view_empties').text(parseInt(d.empties || 0));
            $('#view_deposit').text('Rs ' + parseFloat(d.deposit || 0).toFixed(2));
            $('#view_created').text(d.created ? new Date(d.created.replace(' ', 'T')).toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-');
            $('#view_status').html(d.status == 'Active' ? '<span class="badge bg-success rounded-pill">Active</span>' : '<span class="badge bg-secondary rounded-pill">Inactive</span>');
            $('#view_ledger_link').attr('href', 'ledger.php?customer_id=' + d.id);
            $('#viewCustomerModal').modal('show');
        });
    });
}
</script>

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
                <span class="print-doc-title">Customers List</span>
                <?php if($from_date || $to_date): ?>
                    <span class="print-date-range">
                        <?php echo $from_date ?: 'Start'; ?> to <?php echo $to_date ?: 'End'; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Salesman</th>
                    <th style="width:110px;" class="text-end">Outstanding</th>
                    <th style="width:80px;" class="text-end">Empties</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $print_customers = mysqli_query($conn, $customers_query);
                $sno = 1;
                if($print_customers && mysqli_num_rows($print_customers) > 0):
                    while($row = mysqli_fetch_assoc($print_customers)): 
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['salesman'] ?? '-'); ?></td>
                        <td class="text-end"><?php echo number_format($row['outstanding_balance'], 2); ?></td>
                        <td class="text-end"><?php echo $row['empty_bottles_balance']; ?></td>
                        <td><?php echo $row['status']; ?></td>
                    </tr>
                <?php endwhile; endif; ?>
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
    width: 794px;
    margin: 0 auto;
    padding: 35px 40px;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.print-header {
    margin-bottom: 22px;
}
.print-brand-row {
    display: flex;
    align-items: center;
    gap: 18px;
}
.print-logo-circle {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #A04657, #c96b7e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    flex-shrink: 0;
}
.print-brand-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.print-company {
    font-size: 18px;
    font-weight: 700;
    color: #A04657;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-owner-name {
    font-size: 22px;
    font-weight: 800;
    color: #222;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-address {
    font-size: 13px;
    color: #666;
}
.print-phone {
    font-size: 14px;
    font-weight: 600;
    color: #A04657;
}
.print-divider {
    height: 2px;
    background: linear-gradient(to right, #A04657, #e0a0ab);
    margin: 14px 0 10px;
    border-radius: 2px;
}
.print-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.print-doc-title {
    font-size: 15px;
    font-weight: 700;
    color: #444;
    font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif;
}
.print-date-range {
    font-size: 12px;
    color: #888;
    background: #f5f5f5;
    padding: 5px 14px;
    border-radius: 20px;
}
.print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.print-table th {
    background: #A04657;
    color: #fff;
    padding: 10px 12px;
    font-weight: 600;
    font-size: 12px;
    text-align: left;
}
.print-table th.text-end,
.print-table td.text-end {
    text-align: right;
}
.print-table td {
    padding: 9px 12px;
    border-bottom: 1px solid #e6e6e6;
    color: #333;
}
.print-table tbody tr:nth-child(even) {
    background: #f9f9f9;
}
.print-table tbody tr:last-child td {
    border-bottom: 2px solid #A04657;
}
.print-footer {
    margin-top: 18px;
    text-align: center;
    font-size: 11px;
    color: #aaa;
    padding-top: 12px;
    border-top: 1px solid #eee;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
function printCustomers() {
    const overlay = document.getElementById('print-overlay');
    const printArea = document.getElementById('print-area');
    overlay.style.display = 'block';

    setTimeout(function() {
        html2canvas(printArea, {
            scale: 3,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: printArea.scrollWidth,
            height: printArea.scrollHeight
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const w = window.open('', '_blank');
            w.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Customers List</title>
                    <style>
                        @page { margin: 0; size: A4 landscape; }
                        body { margin: 0; display: flex; justify-content: center; padding: 20px; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src="${imgData}" />
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 300);
                        }
                    <\/script>
                </body>
                </html>
            `);
            w.document.close();
            overlay.style.display = 'none';
        }).catch(err => {
            console.error(err);
            alert('Print failed. Please try again.');
            overlay.style.display = 'none';
        });
    }, 200);
}
</script>

<?php include '../includes/footer.php'; ?>
