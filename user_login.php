<?php
include 'db.php';
session_start();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify Password Hash
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        
        // Redirect to Shop
        header("Location: index.php");
        exit;
    } else {
        $error_message = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FreshCart Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>

<body class="login-body">

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-lock-fill login-icon"></i>
                <h2>Customer Login</h2>
            </div>
            
            <?php if (isset($error_message) && $error_message): ?>
                <div class="alert alert-danger text-center border-0 bg-transparent text-danger mb-4">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="user_login.php" method="POST">
                <div class="form-group mb-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group mb-5">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-login w-100">Login</button>
            </form>

            <div class="login-footer mt-4">
                <p>New here? <a href="user_register.php">Create an account</a></p>
                <p><a href="index.php">Back to Shop</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
