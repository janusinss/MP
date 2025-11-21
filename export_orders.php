<?php
include 'db.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) { exit; }

// Set Headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=orders_export.csv');

// Create File Pointer
$output = fopen('php://output', 'w');

// Add Column Headings
fputcsv($output, ['Order ID', 'Customer', 'Address', 'Total', 'Status', 'Date']);

// Fetch Data
$stmt = $pdo->query("SELECT id, customer_name, address, total_amount, status, created_at FROM orders");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>