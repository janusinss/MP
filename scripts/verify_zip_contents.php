<?php
// scripts/verify_zip_contents.php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../dist_infinityfree.zip') === true) {
    echo "Total entries in zip: " . $zip->numFiles . "\n";
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[] = $zip->getNameIndex($i);
    }
    
    $requiredKeyFiles = [
        '.env',
        '.htaccess',
        'index.php',
        'account/profile.php',
        'admin/index.php',
        'admin/login.php',
        'admin/router.php',
        'admin/actions/product_delete.php',
        'api/config/cors.php',
        'api/config/database.php',
        'api/utils/AuthMiddleware.php',
        'api/utils/Response.php',
        'api/v1/auth/login.php',
        'api/v1/products/index.php',
        'api/v1/cart/index.php',
        'api/v1/orders/create.php',
        'api/v1/orders/history.php',
        'assets/css/style.css',
        'assets/images/.htaccess',
        'auth/login.php',
        'auth/register.php',
        'cart/index.php',
        'cart/add.php',
        'config/db.php',
        'config/env.php',
        'config/security.php',
        'db_check.php',
        'includes/header.php',
        'includes/footer.php',
        'orders/checkout.php',
        'orders/place.php',
        'orders/index.php',
        'orders/details.php',
        'pages/about.php',
        'products/view.php',
        'products/fetch.php'
    ];
    
    echo "\nCHECKING CRITICAL SYSTEM FILES:\n";
    $missing = 0;
    foreach ($requiredKeyFiles as $req) {
        $found = in_array($req, $files);
        echo "  [" . ($found ? "OK" : "MISSING") . "] $req\n";
        if (!$found) $missing++;
    }
    
    echo "\nMISSING FILES COUNT: $missing\n";
    
    // Check if any excluded files leaked in
    $badFiles = [];
    foreach ($files as $f) {
        if (strpos($f, 'database/') === 0 || strpos($f, 'scripts/') === 0 || strpos($f, '.git') === 0 || strpos($f, 'scratch/') === 0) {
            $badFiles[] = $f;
        }
    }
    echo "EXCLUDED FILES LEAKED COUNT: " . count($badFiles) . "\n";
} else {
    echo "Failed to open dist_infinityfree.zip\n";
}
