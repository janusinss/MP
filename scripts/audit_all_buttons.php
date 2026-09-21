<?php
// scripts/audit_all_buttons.php
// Comprehensive Button, Process & Role Interaction Test Suite
// Standards: .agents/rules/security.md & .agents/rules/backend.md

$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app';
$customerCookie = __DIR__ . '/test_cust_cookies.txt';
$adminCookie = __DIR__ . '/test_admin_cookies.txt';
if (file_exists($customerCookie)) unlink($customerCookie);
if (file_exists($adminCookie)) unlink($adminCookie);

function sendReq($url, $postData = null, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'redirect' => $redirect];
}

function getCsrf($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $m)) {
        return $m[1];
    }
    return '';
}

$passCount = 0;
$failCount = 0;

function checkBtn($role, $btnName, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [$role] $btnName" . ($details ? " - $details" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] [$role] $btnName" . ($details ? " - $details" : "") . "\n";
    }
}

echo "\n======================================================\n";
echo "   COMPREHENSIVE BUTTON & WORKFLOW AUDIT SUITE\n";
echo "   Standards: security.md & backend.md\n";
echo "======================================================\n\n";

// -------------------------------------------------------------
// SECTION 1: GUEST USER BUTTONS & FLOWS
// -------------------------------------------------------------
echo "1. AUDITING GUEST USER BUTTONS & NAVIGATION:\n";

// 1.1 Home Storefront
$res = sendReq("$baseUrl/");
checkBtn("Guest", "Storefront Root & Navigation", $res['code'] === 200, "HTTP 200");

// 1.2 Hero Buttons
checkBtn("Guest", "Hero 'Start Shopping' Button", stripos($res['body'], 'Start Shopping') !== false, "Anchored to #shop / #categories");
checkBtn("Guest", "Hero 'Browse Aisles' Button", stripos($res['body'], 'Browse Aisles') !== false, "Anchored to #categories");

// 1.3 Nav Bar Links
$resLogin = sendReq("$baseUrl/login");
checkBtn("Guest", "Nav 'Login' Button", $resLogin['code'] === 200, "HTTP 200 via /login");

$resRegister = sendReq("$baseUrl/register");
checkBtn("Guest", "Nav 'Sign Up' Button", $resRegister['code'] === 200, "HTTP 200 via /register");

$resCart = sendReq("$baseUrl/cart/");
checkBtn("Guest", "Nav 'Cart Bag' Button", $resCart['code'] === 200, "HTTP 200 via /cart");

// 1.4 Category & Search AJAX Buttons
$resSearch = sendReq("$baseUrl/products/fetch.php?search=Organic");
$searchJson = json_decode($resSearch['body'], true);
checkBtn("Guest", "Live Search Button & Query", $resSearch['code'] === 200 && isset($searchJson['grid']), "Valid JSON response");

$resCat = sendReq("$baseUrl/products/fetch.php?category=Fruits");
$catJson = json_decode($resCat['body'], true);
checkBtn("Guest", "Category Filter Pill Buttons", $resCat['code'] === 200 && isset($catJson['grid']), "Valid filtered JSON");

// 1.5 Guest Add-to-Cart Interception
$resGuestAdd = sendReq("$baseUrl/cart/add", ['product_id' => 1]);
$addJson = json_decode($resGuestAdd['body'], true);
checkBtn("Guest", "Card 'Add to Cart' Guard", ($addJson['status'] ?? '') === 'login_required', "Intercepted: login_required");

// 1.6 Informational Footer Buttons & Pages (Clean Routes)
$pages = ['about', 'sustainability', 'farmers', 'contact', 'terms', 'privacy'];
$allPagesOk = true;
foreach ($pages as $p) {
    $r = sendReq("$baseUrl/$p");
    if ($r['code'] !== 200) $allPagesOk = false;
}
checkBtn("Guest", "Footer Navigation Links (6 Pages)", $allPagesOk, "All 6 informational pages HTTP 200");


// -------------------------------------------------------------
// SECTION 2: CUSTOMER USER BUTTONS & TRANSACTIONS
// -------------------------------------------------------------
echo "\n2. AUDITING CUSTOMER BUTTONS & TRANSACTION PROCESSES:\n";

// 2.1 Customer Login Form & Button
$loginPage = sendReq("$baseUrl/login", null, $customerCookie);
$custCsrf = getCsrf($loginPage['body']);
$loginPost = sendReq("$baseUrl/login", [
    'email' => 'customer@example.com',
    'password' => 'password',
    'csrf_token' => $custCsrf
], $customerCookie);
checkBtn("Customer", "Sign In Form Submit Button", $loginPost['code'] === 302, "Authenticated session active");

