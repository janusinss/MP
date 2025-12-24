<?php
// Use environment variables if available (Railway), otherwise use local defaults (XAMPP)
$host = getenv('MYSQLHOST') ? getenv('MYSQLHOST') : 'localhost';
$dbname = getenv('MYSQLDATABASE') ? getenv('MYSQLDATABASE') : 'grocery_db';
$username = getenv('MYSQLUSER') ? getenv('MYSQLUSER') : 'root';
$password = getenv('MYSQLPASSWORD') ? getenv('MYSQLPASSWORD') : '';
// specific port is sometimes needed for cloud dbs (Railway usually provides MYSQLPORT)
$port = getenv('MYSQLPORT') ? getenv('MYSQLPORT') : 3306;

try {
    // We use PDO and include the port in the DSN
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>