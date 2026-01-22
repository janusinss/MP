<?php
// verify_api.php
$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app/api/v1';

function test($name, $url, $method = 'GET', $data = [], $token = null)
{
    echo "Testing: $name... ";

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
        echo "✅ OK\n";
        return $json['data'] ?? [];
    } else {
        echo "❌ FAILED ($httpCode)\n";
        print_r($json);
        return false; // Stop or continue?
    }
}

// 1. Test Login
// Need a valid user. Assuming 'janusdominic0@gmail.com' exists or let's try a known user
// We assume there's a user with id=1? Or we fail.
// Let's use hardcoded credentials if known, or fail gracefully.
// We'll rely on the user running this to know the DB.
// Let's try grabbing a user from DB first? No, script is external.
// HARDCODED TEST CREDENTIALS (Update if needed)
$email = 'janusdominic0@gmail.com';
$pass = 'password123'; // Guess? Or let's create a temp user?

// For this verification to work without guessing password, I will just CREATE a temp user in this script using DB access
include 'db.php';
$email = 'api_test_' . time() . '@test.com';
$pass = 'testpass';
$hash = password_hash($pass, PASSWORD_DEFAULT);
// 2. Create Temp User
try {
    $testToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, address, api_token) VALUES ('API Tester', ?, ?, '123 Test St', ?)");
    $stmt->execute([$email, $hash, $testToken]);
    $userId = $pdo->lastInsertId();
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        // Ignore duplicate if user exists from diff run
        // Fetch existing
        $stmt = $pdo->prepare("SELECT id, api_token FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        $userId = $u['id'];
        $testToken = $u['api_token'];
    } else {
        die("Setup Error: " . $e->getMessage());
    }
}

// --- START TESTS ---

// 1. Auth
$loginData = test("Login", "$baseUrl/auth/login.php", 'POST', ['email' => $email, 'password' => $pass]);
if (!$loginData)
    exit("Login failed. Stopping.\n");

$token = $loginData['token'];
echo "Got Token: " . substr($token, 0, 10) . "...\n";

// 2. Prods
test("List Products", "$baseUrl/products/index.php");

// 3. Cart Add
// Need a valid product ID. Let's assume ID 1 exists.
test("Add to Cart", "$baseUrl/cart/add.php", 'POST', ['product_id' => 1, 'quantity' => 2], $token);

// 4. Cart Index
test("Get Cart", "$baseUrl/cart/index.php", 'GET', [], $token);

// 5. Create Order
test("Checkout Order", "$baseUrl/orders/create.php", 'POST', ['address' => '123 API St'], $token);

// 6. History
test("Order History", "$baseUrl/orders/history.php", 'GET', [], $token);

echo "\nverification Complete.\n";
?>