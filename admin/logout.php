<?php
// admin/logout.php
// Cleanly terminates administrator session and redirects directly to storefront landing
require_once __DIR__ . '/../config/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Wipe all active session state
$_SESSION = [];

// 2. Invalidate session cookie on the client
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params["path"] ?: '/',
        'domain' => $params["domain"] ?: '',
        'secure' => $params["secure"] ?? false,
        'httponly' => $params["httponly"] ?? true,
        'samesite' => $params["samesite"] ?? 'Lax'
    ]);
}

// 3. Destroy session on server
session_destroy();
session_write_close();

// 4. Force browser cache eviction & anti-caching
if (!headers_sent()) {
    header('Clear-Site-Data: "cache", "storage"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

$appRoot = function_exists('get_app_root') ? get_app_root() : '/';
header("Location: " . $appRoot);
exit;
