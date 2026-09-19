<?php
/**
 * End-to-end test for restructured storefront workflows with CSRF & canonical URL verification
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

// 1. Home (Clean URL)
$res = makeReq("$baseUrl/");
echo "1. Clean Home URL: HTTP {$res['code']}" . ($res['code'] === 200 ? " [PASS]\n" : " [FAIL]\n");

// 1b. Canonical Redirect of index.php
$res = makeReq("$baseUrl/index.php");
echo "1b. Canonical Redirect of index.php: HTTP {$res['code']}" . (in_array($res['code'], [301, 302]) ? " [PASS]\n" : " [FAIL]\n");

// 2. Fetch Login & CSRF
$loginPage = makeReq("$baseUrl/auth/login.php");
$token = extractCsrfToken($loginPage['body']);

// 2b. Customer Login with CSRF Token
$res = makeReq("$baseUrl/auth/login.php", [
    'email' => 'customer@example.com',
    'password' => 'password',
    'csrf_token' => $token
]);
echo "2. Customer Login (auth/login.php): HTTP {$res['code']} -> Redirect: {$res['redirect']}" . ($res['code'] === 302 ? " [PASS]\n" : " [FAIL]\n");

// 3. Profile
$res = makeReq("$baseUrl/account/profile.php");
echo "3. Account Profile (account/profile.php): HTTP {$res['code']}" . ($res['code'] === 200 && strpos($res['body'], 'Account Settings') !== false ? " [PASS]\n" : " [FAIL]\n");

// 4. Products View
$res = makeReq("$baseUrl/products/view.php?id=1");
echo "4. Product View (products/view.php?id=1): HTTP {$res['code']}" . ($res['code'] === 200 && strpos($res['body'], 'Add to Cart') !== false ? " [PASS]\n" : " [FAIL]\n");

// 5. Products Fetch (AJAX)
$res = makeReq("$baseUrl/products/fetch.php?search=Apple");
$json = json_decode($res['body'], true);
echo "5. Products Fetch (products/fetch.php): HTTP {$res['code']}" . ($res['code'] === 200 && isset($json['grid']) ? " [PASS]\n" : " [FAIL]\n");

// 6. Add to Cart (authenticated)
$res = makeReq("$baseUrl/cart/add.php", ['product_id' => 1]);
$json = json_decode($res['body'], true);
echo "6. Cart Add (cart/add.php): HTTP {$res['code']} (Status: " . ($json['status'] ?? 'unknown') . ")" . (($json['status'] ?? '') === 'success' ? " [PASS]\n" : " [FAIL]\n");

// 7. Cart View
$res = makeReq("$baseUrl/cart/index.php");
echo "7. Cart Page (cart/index.php): HTTP {$res['code']}" . ($res['code'] === 200 && strpos($res['body'], 'Shopping Bag') !== false ? " [PASS]\n" : " [FAIL]\n");

// 8. Orders Checkout View & Extract CSRF
$checkoutPage = makeReq("$baseUrl/orders/checkout.php");
$checkoutCsrf = extractCsrfToken($checkoutPage['body']);
echo "8. Orders Checkout (orders/checkout.php): HTTP {$checkoutPage['code']}" . ($checkoutPage['code'] === 200 && strpos($checkoutPage['body'], 'Secure Checkout') !== false ? " [PASS]\n" : " [FAIL]\n");

// 9. Orders Place with CSRF
$res = makeReq("$baseUrl/orders/place.php", [
    'customer_name' => 'John Customer',
    'address' => '123 Main St, Test City',
    'payment_method' => 'COD',
    'csrf_token' => $checkoutCsrf
]);
echo "9. Orders Place (orders/place.php): HTTP {$res['code']} -> Redirect: {$res['redirect']}" . ($res['code'] === 302 && strpos($res['redirect'], 'success.php') !== false ? " [PASS]\n" : " [FAIL]\n");

// 10. Orders History
$res = makeReq("$baseUrl/orders/index.php");
echo "10. Orders History (orders/index.php): HTTP {$res['code']}" . ($res['code'] === 200 && strpos($res['body'], 'My Orders') !== false ? " [PASS]\n" : " [FAIL]\n");

if (file_exists($cookieFile)) unlink($cookieFile);
echo "=== All Tests Complete ===\n";
