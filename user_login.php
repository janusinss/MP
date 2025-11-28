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

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
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
    <title>Login - FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="auth-wrapper">
        <div class="row g-0 h-100">
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="auth-banner-side">
                    <div class="auth-banner-content">
                        <h1 style="font-family: var(--font-serif);font-size: 3rem;margin-bottom: 1.5rem;line-height: 1.1; color: #fff; opacity: 0.9;" >Quality you can taste, aesthetics you can feel.</h1>
                        <p class="fs-5 opacity-75">Welcome back to your curated grocery experience.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="auth-form-side">
                    <div class="hero-blob blob-2" style="width: 200px; height: 200px; top: -50px; right: -50px; opacity: 0.4;"></div>

                    <div class="auth-form-container">
                        <div class="text-center mb-5">
                            <h2 class="mb-2" style="font-family: var(--font-serif); font-size: 2.5rem;">Welcome Back</h2>
                            <p class="text-muted">Please enter your details to sign in.</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 text-center">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_message ?>
                            </div>
                        <?php endif; ?>

                        <form action="user_login.php" method="POST">
                            
                            <div class="mb-2">
                                <label class="auth-label">Email Address</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" name="email" placeholder="you@example.com" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="auth-label">Password</label>
                                <div class="auth-input-group">
                                    <i class="bi bi-lock"></i>
                                    <input type="password" name="password" placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-auth w-100 py-3 mb-4 shadow">Sign In</button>

                            <div class="text-center d-flex flex-column gap-2">
                                <span class="text-muted">Don't have an account? <a href="user_register.php" class="text-dark fw-bold text-decoration-underline">Create one</a></span>
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