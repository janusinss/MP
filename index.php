<?php
include 'db.php';
session_start();

// --- FORCE LOGOUT CHECK ---
if (isset($_SESSION['user_id'])) {
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmtCheck->execute([$_SESSION['user_id']]);
    if (!$stmtCheck->fetch()) {
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

// Cart Count
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// --- PAGINATION & FILTER LOGIC ---
$limit = 12; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';

// Build Query
$sqlWhere = "WHERE 1=1";
$params = [];

if ($search) {
    $sqlWhere .= " AND name LIKE ?";
    $params[] = "%$search%";
}
if ($category) {
    $sqlWhere .= " AND category = ?";
    $params[] = $category;
}

// Count Total
$countSql = "SELECT COUNT(*) FROM products $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// Fetch Items
$sql = "SELECT * FROM products $sqlWhere";
if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} elseif ($sort === 'alpha') {
    $sql .= " ORDER BY name ASC";
} else {
    $sql .= " ORDER BY id DESC";
}

$sql .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories for Filter
$catStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>FreshCart Market | Organic & Fresh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <h3 class="m-0" style="font-family: var(--font-serif); letter-spacing: -0.05em; font-weight: 800;">
                    FreshCart<span style="color: var(--accent-color)">.</span>
                </h3>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link-custom dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 animate-fade-in">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="my_orders.php"><i class="bi bi-box-seam me-2"></i> Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="user_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a href="user_login.php" class="nav-link-custom">Login</a></li>
                        <li class="nav-item"><a href="user_register.php" class="btn btn-primary rounded-pill px-4 shadow-sm">Sign Up</a></li>
                    <?php endif; ?>

                    <li class="nav-item position-relative">
                        <a href="cart.php" class="btn btn-outline-secondary border-0 position-relative">
                            <i class="bi bi-bag" style="font-size: 1.3rem;"></i>
                            <?php if($cartCount > 0): ?>
                                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem;">
                                    <?= $cartCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-wrapper">
        <div class="container hero-content">
            <div class="row align-items-center">
                
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="welcome-badge">
                            <i class="bi bi-basket-fill"></i> 
                            <span>Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>!</span>
                        </div>
                        <h1 class="hero-display-text animate-fade-in">Restock your<br>kitchen favorites.</h1>
                        <p class="hero-lead">Your pantry looks a little empty. Let's fill it up with fresh, organic goodness delivered by tomorrow.</p>
                    <?php else: ?>
                        <span class="text-uppercase text-success fw-bold small mb-2 d-block tracking-wider"><i class="bi bi-patch-check-fill me-1"></i> Certified Organic</span>
                        <h1 class="hero-display-text animate-fade-in">Groceries,<br>simplified.</h1>
                        <p class="hero-lead">Skip the line and get farm-fresh produce delivered to your door. Quality you can taste, convenience you'll love.</p>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-3">
                        <a href="#shop" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">Start Shopping</a>
                        <a href="#categories" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Browse Aisles</a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-image-container">
                        <!-- <div class="hero-image-blob"></div> -->
                        
                        <img src="assets/images/gulay.jpg" id="dynamic-hero-img" class="hero-img-front" alt="Fresh Grocery Bag">
                        
                        <div class="floating-card card-1">
                            <i class="bi bi-star-fill text-warning"></i> 4.9 Rating
                        </div>
                        <div class="floating-card card-2">
                            <i class="bi bi-truck text-success"></i> Free Delivery
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container search-capsule-container" id="shop">
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <form method="GET" class="search-capsule">
                    
                    <div class="capsule-input-group">
                        <label class="capsule-label">Search</label>
                        <input type="text" name="search" class="capsule-input" placeholder="Apple, Milk, Bread..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="search-divider d-none d-md-block"></div>

                    <div class="capsule-input-group d-none d-md-flex">
                        <label class="capsule-label">Category</label>
                        <select name="category" class="capsule-select">
                            <option value="">All Departments</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-divider d-none d-md-block"></div>

                    <div class="capsule-input-group d-none d-md-flex">
                        <label class="capsule-label">Sort By</label>
                        <select name="sort" class="capsule-select">
                            <option value="">Newest First</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="alpha" <?= $sort === 'alpha' ? 'selected' : '' ?>>Name: A-Z</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-capsule-search">
                        <i class="bi bi-search fs-5"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-5 pt-2" id="categories">
        
        <div class="category-rail-wrapper text-center text-md-start">
            <a href="index.php" class="cat-chip <?= $category == '' ? 'active' : '' ?>">All Items</a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?category=<?= urlencode($cat) ?>" class="cat-chip <?= $category === $cat ? 'active' : '' ?>">
                    <?= $cat ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="product-card">
                            
                            <?php $imgName = $product['image'] ? $product['image'] : 'default.jpg'; ?>
                            <a href="product.php?id=<?= $product['id'] ?>" class="text-decoration-none">
                                <div class="position-relative">
                                    <img src="assets/images/<?php echo $imgName; ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php if($product['stock_qty'] < 5 && $product['stock_qty'] > 0): ?>
                                        <span class="position-absolute top-0 start-0 m-2 badge bg-warning text-dark border shadow-sm">Low Stock</span>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <div class="card-body d-flex flex-column">
                                <span class="category-badge"><?= $product['category'] ?></span>
                                <a href="product.php?id=<?= $product['id'] ?>" class="text-decoration-none text-dark">
                                    <h6 class="fw-bold mb-2 text-truncate"><?= htmlspecialchars($product['name']) ?></h6>
                                </a>

                                <div class="price-tag mb-3">$<?= number_format($product['price'], 2) ?></div>

                                <div class="mt-auto">
                                    <?php if ($product['stock_qty'] > 0): ?>
                                        <form action="add_to_cart.php" method="POST" class="add-cart-form">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <button type="submit" class="btn btn-outline-dark w-100 rounded-pill btn-sm hover-fill">
                                                Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-light w-100 rounded-pill btn-sm text-muted" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="bg-light rounded-4 p-5 border border-dashed">
                        <i class="bi bi-search display-4 text-muted mb-3 d-block"></i>
                        <h3 class="text-muted" style="font-family: var(--font-serif);">No products found.</h3>
                        <p>We couldn't find what you were looking for.</p>
                        <a href="index.php" class="btn btn-primary rounded-pill mt-2">Clear Filters</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-5 mb-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link border-0 text-dark" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item">
                        <a class="page-link border-0 <?= ($i == $page) ? 'fw-bold text-decoration-underline text-dark' : 'text-muted' ?>" 
                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link border-0 text-dark" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>

    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 style="font-family: var(--font-serif);">FreshCart<span style="color: var(--accent-color)">.</span></h5>
                    <p class="small text-muted mt-3">Delivering nature's best to your doorstep. Organic, sustainable, and fresh from local farmers.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="text-uppercase fw-bold small mb-3">Quick Links</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2">
                        <li><a href="index.php" class="text-decoration-none text-muted">Shop All</a></li>
                        <li><a href="my_orders.php" class="text-decoration-none text-muted">Order History</a></li>
                        <li><a href="profile.php" class="text-decoration-none text-muted">Account Settings</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h6 class="text-uppercase fw-bold small mb-3">Stay Updated</h6>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control border-0 bg-white" placeholder="Email address" style="border-radius: 50px 0 0 50px; padding-left: 20px;">
                        <button class="btn btn-dark" type="button" style="border-radius: 0 50px 50px 0; padding-right: 20px;">Join</button>
                    </div>
                </div>
            </div>
            <div class="text-center pt-4 border-top border-secondary-subtle mt-4">
                <small class="text-muted">&copy; 2025 FreshCart Market. Student Project.</small>
            </div>
        </div>
    </footer>

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
        // AJAX Add to Cart Logic
        document.querySelectorAll('.add-cart-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('add_to_cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('cart-badge');
                        if (badge) {
                            badge.innerText = data.cart_count;
                        } else {
                            // If badge doesn't exist yet (count was 0), reload to show it or create element
                            location.reload(); 
                        }
                        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                        toast.show();
                    } else if (data.status === 'login_required') {
                        window.location.href = 'user_login.php';
                    } else {
                        alert(data.message);
                    }
                });
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 1. INPUT YOUR IMAGES HERE
            // Make sure these files exist in your 'assets/images/' folder
            const heroImages = [
                "assets/images/gulay.jpg",       // Image 1
                "assets/images/gulay1.jpg", // Image 2
                "assets/images/gulay2.jpg",    // Image 3
                "assets/images/gulay3.jpg"   // Image 4
            ];

            let currentIndex = 0;
            const imgElement = document.getElementById('dynamic-hero-img');

            // Only run if the image element exists and we have multiple images
            if (imgElement && heroImages.length > 1) {
                
                setInterval(() => {
                    // Step A: Add class to fade out
                    imgElement.classList.add('changing');

                    // Step B: Wait 500ms (match CSS transition), then swap source
                    setTimeout(() => {
                        currentIndex = (currentIndex + 1) % heroImages.length;
                        imgElement.src = heroImages[currentIndex];

                        // Step C: Once image loads, fade back in
                        imgElement.onload = () => {
                            imgElement.classList.remove('changing');
                        };
                        
                        // Fallback: Remove class anyway after small delay if image is cached instantly
                        setTimeout(() => imgElement.classList.remove('changing'), 100);

                    }, 500); // 0.5s wait
                    
                }, 3000); // RUNS EVERY 3 SECONDS
            }
        });
    </script>
</body>
</html>