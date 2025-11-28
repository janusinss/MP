<?php
include 'db.php';

// Check if a session is already active before starting one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                        <li class="nav-item">
                            <a class="nav-link-custom d-flex align-items-center gap-2" href="profile.php">
                                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </a>
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
                        <h1 class="hero-display-text animate-fade-in">Groceries made,<br> much more easier</h1>
                        <p class="hero-lead">Skip the line and get farm-fresh produce delivered to your door. Quality you can taste, convenience you'll love.</p>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-3">
                        <a href="#shop" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">Start Shopping</a>
                        <a href="#categories" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Browse Aisles</a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-image-container">
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
                <form id="filterForm" class="search-capsule" onsubmit="return false;">
                    <div class="capsule-input-group">
                        <label class="capsule-label">Search</label>
                        <input type="text" id="searchInput" class="capsule-input" placeholder="Apple, Milk, Bread..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="search-divider d-none d-md-block"></div>
                    <div class="capsule-input-group">
                        <label class="capsule-label">Sort By</label>
                        <select id="sortSelect" class="capsule-select">
                            <option value="">Newest First</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="alpha">Name: A-Z</option>
                        </select>
                    </div>
                    <!-- <button type="button" class="btn-capsule-search" onclick="fetchData()">
                        <i class="bi bi-search fs-5"></i>
                    </button> -->
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-5 pt-2" id="categories">
        
        <div class="category-rail-wrapper text-center text-md-start" id="categoryRail">
            <button class="cat-chip active" data-category="All">All Items</button>
            <?php foreach ($categories as $cat): ?>
                <button class="cat-chip" data-category="<?= htmlspecialchars($cat) ?>">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="row g-4" id="product-grid">
            <?php 
               if (count($products) > 0) {
                   foreach ($products as $product) {
                       $imgName = $product['image'] ? $product['image'] : 'default.jpg';
                       echo '<div class="col-6 col-md-4 col-lg-3"><div class="product-card"><div class="product-thumb"><div class="card-badge-container">';
                       if($product['stock_qty'] < 5 && $product['stock_qty'] > 0) echo '<span class="badge-pill badge-stock">Low Stock</span>';
                       echo '<span class="badge-pill badge-cat">'.htmlspecialchars($product['category']).'</span></div>';
                       echo '<a href="product.php?id='.$product['id'].'"><img src="assets/images/'.$imgName.'" class="card-img-front" alt="'.htmlspecialchars($product['name']).'"></a>';
                       echo '<div class="card-action-overlay">';
                       if ($product['stock_qty'] > 0) {
                           echo '<form action="add_to_cart.php" method="POST" class="add-cart-form"><input type="hidden" name="product_id" value="'.$product['id'].'"><button type="submit" class="btn-quick-add"><i class="bi bi-plus-lg fs-5"></i><span>Add</span></button></form>';
                       } else {
                           echo '<button class="btn-quick-add" disabled style="opacity:0.5"><i class="bi bi-x-lg"></i></button>';
                       }
                       echo '</div></div><div class="product-details"><a href="product.php?id='.$product['id'].'" class="product-title-link text-truncate">'.htmlspecialchars($product['name']).'</a><div class="d-flex align-items-center justify-content-between"><span class="product-price">$'.number_format($product['price'], 2).'</span><span class="product-unit">per unit</span></div></div></div></div>';
                   }
               } else {
                   echo '<div class="col-12 text-center py-5"><h3 class="text-muted">No products found.</h3></div>';
               }
            ?>
        </div>

        <div id="pagination-container" class="mt-5 mb-5">
            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-glass">
                        <a href="#" class="page-btn page-arrow <?= ($page <= 1) ? 'disabled' : '' ?>" data-page="<?= max(1, $page-1) ?>"><i class="bi bi-arrow-left"></i></a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="#" class="page-btn <?= ($i == $page) ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="#" class="page-btn page-arrow <?= ($page >= $totalPages) ? 'disabled' : '' ?>" data-page="<?= min($totalPages, $page+1) ?>"><i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <footer class="site-footer">
        <div class="container">
            <div class="row g-5">
                
                <div class="col-lg-4 col-md-6">
                    <a href="index.php" class="text-decoration-none">
                        <span class="footer-brand">FreshCart<span style="color: var(--accent-color)">.</span></span>
                    </a>
                    <p class="text-muted small lh-lg mb-4">
                        Delivering nature's best to your doorstep. We partner directly with local organic farmers to ensure freshness, sustainability, and fair trade practices.
                    </p>
                    <div class="d-flex">
                        <a href="https://www.facebook.com/notagirlgamer69" target="_blank" class="social-icon-link"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/janusinss/" target="_blank" class="social-icon-link"><i class="bi bi-instagram"></i></a>
                        <a href="https://x.com/Syrupynut" target="_blank" class="social-icon-link"><i class="bi bi-twitter"></i></a>
                        <a href="https://www.linkedin.com/in/janusdominic/" target="_blank" class="social-icon-link"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="footer-heading">Shop</h6>
                    <ul class="list-unstyled footer-link-list">
                        <li><a href="#shop" class="footer-link">All Products</a></li>
                        <li><a href="#categories" class="footer-link">Fresh Produce</a></li>
                        <li><a href="#categories" class="footer-link">Dairy & Eggs</a></li>
                        <li><a href="#categories" class="footer-link">Bakery</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="footer-heading">Company</h6>
                    <ul class="list-unstyled footer-link-list">
                        <li><a href="about.php" class="footer-link">About Us</a></li>
                        <li><a href="sustainability.php" class="footer-link">Sustainability</a></li>
                        <li><a href="farmers.php" class="footer-link">Farmers</a></li>
                        <li><a href="contact.php" class="footer-link">Contact</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <h6 class="footer-heading">Stay Fresh</h6>
                    <p class="small text-muted mb-3">Join our newsletter for exclusive organic deals and recipes.</p>
                    
                    <form action="#">
                        <div class="footer-newsletter-group">
                            <input type="email" class="footer-email-input" placeholder="Your email address">
                            <button class="footer-subscribe-btn" type="button">Join</button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <p class="small text-muted mb-2">Secure Payment</p>
                        <div class="d-flex flex-wrap">
                            <span class="payment-badge">VISA</span>
                            <span class="payment-badge">MasterCard</span>
                            <span class="payment-badge">PayPal</span>
                            <span class="payment-badge">GCash</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="border-top mt-5 pt-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
                <small class="text-muted mb-2 mb-md-0">&copy; 2025 FreshCart Market. Student Project by <span style="color: var(--accent-color); text-transform: uppercase; font-weight: bold">Janus Dominic</span>.</small>
                <div class="small text-muted">
                    <a href="privacy_policy.php" class="text-decoration-none text-muted fw-bold me-3">Privacy Policy</a>
                    <a href="terms_of_service.php" class="text-decoration-none text-muted fw-bold">Terms of Service</a>
                </div>
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
        document.addEventListener("DOMContentLoaded", function() {
            // IMAGES: Make sure these exist in 'assets/images/'
            const heroImages = [
                "assets/images/gulay.jpg",       
                "assets/images/gulay1.jpg", 
                "assets/images/gulay2.jpg",    
                "assets/images/gulay3.jpg"   
            ];

            let currentIndex = 0;
            const imgElement = document.getElementById('dynamic-hero-img');

            if (imgElement && heroImages.length > 1) {
                setInterval(() => {
                    imgElement.classList.add('changing');
                    setTimeout(() => {
                        currentIndex = (currentIndex + 1) % heroImages.length;
                        imgElement.src = heroImages[currentIndex];
                        imgElement.onload = () => { imgElement.classList.remove('changing'); };
                        setTimeout(() => imgElement.classList.remove('changing'), 100);
                    }, 500); 
                }, 3000); 
            }
        });
    </script>

    <script>
        let currentCategory = '<?= $category ?: "All" ?>';
        let currentSearch = '<?= $search ?>';
        let currentSort = '<?= $sort ?>';
        let currentPage = 1;

        document.addEventListener("DOMContentLoaded", () => {
            // Category Chips Click
            const catButtons = document.querySelectorAll('#categoryRail .cat-chip');
            catButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    catButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentCategory = btn.getAttribute('data-category');
                    currentPage = 1; 
                    fetchData();
                });
            });

            // Search Input
            const searchInput = document.getElementById('searchInput');
            let timeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    currentSearch = this.value;
                    currentPage = 1;
                    fetchData();
                }, 500); 
            });

            // Sort Select
            const sortSelect = document.getElementById('sortSelect');
            sortSelect.addEventListener('change', function() {
                currentSort = this.value;
                fetchData();
            });
        });

        function fetchData() {
            const grid = document.getElementById('product-grid');
            const pagination = document.getElementById('pagination-container');
            grid.style.opacity = '0.5';

            const params = new URLSearchParams({
                category: currentCategory,
                search: currentSearch,
                sort: currentSort,
                page: currentPage
            });

            fetch('fetch_products.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    grid.innerHTML = data.grid;
                    pagination.innerHTML = data.pagination;
                    attachPaginationListeners();
                    grid.style.opacity = '1';
                })
                .catch(error => console.error('Error:', error));
        }

        function attachPaginationListeners() {
            const pageBtns = document.querySelectorAll('.page-btn');
            pageBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if(btn.classList.contains('disabled')) return;
                    const newPage = btn.getAttribute('data-page');
                    if(newPage) {
                        currentPage = newPage;
                        fetchData();
                        document.getElementById('categories').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            currentSearch = '';
            currentCategory = 'All';
            document.querySelectorAll('.cat-chip').forEach(b => b.classList.remove('active'));
            document.querySelector('.cat-chip[data-category="All"]').classList.add('active');
            fetchData();
        }

        attachPaginationListeners();
    </script>

    <script>
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('add-cart-form')) {
                e.preventDefault();
                const formData = new FormData(e.target);
                fetch('add_to_cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('cart-badge');
                        if (badge) badge.innerText = data.cart_count;
                        else location.reload();
                        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                        toast.show();
                    } else if (data.status === 'login_required') {
                        window.location.href = 'user_login.php';
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    </script>

</body>
</html>