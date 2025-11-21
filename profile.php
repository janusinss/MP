<?php
include 'db.php';
session_start();

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['full_name'];
    $address = $_POST['address'];
    
    // Optional: Password Change Logic
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
    
    // Update Session Name
    $_SESSION['user_name'] = $name;
    $msg = "Profile updated successfully!";
}

// Fetch Current User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h4 class="mb-0">👤 Edit Profile</h4>
                    <a href="index.php" class="btn btn-sm btn-light">Back to Shop</a>
                </div>
                <div class="card-body">
                    <?php if ($msg): ?>
                        <div class="alert alert-success"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Email (Cannot be changed)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label>Default Address</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>

                        <hr>
                        
                        <div class="mb-3">
                            <label>New Password (Leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control" placeholder="********">
                        </div>

                        <button type="submit" class="btn btn-success w-100">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>