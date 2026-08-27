<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$is_salesman_user = is_salesman();
$salesman_name = $is_salesman_user ? mysqli_real_escape_string($conn, $_SESSION['admin_name']) : '';

$message = '';

// ---- DELETE Customer ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($is_salesman_user) {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM customers c WHERE id=$id AND " . salesman_match_condition($conn)));
        if (!$check['cnt']) {
            header("Location: empty_bottle_return.php");
            exit();
        }
    }
    mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
    header("Location: empty_bottle_return.php?msg=deleted");
    exit();
}

// ---- EDIT Customer ----
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
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
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $message = "<div class='alert alert-success'>Customer deleted.</div>";

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$tracking = [];
$customer_name = '';
$customer_mobile = '';
$customer_code = '';
$current_empty_balance = 0;

if ($customer_id) {
    $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_name, mobile, customer_code, empty_bottles_balance FROM customers WHERE id=$customer_id"));
    if($cust) {
        $customer_name = $cust['customer_name'];
        $customer_mobile = $cust['mobile'];
        $customer_code = $cust['customer_code'] ?? '';
        $current_empty_balance = $cust['empty_bottles_balance'];
        
        // Apply date filters
        $date_condition = "";
        if($from_date && $to_date) {
            $date_condition = "AND DATE(tracking_date) BETWEEN '$from_date' AND '$to_date'";
        } elseif($from_date) {
            $date_condition = "AND DATE(tracking_date) >= '$from_date'";
        } elseif($to_date) {
            $date_condition = "AND DATE(tracking_date) <= '$to_date'";
        }
        
        $tracking_query = "SELECT bt.*, p.product_name, p.product_code, d.voucher_no FROM bottle_tracking bt 
                           LEFT JOIN water_deliveries d ON bt.reference_id = d.id 
                           LEFT JOIN products p ON d.product_id = p.id 
                           WHERE bt.customer_id=$customer_id $date_condition ORDER BY bt.tracking_date DESC";
        $tracking = mysqli_query($conn, $tracking_query);
    }
}

$where_cust = " WHERE c.status='Active'";
if ($search) $where_cust .= " AND (c.customer_name LIKE '%$search%' OR c.customer_code LIKE '%$search%' OR c.mobile LIKE '%$search%')";
$where_cust .= $is_salesman_user ? " AND " . salesman_match_condition($conn) : '';
$customers = mysqli_query($conn, "SELECT c.id, c.customer_code, c.customer_name, c.mobile, c.address, c.salesman, c.security_deposit, c.empty_bottles_balance, c.status FROM customers c $where_cust ORDER BY c.customer_name");

// Calculate ALL customers summary stats
$grand_delivered = 0;
$grand_returned = 0;
$grand_pending = 0;
$grand_q = mysqli_query($conn, "SELECT COALESCE(SUM(bottles_delivered),0) AS total_del, COALESCE(SUM(bottles_returned),0) AS total_ret FROM bottle_tracking");
if($grand_q && mysqli_num_rows($grand_q) > 0) {
    $grand_row = mysqli_fetch_assoc($grand_q);
    $grand_delivered = $grand_row['total_del'];
    $grand_returned = $grand_row['total_ret'];
}
$grand_pending_q = mysqli_query($conn, "SELECT COALESCE(SUM(empty_bottles_balance),0) AS total FROM customers WHERE status='Active'");
$grand_pending = mysqli_fetch_assoc($grand_pending_q)['total'];

