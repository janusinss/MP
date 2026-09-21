<?php
// config/db.php
// Centralized Database Connection Configuration (FreshCart)

// 0. Load Central Security Architecture & Session Hardening
require_once __DIR__ . '/security.php';

// Safe environment variable helper across all hosting environments
if (!function_exists('get_config_var')) {
    function get_config_var($key, $default = null) {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        if (function_exists('getenv')) {
            $val = getenv($key);
            if ($val !== false && $val !== '') {
                return $val;
            }
        }
        return $default;
    }
}

// 1. Detect if running on localhost / local development
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']) 
    || strpos($httpHost, '192.168.') === 0
    || (php_sapi_name() === 'cli' && (!getenv('MYSQLHOST') || getenv('MYSQLHOST') === 'localhost'));

// 2. Load .env if present
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
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
            if (function_exists('putenv')) {
                @putenv("$key=$val");
            }
        }
    }
}

// 3. If on production/remote hosting, load config/env.php as non-dotfile fallback
if (!$isLocal) {
    $envPhp = __DIR__ . '/env.php';
    if (file_exists($envPhp)) {
        $configArray = require $envPhp;
        if (is_array($configArray)) {
            foreach ($configArray as $k => $v) {
                if (!isset($_ENV[$k]) || $_ENV[$k] === '') {
                    $_ENV[$k] = $v;
                }
                if (!isset($_SERVER[$k]) || $_SERVER[$k] === '') {
                    $_SERVER[$k] = $v;
                }
                if (function_exists('putenv')) {
                    @putenv("$k=$v");
                }
            }
        }
    }
}

// 4. Default database credentials by environment
$defaultHost = $isLocal ? 'localhost' : 'sql105.infinityfree.com';
$defaultDb   = $isLocal ? 'grocery_db' : 'if0_42958450_grocery_db';
$defaultUser = $isLocal ? 'root' : 'if0_42958450';
$defaultPass = $isLocal ? '' : 'LDK0QkYYT4jd';
$defaultPort = 3306;

$host     = get_config_var('MYSQLHOST', $defaultHost);
$dbname   = get_config_var('MYSQLDATABASE', $defaultDb);
$username = get_config_var('MYSQLUSER', $defaultUser);
$password = get_config_var('MYSQLPASSWORD', $defaultPass);
$port     = (int)get_config_var('MYSQLPORT', $defaultPort);

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (PDOException $e) {
    // Safely log error on server
    error_log("Database Connection Error: " . $e->getMessage());

    // Provide detailed diagnostics if requested via ?debug_db=1
    $showDebug = isset($_GET['debug_db']) || (isset($_GET['debug']) && $_GET['debug'] === '1');
    $errorMessage = "Service Unavailable: Database connection failed.";
    if ($showDebug) {
        $errorMessage .= " [Diagnostic: Host=$host, DB=$dbname, User=$username, Error=" . htmlspecialchars($e->getMessage()) . "]";
    }

    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => $errorMessage]);
        exit;
    }

    http_response_code(503);
    die($errorMessage);
}
