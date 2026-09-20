<?php
// admin/product_edit.php
// Catalog Product & Inventory Management Editor
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Administrator role enforcement
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$adminName = $_SESSION['user_name'] ?? 'Administrator';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { 
    header("Location: index.php?view=products"); 
    exit; 
}

// Fetch available product record
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: index.php?view=products");
    exit;
}

// Query categories from database catalog
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($categories)) {
    $categories = ['Bakery', 'Beverages', 'Dairy', 'Fruits', 'General', 'Meat', 'Pantry', 'Snacks', 'Vegetables'];
}
if (!in_array($product['category'], $categories, true)) {
    $categories[] = $product['category'];
    sort($categories);
}

$error = '';

// Handle Update POST Request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Invalid CSRF token.");
    }
    
    $newStock = (int)($_POST['stock_qty'] ?? 0);
    $newPrice = (float)($_POST['price'] ?? 0);
    $newCat   = trim($_POST['category'] ?? 'General');

    if ($newPrice < 0) {
        $error = "Unit price cannot be negative.";
    } elseif ($newStock < 0) {
        $error = "Stock quantity cannot be negative.";
    } else {
        // 1. Check if a NEW image was uploaded
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = __DIR__ . "/../assets/images/";
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES["image"]["tmp_name"]);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($_FILES["image"]["tmp_name"]);
            }

            if ($check !== false && in_array($file_extension, $allowed_types, true) && in_array($mime, $allowed_mimes, true)) {
                $new_filename = uniqid('prod_') . "." . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ?, image = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$newStock, $newPrice, $newCat, $new_filename, $id]);
                    
                    header("Location: index.php?view=products&msg=updated");
                    exit;
                }
            } else {
                $error = "Only valid JPG, JPEG, PNG, WEBP & GIF image files are allowed.";
            }
        }

        if (empty($error)) {
            // 2. If NO image uploaded or valid update
            $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newStock, $newPrice, $newCat, $id]);

            header("Location: index.php?view=products&msg=updated");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?> - FreshCart Admin Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
