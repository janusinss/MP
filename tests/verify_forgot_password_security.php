<?php
// tests/verify_forgot_password_security.php
// Penetration Testing & Negative Security Suite for Forgot Password Feature
// Standards: .agents/rules/penetrating-and-testing.md & .agents/rules/security.md

require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app';
$cookieFile = tempnam(sys_get_temp_dir(), 'fc_pentest_cookie_');

function pentest_req($url, $method = 'GET', $data = null, $headers = []) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    $reqHeaders = [];
    foreach ($headers as $k => $v) {
        $reqHeaders[] = "$k: $v";
    }
    if (!empty($reqHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err = curl_error($ch);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $response, 'redirect' => $redirect, 'error' => $err];
}

echo "=========================================================\n";
echo "   FORGOT PASSWORD PENETRATION & SECURITY AUDIT         \n";
echo "   Standards: .agents/rules/penetrating-and-testing.md   \n";
echo "=========================================================\n\n";

$allPassed = true;

function logTest($name, $pass, $details = '') {
    global $allPassed;
    if ($pass) {
        echo "  [PASS] $name\n";
    } else {
        echo "  [FAIL] $name" . ($details ? " ($details)" : '') . "\n";
        $allPassed = false;
    }
}

// 0. Extract valid CSRF token
$cleanRes = pentest_req("$baseUrl/forgot-password");
preg_match('/name="csrf_token" value="([^"]+)"/', $cleanRes['body'], $m);
$csrfToken = $m[1] ?? '';
logTest("1. CSRF Token extraction from /forgot-password", !empty($csrfToken));

// TEST 1: Anti-Enumeration Defense
// Submit unregistered email, verify server emits identical generic message without disclosing account absence
$anonRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
    'auth_action' => 'send_otp',
    'csrf_token' => $csrfToken,
    'email' => 'nonexistent_account_' . time() . '@nowhere.invalid'
], ['X-Requested-With' => 'XMLHttpRequest']);
$anonData = json_decode($anonRes['body'], true);
$antiEnumPass = $anonRes['code'] === 200 && ($anonData['success'] ?? false) === true && str_contains($anonData['message'], 'If an account is associated with');
logTest("2. Anti-Enumeration: Unregistered email returns identical generic response", $antiEnumPass);

// TEST 2: SQL Injection Boundary Testing
$sqliPayloads = [
    "' OR '1'='1",
    "admin' --",
    "' UNION SELECT 1,2,3,4,5 --",
    "test@example.com' OR 1=1#"
];
$sqliPassed = true;
foreach ($sqliPayloads as $payload) {
    $res = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'send_otp',
        'csrf_token' => $csrfToken,
        'email' => $payload
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    // Must be rejected as invalid email (HTTP 400) with zero SQL leakage
    if ($res['code'] !== 400 || str_contains(strtolower($res['body']), 'sql') || str_contains(strtolower($res['body']), 'syntax error')) {
        $sqliPassed = false;
        break;
    }
}
logTest("3. Injection Defense: SQLi probes rejected with clean validation & zero SQL leakage", $sqliPassed);

// TEST 3: State-Bypass Attack (CWE-285)
// Attempt to call reset_password directly without verified OTP session
$bypassRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
    'auth_action' => 'reset_password',
    'csrf_token' => $csrfToken,
    'new_password' => 'HackedPassword123!',
    'confirm_password' => 'HackedPassword123!'
], ['X-Requested-With' => 'XMLHttpRequest']);
logTest("4. Access Control: Unauthorized reset_password without verified OTP blocked (HTTP 403)", $bypassRes['code'] === 403);

// TEST 4: CSRF Defense on State Mutation (CWE-352)
$noCsrfReset = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
    'auth_action' => 'send_otp',
    'email' => 'customer@example.com'
], ['X-Requested-With' => 'XMLHttpRequest']);
logTest("5. CSRF Defense: Cross-site forged submission without token rejected (HTTP 403)", $noCsrfReset['code'] === 403);

