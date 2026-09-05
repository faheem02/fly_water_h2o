<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) header("Location: ../login.php");

$salesman_where = is_salesman() ? ' AND ' . salesman_match_condition($conn) : '';

$salesman = isset($_GET['salesman']) ? mysqli_real_escape_string($conn, $_GET['salesman']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$where = " WHERE 1=1";
if ($search) $where .= " AND (c.customer_name LIKE '%$search%' OR c.customer_code LIKE '%$search%' OR d.voucher_no LIKE '%$search%')";
if ($salesman) $where .= " AND c.salesman LIKE '%$salesman%'";
if ($from_date) $where .= " AND DATE(d.delivery_datetime) >= '$from_date'";
if ($to_date) $where .= " AND DATE(d.delivery_datetime) <= '$to_date'";
$where .= $salesman_where;

$message = '';

function delivery_cash_received($conn, $delivery_id) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(credit_amount),0) as cash FROM customer_ledger WHERE reference_id=$delivery_id AND reference_type='payment'"));
    return floatval($row['cash']);
}

function recompute_customer_ledger($conn, $customer_id) {
    $rows = mysqli_query($conn, "SELECT id, debit_amount, credit_amount FROM customer_ledger WHERE customer_id=$customer_id ORDER BY transaction_date ASC, id ASC");
    $running = 0;
    while ($r = mysqli_fetch_assoc($rows)) {
        $running += floatval($r['debit_amount']) - floatval($r['credit_amount']);
        mysqli_query($conn, "UPDATE customer_ledger SET running_balance=" . number_format($running, 2, '.', '') . " WHERE id=" . intval($r['id']));
    }
    $bal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(debit_amount),0) - COALESCE(SUM(credit_amount),0) as bal FROM customer_ledger WHERE customer_id=$customer_id"));
    mysqli_query($conn, "UPDATE customers SET outstanding_balance=" . floatval($bal['bal']) . " WHERE id=$customer_id");
}

