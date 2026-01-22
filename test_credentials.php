<?php
// test_credentials.php

$candidates = [
    ['root', ''],
    ['root', 'root'],
    ['root', 'admin'],
    ['root', 'password'],
    ['root', '123456'],
    ['admin', 'admin'],
];

echo "<h2>Database Credential Check</h2>";

foreach ($candidates as $cred) {
    $user = $cred[0];
    $pass = $cred[1];
    $mask = $pass === '' ? '(empty)' : $pass;

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=grocery_db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<div style='color:green'>✅ SUCCESS! User: <b>$user</b> | Password: <b>$mask</b></div>";
        // Exit after finding one
        exit;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Simplify error
        if (strpos($msg, 'Access denied') !== false) {
            echo "<div style='color:red'>❌ Failed: User: $user | Password: $mask (Access Denied)</div>";
        } else {
            echo "<div style='color:orange'>⚠️ Error: User: $user | Password: $mask ($msg)</div>";
        }
    }
}

echo "<hr><p>Could not find working credentials in common list.</p>";
?>