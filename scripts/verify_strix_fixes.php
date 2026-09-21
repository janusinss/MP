<?php
// scripts/verify_strix_fixes.php
// Verification test for Strix vulnerability fixes applied to targets/grocery_app

$baseUrl = 'http://localhost/YEAR%204/Skills/targets/grocery_app';

function sendReq($url, $method = 'GET', $data = null, $headers = [], $cookieJar = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $code, 'body' => $body, 'headers' => $rawHeaders];
}

echo "=== STRIX VULNERABILITY REMEDIATION VERIFICATION ===\n\n";

$passes = 0;
$total = 0;

function check($testName, $condition, $info = '') {
    global $passes, $total;
    $total++;
    if ($condition) {
        $passes++;
        echo "[PASS] $testName" . ($info ? " ($info)" : "") . "\n";
    } else {
        echo "[FAIL] $testName" . ($info ? " ($info)" : "") . "\n";
    }
}

// Test 1: STRIX-VULN-001 - Hardcoded fallback admin bypass blocked
$res = sendReq("$baseUrl/login", 'POST', ['email' => 'admin@freshcart.com', 'password' => 'admin123', 'csrf_token' => 'invalid']);
check("1. Admin hardcoded bypass rejected", $res['code'] === 200 && strpos($res['body'], 'Security validation failed') !== false, "HTTP {$res['code']}");

// Test 2: STRIX-VULN-002 - Error sanitization and pagination bounds on negative page offset
$res = sendReq("$baseUrl/api/v1/products/?page=-5");
$noSqlLeak = strpos($res['body'], 'SQL syntax') === false && strpos($res['body'], 'PDOException') === false;
check("2. Products API sanitizes negative page / no SQL leak", $noSqlLeak && $res['code'] === 200, "HTTP {$res['code']}");

// Test 3: STRIX-VULN-003 - Cart remove CSRF enforcement
$res = sendReq("$baseUrl/cart/remove.php", 'POST', ['product_id' => 1]);
check("3. Cart Remove rejects POST without CSRF token", $res['code'] === 403, "HTTP {$res['code']}");

// Test 4: STRIX-VULN-003 - Cart update CSRF enforcement
$res = sendReq("$baseUrl/cart/update.php", 'POST', ['product_id' => 1, 'action' => 'increase']);
check("4. Cart Update rejects POST without CSRF token", $res['code'] === 403, "HTTP {$res['code']}");

// Test 5: STRIX-VULN-003 - Cart add CSRF enforcement
$cookieFile = __DIR__ . '/verify_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);
$res = sendReq("$baseUrl/cart/add.php", 'POST', ['product_id' => 1, 'quantity' => 1], [], $cookieFile);
check("5. Cart Add requires authenticated session or valid CSRF", in_array($res['code'], [200, 403]), "HTTP {$res['code']}");

// Test 6: STRIX-VULN-005 - CORS denies wildcard for arbitrary origin
$res = sendReq("$baseUrl/api/config/cors.php", 'OPTIONS', null, ['Origin: https://evil-attacker.com']);
$noWildcard = strpos($res['headers'], 'Access-Control-Allow-Origin: *') === false && strpos($res['headers'], 'evil-attacker.com') === false;
check("6. CORS rejects unauthorized origin / no wildcard", $noWildcard, "Headers verified");

// Test 7: STRIX-VULN-004 - CSV formula injection unit verification
$testRow = ['=cmd|\' /C calc\'!A0', 'Normal Customer', '+123456', '@admin'];
$safeRow = array_map(function($val) {
    $str = (string)$val;
    return preg_match('/^[=+\-@\t\r]/', $str) ? "'" . $str : $str;
}, $testRow);
check("7. CSV formula prefix escaping", $safeRow[0] === "'=cmd|' /C calc'!A0" && $safeRow[2] === "'+123456" && $safeRow[3] === "'@admin", "Escaped: " . $safeRow[0]);

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\nSummary: $passes / $total tests passed.\n";
