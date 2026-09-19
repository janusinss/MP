<?php
// admin/product_add.php
require_once __DIR__ . '/../config/db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock_qty'] ?? 0);
    $image = "default.jpg";
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false || !in_array($file_extension, $allowed_types)) {
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body style="background-color: var(--bg-color);">
    
    <div class="form-card-wrapper animate-fade-in">
        <div class="form-card">
            
            <h2 class="form-header-title">Create Product</h2>
            <p class="form-header-subtitle">Add a new item to your FreshCart inventory.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group-modern">
                    <label class="label-modern">Product Image</label>
                    <div class="upload-area-modern" id="uploadPreview">
                        <div id="uploadPlaceholder">
                            <div class="upload-icon-circle">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>
                            <h6 class="mb-1 text-dark">Click to upload image</h6>
                            <p class="text-muted small mb-0">SVG, PNG, JPG or GIF (Max 2MB)</p>
                        </div>
                        <input type="file" name="image" id="imageInput" class="file-input-hidden" accept="image/*" required>
                    </div>
                </div>

                <div class="form-group-modern">
                    <label class="label-modern">Product Name</label>
                    <input type="text" name="name" class="input-modern" placeholder="e.g. Organic Bananas" required>
                </div>

                <div class="row">
                    <div class="col-md-12 form-group-modern">
                        <label class="label-modern">Category</label>
                        <select name="category" class="input-modern" required>
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
                <a href="index.php?view=products" class="btn-cancel-link">Cancel</a>
            </form>
        </div>
    </div>

<script>
    const imageInput = document.getElementById('imageInput');
    const uploadPreview = document.getElementById('uploadPreview');

    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100%';
                img.style.height = '220px';
                img.style.objectFit = 'contain';
                img.style.borderRadius = '12px';
                img.style.mixBlendMode = 'multiply';

                const changeLabel = document.createElement('p');
                changeLabel.innerText = "Click here to change image";
                changeLabel.className = "text-muted small mt-3 mb-0 fw-bold text-uppercase";
                changeLabel.style.letterSpacing = "0.05em";

                uploadPreview.innerHTML = ''; 
                uploadPreview.appendChild(imageInput);
                uploadPreview.appendChild(img);
                uploadPreview.appendChild(changeLabel);
            }
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>
