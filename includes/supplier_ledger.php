<?php

function recomputePurchaseStatus($purchase_id) {
    global $conn;
    $purchase_id = intval($purchase_id);
    mysqli_query($conn, "UPDATE raw_material_purchases 
                         SET payment_status = CASE 
                             WHEN credit_amount <= 0 THEN 'Paid'
                             WHEN paid_amount > 0 THEN 'Partial'
                             ELSE 'Credit'
                         END
                         WHERE id=$purchase_id");
}

function rebuildSupplierLedger($supplier_id) {
    global $conn;
    $supplier_id = intval($supplier_id);
    $sup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT opening_balance, created_datetime FROM suppliers WHERE id=$supplier_id"));
    if(!$sup) return;
    $opening = floatval($sup['opening_balance']);

    // Preserve original opening-balance date if an opening entry exists
    $opening_dt = $sup['created_datetime'] ?: date('Y-m-d H:i:s');
    $opening_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT transaction_date FROM supplier_ledger WHERE supplier_id=$supplier_id AND reference_type='opening' ORDER BY id ASC LIMIT 1"));
    if($opening_row) $opening_dt = $opening_row['transaction_date'];

    mysqli_query($conn, "DELETE FROM supplier_ledger WHERE supplier_id=$supplier_id");

    $rows = [];
    if($opening != 0) {
        $rows[] = [
            'dt'    => $opening_dt,
            'seq'   => 0,
            'id'    => 0,
            'desc'  => 'Opening Balance',
            'debit' => $opening < 0 ? abs($opening) : 0,
            'credit'=> $opening > 0 ? $opening : 0,
            'ref_id' => 'NULL',
            'ref_type' => 'opening'
        ];
    }

    $purchases = mysqli_query($conn, "SELECT id, purchase_date, total_amount, invoice_no, voucher_no FROM raw_material_purchases WHERE supplier_id=$supplier_id");
    while($p = mysqli_fetch_assoc($purchases)) {
        $inv = $p['invoice_no'] ?: ($p['voucher_no'] ?: ('#' . $p['id']));
        $rows[] = [
            'dt'    => $p['purchase_date'],
            'seq'   => 1,
            'id'    => intval($p['id']),
            'desc'  => "Purchase - Invoice: " . $inv,
            'debit' => 0,
            'credit'=> floatval($p['total_amount']),
            'ref_id' => intval($p['id']),
            'ref_type' => 'purchase'
        ];
    }

    $payments = mysqli_query($conn, "SELECT id, payment_datetime, payment_amount, voucher_no, purchase_id FROM supplier_payments WHERE supplier_id=$supplier_id");
    while($p = mysqli_fetch_assoc($payments)) {
        $desc = $p['purchase_id'] ? ("Payment against Purchase #" . $p['purchase_id']) : "Supplier Payment";
        if(!empty($p['voucher_no'])) $desc .= " - Voucher: " . $p['voucher_no'];
        $rows[] = [
            'dt'    => $p['payment_datetime'],
            'seq'   => 2,
            'id'    => intval($p['id']),
            'desc'  => $desc,
            'debit' => floatval($p['payment_amount']),
            'credit'=> 0,
            'ref_id' => intval($p['id']),
            'ref_type' => 'payment'
        ];
    }

    usort($rows, function($a, $b) {
        if($a['dt'] !== $b['dt']) return strcmp($a['dt'], $b['dt']);
        if($a['seq'] !== $b['seq']) return $a['seq'] <=> $b['seq'];
        return $a['id'] <=> $b['id'];
    });

    $running = 0;
    foreach($rows as $r) {
        $running = $running + $r['credit'] - $r['debit'];
        $desc = mysqli_real_escape_string($conn, $r['desc']);
        mysqli_query($conn, "INSERT INTO supplier_ledger (supplier_id, transaction_date, description, debit_amount, credit_amount, running_balance, reference_id, reference_type) 
                             VALUES ($supplier_id, '{$r['dt']}', '$desc', {$r['debit']}, {$r['credit']}, $running, {$r['ref_id']}, '{$r['ref_type']}')");
    }

    mysqli_query($conn, "UPDATE suppliers SET current_balance=$running WHERE id=$supplier_id");
}
