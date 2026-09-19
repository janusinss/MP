<?php
$base = "http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app";
$routes = [
    "/auth/login.php" => "/login",
    "/auth/register.php" => "/register",
    "/cart/index.php" => "/cart",
    "/orders/checkout.php" => "/checkout",
    "/orders/index.php" => "/orders",
    "/account/profile.php" => "/profile",
    "/pages/about.php" => "/about",
    "/pages/contact.php" => "/contact",
    "/pages/farmers.php" => "/farmers",
    "/pages/sustainability.php" => "/sustainability",
    "/pages/privacy_policy.php" => "/privacy",
    "/pages/terms_of_service.php" => "/terms",
];

echo "=== Testing Canonical 301 Redirects ===\n";
$allPass = true;
foreach ($routes as $phpPath => $expectedClean) {
    $ch = curl_init($base . $phpPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    preg_match('/Location:\s*([^\r\n]+)/i', $res, $loc);
    $location = trim($loc[1] ?? '');

    if ($code === 301 && str_contains($location, $expectedClean)) {
        echo "[PASS] $phpPath -> HTTP $code -> $location\n";
    } else {
        echo "[FAIL] $phpPath -> HTTP $code -> $location (expected $expectedClean)\n";
        $allPass = false;
    }
}

echo "\n=== Testing Clean Friendly Route Serving (HTTP 200) ===\n";
$cleanRoutes = [
    "/login",
    "/register",
    "/about",
    "/contact",
    "/farmers",
    "/sustainability",
    "/privacy",
    "/terms",
];

foreach ($cleanRoutes as $route) {
    $ch = curl_init($base . $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    preg_match('/<title>(.*?)<\/title>/i', $res, $t);
    $title = trim($t[1] ?? 'No title');

    if ($code === 200) {
        echo "[PASS] $route -> HTTP $code | Title: $title\n";
    } else {
        echo "[FAIL] $route -> HTTP $code\n";
        $allPass = false;
    }
}

exit($allPass ? 0 : 1);
