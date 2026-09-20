<?php
// auth/register.php
// Customer Registration Portal for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (!check_rate_limit('register_attempt', 5, 60)) {
        $error = "Too many registration attempts. Please wait 1 minute.";
    } elseif (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh the page.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email is already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, password, address, role) VALUES (?, ?, ?, ?, 'customer')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$name, $email, $hashed_password, $address])) {
                $success = "Account created successfully! Redirecting to login...";
                echo "<script>setTimeout(function(){ window.location.href = 'login'; }, 1500);</script>";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
$appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$appRoot = $appRoot ? $appRoot . '/' : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - FreshCart Market</title>
    <meta name="description" content="Create a FreshCart account to receive fresh farm-to-door organic deliveries and support independent growers.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $appRoot ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

    <main class="auth-split-wrapper">
        <!-- Left: Farmstead Showcase Panel -->
        <section class="auth-showcase-panel" style="background-image: url('https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=1200&auto=format&fit=crop&q=80');" aria-label="Farmstead Storytelling">
            <div class="auth-showcase-top">
                <a href="<?= $appRoot ?>" class="auth-brand-logo" title="Return to FreshCart Home">
                    <span>FreshCart</span><span class="auth-brand-dot"></span>
                </a>
            </div>
            
            <div class="auth-showcase-bottom">
                <div class="auth-showcase-kicker">
                    <i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i>
                    <span>Regenerative Agriculture Network</span>
                </div>
                <h1 class="auth-showcase-title">Direct from the growers who cultivate the soil.</h1>
                <p class="auth-showcase-desc">Join our transparent food network to enjoy peak-season produce, pasture-raised eggs, and farmstead cheeses harvested within 24 hours of delivery.</p>
                
                <div class="auth-proof-card">
                    <img src="https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=120&auto=format&fit=crop&q=80" alt="Alvarez Organic Groves" class="auth-proof-avatar" width="48" height="48" loading="lazy">
                    <div class="auth-proof-content">
                        <p class="auth-proof-quote">&ldquo;78% of every customer purchase returns directly to our farm, preserving organic heritage seeds for tomorrow.&rdquo;</p>
                        <span class="auth-proof-author">Clara Alvarez &bull; Alvarez Organic Groves</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Auth Form Panel -->
        <section class="auth-form-panel" aria-label="Registration Form">
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
                    <a href="login" class="auth-switch-tab" role="tab" aria-selected="false">Sign In</a>
                    <a href="register" class="auth-switch-tab active" role="tab" aria-selected="true">Create Account</a>
                </div>

                <div class="auth-card-header">
                    <h2 class="auth-card-title">Create Account</h2>
                    <p class="auth-card-subtitle">Start receiving farm-fresh harvests at your door</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                        <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                        <i class="bi bi-check-circle-fill text-success flex-shrink-0" aria-hidden="true"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <?= csrf_input() ?>
                    
                    <div class="auth-field-group">
                        <label for="regFullName" class="auth-field-label">Full Name</label>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" id="regFullName" name="full_name" class="auth-input" placeholder="e.g. John Doe" value="<?= htmlspecialchars($name ?? '') ?>" required autofocus autocomplete="name" inputmode="text" autocapitalize="words">
                        </div>
                    </div>

                    <div class="auth-field-group">
                        <label for="regEmail" class="auth-field-label">Email Address</label>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                            <input type="email" id="regEmail" name="email" class="auth-input" placeholder="you@example.com" value="<?= htmlspecialchars($email ?? '') ?>" required autocomplete="email" inputmode="email" autocapitalize="none" autocorrect="off">
                        </div>
                    </div>

                    <div class="auth-field-group">
                        <div class="auth-field-label">
                            <label for="regPassword" class="m-0">Password</label>
                            <span class="text-muted fw-normal small">Min 6 characters</span>
                        </div>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input type="password" id="regPassword" name="password" class="auth-input" placeholder="Create a secure password" minlength="6" required autocomplete="new-password">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('regPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-field-group">
                        <div class="auth-field-label">
                            <label for="regAddress" class="m-0">Delivery Address</label>
                            <span class="text-muted fw-normal small">Optional</span>
                        </div>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                            <input type="text" id="regAddress" name="address" class="auth-input" placeholder="Street Address, City, Postal Code" value="<?= htmlspecialchars($address ?? '') ?>" autocomplete="street-address" inputmode="text" autocapitalize="words">
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <span>Register Now</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>

                    <div class="auth-footer-nav">
                        <div class="auth-switch-prompt">Already have an account? <a href="login" class="auth-nav-link">Sign In</a></div>
                        <div>
                            <a href="<?= $appRoot ?>" class="auth-back-link">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                <span>Back to Market</span>
                            </a>
                        </div>
                    </div>

                    <div class="auth-trust-strip">
                        <span class="auth-trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i> SSL 256-Bit</span>
                        <span class="auth-trust-item"><i class="bi bi-patch-check" aria-hidden="true"></i> 100% Guaranteed</span>
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
    </script>
</body>
</html>
