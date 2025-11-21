<?php
include 'db.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: admin_products.php"); exit; }

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
                // Update Database WITH new image
                $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ?, image = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newStock, $newPrice, $newCat, $new_filename, $id]);
                
                header("Location: admin_products.php");
                exit;
            }
        }
    }

    // 2. If NO image uploaded, just update the text fields (Keep old image)
    $sql = "UPDATE products SET stock_qty = ?, price = ?, category = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStock, $newPrice, $newCat, $id]);

    header("Location: admin_products.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit: <?= htmlspecialchars($product['name']) ?></h4>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-3 text-center">
                            <label class="form-label d-block">Current Image</label>
                            <img src="assets/images/<?= $product['image'] ?>" alt="Current Image" 
                                 style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; border-radius: 5px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Change Image (Optional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="Fruits" <?= $product['category'] == 'Fruits' ? 'selected' : '' ?>>Fruits</option>
                                <option value="Dairy" <?= $product['category'] == 'Dairy' ? 'selected' : '' ?>>Dairy</option>
                                <option value="Bakery" <?= $product['category'] == 'Bakery' ? 'selected' : '' ?>>Bakery</option>
                                <option value="Beverages" <?= $product['category'] == 'Beverages' ? 'selected' : '' ?>>Beverages</option>
                                <option value="General" <?= $product['category'] == 'General' ? 'selected' : '' ?>>General</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price ($)</label>
                            <input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_qty" class="form-control" value="<?= $product['stock_qty'] ?>" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                            <a href="admin_products.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>