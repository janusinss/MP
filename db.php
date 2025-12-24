<?php
// Use environment variables if available (Railway/Render)
$host = getenv('MYSQLHOST') ? getenv('MYSQLHOST') : 'localhost';
$dbname = getenv('MYSQLDATABASE') ? getenv('MYSQLDATABASE') : 'grocery_db';
$username = getenv('MYSQLUSER') ? getenv('MYSQLUSER') : 'root';
$password = getenv('MYSQLPASSWORD') ? getenv('MYSQLPASSWORD') : '';

// Check if we are on InfinityFree (Host matches specific pattern)
if ($_SERVER['HTTP_HOST'] == 'janus-grocery.ct.ws' || $_SERVER['HTTP_HOST'] == 'janus-grocery.infinityfreeapp.com' || strpos($host, 'infinityfree') !== false) {
    $host = 'sql309.infinityfree.com';
    $dbname = 'if0_40753726_grocery_db';
    $username = 'if0_40753726';
    $password = '092633449090';
}

$port = getenv('MYSQLPORT') ? getenv('MYSQLPORT') : 3306;

try {
    // We use PDO and include the port in the DSN
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}