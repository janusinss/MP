<?php
include 'db.php';
session_start();

if (!isset($_GET['orderid'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_GET['orderid'];

// Fetch Order Details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Invalid Order ID");
}

// Fetch Order Items
$stmtItems = $pdo->prepare("SELECT order_items.*, products.name, products.price 
                            FROM order_items 
                            JOIN products ON order_items.product_id = products.id 
                            WHERE order_items.order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Order Confirmed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Print Styles: Hide buttons when printing */
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
        .receipt-header { border-bottom: 2px dashed #ddd; padding-bottom: 20px; margin-bottom: 20px; }
        .receipt-footer { border-top: 2px dashed #ddd; padding-top: 20px; margin-top: 20px; }
    </style>
</head>
<body class="container mt-5">
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow p-4">
                
                <div class="text-center receipt-header">
                    <h1 class="text-success">✔ Success!</h1>
                    <h4 class="mb-0">FreshCart Market</h4>
                    <p class="text-muted">Receipt #<?= str_pad($order['id'], 6, "0", STR_PAD_LEFT) ?></p>
                </div>

                <div class="mb-4">
                    <strong>Billed To:</strong><br>
                    <?= htmlspecialchars($order['customer_name']) ?><br>
                    <?= htmlspecialchars($order['address']) ?><br>
                    <small class="text-muted"><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></small>
                </div>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $subtotal = 0; ?>
                        <?php foreach ($items as $item): ?>
                            <?php $lineTotal = $item['price'] * $item['quantity']; ?>
                            <?php $subtotal += $lineTotal; ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end">$<?= number_format($lineTotal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="receipt-footer">
                    <div class="d-flex justify-content-between">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    
                    <?php $discount = $subtotal - $order['total_amount']; ?>
                    <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between text-success">
                            <span>Discount Applied:</span>
                            <span>-$<?= number_format($discount, 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between fs-4 fw-bold mt-2">
                        <span>Total Paid:</span>
                        <span>$<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                </div>

                <div class="mt-4 d-grid gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-outline-dark">
                        🖨 Print Receipt
                    </button>
                    <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                </div>

            </div>
        </div>
    </div>

</body>
</html>