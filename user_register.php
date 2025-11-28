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

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "Email is already registered!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (full_name, email, password, address) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$name, $email, $hashed_password, $address])) {
            $success = "Account created successfully!";
            echo "<script>setTimeout(function(){ window.location.href = 'user_login.php'; }, 2000);</script>";
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
    <title>Sign Up - FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="auth-wrapper">
        <div class="row g-0 h-100">
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="auth-banner-side register-bg">
                    <div class="auth-banner-content">
                        <h1 style="font-family: var(--font-serif);font-size: 3rem;margin-bottom: 1.5rem;line-height: 1.1; color: #fff; opacity: 0.9;" >Join the community of clean, fresh food lovers.</h1>
                        <p class="fs-5 opacity-75">Track orders, save favorites, and get many exclusive organic deals.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="auth-form-side">
                    <div class="hero-blob blob-1" style="width: 250px; height: 250px; bottom: -50px; left: -50px; opacity: 0.4;"></div>

                    <div class="auth-form-container">
                        <div class="text-center mb-4">
                            <h2 class="mb-2" style="font-family: var(--font-serif); font-size: 2.5rem;">Create Account</h2>
                            <p class="text-muted">It only takes a minute to join.</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 text-center">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 text-center">
                                <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-2">
                                <label class="auth-label">Full Name</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-person"></i>
                                    <input type="text" name="full_name" placeholder="John Doe" required>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="auth-label">Email Address</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" name="email" placeholder="you@example.com" required>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="auth-label">Password</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" name="password" placeholder="Create a strong password" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="auth-label">Delivery Address (Optional)</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-geo-alt"></i>
                                    <input type="text" name="address" placeholder="123 Main St, City">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-auth w-100 py-3 mb-4 shadow">Register Now</button>

                            <div class="text-center d-flex flex-column gap-2">
                                <span class="text-muted">Already have an account? <a href="user_login.php" class="text-dark fw-bold text-decoration-underline">Login</a></span>
                                <a href="index.php" class="text-muted small text-decoration-none mt-2">← Back to Shop</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>