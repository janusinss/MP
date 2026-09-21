<?php
// admin/product_add.php
// New Produce & Catalog Item Publication Portal
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Administrator session enforcement
$rootPath = function_exists('get_app_root') ? get_app_root() : '/';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: " . $rootPath . "login");
    exit;
}

$adminName = $_SESSION['user_name'] ?? 'Administrator';
$error = '';

// Query active categories from database catalog
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($categories)) {
    $categories = ['Bakery', 'Beverages', 'Dairy', 'Fruits', 'General', 'Meat', 'Pantry', 'Snacks', 'Vegetables'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Invalid CSRF token.");
    }

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock_qty'] ?? 0);
    $image = "default.jpg";

    if (empty($name)) {
        $error = "Product title is required.";
    } elseif ($price < 0) {
        $error = "Retail unit price cannot be negative.";
    } elseif ($stock < 0) {
        $error = "Stock quantity cannot be negative.";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
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

            if ($check === false || !in_array($file_extension, $allowed_types, true) || !in_array($mime, $allowed_mimes, true)) {
                $error = "Only valid JPG, JPEG, PNG, WEBP & GIF image files are allowed.";
            } else {
                $target_dir = __DIR__ . "/../assets/images/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                $new_filename = uniqid('prod_') . "." . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image = $new_filename;
                }
            }
        }

        if (empty($error)) {
            $sql = "INSERT INTO products (name, category, price, stock_qty, image) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $category, $price, $stock, $image]);

            header("Location: index.php?view=products&msg=added");
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
    <title>Add New Product - FreshCart Admin Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <script>
        // Security: Defeat Back-Forward Cache (bfcache) & History Navigation Leaks after Logout
        (function() {
            function enforceFreshAuth() {
                var navEntries = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation') : null;
                var isBackForward = (navEntries && navEntries.length > 0 && navEntries[0].type === 'back_forward') || 
                                    (window.performance && window.performance.navigation && window.performance.navigation.type === 2);
                if (isBackForward) {
                    window.location.reload();
                }
            }
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                } else {
                    enforceFreshAuth();
                }
            });
        })();
    </script>
