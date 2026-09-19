<?php
// config/db.php
// Centralized Database Connection Configuration (FreshCart)

// 0. Load Central Security Architecture & Session Hardening
require_once __DIR__ . '/security.php';

// 1. Automatically load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Default to standard settings with automatic cloud detection and environment variable overrides
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) 
    || strpos($_SERVER['HTTP_HOST'] ?? '', '192.168.') === 0
    || (php_sapi_name() === 'cli' && !getenv('MYSQLHOST'));

$host = getenv('MYSQLHOST') ?: ($isLocal ? 'localhost' : 'sql105.infinityfree.com');
$dbname = getenv('MYSQLDATABASE') ?: ($isLocal ? 'grocery_db' : 'if0_42958450_grocery_db');
$username = getenv('MYSQLUSER') ?: ($isLocal ? 'root' : 'if0_42958450');
$password = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : ($isLocal ? '' : 'LDK0QkYYT4jd');
$port = getenv('MYSQLPORT') ?: 3306;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // If debug flag provided in query string, output precise diagnostic
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        die("Database Debug Error: " . htmlspecialchars($e->getMessage()) . " [Host: $host, User: $username, DB: $dbname, env_exists: " . (file_exists($envFile) ? 'YES' : 'NO') . "]");
    }
    
    // If we are in an API call, return JSON without internal detail disclosure
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Service Unavailable: Database connection failed.']);
        exit;
    }
    // Otherwise standard message for HTML
    http_response_code(503);
    die("Service Unavailable: Database connection failed.");
}
