<?php
$script = "/YEAR 3/Mini Project ADS/grocery_app/auth/login.php";
$root = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
echo "Localhost Root: " . ($root ? $root . '/' : '/') . "\n";

$liveScript = "/auth/login.php";
$liveRoot = rtrim(str_replace('\\', '/', dirname(dirname($liveScript))), '/');
echo "Live Root: " . ($liveRoot ? $liveRoot . '/' : '/') . "\n";
