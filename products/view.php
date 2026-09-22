<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slugParam = $_GET['slug'] ?? null;
$idParam = $_GET['id'] ?? null;

if (!$slugParam && !$idParam) {
    header("Location: ../");
    exit;
}

// 0. Fetch Product Details by Slug (Clean SEO route) or ID
if ($slugParam) {
    if (is_numeric($slugParam)) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([(int)$slugParam]);
    } else {
        $cleanName = str_replace('-', ' ', $slugParam);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE LOWER(REPLACE(REPLACE(name, ' ', '-'), '/', '-')) = ? OR LOWER(name) = ?");
        $stmt->execute([strtolower($slugParam), strtolower($cleanName)]);
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([(int)$idParam]);
}

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: ../");
    exit;
}

$id = (int)$product['id'];

// Canonical redirect: If accessed via legacy query string (view.php?id=...), redirect to clean masked URL (/product/slug)
if (isset($_GET['id']) && !isset($_GET['slug']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $cleanSlug = slugify($product['name']);
    header("Location: ../product/" . $cleanSlug, true, 301);
    exit;
}

$review_msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
    
    if (!verify_csrf_token()) {
        $review_msg = "Security validation failed. Please refresh.";
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim($_POST['comment'] ?? '');

        try {
            $stmtRev = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmtRev->execute([$id, $user_id, $rating, $comment]);
            $review_msg = "Review submitted successfully!";
        } catch (Exception $e) {
            $review_msg = "Error submitting review.";
        }
    }
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body class="container mt-5 mb-5">
    <a href="../" class="btn btn-outline-secondary rounded-pill mb-4 px-4">&larr; Back to Shop</a>

    <div class="product-showcase animate-fade-in">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="product-image-stage">
                    <?php $img = $product['image'] ? $product['image'] : 'default.jpg'; ?>
                    <img src="../assets/images/<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainImage">
                    
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
                        Freshly sourced and carefully selected. Our <?= htmlspecialchars(strtolower($product['name']), ENT_QUOTES, 'UTF-8') ?> is perfect for your daily needs, guaranteeing quality and taste in every bite.
                    </p>

                    <ul class="product-features">
                        <li><i class="bi bi-check2"></i> 100% Organic & Sustainably Sourced</li>
                        <li><i class="bi bi-check2"></i> Quality Checked for Freshness</li>
                        <li><i class="bi bi-check2"></i> Available for Express Delivery</li>
                    </ul>

                    <?php if ($product['stock_qty'] > 0): ?>
                        <form action="../cart/add" method="POST" id="addToCartForm" class="mt-4">
                            <?= csrf_input() ?>
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
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (window.FreshToast) {
                            FreshToast.success(<?= json_encode($review_msg) ?>, 'Feedback Received');
                        }
                    });
                </script>
            <?php endif; ?>

            <div class="review-form-card">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <h5 class="mb-3">Share your experience</h5>
                    <form method="POST">
                        <?= csrf_input() ?>
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
                        <a href="<?= $rootPath ?>login" class="btn btn-outline-dark rounded-pill px-4">Login to Review</a>
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
                    
                    <a href="../product/<?= slugify($rp['name']) ?>" class="text-decoration-none text-dark">
                        <div class="mini-product-card">
                            <div class="mini-img-box">
                                <img src="../assets/images/<?= $rImg ?>" alt="<?= htmlspecialchars($rp['name']) ?>">
                            </div>
                            <div class="mini-details">
                                <span class="badge bg-light text-secondary border mb-1" style="font-size: 0.6rem;"><?= htmlspecialchars($rp['category'], ENT_QUOTES, 'UTF-8') ?></span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const origHtml = submitBtn ? submitBtn.innerHTML : '';
            const formData = new FormData(this);
            if (!formData.has('csrf_token') || !formData.get('csrf_token')) {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta && csrfMeta.content) {
                    formData.append('csrf_token', csrfMeta.content);
                }
            }
            const csrfToken = formData.get('csrf_token') || document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('../cart/add.php', {
                method: 'POST',
                headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const badge = document.getElementById('cart-badge');
                    if (badge) badge.innerText = data.cart_count;
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="bi bi-check2 me-1"></i> Added to Cart';
                        submitBtn.classList.add('btn-success');
                        setTimeout(() => {
                            submitBtn.innerHTML = origHtml;
                            submitBtn.classList.remove('btn-success');
                        }, 1800);
                    }
                } else if (data.status === 'login_required') {
                    window.location.href = '../auth/login.php';
                } else {
                    if (window.FreshToast) {
                        FreshToast.error(data.message || 'Could not add product to cart.', 'Basket Notice');
                    } else {
                        alert(data.message);
                    }
                }
            });
        });
    </script>
    <script src="../assets/js/toast.js?v=<?= time() ?>"></script>
</body>
</html>
