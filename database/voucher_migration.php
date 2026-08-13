<?php
/**
 * Voucher Numbering Migration
 *
 * Adds the `voucher_no` column to the Sales, Purchase, Receiving (customer
 * payment) and Payment (supplier payment) tables, then back-fills voucher
 * numbers for any existing rows.
 *
 * Safe to run multiple times (idempotent).
 * Execute: http://localhost/fly_water_h2o/database/voucher_migration.php
 */

require_once __DIR__ . '/../includes/db.php';

function column_exists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    $row = $res ? mysqli_fetch_assoc($res) : ['cnt' => 0];
    return intval($row['cnt']) > 0;
}

function add_column($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    return mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `$c` VARCHAR(30) DEFAULT NULL, ADD KEY `idx_voucher_$c` (`$c`)");
}

function backfill_vouchers($conn, $table, $column, $prefix) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $rows = mysqli_query($conn, "SELECT id FROM `$t` WHERE `$c` IS NULL OR `$c` = '' ORDER BY id ASC");
    $seq = 1;
    while ($r = mysqli_fetch_assoc($rows)) {
        $voucher = $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
        mysqli_query($conn, "UPDATE `$t` SET `$c` = '$voucher' WHERE id = " . intval($r['id']));
        $seq++;
    }
    return $seq - 1;
}

$tables = [
    ['table' => 'water_deliveries',        'column' => 'voucher_no', 'prefix' => 'SLS-'],
    ['table' => 'raw_material_purchases',  'column' => 'voucher_no', 'prefix' => 'PUR-'],
    ['table' => 'customer_payments',       'column' => 'voucher_no', 'prefix' => 'RCP-'],
    ['table' => 'supplier_payments',       'column' => 'voucher_no', 'prefix' => 'PAY-'],
];

$messages = [];

foreach ($tables as $cfg) {
    $table  = $cfg['table'];
    $column = $cfg['column'];
    $prefix = $cfg['prefix'];

    if (!column_exists($conn, $table, $column)) {
        if (add_column($conn, $table, $column)) {
            $messages[] = "Added column `$column` to `$table`.";
        } else {
            $messages[] = "ERROR adding `$column` to `$table`: " . mysqli_error($conn);
            continue;
        }
    }

    $count = backfill_vouchers($conn, $table, $column, $prefix);
    $messages[] = "`$table` ready (" . ($count ? "back-filled $count voucher(s)" : "0 rows to back-fill") . ").";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher Numbering Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-success text-white rounded-top-4">
            <h4 class="mb-0"><i class="fa-solid fa-tags"></i> Voucher Numbering Migration</h4>
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <?php foreach ($messages as $m): ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($m); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 mb-0 text-muted small">Migration completed. You can close this page.</p>
        </div>
    </div>
</div>
</body>
</html>
