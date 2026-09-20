<?php
/**
 * End-to-end test for clean URL architecture, CSRF defense, and canonical redirection
 */
$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app';
$cookieFile = __DIR__ . '/test_cookies.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function makeReq($url, $postData = null) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
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

function extractCsrfToken($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "=== E2E Storefront Web Verification ===\n";
$allPass = true;

// 1. Home (Clean URL)
$res = makeReq("$baseUrl/");
$ok = $res['code'] === 200;
echo "1. Clean Home URL: HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 1b. Canonical Redirect of index.php
$res = makeReq("$baseUrl/index.php");
$ok = in_array($res['code'], [301, 302]);
echo "1b. Canonical Redirect of index.php: HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 1c. Canonical Redirect of auth/login.php
$res = makeReq("$baseUrl/auth/login.php");
$ok = $res['code'] === 301 && str_contains($res['redirect'], '/login');
echo "1c. Canonical Redirect of auth/login.php -> /login: HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 2. Fetch Clean Login Route & CSRF
$loginPage = makeReq("$baseUrl/login");
$token = extractCsrfToken($loginPage['body']);

// 2b. Customer Login with CSRF Token
$res = makeReq("$baseUrl/login", [
    'email' => 'customer@example.com',
    'password' => 'password',
    'csrf_token' => $token
]);
$ok = $res['code'] === 302;
echo "2. Customer Login (/login): HTTP {$res['code']} -> Redirect: {$res['redirect']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 3. Clean Profile Route
$res = makeReq("$baseUrl/profile");
$ok = $res['code'] === 200 && strpos($res['body'], 'Account Settings') !== false;
echo "3. Account Profile (/profile): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 4. Products View (Masked Clean URL)
$res = makeReq("$baseUrl/product/red-apple");
$ok = $res['code'] === 200 && strpos($res['body'], 'Add to Cart') !== false;
echo "4. Product View (/product/red-apple): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 5. Products Fetch (AJAX)
$res = makeReq("$baseUrl/products/fetch.php?search=Apple");
$json = json_decode($res['body'], true);
$ok = $res['code'] === 200 && isset($json['grid']);
echo "5. Products Fetch (products/fetch.php): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 6. Add to Cart (authenticated via clean route cart/add)
$res = makeReq("$baseUrl/cart/add", ['product_id' => 1]);
$json = json_decode($res['body'], true);
$ok = $res['code'] === 200 && ($json['status'] ?? '') === 'success';
echo "6. Cart Add (/cart/add): HTTP {$res['code']} (Status: " . ($json['status'] ?? 'unknown') . ")" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 7. Cart View (Clean URL)
$res = makeReq("$baseUrl/cart/");
$ok = $res['code'] === 200 && strpos($res['body'], 'Shopping Bag') !== false;
echo "7. Cart Page (/cart/): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 8. Orders Checkout View & Extract CSRF (Clean Route)
$checkoutPage = makeReq("$baseUrl/checkout");
$checkoutCsrf = extractCsrfToken($checkoutPage['body']);
$ok = $checkoutPage['code'] === 200 && strpos($checkoutPage['body'], 'Secure Checkout') !== false;
echo "8. Orders Checkout (/checkout): HTTP {$checkoutPage['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 9. Orders Place with CSRF (Clean Route)
$res = makeReq("$baseUrl/orders/place", [
    'customer_name' => 'John Customer',
    'address' => '123 Main St, Test City',
    'payment_method' => 'COD',
    'csrf_token' => $checkoutCsrf
]);
$ok = $res['code'] === 302 && strpos($res['redirect'], 'success.php') !== false;
echo "9. Orders Place (/orders/place): HTTP {$res['code']} -> Redirect: {$res['redirect']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 10. Orders History (Clean Route)
$res = makeReq("$baseUrl/orders/");
$ok = $res['code'] === 200 && strpos($res['body'], 'My Orders') !== false;
echo "10. Orders History (/orders/): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 11. Category Pill Async Route (Without #all-foods hash jump)
$res = makeReq("$baseUrl/?category=Bakery");
$ok = $res['code'] === 200 
    && strpos($res['body'], 'Bakery Aisle') !== false 
    && strpos($res['body'], 'href="?category=Bakery#all-foods"') === false;
echo "11. Category Pill Route (?category=Bakery, in-place no-hash): HTTP {$res['code']}" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

// 12. Unified Admin Login (/login -> /admin/)
$adminCookie = __DIR__ . '/test_admin_unified.txt';
if (file_exists($adminCookie)) unlink($adminCookie);
$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$loginHtml = curl_exec($ch);
curl_close($ch);
$adminCsrf = extractCsrfToken($loginHtml);

$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'email' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $adminCsrf
]);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$adminRedirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
$adminCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if (file_exists($adminCookie)) unlink($adminCookie);

$ok = $adminCode === 302 && str_contains($adminRedirect, '/admin');
echo "12. Unified Admin Login (/login -> /admin/): HTTP $adminCode -> Redirect: $adminRedirect" . ($ok ? " [PASS]\n" : " [FAIL]\n");
if (!$ok) $allPass = false;

if (file_exists($cookieFile)) unlink($cookieFile);
echo "=== All Tests Complete ===\n";
exit($allPass ? 0 : 1);
