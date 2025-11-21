<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC"); // Newest first
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Product Inventory</h2>
        <div>
            <a href="add_product.php" class="btn btn-success me-2">➕ Add Product</a>
            <a href="admin.php" class="btn btn-secondary">Back to Orders</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Image</th> <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="width: 80px;">
                            <?php $img = $p['image'] ?? 'default.jpg'; ?>
                            <img src="assets/images/<?= $img ?>" alt="img" 
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                        </td>
                        
                        <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['category']) ?></span></td>
                        <td>$<?= number_format($p['price'], 2) ?></td>
                        <td><span class="fw-bold"><?= $p['stock_qty'] ?></span></td>

                        <td>
                            <?php if ($p['stock_qty'] == 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($p['stock_qty'] < 5): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">✏ Edit</a>
                            <a href="delete_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this item?');">🗑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>