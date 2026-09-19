<?php
// Fix session error: Check if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate Cart Count safely
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$rootPath = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'grocery_app') ? '' : '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreshCart Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $rootPath ?>assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <div id="flash-promo" class="bg-dark text-white text-center py-2 small fw-bold" 
         style="letter-spacing: 0.05em; transition: opacity 1s ease-out, height 1s ease-out; overflow: hidden;">
        ⚡ FLASH SALE: Use code <span class="text-warning border-bottom border-warning" style="cursor:pointer;" onclick="navigator.clipboard.writeText('FRESH50'); alert('Code FRESH50 copied!');">FRESH50</span> for 50% OFF your first order!
    </div>

    <script>
        // Auto-hide promo bar after 6 seconds
        setTimeout(() => {
            const promo = document.getElementById('flash-promo');
            if (promo) {
                promo.style.opacity = '0'; // Fade out
                setTimeout(() => {
                    promo.style.height = '0';
                    promo.style.padding = '0';
                }, 1000); 
            }
        }, 1000);
    </script>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= $rootPath ?: './' ?>">
                <h3 class="m-0" style="font-family: var(--font-serif); letter-spacing: -0.05em; font-weight: 800;">
                    FreshCart<span style="color: var(--accent-color)">.</span>
                </h3>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link-custom d-flex align-items-center gap-2" href="<?= $rootPath ?>account/profile.php">
                                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a href="<?= $rootPath ?>auth/login.php" class="nav-link-custom">Login</a></li>
                        <li class="nav-item"><a href="<?= $rootPath ?>auth/register.php" class="btn btn-primary rounded-pill px-4 shadow-sm">Sign Up</a></li>
                    <?php endif; ?>

                    <li class="nav-item position-relative">
                        <a href="<?= $rootPath ?>cart/index.php" class="btn btn-outline-secondary border-0 position-relative">
                            <i class="bi bi-bag" style="font-size: 1.3rem;"></i>
                            <?php if($cartCount > 0): ?>
                                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem;">
                                    <?= $cartCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>