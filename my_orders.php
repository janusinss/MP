<?php
include 'db.php';
session_start();

// Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch orders for THIS user only
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 My Order History</h2>
        <a href="index.php" class="btn btn-primary">Back to Shop</a>
    </div>

    <?php if (count($orders) > 0): ?>
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-md-12 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Order <strong>#<?= $order['id'] ?></strong></span>
                            <small class="text-muted"><?= date('M d, Y', strtotime($order['created_at'])) ?></small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="mb-1"><strong>Total:</strong> $<?= number_format($order['total_amount'], 2) ?></p>
                                    <p class="mb-1"><strong>Status:</strong> 
                                        <span class="badge bg-secondary"><?= $order['status'] ?></span>
                                    </p>
                                </div>
                                <div class="col-md-8">
                                    <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">You haven't placed any orders yet.</div>
    <?php endif; ?>

</body>
</html>