</head>
<body class="admin-body">

    <!-- Native Mobile App Topbar (375px+ touch optimized) -->
    <header class="admin-mobile-topbar d-lg-none" role="banner">
        <div class="d-flex align-items-center gap-2">
            <span class="admin-logo-mark"><i class="bi bi-basket3-fill text-success fs-5"></i></span>
            <div class="fw-bold fs-6 text-dark" style="letter-spacing: -0.02em;">
                FreshCart<span class="text-success">.</span> <span class="badge bg-dark-subtle text-dark border ms-1" style="font-size: 0.65rem; font-weight: 700;">ADMIN</span>
            </div>
        </div>
        <a href="../" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" style="min-height: 40px; font-weight: 600; font-size: 0.8rem; padding: 0 12px;" title="View Public Storefront" aria-label="View Public Storefront">
            <i class="bi bi-shop"></i> <span>Store</span>
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
                <span class="text-secondary small fw-medium">New SKU Publication</span>
            </div>

            <!-- View Header -->
            <div class="admin-view-header mb-4">
                <div class="admin-view-header-main">
                    <span class="admin-kicker">Catalog &amp; Stock Operations</span>
                    <div class="admin-view-heading-group">
                        <h1 class="admin-view-title mb-1">Add New Product</h1>
                    </div>
                    <p class="admin-view-subtitle mb-0">Publish an item to the storefront grocery catalog with live stock tracking and photography.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Main Form Bento Card -->
            <div class="admin-card p-4">
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <?= csrf_input() ?>

                    <div class="row g-4">
                        <!-- Left Column: Product Photography -->
                        <div class="col-lg-5">
                            <div class="d-flex flex-column h-100">
                                <label for="imageInput" class="form-label text-dark fw-bold small text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Product Photography
                                </label>
                                
                                <div class="border rounded-3 p-3 text-center bg-light position-relative d-flex flex-column align-items-center justify-content-center flex-grow-1" id="uploadDropzone" style="min-height: 260px; border-style: dashed !important; border-color: #cbd5e1 !important; transition: border-color 0.2s ease, background-color 0.2s ease;">
                                    <div id="uploadPlaceholder" class="py-3">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm border mb-3" style="width: 56px; height: 56px;">
                                            <i class="bi bi-cloud-arrow-up fs-3 text-success"></i>
                                        </div>
                                        <div class="fw-bold text-dark small mb-1">Click to select photo</div>
                                        <div class="text-muted small" style="font-size: 0.78rem;">JPG, PNG, or WEBP (Max 2MB)</div>
                                    </div>
                                    <div id="imagePreviewContainer" class="d-none w-100"></div>
                                    <input type="file" name="image" id="imageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" accept="image/jpeg,image/png,image/webp,image/gif" style="cursor: pointer;" aria-label="Upload product image">
                                </div>

                                <div class="d-flex align-items-start gap-2 mt-2 pt-1 text-muted" style="font-size: 0.76rem;">
                                    <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
                                    <span>Real high-contrast photography against clean or natural backgrounds produces optimal storefront display.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Product Specifications -->
                        <div class="col-lg-7">
                            <!-- Product Title -->
                            <div class="mb-3">
                                <label for="prodName" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Product Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="prodName" name="name" class="form-control" placeholder="e.g. Organic Gala Apples (1 lb)" required style="font-size: 0.95rem; min-height: 44px;">
                            </div>

                            <!-- Category / Aisle -->
                            <div class="mb-3">
                                <label for="prodCategory" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                    Aisle / Category <span class="text-danger">*</span>
                                </label>
                                <select id="prodCategory" name="category" class="form-select" required style="font-size: 0.92rem; min-height: 44px;">
                                    <option value="" disabled selected>Select Aisle / Department...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Pricing & Initial Stock Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <label for="prodPrice" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                        Unit Retail Price ($) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted fw-semibold">$</span>
                                        <input type="number" id="prodPrice" step="0.01" min="0" name="price" class="form-control" placeholder="0.00" required style="font-size: 0.95rem; min-height: 44px;">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="prodStock" class="form-label text-dark fw-bold small text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                                        Initial Stock Units <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" id="prodStock" min="0" name="stock_qty" class="form-control" placeholder="50" required style="font-size: 0.95rem; min-height: 44px;">
                                        <span class="input-group-text bg-light text-muted small">units</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex flex-wrap align-items-center gap-2 border-top pt-3 mt-4">
                                <button type="submit" class="btn btn-success px-4 fw-semibold d-inline-flex align-items-center gap-2" style="min-height: 44px;">
                                    <i class="bi bi-check2-circle"></i>
                                    <span>Publish Product</span>
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

    <!-- Native Mobile Bottom Tab Rail (Fixed 5-Tab) -->
    <nav class="admin-mobile-bottom-nav d-lg-none" aria-label="Mobile Navigation">
        <ul class="admin-mobile-nav-grid">
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=dashboard" class="admin-mobile-nav-btn">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=orders" class="admin-mobile-nav-btn">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=products" class="admin-mobile-nav-btn active">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=users" class="admin-mobile-nav-btn">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="admin-mobile-nav-item">
                <a href="index.php?view=reviews" class="admin-mobile-nav-btn">
                    <i class="bi bi-star"></i>
                    <span>Reviews</span>
                </a>
            </li>
        </ul>
    </nav>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        const imageInput = document.getElementById('imageInput');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const dropzone = document.getElementById('uploadDropzone');

        if (imageInput && imagePreviewContainer && uploadPlaceholder) {
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreviewContainer.innerHTML = '<img src="' + e.target.result + '" alt="Catalog Preview" class="img-fluid rounded-2 shadow-sm" style="width: 100%; max-height: 220px; object-fit: cover; border: 1px solid #e2e8f0;">' +
                            '<div class="mt-2 text-success small fw-semibold"><i class="bi bi-check2"></i> Photo selected (' + (file.size / 1024).toFixed(0) + ' KB)</div>';
                        imagePreviewContainer.classList.remove('d-none');
                        uploadPlaceholder.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Drag and drop visual feedback
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.style.borderColor = '#15803d';
                    dropzone.style.backgroundColor = '#f0fdf4';
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.style.borderColor = '#cbd5e1';
                    dropzone.style.backgroundColor = '#f8fafc';
                }, false);
            });
        }
    </script>
</body>
</html>
