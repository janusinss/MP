<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock_qty'];
    
    // IMAGE UPLOAD LOGIC
    $image = "default.jpg"; // Default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "assets/images/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = $new_filename;
            }
        }
    }

    // Insert into Database
    $sql = "INSERT INTO products (name, category, price, stock_qty, image) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $category, $price, $stock, $image]);

    // Redirect to Product List
    header("Location: admin.php"); // Updated redirect to main admin
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add New Product | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body style="background-color: var(--bg-color);">
    
    <div class="form-card-wrapper animate-fade-in">
        <div class="form-card">
            
            <h2 class="form-header-title">Create Product</h2>
            <p class="form-header-subtitle">Add a new item to your FreshCart inventory.</p>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-group-modern">
                    <label class="label-modern">Product Image</label>
                    <div class="upload-area-modern">
                        <div class="upload-icon-circle">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <h6 class="mb-1 text-dark">Click to upload image</h6>
                        <p class="text-muted small mb-0">SVG, PNG, JPG or GIF (Max 2MB)</p>
                        <input type="file" name="image" class="file-input-hidden" accept="image/*" required>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label class="label-modern">Product Name</label>
                    <input type="text" name="name" class="input-modern" placeholder="e.g. Organic Bananas" required>
                </div>

                <div class="row">
                    <div class="col-md-12 form-group-modern">
                        <label class="label-modern">Category</label>
                        <select name="category" class="input-modern">
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
                </div>

                <div class="row">
                    <div class="col-md-6 form-group-modern">
                        <label class="label-modern">Price ($)</label>
                        <input type="number" step="0.01" name="price" class="input-modern" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6 form-group-modern">
                        <label class="label-modern">Stock Quantity</label>
                        <input type="number" name="stock_qty" class="input-modern" placeholder="100" required>
                    </div>
                </div>

                <button type="submit" class="btn-create">Publish Product</button>
                <a href="admin.php" class="btn-cancel-link">Cancel</a>

            </form>
        </div>
    </div>

</body>
</html>