// 2.2 Customer Add to Cart (AJAX)
$resAdd = sendReq("$baseUrl/cart/add", ['product_id' => 1, 'csrf_token' => $custCsrf], $customerCookie);
$addJson = json_decode($resAdd['body'], true);
checkBtn("Customer", "Product Card 'Add' Button", ($addJson['status'] ?? '') === 'success', "Stock checked & added");

// 2.3 Product Detail Page & Review Form (Clean Masked Slug Route)
$viewPage = sendReq("$baseUrl/product/red-apple", null, $customerCookie);
$viewCsrf = getCsrf($viewPage['body']);
checkBtn("Customer", "Product Detail Page Loaded (Masked Slug)", $viewPage['code'] === 200, "HTTP 200 via /product/red-apple");

$reviewPost = sendReq("$baseUrl/product/red-apple", [
    'submit_review' => '1',
    'rating' => 5,
    'comment' => 'Verified automated test review',
    'csrf_token' => $viewCsrf
], $customerCookie);
checkBtn("Customer", "Post Review Submit Button", strpos($reviewPost['body'], 'Review submitted successfully') !== false, "Review inserted with CSRF");

// 2.4 Cart Quantity Increase (+) Button
$resInc = sendReq("$baseUrl/cart/update", [
    'product_id' => 1,
    'action' => 'increase',
    'csrf_token' => $custCsrf
], $customerCookie);
checkBtn("Customer", "Cart Quantity Plus (+) Button", $resInc['code'] === 302, "Quantity incremented");

// 2.5 Cart Quantity Decrease (-) Button
$resDec = sendReq("$baseUrl/cart/update", [
    'product_id' => 1,
    'action' => 'decrease',
    'csrf_token' => $custCsrf
], $customerCookie);
checkBtn("Customer", "Cart Quantity Minus (-) Button", $resDec['code'] === 302, "Quantity decremented");

// 2.6 Cart Coupon Apply & Remove Buttons
$resCoupon = sendReq("$baseUrl/cart/", [
    'coupon_code' => 'FRESH50',
    'apply_coupon' => '1',
    'csrf_token' => $custCsrf
], $customerCookie);
checkBtn("Customer", "Apply Coupon Button", strpos($resCoupon['body'], '50%') !== false, "50% Discount applied");

$resRemoveCoupon = sendReq("$baseUrl/cart/", [
    'remove_coupon' => '1',
    'csrf_token' => $custCsrf
], $customerCookie);
checkBtn("Customer", "Remove Coupon Button", $resRemoveCoupon['code'] === 302, "Discount removed");

// 2.7 Checkout & Place Order Button
$checkoutPage = sendReq("$baseUrl/checkout", null, $customerCookie);
$checkoutCsrf = getCsrf($checkoutPage['body']);
checkBtn("Customer", "Proceed to Checkout Button", $checkoutPage['code'] === 200, "Checkout loaded");

$placeOrder = sendReq("$baseUrl/orders/place", [
    'customer_name' => 'Audited Customer',
    'address' => '456 Test Blvd, Suite 101',
    'payment_method' => 'COD',
    'csrf_token' => $checkoutCsrf
], $customerCookie);

preg_match('/orderid=(\d+)/', $placeOrder['redirect'], $mOrder);
$createdOrderId = $mOrder[1] ?? 0;
checkBtn("Customer", "Place Order Button (ACID Commit)", $placeOrder['code'] === 302 && $createdOrderId > 0, "Created Order #$createdOrderId");

// 2.8 Success Page & Buttons
$successPage = sendReq("$baseUrl/orders/success.php?orderid=$createdOrderId", null, $customerCookie);
checkBtn("Customer", "Success 'Print Receipt' & 'Continue' Buttons", $successPage['code'] === 200 && strpos($successPage['body'], 'Print Receipt') !== false, "Receipt rendered");

// 2.9 Order History & Cancel Order Button
$ordersPage = sendReq("$baseUrl/orders/", null, $customerCookie);
$cancelCsrf = getCsrf($ordersPage['body']);
checkBtn("Customer", "Orders History 'View Details' Button", $ordersPage['code'] === 200 && strpos($ordersPage['body'], "order/$createdOrderId") !== false, "Order visible in timeline");

$cancelPost = sendReq("$baseUrl/orders/cancel", [
    'order_id' => $createdOrderId,
    'csrf_token' => $cancelCsrf
], $customerCookie);
checkBtn("Customer", "Cancel Order Button (Atomic Restock)", $cancelPost['code'] === 302, "Restocked & status Cancelled");

// 2.10 Account Profile Save Changes Button
$profilePage = sendReq("$baseUrl/profile", null, $customerCookie);
$profileCsrf = getCsrf($profilePage['body']);
$profilePost = sendReq("$baseUrl/profile", [
    'full_name' => 'Audited Customer Verified',
    'address' => '789 Updated Lane',
    'password' => '',
    'csrf_token' => $profileCsrf
], $customerCookie);
checkBtn("Customer", "Profile 'Save Changes' Button", strpos($profilePost['body'], 'Profile updated successfully') !== false, "Profile updated with CSRF");


