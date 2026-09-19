<?php
// admin/export_orders.php
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { 
    exit("Access Denied"); 
}

// Force CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=freshcart_orders_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Order ID', 'Customer Name', 'Address', 'Total Amount', 'Status', 'Date Placed']);

$stmt = $pdo->query("SELECT id, customer_name, address, total_amount, status, created_at FROM orders ORDER BY id DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
