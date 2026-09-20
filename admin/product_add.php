<?php
// admin/product_add.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$error = '';

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
                mkdir($target_dir, 0777, true);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
</head>
<body style="background-color: var(--color-canvas, #F7F6F2); color: #0f172a; min-height: 100vh;">
    
    <div class="container py-4" style="max-width: 920px;">
        
        <!-- Navigation Header -->
        <div class="mb-4">
            <a href="index.php?view=products" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Back to Inventory
            </a>
        </div>

        <!-- View Header -->
        <div class="admin-view-header mb-4">
            <div>
                <span class="admin-kicker">Inventory Management</span>
                <h1 class="admin-view-title mb-1">Add New Product</h1>
                <p class="admin-view-subtitle mb-0">Publish a new produce item to the storefront grocery catalog.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Main Form Card -->
        <div class="admin-card p-4">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div class="row g-4">
                    <!-- Left Col: Product Image Upload -->
                    <div class="col-lg-5">
                        <label class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Photography</label>
                        <div class="border rounded p-3 text-center bg-light position-relative" id="uploadDropzone" style="min-height: 240px; cursor: pointer; border-style: dashed !important; border-color: #cbd5e1 !important;">
                            <div id="uploadPlaceholder" class="py-4">
                                <i class="bi bi-cloud-arrow-up fs-1 text-muted d-block mb-2"></i>
                                <div class="fw-semibold text-dark small mb-1">Click to select photo</div>
                                <div class="text-muted" style="font-size: 0.75rem;">JPG, PNG, or WEBP (Max 2MB)</div>
                            </div>
                            <div id="imagePreviewContainer" class="d-none"></div>
                            <input type="file" name="image" id="imageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" accept="image/*" style="cursor: pointer;">
                        </div>
                        <div class="form-text text-muted mt-2" style="font-size: 0.75rem;">
                            High-contrast real produce photography is recommended.
                        </div>
                    </div>

                    <!-- Right Col: Product Information -->
                    <div class="col-lg-7">
                        <div class="mb-3">
                            <label for="prodName" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Title</label>
                            <input type="text" id="prodName" name="name" class="form-control" placeholder="e.g. Organic Hass Avocados" required>
                        </div>

                        <div class="mb-3">
                            <label for="prodCategory" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Aisle / Category</label>
                            <select id="prodCategory" name="category" class="form-select" required>
                                <option value="" disabled selected>Select Category...</option>
                                <option value="Fruits">Fruits</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Bakery">Bakery</option>
                                <option value="Beverages">Beverages</option>
                                <option value="General">General</option>
                                <option value="Pantry">Pantry</option>
                                <option value="Meat">Meat</option>
                                <option value="Snacks">Snacks</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="prodPrice" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Unit Retail Price ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted">$</span>
                                    <input type="number" id="prodPrice" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="prodStock" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Initial Stock Units</label>
                                <input type="number" id="prodStock" name="stock_qty" class="form-control" placeholder="100" required>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 border-top pt-3">
                            <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                                Publish Product
                            </button>
                            <a href="index.php?view=products" class="btn btn-outline-secondary px-3 py-2">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

    </div>

<script>
    const imageInput = document.getElementById('imageInput');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');

    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreviewContainer.innerHTML = '<img src="' + e.target.result + '" alt="preview" style="width: 100%; height: 200px; object-fit: cover; border-radius: 6px;">';
                imagePreviewContainer.classList.remove('d-none');
                uploadPlaceholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>
