<?php
// db.php

// 1. Default to standard XAMPP settings
$host = 'localhost';
$dbname = 'grocery_db';
$username = 'root';
$password = '';

// 2. Allow Environment Variable Overrides (Railway/Render/etc)
if (getenv('MYSQLHOST'))
    $host = getenv('MYSQLHOST');
if (getenv('MYSQLDATABASE'))
    $dbname = getenv('MYSQLDATABASE');
if (getenv('MYSQLUSER'))
    $username = getenv('MYSQLUSER');
if (getenv('MYSQLPASSWORD'))
    $password = getenv('MYSQLPASSWORD');
$port = getenv('MYSQLPORT') ? getenv('MYSQLPORT') : 3306;

// 3. InfinityFree/Server-Specific Overrides
// Check HTTP_HOST safely
$serverHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

if (strpos($serverHost, 'janus-grocery') !== false || strpos($host, 'infinityfree') !== false) {
    $host = 'sql309.infinityfree.com';
    $dbname = 'if0_40753726_grocery_db';
    $username = 'if0_40753726';
    $password = '092633449090';
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If we are in an API call, return JSON
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    // Otherwise standard die for HTML
    die("Connection failed: " . $e->getMessage());
}
?>