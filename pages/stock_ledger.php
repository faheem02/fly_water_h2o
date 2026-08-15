<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

// Filters
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Products dropdown
$products = mysqli_query($conn, "SELECT id, product_name, product_code FROM products ORDER BY product_name");

// Build where clause
$where = "WHERE 1=1";
if ($product_filter > 0) $where .= " AND sl.product_id = $product_filter";
if ($from_date) $where .= " AND DATE(sl.transaction_date) >= '$from_date'";
if ($to_date) $where .= " AND DATE(sl.transaction_date) <= '$to_date'";

// Fetch rows ascending so running balances build up correctly
$ledger = mysqli_query($conn, "SELECT sl.*, p.product_name, p.product_code FROM stock_ledger sl JOIN products p ON sl.product_id = p.id $where ORDER BY sl.transaction_date ASC, sl.id ASC");

$rows = [];
$product_names = [];
if ($ledger && mysqli_num_rows($ledger) > 0) {
    while ($sl = mysqli_fetch_assoc($ledger)) {
        $rows[] = $sl;
        $product_names[$sl['product_id']] = $sl['product_name'];
    }
}

// Opening balance per product = net of all ledger entries before the filter window.
// Only queried when a from_date is set; with no from_date the window covers all
// history so the opening is 0. Computed from quantity_in/out (not stored
// running_stock) so it stays correct even after edits/deletions.
$opening_map = [];
foreach (array_keys($product_names) as $pid) {
    if ($from_date) {
        $oq = mysqli_query($conn, "SELECT COALESCE(SUM(quantity_in),0) - COALESCE(SUM(quantity_out),0) AS opening FROM stock_ledger WHERE product_id=$pid AND transaction_date < '$from_date 00:00:00'");
        $opening_map[$pid] = ($oq && mysqli_num_rows($oq) > 0) ? floatval(mysqli_fetch_assoc($oq)['opening']) : 0;
    } else {
        $opening_map[$pid] = 0;
    }
}

// Compute running balances + totals
$running_map = $opening_map;
$total_in = 0;
$total_out = 0;
foreach ($rows as $i => $r) {
    $pid = $r['product_id'];
    $running_map[$pid] += floatval($r['quantity_in']) - floatval($r['quantity_out']);
    $rows[$i]['balance'] = $running_map[$pid];
    $total_in += floatval($r['quantity_in']);
    $total_out += floatval($r['quantity_out']);
}
$net_change = $total_in - $total_out;

// Selected product name for titles
$selected_product_name = '';
if ($product_filter > 0) {
    $sp = mysqli_query($conn, "SELECT product_name FROM products WHERE id=$product_filter");
    if ($sp && mysqli_num_rows($sp) > 0) $selected_product_name = mysqli_fetch_assoc($sp)['product_name'];
}

