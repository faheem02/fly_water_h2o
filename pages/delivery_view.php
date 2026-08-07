<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$salesman_where = is_salesman() ? ' AND ' . salesman_match_condition($conn) : '';

$salesman = isset($_GET['salesman']) ? mysqli_real_escape_string($conn, $_GET['salesman']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$where = " WHERE 1=1";
if ($salesman) $where .= " AND c.salesman LIKE '%$salesman%'";
if ($from_date) $where .= " AND DATE(d.delivery_datetime) >= '$from_date'";
if ($to_date) $where .= " AND DATE(d.delivery_datetime) <= '$to_date'";
$where .= $salesman_where;

$query = "SELECT d.*, p.product_name, p.track_empty_bottles, c.customer_name, c.mobile, c.salesman as cust_salesman,
                 COALESCE((SELECT SUM(cl.credit_amount) FROM customer_ledger cl WHERE cl.reference_id = d.id AND cl.reference_type = 'payment'), 0) as cash_received
          FROM water_deliveries d 
          LEFT JOIN customers c ON d.customer_id = c.id 
          LEFT JOIN products p ON d.product_id = p.id 
          $where 
          ORDER BY d.delivery_datetime DESC";

$deliveries = mysqli_query($conn, $query);

// Summary
$summary_query = "SELECT COUNT(*) as total_deliveries, COALESCE(SUM(d.bottles_delivered),0) as total_bottles, COALESCE(SUM(d.total_amount),0) as total_amount 
                  FROM water_deliveries d 
                  LEFT JOIN customers c ON d.customer_id = c.id 
                  $where";
$summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_query));

// Total cash received summary
$cash_query = "SELECT COALESCE(SUM(cl.credit_amount),0) as total_cash_received 
               FROM customer_ledger cl 
               INNER JOIN water_deliveries d ON cl.reference_id = d.id 
               LEFT JOIN customers c ON d.customer_id = c.id 
               WHERE cl.reference_type = 'payment'";
