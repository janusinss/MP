<?php
// admin/login.php
require_once __DIR__ . '/../config/security.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!check_rate_limit('admin_auth', 5, 60)) {
        $error = "Too many login attempts. Please wait 1 minute.";
    } elseif (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh.";
    } else {
        $adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
        $adminPass = getenv('ADMIN_PASSWORD') ?: 'admin123';

        if ($username === $adminUser && $password === $adminPass) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FreshCart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 12px; }
    </style>
</head>
<body>

    <div class="card login-card shadow">
        <h3 class="text-center mb-4">🔒 Admin Portal</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_input() ?>
            <div class="mb-3">
                <label class="form-label fw-bold">Username</label>
                <input type="text" name="username" class="form-control" required autofocus placeholder="admin">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Sign In to Dashboard</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="../" class="text-decoration-none">← Back to Storefront</a>
        </div>
    </div>

</body>
</html>
