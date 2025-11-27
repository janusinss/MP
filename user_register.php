<?php
include 'db.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $address = $_POST['address'];

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "Email is already registered!";
    } else {
        // Hash the password (Security Best Practice)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (full_name, email, password, address) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$name, $email, $hashed_password, $address])) {
            $success = "Account created! You can now <a href='user_login.php'>login here</a>.";
        } else {
            $error = "Registration failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - FreshCart Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body class="login-body">

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-person-plus-fill login-icon"></i>
                <h2>Create Account</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center border-0 bg-transparent text-danger mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success text-center border-0 bg-transparent text-success mb-4">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group mb-4">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group mb-5">
                    <label class="form-label">Delivery Address (Optional)</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-login w-100">Register</button>
            </form>

            <div class="login-footer mt-4">
                <p>Already have an account? <a href="user_login.php">Login</a></p>
                <p><a href="index.php">Back to Shop</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
