<?php
// scripts/check_credentials.php
// Diagnostic tool to probe MySQL connection credentials.

$candidates = [
    ['root', ''],
    ['root', 'root'],
    ['root', 'admin'],
    ['root', 'password'],
    ['root', '123456'],
    ['admin', 'admin'],
];

echo "<h2>Database Credential Check</h2>\n";

foreach ($candidates as $cred) {
    $user = $cred[0];
    $pass = $cred[1];
    $mask = $pass === '' ? '(empty)' : $pass;

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=grocery_db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<div style='color:green'>✅ SUCCESS! User: <b>$user</b> | Password: <b>$mask</b></div>\n";
        exit;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Access denied') !== false) {
            echo "<div style='color:red'>❌ Failed: User: $user | Password: $mask (Access Denied)</div>\n";
        } else {
            echo "<div style='color:orange'>⚠️ Error: User: $user | Password: $mask (" . htmlspecialchars($msg) . ")</div>\n";
        }
    }
}

echo "<hr><p>Could not find working credentials in common list.</p>\n";
?>