if ($salesman) $cash_query .= " AND c.salesman LIKE '%$salesman%'";
if ($from_date) $cash_query .= " AND DATE(d.delivery_datetime) >= '$from_date'";
if ($to_date) $cash_query .= " AND DATE(d.delivery_datetime) <= '$to_date'";
$cash_query .= $salesman_where;
$total_cash_received = mysqli_fetch_assoc(mysqli_query($conn, $cash_query))['total_cash_received'];
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.delivery-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px 10px;
    white-space: nowrap;
}
.delivery-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.delivery-table tbody tr:hover { background: #f8f9fa; }
.empty-state { text-align: center; padding: 60px 20px; color: #b0bec5; }
.empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
@media print { .no-print { display: none !important; } }
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-eye me-2" style="color: #A04657;"></i> Delivery View Point
        </h2>
        <div class="d-flex gap-2 no-print">
            <button onclick="printDeliveries()" class="btn btn-outline-dark rounded-pill px-4">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-user-tie me-1"></i> Salesman</label>
                    <input type="text" name="salesman" class="form-control" placeholder="Salesman..." value="<?php echo htmlspecialchars($salesman); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> From</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i> To</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                    <a href="delivery_view.php" class="btn btn-outline-secondary flex-fill" style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Bar -->
    <div class="d-flex flex-wrap gap-4 mb-4">
        <div><strong>Total Deliveries:</strong> <span class="badge bg-primary rounded-pill"><?php echo number_format($summary['total_deliveries']); ?></span></div>
        <div><strong>Total Bottles:</strong> <span class="badge bg-info rounded-pill"><?php echo number_format($summary['total_bottles']); ?></span></div>
        <div><strong>Total Amount:</strong> <span class="badge bg-success rounded-pill">Rs <?php echo number_format($summary['total_amount'], 2); ?></span></div>
        <div><strong>Total Cash Received:</strong> <span class="badge bg-warning rounded-pill">Rs <?php echo number_format($total_cash_received, 2); ?></span></div>
    </div>

    <!-- Deliveries Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table delivery-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th style="min-width:130px">Customer</th>
                            <th style="min-width:100px">Mobile</th>
                            <th style="min-width:100px">Salesman</th>
                            <th style="min-width:100px">Entry By</th>
                            <th style="min-width:100px">Product</th>
                            <th style="width:70px" class="text-center">Bottles</th>
                            <th style="width:70px" class="text-center">Empty</th>
                            <th style="width:90px" class="text-end">Rate</th>
                            <th style="width:110px" class="text-end">Total (Rs)</th>
                            <th style="width:110px" class="text-end">Cash Received</th>
                            <th style="width:130px">Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deliveries && mysqli_num_rows($deliveries) > 0): ?>
                            <?php while($d = mysqli_fetch_assoc($deliveries)): 
                                $display_salesman = $d['cust_salesman'] ?: '-';
                            ?>
                            <tr>
                                <td><?php echo $d['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($d['customer_name'] ?? 'Walk-in'); ?></strong></td>
                                <td><?php echo htmlspecialchars($d['mobile'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($display_salesman); ?></td>
                                <td><?php echo !empty($d['salesman']) ? '<span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">' . htmlspecialchars($d['salesman']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                                <td><?php echo htmlspecialchars($d['product_name'] ?? 'N/A'); ?></td>
                                <td class="text-center"><span class="badge bg-primary rounded-pill"><?php echo $d['bottles_delivered']; ?></span></td>
                                <td class="text-center"><?php echo (!empty($d['track_empty_bottles'])) ? $d['empty_bottles_returned'] : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-end"><?php echo number_format($d['bottle_rate'], 2); ?></td>
                                <td class="text-end fw-bold text-success">Rs <?php echo number_format($d['total_amount'], 2); ?></td>
                                <td class="text-end fw-semibold" style="color: #e67e22;">Rs <?php echo number_format($d['cash_received'], 2); ?></td>
                                <td><?php echo date('d/m/y h:i A', strtotime($d['delivery_datetime'])); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($d['notes'] ?? '-'); ?></small></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="fas fa-truck fa-3x mb-3 d-block opacity-25"></i>
                                    No deliveries found.
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
                <span class="print-doc-title">Delivery Report</span>
                <?php if($salesman): ?>
                    <span class="print-date-range">
                        <?php 
                        $print_parts = [];
                        if($salesman) $print_parts[] = 'Salesman: ' . htmlspecialchars($salesman);
                        echo implode(' | ', $print_parts);
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Product</th>
                    <th style="width:70px;" class="text-end">Bottles</th>
                    <th style="width:100px;" class="text-end">Total (Rs)</th>
                    <th style="width:100px;" class="text-end">Cash Received</th>
                    <th style="width:90px;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $print_result = mysqli_query($conn, $query);
                $sno = 1;
                $print_bottles = 0;
                $print_amount = 0;
                $print_cash = 0;
                if($print_result && mysqli_num_rows($print_result) > 0):
                    while($d = mysqli_fetch_assoc($print_result)):
                        $print_bottles += $d['bottles_delivered'];
                        $print_amount += $d['total_amount'];
                        $print_cash += $d['cash_received'];
                ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><strong><?php echo htmlspecialchars($d['customer_name'] ?? 'Walk-in'); ?></strong></td>
                        <td><?php echo htmlspecialchars($d['mobile'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($d['product_name'] ?? 'N/A'); ?></td>
                        <td class="text-end"><?php echo $d['bottles_delivered']; ?></td>
                        <td class="text-end"><?php echo number_format($d['total_amount'], 2); ?></td>
                        <td class="text-end">Rs <?php echo number_format($d['cash_received'], 2); ?></td>
                        <td><?php echo date('d/m/y', strtotime($d['delivery_datetime'])); ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="font-weight:700;background:#f0f0f0;">
                        <td colspan="4" class="text-end">Total</td>
                        <td class="text-end"><?php echo $print_bottles; ?></td>
                        <td class="text-end">Rs <?php echo number_format($print_amount, 2); ?></td>
                        <td class="text-end">Rs <?php echo number_format($print_cash, 2); ?></td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center" style="padding:40px;color:#999;">No deliveries found.</td></tr>
                <?php endif; ?>
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
.print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
</style>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
function printDeliveries() {
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
                    <title>Delivery Report</title>
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
