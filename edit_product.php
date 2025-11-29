<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
// FIX: Redirect to the main admin router if no ID
if (!$id) { header("Location: admin.php?view=products"); exit; }

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newStock = $_POST['stock_qty'];
    $newPrice = $_POST['price'];
    $newCat   = $_POST['category'];

    // 1. Check if a NEW image was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "assets/images/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ?, image = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newStock, $newPrice, $newCat, $new_filename, $id]);
                
                // FIX: Redirect to admin.php with success message
                header("Location: admin.php?view=products&msg=updated");
                exit;
            }
        }
    }

    // 2. If NO image uploaded
    $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStock, $newPrice, $newCat, $id]);

    // FIX: Redirect to admin.php with success message
    header("Location: admin.php?view=products&msg=updated");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body style="background-color: var(--bg-color);">

    <div class="edit-product-wrapper">
        <div class="edit-card animate-fade-in">
            
            <h2 class="edit-title">Edit Product</h2>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="edit-img-preview-box mb-0">
                    <img src="assets/images/<?= $product['image'] ?>" id="currentPreviewImage" alt="Current Image">
                    <div class="edit-img-overlay" id="previewOverlay">Current</div>
                </div>
                <p id="changeImageHelper" class="text-center text-muted small fw-bold text-uppercase mt-2 mb-4" style="display: none; letter-spacing: 0.05em;">
                    Click "Update Image" below to change selection
                </p>

                <div class="mb-4 mt-4">
                    <label class="form-label-edit">Product Name (Read-only)</label>
                    <input type="text" class="form-control form-control-edit text-muted" value="<?= htmlspecialchars($product['name']) ?>" readonly style="background: #f0f0f0;">
                </div>

                <div class="mb-4">
                    <label class="form-label-edit">Update Image</label>
                    <input type="file" name="image" id="editImageInput" class="form-control form-control-edit" accept="image/*">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label-edit">Category</label>
                        <select name="category" class="form-select form-control-edit">
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
                    <div class="col-md-6 mb-4">
                        <label class="form-label-edit">Price ($)</label>
                        <input type="number" step="0.01" name="price" class="form-control form-control-edit" value="<?= $product['price'] ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label-edit">Stock Quantity</label>
                    <input type="number" name="stock_qty" class="form-control form-control-edit" value="<?= $product['stock_qty'] ?>" required>
                </div>

                <button type="submit" class="btn-save">Save Changes</button>
                
                <a href="admin.php?view=products" class="btn-cancel-link">Cancel</a>

            </form>
        </div>
    </div>

    <script>
        // Image Preview Logic for Edit Product
        const editInput = document.getElementById('editImageInput');
        const previewImg = document.getElementById('currentPreviewImage');
        const helperText = document.getElementById('changeImageHelper');
        const overlayText = document.getElementById('previewOverlay');

        editInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Update the src of the top image instantly
                    previewImg.src = e.target.result;
                    // Show the helper text
                    helperText.style.display = 'block';
                    // Change overlay label
                    overlayText.innerText = 'New Selection';
                }
                
                reader.readAsDataURL(file);
            }
        });
    </script>

</body>
</html>