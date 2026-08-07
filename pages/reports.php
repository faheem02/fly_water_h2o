<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$report_type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$where_dates = "DATE(delivery_datetime) BETWEEN '$from_date' AND '$to_date'";

$report_data = '';
$report_title = '';

// Get summary for dashboard cards
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM customers WHERE status='Active'"))['total'];
$total_outstanding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(outstanding_balance) as total FROM customers"))['total'] ?? 0;
$total_bottles_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(empty_bottles_balance) as total FROM customers"))['total'] ?? 0;

if($report_type == 'monthly') {
    $report_title = "Monthly Sales Report - " . date('F Y', strtotime($month . '-01'));
    
    $monthly_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total, COUNT(*) as deliveries, SUM(bottles_delivered) as bottles FROM water_deliveries WHERE DATE_FORMAT(delivery_datetime, '%Y-%m')='$month'"));
    
    // Get monthly breakdown by day
    $monthly_breakdown = mysqli_query($conn, "SELECT DAY(delivery_datetime) as day, SUM(total_amount) as total, COUNT(*) as count FROM water_deliveries WHERE DATE_FORMAT(delivery_datetime, '%Y-%m')='$month' GROUP BY DAY(delivery_datetime) ORDER BY day ASC");
    
    // Get previous month comparison
    $prev_month = date('Y-m', strtotime($month . '-01 -1 month'));
    $prev_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM water_deliveries WHERE DATE_FORMAT(delivery_datetime, '%Y-%m')='$prev_month'"))['total'] ?? 0;
    
    $growth = 0;
    if($prev_sales > 0) {
        $growth = (($monthly_sales['total'] - $prev_sales) / $prev_sales) * 100;
    }
    
    $report_data = '
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="summary-card bg-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Total Sales</small>
                        <h3 class="mb-0">Rs ' . number_format($monthly_sales['total'], 2) . '</h3>
                    </div>
                    <i class="fas fa-chart-line fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card bg-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Total Deliveries</small>
                        <h3 class="mb-0">' . number_format($monthly_sales['deliveries']) . '</h3>
                    </div>
                    <i class="fas fa-truck fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card bg-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>Bottles Delivered</small>
                        <h3 class="mb-0">' . number_format($monthly_sales['bottles']) . '</h3>
                    </div>
                    <i class="fas fa-wine-bottle fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card ' . ($growth >= 0 ? 'bg-warning' : 'bg-danger') . '">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small>vs Previous Month</small>
                        <h3 class="mb-0">' . ($growth >= 0 ? '+' : '') . number_format($growth, 1) . '%</h3>
                    </div>
                    <i class="fas ' . ($growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down') . ' fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>';
    
    if(mysqli_num_rows($monthly_breakdown) > 0) {
        $report_data .= '
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Deliveries</th>
                        <th class="text-end">Total Amount (Rs)</th>
                    </tr>
                </thead>
                <tbody>';
        while($day = mysqli_fetch_assoc($monthly_breakdown)) {
            $report_data .= '
                <tr>
                    <td>' . date('d-m-Y', strtotime($month . '-' . str_pad($day['day'], 2, '0', STR_PAD_LEFT))) . '</td>
                    <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">' . $day['count'] . ' deliveries</span></td>
                    <td class="text-end fw-bold">Rs ' . number_format($day['total'], 2) . '</td>
                </tr>';
        }
        $report_data .= '
                </tbody>
            </table>
        </div>';
    } else {
        $report_data .= '<div class="empty-state"><i class="fas fa-chart-line"></i><p>No sales data found for selected month</p></div>';
    }
    
} elseif($report_type == 'outstanding') {
    $report_title = "Customer Outstanding Report";
    $outstanding = mysqli_query($conn, "SELECT c.id, c.customer_name, c.mobile, c.outstanding_balance FROM customers c WHERE c.outstanding_balance > 0 ORDER BY c.outstanding_balance DESC");
    $total_outstanding_sum = 0;
    
    $report_data = '<div class="table-responsive"><table class="report-table">';
    $report_data .= '<thead><tr><th>#</th><th>Customer Name</th><th>Mobile</th><th class="text-end">Outstanding (Rs)</th></tr></thead><tbody>';
    $count = 1;
    while($o = mysqli_fetch_assoc($outstanding)) {
        $total_outstanding_sum += $o['outstanding_balance'];
        $report_data .= '<tr>
            <td>' . $count++ . '</td>
            <td><strong>' . htmlspecialchars($o['customer_name']) . '</strong></td>
            <td>' . $o['mobile'] . '</td>
            <td class="text-end text-danger fw-bold">Rs ' . number_format($o['outstanding_balance'], 2) . '</td>
        </tr>';
    }
    if($count == 1) {
        $report_data .= '<tr><td colspan="4" class="empty-state">No outstanding customers found</td></tr>';
    }
    $report_data .= '<tr class="total-row"><td colspan="3" class="text-end fw-bold">TOTAL OUTSTANDING</td><td class="text-end fw-bold">Rs ' . number_format($total_outstanding_sum, 2) . '</td></tr>';
    $report_data .= '</tbody></table></div>';
    
} elseif($report_type == 'bottle_balance') {
    $report_title = "Bottle Balance Report";
    $bottles = mysqli_query($conn, "SELECT c.customer_name, c.mobile, c.empty_bottles_balance FROM customers c WHERE c.empty_bottles_balance != 0 ORDER BY c.empty_bottles_balance DESC");
    $total_bottles = 0;
    
    $report_data = '<div class="table-responsive"><table class="report-table">';
    $report_data .= '<thead><tr><th>#</th><th>Customer Name</th><th>Mobile</th><th class="text-end">Empty Bottles</th></tr></thead><tbody>';
    $count = 1;
    while($b = mysqli_fetch_assoc($bottles)) {
        $total_bottles += $b['empty_bottles_balance'];
        $report_data .= '<tr>
            <td>' . $count++ . '</td>
            <td><strong>' . htmlspecialchars($b['customer_name']) . '</strong></td>
            <td>' . $b['mobile'] . '</td>
            <td class="text-end"><span class="badge bg-warning rounded-pill px-3">' . number_format($b['empty_bottles_balance']) . ' bottles</span></td>
        </tr>';
    }
    if($count == 1) {
        $report_data .= '<tr><td colspan="4" class="empty-state">No bottles pending with customers</td></tr>';
    }
    $report_data .= '<tr class="total-row"><td colspan="3" class="text-end fw-bold">TOTAL PENDING BOTTLES</td><td class="text-end fw-bold">' . number_format($total_bottles) . ' bottles</td></tr>';
    $report_data .= '</tbody></table></div>';
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.reports-wrapper {
    padding: 20px;
}

.report-header {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    color: white;
}

.report-header h2 {
    color: white;
    margin: 0;
    font-size: 24px;
}

.report-header p {
    margin: 5px 0 0;
    opacity: 0.9;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.filter-label {
    font-weight: 600;
    font-size: 13px;
    color: #555;
    margin-bottom: 8px;
    display: block;
}

.filter-control {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
    width: 100%;
}

.filter-control:focus {
    border-color: #A04657;
    outline: none;
    box-shadow: 0 0 0 2px rgba(160,70,87,0.1);
}

.btn-generate {
    background: #A04657;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 25px;
    font-weight: 500;
    width: 100%;
    transition: all 0.2s;
}

.btn-generate:hover {
    background: #7a3542;
    transform: translateY(-2px);
}

.result-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.result-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #A04657;
    display: inline-block;
}

.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    transition: transform 0.2s;
}

