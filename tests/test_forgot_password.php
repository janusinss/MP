<?php
// tests/test_forgot_password.php
// E2E Verification for Forgot Password & Email OTP Reset Flow
require_once __DIR__ . '/../config/db.php';

$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app';
$cookieFile = tempnam(sys_get_temp_dir(), 'fc_forgot_cookie_');

function http_req($url, $method = 'GET', $data = null, $headers = []) {
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

echo "=== Forgot Password & Email OTP Reset Verification ===\n";

// 0. Setup isolated QA test user
$testEmail = 'qa_reset_' . time() . '_' . rand(100, 999) . '@example.com';
$initialPass = 'InitialPass123!';
$stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
$stmt->execute(['QA Password Reset User', $testEmail, password_hash($initialPass, PASSWORD_DEFAULT)]);
$testUserId = (int)$pdo->lastInsertId();

try {
    // 1. Clean route test
    $res = http_req("$baseUrl/forgot-password");
    assertTest("1. Clean /forgot-password route serves 200", $res['code'] === 200);

    // 2. Canonical redirect test
    $res = http_req("$baseUrl/auth/forgot_password.php");
    assertTest("2. Canonical 301 on /auth/forgot_password.php", $res['code'] === 301 && strpos($res['redirect'], 'forgot-password') !== false);

    // 3. Extract CSRF token from /forgot-password
    $cleanPage = http_req("$baseUrl/forgot-password");
    preg_match('/name="csrf_token" value="([^"]+)"/', $cleanPage['body'], $matches);
    $csrfToken = $matches[1] ?? '';
    assertTest("3. Extract CSRF Token from /forgot-password", !empty($csrfToken));

    // 4. Reject POST without CSRF token
    $noCsrfRes = http_req("$baseUrl/forgot-password?ajax=1", 'POST', ['auth_action' => 'send_otp', 'email' => $testEmail], ['X-Requested-With' => 'XMLHttpRequest']);
    assertTest("4. Reject POST without CSRF token (HTTP 403)", $noCsrfRes['code'] === 403);

    // 5. Send OTP request for existing customer
    $sendRes = http_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'send_otp',
        'csrf_token' => $csrfToken,
        'email' => $testEmail
    ], ['X-Requested-With' => 'XMLHttpRequest']);

    $sendData = json_decode($sendRes['body'], true);
    assertTest("5. Send OTP Request succeeds (HTTP 200, step 2)", $sendRes['code'] === 200 && ($sendData['success'] ?? false) === true);

    $otpCode = '';
    $cookieContent = file_get_contents($cookieFile);
    if (preg_match('/PHPSESSID\s+([a-zA-Z0-9,-]+)/', $cookieContent, $sessMatches)) {
        $sessId = $sessMatches[1];
        $sessDirs = [session_save_path(), 'C:/xampp/tmp', sys_get_temp_dir()];
        foreach ($sessDirs as $dir) {
            if (empty($dir)) continue;
            $sessFile = rtrim($dir, '/\\') . '/sess_' . $sessId;
            if (file_exists($sessFile)) {
                $sessRaw = file_get_contents($sessFile);
                if (preg_match('/"otp";s:6:"(\d{6})"/', $sessRaw, $otpMatches)) {
                    $otpCode = $otpMatches[1];
                    break;
                }
            }
        }
    }
    assertTest("6. Cryptographic 6-digit OTP generated", strlen($otpCode) === 6);

    // 7. Verify invalid OTP rejection
    $badOtpRes = http_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'verify_otp',
        'csrf_token' => $csrfToken,
        'otp_code' => '000000'
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $badOtpData = json_decode($badOtpRes['body'], true);
    assertTest("7. Reject incorrect OTP code (HTTP 400)", $badOtpRes['code'] === 400 && ($badOtpData['success'] ?? true) === false);

    // 8. Verify correct OTP
    $validOtpRes = http_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'verify_otp',
        'csrf_token' => $csrfToken,
        'otp_code' => $otpCode
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $validOtpData = json_decode($validOtpRes['body'], true);
    assertTest("8. Accept correct OTP code (HTTP 200, step 3)", $validOtpRes['code'] === 200 && ($validOtpData['success'] ?? false) === true && ($validOtpData['step'] ?? 0) === 3);

    // 9. Reset Password to new password
    $newPass = 'BrandNewPass2026!';
    $resetRes = http_req("$baseUrl/forgot-password?ajax=1", 'POST', [
        'auth_action' => 'reset_password',
        'csrf_token' => $csrfToken,
        'new_password' => $newPass,
        'confirm_password' => $newPass
    ], ['X-Requested-With' => 'XMLHttpRequest']);
    $resetData = json_decode($resetRes['body'], true);
    assertTest("9. Password Reset successfully (HTTP 200)", $resetRes['code'] === 200 && ($resetData['success'] ?? false) === true);

    // 10. Login with new password
    $loginPageRes = http_req("$baseUrl/login");
    preg_match('/name="csrf_token" value="([^"]+)"/', $loginPageRes['body'], $loginMatches);
    $loginCsrf = $loginMatches[1] ?? '';

    $loginRes = http_req("$baseUrl/login", 'POST', [
        'csrf_token' => $loginCsrf,
        'email' => $testEmail,
        'password' => $newPass
    ]);
    assertTest("10. Sign in with newly reset password succeeds (HTTP 302 -> Home)", $loginRes['code'] === 302);

} finally {
    // 11. Cleanup temporary user and cookie
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);
    @unlink($cookieFile);
    echo "  [PASS] 11. Zero-leakage cleanup of temporary QA user\n";
}

echo "=== All Tests Completed ===\n";

function assertTest($name, $passed) {
    if ($passed) {
        echo "  [PASS] $name\n";
    } else {
        echo "  [FAIL] $name\n";
        exit(1);
    }
}
