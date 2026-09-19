<?php
// scripts/verify_api.php
// Integration test suite for the REST API endpoints.

// Auto-detect base host and URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . '/YEAR%203/Mini%20Project%20ADS/grocery_app/api/v1';

echo "<h1>FreshCart API Verification</h1>";
echo "<p>Testing Base URL: <code>$baseUrl</code></p>";

function test($name, $url, $method = 'GET', $data = [], $token = null)
{
    echo "Testing: <b>" . htmlspecialchars($name) . "</b>... ";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($json['success']) && $json['success']) {
        echo "<span style='color:green'>✅ OK</span><br>";
        return $json['data'] ?? [];
    } else {
        echo "<span style='color:red'>❌ FAILED ($httpCode)</span><br>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        return false;
    }
}

// 1. Test Login
$loginData = test('User Login', "$baseUrl/auth/login.php", 'POST', [
    'email' => 'customer@example.com',
    'password' => 'password'
]);

$token = $loginData['token'] ?? null;

// 2. Test Get Products
test('Get Products', "$baseUrl/products/index.php");

// 3. Test Cart Operations
if ($token) {
    test('Get Cart', "$baseUrl/cart/index.php", 'GET', [], $token);
    test('Add to Cart', "$baseUrl/cart/add.php", 'POST', ['product_id' => 1, 'quantity' => 2], $token);
    test('Update Cart', "$baseUrl/cart/update.php", 'POST', ['product_id' => 1, 'quantity' => 3], $token);
    test('Get Order History', "$baseUrl/orders/history.php", 'GET', [], $token);
} else {
    echo "<p style='color:orange'>⚠️ Skipping authenticated endpoints (Login did not return token. Ensure test user customer@example.com exists).</p>";
}
?>
