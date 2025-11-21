<?php
include 'db.php';
session_start();

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// 1. Handle Review Submission
$review_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: user_login.php");
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    $stmtRev = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmtRev->execute([$id, $user_id, $rating, $comment]);
    
    $review_msg = "Review submitted successfully!";
}

// 2. Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found.");
}

// 3. Fetch Reviews for this product (Join with Users to get names)
$stmtReviews = $pdo->prepare("SELECT reviews.*, users.full_name FROM reviews 
                              JOIN users ON reviews.user_id = users.id 
                              WHERE product_id = ? ORDER BY created_at DESC");
$stmtReviews->execute([$id]);
$reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

// 4. RECOMMENDATION ENGINE: Fetch Related Products
// Logic: Same Category, but NOT the current product ID
$stmtRelated = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4");
$stmtRelated->execute([$product['category'], $id]);
$relatedProducts = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

// Calculate Average Rating
$avgRating = 0;
if (count($reviews) > 0) {
    $sum = 0;
    foreach ($reviews as $r) $sum += $r['rating'];
    $avgRating = round($sum / count($reviews), 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($product['name']) ?> - Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary">&larr; Back to Shop</a>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="row g-0">
            <div class="col-md-6">
                <?php $img = $product['image'] ?? 'default.jpg'; ?>
                <img src="assets/images/<?= $img ?>" class="img-fluid rounded-start w-100" style="height: 400px; object-fit: cover;" alt="Product">
            </div>
            <div class="col-md-6">
                <div class="card-body p-4">
                    <h6 class="text-muted text-uppercase"><?= $product['category'] ?></h6>
                    <h1 class="display-5 fw-bold"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="mb-3">
                        <span class="fs-4 fw-bold text-success">$<?= number_format($product['price'], 2) ?></span>
                        
                        <span class="ms-3 text-warning">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $avgRating ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <small class="text-muted">(<?= count($reviews) ?> reviews)</small>
                    </div>

                    <p class="lead">Fresh, high-quality <?= strtolower($product['category']) ?> sourced directly from local farmers. Guaranteed freshness or your money back.</p>

                    <?php if ($product['stock_qty'] > 0): ?>
                        <p class="text-success"><i class="bi bi-check-circle-fill"></i> In Stock (<?= $product['stock_qty'] ?> available)</p>
                        
                        <form action="add_to_cart.php" method="POST" class="d-grid">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-lg">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <p class="text-danger"><i class="bi bi-x-circle-fill"></i> Out of Stock</p>
                        <button class="btn btn-secondary btn-lg w-100" disabled>Unavailable</button>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <h3 class="mb-4">Customer Reviews</h3>
            
            <?php if ($review_msg): ?>
                <div class="alert alert-success"><?= $review_msg ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="card mb-4 bg-light border-0">
                    <div class="card-body">
                        <h5 class="card-title">Write a Review</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-select w-25">
                                    <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                                    <option value="4">⭐⭐⭐⭐ (4/5)</option>
                                    <option value="3">⭐⭐⭐ (3/5)</option>
                                    <option value="2">⭐⭐ (2/5)</option>
                                    <option value="1">⭐ (1/5)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <textarea name="comment" class="form-control" rows="3" placeholder="Share your thoughts..." required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-dark">Post Review</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Please <a href="user_login.php">login</a> to write a review.
                </div>
            <?php endif; ?>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold"><?= htmlspecialchars($r['full_name']) ?></h6>
                                <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                            </div>
                            <div class="text-warning mb-2">
                                <?php for($i=0; $i<$r['rating']; $i++) echo '★'; ?>
                            </div>
                            <p class="card-text"><?= htmlspecialchars($r['comment']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </div>
    </div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill"></i> Item added to cart!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('form[action="add_to_cart.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); 

                const formData = new FormData(this);

                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Success Logic (Update Badge + Toast)
                        // Note: product.php might not have the badge ID if headers differ, 
                        // but if you use the same header include, it works.
                        const badge = document.getElementById('cart-badge');
                        if(badge) badge.innerText = data.cart_count;
                        
                        const toastEl = document.getElementById('liveToast');
                        if(toastEl) {
                            const toast = new bootstrap.Toast(toastEl);
                            toast.show();
                        }
                    } 
                    // *** NEW: ALERT BEFORE REDIRECT ***
                    else if (data.status === 'login_required') {
                        alert("Log in to add to cart"); 
                        window.location.href = 'user_login.php';
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
    </script>

<?php if (count($relatedProducts) > 0): ?>
    <div class="mt-5 mb-5">
        <h3 class="mb-4">You Might Also Like</h3>
        <div class="row">
            <?php foreach ($relatedProducts as $rp): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <a href="product.php?id=<?= $rp['id'] ?>" class="text-decoration-none text-dark">
                            <?php $rImg = $rp['image'] ?? 'default.jpg'; ?>
                            <div class="position-relative">
                                <img src="assets/images/<?= $rImg ?>" class="card-img-top" style="height: 150px; object-fit: cover;">
                                <span class="position-absolute top-0 start-0 badge bg-light text-dark m-2 border">
                                    <?= $rp['category'] ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title text-truncate"><?= htmlspecialchars($rp['name']) ?></h6>
                                <p class="text-success fw-bold mb-0">$<?= number_format($rp['price'], 2) ?></p>
                            </div>
                        </a>
                        <div class="card-footer bg-white border-0">
                            <form action="add_to_cart.php" method="POST">
                                <input type="hidden" name="product_id" value="<?= $rp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Add</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    
</body>
</html>