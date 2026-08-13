<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$voucher_search = isset($_GET['voucher_no']) ? trim(mysqli_real_escape_string($conn, $_GET['voucher_no'])) : '';

$results = [];
$search_done = false;

if ($voucher_search !== '') {
    $search_done = true;

    // Sales (water deliveries)
    $res = mysqli_query($conn, "SELECT d.voucher_no, d.delivery_datetime, d.total_amount, c.customer_name AS party_name
                                FROM water_deliveries d
                                LEFT JOIN customers c ON d.customer_id = c.id
                                WHERE d.voucher_no LIKE '%$voucher_search%'
                                ORDER BY d.delivery_datetime DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $results[] = [
                'type'   => 'Sale',
                'badge'  => 'bg-primary',
                'icon'   => 'fas fa-truck',
                'voucher'=> $r['voucher_no'],
                'date'   => $r['delivery_datetime'],
                'party'  => $r['party_name'] ?? 'Walk-in',
                'amount' => $r['total_amount'],
                'link'   => 'delivery_view.php?search=' . urlencode($r['voucher_no']),
            ];
        }
    }

    // Purchases (raw material purchases)
    $res = mysqli_query($conn, "SELECT p.id, p.voucher_no, p.purchase_date, p.total_amount, s.supplier_name AS party_name
                                FROM raw_material_purchases p
                                LEFT JOIN suppliers s ON p.supplier_id = s.id
                                WHERE p.voucher_no LIKE '%$voucher_search%'
                                ORDER BY p.purchase_date DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $results[] = [
                'type'   => 'Purchase',
                'badge'  => 'bg-success',
                'icon'   => 'fas fa-shopping-cart',
                'voucher'=> $r['voucher_no'],
                'date'   => $r['purchase_date'],
                'party'  => $r['party_name'] ?? '—',
                'amount' => $r['total_amount'],
                'link'   => 'purchase_details.php?id=' . intval($r['id'] ?? 0),
            ];
        }
    }

    // Receivings (customer payments)
    $res = mysqli_query($conn, "SELECT cp.voucher_no, cp.payment_datetime, cp.payment_amount, c.customer_name AS party_name, cp.customer_id
                                FROM customer_payments cp
                                LEFT JOIN customers c ON cp.customer_id = c.id
                                WHERE cp.voucher_no LIKE '%$voucher_search%'
                                ORDER BY cp.payment_datetime DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $results[] = [
                'type'   => 'Receiving',
                'badge'  => 'bg-info',
                'icon'   => 'fas fa-money-bill-wave',
                'voucher'=> $r['voucher_no'],
                'date'   => $r['payment_datetime'],
                'party'  => $r['party_name'] ?? '—',
                'amount' => $r['payment_amount'],
                'link'   => 'payments.php',
            ];
        }
    }

    // Payments (supplier payments)
    $res = mysqli_query($conn, "SELECT sp.voucher_no, sp.payment_datetime, sp.payment_amount, s.supplier_name AS party_name, sp.supplier_id
                                FROM supplier_payments sp
                                LEFT JOIN suppliers s ON sp.supplier_id = s.id
                                WHERE sp.voucher_no LIKE '%$voucher_search%'
                                ORDER BY sp.payment_datetime DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $results[] = [
                'type'   => 'Payment',
                'badge'  => 'bg-warning',
                'icon'   => 'fas fa-money-bill',
                'voucher'=> $r['voucher_no'],
                'date'   => $r['payment_datetime'],
                'party'  => $r['party_name'] ?? '—',
                'amount' => $r['payment_amount'],
                'link'   => 'supplier_payment.php?supplier_id=' . intval($r['supplier_id']),
            ];
        }
    }

    usort($results, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.voucher-search-card {
    background: linear-gradient(135deg, #A04657 0%, #c96b7e 100%);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 25px;
}
.voucher-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.voucher-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.voucher-table tbody tr:hover { background: #f8f9fa; }
.empty-state { text-align: center; padding: 60px 20px; color: #999; }
.empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-search me-2" style="color: #A04657;"></i> Voucher Search
        </h2>
    </div>

    <!-- Search Card -->
    <div class="voucher-search-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label text-white mb-1">
                    <i class="fas fa-tag me-1"></i> Enter Voucher Number
                </label>
                <input type="text" name="voucher_no" class="form-control form-control-lg" style="border-radius: 30px;"
                       placeholder="e.g. SLS-00001, PUR-00001, RCP-00001, PAY-00001"
                       value="<?php echo htmlspecialchars($voucher_search); ?>" autofocus>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-light btn-lg w-100" style="border-radius: 30px;">
                    <i class="fas fa-search me-2"></i> Search
                </button>
            </div>
        </form>
        <small class="text-white-50 d-block mt-3"><i class="fas fa-info-circle me-1"></i> Search by full or partial voucher number. Prefixes: SLS = Sale, PUR = Purchase, RCP = Receiving (customer), PAY = Payment (supplier).</small>
    </div>

    <!-- Results -->
    <?php if($search_done): ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="mb-0"><i class="fas fa-list me-2" style="color: #A04657;"></i> Search Results (<?php echo count($results); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table voucher-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>Type</th>
                                <th style="min-width:120px">Voucher No</th>
                                <th style="min-width:120px">Date & Time</th>
                                <th style="min-width:160px">Party</th>
                                <th style="min-width:110px" class="text-end">Amount (Rs)</th>
                                <th style="width:120px" class="text-center">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($results) > 0): ?>
                                <?php $sno = 1; foreach($results as $r): ?>
                                    <tr>
                                        <td><?php echo $sno++; ?></td>
                                        <td><span class="badge <?php echo $r['badge']; ?> rounded-pill"><i class="<?php echo $r['icon']; ?> me-1"></i> <?php echo $r['type']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($r['voucher']); ?></strong></td>
                                        <td><?php echo date('d-m-Y h:i A', strtotime($r['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($r['party']); ?></td>
                                        <td class="text-end fw-bold">Rs <?php echo number_format($r['amount'], 2); ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo $r['link']; ?>" class="btn btn-xs btn-outline-primary" style="padding: 6px 10px; font-size: 12px; line-height: 1.3; border-radius: 6px;" title="Open">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-search"></i>
                                        <p class="mb-0">No vouchers found matching <strong><?php echo htmlspecialchars($voucher_search); ?></strong></p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center py-5">
                <i class="fas fa-tags fa-4x mb-3 text-muted opacity-25"></i>
                <h4 class="text-muted">Search a Voucher Number</h4>
                <p class="text-muted">Enter a voucher number above to find any sale, purchase, receiving or payment.</p>
            </div>
        </div>
    <?php endif; ?>

</div>
</div>

<?php include '../includes/footer.php'; ?>
