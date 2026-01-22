<?php
// api/v1/products/index.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';

// 1. Get Params
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// 2. Build Query
$sqlWhere = "WHERE 1=1";
$params = [];

if ($search) {
    $sqlWhere .= " AND name LIKE ?";
    $params[] = "%$search%";
}
if ($category && $category !== 'All') {
    $sqlWhere .= " AND category = ?";
    $params[] = $category;
}

try {
    // Count
    $countSql = "SELECT COUNT(*) FROM products $sqlWhere";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalItems = $stmtCount->fetchColumn();
    $totalPages = ceil($totalItems / $limit);

    // Fetch
    $sql = "SELECT id, name, category, price, stock_qty, image FROM products $sqlWhere";

    // Sorting
    if ($sort === 'price_asc')
        $sql .= " ORDER BY price ASC";
    elseif ($sort === 'price_desc')
        $sql .= " ORDER BY price DESC";
    elseif ($sort === 'alpha')
        $sql .= " ORDER BY name ASC";
    else
        $sql .= " ORDER BY id DESC";

    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Add full image URL if needed
    foreach ($products as &$p) {
        $img = $p['image'] ?: 'default.jpg';
        // Assuming strict relative path for now, frontend handles base URL
        $p['image_url'] = 'assets/images/' . $img;
    }

    Response::success([
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'limit' => $limit
        ],
        'products' => $products
    ]);

} catch (Exception $e) {
    Response::error("Database Error: " . $e->getMessage(), 500);
}
?>