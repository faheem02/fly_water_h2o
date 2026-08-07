<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$tracking = [];
$customer_name = '';
$customer_mobile = '';
$current_empty_balance = 0;

if ($customer_id) {
    $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_name, mobile, empty_bottles_balance FROM customers WHERE id=$customer_id"));
    if($cust) {
        $customer_name = $cust['customer_name'];
        $customer_mobile = $cust['mobile'];
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
        
        $tracking_query = "SELECT * FROM bottle_tracking WHERE customer_id=$customer_id $date_condition ORDER BY tracking_date DESC";
        $tracking = mysqli_query($conn, $tracking_query);
    }
}

$customers = mysqli_query($conn, "SELECT id, customer_name, mobile, empty_bottles_balance FROM customers WHERE status='Active' ORDER BY customer_name");

// Calculate summary stats
$total_delivered = 0;
$total_returned = 0;
$total_broken = 0;
if($customer_id && $tracking && mysqli_num_rows($tracking) > 0) {
    // Reset pointer to calculate totals
    mysqli_data_seek($tracking, 0);
    while($t = mysqli_fetch_assoc($tracking)) {
        $total_delivered += $t['bottles_delivered'];
        $total_returned += $t['bottles_returned'];
        $total_broken += $t['bottles_broken'];
    }
    // Reset pointer again for display
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
    overflow: hidden;
}
.tracking-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.customer-select-card {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
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
    </div>

    <!-- Customer Selection Card -->
    <div class="customer-select-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label text-white mb-1">
                            <i class="fas fa-user me-1"></i> Select Customer
                        </label>
                        <select name="customer_id" class="form-select rounded-pill" style="border-radius: 30px;" required>
                            <option value="">-- Choose Customer --</option>
                            <?php while($c = mysqli_fetch_assoc($customers)): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($customer_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['customer_name']); ?> - <?php echo $c['mobile']; ?> (Empty: <?php echo $c['empty_bottles_balance']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-light w-100 rounded-pill">
                            <i class="fas fa-eye me-2"></i> Show Tracking
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="fas fa-chart-bar fa-3x text-white opacity-50"></i>
            </div>
        </div>
    </div>

    <?php if($customer_id && $customer_name): ?>
        <!-- Customer Info -->
        <div class="customer-info-card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-circle fa-3x" style="color: #A04657;"></i>
                        </div>
                        <div>
                            <h3 class="mb-1"><?php echo htmlspecialchars($customer_name); ?></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-phone me-1"></i> <?php echo $customer_mobile ?: 'No mobile'; ?>
                                <br><i class="fas fa-wine-bottle me-1"></i> Current Empty Bottles: 
                                <strong class="text-primary"><?php echo $current_empty_balance; ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row g-3">
                        <div class="col-4">
                            <div class="bottle-stats delivered">
                                <i class="fas fa-truck"></i>
                                <h4><?php echo number_format($total_delivered); ?></h4>
                                <small>Total Delivered</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bottle-stats returned">
                                <i class="fas fa-undo-alt"></i>
                                <h4><?php echo number_format($total_returned); ?></h4>
                                <small>Total Returned</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bottle-stats broken">
                                <i class="fas fa-wine-bottle"></i>
                                <h4><?php echo number_format($total_broken); ?></h4>
                                <small>Total Broken</small>
                            </div>
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
                                <th style="width: 100px" class="text-center">Delivered</th>
                                <th style="width: 100px" class="text-center">Returned</th>
                                <th style="width: 100px" class="text-center">Broken</th>
                                <th style="width: 120px" class="text-center">Pending Empties</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($tracking && mysqli_num_rows($tracking) > 0): ?>
                                <?php while($t = mysqli_fetch_assoc($tracking)): ?>
                                    <tr>
                                        <td>
                                            <i class="far fa-calendar-alt me-1 text-muted"></i>
                                            <?php echo date('d-m-Y h:i A', strtotime($t['tracking_date'])); ?>
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
                                            <?php if($t['bottles_broken'] > 0): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1 rounded-pill">
                                                    <i class="fas fa-wine-bottle"></i> <?php echo $t['bottles_broken']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-primary"><?php echo $t['pending_empties']; ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $t['notes'] ? htmlspecialchars($t['notes']) : '—'; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-boxes"></i>
                                        <p class="mb-0">No bottle movement found for this customer.</p>
                                        <small class="text-muted">Bottle tracking is automatically created when deliveries are recorded.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bottle Balance Summary Card -->
        <div class="row mt-4">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light border-0 rounded-4">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-chart-pie fa-2x mb-2" style="color: #A04657;"></i>
                        <h5>Bottle Balance Summary</h5>
                        <div class="row mt-3">
                            <div class="col-4">
                                <div class="border-end">
                                    <h6 class="text-muted mb-1">Total Received</h6>
                                    <h4 class="text-primary mb-0"><?php echo number_format($total_delivered); ?></h4>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h6 class="text-muted mb-1">Total Returned</h6>
                                    <h4 class="text-success mb-0"><?php echo number_format($total_returned); ?></h4>
                                </div>
                            </div>
                            <div class="col-4">
                                <h6 class="text-muted mb-1">Current Pending</h6>
                                <h4 class="text-warning mb-0"><?php echo number_format($current_empty_balance); ?></h4>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <?php 
                            $return_percent = ($total_delivered > 0) ? ($total_returned / $total_delivered) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $return_percent; ?>%" role="progressbar"></div>
                            <div class="progress-bar bg-warning" style="width: <?php echo 100 - $return_percent; ?>%" role="progressbar"></div>
                        </div>
                        <small class="text-muted mt-2 d-block">Return Rate: <?php echo number_format($return_percent, 1); ?>%</small>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif($customer_id && !$customer_name): ?>
        <div class="alert alert-warning rounded-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Customer not found. Please select a valid customer.
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="card tracking-card">
            <div class="card-body text-center py-5">
                <i class="fas fa-boxes fa-4x mb-3 text-muted opacity-25"></i>
                <h4 class="text-muted">Select a Customer to View Bottle Tracking</h4>
                <p class="text-muted">Choose a customer from the dropdown above to see their bottle movement history.</p>
            </div>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
// Auto-submit when customer is selected
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.querySelector('select[name="customer_id"]');
    if(customerSelect) {
        customerSelect.addEventListener('change', function() {
            if(this.value) {
                this.closest('form').submit();
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>