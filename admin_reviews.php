<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch all reviews with User Name and Product Name
$sql = "SELECT reviews.*, users.full_name, products.name as product_name, products.image 
        FROM reviews 
        JOIN users ON reviews.user_id = users.id 
        JOIN products ON reviews.product_id = products.id 
        ORDER BY reviews.created_at DESC";
$stmt = $pdo->query($sql);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>⭐ Customer Reviews</h2>
        <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (count($reviews) > 0): ?>
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/<?= $r['image'] ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                    <span class="fw-bold"><?= htmlspecialchars($r['product_name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td class="text-warning fw-bold">
                                <?= $r['rating'] ?>/5
                            </td>
                            <td>
                                <em class="text-muted">"<?= htmlspecialchars($r['comment']) ?>"</em>
                            </td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <a href="delete_review.php?id=<?= $r['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this review?');">
                                    🗑 Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info text-center">No reviews found.</div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>