<?php
include 'db.php';
session_start();

// 1. Get Filters from the AJAX request
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; 
$offset = ($page - 1) * $limit;

// 2. Build Query
$sqlWhere = "WHERE 1=1";
$params = [];

if ($search) {
    $sqlWhere .= " AND name LIKE ?";
    $params[] = "%$search%";
}
if ($category && $category !== 'All') { // 'All' handles the reset
    $sqlWhere .= " AND category = ?";
    $params[] = $category;
}

// 3. Count Total (For Pagination)
$countSql = "SELECT COUNT(*) FROM products $sqlWhere";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $limit);

// 4. Fetch Products
$sql = "SELECT * FROM products $sqlWhere";

// Sorting
if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} elseif ($sort === 'alpha') {
    $sql .= " ORDER BY name ASC";
} else {
    $sql .= " ORDER BY id DESC"; // Default
}

$sql .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Generate HTML Response
// We return a JSON object containing the Grid HTML and the Pagination HTML
$response = [];

// -- Part A: Product Grid HTML --
ob_start();
if (count($products) > 0) {
    foreach ($products as $product) {
        $imgName = $product['image'] ? $product['image'] : 'default.jpg';
        // Reuse your exact Card Design
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card">
                <div class="product-thumb">
                    <div class="card-badge-container">
                        <?php if($product['stock_qty'] < 5 && $product['stock_qty'] > 0): ?>
                            <span class="badge-pill badge-stock">Low Stock</span>
                        <?php endif; ?>
                        <span class="badge-pill badge-cat"><?= htmlspecialchars($product['category']) ?></span>
                    </div>
                    <a href="product.php?id=<?= $product['id'] ?>">
                        <img src="assets/images/<?= $imgName ?>" class="card-img-front" alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>
                    <div class="card-action-overlay">
                        <?php if ($product['stock_qty'] > 0): ?>
                            <form action="add_to_cart.php" method="POST" class="add-cart-form">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" class="btn-quick-add" title="Add to Cart">
                                    <i class="bi bi-plus-lg fs-5"></i>
                                    <span>Add</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn-quick-add" disabled style="opacity: 0.5; cursor: not-allowed;">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-details">
                    <a href="product.php?id=<?= $product['id'] ?>" class="product-title-link text-truncate">
                        <?= htmlspecialchars($product['name']) ?>
                    </a>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="product-price">$<?= number_format($product['price'], 2) ?></span>
                        <span class="product-unit">per unit</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    ?>
    <div class="col-12 text-center py-5">
        <div class="bg-light rounded-4 p-5 border border-dashed">
            <i class="bi bi-search display-4 text-muted mb-3 d-block"></i>
            <h3 class="text-muted" style="font-family: var(--font-serif);">No products found.</h3>
            <p>We couldn't find what you were looking for.</p>
            <button onclick="resetFilters()" class="btn btn-primary rounded-pill mt-2">Clear Filters</button>
        </div>
    </div>
    <?php
}
$response['grid'] = ob_get_clean();

// -- Part B: Pagination HTML --
ob_start();
if ($totalPages > 1) {
    ?>
    <div class="pagination-wrapper">
        <div class="pagination-glass">
            <a href="#" class="page-btn page-arrow <?= ($page <= 1) ? 'disabled' : '' ?>" data-page="<?= $page - 1 ?>">
                <i class="bi bi-arrow-left"></i>
            </a>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="#" class="page-btn <?= ($i == $page) ? 'active' : '' ?>" data-page="<?= $i ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <a href="#" class="page-btn page-arrow <?= ($page >= $totalPages) ? 'disabled' : '' ?>" data-page="<?= $page + 1 ?>">
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php
}
$response['pagination'] = ob_get_clean();

// Output JSON
header('Content-Type: application/json');
echo json_encode($response);
?>