</head>
<body class="admin-body">

    <!-- Mobile Admin Topbar -->
    <header class="admin-mobile-topbar d-lg-none">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSidebar()" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="fw-bold font-serif fs-5 text-dark">
            FreshCart<span class="text-success">.</span> <span class="text-muted fw-normal fs-6">Admin</span>
        </div>
        <a href="../" class="btn btn-sm btn-outline-secondary" title="View Storefront">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </header>

    <!-- Mobile Sidebar Backdrop -->
    <div class="admin-sidebar-backdrop d-lg-none" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-brand-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="admin-sidebar-title">FreshCart<span class="text-success">.</span></div>
                        <div class="admin-sidebar-subtitle">Operations Console</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light border-0 d-lg-none" onclick="toggleSidebar()" aria-label="Close menu">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            
            <nav class="nav flex-column gap-1 flex-grow-1 mt-3">
                <a href="index.php?view=dashboard" class="admin-nav-link">
                    <i class="bi bi-graph-up-arrow"></i> <span>Analytics</span>
                </a>
                <a href="index.php?view=orders" class="admin-nav-link">
                    <i class="bi bi-receipt-cutoff"></i> <span>Orders</span>
                </a>
                <a href="index.php?view=products" class="admin-nav-link active">
                    <i class="bi bi-box-seam"></i> <span>Inventory</span>
                </a>
                <a href="index.php?view=users" class="admin-nav-link">
                    <i class="bi bi-people"></i> <span>Customers</span>
                </a>
                <a href="index.php?view=reviews" class="admin-nav-link">
                    <i class="bi bi-star"></i> <span>Reviews</span>
                </a>
            </nav>

            <!-- Admin Profile Footer -->
            <div class="admin-sidebar-footer">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="admin-avatar-initial">
                        <?= strtoupper(substr($adminName, 0, 1)) ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="admin-user-name text-truncate"><?= htmlspecialchars($adminName) ?></div>
                        <div class="admin-user-role">System Administrator</div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="../" class="btn btn-sm btn-outline-secondary w-50 d-flex align-items-center justify-content-center gap-1" title="View Storefront">
                        <i class="bi bi-shop"></i> <span>Store</span>
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger w-50 d-flex align-items-center justify-content-center gap-1" title="Sign out">
                        <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Dynamic Main Content Area -->
        <main class="admin-main" id="mainContent" role="main">
            
            <!-- Breadcrumb Navigation Bar -->
            <div class="mb-3 d-flex align-items-center gap-2">
                <a href="index.php?view=products" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Back to Inventory
                </a>
                <span class="text-muted small">/</span>
                <span class="text-secondary small fw-medium">Edit Product #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></span>
            </div>

            <!-- View Header -->
            <div class="admin-view-header mb-4">
                <div>
                    <span class="admin-kicker">Catalog &amp; Stock Operations</span>
                    <h1 class="admin-view-title mb-1">Edit Product #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></h1>
                    <p class="admin-view-subtitle mb-0">Update retail pricing, warehouse on-hand stock units, aisle placement, and catalog photography.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Main Edit Bento Card -->
            <div class="admin-card p-4">
                <form method="POST" enctype="multipart/form-data" id="editProductForm">
                    <?= csrf_input() ?>

                    <div class="row g-4">
                        <!-- Left Column: Current Image Preview & Replacement -->
                        <div class="col-lg-5">
                            <div class="d-flex flex-column h-100">
                                <label for="editImageInput" class="form-label text-dark fw-bold small text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Product Photography
                                </label>
                                
                                <div class="border rounded-3 p-3 text-center bg-light mb-3 position-relative" style="border-color: #e2e8f0 !important;">
                                    <img src="../assets/images/<?= htmlspecialchars($product['image'] ?: 'default.jpg') ?>" id="currentPreviewImage" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid rounded-2 shadow-sm" style="width: 100%; max-height: 220px; object-fit: cover; border: 1px solid #e2e8f0;">
                                    
                                    <div class="mt-2 text-muted small d-flex align-items-center justify-content-center gap-1" id="previewBadge">
                                        <i class="bi bi-image text-muted"></i>
                                        <span>Current catalog asset: <code><?= htmlspecialchars($product['image'] ?: 'default.jpg') ?></code></span>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label for="editImageInput" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                        Replace Photo (Optional)
                                    </label>
                                    <input type="file" name="image" id="editImageInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif" style="min-height: 40px;" aria-label="Replace product photo">
                                    <div class="form-text text-muted mt-1" style="font-size: 0.75rem;">
                                        JPG, PNG, or WEBP up to 2MB. Leave empty to keep existing photo.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Product Specifications -->
                        <div class="col-lg-7">
                            <!-- Product Title (SKU Protected) -->
                            <div class="mb-3">
                                <label class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Product Title (SKU Protected)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light text-secondary border-start-0" value="<?= htmlspecialchars($product['name']) ?>" readonly style="font-size: 0.95rem; min-height: 44px;" aria-label="Product title">
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.75rem;">
                                    Title is locked to preserve historical consistency across existing customer receipts and manifests.
                                </div>
                            </div>

                            <!-- Category / Aisle -->
                            <div class="mb-3">
                                <label for="editCategory" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Aisle / Category <span class="text-danger">*</span>
                                </label>
                                <select id="editCategory" name="category" class="form-select" required style="font-size: 0.92rem; min-height: 44px;">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($product['category'] === $cat) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Pricing & On-Hand Stock Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <label for="editPrice" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                        Retail Price ($) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted fw-semibold">$</span>
                                        <input type="number" id="editPrice" step="0.01" min="0" name="price" class="form-control" value="<?= htmlspecialchars($product['price']) ?>" required style="font-size: 0.95rem; min-height: 44px;">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="editStock" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                        On-Hand Stock Units <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" id="editStock" min="0" name="stock_qty" class="form-control" value="<?= (int)$product['stock_qty'] ?>" required style="font-size: 0.95rem; min-height: 44px;">
                                        <span class="input-group-text bg-light text-muted small">units</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex flex-wrap align-items-center gap-2 border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-dark px-4 fw-semibold d-inline-flex align-items-center gap-2" style="min-height: 44px;">
                                    <i class="bi bi-save2"></i>
                                    <span>Save Changes</span>
                                </button>
                                <a href="index.php?view=products" class="btn btn-outline-secondary px-3 d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </main>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        const editInput = document.getElementById('editImageInput');
        const previewImg = document.getElementById('currentPreviewImage');
        const previewBadge = document.getElementById('previewBadge');

        if (editInput && previewImg && previewBadge) {
            editInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewBadge.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check2"></i> New image selected (' + (file.size / 1024).toFixed(0) + ' KB)</span>';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
