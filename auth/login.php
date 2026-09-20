<?php
// auth/login.php
// Customer Authentication Portal for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$appRoot = $appRoot ? $appRoot . '/' : '/';

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!check_rate_limit('login_' . ($email ?: 'anon'), 6, 60)) {
        $error_message = "Too many login attempts. Please wait 1 minute.";
    } elseif (!verify_csrf_token()) {
        $error_message = "Security validation failed. Please refresh the page.";
    } else {
        // 1. Check system administrator credentials
        $adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
        $adminPass = getenv('ADMIN_PASSWORD') ?: 'admin123';

        if (($email === $adminUser || $email === 'admin@freshcart.com') && $password === $adminPass) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = 'Administrator';
            $_SESSION['role'] = 'admin';
            header("Location: " . $appRoot . "admin/");
            exit;
        }

        // 2. Query user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'] ?? 'customer';

            if (($user['role'] ?? '') === 'admin') {
                $_SESSION['admin_logged_in'] = true;
                header("Location: " . $appRoot . "admin/");
                exit;
            }

            header("Location: " . $appRoot);
            exit;
        } else {
            $error_message = "Invalid email or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - FreshCart Market</title>
    <meta name="description" content="Sign in to your FreshCart account to access your farm-fresh deliveries and subscriptions.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $appRoot ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

    <main class="auth-split-wrapper">
        <!-- Left: Farmstead Showcase Panel -->
        <section class="auth-showcase-panel" style="background-image: url('https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&auto=format&fit=crop&q=80');" aria-label="Farmstead Storytelling">
            <div class="auth-showcase-top">
                <a href="<?= $appRoot ?>" class="auth-brand-logo" title="Return to FreshCart Home">
                    <span>FreshCart</span><span class="auth-brand-dot"></span>
                </a>
            </div>
            
            <div class="auth-showcase-bottom">
                <div class="auth-showcase-kicker">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    <span>Direct Agricultural Sourcing</span>
                </div>
                <h1 class="auth-showcase-title">Sourced at dawn. At your table by evening.</h1>
                <p class="auth-showcase-desc">Log in to track your active 4&deg;C cold-chain harvest deliveries, manage seasonal produce subscriptions, and connect directly with independent family farms.</p>
                
                <div class="auth-proof-card">
                    <img src="https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=120&auto=format&fit=crop&q=80" alt="Mateo Alvarez, independent organic grower" class="auth-proof-avatar" width="48" height="48" loading="lazy">
                    <div class="auth-proof-content">
                        <p class="auth-proof-quote">&ldquo;FreshCart connects our family orchards directly to households that cherish clean, living food.&rdquo;</p>
                        <span class="auth-proof-author">Mateo Alvarez &bull; Alvarez Organic Groves</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Auth Form Panel -->
        <section class="auth-form-panel" aria-label="Sign In Form">
            <div class="auth-card">
                <!-- Mobile App Header with Back Navigation -->
                <div class="auth-mobile-header">
                    <a href="<?= $appRoot ?>" class="auth-back-pill" aria-label="Back to Storefront">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <span>Market</span>
                    </a>
                    <a href="<?= $appRoot ?>" class="auth-mobile-logo">
                        FreshCart<span>.</span>
                    </a>
                </div>

                <!-- 1-Tap Segmented Auth Switcher -->
                <div class="auth-segmented-switch" role="tablist" aria-label="Account Access Options">
                    <a href="login" class="auth-switch-tab active" role="tab" aria-selected="true">Sign In</a>
                    <a href="register" class="auth-switch-tab" role="tab" aria-selected="false">Create Account</a>
                </div>

                <div class="auth-card-header">
                    <h2 class="auth-card-title">Welcome Back</h2>
                    <p class="auth-card-subtitle">Sign in to manage your orders &amp; fresh harvests</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                        <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                        <div><?= htmlspecialchars($error_message) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <?= csrf_input() ?>
                    
                    <div class="auth-field-group">
                        <label for="loginEmail" class="auth-field-label">Email Address or Username</label>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" id="loginEmail" name="email" class="auth-input" placeholder="you@example.com or admin" value="<?= htmlspecialchars($email ?? '') ?>" required autofocus autocomplete="username" inputmode="email" autocapitalize="none" autocorrect="off">
                        </div>
                    </div>

                    <div class="auth-field-group">
                        <div class="auth-field-label">
                            <label for="loginPassword" class="m-0">Password</label>
                        </div>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input type="password" id="loginPassword" name="password" class="auth-input" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <span>Sign In</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>

                    <!-- Fast One-Tap Demo Fill for Reviewers & Mobile Testing -->
                    <div class="auth-quick-fill">
                        <span class="quick-fill-label"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Demo:</span>
                        <button type="button" class="quick-fill-pill" onclick="fillDemo('customer@gmail.com', 'password')">Customer</button>
                        <button type="button" class="quick-fill-pill" onclick="fillDemo('admin@freshcart.com', 'admin123')">Admin</button>
                    </div>

                    <div class="auth-footer-nav">
                        <div>Don't have an account? <a href="register" class="auth-nav-link">Create an account</a></div>
                        <div>
                            <a href="<?= $appRoot ?>" class="auth-back-link">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                <span>Back to Market</span>
                            </a>
                        </div>
                    </div>

                    <div class="auth-trust-strip">
                        <span class="auth-trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i> SSL 256-Bit</span>
                        <span class="auth-trust-item"><i class="bi bi-patch-check" aria-hidden="true"></i> Farm Guaranteed</span>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    }

    function fillDemo(email, pass) {
        const emailInput = document.getElementById('loginEmail');
        const passInput = document.getElementById('loginPassword');
        if (emailInput && passInput) {
            emailInput.value = email;
            passInput.value = pass;
            passInput.focus();
        }
    }
    </script>
</body>
</html>
