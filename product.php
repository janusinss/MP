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

    try {
        $stmtRev = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmtRev->execute([$id, $user_id, $rating, $comment]);
        $review_msg = "Review submitted successfully!";
    } catch (Exception $e) {
        $review_msg = "Error submitting review.";
    }
}

// 2. Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found.");
}

// 3. Fetch Reviews
$stmtReviews = $pdo->prepare("SELECT reviews.*, users.full_name FROM reviews 
                              JOIN users ON reviews.user_id = users.id 
                              WHERE product_id = ? ORDER BY created_at DESC");
$stmtReviews->execute([$id]);
$reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Related Products
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
    <title><?= htmlspecialchars($product['name']) ?> | FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="container mt-5 mb-5">
    
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to Shop</a>

    <div class="product-showcase animate-fade-in">
        <div class="row g-0">
            <div class="col-lg-6">
                <div class="product-image-stage">
                    <?php $img = $product['image'] ? $product['image'] : 'default.jpg'; ?>
                    <img src="assets/images/<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainImage">
                    
                    <div class="position-absolute bottom-0 start-0 p-4">
                        <span class="badge bg-white text-dark shadow-sm border">
                            <i class="bi bi-camera"></i> Zoom on Hover
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="product-details-panel">
                    <div class="product-breadcrumb">
                        Home / <?= htmlspecialchars($product['category']) ?> / <?= htmlspecialchars($product['name']) ?>
                    </div>

                    <h1 class="product-title-large"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="product-rating">
                        <?php 
                        for($i=1; $i<=5; $i++) {
                            if($i <= $avgRating) echo '<i class="bi bi-star-fill"></i>';
                            elseif($i - 0.5 <= $avgRating) echo '<i class="bi bi-star-half"></i>';
                            else echo '<i class="bi bi-star text-muted opacity-25"></i>';
                        } 
                        ?>
                        <span class="text-muted ms-2">(<?= count($reviews) ?> reviews)</span>
                    </div>

                    <div class="product-price-large">
                        $<?= number_format($product['price'], 2) ?>
                        <?php if($product['stock_qty'] > 0): ?>
                            <span class="stock-badge"><i class="bi bi-check-circle-fill"></i> In Stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <p>Experience the freshness of our premium <?= strtolower($product['name']) ?>. Sourced from sustainable farms, handled with care, and delivered directly to your doorstep. Perfect for your daily nutrition needs.</p>
                        <ul class="list-unstyled text-muted small mt-3">
                            <li><i class="bi bi-check2 text-success me-2"></i> 100% Organic & Fresh</li>
                            <li><i class="bi bi-check2 text-success me-2"></i> Quality Checked</li>
                            <li><i class="bi bi-check2 text-success me-2"></i> Fast Delivery Available</li>
                        </ul>
                    </div>

                    <?php if ($product['stock_qty'] > 0): ?>
                        <form action="add_to_cart.php" method="POST" id="addToCartForm">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <div class="row align-items-center g-2">
                                <div class="col-12">
                                    <button type="submit" class="btn-add-large">
                                        Add to Cart — $<?= number_format($product['price'], 2) ?>
                                    </button>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted"><i class="bi bi-truck"></i> Free shipping on orders over $50</small>
                            </div>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 py-3 rounded-4" disabled>Currently Unavailable</button>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        
        <div class="col-lg-7">
            <h3 class="mb-4" style="font-family: var(--font-serif);">Customer Reviews</h3>
            
            <div class="review-section">
                <?php if ($review_msg): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4"><?= $review_msg ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Leave a Review</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small text-muted text-uppercase">Rating</label>
                                    <div class="rating-input">
                                        <select name="rating" class="form-select w-auto" required>
                                            <option value="5">★★★★★ (Excellent)</option>
                                            <option value="4">★★★★ (Good)</option>
                                            <option value="3">★★★ (Average)</option>
                                            <option value="2">★★ (Poor)</option>
                                            <option value="1">★ (Terrible)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <textarea name="comment" class="form-control" rows="3" placeholder="How was the product?" required></textarea>
                                </div>
                                <button type="submit" name="submit_review" class="btn btn-dark btn-sm px-4 rounded-pill">Post Review</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border mb-4">
                        Please <a href="user_login.php" class="text-decoration-underline text-dark">login</a> to write a review.
                    </div>
                <?php endif; ?>

                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-card">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-bold"><?= htmlspecialchars($r['full_name']) ?></span>
                                <span class="text-warning small">
                                    <?php for($i=0; $i<$r['rating']; $i++) echo '★'; ?>
                                </span>
                            </div>
                            <p class="text-muted mb-1"><?= htmlspecialchars($r['comment']) ?></p>
                            <small class="text-muted opacity-50"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No reviews yet. Be the first!</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <h3 class="mb-4" style="font-family: var(--font-serif);">You Might Also Like</h3>
            <div class="row g-3">
                <?php foreach ($relatedProducts as $rp): ?>
                    <div class="col-sm-6">
                        <div class="product-card h-100">
                            <?php $rImg = $rp['image'] ? $rp['image'] : 'default.jpg'; ?>
                            <a href="product.php?id=<?= $rp['id'] ?>" class="text-decoration-none text-dark">
                                <img src="assets/images/<?= $rImg ?>" class="card-img-top" alt="<?= htmlspecialchars($rp['name']) ?>">
                                <div class="card-body p-3">
                                    <span class="category-badge"><?= $rp['category'] ?></span>
                                    <h6 class="fw-bold mb-2 text-truncate"><?= htmlspecialchars($rp['name']) ?></h6>
                                    <div class="price-tag small mb-2">$<?= number_format($rp['price'], 2) ?></div>
                                    <button class="btn btn-outline-dark btn-sm w-100 rounded-pill">View Details</button>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">Item added to cart successfully!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // AJAX Add to Cart
        document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('add_to_cart.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                    toast.show();
                    // Optional: Update Cart Badge here if you have one in the header
                } else if (data.status === 'login_required') {
                    window.location.href = 'user_login.php';
                } else {
                    alert(data.message);
                }
            });
        });
    </script>
</body>
</html>