// Calculate summary stats
$total_delivered = 0;
$total_returned = 0;
$total_broken = 0;
if($customer_id && $tracking && mysqli_num_rows($tracking) > 0) {
    mysqli_data_seek($tracking, 0);
    while($t = mysqli_fetch_assoc($tracking)) {
        $total_delivered += $t['bottles_delivered'];
        $total_returned += $t['bottles_returned'];
        $total_broken += $t['bottles_broken'] ?? 0;
    }
    mysqli_data_seek($tracking, 0);
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.tracking-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}
.tracking-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.customer-info-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.bottle-stats {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}
.bottle-stats:hover {
    transform: translateY(-3px);
}
.bottle-stats i {
    font-size: 32px;
    margin-bottom: 10px;
}
.bottle-stats h4 {
    font-size: 28px;
    font-weight: 700;
    margin: 5px 0;
}
.bottle-stats.delivered i { color: #2196f3; }
.bottle-stats.returned i { color: #4caf50; }
.bottle-stats.broken i { color: #ff9800; }
.bottle-stats.pending i { color: #A04657; }
.tracking-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.tracking-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.tracking-table tr:hover {
    background-color: #f8f9fa;
}
.btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
    white-space: nowrap;
}
.filter-box {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}
.date-input {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 8px 12px;
}
.btn-filter {
    background: #A04657;
    color: white;
    border-radius: 12px;
    padding: 8px 20px;
    border: none;
}
.btn-filter:hover {
    background: #7a3542;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}
.badge-delivered {
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 11px;
}
.badge-returned {
    background: #e8f5e9;
    color: #388e3c;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 11px;
}
@media (max-width: 768px) {
    .tracking-table {
        font-size: 11px;
    }
    .tracking-table th,
    .tracking-table td {
        padding: 6px;
    }
    .bottle-stats h4 {
        font-size: 20px;
    }
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-cubes me-2" style="color: #A04657;"></i> Bottle Tracking
        </h2>
        <div class="d-flex gap-2 no-print">
            <?php if($customer_id): ?>
                <button onclick="printCustomerHistory()" class="btn btn-outline-dark rounded-pill px-4">
                    <i class="fas fa-print me-2"></i> Print
                </button>
                <a href="?search=<?php echo urlencode($search); ?>" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i> Back to All Customers
                </a>
            <?php else: ?>
                <button onclick="printCustomers()" class="btn btn-outline-dark rounded-pill px-4">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- Grand Summary Stats - Always Visible -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div style="background:#e3f2fd; border-radius:15px; padding:18px 12px; text-align:center;">
                <i class="fas fa-truck" style="font-size:28px; color:#2196f3; margin-bottom:6px;"></i>
                <h4 style="font-size:26px; font-weight:700; color:#1565c0; margin:4px 0;"><?php echo number_format($grand_delivered); ?></h4>
                <small style="color:#666; font-weight:600;">Total Delivered</small>
            </div>
        </div>
        <div class="col-4">
            <div style="background:#e8f5e9; border-radius:15px; padding:18px 12px; text-align:center;">
                <i class="fas fa-undo-alt" style="font-size:28px; color:#4caf50; margin-bottom:6px;"></i>
                <h4 style="font-size:26px; font-weight:700; color:#2e7d32; margin:4px 0;"><?php echo number_format($grand_returned); ?></h4>
                <small style="color:#666; font-weight:600;">Total Returned</small>
            </div>
        </div>
        <div class="col-4">
            <div style="background:#fce4ec; border-radius:15px; padding:18px 12px; text-align:center;">
                <i class="fas fa-hourglass-half" style="font-size:28px; color:#A04657; margin-bottom:6px;"></i>
                <h4 style="font-size:26px; font-weight:700; color:#A04657; margin:4px 0;"><?php echo number_format($grand_pending); ?></h4>
                <small style="color:#666; font-weight:600;">Total Pending Empties</small>
            </div>
        </div>
    </div>

    <?php if(!$customer_id): ?>
    <!-- All Customers Summary -->
    <div class="card tracking-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <span><i class="fas fa-users me-2"></i> All Customers - Empty Bottles Summary</span>
        </div>
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search by ID or Customer Name</label>
                    <input type="text" name="search" class="form-control" placeholder="Enter customer ID or name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="?" class="btn btn-outline-secondary w-100" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table tracking-table mb-0" id="customersSummaryTable">
                    <thead>
                        <tr>
                            <th style="width:80px">ID</th>
                            <th>Customer Name</th>
                            <th style="min-width:110px">Mobile</th>
                            <th style="width:130px" class="text-center">Empty Bottles</th>
                            <th style="width:190px" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($customers && mysqli_num_rows($customers) > 0):
                            mysqli_data_seek($customers, 0);
                            while($c = mysqli_fetch_assoc($customers)): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($c['customer_code']); ?></td>
                                <td><strong><?php echo htmlspecialchars($c['customer_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['mobile'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $c['empty_bottles_balance'] > 0 ? 'bg-primary' : 'bg-secondary'; ?> rounded-pill px-3">
                                        <?php echo number_format($c['empty_bottles_balance']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="?customer_id=<?php echo $c['id']; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-xs btn-outline-primary" title="View History">
                                            <i class="fas fa-eye me-1"></i> History
                                        </a>
                                        <button type="button" class="btn btn-xs btn-warning editCustomerBtn" title="Edit"
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($c['customer_name']); ?>"
                                            data-mobile="<?php echo htmlspecialchars($c['mobile'] ?? ''); ?>"
                                            data-address="<?php echo htmlspecialchars($c['address'] ?? '', ENT_QUOTES); ?>"
                                            data-salesman="<?php echo htmlspecialchars($c['salesman'] ?? ''); ?>"
                                            data-deposit="<?php echo $c['security_deposit']; ?>"
                                            data-empties="<?php echo $c['empty_bottles_balance']; ?>"
                                            data-status="<?php echo htmlspecialchars($c['status'] ?? 'Active'); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete customer? This will remove all related deliveries, payments, and ledger entries.')" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-2 d-block opacity-25"></i>
                                    No customers found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($customer_id && $customer_name): ?>

        <!-- Bottle Summary Stats - Top -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div style="background:#e3f2fd; border-radius:15px; padding:18px 12px; text-align:center;">
                    <i class="fas fa-truck" style="font-size:28px; color:#2196f3; margin-bottom:6px;"></i>
                    <h4 style="font-size:26px; font-weight:700; color:#1565c0; margin:4px 0;"><?php echo number_format($total_delivered); ?></h4>
                    <small style="color:#666; font-weight:600;">Total Delivered</small>
                </div>
            </div>
            <div class="col-4">
                <div style="background:#e8f5e9; border-radius:15px; padding:18px 12px; text-align:center;">
                    <i class="fas fa-undo-alt" style="font-size:28px; color:#4caf50; margin-bottom:6px;"></i>
                    <h4 style="font-size:26px; font-weight:700; color:#2e7d32; margin:4px 0;"><?php echo number_format($total_returned); ?></h4>
                    <small style="color:#666; font-weight:600;">Total Returned</small>
                </div>
            </div>
            <div class="col-4">
                <div style="background:#fce4ec; border-radius:15px; padding:18px 12px; text-align:center;">
                    <i class="fas fa-hourglass-half" style="font-size:28px; color:#A04657; margin-bottom:6px;"></i>
                    <h4 style="font-size:26px; font-weight:700; color:#A04657; margin:4px 0;"><?php echo number_format($current_empty_balance); ?></h4>
                    <small style="color:#666; font-weight:600;">Pending Empties</small>
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="customer-info-card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-circle fa-3x" style="color: #A04657;"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($customer_name); ?>
                                <?php if(!empty($customer_code)): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill align-middle ms-1"><?php echo htmlspecialchars($customer_code); ?></span>
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted mb-0" style="font-size:13px;">
                                <i class="fas fa-phone me-1"></i> <?php echo $customer_mobile ?: 'No mobile'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="filter-box">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar-alt me-1"></i> From Date</label>
                    <input type="date" name="from_date" class="form-control date-input" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar-alt me-1"></i> To Date</label>
                    <input type="date" name="to_date" class="form-control date-input" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-filter me-2"></i> Apply Filter
                    </button>
                </div>
            </form>
            <?php if($from_date || $to_date): ?>
                <div class="mt-3 text-end">
                    <a href="?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottle Movement Table -->
        <div class="card tracking-card">
            <div class="card-header">
                <i class="fas fa-list-alt me-2"></i> Bottle Movement History
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table tracking-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 140px">Date & Time</th>
                                <th style="width: 160px">Bottle</th>
                                <th style="width: 100px" class="text-center">Delivered</th>
                                <th style="width: 100px" class="text-center">Returned</th>
                                <th style="width: 100px" class="text-center">Broken</th>
                                <th style="width: 120px" class="text-center">After This</th>
                                <th>Notes / Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($tracking && mysqli_num_rows($tracking) > 0): ?>
                                <?php 
                                $history = [];
                                while($t = mysqli_fetch_assoc($tracking)) {
                                    $history[] = $t;
                                }
                                $after_map = [];
                                $running_empty = $current_empty_balance;
                                foreach($history as $t) {
                                    $after_map[$t['id']] = $running_empty;
                                    if($t['bottles_delivered'] > 0) {
                                        $running_empty = $running_empty - $t['bottles_delivered'];
                                    }
                                    if($t['bottles_returned'] > 0) {
                                        $running_empty = $running_empty + $t['bottles_returned'];
                                    }
                                    if(($t['bottles_broken'] ?? 0) > 0) {
                                        $running_empty = $running_empty + ($t['bottles_broken'] ?? 0);
                                    }
                                }
                                $history = array_reverse($history);
                                ?>
                                <?php foreach($history as $t): ?>
                                    <tr>
                                        <td>
                                            <i class="far fa-calendar-alt me-1 text-muted"></i>
                                            <?php echo date('d-m-Y h:i A', strtotime($t['tracking_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill">
                                                <i class="fas fa-wine-bottle me-1"></i>
                                                <?php echo !empty($t['product_name']) ? htmlspecialchars($t['product_name']) : '—'; ?>
                                                <?php if(!empty($t['product_code'])): ?>
                                                    <span class="opacity-75 ms-1">[<?php echo htmlspecialchars($t['product_code']); ?>]</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if(!empty($t['voucher_no'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-file-invoice me-1"></i> Voucher: <strong><?php echo htmlspecialchars($t['voucher_no']); ?></strong>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($t['bottles_delivered'] > 0): ?>
                                                <span class="badge-delivered">
                                                    <i class="fas fa-truck"></i> <?php echo $t['bottles_delivered']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($t['bottles_returned'] > 0): ?>
                                                <span class="badge-returned">
                                                    <i class="fas fa-undo-alt"></i> <?php echo $t['bottles_returned']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if(($t['bottles_broken'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 rounded-pill">
                                                    <i class="fas fa-wine-bottle"></i> <?php echo $t['bottles_broken'] ?? 0; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php $running_empty = $after_map[$t['id']] ?? $current_empty_balance; ?>
                                            <strong class="<?php echo $running_empty < 0 ? 'text-danger' : 'text-primary'; ?>">
                                                <?php echo $running_empty; ?> bottles
                                            </strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                $ref_type = $t['reference_type'] ?? '';
                                                if($t['notes']) {
                                                    echo htmlspecialchars($t['notes']);
                                                } elseif($ref_type == 'return_only') {
                                                    echo '<span class="text-info">Empty bottle return only</span>';
                                                } elseif($ref_type == 'delivery') {
                                                    echo '<span class="text-primary">Water delivery</span>';
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    <tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-boxes"></i>
                                        <p class="mb-0">No bottle movement found for this customer.</p>
                                        <small class="text-muted">Bottle tracking is created when deliveries or returns are recorded.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <div><i class="fas fa-truck text-primary me-1"></i> <small>Water Delivery (Increases empty bottles)</small></div>
                    <div><i class="fas fa-undo-alt text-success me-1"></i> <small>Bottle Return (Decreases empty bottles)</small></div>
                    <div><i class="fas fa-wine-bottle text-danger me-1"></i> <small>Broken Bottles (Decreases empty bottles)</small></div>
                </div>
            </div>
        </div>

    <?php elseif($customer_id && !$customer_name): ?>
        <div class="alert alert-warning rounded-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Customer not found. Please select a valid customer.
        </div>
    <?php endif; ?>

</div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
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

<!-- Print Overlay (customers summary) -->
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
                <span class="print-doc-title">Empty Bottles Summary</span>
                <?php if($search): ?>
                    <span class="print-date-range">Search: <?php echo htmlspecialchars($search); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex; gap:16px; margin-bottom:16px;">
            <div style="flex:1; background:#e3f2fd; border-radius:10px; padding:12px 14px; text-align:center;">
                <div style="font-size:22px; font-weight:700; color:#1565c0;"><?php echo number_format($grand_delivered); ?></div>
                <div style="font-size:11px; color:#666; font-weight:600;">Total Delivered</div>
            </div>
            <div style="flex:1; background:#e8f5e9; border-radius:10px; padding:12px 14px; text-align:center;">
                <div style="font-size:22px; font-weight:700; color:#2e7d32;"><?php echo number_format($grand_returned); ?></div>
                <div style="font-size:11px; color:#666; font-weight:600;">Total Returned</div>
            </div>
            <div style="flex:1; background:#fce4ec; border-radius:10px; padding:12px 14px; text-align:center;">
                <div style="font-size:22px; font-weight:700; color:#A04657;"><?php echo number_format($grand_pending); ?></div>
                <div style="font-size:11px; color:#666; font-weight:600;">Total Pending Empties</div>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:60px;">ID</th>
                    <th>Customer Name</th>
                    <th>Mobile</th>
                    <th style="width:100px;" class="text-end">Empty Bottles</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_customers = mysqli_query($conn, "SELECT c.customer_code, c.customer_name, c.mobile, c.empty_bottles_balance FROM customers c $where_cust ORDER BY c.customer_name");
                $sno = 1;
                $total_empties = 0;
                if($print_customers && mysqli_num_rows($print_customers) > 0):
                    while($pc = mysqli_fetch_assoc($print_customers)):
                        $total_empties += $pc['empty_bottles_balance'];
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo htmlspecialchars($pc['customer_code']); ?></td>
                        <td><strong><?php echo htmlspecialchars($pc['customer_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pc['mobile'] ?? '-'); ?></td>
                        <td class="text-end"><?php echo number_format($pc['empty_bottles_balance']); ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="font-weight:700;background:#f0f0f0;">
                        <td colspan="4" class="text-end">Total</td>
                        <td class="text-end"><?php echo number_format($total_empties); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center" style="padding:40px;color:#999;">No customers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
</div>

<style>
/* Screen: hide print overlays */
#print-overlay,
#print-overlay-history {
    display: none;
}
#print-area,
#print-area-history {
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
.print-customer-info {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 22px;
    font-size: 12px;
    color: #555;
    background: #f7f7f7;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 14px;
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

<!-- History Print Overlay -->
<div id="print-overlay-history" style="display:none;">
    <div id="print-area-history">
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
                <span class="print-doc-title">Empty Bottles History - <?php echo htmlspecialchars($customer_name); ?></span>
                <span class="print-date-range">Code: <?php echo htmlspecialchars($customer_code ?? '-'); ?></span>
            </div>
        </div>

        <div class="print-customer-info">
            <span>Mobile: <?php echo htmlspecialchars($customer_mobile ?: '-'); ?></span>
            <span>Current Empty Bottles: <strong><?php echo $current_empty_balance; ?></strong></span>
            <span>Delivered: <strong><?php echo $total_delivered; ?></strong></span>
            <span>Returned: <strong><?php echo $total_returned; ?></strong></span>
            <?php if($from_date || $to_date): ?>
                <span>Period: <?php echo $from_date ?: 'Start'; ?> to <?php echo $to_date ?: 'Today'; ?></span>
            <?php endif; ?>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Bottle</th>
                    <th class="text-end">Delivered</th>
                    <th class="text-end">Returned</th>
                    <th class="text-end">Broken</th>
                    <th class="text-end">After This</th>
                    <th>Notes / Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $print_history_rows = [];
                if($customer_id && $customer_name) {
                    $print_tracking = mysqli_query($conn, "SELECT bt.*, p.product_name, p.product_code, d.voucher_no FROM bottle_tracking bt 
                                                            LEFT JOIN water_deliveries d ON bt.reference_id = d.id 
                                                            LEFT JOIN products p ON d.product_id = p.id 
                                                            WHERE bt.customer_id=$customer_id $date_condition ORDER BY bt.tracking_date DESC");
                    if($print_tracking && mysqli_num_rows($print_tracking) > 0) {
                        while($pt = mysqli_fetch_assoc($print_tracking)) $print_history_rows[] = $pt;
                        $after_map = [];
                        $running_empty = $current_empty_balance;
                        foreach($print_history_rows as $t) {
                            $after_map[$t['id']] = $running_empty;
                            if($t['bottles_delivered'] > 0) $running_empty = $running_empty - $t['bottles_delivered'];
                            if($t['bottles_returned'] > 0) $running_empty = $running_empty + $t['bottles_returned'];
                            if(($t['bottles_broken'] ?? 0) > 0) $running_empty = $running_empty + ($t['bottles_broken'] ?? 0);
                        }
                        $print_history_rows = array_reverse($print_history_rows);
                    }
                }
                if(!empty($print_history_rows)):
                    foreach($print_history_rows as $t):
                        $after_val = $after_map[$t['id']] ?? $current_empty_balance;
                ?>
                    <tr>
                        <td><?php echo date('d-m-Y h:i A', strtotime($t['tracking_date'])); ?></td>
                        <td><?php echo !empty($t['product_name']) ? htmlspecialchars($t['product_name']) : '—'; ?>
                            <?php if(!empty($t['product_code'])): ?> [<?php echo htmlspecialchars($t['product_code']); ?>]<?php endif; ?>
                            <?php if(!empty($t['voucher_no'])): ?><br><small>Voucher: <?php echo htmlspecialchars($t['voucher_no']); ?></small><?php endif; ?>
                        </td>
                        <td class="text-end"><?php echo $t['bottles_delivered'] > 0 ? $t['bottles_delivered'] : '—'; ?></td>
                        <td class="text-end"><?php echo $t['bottles_returned'] > 0 ? $t['bottles_returned'] : '—'; ?></td>
                        <td class="text-end"><?php echo ($t['bottles_broken'] ?? 0) > 0 ? ($t['bottles_broken'] ?? 0) : '—'; ?></td>
                        <td class="text-end"><strong><?php echo $after_val; ?></strong></td>
                        <td>
                            <?php
                            if($t['notes']) {
                                echo htmlspecialchars($t['notes']);
                            } elseif(($t['reference_type'] ?? '') == 'return_only') {
                                echo 'Empty bottle return only';
                            } elseif(($t['reference_type'] ?? '') == 'delivery') {
                                echo 'Water delivery';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:#999;">No bottle movement found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="print-footer">
            Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
</div>

<script>
function doPrint(areaId, title) {
    var content = document.getElementById(areaId).innerHTML;
    var w = window.open('', '_blank', 'width=900,height=700');
    w.document.open();
    w.document.write(
        '<!DOCTYPE html>' +
        '<html>' +
        '<head>' +
        '<meta charset="UTF-8">' +
        '<title>' + title + '</title>' +
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">' +
        '<style>' +
        '* { box-sizing: border-box; margin: 0; padding: 0; }' +
        'body { font-family: Poppins, Segoe UI, Arial, sans-serif; color: #222; background: #fff; padding: 30px 35px; font-size: 12px; }' +
        '.print-header { margin-bottom: 18px; }' +
        '.print-brand-row { display: flex; align-items: center; gap: 16px; }' +
        '.print-logo-circle { width: 56px; height: 56px; background: linear-gradient(135deg,#A04657,#c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; color: #fff; flex-shrink: 0; }' +
        '.print-brand-text { display: flex; flex-direction: column; gap: 2px; }' +
        '.print-owner-name { font-size: 20px; font-weight: 800; color: #222; }' +
        '.print-company { font-size: 16px; font-weight: 700; color: #A04657; }' +
        '.print-address { font-size: 12px; color: #666; }' +
        '.print-phone { font-size: 13px; font-weight: 600; color: #A04657; }' +
        '.print-divider { height: 2px; background: linear-gradient(to right,#A04657,#e0a0ab); margin: 12px 0 8px; border-radius: 2px; }' +
        '.print-title-row { display: flex; justify-content: space-between; align-items: center; }' +
        '.print-doc-title { font-size: 14px; font-weight: 700; color: #444; }' +
        '.print-date-range { font-size: 11px; color: #888; background: #f5f5f5; padding: 4px 12px; border-radius: 20px; }' +
        '.print-customer-info { display: flex; flex-wrap: wrap; gap: 8px 20px; font-size: 11px; color: #555; background: #f7f7f7; border-radius: 8px; padding: 9px 13px; margin-bottom: 13px; }' +
        '.print-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 14px; }' +
        '.print-table th { background: #A04657; color: #fff; padding: 9px 11px; font-weight: 600; text-align: left; }' +
        '.print-table th.text-end, .print-table td.text-end { text-align: right; }' +
        '.print-table td { padding: 8px 11px; border-bottom: 1px solid #e6e6e6; color: #333; }' +
        '.print-table tbody tr:nth-child(even) { background: #f9f9f9; }' +
        '.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }' +
        '.print-footer { margin-top: 16px; text-align: center; font-size: 10px; color: #aaa; padding-top: 10px; border-top: 1px solid #eee; }' +
        '@media print { @page { margin: 10mm 12mm; } }' +
        '</style>' +
        '</head>' +
        '<body>' +
        content +
        '</body>' +
        '</html>'
    );
    w.document.close();
    w.onload = function() {
        w.focus();
        w.print();
        w.close();
    };
}

function printCustomers() {
    doPrint('print-area', 'Empty Bottles Summary');
}

function printCustomerHistory() {
    doPrint('print-area-history', 'Empty Bottles History');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.editCustomerBtn').forEach(function(btn) {
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
});
</script>

<?php include '../includes/footer.php'; ?>