function ref_type_label($type, $in) {
    switch ($type) {
        case 'opening': return 'Opening Stock';
        case 'production': return 'Production';
        case 'delivery': return 'Delivery';
        case 'adjustment': return $in > 0 ? 'Adjustment In' : 'Adjustment Out';
        case 'stock_add': return 'Add Stock';
        default: return ucfirst(str_replace('_', ' ', $type));
    }
}
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
.ledger-table th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}
.ledger-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}
.ledger-table tbody tr:hover {
    background: #fafafa;
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
.summary-card.in {
    background: linear-gradient(135deg, #1e8449 0%, #27ae60 100%);
    color: white;
}
.summary-card.out {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    color: white;
}
.summary-card.net-up {
    background: linear-gradient(135deg, #A04657 0%, #c75c6f 100%);
    color: white;
}
.summary-card.net-down {
    background: linear-gradient(135deg, #2c3e50 0%, #e67e22 100%);
    color: white;
}
.btn-ui {
    height: 46px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-history me-2" style="color: #A04657;"></i> Stock Ledger
        </h2>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-dark rounded-pill px-4 py-2" onclick="printLedger()">
                <i class="fas fa-print me-2"></i> Print
            </button>
            <a href="stock.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                <i class="fas fa-boxes me-2"></i> Back to Stock
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card in">
                <small>Total Stock In</small>
                <h4 class="mb-0"><?php echo number_format($total_in); ?></h4>
                <small><?php echo $selected_product_name ? htmlspecialchars($selected_product_name) : 'All products (units)'; ?></small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card out">
                <small>Total Stock Out</small>
                <h4 class="mb-0"><?php echo number_format($total_out); ?></h4>
                <small><?php echo $selected_product_name ? htmlspecialchars($selected_product_name) : 'All products (units)'; ?></small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card <?php echo $net_change >= 0 ? 'net-up' : 'net-down'; ?>">
                <small>Net Change</small>
                <h4 class="mb-0"><?php echo ($net_change >= 0 ? '+' : '') . number_format($net_change); ?></h4>
                <small><?php echo $net_change >= 0 ? 'Stock increased' : 'Stock decreased'; ?></small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="summary-card net-up">
                <small>Total Entries</small>
                <h4 class="mb-0"><?php echo number_format(count($rows)); ?></h4>
                <small>Ledger entries in this view</small>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-muted mb-1"><i class="fas fa-box me-1"></i> Product</label>
                    <select name="product_id" class="form-select">
                        <option value="0">All Products</option>
                        <?php if ($products && mysqli_num_rows($products) > 0):
                            while ($pr = mysqli_fetch_assoc($products)): ?>
                            <option value="<?php echo $pr['id']; ?>" <?php echo $product_filter == $pr['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pr['product_name']); ?> (<?php echo htmlspecialchars($pr['product_code']); ?>)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted mb-1"><i class="fas fa-calendar-day me-1"></i> From</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted mb-1"><i class="fas fa-calendar-day me-1"></i> To</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill btn-ui">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="stock_ledger.php" class="btn btn-outline-secondary flex-fill btn-ui">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Ledger Table Card -->
    <div class="card payment-card">
        <div class="card-header">
            <i class="fas fa-list me-2"></i> Transaction History
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table ledger-table mb-0" id="stockLedgerTable">
                    <thead>
                        <tr>
                            <th style="width:60px">ID</th>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Source</th>
                            <th class="text-center">IN</th>
                            <th class="text-center">OUT</th>
                            <th class="text-center">Balance</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): ?>
                            <?php foreach ($rows as $sl): ?>
                                <tr>
                                    <td class="text-muted fw-semibold">#<?php echo $sl['id']; ?></td>
                                    <td class="text-nowrap" data-order="<?php echo htmlspecialchars($sl['transaction_date']); ?>"><?php echo date('d-m-Y h:i A', strtotime($sl['transaction_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($sl['product_name']); ?></strong> <span class="badge bg-light text-dark border rounded-pill"><?php echo htmlspecialchars($sl['product_code']); ?></span></td>
                                    <td>
                                        <span class="badge rounded-pill bg-light text-dark border">
                                            <i class="fas fa-tag me-1"></i><?php echo ref_type_label($sl['reference_type'], $sl['quantity_in']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sl['quantity_in'] > 0): ?>
                                            <span class="badge bg-success rounded-pill">+<?php echo number_format($sl['quantity_in']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sl['quantity_out'] > 0): ?>
                                            <span class="badge bg-danger rounded-pill">-<?php echo number_format($sl['quantity_out']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo number_format($sl['balance']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($sl['description']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-history fa-3x mb-3 d-block opacity-25"></i>
                                    No stock transactions found for the selected filters.
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
                <span class="print-doc-title">Stock Ledger</span>
                <span class="print-date-range">
                    <?php echo $selected_product_name ? htmlspecialchars($selected_product_name) . ' | ' : ''; ?>
                    <?php if ($from_date || $to_date): ?>
                        <?php echo ($from_date ? 'From ' . date('d-m-Y', strtotime($from_date)) : 'From Start'); ?> to <?php echo ($to_date ? date('d-m-Y', strtotime($to_date)) : 'Today'); ?>
                    <?php else: ?>
                        All dates
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="print-summary">
            Total In: <strong><?php echo number_format($total_in); ?></strong> &nbsp;|&nbsp;
            Total Out: <strong><?php echo number_format($total_out); ?></strong> &nbsp;|&nbsp;
            Net Change: <strong><?php echo ($net_change >= 0 ? '+' : '') . number_format($net_change); ?></strong>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:24px;">#</th>
                    <th style="width:45px;">ID</th>
                    <th style="width:110px;">Date & Time</th>
                    <th>Product</th>
                    <th style="width:80px;">Source</th>
                    <th style="width:45px;" class="text-end">IN</th>
                    <th style="width:45px;" class="text-end">OUT</th>
                    <th style="width:55px;" class="text-end">Balance</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) > 0): $sno = 1; foreach ($rows as $sl): ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><?php echo $sl['id']; ?></td>
                        <td><?php echo date('d-m-Y h:i A', strtotime($sl['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($sl['product_name']); ?> (<?php echo htmlspecialchars($sl['product_code']); ?>)</td>
                        <td><?php echo ref_type_label($sl['reference_type'], $sl['quantity_in']); ?></td>
                        <td class="text-end"><?php echo $sl['quantity_in'] > 0 ? number_format($sl['quantity_in']) : '-'; ?></td>
                        <td class="text-end"><?php echo $sl['quantity_out'] > 0 ? number_format($sl['quantity_out']) : '-'; ?></td>
                        <td class="text-end"><?php echo number_format($sl['balance']); ?></td>
                        <td><small><?php echo htmlspecialchars($sl['description']); ?></small></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center" style="padding:40px;color:#999;">No stock transactions found.</td></tr>
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
.print-summary { font-size: 12px; color: #333; background: #fdf2f4; padding: 8px 14px; border-radius: 8px; margin-bottom: 10px; }
.print-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.print-table th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 12px; text-align: left; white-space: nowrap; }
.print-table th.text-end, .print-table td.text-end { text-align: right; }
.print-table td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
.print-table tbody tr:nth-child(even) { background: #f9f9f9; }
.print-table tbody tr:last-child td { border-bottom: 2px solid #A04657; }
.print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
</style>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    <?php if (count($rows) > 0): ?>
    $('#stockLedgerTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ transactions"
        }
    });
    <?php endif; ?>
});

function printLedger() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Stock Ledger</title>
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
                .print-company { font-size: 18px; font-weight: 700; color: #A04657; }
                .print-owner-name { font-size: 22px; font-weight: 800; color: #222; }
                .print-address { font-size: 13px; color: #666; }
                .print-phone { font-size: 14px; font-weight: 600; color: #A04657; }
                .print-divider { height: 2px; background: linear-gradient(to right, #A04657, #e0a0ab); margin: 14px 0 10px; border-radius: 2px; }
                .print-title-row { display: flex; justify-content: space-between; align-items: center; }
                .print-doc-title { font-size: 15px; font-weight: 700; color: #444; }
                .print-date-range { font-size: 12px; color: #888; background: #f5f5f5; padding: 5px 14px; border-radius: 20px; }
                .print-summary { font-size: 12px; color: #333; background: #fdf2f4; padding: 8px 14px; border-radius: 8px; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; }
                th { background: #A04657; color: #fff; padding: 10px 12px; font-weight: 600; font-size: 11px; text-align: left; white-space: nowrap; }
                th.text-end, td.text-end { text-align: right; }
                td { padding: 9px 12px; border-bottom: 1px solid #e6e6e6; color: #333; }
                tr:nth-child(even) td { background: #f9f9f9; }
                tr:last-child td { border-bottom: 2px solid #A04657; }
                .print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Stock Ledger</strong>
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
