<?php
// scripts/verify_api.php
// Integration test suite for the REST API endpoints.

require_once __DIR__ . '/../config/db.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . '/YEAR%203/Mini%20Project%20ADS/grocery_app/api/v1';

$isCli = (php_sapi_name() === 'cli');
$allPass = true;

function logOut($msg, $isCli) {
    if ($isCli) {
        echo strip_tags($msg) . "\n";
    } else {
        echo $msg . "<br>";
    }
}

logOut("=== FreshCart REST API Verification Suite ===", $isCli);
logOut("Base URL: $baseUrl", $isCli);

function apiReq($url, $method = 'GET', $data = [], $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    return ['code' => $httpCode, 'json' => $json, 'raw' => $response];
}

// 1. Test Customer Registration (CRIT-03)
$regEmail = 'qa_test_' . time() . '_' . rand(100, 999) . '@example.com';
$regRes = apiReq("$baseUrl/auth/register.php", 'POST', [
    'full_name' => 'QA Automated Customer',
    'email' => $regEmail,
    'password' => 'Secur3Passw0rd!',
    'address' => '742 Evergreen Terrace'
]);

if ($regRes['code'] === 201 && !empty($regRes['json']['data']['token'])) {
    logOut("  [PASS] 1. REST API Customer Registration (HTTP 201 Created)", $isCli);
    $newUserId = $regRes['json']['data']['user_id'] ?? null;
    $newRegToken = $regRes['json']['data']['token'] ?? null;
} else {
    logOut("  [FAIL] 1. REST API Customer Registration (HTTP {$regRes['code']})", $isCli);
    $allPass = false;
    $newUserId = null;
    $newRegToken = null;
}

// 1b. Duplicate Email Rejection
$dupRes = apiReq("$baseUrl/auth/register.php", 'POST', [
    'full_name' => 'Duplicate QA',
    'email' => $regEmail,
    'password' => 'Secur3Passw0rd!'
]);
if ($dupRes['code'] === 409) {
    logOut("  [PASS] 1b. Duplicate Email Rejection (HTTP 409 Conflict)", $isCli);
} else {
    logOut("  [FAIL] 1b. Duplicate Email Rejection (HTTP {$dupRes['code']})", $isCli);
    $allPass = false;
}

// 1c. Short Password Rejection (< 8 chars)
$shortPassRes = apiReq("$baseUrl/auth/register.php", 'POST', [
    'full_name' => 'Short Pass',
    'email' => 'shortpass_' . time() . '@example.com',
    'password' => '123'
]);
if ($shortPassRes['code'] === 400) {
    logOut("  [PASS] 1c. Short Password Validation (HTTP 400 Bad Request)", $isCli);
} else {
    logOut("  [FAIL] 1c. Short Password Validation (HTTP {$shortPassRes['code']})", $isCli);
    $allPass = false;
}

// 2. Test Login
$loginRes = apiReq("$baseUrl/auth/login.php", 'POST', [
    'email' => 'customer@example.com',
    'password' => 'password'
]);

if ($loginRes['code'] === 200 && !empty($loginRes['json']['data']['token'])) {
    logOut("  [PASS] 2. Customer Authentication Login (HTTP 200 OK)", $isCli);
    $customerToken = $loginRes['json']['data']['token'];
} else {
    logOut("  [FAIL] 2. Customer Authentication Login (HTTP {$loginRes['code']})", $isCli);
    $allPass = false;
    $customerToken = $newRegToken;
}

// 3. Test Products Endpoint
$prodRes = apiReq("$baseUrl/products/index.php");
if ($prodRes['code'] === 200 && is_array($prodRes['json']['data'] ?? null)) {
    logOut("  [PASS] 3. Products Catalog Retrieval (HTTP 200 OK)", $isCli);
} else {
    logOut("  [FAIL] 3. Products Catalog Retrieval (HTTP {$prodRes['code']})", $isCli);
    $allPass = false;
}

// 4. Cart Lifecycle & Dedicated Delete Endpoint (WARN-01)
if ($customerToken) {
    // 4a. Add Item
    $addRes = apiReq("$baseUrl/cart/add.php", 'POST', ['product_id' => 1, 'quantity' => 2], $customerToken);
    if ($addRes['code'] === 200) {
        logOut("  [PASS] 4a. Cart Add Item (HTTP 200 OK)", $isCli);
    } else {
        logOut("  [FAIL] 4a. Cart Add Item (HTTP {$addRes['code']})", $isCli);
        $allPass = false;
    }

    // 4b. Get Cart
    $getRes = apiReq("$baseUrl/cart/index.php", 'GET', [], $customerToken);
    if ($getRes['code'] === 200) {
        logOut("  [PASS] 4b. Cart Get Items (HTTP 200 OK)", $isCli);
    } else {
        logOut("  [FAIL] 4b. Cart Get Items (HTTP {$getRes['code']})", $isCli);
        $allPass = false;
    }

    // 4c. Update Cart
    $upRes = apiReq("$baseUrl/cart/update.php", 'POST', ['product_id' => 1, 'quantity' => 4], $customerToken);
    if ($upRes['code'] === 200) {
        logOut("  [PASS] 4c. Cart Update Quantity (HTTP 200 OK)", $isCli);
    } else {
        logOut("  [FAIL] 4c. Cart Update Quantity (HTTP {$upRes['code']})", $isCli);
        $allPass = false;
    }

    // 4d. Dedicated Cart Item Delete Endpoint (WARN-01)
    $delRes = apiReq("$baseUrl/cart/delete.php", 'POST', ['product_id' => 1], $customerToken);
    if ($delRes['code'] === 200 && ($delRes['json']['success'] ?? false)) {
        logOut("  [PASS] 4d. Dedicated Cart Item Deletion (HTTP 200 OK)", $isCli);
    } else {
        logOut("  [FAIL] 4d. Dedicated Cart Item Deletion (HTTP {$delRes['code']})", $isCli);
        $allPass = false;
    }

    // 4e. Order History
    $histRes = apiReq("$baseUrl/orders/history.php", 'GET', [], $customerToken);
    if ($histRes['code'] === 200) {
        logOut("  [PASS] 4e. Customer Order History (HTTP 200 OK)", $isCli);
    } else {
        logOut("  [FAIL] 4e. Customer Order History (HTTP {$histRes['code']})", $isCli);
        $allPass = false;
    }
} else {
    logOut("  [SKIP] Cart tests skipped (no auth token)", $isCli);
    $allPass = false;
}

// Cleanup QA registered user
if ($newUserId) {
    try {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$newUserId]);
        logOut("  [PASS] 5. Zero-leakage cleanup of temporary QA user", $isCli);
    } catch (Exception $e) {}
}

logOut("=== API Verification Result: " . ($allPass ? "ALL PASS" : "FAILED") . " ===", $isCli);

if ($isCli) {
    exit($allPass ? 0 : 1);
}
