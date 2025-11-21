<?php
include 'db.php';
session_start();

// Calculate current cart count
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// --- PAGINATION & FILTER LOGIC ---
$limit = 12; // Items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get Filter Inputs
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';

// 1. Build Query Conditions
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

// 2. Count TOTAL items (for pagination numbers)
$countSql = "SELECT COUNT(*) FROM products $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// 3. Fetch ITEMS for current page
$sql = "SELECT * FROM products $sqlWhere";

// Add Sorting
if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} elseif ($sort === 'alpha') {
    $sql .= " ORDER BY name ASC";
} else {
    $sql .= " ORDER BY id DESC"; // Default new items first
}

// Add Limit/Offset
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique categories for dropdown
$catStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>FreshCart Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>🛒 FreshCart Market</h1>
        
        <div class="d-flex align-items-center gap-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        👤 Hi, <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php">👤 Edit Profile</a></li>
                        <li><a class="dropdown-item" href="my_orders.php">📦 My Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="user_logout.php">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <div>
                    <a href="user_login.php" class="btn btn-outline-primary btn-sm">Login</a>
                    <a href="user_register.php" class="btn btn-primary btn-sm">Register</a>
                </div>
            <?php endif; ?>

            <a href="cart.php" class="btn btn-success position-relative">
                <i class="bi bi-cart-fill"></i> Cart
                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $cartCount ?>
                </span>
            </a>
        </div>
    </div>
    
    <div class="card p-3 mb-4 shadow-sm bg-light">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                    <option value="" disabled <?= empty($sort) ? 'selected' : '' ?>>Sort by...</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="alpha" <?= $sort === 'alpha' ? 'selected' : '' ?>>Name: A-Z</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <a href="index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    
    <div class="row">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $product): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        
                        <?php $imgName = $product['image'] ?? 'default.jpg'; ?>
                        <a href="product.php?id=<?= $product['id'] ?>" class="text-decoration-none text-dark">
                            <div class="position-relative">
                                <img src="assets/images/<?php echo $imgName; ?>" class="card-img-top" alt="img" style="height: 200px; object-fit: cover;">
                                <span class="position-absolute top-0 start-0 badge bg-info text-dark m-2"><?= $product['category'] ?></span>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title text-truncate"><?= htmlspecialchars($product['name']); ?></h5>
                                
                                <?php
                                    $stmtRating = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE product_id = ?");
                                    $stmtRating->execute([$product['id']]);
                                    $avgRating = round($stmtRating->fetchColumn(), 1);
                                ?>
                                <div class="mb-2">
                                    <?php if($avgRating > 0): ?>
                                        <span class="text-warning">
                                            <?php 
                                            for($i=1; $i<=5; $i++) {
                                                if($i <= $avgRating) echo '<i class="bi bi-star-fill"></i>';
                                                elseif($i - 0.5 <= $avgRating) echo '<i class="bi bi-star-half"></i>';
                                                else echo '<i class="bi bi-star"></i>';
                                            } 
                                            ?>
                                        </span>
                                        <span class="text-muted small">(<?= $avgRating ?>)</span>
                                    <?php else: ?>
                                        <span class="text-muted small">No reviews yet</span>
                                    <?php endif; ?>
                                </div>
                        </a>
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-auto">
                            <span class="fw-bold fs-5">$<?php echo number_format($product['price'], 2); ?></span>
                            <?php 
                            $stock = $product['stock_qty'];
                            if ($stock == 0) echo '<span class="badge bg-danger">Out of Stock</span>';
                            elseif ($stock < 5) echo '<span class="text-danger fw-bold small">Only ' . $stock . ' left!</span>';
                            else echo '<span class="text-muted small">Stock: ' . $stock . '</span>';
                            ?>
                        </div>
                        
                        <?php if ($stock > 0): ?>
                            <form action="add_to_cart.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-outline-primary w-100">Add to Cart</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>Out of Stock</button>
                        <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5"><h3 class="text-muted">No products found.</h3></div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php 
                $queryParams = $_GET; 
                $queryParams['page'] = $page - 1;
                $prevLink = '?' . http_build_query($queryParams);
                
                $queryParams['page'] = $page + 1;
                $nextLink = '?' . http_build_query($queryParams);
            ?>

            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $prevLink ?>">Previous</a>
            </li>

            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <?php 
                    $queryParams['page'] = $i;
                    $link = '?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $link ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $nextLink ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle-fill"></i> Item added to cart!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('form[action="add_to_cart.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('add_to_cart.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('cart-badge');
                        badge.innerText = data.cart_count;
                        badge.style.animation = 'none';
                        badge.offsetHeight;
                        badge.style.animation = 'popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
                        toast.show();
                    } 
                    else if (data.status === 'login_required') {
                        alert("Log in to add to cart"); 
                        window.location.href = 'user_login.php';
                    }
                });
            });
        });
    </script>

</body>
</html>