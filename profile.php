<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['full_name'];
    $address = $_POST['address'];
    
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET full_name=?, address=?, password=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $address, $pass, $user_id]);
    } else {
        $sql = "UPDATE users SET full_name=?, address=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $address, $user_id]);
    }
    
    $_SESSION['user_name'] = $name;
    $msg = "Profile updated successfully!";
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile | FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="container mt-5">
    
    <a href="index.php" class="btn btn-outline-secondary mb-4">&larr; Back to Shop</a>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="m-0" style="font-family: var(--font-serif);">Account Settings</h2>
                        
                        <div class="d-flex gap-2">
                            <a href="my_orders.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="bi bi-box-seam me-1"></i> Order History
                            </a>
                            <a href="user_logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </a>
                        </div>
                    </div>

                    <?php if ($msg): ?>
                        <div class="alert alert-success rounded-pill text-center mb-4"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">FULL NAME</label>
                                <input type="text" name="full_name" class="form-control bg-light" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">EMAIL (LOCKED)</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">DELIVERY ADDRESS</label>
                            <textarea name="address" class="form-control bg-light" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">NEW PASSWORD <span class="fw-normal">(Leave blank to keep current)</span></label>
                            <input type="password" name="password" class="form-control bg-light" placeholder="••••••••">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2">Save Changes</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

</body>
</html>