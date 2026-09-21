<?php
// scripts/verify_security_suite.php
// Comprehensive Security Standards & Negative TDD Verification (security.md)

$baseUrl = 'http://localhost/YEAR%204/Skills/targets/grocery_app';
$cookieFile = __DIR__ . '/sec_test_cookies.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq($url, $postData = null, $headers = []) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }
    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'redirect' => $redirect, 'headers' => $rawHeaders];
}

function extractCsrf($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "=========================================================\n";
echo "   APPLICATION SECURITY & NEGATIVE TDD VERIFICATION\n";
echo "=========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest($name, $condition, $details = '') {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "[PASS] $name" . ($details ? " ($details)" : "") . "\n";
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
    }
}

// 1. Direct Access Block: Database Dumps
$res = httpReq("$baseUrl/database/grocery_db.sql");
assertTest("Block Direct Access to Database Dump", $res['code'] === 403, "HTTP {$res['code']}");

// 2. Direct Access Block: Dotenv File
$res = httpReq("$baseUrl/.env");
assertTest("Block Direct Access to .env Secret File", $res['code'] === 403, "HTTP {$res['code']}");

// 3. Direct Access Block: Database Directory Indexing
$res = httpReq("$baseUrl/database/");
assertTest("Prevent Directory Listing on /database/", $res['code'] === 403, "HTTP {$res['code']}");

// 4. URL Canonicalization: index.php hidden
$res = httpReq("$baseUrl/index.php");
assertTest("Canonical 301 Redirect Stripping index.php", in_array($res['code'], [301, 302], true), "HTTP {$res['code']} -> {$res['redirect']}");

// 5. Clean URL Routing: /login and /cart
$res = httpReq("$baseUrl/login");
assertTest("Clean Extensionless Route /login Serves Auth", $res['code'] === 200 && strpos($res['body'], 'Sign In') !== false, "HTTP {$res['code']}");

// 6. Canonical 301 Redirect on Legacy .php URL
$legacyRes = httpReq("$baseUrl/auth/login.php");
assertTest("Canonical 301 Redirect Stripping .php from /auth/login.php", $legacyRes['code'] === 301 && str_contains($legacyRes['redirect'], '/login'), "HTTP {$legacyRes['code']} -> {$legacyRes['redirect']}");

// 7. CSRF Negative Test: Login Without CSRF Token
$res = httpReq("$baseUrl/login", ['email' => 'customer@example.com', 'password' => 'password']);
assertTest("Reject POST Without CSRF Token (Login)", strpos($res['body'], 'Security validation failed') !== false, "Blocked forged submission");

// 8. CSRF Negative Test: Profile Password Change Without CSRF Token
$res = httpReq("$baseUrl/profile", ['full_name' => 'Attacker', 'password' => 'hacked']);
assertTest("Reject Profile Update Without Valid Session/CSRF", $res['code'] === 302 || $res['code'] === 403, "HTTP {$res['code']}");

// 9. Legitimate Auth Handshake With CSRF Token Extraction
$loginPage = httpReq("$baseUrl/login");
$csrfToken = extractCsrf($loginPage['body']);
assertTest("Extract CSRF Token from Form", !empty($csrfToken), "Token: " . substr($csrfToken, 0, 8) . "...");

$loginPost = httpReq("$baseUrl/login", [
    'email' => 'customer@example.com',
    'password' => 'password',
    'csrf_token' => $csrfToken
]);
assertTest("Authenticated Login with CSRF Token", $loginPost['code'] === 302, "HTTP {$loginPost['code']} -> {$loginPost['redirect']}");

// 10. Verify Session & Profile Access
$profileRes = httpReq("$baseUrl/profile");
assertTest("Access Profile With Authenticated Session", $profileRes['code'] === 200 && strpos($profileRes['body'], 'Account Settings') !== false, "HTTP {$profileRes['code']}");

// 11. CSRF Protection on Order Cancellation (must be logged in first or rejected)
$cancelForged = httpReq("$baseUrl/orders/cancel", ['order_id' => 999]);
assertTest("Reject Order Cancellation Without CSRF Token", $cancelForged['code'] === 403, "HTTP {$cancelForged['code']}");

// 12. Admin Deletion Protection Without CSRF Token
$adminDeleteForged = httpReq("$baseUrl/admin/actions/product_delete.php?id=1");
assertTest("Reject Admin Delete Without CSRF Token or Admin Session", in_array($adminDeleteForged['code'], [302, 403], true), "HTTP {$adminDeleteForged['code']}");

// 13. Reconnaissance: Direct SQLite / DB Extension Access Block
$sqliteRes = httpReq("$baseUrl/database/app.sqlite");
assertTest("Block Direct Access to .sqlite Database File", $sqliteRes['code'] === 403, "HTTP {$sqliteRes['code']}");

// 14. Reconnaissance: Internal .agents Directory Shielding
$agentsRes = httpReq("$baseUrl/.agents/");
assertTest("Block Direct Access to Internal .agents Directory", $agentsRes['code'] === 403, "HTTP {$agentsRes['code']}");

// 15. Security Headers: X-Content-Type-Options, X-Frame-Options, Permissions-Policy
$homeRes = httpReq("$baseUrl/");
$hasNoSniff = stripos($homeRes['headers'], 'X-Content-Type-Options: nosniff') !== false;
$hasFrameOpt = stripos($homeRes['headers'], 'X-Frame-Options') !== false;
$hasPermPolicy = stripos($homeRes['headers'], 'Permissions-Policy') !== false;
assertTest("Enforce Defense-in-Depth Security Headers", $hasNoSniff && $hasFrameOpt && $hasPermPolicy, "nosniff, frame-options, permissions-policy verified");

// 16. Server Fingerprinting: Suppression of X-Powered-By
$noPoweredBy = stripos($homeRes['headers'], 'X-Powered-By') === false;
assertTest("Server Fingerprint Suppression (Zero X-Powered-By)", $noPoweredBy, "X-Powered-By header suppressed");

echo "\n=========================================================\n";
echo "Results: $testsPassed / $totalTests Tests Passed\n";
echo "=========================================================\n";

if (file_exists($cookieFile)) unlink($cookieFile);
exit($testsPassed === $totalTests ? 0 : 1);
