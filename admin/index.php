<?php
// admin/index.php
// Centralized Administrative Dashboard for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = function_exists('get_app_root') ? get_app_root() : '/';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: " . $rootPath . "login");
    exit;
}

$adminName = $_SESSION['user_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreshCart Admin Console</title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="shortcut icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="../assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<body class="admin-body">

    <!-- Mobile Admin Topbar -->
    <header class="admin-mobile-topbar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/logo.png" alt="FreshCart Logo" class="admin-topbar-logo" width="28" height="28">
            <div class="fw-bold fs-6 text-dark" style="letter-spacing: -0.02em;">
                FreshCart<span class="text-success">.</span> <span class="badge bg-dark-subtle text-dark border ms-1" style="font-size: 0.65rem; font-weight: 700;">ADMIN</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" style="min-height: 40px; font-weight: 600; font-size: 0.8rem; padding: 0 12px;" title="View Live Storefront">
                <i class="bi bi-shop"></i> <span>Store</span>
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Sign out" aria-label="Logout">
                <i class="bi bi-box-arrow-left"></i>
            </a>
        </div>
    </header>

    <!-- Mobile Sidebar Backdrop -->
    <div class="admin-sidebar-backdrop d-lg-none" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-brand-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <img src="../assets/images/logo.png" alt="FreshCart Logo" class="admin-sidebar-logo" width="34" height="34">
                        <div>
                            <div class="admin-sidebar-title">FreshCart<span class="text-success">.</span></div>
                            <div class="admin-sidebar-subtitle">Operations Console</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light border-0 d-lg-none" onclick="toggleSidebar()" aria-label="Close menu" style="width: 40px; height: 40px;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            
            <nav class="nav flex-column gap-1 flex-grow-1 mt-3">
                <a href="#" id="nav-dashboard" onclick="loadView('dashboard')" class="admin-nav-link active">
                    <i class="bi bi-graph-up-arrow"></i> <span>Analytics</span>
                </a>
                <a href="#" id="nav-orders" onclick="loadView('orders')" class="admin-nav-link">
                    <i class="bi bi-receipt-cutoff"></i> <span>Orders</span>
                </a>
                <a href="#" id="nav-products" onclick="loadView('products')" class="admin-nav-link">
                    <i class="bi bi-box-seam"></i> <span>Inventory</span>
                </a>
                <a href="#" id="nav-users" onclick="loadView('users')" class="admin-nav-link">
                    <i class="bi bi-people"></i> <span>Customers</span>
                </a>
                <a href="#" id="nav-reviews" onclick="loadView('reviews')" class="admin-nav-link">
                    <i class="bi bi-star"></i> <span>Reviews</span>
                </a>
            </nav>

            <!-- Admin Profile Footer -->
            <div class="admin-sidebar-footer">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="admin-avatar-initial">
                        <?= strtoupper(substr($adminName, 0, 1)) ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="admin-user-name text-truncate"><?= htmlspecialchars($adminName) ?></div>
                        <div class="admin-user-role">System Administrator</div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="../" class="btn btn-sm btn-outline-secondary w-50 d-flex align-items-center justify-content-center gap-1" style="min-height: 40px;" title="View Storefront">
                        <i class="bi bi-shop"></i> <span>Store</span>
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger w-50 d-flex align-items-center justify-content-center gap-1" style="min-height: 40px;" title="Sign out">
                        <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Dynamic Main Content Area -->
        <main class="admin-main" id="mainContent" role="main" aria-live="polite">
            <div class="py-5 text-center text-muted">
                <div class="spinner-border text-success spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading view...</span>
                </div>
            </div>
        </main>

    </div>

    <!-- Admin Mobile Bottom Navigation Bar (Fixed 5-Tab Touch Rail) -->
    <nav class="admin-mobile-bottom-nav d-lg-none" aria-label="Admin Mobile Navigation">
        <ul class="admin-mobile-nav-grid">
            <li class="admin-mobile-nav-item">
                <button type="button" id="mob-nav-dashboard" class="admin-mobile-nav-btn active" onclick="loadView('dashboard')">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Analytics</span>
                </button>
            </li>
            <li class="admin-mobile-nav-item">
                <button type="button" id="mob-nav-orders" class="admin-mobile-nav-btn" onclick="loadView('orders')">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Orders</span>
                </button>
            </li>
            <li class="admin-mobile-nav-item">
                <button type="button" id="mob-nav-products" class="admin-mobile-nav-btn" onclick="loadView('products')">
                    <i class="bi bi-box-seam"></i>
                    <span>Products</span>
                </button>
            </li>
            <li class="admin-mobile-nav-item">
                <button type="button" id="mob-nav-users" class="admin-mobile-nav-btn" onclick="loadView('users')">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </button>
            </li>
            <li class="admin-mobile-nav-item">
                <button type="button" id="mob-nav-reviews" class="admin-mobile-nav-btn" onclick="loadView('reviews')">
                    <i class="bi bi-star"></i>
                    <span>Reviews</span>
                </button>
            </li>
        </ul>
    </nav>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            const currentView = urlParams.get('view') || 'dashboard';
            loadView(currentView);
        });

        function loadView(viewName) {
            const baseView = viewName.split('&')[0]; 
            
            // Sync Desktop Sidebar
            document.querySelectorAll('.admin-nav-link').forEach(el => el.classList.remove('active'));
            const activeLink = document.getElementById('nav-' + baseView);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            // Sync Mobile Bottom Navigation
            document.querySelectorAll('.admin-mobile-nav-btn').forEach(el => el.classList.remove('active'));
            const mobActiveLink = document.getElementById('mob-nav-' + baseView);
            if (mobActiveLink) {
                mobActiveLink.classList.add('active');
            }

            // Close mobile sidebar on navigation
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.remove('mobile-open');
            if (backdrop) backdrop.classList.remove('active');

            // Scroll window to top
            window.scrollTo({ top: 0, behavior: 'instant' });

            const main = document.getElementById('mainContent');

            fetch('router.php?view=' + viewName)
                .then(response => response.text())
                .then(html => {
                    main.innerHTML = html;
                    
                    const scripts = main.querySelectorAll("script");
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement("script");
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                })
                .catch(err => {
                    console.error('Error loading view:', err);
                    main.innerHTML = '<div class="alert alert-danger">Failed to load content. Please refresh.</div>';
                });
        }
    </script>

    <!-- Floating Notifications Container (Web: Bottom-Left, Mobile: UX-Friendly Docked) -->
    <div class="fresh-toast-container" id="freshToastContainer" aria-live="polite" aria-atomic="true">
        <?php if (isset($_GET['msg']) && in_array($_GET['msg'], ['updated', 'added', 'deleted'])): ?>
            <?php
                $msgKey = $_GET['msg'];
                $toastTitle = 'Record Saved';
                $toastMsg = 'The requested record has been saved successfully.';
                if ($msgKey === 'added') {
                    $toastTitle = 'Record Published';
                    $toastMsg = 'The new record has been published successfully.';
                } elseif ($msgKey === 'deleted') {
                    $toastTitle = 'Record Removed';
                    $toastMsg = 'The requested record has been deleted successfully.';
                }
            ?>
            <div class="fresh-toast fresh-toast-success is-visible" role="alert" id="adminServerToast">
                <div class="fresh-toast-icon">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                </div>
                <div class="fresh-toast-body">
                    <div class="fresh-toast-title"><?= htmlspecialchars($toastTitle) ?></div>
                    <div class="fresh-toast-message"><?= htmlspecialchars($toastMsg) ?></div>
                </div>
                <button type="button" class="fresh-toast-close" onclick="dismissAdminToast()" aria-label="Dismiss notification">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
                <div class="fresh-toast-progress">
                    <div class="fresh-toast-progress-bar" style="transform: scaleX(0); transition: transform 4500ms linear;"></div>
                </div>
            </div>
            <script>
                function dismissAdminToast() {
                    const t = document.getElementById('adminServerToast');
                    if (t) {
                        t.classList.remove('is-visible');
                        t.classList.add('is-hiding');
                        setTimeout(function() { if (t.parentNode) t.parentNode.removeChild(t); }, 280);
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.delete('msg');
                    window.history.replaceState({}, document.title, url.toString());
                }
                setTimeout(dismissAdminToast, 4500);
            </script>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast.js?v=<?= time() ?>"></script>
</body>
</html>
