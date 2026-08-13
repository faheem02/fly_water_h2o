<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$ledger = [];
$supplier = null;
$current_balance = 0;
$total_debit = 0;
$total_credit = 0;

if ($supplier_id) {
    $sres = mysqli_query($conn, "SELECT * FROM suppliers WHERE id=$supplier_id");
    $supplier = mysqli_fetch_assoc($sres);
    if($supplier) {
        $current_balance = $supplier['current_balance'];

        $date_condition = "";
        if($from_date && $to_date) {
            $date_condition = "AND DATE(wl.transaction_date) BETWEEN '$from_date' AND '$to_date'";
        } elseif($from_date) {
            $date_condition = "AND DATE(wl.transaction_date) >= '$from_date'";
        } elseif($to_date) {
            $date_condition = "AND DATE(wl.transaction_date) <= '$to_date'";
        }

        $ledger_query = "SELECT wl.* FROM supplier_ledger wl WHERE wl.supplier_id=$supplier_id $date_condition ORDER BY wl.transaction_date ASC, wl.id ASC";
        $ledger = mysqli_query($conn, $ledger_query);
    }
}

$suppliers = mysqli_query($conn, "SELECT id, supplier_code, supplier_name, current_balance FROM suppliers WHERE status='Active' ORDER BY supplier_name");

// Calculate summary stats for selected supplier
if($supplier_id && $ledger) {
    $summary_query = mysqli_query($conn, "SELECT COALESCE(SUM(debit_amount),0) as total_debit, COALESCE(SUM(credit_amount),0) as total_credit FROM supplier_ledger WHERE supplier_id=$supplier_id");
    $summary = mysqli_fetch_assoc($summary_query);
    $total_debit = $summary['total_debit'];
    $total_credit = $summary['total_credit'];
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
.ledger-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}
.ledger-card .card-header {
    background: #A04657;
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}
.customer-select-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
.ledger-table th {
    background: #A04657;
    color: white;
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
    white-space: nowrap;
}
.ledger-table td {
    padding: 10px;
    vertical-align: middle;
    font-size: 13px;
}
.ledger-table tr:hover {
    background-color: #f8f9fa;
}
.debit-amount {
    color: #dc3545;
    font-weight: 600;
}
.credit-amount {
    color: #28a745;
    font-weight: 600;
}
.balance-credit {
    color: #b45309;
    font-weight: 700;
}
.balance-advance {
    color: #16a34a;
    font-weight: 700;
}
.summary-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px 15px;
    text-align: center;
    border-left: 4px solid #A04657;
}
.summary-box h6 {
    font-size: 12px;
    color: #888;
    margin-bottom: 5px;
}
.summary-box h4 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
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
@media (max-width: 768px) {
    .ledger-table {
        font-size: 11px;
    }
    .ledger-table th,
    .ledger-table td {
        padding: 6px;
    }
    .summary-box h4 {
        font-size: 16px;
    }
}
</style>

