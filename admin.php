<?php
include 'db.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FreshCart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    
    <div class="admin-wrapper">
        
        <div class="admin-sidebar">
            <div class="mb-5">
                <div class="admin-brand">FreshCart<span>.</span></div>
                <span class="admin-badge">Admin Panel</span>
            </div>
            
            <nav class="nav flex-column gap-2 flex-grow-1">
                <a href="#" id="nav-dashboard" onclick="loadView('dashboard')" class="admin-nav-link active">
                    <i class="bi bi-grid-1x2-fill"></i> Overview
                </a>
                <a href="#" id="nav-products" onclick="loadView('products')" class="admin-nav-link">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a href="#" id="nav-users" onclick="loadView('users')" class="admin-nav-link">
                    <i class="bi bi-people"></i> Customers
                </a>
                <a href="#" id="nav-reviews" onclick="loadView('reviews')" class="admin-nav-link">
                    <i class="bi bi-star"></i> Reviews
                </a>
            </nav>

            <div class="mt-auto border-top pt-4">
                <a href="index.php" class="admin-nav-link mb-1">
                    <i class="bi bi-shop-window"></i> Storefront
                </a>
                <a href="logout.php" class="admin-nav-link text-danger">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </div>
        </div>

        <main class="admin-main" id="mainContent">
            </main>

    </div>

    <script>
        // Default Load
        document.addEventListener("DOMContentLoaded", () => {
            loadView('dashboard');
        });

        function loadView(viewName) {
            // 1. Sidebar Logic: Remove 'active' from all, add to current
            document.querySelectorAll('.admin-nav-link').forEach(el => el.classList.remove('active'));
            
            // Find the sidebar link by ID and highlight it
            const activeLink = document.getElementById('nav-' + viewName);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            const main = document.getElementById('mainContent');

            // 2. Fetch Content INSTANTLY
            fetch('admin_router.php?view=' + viewName)
                .then(response => response.text())
                .then(html => {
                    main.innerHTML = html;
                    
                    // Execute scripts inside the fetched HTML (like Charts)
                    const scripts = main.querySelectorAll("script");
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement("script");
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                })
                .catch(err => console.error('Error loading view:', err));
        }
    </script>

</body>
</html>