<?php
session_start();
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "fly_water_h2o";

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

require_once __DIR__ . '/txt.php';

function current_role() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}

function is_admin() {
    return current_role() === 'admin';
}

function is_salesman() {
    return current_role() === 'salesman';
}

function current_salesman_names($conn) {
    $names = [];
    $uid = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
    if (!$uid) return $names;
    $res = mysqli_query($conn, "SELECT username, full_name FROM users WHERE id=$uid");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!empty($row['username'])) $names[] = $row['username'];
        if (!empty($row['full_name'])) $names[] = $row['full_name'];
    }
    return $names;
}

function salesman_match_condition($conn, $alias = 'c') {
    if (!is_salesman()) return '';
    $names = [];
    foreach (current_salesman_names($conn) as $n) {
        $names[] = mysqli_real_escape_string($conn, $n);
    }
    if (empty($names)) return '';
    $parts = [];
    foreach ($names as $n) {
        $parts[] = "LOWER($alias.salesman) = LOWER('$n')";
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function salesman_owns_customer($conn, $customer_salesman) {
    if (!is_salesman()) return true;
    $lname = strtolower(trim((string)$customer_salesman));
    if ($lname === '') return false;
    foreach (current_salesman_names($conn) as $n) {
        if (strtolower(trim((string)$n)) === $lname) return true;
    }
    return false;
}

$allowed_salesman_pages = ['deliveries.php', 'delivery_view.php'];
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && is_salesman()) {
    $script_name = basename($_SERVER['PHP_SELF']);
    if (!in_array($script_name, $allowed_salesman_pages)) {
        $in_pages = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false);
        header("Location: " . ($in_pages ? 'deliveries.php' : 'pages/deliveries.php'));
        exit;
    }
}
?>