<div class="main-wrapper">
<div class="container-fluid p-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h2 class="page-heading mb-2 mb-sm-0">
            <i class="fas fa-book me-2" style="color: #A04657;"></i> Supplier Ledger
        </h2>
        <div class="d-flex flex-wrap gap-2">
            <a href="suppliers.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Suppliers
            </a>
            <?php if($supplier): ?>
                <button onclick="printLedger()" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Supplier Selection Card -->
    <div class="customer-select-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label text-white mb-1">
                            <i class="fas fa-user me-1"></i> Select Supplier
                        </label>
                        <select name="supplier_id" class="form-select rounded-pill" style="border-radius: 30px;" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php while($s = mysqli_fetch_assoc($suppliers)): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($supplier_id == $s['id']) ? 'selected' : ''; ?> data-balance="<?php echo $s['current_balance']; ?>">
                                    <?php echo htmlspecialchars($s['supplier_name']); ?> - <?php echo htmlspecialchars($s['supplier_code']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-light w-100 rounded-pill">
                            <i class="fas fa-eye me-2"></i> View Ledger
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="fas fa-chart-line fa-3x text-white opacity-50"></i>
            </div>
        </div>
    </div>

    <?php if($supplier): ?>
        <!-- Supplier Info & Summary -->
        <div class="customer-info-card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-circle fa-3x" style="color: #A04657;"></i>
                        </div>
                        <div>
                            <h3 class="mb-1"><?php echo htmlspecialchars($supplier['supplier_name']); ?> <small class="text-muted fs-6">(ID: <?php echo htmlspecialchars($supplier['supplier_code']); ?>)</small></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-phone me-1"></i> <?php echo $supplier['mobile'] ?: 'No phone'; ?>
                                <?php if($supplier['contact_person']): ?>
                                    <span class="mx-2">|</span> <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($supplier['contact_person']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="summary-box">
                                <h6>Total Debit (Payments)</h6>
                                <h4 class="debit-amount">Rs <?php echo number_format($total_debit, 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="summary-box">
                                <h6>Total Credit (Purchases)</h6>
                                <h4 class="credit-amount">Rs <?php echo number_format($total_credit, 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="summary-box">
                                <h6>Current Balance</h6>
                                <h4 class="<?php echo $current_balance >= 0 ? 'balance-credit' : 'balance-advance'; ?>">
                                    Rs <?php echo number_format(abs($current_balance), 2); ?>
                                    <small>(<?php echo $current_balance >= 0 ? 'Credit' : 'Advance'; ?>)</small>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="filter-box">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>">
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
                    <a href="?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ledger Table -->
        <div class="card ledger-card">
            <div class="card-header">
                <i class="fas fa-list-alt me-2"></i> Transaction History
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table ledger-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 140px">Date</th>
                                <th>Description</th>
                                <th style="width: 150px" class="text-end">Debit (Payment)</th>
                                <th style="width: 150px" class="text-end">Credit (Purchase)</th>
                                <th style="width: 180px" class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($ledger && mysqli_num_rows($ledger) > 0): ?>
                                <?php 
                                $balance = 0;
                                while($row = mysqli_fetch_assoc($ledger)): 
                                    $balance = $row['running_balance'];
                                ?>
                                    <tr>
                                        <td>
                                            <i class="far fa-calendar-alt me-1 text-muted"></i>
                                            <?php echo date('d-m-Y h:i A', strtotime($row['transaction_date'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $icon = '';
                                            if(strpos($row['description'], 'Payment') !== false) {
                                                $icon = '<i class="fas fa-money-bill-wave text-success me-1"></i>';
                                            } elseif(strpos($row['description'], 'Purchase') !== false) {
                                                $icon = '<i class="fas fa-shopping-cart text-primary me-1"></i>';
                                            } elseif(strpos($row['description'], 'Opening') !== false) {
                                                $icon = '<i class="fas fa-chart-line text-info me-1"></i>';
                                            } else {
                                                $icon = '<i class="fas fa-exchange-alt text-secondary me-1"></i>';
                                            }
                                            echo $icon . ' ' . htmlspecialchars($row['description']); 
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if($row['debit_amount'] > 0): ?>
                                                <span class="debit-amount">Rs <?php echo number_format($row['debit_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if($row['credit_amount'] > 0): ?>
                                                <span class="credit-amount">Rs <?php echo number_format($row['credit_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if($balance >= 0): ?>
                                                <span class="balance-credit">Rs <?php echo number_format($balance, 2); ?> <small>(Credit)</small></span>
                                            <?php else: ?>
                                                <span class="balance-advance">Rs <?php echo number_format(abs($balance), 2); ?> <small>(Advance)</small></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <!-- Closing Balance Row -->
                                <tr style="background: #f8f9fa; font-weight: 700;">
                                    <td colspan="4" class="text-end"><strong>Closing Balance</strong></td>
                                    <td class="text-end">
                                        <?php if($current_balance >= 0): ?>
                                            <span class="balance-credit">Rs <?php echo number_format($current_balance, 2); ?> <small>(Credit)</small></span>
                                        <?php else: ?>
                                            <span class="balance-advance">Rs <?php echo number_format(abs($current_balance), 2); ?> <small>(Advance)</small></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fas fa-book-open"></i>
                                        <p class="mb-0">No transactions found for this supplier.</p>
                                        <small class="text-muted">Add purchases or payments to see ledger entries.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Print Overlay -->
        <div id="print-overlay">
            <div id="print-area">
                <div class="print-header">
                    <!-- OWNER SECTION -->
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

                    <!-- SUPPLIER SECTION -->
                    <div class="print-customer-section">
                        <div class="print-customer-row">
                            <span class="print-label">Supplier Name:</span>
                            <span class="print-value"><?php echo htmlspecialchars($supplier['supplier_name']); ?> (<?php echo htmlspecialchars($supplier['supplier_code']); ?>)</span>
                        </div>
                        <div class="print-customer-row">
                            <span class="print-label">Phone:</span>
                            <span class="print-value"><?php echo htmlspecialchars($supplier['mobile']); ?></span>
                        </div>
                        <?php if($supplier['address']): ?>
                        <div class="print-customer-row">
                            <span class="print-label">Address:</span>
                            <span class="print-value"><?php echo htmlspecialchars($supplier['address']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="print-customer-row">
                            <span class="print-label">Current Balance:</span>
                            <span class="print-value">
                                Rs <?php echo number_format(abs($current_balance), 2); ?>
                                (<?php echo $current_balance >= 0 ? 'Credit' : 'Advance'; ?>)
                            </span>
                        </div>
                    </div>

                    <div class="print-thin-divider"></div>

                    <div class="print-title-row">
                        <span class="print-doc-title">Supplier Ledger Statement</span>
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
                            <th style="width:100px;">Date</th>
                            <th>Description</th>
                            <th style="width:110px;" class="text-end">Debit (Rs)</th>
                            <th style="width:110px;" class="text-end">Credit (Rs)</th>
                            <th style="width:120px;" class="text-end">Balance (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ledger_print = mysqli_query($conn, $ledger_query);
                        $balance = 0;
                        $sno = 1;
                        $print_total_debit = 0;
                        $print_total_credit = 0;
                        if($ledger_print && mysqli_num_rows($ledger_print) > 0):
                            while($row = mysqli_fetch_assoc($ledger_print)): 
                                $balance = $row['running_balance'];
                                $print_total_debit += $row['debit_amount'];
                                $print_total_credit += $row['credit_amount'];
                        ?>
                            <tr>
                                <td><?php echo $sno++; ?></td>
                                <td><?php echo date('d-m-Y', strtotime($row['transaction_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td class="text-end"><?php echo $row['debit_amount'] > 0 ? number_format($row['debit_amount'], 2) : '-'; ?></td>
                                <td class="text-end"><?php echo $row['credit_amount'] > 0 ? number_format($row['credit_amount'], 2) : '-'; ?></td>
                                <td class="text-end"><?php echo number_format($balance, 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding:40px;color:#999;">No transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td class="text-end"><strong><?php echo number_format($print_total_debit, 2); ?></strong></td>
                            <td class="text-end"><strong><?php echo number_format($print_total_credit, 2); ?></strong></td>
                            <td class="text-end"><strong><?php echo number_format($current_balance, 2); ?></strong></td>
                        </tr>
                    </tfoot>
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
        .print-thin-divider {
            height: 1px;
            background: #ddd;
            margin: 10px 0;
        }
        .print-customer-section {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 30px;
            padding: 8px 0;
        }
        .print-customer-row {
            display: flex;
            gap: 6px;
            font-size: 13px;
        }
        .print-label {
            font-weight: 600;
            color: #666;
            min-width: 100px;
        }
        .print-value {
            color: #222;
            font-weight: 500;
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
        .print-table tfoot tr {
            background: #f0f0f0;
        }
        .print-table tfoot td {
            padding: 10px 12px;
            border-top: 2px solid #A04657;
            color: #222;
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
        function printLedger() {
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
                            <title>Ledger - <?php echo addslashes($supplier['supplier_name']); ?></title>
                            <style>
                                @page { margin: 0; size: A4; }
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

    <?php elseif($supplier_id && !$supplier): ?>
        <div class="alert alert-warning rounded-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Supplier not found. Please select a valid supplier.
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="card ledger-card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book fa-4x mb-3 text-muted opacity-25"></i>
                <h4 class="text-muted">Select a Supplier to View Ledger</h4>
                <p class="text-muted">Choose a supplier from the dropdown above to see their complete transaction history.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Legend -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <div><i class="fas fa-shopping-cart text-primary me-1"></i> <small>Purchase (Credit)</small></div>
                <div><i class="fas fa-money-bill-wave text-success me-1"></i> <small>Payment Made (Debit)</small></div>
                <div><i class="fas fa-chart-line text-info me-1"></i> <small>Opening Balance</small></div>
            </div>
        </div>
    </div>

</div>
</div>

<script>
// Auto-submit when supplier is selected
document.addEventListener('DOMContentLoaded', function() {
    const supplierSelect = document.querySelector('select[name="supplier_id"]');
    if(supplierSelect) {
        supplierSelect.addEventListener('change', function() {
            if(this.value) {
                this.closest('form').submit();
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