.summary-card:hover {
    transform: translateY(-3px);
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th {
    background: #f8f9fa;
    padding: 12px;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 2px solid #dee2e6;
}

.report-table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
    font-size: 13px;
}

.report-table tr:hover {
    background: #f8f9fa;
}

.total-row {
    background: #f8f9fa;
    font-weight: 700;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.3;
}

.quick-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.quick-btn {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 30px;
    padding: 8px 20px;
    font-size: 13px;
    color: #555;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-btn:hover, .quick-btn.active {
    background: #A04657;
    color: white;
    border-color: #A04657;
}

@media (max-width: 768px) {
    .report-table {
        font-size: 11px;
    }
    .report-table th,
    .report-table td {
        padding: 8px;
    }
    .summary-card h3 {
        font-size: 18px;
    }
}
</style>

<div class="main-wrapper">
<div class="reports-wrapper container-fluid p-4">

    <!-- Report Header -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><i class="fas fa-chart-bar me-2"></i> Reports Dashboard</h2>
                <p>View and analyze your business performance</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="row">
                    <div class="col-6">
                        <div class="small text-white-50">Total Customers</div>
                        <h4 class="text-white mb-0"><?php echo number_format($total_customers); ?></h4>
                    </div>
                    <div class="col-6">
                        <div class="small text-white-50">Total Outstanding</div>
                        <h4 class="text-white mb-0">Rs <?php echo number_format($total_outstanding, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Report Buttons -->
    <div class="quick-buttons">
        <a href="?type=monthly" class="quick-btn <?php echo $report_type == 'monthly' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt me-1"></i> Monthly Sales
        </a>
        <a href="?type=outstanding" class="quick-btn <?php echo $report_type == 'outstanding' ? 'active' : ''; ?>">
            <i class="fas fa-rupee-sign me-1"></i> Outstanding
        </a>
        <a href="?type=bottle_balance" class="quick-btn <?php echo $report_type == 'bottle_balance' ? 'active' : ''; ?>">
            <i class="fas fa-wine-bottle me-1"></i> Bottle Balance
        </a>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            
            <?php if($report_type == 'monthly'): ?>
            <div class="col-md-3">
                <label class="filter-label"><i class="fas fa-calendar-alt me-1"></i> Select Month</label>
                <input type="month" name="month" class="filter-control" value="<?php echo $month; ?>">
            </div>
            <?php else: ?>
            <div class="col-md-3">
                <label class="filter-label"><i class="fas fa-calendar-alt me-1"></i> From Date</label>
                <input type="date" name="from_date" class="filter-control" value="<?php echo $from_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="filter-label"><i class="fas fa-calendar-alt me-1"></i> To Date</label>
                <input type="date" name="to_date" class="filter-control" value="<?php echo $to_date; ?>">
            </div>
            <?php endif; ?>
            
            <div class="col-md-6">
                <button type="submit" class="btn-generate">
                    <i class="fas fa-chart-line me-2"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Report Result -->
    <div class="result-card">
        <div class="result-title">
            <i class="fas fa-chart-pie me-2" style="color: #A04657;"></i> <?php echo $report_title; ?>
        </div>
        <div class="mt-3">
            <?php echo $report_data; ?>
        </div>
    </div>

  <!-- Print Button -->
    <div class="row mt-4">
        <div class="col-12 text-end">
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>

</div>
</div>

<!-- Print Styles -->
<style media="print">
    .fixed-header, .fixed-sidebar, .fixed-footer, .filter-card, .quick-buttons, .btn-outline-secondary, .report-header .text-end, .print-hide {
        display: none !important;
    }
    .reports-wrapper {
        padding: 0 !important;
        margin: 0 !important;
    }
    .result-card {
        box-shadow: none !important;
        padding: 0 !important;
    }
    .report-table th {
        background: #f0f0f0 !important;
    }
    @page {
        size: A4;
        margin: 1cm;
    }
</style>

<?php include '../includes/footer.php'; ?>