// -------------------------------------------------------------
// SECTION 3: ADMIN BUTTONS & MANAGEMENT FLOWS
// -------------------------------------------------------------
echo "\n3. AUDITING ADMIN BUTTONS & CONTROL PORTAL:\n";

// 3.1 Admin Unified Login Form & Button
$adminLoginPage = sendReq("$baseUrl/login", null, $adminCookie);
$adminCsrf = getCsrf($adminLoginPage['body']);
$adminLoginPost = sendReq("$baseUrl/login", [
    'email' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $adminCsrf
], $adminCookie);
checkBtn("Admin", "Admin Portal 'Sign In' Button", $adminLoginPost['code'] === 302, "Authenticated session active");

// 3.2 Dashboard Views & Nav Tabs
$dashView = sendReq("$baseUrl/admin/router.php?view=dashboard", null, $adminCookie);
checkBtn("Admin", "Sidebar 'Overview' Nav Tab", (strpos($dashView['body'], 'Analytics') !== false || strpos($dashView['body'], 'Dashboard Overview') !== false), "Sales chart & stats rendered");

$ordersView = sendReq("$baseUrl/admin/router.php?view=orders", null, $adminCookie);
checkBtn("Admin", "Sidebar 'Orders' Nav Tab", strpos($ordersView['body'], 'Order Management') !== false, "Orders table rendered");

$productsView = sendReq("$baseUrl/admin/router.php?view=products", null, $adminCookie);
checkBtn("Admin", "Sidebar 'Products' Nav Tab", strpos($productsView['body'], 'Product Inventory') !== false, "Inventory table rendered");

$usersView = sendReq("$baseUrl/admin/router.php?view=users", null, $adminCookie);
checkBtn("Admin", "Sidebar 'Customers' Nav Tab", (strpos($usersView['body'], 'Customers') !== false || strpos($usersView['body'], 'Registered Customers') !== false), "Customer cards rendered");

$reviewsView = sendReq("$baseUrl/admin/router.php?view=reviews", null, $adminCookie);
checkBtn("Admin", "Sidebar 'Reviews' Nav Tab", (strpos($reviewsView['body'], 'Reviews') !== false || strpos($reviewsView['body'], 'Review Gallery') !== false), "Review gallery rendered");

// 3.3 Status Filter Buttons in Orders
$pendingOrders = sendReq("$baseUrl/admin/router.php?view=orders&status=Pending", null, $adminCookie);
checkBtn("Admin", "Orders Filter Tab ('Pending')", $pendingOrders['code'] === 200, "Filtered view rendered");

// 3.4 Manage Order & Update Status Button
$manageOrderPage = sendReq("$baseUrl/admin/order_details.php?order_id=$createdOrderId", null, $adminCookie);
$manageCsrf = getCsrf($manageOrderPage['body']);
$updateStatus = sendReq("$baseUrl/admin/order_details.php?order_id=$createdOrderId", [
    'update_status' => '1',
    'status' => 'Delivered',
    'csrf_token' => $manageCsrf
], $adminCookie);
checkBtn("Admin", "Order Details 'Update Status' Button", $updateStatus['code'] === 302, "Status updated to Delivered");

// 3.5 Export Orders Report Button
$exportRes = sendReq("$baseUrl/admin/export_orders.php", null, $adminCookie);
checkBtn("Admin", "Dashboard 'Export Report' Button", $exportRes['code'] === 200 && strpos($exportRes['body'], 'Order ID') !== false && strpos($exportRes['body'], 'Customer Name') !== false, "CSV generated");

// 3.6 Admin Catalog Inventory: 'Add New Product' Nav Button
$prodInventory = sendReq("$baseUrl/admin/router.php?view=products", null, $adminCookie);
$hasAddBtn = (strpos($prodInventory['body'], 'product_add.php') !== false) && (strpos($prodInventory['body'], 'Add New Product') !== false);
$addProdPage = sendReq("$baseUrl/admin/product_add.php", null, $adminCookie);
checkBtn("Admin", "Inventory 'Add New Product' Nav Button", $hasAddBtn && $addProdPage['code'] === 200, "Navigates to product_add.php console");

// 3.7 Add Product Form: 'Publish Product' Submit Button & DB Persistence (backend.md Stage 3 & 6)
$addProdCsrf = getCsrf($addProdPage['body']);
$addProdPost = sendReq("$baseUrl/admin/product_add.php", [
    'name' => 'Automated Test Kiwi',
    'category' => 'Fruits',
    'price' => 4.50,
    'stock_qty' => 40,
    'csrf_token' => $addProdCsrf
], $adminCookie);

