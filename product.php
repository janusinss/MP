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
$stmtRelated = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id != ? LIMIT 5");
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
    <a href="index.php" class="btn btn-outline-secondary rounded-pill mb-4 px-4">&larr; Back to Shop</a>

    <div class="product-showcase animate-fade-in">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="product-image-stage">
                    <?php $img = $product['image'] ? $product['image'] : 'default.jpg'; ?>
                    <img src="assets/images/<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainImage">
                    
                    <div class="position-absolute bottom-0 start-0 p-4">
                        <span class="badge bg-white text-dark shadow-sm border rounded-pill px-3 py-2">
                            <i class="bi bi-arrows-angle-expand me-1"></i> Hover to Zoom
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="product-details-panel">
                    <div class="product-breadcrumb">
                        Home / <?= htmlspecialchars($product['category']) ?>
                    </div>

                    <h1 class="product-title-large"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                if($i <= $avgRating) echo '<i class="bi bi-star-fill"></i>';
                                elseif($i - 0.5 <= $avgRating) echo '<i class="bi bi-star-half"></i>';
                                else echo '<i class="bi bi-star text-muted opacity-25"></i>';
                            } 
                            ?>
                        </div>
                        <span class="text-muted small">(<?= count($reviews) ?> reviews)</span>
                    </div>

                    <div class="product-price-large">
                        $<?= number_format($product['price'], 2) ?>
                        <?php if($product['stock_qty'] > 0): ?>
                            <span class="stock-badge"><i class="bi bi-check-circle-fill me-1"></i> In Stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill px-3">Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <p class="text-muted lead mb-4">
                        Freshly sourced and carefully selected. Our <?= strtolower($product['name']) ?> is perfect for your daily needs, guaranteeing quality and taste in every bite.
                    </p>

                    <ul class="product-features">
                        <li><i class="bi bi-check2"></i> 100% Organic & Sustainably Sourced</li>
                        <li><i class="bi bi-check2"></i> Quality Checked for Freshness</li>
                        <li><i class="bi bi-check2"></i> Available for Express Delivery</li>
                    </ul>

                    <?php if ($product['stock_qty'] > 0): ?>
                        <form action="add_to_cart.php" method="POST" id="addToCartForm" class="mt-4">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn-add-large">
                                Add to Cart — $<?= number_format($product['price'], 2) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 py-3 rounded-pill mt-4" disabled>Currently Unavailable</button>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div class="product-lower-section mt-5">
        
        <div class="reviews-wrapper">
            <h3 class="mb-4" style="font-family: var(--font-serif);">Customer Feedback</h3>
            
            <?php if ($review_msg): ?>
                <div class="alert alert-success border-0 shadow-sm mb-4 rounded-3"><i class="bi bi-check-circle me-2"></i> <?= $review_msg ?></div>
            <?php endif; ?>

            <div class="review-form-card">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <h5 class="mb-3">Share your experience</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-uppercase fw-bold text-muted">Your Rating</label>
                            <select name="rating" class="form-select border-0 bg-light rounded-pill w-auto px-4 fw-bold" required>
                                <option value="5">★★★★★ Excellent</option>
                                <option value="4">★★★★ Good</option>
                                <option value="3">★★★ Average</option>
                                <option value="2">★★ Poor</option>
                                <option value="1">★ Terrible</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <textarea name="comment" class="form-control border-0 bg-light rounded-4 p-3" rows="3" placeholder="What did you like or dislike?" required></textarea>
                        </div>
                        <button type="submit" name="submit_review" class="btn btn-dark rounded-pill px-4">Post Review</button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-2">
                        <p class="text-muted mb-3">Have you tried this product?</p>
                        <a href="user_login.php" class="btn btn-outline-dark rounded-pill px-4">Login to Review</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-secondary fw-bold" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                </div>
                                <span class="reviewer-name"><?= htmlspecialchars($r['full_name']) ?></span>
                            </div>
                            <span class="review-date"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                        </div>
                        
                        <div class="mb-2 text-warning" style="font-size: 0.8rem;">
                            <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="bi bi-star-fill"></i> '; ?>
                        </div>
                        
                        <p class="review-body">"<?= htmlspecialchars($r['comment']) ?>"</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-reviews-state">
                    <i class="bi bi-chat-square-quote fs-1 opacity-25 mb-3 d-block"></i>
                    <p class="mb-0">No reviews yet. Be the first to share your thoughts!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-wrapper">
            <h4 class="sidebar-title">You Might Also Like</h4>
            
            <div class="d-flex flex-column gap-2">
                <?php foreach ($relatedProducts as $rp): ?>
                    <?php $rImg = $rp['image'] ? $rp['image'] : 'default.jpg'; ?>
                    
                    <a href="product.php?id=<?= $rp['id'] ?>" class="text-decoration-none text-dark">
                        <div class="mini-product-card">
                            <div class="mini-img-box">
                                <img src="assets/images/<?= $rImg ?>" alt="<?= htmlspecialchars($rp['name']) ?>">
                            </div>
                            <div class="mini-details">
                                <span class="badge bg-light text-secondary border mb-1" style="font-size: 0.6rem;"><?= $rp['category'] ?></span>
                                <h6 class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($rp['name']) ?></h6>
                                <div class="mini-price">$<?= number_format($rp['price'], 2) ?></div>
                            </div>
                            <div class="ms-auto">
                                <button class="btn btn-sm btn-light rounded-circle border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-arrow-right-short"></i>
                                </button>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-dark border-0 rounded-4 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex p-2">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-bag-check-fill text-success fs-5"></i>
                    <span>Item added to cart!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('add_to_cart.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                    toast.show();
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