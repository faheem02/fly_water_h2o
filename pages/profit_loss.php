<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

$from_dt = date('Y-m-d', strtotime($from_date)) . ' 00:00:00';
$to_dt = date('Y-m-d', strtotime($to_date)) . ' 23:59:59';

// Income: Water deliveries (sales)
$sales_query = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS total FROM water_deliveries WHERE delivery_datetime BETWEEN '$from_dt' AND '$to_dt'");
$total_sales = mysqli_fetch_assoc($sales_query)['total'];

$sales_by_product = mysqli_query($conn, "SELECT COALESCE(p.product_name,'Product') AS product_name, COUNT(*) AS deliveries, COALESCE(SUM(wd.bottles_delivered),0) AS bottles, COALESCE(SUM(wd.total_amount),0) AS amount
    FROM water_deliveries wd LEFT JOIN products p ON wd.product_id = p.id
    WHERE wd.delivery_datetime BETWEEN '$from_dt' AND '$to_dt'
    GROUP BY wd.product_id, p.product_name ORDER BY amount DESC");

// COGS: Raw material purchases
$purchase_query = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS total FROM raw_material_purchases WHERE purchase_date BETWEEN '$from_dt' AND '$to_dt'");
$total_purchases = mysqli_fetch_assoc($purchase_query)['total'];

// Operating expenses
$expense_query = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date'");
$total_expenses = mysqli_fetch_assoc($expense_query)['total'];

$expenses_by_category = mysqli_query($conn, "SELECT COALESCE(ec.category_name,'Other') AS category_name, COUNT(*) AS count, COALESCE(SUM(e.amount),0) AS amount
    FROM expenses e LEFT JOIN expense_categories ec ON e.expense_category = ec.id
    WHERE e.expense_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY e.expense_category, ec.category_name ORDER BY amount DESC");

// Totals
$total_income = $total_sales;
$total_cost = $total_purchases + $total_expenses;
$gross_profit = $total_sales - $total_purchases;
$net_profit = $total_sales - $total_cost;
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.payment-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.payment-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.payment-table th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}
.payment-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.summary-card {
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}
.summary-card:hover {
    transform: translateY(-3px);
}
.summary-card.income {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
    color: white;
}
.summary-card.cogs {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    color: white;
}
.summary-card.expense {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    color: white;
}
.summary-card.net {
    background: linear-gradient(135deg, #1e8449 0%, #27ae60 100%);
    color: white;
}
.pl-section td {
    background: #f8e3e7;
    color: #A04657;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pl-total-row {
    background: #fdf2f4;
    font-weight: 700;
}
.pl-net-profit {
    background: #e9f7ef !important;
    font-weight: 800;
    font-size: 15px;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-chart-pie me-2" style="color: #A04657;"></i> Profit & Loss
        </h2>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" onclick="printPL()">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card income">
                <small>Total Income</small>
                <h4 class="mb-0">Rs <?php echo number_format($total_income, 2); ?></h4>
                <small>Water deliveries (sales)</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card cogs">
                <small>Cost of Goods Sold</small>
                <h4 class="mb-0">Rs <?php echo number_format($total_purchases, 2); ?></h4>
                <small>Stock / raw material purchases</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card expense">
                <small>Total Expenses</small>
                <h4 class="mb-0">Rs <?php echo number_format($total_expenses, 2); ?></h4>
                <small>Operating expenses</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card <?php echo $net_profit >= 0 ? 'net' : 'expense'; ?>">
                <small>Net Profit / (Loss)</small>
                <h4 class="mb-0">Rs <?php echo number_format($net_profit, 2); ?></h4>
                <small><?php echo $net_profit >= 0 ? 'Net Profit' : 'Net Loss'; ?> (Gross: Rs <?php echo number_format($gross_profit, 2); ?>)</small>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card payment-card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1 d-block">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>" style="height: 46px; border-radius: 8px;" required>
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1 d-block">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>" style="height: 46px; border-radius: 8px;" required>
                </div>
                <div class="col-auto align-self-end">
                    <button type="submit" class="btn btn-primary" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-filter me-1"></i> Generate
                    </button>
                </div>
                <div class="col-auto align-self-end">
                    <a href="profit_loss.php" class="btn btn-outline-secondary" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                </div>
                <div class="col-auto align-self-end">
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-2">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo date('d-m-Y', strtotime($from_date)); ?> to <?php echo date('d-m-Y', strtotime($to_date)); ?>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- P&L Statement -->
    <div class="card payment-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-chart-pie me-2"></i> Profit & Loss Statement</span>
            <span class="badge bg-white text-dark rounded-pill"><?php echo mysqli_num_rows($sales_by_product) > 0 ? mysqli_num_rows($sales_by_product) . ' products' : 'No sales'; ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table payment-table mb-0">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th class="text-end">Details (Rs)</th>
                            <th class="text-end" style="width:150px;">Amount (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- INCOME SECTION -->
                        <tr class="pl-section">
                            <td colspan="3"><i class="fas fa-arrow-circle-down me-2"></i> Income</td>
                        </tr>
                        <?php if($sales_by_product && mysqli_num_rows($sales_by_product) > 0): ?>
                            <?php while($s = mysqli_fetch_assoc($sales_by_product)): ?>
                                <tr>
                                    <td style="padding-left: 30px;"><?php echo htmlspecialchars($s['product_name']); ?> Sales <small class="text-muted">(<?php echo number_format($s['deliveries']); ?> deliveries / <?php echo number_format($s['bottles']); ?> bottles)</small></td>
                                    <td class="text-end"><?php echo number_format($s['amount'], 2); ?></td>
                                    <td></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td style="padding-left: 30px;">Sales (Water Deliveries)</td>
                                <td class="text-end"><?php echo number_format($total_sales, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="pl-total-row">
                            <td>Total Income</td>
                            <td></td>
                            <td class="text-end text-success"><?php echo number_format($total_income, 2); ?></td>
                        </tr>

                        <!-- EXPENSES SECTION -->
                        <tr class="pl-section">
                            <td colspan="3"><i class="fas fa-arrow-circle-up me-2"></i> Expenses</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 30px;">Cost of Goods Sold <small class="text-muted">(Stock / Raw Material Purchases)</small></td>
                            <td class="text-end"><?php echo number_format($total_purchases, 2); ?></td>
                            <td></td>
                        </tr>
                        <?php if($expenses_by_category && mysqli_num_rows($expenses_by_category) > 0): ?>
                            <?php while($e = mysqli_fetch_assoc($expenses_by_category)): ?>
                                <tr>
                                    <td style="padding-left: 45px;"><small class="text-muted"><?php echo htmlspecialchars($e['category_name']); ?> (<?php echo $e['count']; ?>)</small></td>
                                    <td class="text-end"><small class="text-muted"><?php echo number_format($e['amount'], 2); ?></small></td>
                                    <td></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        <tr>
                            <td style="padding-left: 30px;">General Expenses</td>
                            <td class="text-end"><?php echo number_format($total_expenses, 2); ?></td>
                            <td></td>
                        </tr>
                        <tr class="pl-total-row">
                            <td>Total Expenses</td>
                            <td></td>
                            <td class="text-end text-danger"><?php echo number_format($total_cost, 2); ?></td>
                        </tr>

                        <!-- NET PROFIT -->
                        <tr class="pl-net-profit">
                            <td><?php echo $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS'; ?></td>
                            <td class="text-end text-muted small">(Gross Profit: Rs <?php echo number_format($gross_profit, 2); ?>)</td>
                            <td class="text-end <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Rs <?php echo number_format($net_profit, 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Print Report Overlay -->
<div id="print-overlay" style="display:none;">
    <div id="print-area">
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle"><i class="fas fa-tint"></i></div>
                <div class="print-brand-text">
                    <div class="print-owner-name"><?php echo htmlspecialchars($owner_name); ?></div>
                    <div class="print-company"><?php echo htmlspecialchars($company_name); ?></div>
                    <div class="print-address"><?php echo htmlspecialchars($owner_address); ?></div>
                    <div class="print-phone"><?php echo htmlspecialchars($owner_phone); ?></div>
                </div>
            </div>
            <div class="print-divider"></div>
            <div class="print-title-row">
                <span class="print-doc-title">Profit &amp; Loss Statement</span>
                <span class="print-date-range"><?php echo date('d-m-Y', strtotime($from_date)); ?> to <?php echo date('d-m-Y', strtotime($to_date)); ?></span>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Particulars</th>
                    <th class="text-end" style="width:130px;">Details (Rs)</th>
                    <th class="text-end" style="width:130px;">Amount (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="print-section"><td colspan="3">INCOME</td></tr>
                <?php
                if($sales_by_product && mysqli_num_rows($sales_by_product) > 0):
                    mysqli_data_seek($sales_by_product, 0);
                    while($s = mysqli_fetch_assoc($sales_by_product)): ?>
                        <tr>
                            <td style="padding-left:18px;"><?php echo htmlspecialchars($s['product_name']); ?> Sales (<?php echo number_format($s['deliveries']); ?> deliveries)</td>
                            <td class="text-end"><?php echo number_format($s['amount'], 2); ?></td>
                            <td></td>
                        </tr>
                <?php endwhile; endif; ?>
                <tr class="print-total">
                    <td>Total Income</td>
                    <td></td>
                    <td class="text-end"><?php echo number_format($total_income, 2); ?></td>
                </tr>

                <tr class="print-section"><td colspan="3">EXPENSES</td></tr>
                <tr>
                    <td style="padding-left:18px;">Cost of Goods Sold (Stock / Raw Material Purchases)</td>
                    <td class="text-end"><?php echo number_format($total_purchases, 2); ?></td>
                    <td></td>
                </tr>
                <?php
                if($expenses_by_category && mysqli_num_rows($expenses_by_category) > 0):
                    mysqli_data_seek($expenses_by_category, 0);
                    while($e = mysqli_fetch_assoc($expenses_by_category)): ?>
                        <tr>
                            <td style="padding-left:30px;"><?php echo htmlspecialchars($e['category_name']); ?> (<?php echo $e['count']; ?>)</td>
                            <td class="text-end"><?php echo number_format($e['amount'], 2); ?></td>
                            <td></td>
                        </tr>
                <?php endwhile; endif; ?>
                <tr>
                    <td style="padding-left:18px;">General Expenses</td>
                    <td class="text-end"><?php echo number_format($total_expenses, 2); ?></td>
                    <td></td>
                </tr>
                <tr class="print-total">
                    <td>Total Expenses</td>
                    <td></td>
                    <td class="text-end"><?php echo number_format($total_cost, 2); ?></td>
                </tr>

                <tr class="print-profit">
                    <td><?php echo $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS'; ?></td>
                    <td class="text-end">(Gross Profit: <?php echo number_format($gross_profit, 2); ?>)</td>
                    <td class="text-end"><?php echo number_format($net_profit, 2); ?></td>
                </tr>
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
    padding: 35px 40px;
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    color: #222;
}
.print-header { margin-bottom: 22px; }
.print-brand-row { display: flex; align-items: center; gap: 18px; }
.print-logo-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; flex-shrink: 0; }
.print-brand-text { display: flex; flex-direction: column; gap: 2px; }
.print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-address { font-size: 13px; color: #666; }
.print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
.print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
.print-title-row { display: flex; justify-content: space-between; align-items: center; }
.print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: 'Quicksand', 'Segoe UI', Arial, sans-serif; }
.print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-section td { background: #f2d9dd; font-weight: 700; letter-spacing: 0.5px; }
.print-total td { background: #fdf2f4; font-weight: 700; }
.print-profit td { background: #e9f7ef; font-weight: 800; }
.print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
</style>

<script>
function printPL() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Profit &amp; Loss Statement</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f0f0f0; }
                @page { size: A4 landscape; margin: 8mm; }
                .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 2px solid #A04657; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .toolbar button { padding: 8px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
                .btn-print { background: #A04657; color: #fff; }
                .btn-print:hover { background: #8a3a4a; }
                .btn-close { background: #6c757d; color: #fff; margin-left: auto; }
                .btn-close:hover { background: #5a6268; }
                .report-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
                .print-header { margin-bottom: 22px; }
                .print-brand-row { display: flex; align-items: center; gap: 18px; }
                .print-logo-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #A04657, #c96b7e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; flex-shrink: 0; }
                .print-brand-text { display: flex; flex-direction: column; gap: 2px; }
                .print-company { font-size: 18px; font-weight: 700; color: #A04657; font-family: Arial, sans-serif; }
                .print-owner-name { font-size: 22px; font-weight: 800; color: #222; font-family: Arial, sans-serif; }
                .print-address { font-size: 13px; color: #666; }
                .print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
                .print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
                .print-title-row { display: flex; justify-content: space-between; align-items: center; }
                .print-doc-title { font-size: 15px; font-weight: 700; color: #444; font-family: Arial, sans-serif; }
                .print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
                table.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
                .print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; }
                .print-table th.text-end, .print-table td.text-end { text-align: right; }
                .print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
                .print-table tbody tr:nth-child(even) { background: #f9f9f9; }
                .print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
                .print-section td { background: #f2d9dd; font-weight: 700; letter-spacing: 0.5px; }
                .print-total td { background: #fdf2f4; font-weight: 700; }
                .print-profit td { background: #e9f7ef; font-weight: 800; }
                .print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Profit &amp; Loss Statement</strong>
                <button class="btn-print" onclick="window.print()">Print</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <div class="report-wrap">
                ${content}
            </div>
        </body>
        </html>
    `);
    w.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>
