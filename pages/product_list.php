<?php
require_once '../includes/db.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: ../login.php"); exit; }

$result = mysqli_query($conn, "SELECT product_code, product_name, sale_price, current_stock FROM products WHERE status='Active' ORDER BY product_code ASC");
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<style>
    .print-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .btn-print { background: #A04657; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; }
    .btn-print:hover { background: #8a3a4a; }
    .search-box { border: 1px solid #dee2e6; border-radius: 8px; padding: 8px 14px; font-size: 14px; width: 280px; }
    .search-box:focus { outline: none; border-color: #A04657; box-shadow: 0 0 0 2px rgba(160,70,87,0.15); }
    .list-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .list-table th { background: #A04657; color: #fff; padding: 10px 12px; text-align: left; font-size: 13px; }
    .list-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
    .list-table tr:hover td { background: #f9f9f9; }
    .list-table .id-col { font-weight: bold; color: #A04657; width: 70px; }
    .empty-msg { text-align: center; padding: 30px; color: #999; }
    .record-count { font-size: 13px; color: #888; }

    @media print {
        body * { visibility: hidden; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; padding: 15px; }
        .no-print { display: none !important; }
        .list-table th { background: #A04657 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="print-header no-print">
    <h5><i class="fas fa-boxes me-2"></i> Product ID List</h5>
    <div class="d-flex align-items-center gap-3">
        <input type="text" class="search-box" id="searchInput" placeholder="Search by ID, name..." oninput="filterTable()">
        <span class="record-count" id="recordCount"></span>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
    </div>
</div>

<div class="print-area">
    <?php if($result && mysqli_num_rows($result) > 0): ?>
    <table class="list-table" id="listTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Sale Rate</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td class="id-col"><?php echo htmlspecialchars($row['product_code']); ?></td>
                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td>Rs <?php echo number_format($row['sale_price'], 2); ?></td>
                <td><?php echo $row['current_stock']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="empty-msg">No active products found.</p>
    <?php endif; ?>
</div>

<script>
function filterTable() {
    var input = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#listTable tbody tr');
    var visible = 0;
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        if (text.includes(input)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('recordCount').textContent = visible + ' of ' + rows.length + ' records';
}
filterTable();
</script>

<?php include '../includes/footer.php'; ?>
