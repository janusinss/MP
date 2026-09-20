<?php
// admin/index.php
// Centralized Administrative Dashboard for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">
    
    <?php if (isset($_GET['msg']) && in_array($_GET['msg'], ['updated', 'added'])): ?>
    <div class="admin-alert-banner alert alert-success alert-dismissible fade show" role="alert" id="successModal">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>
                <strong>Operation Completed</strong>: The requested record has been saved successfully.
            </div>
        </div>
        <button type="button" class="btn-close" onclick="closeModal()" aria-label="Close"></button>
    </div>
    <script>
        function closeModal() {
            const el = document.getElementById('successModal');
            if (el) el.style.display = 'none';
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view') || 'dashboard';
            window.history.replaceState({}, document.title, 'index.php?view=' + view);
        }
    </script>
    <?php endif; ?>

    <!-- Mobile Admin Topbar -->
    <header class="admin-mobile-topbar d-lg-none">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSidebar()" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="fw-bold font-serif fs-5 text-dark">
            FreshCart<span class="text-success">.</span> <span class="text-muted fw-normal fs-6">Admin</span>
        </div>
        <a href="../" class="btn btn-sm btn-outline-secondary" target="_blank" title="View Storefront">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </header>

    <!-- Mobile Sidebar Backdrop -->
    <div class="admin-sidebar-backdrop d-lg-none" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <div class="admin-wrapper">
        
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-brand-box">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="admin-sidebar-title">FreshCart<span class="text-success">.</span></div>
                        <div class="admin-sidebar-subtitle">Operations Console</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light border-0 d-lg-none" onclick="toggleSidebar()" aria-label="Close menu">
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
                    <a href="../" class="btn btn-sm btn-outline-secondary w-50 d-flex align-items-center justify-content-center gap-1" target="_blank" title="View Storefront">
                        <i class="bi bi-shop"></i> <span>Store</span>
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger w-50 d-flex align-items-center justify-content-center gap-1" title="Sign out">
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
            document.querySelectorAll('.admin-nav-link').forEach(el => el.classList.remove('active'));
            
            const activeLink = document.getElementById('nav-' + baseView);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            // Close mobile sidebar on navigation
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.remove('mobile-open');
            if (backdrop) backdrop.classList.remove('active');

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

</body>
</html>