function reverse_delivery($conn, $delivery) {
    $did = intval($delivery['id']);
    $customer_id = intval($delivery['customer_id']);
    $product_id = intval($delivery['product_id']);
    $bottles = intval($delivery['bottles_delivered']);
    $empties = intval($delivery['empty_bottles_returned']);
    $total = floatval($delivery['total_amount']);
    $cash = delivery_cash_received($conn, $did);

    if ($product_id) {
        $prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT track_empty_bottles FROM products WHERE id=$product_id"));
        $track_empties = ($prod && !empty($prod['track_empty_bottles']));
        $net_deducted = $bottles - ($track_empties ? $empties : 0);
        mysqli_query($conn, "UPDATE products SET current_stock = current_stock + $net_deducted WHERE id=$product_id");
        if ($track_empties) {
            mysqli_query($conn, "UPDATE customers SET empty_bottles_balance = empty_bottles_balance - $bottles + $empties WHERE id=$customer_id");
        }
    }

    mysqli_query($conn, "DELETE FROM customer_ledger WHERE reference_id=$did AND reference_type IN ('delivery','payment')");
    mysqli_query($conn, "DELETE FROM bottle_tracking WHERE reference_id=$did");
    mysqli_query($conn, "DELETE FROM stock_ledger WHERE reference_id=$did AND reference_type IN ('delivery','empty_return')");
    mysqli_query($conn, "DELETE FROM water_deliveries WHERE id=$did");

    recompute_customer_ledger($conn, $customer_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_delivery'])) {
        $id = intval($_POST['delivery_id']);
        $delivery = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM water_deliveries WHERE id=$id"));
        if ($delivery) {
            $ok = true;
            if (is_salesman()) {
                $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT salesman FROM customers WHERE id=" . intval($delivery['customer_id'])));
                if (!$c || !salesman_owns_customer($conn, $c['salesman'])) $ok = false;
            }
            if ($ok) {
                reverse_delivery($conn, $delivery);
                $message = "<div class='alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-check-circle me-2'></i> Delivery #$id deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-exclamation-circle me-2'></i> You can only delete deliveries for your own customers.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        }
    }
    elseif (isset($_POST['edit_delivery'])) {
        $id = intval($_POST['delivery_id']);
        $delivery = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM water_deliveries WHERE id=$id"));
        if ($delivery) {
            $ok = true;
            if (is_salesman()) {
                $c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT salesman FROM customers WHERE id=" . intval($delivery['customer_id'])));
                if (!$c || !salesman_owns_customer($conn, $c['salesman'])) $ok = false;
            }
            if (!$ok) {
                $message = "<div class='alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-exclamation-circle me-2'></i> You can only edit deliveries for your own customers.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $product_id = intval($_POST['product_id']);
                $bottles = intval($_POST['bottles_delivered']);
                $empties = intval($_POST['empty_bottles_returned']);
                $rate = floatval($_POST['bottle_rate']);
                $cash_received = floatval($_POST['cash_received']);
                $notes = mysqli_real_escape_string($conn, $_POST['notes']);
                $customer_id = intval($delivery['customer_id']);
                $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id"));

                if (!$product) {
                    $message = "<div class='alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-exclamation-circle me-2'></i> Product not found!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                } elseif ($bottles <= 0 || $rate <= 0) {
                    $message = "<div class='alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-exclamation-circle me-2'></i> Bottles and rate must be greater than zero.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                } else {
                    $track_empties = !empty($product['track_empty_bottles']);
                    if (!$track_empties) $empties = 0;
                    $total = $bottles * $rate;
                    $old_prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT track_empty_bottles FROM products WHERE id=" . intval($delivery['product_id'])));
                    $old_track_empties = ($old_prod && !empty($old_prod['track_empty_bottles']));
                    $old_net_deducted = ($delivery['product_id'] == $product_id) ? (intval($delivery['bottles_delivered']) - ($old_track_empties ? intval($delivery['empty_bottles_returned']) : 0)) : 0;
                    $new_net_deducted = $bottles - ($track_empties ? $empties : 0);
                    $stock_after = intval($product['current_stock']) + $old_net_deducted - $new_net_deducted;

                    if ($stock_after < 0) {
                        $message = "<div class='alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-exclamation-circle me-2'></i> Insufficient stock! Only " . (intval($product['current_stock']) + $old_net_deducted) . " bottles available.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                    } else {
                        $datetime = $delivery['delivery_datetime'];
                        $entry_salesman = mysqli_real_escape_string($conn, $delivery['salesman'] ?? '');
                        $voucher_no = !empty($delivery['voucher_no']) ? mysqli_real_escape_string($conn, $delivery['voucher_no']) : generate_voucher_no($conn, 'water_deliveries', 'voucher_no', 'SLS-');

                        reverse_delivery($conn, $delivery);

                        mysqli_query($conn, "INSERT INTO water_deliveries (id, voucher_no, customer_id, product_id, bottles_delivered, empty_bottles_returned, bottle_rate, total_amount, salesman, notes, delivery_datetime, created_datetime) 
                                             VALUES ($id, '$voucher_no', $customer_id, $product_id, $bottles, $empties, $rate, $total, '$entry_salesman', '$notes', '$datetime', '" . $delivery['created_datetime'] . "')");

                        if ($track_empties) {
                            mysqli_query($conn, "UPDATE customers SET empty_bottles_balance = empty_bottles_balance + $bottles - $empties WHERE id=$customer_id");
                        }

                        mysqli_query($conn, "UPDATE products SET current_stock = $stock_after WHERE id=$product_id");

                        $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_name FROM customers WHERE id=$customer_id"));
                        $running = mysqli_fetch_assoc(mysqli_query($conn, "SELECT running_balance FROM customer_ledger WHERE customer_id=$customer_id ORDER BY id DESC LIMIT 1"))['running_balance'] ?? 0;
                        $new_balance = $running + $total;
                        mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                             VALUES ($customer_id, '$datetime', '" . mysqli_real_escape_string($conn, $product['product_name']) . "', $total, 0, $new_balance, $id, 'delivery')");
                        if ($cash_received > 0) {
                            $new_balance2 = $new_balance - $cash_received;
                            mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                                                 VALUES ($customer_id, '$datetime', 'Cash Received - Rs " . number_format($cash_received, 2) . "', 0, $cash_received, $new_balance2, $id, 'payment')");
                        }
                        if ($track_empties) {
                            $empties_bal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT empty_bottles_balance FROM customers WHERE id=$customer_id"))['empty_bottles_balance'];
                            mysqli_query($conn, "INSERT INTO bottle_tracking (customer_id, tracking_date, bottles_delivered, bottles_returned, bottles_broken, pending_empties, notes, reference_id) 
                                                 VALUES ($customer_id, '$datetime', $bottles, $empties, 0, $empties_bal, '$notes', $id)");
                        }

                        // 1. Record stock OUT for the delivered bottles
                        $stock_after_out = intval($product['current_stock']) + $old_net_deducted - $bottles;
                        $desc_out = "Water delivery: $bottles bottles of {$product['product_name']} delivered to customer: " . mysqli_real_escape_string($conn, $cust['customer_name']) . " (Voucher: $voucher_no)";
                        mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_out, running_stock, description, created_datetime) 
                                             VALUES ($product_id, '$datetime', 'OUT', 'delivery', $id, $bottles, $stock_after_out, '$desc_out', '$datetime')");

                        // 2. If empty bottles returned, record stock IN
                        if ($track_empties && $empties > 0) {
                            $desc_in = "Empty bottles returned: $empties bottles from customer: " . mysqli_real_escape_string($conn, $cust['customer_name']) . " (Voucher: $voucher_no)";
                            mysqli_query($conn, "INSERT INTO stock_ledger (product_id, transaction_date, transaction_type, reference_type, reference_id, quantity_in, running_stock, description, created_datetime) 
                                                 VALUES ($product_id, '$datetime', 'IN', 'empty_return', $id, $empties, $stock_after, '$desc_in', '$datetime')");
                        }

                        recompute_customer_ledger($conn, $customer_id);

                        $message = "<div class='alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm' role='alert'><i class='fas fa-check-circle me-2'></i> Delivery #$id updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                    }
                }
            }
        }
    }
}

$query = "SELECT d.*, p.product_name, p.track_empty_bottles, c.customer_name, c.customer_code, c.mobile, c.salesman as cust_salesman,
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

// Products list for the edit modal
$products_edit = mysqli_query($conn, "SELECT id, product_name, purchase_price, sale_price, track_empty_bottles FROM products ORDER BY product_name");

// Preserve current GET filters on POST forms
$form_action = 'delivery_view.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '');
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
.delivery-table .btn-xs {
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.3;
    border-radius: 6px;
}
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

    <?php if($message): ?>
        <?php echo $message; ?>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="fas fa-search me-1"></i> Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Voucher #, customer ID or name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
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
                            <th style="width:60px">ID</th>
                            <th style="min-width:110px">Voucher No</th>
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
                            <th style="width:100px; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deliveries && mysqli_num_rows($deliveries) > 0): ?>
                            <?php while($d = mysqli_fetch_assoc($deliveries)): 
                                $display_salesman = $d['cust_salesman'] ?: '-';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo $d['customer_code'] ?? $d['id']; ?></td>
                                <td><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill"><?php echo htmlspecialchars($d['voucher_no'] ?? '—'); ?></span></td>
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
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-xs btn-outline-info viewDeliveryBtn" title="View"
                                            data-id="<?php echo $d['id']; ?>"
                                            data-voucher="<?php echo htmlspecialchars($d['voucher_no'] ?? ''); ?>"
                                            data-code="<?php echo htmlspecialchars($d['customer_code'] ?? ''); ?>"
                                            data-customer="<?php echo htmlspecialchars($d['customer_name'] ?? 'Walk-in'); ?>"
                                            data-mobile="<?php echo htmlspecialchars($d['mobile'] ?? ''); ?>"
                                            data-salesman="<?php echo htmlspecialchars($d['cust_salesman'] ?? ''); ?>"
                                            data-entry="<?php echo htmlspecialchars($d['salesman'] ?? ''); ?>"
                                            data-product="<?php echo htmlspecialchars($d['product_name'] ?? ''); ?>"
                                            data-bottles="<?php echo $d['bottles_delivered']; ?>"
                                            data-empties="<?php echo $d['empty_bottles_returned']; ?>"
                                            data-rate="<?php echo number_format($d['bottle_rate'], 2); ?>"
                                            data-total="<?php echo number_format($d['total_amount'], 2); ?>"
                                            data-cash="<?php echo number_format($d['cash_received'], 2); ?>"
                                            data-date="<?php echo date('d/m/y h:i A', strtotime($d['delivery_datetime'])); ?>"
                                            data-notes="<?php echo htmlspecialchars($d['notes'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-dark printDeliveryBtn" title="Print"
                                            data-id="<?php echo $d['id']; ?>"
                                            data-voucher="<?php echo htmlspecialchars($d['voucher_no'] ?? ''); ?>"
                                            data-code="<?php echo htmlspecialchars($d['customer_code'] ?? ''); ?>"
                                            data-customer="<?php echo htmlspecialchars($d['customer_name'] ?? 'Walk-in'); ?>"
                                            data-mobile="<?php echo htmlspecialchars($d['mobile'] ?? ''); ?>"
                                            data-salesman="<?php echo htmlspecialchars($d['cust_salesman'] ?? ''); ?>"
                                            data-entry="<?php echo htmlspecialchars($d['salesman'] ?? ''); ?>"
                                            data-product="<?php echo htmlspecialchars($d['product_name'] ?? ''); ?>"
                                            data-bottles="<?php echo $d['bottles_delivered']; ?>"
                                            data-empties="<?php echo $d['empty_bottles_returned']; ?>"
                                            data-rate="<?php echo number_format($d['bottle_rate'], 2); ?>"
                                            data-total="<?php echo number_format($d['total_amount'], 2); ?>"
                                            data-cash="<?php echo number_format($d['cash_received'], 2); ?>"
                                            data-date="<?php echo date('d/m/y h:i A', strtotime($d['delivery_datetime'])); ?>"
                                            data-notes="<?php echo htmlspecialchars($d['notes'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-warning editDeliveryBtn"
                                            data-id="<?php echo $d['id']; ?>"
                                            data-product="<?php echo $d['product_id']; ?>"
                                            data-bottles="<?php echo $d['bottles_delivered']; ?>"
                                            data-empties="<?php echo $d['empty_bottles_returned']; ?>"
                                            data-rate="<?php echo $d['bottle_rate']; ?>"
                                            data-cash="<?php echo $d['cash_received']; ?>"
                                            data-notes="<?php echo htmlspecialchars($d['notes'] ?? '', ENT_QUOTES); ?>"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="<?php echo $form_action; ?>" style="display:inline" onsubmit="return confirm('Delete this delivery? Customer balance, empty bottles and product stock will be reverted.')">
                                            <input type="hidden" name="delivery_id" value="<?php echo $d['id']; ?>">
                                            <button type="submit" name="delete_delivery" class="btn btn-xs btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="15" class="text-center py-5 text-muted">
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
                    <th style="width:55px;">ID</th>
                    <th style="width:100px;">Voucher</th>
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
                        <td><?php echo $d['customer_code'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($d['voucher_no'] ?? '-'); ?></td>
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
                        <td colspan="6" class="text-end">Total</td>
                        <td class="text-end"><?php echo $print_bottles; ?></td>
                        <td class="text-end">Rs <?php echo number_format($print_amount, 2); ?></td>
                        <td class="text-end">Rs <?php echo number_format($print_cash, 2); ?></td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center" style="padding:40px;color:#999;">No deliveries found.</td></tr>
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
const ownerName = <?php echo json_encode($owner_name); ?>;
const companyName = <?php echo json_encode($company_name); ?>;
const ownerAddress = <?php echo json_encode($owner_address); ?>;
const ownerPhone = <?php echo json_encode($owner_phone); ?>;

function printDelivery(id) {
    const btn = document.querySelector('.printDeliveryBtn[data-id="' + id + '"]');
    if (!btn) return;
    const d = {
        voucher: btn.getAttribute('data-voucher') || '—',
        code: btn.getAttribute('data-code') || '-',
        customer: btn.getAttribute('data-customer') || 'Walk-in',
        mobile: btn.getAttribute('data-mobile') || '-',
        salesman: btn.getAttribute('data-salesman') || '-',
        entry: btn.getAttribute('data-entry') || '-',
        product: btn.getAttribute('data-product') || 'N/A',
        bottles: btn.getAttribute('data-bottles') || '0',
        empties: btn.getAttribute('data-empties') || '0',
        rate: btn.getAttribute('data-rate') || '0.00',
        total: btn.getAttribute('data-total') || '0.00',
        cash: btn.getAttribute('data-cash') || '0.00',
        date: btn.getAttribute('data-date') || '',
        notes: btn.getAttribute('data-notes') || ''
    };
    const notesRow = d.notes ? '<tr><th>Notes</th><td>' + d.notes + '</td></tr>' : '';
    const slip = `
        <div class="print-header">
            <div class="print-brand-row">
                <div class="print-logo-circle"><i class="fas fa-tint"></i></div>
                <div class="print-brand-text">
                    <div class="print-owner-name">${ownerName}</div>
                    <div class="print-company">${companyName}</div>
                    <div class="print-address">${ownerAddress}</div>
                    <div class="print-phone">${ownerPhone}</div>
                </div>
            </div>
            <div class="print-divider"></div>
        </div>
        <div class="print-title-row">
            <span class="print-doc-title">Delivery Voucher</span>
            <span class="print-date-range">Voucher: ${d.voucher}</span>
        </div>
        <div class="print-thin-divider"></div>
        <div class="print-customer-section">
            <div class="print-customer-row"><span class="print-label">Customer:</span><span class="print-value"><strong>${d.customer}</strong> <span style="color:#888;">(${d.code})</span></span></div>
            <div class="print-customer-row"><span class="print-label">Mobile:</span><span class="print-value">${d.mobile}</span></div>
            <div class="print-customer-row"><span class="print-label">Salesman:</span><span class="print-value">${d.salesman}</span></div>
            <div class="print-customer-row"><span class="print-label">Date & Time:</span><span class="print-value">${d.date}</span></div>
        </div>
        <div class="print-thin-divider"></div>
        <table class="print-table">
            <tbody>
                <tr><th>Product</th><td>${d.product}</td></tr>
                <tr><th>Bottles Delivered</th><td>${d.bottles}</td></tr>
                <tr><th>Empty Bottles Returned</th><td>${d.empties}</td></tr>
                <tr><th>Bottle Rate (Rs)</th><td>${d.rate}</td></tr>
                <tr><th>Total Amount (Rs)</th><td><strong>${d.total}</strong></td></tr>
                <tr><th>Cash Received (Rs)</th><td>${d.cash}</td></tr>
                ${notesRow}
            </tbody>
        </table>
        <div class="print-sign-row">
            <div class="print-sign-box"><div class="print-sign-line">Customer Signature</div></div>
            <div class="print-sign-box"><div class="print-sign-line">Authorized Signature</div></div>
        </div>
        <div class="print-footer">Generated on: ${new Date().toLocaleString('en-GB')}</div>`;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Delivery Voucher ${d.voucher}</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f0f0f0; }
                @page { size: A4 portrait; margin: 10mm; }
                .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 2px solid #A04657; padding: 10px 20px; display: flex; gap: 10px; align-items: center; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .toolbar button { padding: 8px 20px; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
                .btn-print { background: #A04657; color: #fff; }
                .btn-print:hover { background: #8a3a4a; }
                .btn-close { background: #6c757d; color: #fff; margin-left: auto; }
                .btn-close:hover { background: #5a6268; }
                .voucher-wrap { max-width: 720px; margin: 0 auto; padding: 30px; background: #fff; min-height: 900px; }
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
                .print-thin-divider { border-top: 1px dashed #ccc; margin: 14px 0; }
                .print-customer-section { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
                .print-customer-row { display: flex; gap: 10px; font-size: 13px; }
                .print-label { color: #888; min-width: 100px; font-weight: 600; }
                .print-value { color: #222; }
                .print-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 8px; }
                .print-table th { text-align: left; color: #888; padding: 10px 12px; font-weight: 600; width: 220px; border-bottom: 1px solid #eee; }
                .print-table td { padding: 10px 12px; border-bottom: 1px solid #eee; color: #222; }
                .print-sign-row { display: flex; justify-content: space-between; gap: 40px; margin-top: 80px; }
                .print-sign-box { flex: 1; text-align: center; }
                .print-sign-line { border-top: 1px solid #999; padding-top: 8px; font-size: 12px; color: #555; }
                .print-footer { margin-top: 40px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } .voucher-wrap { box-shadow: none; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Delivery Voucher ${d.voucher}</strong>
                <button class="btn-print" onclick="window.print()">Print</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <div class="voucher-wrap">
                ${slip}
            </div>
        </body>
        </html>
    `);
    w.document.close();
}

function printDeliveries() {
    const content = document.getElementById('print-area').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Delivery Report</title>
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
                .print-footer { margin-top: 18px; text-align: center; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #eee; }
                @media print { .toolbar { display: none; } body { background: #fff; } }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <strong style="color:#A04657;">Delivery Report</strong>
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

<!-- View Delivery Modal -->
<div class="modal fade" id="viewDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i> Delivery Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted d-block">Customer</small>
                            <strong id="view_customer" class="fs-5"></strong>
                        </div>
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill fs-6" id="view_voucher"></span>
                    </div>
                    <small class="text-muted" id="view_mobile"></small>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr><td class="text-muted">Customer Code</td><td class="text-end fw-semibold" id="view_code"></td></tr>
                        <tr><td class="text-muted">Salesman</td><td class="text-end fw-semibold" id="view_salesman"></td></tr>
                        <tr><td class="text-muted">Entry By</td><td class="text-end fw-semibold" id="view_entry"></td></tr>
                        <tr><td class="text-muted">Product</td><td class="text-end fw-semibold" id="view_product"></td></tr>
                        <tr><td class="text-muted">Bottles Delivered</td><td class="text-end fw-semibold" id="view_bottles"></td></tr>
                        <tr><td class="text-muted">Empty Returned</td><td class="text-end fw-semibold" id="view_empties"></td></tr>
                        <tr><td class="text-muted">Bottle Rate</td><td class="text-end fw-semibold" id="view_rate"></td></tr>
                        <tr><td class="text-muted">Total Amount</td><td class="text-end fw-bold text-success fs-5" id="view_total"></td></tr>
                        <tr><td class="text-muted">Cash Received</td><td class="text-end fw-semibold" style="color:#e67e22;" id="view_cash"></td></tr>
                        <tr><td class="text-muted">Date & Time</td><td class="text-end fw-semibold" id="view_date"></td></tr>
                        <tr><td class="text-muted">Notes</td><td class="text-end" id="view_notes"></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Delivery Modal -->
<div class="modal fade" id="editDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-warning text-white border-0 rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Delivery</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo $form_action; ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="delivery_id" id="edit_delivery_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product *</label>
                        <select name="product_id" id="edit_product_id" class="form-select" required>
                            <?php
                            mysqli_data_seek($products_edit, 0);
                            while($pe = mysqli_fetch_assoc($products_edit)): ?>
                            <option value="<?php echo $pe['id']; ?>">
                                <?php echo htmlspecialchars($pe['product_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Bottles Delivered *</label>
                            <input type="number" name="bottles_delivered" id="edit_bottles" class="form-control" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty Returned</label>
                            <input type="number" name="empty_bottles_returned" id="edit_empties" class="form-control" min="0" value="">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Bottle Rate (Rs) *</label>
                            <input type="number" step="0.01" name="bottle_rate" id="edit_rate" class="form-control" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Cash Received (Rs)</label>
                            <input type="number" step="0.01" name="cash_received" id="edit_cash" class="form-control" min="0" value="">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_delivery" class="btn btn-primary rounded-pill px-4">Update Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.viewDeliveryBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('view_customer').textContent = this.getAttribute('data-customer');
            document.getElementById('view_voucher').textContent = this.getAttribute('data-voucher') || '—';
            document.getElementById('view_mobile').textContent = this.getAttribute('data-mobile') ? 'Mobile: ' + this.getAttribute('data-mobile') : '';
            document.getElementById('view_code').textContent = this.getAttribute('data-code') || '-';
            document.getElementById('view_salesman').textContent = this.getAttribute('data-salesman') || '-';
            document.getElementById('view_entry').textContent = this.getAttribute('data-entry') || '-';
            document.getElementById('view_product').textContent = this.getAttribute('data-product') || 'N/A';
            document.getElementById('view_bottles').textContent = this.getAttribute('data-bottles');
            document.getElementById('view_empties').textContent = this.getAttribute('data-empties');
            document.getElementById('view_rate').textContent = this.getAttribute('data-rate');
            document.getElementById('view_total').textContent = this.getAttribute('data-total');
            document.getElementById('view_cash').textContent = this.getAttribute('data-cash');
            document.getElementById('view_date').textContent = this.getAttribute('data-date');
            document.getElementById('view_notes').textContent = this.getAttribute('data-notes') || '—';
            var viewModal = new bootstrap.Modal(document.getElementById('viewDeliveryModal'));
            viewModal.show();
        });
    });
    document.querySelectorAll('.printDeliveryBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            printDelivery(this.getAttribute('data-id'));
        });
    });
    document.querySelectorAll('.editDeliveryBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_delivery_id').value = this.getAttribute('data-id');
            document.getElementById('edit_product_id').value = this.getAttribute('data-product');
            document.getElementById('edit_bottles').value = this.getAttribute('data-bottles');
            document.getElementById('edit_empties').value = this.getAttribute('data-empties');
            document.getElementById('edit_rate').value = this.getAttribute('data-rate');
            document.getElementById('edit_cash').value = this.getAttribute('data-cash');
            document.getElementById('edit_notes').value = this.getAttribute('data-notes');
            var editModal = new bootstrap.Modal(document.getElementById('editDeliveryModal'));
            editModal.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