// TEST 5: Password Complexity & Validation Enforcement
// Create test user for controlled validation
$pentestEmail = 'pt_user_' . time() . '@example.com';
$stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
$stmt->execute(['Pentest User', $pentestEmail, password_hash('OrigPass123!', PASSWORD_DEFAULT)]);
$ptUserId = (int)$pdo->lastInsertId();

try {
    // Initiate valid OTP flow
    $otpReq = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'send_otp',
        'csrf_token' => $csrfToken,
        'email' => $pentestEmail
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $otpData = json_decode($otpReq['body'], true);
    $devOtp = $otpData['dev_otp'] ?? '';

    // TEST 6: OTP Brute-Force Lockout Defense (CWE-307)
    $lockedOut = false;
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $wrongRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
            'auth_action' => 'verify_otp',
            'csrf_token' => $csrfToken,
            'otp_code' => '999999'
        ], ['X-Requested-With' => 'XMLHttpRequest']);
        if ($attempt > 5 && $wrongRes['code'] === 429) {
            $lockedOut = true;
        }
    }
    logTest("6. Anti-Brute-Force: Account locked out after >5 failed OTP guesses (HTTP 429)", $lockedOut);

    // Re-request OTP for valid completion tests
    $otpReq2 = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'send_otp',
        'csrf_token' => $csrfToken,
        'email' => $pentestEmail
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $otpData2 = json_decode($otpReq2['body'], true);
    $validOtp = $otpData2['dev_otp'] ?? '';

    // Verify correct OTP
    $verRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'verify_otp',
        'csrf_token' => $csrfToken,
        'otp_code' => $validOtp
    ], ['X-Requested-With' => 'XMLHttpRequest']);

    // TEST 7: Short password (<6 chars) rejected
    $shortPassRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'reset_password',
        'csrf_token' => $csrfToken,
        'new_password' => '12345',
        'confirm_password' => '12345'
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    logTest("7. Input Boundary: Short password (<6 chars) rejected (HTTP 400)", $shortPassRes['code'] === 400);

    // TEST 8: Mismatched password rejected
    $mismatchRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'reset_password',
        'csrf_token' => $csrfToken,
        'new_password' => 'ValidPass123!',
        'confirm_password' => 'DifferentPass123!'
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    logTest("8. Input Boundary: Password confirmation mismatch rejected (HTTP 400)", $mismatchRes['code'] === 400);

    // TEST 9: Successful password update & Argon2id/Bcrypt hash verification
    $okPassRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'reset_password',
        'csrf_token' => $csrfToken,
        'new_password' => 'FinalPass2026!#',
        'confirm_password' => 'FinalPass2026!#'
    ], ['X-Requested-With' => 'XMLHttpRequest']);

    // Check database to ensure password hash was updated
    $checkStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $checkStmt->execute([$ptUserId]);
    $updatedRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $hashUpdated = password_verify('FinalPass2026!#', $updatedRow['password'] ?? '');
    logTest("9. Password Persistence: Native password_hash() verified in database", $okPassRes['code'] === 200 && $hashUpdated);

    // TEST 10: One-Time Token Invalidation Defense (CWE-640)
    // Attempting to re-use the verified reset session must fail
    $reuseRes = pentest_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'reset_password',
        'csrf_token' => $csrfToken,
        'new_password' => 'ReusedPass2026!',
        'confirm_password' => 'ReusedPass2026!'
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    logTest("10. Replay Defense: Reset session destroyed after use; re-execution blocked (HTTP 403)", $reuseRes['code'] === 403);

} finally {
    // Teardown synthetic user
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$ptUserId]);
    @unlink($cookieFile);
}

echo "\n=========================================================\n";
echo "PENETRATION & SECURITY AUDIT VERDICT: " . ($allPassed ? "10/10 TESTS PASSED" : "VULNERABILITIES DETECTED") . "\n";
echo "=========================================================\n";

exit($allPassed ? 0 : 1);
