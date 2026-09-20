<?php
// admin/product_edit.php
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { 
    header("Location: index.php?view=products"); 
    exit; 
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Invalid CSRF token.");
    }
    
    $newStock = (int)($_POST['stock_qty'] ?? 0);
    $newPrice = (float)($_POST['price'] ?? 0);
    $newCat   = trim($_POST['category'] ?? 'General');

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
        }
    }

    // 2. If NO image uploaded
    $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStock, $newPrice, $newCat, $id]);

    header("Location: index.php?view=products&msg=updated");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product #<?= $id ?> | Admin Portal</title>
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
                <h1 class="admin-view-title mb-1">Edit Product #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></h1>
                <p class="admin-view-subtitle mb-0">Update item pricing, warehouse stock levels, and catalog media.</p>
            </div>
        </div>

        <!-- Main Edit Card -->
        <div class="admin-card p-4">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div class="row g-4">
                    <!-- Left Col: Current Image Preview & Replacement -->
                    <div class="col-lg-5">
                        <label class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Photography</label>
                        <div class="border rounded p-3 text-center bg-light mb-3">
                            <img src="../assets/images/<?= htmlspecialchars($product['image'] ?: 'default.jpg') ?>" id="currentPreviewImage" alt="Current Image" style="width: 100%; height: 200px; object-fit: cover; border-radius: 6px;">
                            <div class="mt-2 text-muted small" id="previewBadge">
                                Current Catalog Image
                            </div>
                        </div>

                        <div>
                            <label for="editImageInput" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Replace Photo (Optional)</label>
                            <input type="file" name="image" id="editImageInput" class="form-control form-control-sm" accept="image/*">
                        </div>
                    </div>

                    <!-- Right Col: Product Information -->
                    <div class="col-lg-7">
                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Product Title (SKU Protected)</label>
                            <input type="text" class="form-control bg-light text-secondary" value="<?= htmlspecialchars($product['name']) ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="editCategory" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Aisle / Category</label>
                            <select id="editCategory" name="category" class="form-select">
                                <option value="Fruits" <?= $product['category'] == 'Fruits' ? 'selected' : '' ?>>Fruits</option>
                                <option value="Dairy" <?= $product['category'] == 'Dairy' ? 'selected' : '' ?>>Dairy</option>
                                <option value="Bakery" <?= $product['category'] == 'Bakery' ? 'selected' : '' ?>>Bakery</option>
                                <option value="Beverages" <?= $product['category'] == 'Beverages' ? 'selected' : '' ?>>Beverages</option>
                                <option value="General" <?= $product['category'] == 'General' ? 'selected' : '' ?>>General</option>
                                <option value="Pantry" <?= $product['category'] == 'Pantry' ? 'selected' : '' ?>>Pantry</option>
                                <option value="Meat" <?= $product['category'] == 'Meat' ? 'selected' : '' ?>>Meat</option>
                                <option value="Snacks" <?= $product['category'] == 'Snacks' ? 'selected' : '' ?>>Snacks</option>
                                <option value="Vegetables" <?= $product['category'] == 'Vegetables' ? 'selected' : '' ?>>Vegetables</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="editPrice" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">Retail Price ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted">$</span>
                                    <input type="number" id="editPrice" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="editStock" class="form-label text-muted small text-uppercase fw-bold" style="font-size: 0.72rem;">On-Hand Stock Units</label>
                                <input type="number" id="editStock" name="stock_qty" class="form-control" value="<?= $product['stock_qty'] ?>" required>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 border-top pt-3">
                            <button type="submit" class="btn btn-dark px-4 py-2 fw-semibold">
                                Save Changes
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
    const editInput = document.getElementById('editImageInput');
    const previewImg = document.getElementById('currentPreviewImage');
    const previewBadge = document.getElementById('previewBadge');

    editInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewBadge.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check2"></i> New image selected</span>';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>
