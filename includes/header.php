<?php
// Fix session error: Check if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/security.php';

// User & Admin Role Status
$isAdmin = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isLoggedIn = isset($_SESSION['user_id']) || $isAdmin;

// Calculate Cart Count safely
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$pos = strpos($scriptName, '/grocery_app');
if ($pos !== false) {
    $rootPath = substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
} else {
    $rootPath = '/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= function_exists('get_csrf_token') ? htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') : '' ?>">
    <title>FreshCart Market</title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="<?= $rootPath ?>assets/images/favicon.png">
    <link rel="shortcut icon" href="<?= $rootPath ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= $rootPath ?>assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $rootPath ?>assets/css/style.css?v=<?php echo time(); ?>">
    <script>
        // Security: Defeat Back-Forward Cache (bfcache) & History Navigation Leaks after Logout
        (function() {
            function enforceFreshAuth() {
                var navEntries = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation') : null;
                var isBackForward = (navEntries && navEntries.length > 0 && navEntries[0].type === 'back_forward') || 
                                    (window.performance && window.performance.navigation && window.performance.navigation.type === 2);
                if (isBackForward) {
                    window.location.reload();
                }
            }
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                } else {
                    enforceFreshAuth();
                }
            });
        })();
    </script>
</head>
<body class="<?= $isLoggedIn ? 'has-fc-bottom-nav' : '' ?>">

    <!-- Welcome Offer Lightbox Pop-up Modal (Guest Only) -->
    <?php if (!isset($_SESSION['user_id']) && !$isAdmin): ?>
    <div id="welcomePromoModal" class="promo-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle" aria-describedby="promoModalDesc">
        <div class="promo-modal-dialog">
            <button type="button" class="promo-modal-close-btn" onclick="dismissPromoModal()" aria-label="Close welcome offer" title="Close (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>

            <div class="promo-modal-leaf-icon" aria-hidden="true">
                <i class="bi bi-gift"></i>
            </div>

            <div class="promo-modal-kicker">
                <span>Welcome Offer</span>
            </div>

            <h3 class="promo-modal-heading" id="promoModalTitle">
                Enjoy <strong>50% off</strong> your first seasonal order
            </h3>

            <p class="promo-modal-desc" id="promoModalDesc">
                Taste the crisp difference of farm-fresh harvests, sustainably grown by local producers and delivered directly to your kitchen table.
            </p>

            <div class="promo-modal-code-wrapper">
                <span class="promo-code-label">Code:</span>
                <span class="promo-code-val" id="promoCodeVal">FRESH50</span>
                <button type="button" class="promo-code-copy-btn" id="promoModalCopyBtn" onclick="copyPromoCode(this, 'FRESH50')" aria-label="Copy promo code FRESH50" title="Click to copy code FRESH50">
                    <i class="bi bi-copy promo-icon-copy" aria-hidden="true"></i>
                    <i class="bi bi-check2 promo-icon-check" aria-hidden="true"></i>
                    <span class="promo-copy-text">Copy</span>
                </button>
                <span class="promo-copied-feedback" role="status" aria-live="polite">Copied!</span>
            </div>

            <div class="promo-modal-actions">
                <a href="<?= $rootPath ?>login" class="btn btn-primary promo-claim-btn" onclick="dismissPromoModal()">
                    Claim Offer &amp; Start Shopping
                </a>
                <button type="button" class="promo-modal-dismiss-link" onclick="dismissPromoModal()">
                    No thanks, continue browsing
                </button>
            </div>
        </div>
    </div>

    <script>
        function openPromoModal() {
            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.classList.add('is-open');
                document.body.classList.add('modal-open-freeze');
            }
        }

        function dismissPromoModal() {
            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.classList.remove('is-open');
                document.body.classList.remove('modal-open-freeze');
                try {
                    sessionStorage.setItem('promo_popup_dismissed', '1');
                } catch (e) {}
            }
        }

        function copyPromoCode(btn, code) {
            if (!code) code = 'FRESH50';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).catch(() => {});
            }
            if (btn) {
                btn.classList.add('is-copied');
                const textSpan = btn.querySelector('.promo-copy-text');
                if (textSpan) textSpan.textContent = 'Copied';
                setTimeout(() => {
                    btn.classList.remove('is-copied');
                    if (textSpan) textSpan.textContent = 'Copy';
                }, 2000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let isDismissed = false;
            try {
                isDismissed = sessionStorage.getItem('promo_popup_dismissed') === '1';
            } catch (e) {}

            if (!isDismissed && window.innerWidth > 768) {
                setTimeout(openPromoModal, 600);
            }

            const modal = document.getElementById('welcomePromoModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        dismissPromoModal();
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    dismissPromoModal();
                }
            });
        });
    </script>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg navbar-glass sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= $rootPath ?: './' ?>" aria-label="FreshCart Home">
                <img src="<?= $rootPath ?>assets/images/logo.png" alt="FreshCart Logo" class="brand-logo-img" width="34" height="34">
                <h3 class="brand-wordmark">FreshCart<span style="color: var(--accent-color)">.</span></h3>
            </a>
            
            <div class="mobile-nav-actions d-flex align-items-center gap-2 d-lg-none">
                <?php if ($isLoggedIn): ?>
                    <a href="<?= $rootPath ?>cart" class="mobile-header-action-btn mobile-header-cart" aria-label="Shopping Cart">
                        <i class="bi bi-bag"></i>
                        <span class="mobile-header-cart-badge <?= ($cartCount > 0) ? '' : 'd-none' ?>"><?= $cartCount ?></span>
                    </a>
                    <a href="<?= $isAdmin ? $rootPath . 'admin/' : $rootPath . 'profile' ?>" class="mobile-header-action-btn mobile-header-avatar" aria-label="Account">
                        <span><?= $isAdmin ? '<i class="bi bi-shield-check"></i>' : strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></span>
                    </a>
                <?php endif; ?>

                <?php if (!$isLoggedIn): ?>
                <button class="navbar-toggler freshcart-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navContent" aria-controls="navContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="toggler-bars" aria-hidden="true">
                        <span class="toggler-bar bar-top"></span>
                        <span class="toggler-bar bar-mid"></span>
                        <span class="toggler-bar bar-bot"></span>
                    </span>
                </button>
                <?php endif; ?>
            </div>

            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <?php if ($isAdmin): ?>
                        <li class="nav-item dropdown">
                            <div class="storefront-admin-pill">
                                <a href="<?= $rootPath ?>admin/" class="storefront-admin-pill-link" title="Return to Admin Console">
                                    <i class="bi bi-arrow-left storefront-admin-pill-icon"></i>
                                    <span>Return to Admin</span>
                                </a>
                                <button class="storefront-admin-pill-toggle dropdown-toggle" type="button" id="globalAdminDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Admin options">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2 mt-2 storefront-admin-dropdown" aria-labelledby="globalAdminDropdown">
                                    <li class="px-3 py-2 border-bottom mb-1" style="background-color: #fafbf9;">
                                        <div class="text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em; font-weight: 700;">Signed in as</div>
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?></div>
                                    </li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>admin/"><i class="bi bi-speedometer2 text-success"></i> <span>Operations Console</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>admin/index.php?view=products"><i class="bi bi-box-seam text-success"></i> <span>Manage Inventory</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>admin/index.php?view=orders"><i class="bi bi-receipt text-success"></i> <span>Manage Orders</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>admin/index.php?view=users"><i class="bi bi-people text-success"></i> <span>Manage Customers</span></a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>admin/index.php?view=reviews"><i class="bi bi-star text-success"></i> <span>Manage Reviews</span></a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 text-danger" href="<?= $rootPath ?>admin/logout.php"><i class="bi bi-box-arrow-right"></i> <span>Sign Out</span></a></li>
                                </ul>
                            </div>
                        </li>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <button class="nav-link-custom d-flex align-items-center gap-2 dropdown-toggle bg-transparent border-0 p-0 text-decoration-none" id="globalUserDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 34px; height: 34px; font-size: 0.85rem; background: var(--color-primary, #15803d);">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2 mt-2" aria-labelledby="globalUserDropdown" style="border-radius: 14px; min-width: 220px; border-color: rgba(0,0,0,0.08);">
                                <li class="px-3 py-2 border-bottom mb-1" style="background-color: #fafbf9;">
                                    <div class="text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em; font-weight: 700;">Signed in as</div>
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>profile"><i class="bi bi-person-gear text-success"></i> <span>Account Settings</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>orders"><i class="bi bi-receipt text-success"></i> <span>My Orders</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3" href="<?= $rootPath ?>cart"><i class="bi bi-bag-check text-success"></i> <span>View Cart (<?= $cartCount ?>)</span></a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 text-danger" href="<?= $rootPath ?>logout"><i class="bi bi-box-arrow-right"></i> <span>Sign Out</span></a></li>
                            </ul>
                        </li>

                        <li class="nav-item position-relative">
                            <a href="<?= $rootPath ?>cart" class="btn btn-outline-secondary border-0 position-relative p-2" aria-label="Shopping Cart">
                                <i class="bi bi-bag fs-5"></i>
                                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light <?= ($cartCount > 0) ? '' : 'd-none' ?>" style="font-size: 0.65rem;">
                                    <?= $cartCount ?>
                                </span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a href="<?= $rootPath ?>login" class="nav-link-custom nav-btn-login">Login</a></li>
                        <li class="nav-item"><a href="<?= $rootPath ?>register" class="btn btn-primary nav-btn-signup rounded-pill px-4 shadow-sm">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($isLoggedIn): 
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $isCheckout = (strpos($reqUri, '/checkout') !== false);
        $isOrdersActive = (strpos($reqUri, '/orders') !== false || strpos($reqUri, '/order/') !== false);
        $isCartActive = (strpos($reqUri, '/cart') !== false);
        $isProfileActive = (strpos($reqUri, '/profile') !== false);
        $isMarketActive = (!$isOrdersActive && !$isCartActive && !$isProfileActive);
    ?>
    <?php if (!$isCheckout): ?>
    <!-- Sleek Mobile Bottom Navigation Bar for Logged-In Shoppers -->
    <nav class="fc-mobile-bottom-bar d-lg-none" aria-label="Quick Mobile Navigation">
        <a href="<?= $rootPath ?: './' ?>" class="fc-bottom-tab <?= $isMarketActive ? 'active' : '' ?>">
            <i class="bi bi-shop" aria-hidden="true"></i>
            <span>Market</span>
        </a>
        <a href="<?= $rootPath ?: './' ?>#categories" class="fc-bottom-tab">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span>Aisles</span>
        </a>
        <a href="<?= $rootPath ?>orders" class="fc-bottom-tab <?= $isOrdersActive ? 'active' : '' ?>">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <span>Orders</span>
        </a>
        <a href="<?= $rootPath ?>cart" class="fc-bottom-tab fc-bottom-tab-cart <?= $isCartActive ? 'active' : '' ?>">
            <div class="position-relative d-inline-block">
                <i class="bi bi-bag" aria-hidden="true"></i>
                <span class="fc-bottom-badge <?= ($cartCount > 0) ? '' : 'd-none' ?>"><?= $cartCount ?></span>
            </div>
            <span>Harvest Bag</span>
        </a>
        <a href="<?= $isAdmin ? $rootPath . 'admin/' : $rootPath . 'profile' ?>" class="fc-bottom-tab <?= $isProfileActive ? 'active' : '' ?>">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>Account</span>
        </a>
    </nav>
    <?php endif; ?>
    <?php endif; ?>