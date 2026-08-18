<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

echo '<pre>';
echo 'PHP version: ' . PHP_VERSION . PHP_EOL . PHP_EOL;

$conn = null;
try {
    @include_once __DIR__ . '/includes/db.php';
    echo 'includes/db.php loaded OK' . PHP_EOL;
    $conn = $conn ?? null;
} catch (Throwable $e) {
    echo 'includes/db.php FAILED: ' . $e->getMessage() . PHP_EOL;
    echo 'at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . PHP_EOL;
}

$tables = [
    'customers'          => ['customer_code'],
    'suppliers'          => ['supplier_code'],
    'products'           => ['product_code'],
    'raw_materials'      => ['material_code'],
    'expense_categories' => ['category_code'],
    'expenses'           => ['voucher_no'],
    'water_deliveries'   => ['voucher_no'],
    'raw_material_purchases' => ['voucher_no'],
    'customer_payments'  => ['voucher_no'],
    'supplier_payments'  => ['voucher_no'],
    'users'              => ['role'],
    'bottle_tracking'    => ['bottles_broken'],
];

foreach ($tables as $table => $cols) {
    foreach ($cols as $col) {
        $state = 'UNKNOWN';
        if ($conn) {
            $t = mysqli_real_escape_string($conn, $table);
            $c = mysqli_real_escape_string($conn, $col);
            $q = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
            $row = $q ? mysqli_fetch_assoc($q) : null;
            $state = ($row && intval($row['cnt']) > 0) ? 'OK   ' : 'MISS ';
        }
        echo $state . $table . '.' . $col . PHP_EOL;
    }
}

echo PHP_EOL . '--- helper functions ---' . PHP_EOL;
foreach (['generate_5digit_code', 'generate_voucher_no', 'is_salesman', 'salesman_match_condition'] as $f) {
    echo (function_exists($f) ? 'OK   ' : 'MISS ') . 'function ' . $f . '()' . PHP_EOL;
}

echo PHP_EOL . '--- session ---' . PHP_EOL;
echo 'admin_logged_in: ' . (isset($_SESSION['admin_logged_in']) ? 'yes' : 'no') . PHP_EOL;
echo 'role: ' . (isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : '(not set)') . PHP_EOL;
echo '</pre>';
