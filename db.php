<?php
$host = 'localhost';
$dbname = 'grocery_db';
$username = 'root';  // Default for XAMPP
$password = '';      // Default is empty for XAMPP

try {
    // We use PDO because it is more secure and professional than old MySQLi
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>