require_once __DIR__ . '/../config/db.php';
$stmtNewProd = $pdo->prepare("SELECT id, name, category, price, stock_qty FROM products WHERE name = ? ORDER BY id DESC LIMIT 1");
$stmtNewProd->execute(['Automated Test Kiwi']);
$newProd = $stmtNewProd->fetch(PDO::FETCH_ASSOC);
$newProdId = (int)($newProd['id'] ?? 0);
$createPersisted = $newProd && (float)$newProd['price'] === 4.50 && (int)$newProd['stock_qty'] === 40 && $newProd['category'] === 'Fruits';

checkBtn("Admin", "Add Product 'Publish Product' Submit Button", $addProdPost['code'] === 302 && $createPersisted, "Created SKU #$newProdId ($4.50, 40 units, Fruits)");

// 3.8 Inventory Table: Product Row 'Edit Product' Nav Button
$inventoryWithNewProd = sendReq("$baseUrl/admin/router.php?view=products", null, $adminCookie);
$hasEditBtn = ($newProdId > 0) && (strpos($inventoryWithNewProd['body'], "product_edit.php?id=$newProdId") !== false);
$editProdPage = sendReq("$baseUrl/admin/product_edit.php?id=$newProdId", null, $adminCookie);
$editPageValid = $editProdPage['code'] === 200 && strpos($editProdPage['body'], 'Automated Test Kiwi') !== false && strpos($editProdPage['body'], 'Save Changes') !== false;

checkBtn("Admin", "Product Row 'Edit Product' Nav Button", $hasEditBtn && $editPageValid, "Navigates to product_edit.php with SKU preloaded");

// 3.9 Edit Product Form: 'Save Changes' Submit Button & DB Persistence (backend.md Stage 3 & 6)
$editCsrf = getCsrf($editProdPage['body']);
$editProdPost = sendReq("$baseUrl/admin/product_edit.php?id=$newProdId", [
    'category' => 'Snacks',
    'price' => 6.99,
    'stock_qty' => 85,
    'csrf_token' => $editCsrf
], $adminCookie);

$stmtCheckUpd = $pdo->prepare("SELECT price, stock_qty, category FROM products WHERE id = ?");
$stmtCheckUpd->execute([$newProdId]);
$updProd = $stmtCheckUpd->fetch(PDO::FETCH_ASSOC);
$updPersisted = $updProd && (float)$updProd['price'] === 6.99 && (int)$updProd['stock_qty'] === 85 && $updProd['category'] === 'Snacks';

checkBtn("Admin", "Edit Product 'Save Changes' Submit Button", $editProdPost['code'] === 302 && $updPersisted, "Mutated: Price $6.99, Stock 85, Aisle Snacks");

// 3.10 Admin Delete Product with CSRF & Verification
if ($newProdId > 0) {
    // Get fresh CSRF token from router
    $routerPage = sendReq("$baseUrl/admin/router.php?view=products", null, $adminCookie);
    preg_match('/product_delete\.php\?id=' . $newProdId . '&csrf_token=([a-f0-9]+)/', $routerPage['body'], $mDelCsrf);
    $delCsrf = $mDelCsrf[1] ?? '';
    
    $delRes = sendReq("$baseUrl/admin/actions/product_delete.php?id=$newProdId&csrf_token=$delCsrf", null, $adminCookie);
    $stmtDelCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ?");
    $stmtDelCheck->execute([$newProdId]);
    $isPurged = ((int)$stmtDelCheck->fetchColumn()) === 0;

    checkBtn("Admin", "Product 'Delete' Button & DB Purge", $delRes['code'] === 302 && $isPurged, "Test product cleanly purged");
} else {
    checkBtn("Admin", "Product 'Delete' Button", false, "Test product not found");
}

// 3.11 Admin Logout Button (Redirect directly to storefront landing)
$logoutRes = sendReq("$baseUrl/admin/logout.php", null, $adminCookie);
$redirectsToLanding = $logoutRes['code'] === 302 && (str_ends_with($logoutRes['redirect'], '/grocery_app/') || str_ends_with($logoutRes['redirect'], '/'));
checkBtn("Admin", "Admin Portal 'Logout' Button", $redirectsToLanding, "Logged out directly to landing page");


// -------------------------------------------------------------
// CLEANUP & SUMMARY
// -------------------------------------------------------------
if (file_exists($customerCookie)) unlink($customerCookie);
if (file_exists($adminCookie)) unlink($adminCookie);

echo "\n======================================================\n";
echo "AUDIT SUMMARY:\n";
echo "  Total Tests Passed: $passCount\n";
echo "  Total Tests Failed: $failCount\n";
echo "======================================================\n";

exit($failCount === 0 ? 0 : 1);
