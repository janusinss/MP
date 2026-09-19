<?php
// scripts/verify_security_rules.php
// Automated Verification Suite for Security Hardening & Canonical URLs

$baseUrl = 'http://localhost/YEAR%203/Mini%20Project%20ADS/grocery_app';

$tests = [
    'Direct DB Dump Access' => [
        'url' => "$baseUrl/database/grocery_db.sql",
        'expect_status' => [403],
    ],
    'Database Directory Indexing' => [
        'url' => "$baseUrl/database/",
        'expect_status' => [403],
    ],
    'Config Directory Access' => [
        'url' => "$baseUrl/config/",
        'expect_status' => [403],
    ],
    'Dotenv File Access' => [
        'url' => "$baseUrl/.env",
        'expect_status' => [403],
    ],
    'Index.php Canonical Redirect' => [
        'url' => "$baseUrl/index.php",
        'expect_status' => [301, 302],
    ],
    'Clean Root Storefront Access' => [
        'url' => "$baseUrl/",
        'expect_status' => [200],
    ],
    'Clean Cart Route Access' => [
        'url' => "$baseUrl/cart/",
        'expect_status' => [200, 302],
    ],
    'Clean Login Route Rewrite' => [
        'url' => "$baseUrl/login",
        'expect_status' => [200],
    ],
];

echo "\n======================================================\n";
echo "   FRESHCART SECURITY & CANONICAL URL TEST SUITE\n";
echo "======================================================\n\n";

$allPassed = true;

foreach ($tests as $name => $spec) {
    $ctx = stream_context_create([
        'http' => [
            'follow_location' => 0,
            'ignore_errors' => true,
            'timeout' => 5
        ]
    ]);
    
    $fp = @fopen($spec['url'], 'r', false, $ctx);
    if (!$fp) {
        echo "[-] $name: FAILED to connect to {$spec['url']}\n";
        $allPassed = false;
        continue;
    }
    
    $meta = stream_get_meta_data($fp);
    $headers = $meta['wrapper_data'] ?? [];
    $statusLine = $headers[0] ?? '';
    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $m);
    $statusCode = isset($m[1]) ? (int)$m[1] : 0;
    
    $location = '';
    $secHeaders = [];
    foreach ($headers as $h) {
        if (stripos($h, 'Location:') === 0) $location = trim(substr($h, 9));
        if (stripos($h, 'X-Frame-Options:') === 0) $secHeaders[] = 'X-Frame-Options';
        if (stripos($h, 'X-Content-Type-Options:') === 0) $secHeaders[] = 'X-Content-Type-Options';
    }
    
    $passed = in_array($statusCode, $spec['expect_status'], true);
    if ($passed) {
        echo "[+] $name: PASS (HTTP $statusCode" . ($location ? " -> $location" : "") . ")\n";
    } else {
        echo "[-] $name: FAIL (Got HTTP $statusCode, expected " . implode('/', $spec['expect_status']) . ")\n";
        $allPassed = false;
    }
}

echo "\nHeader Verification on Root:\n";
$rootFp = @fopen("$baseUrl/", 'r', false, stream_context_create(['http' => ['ignore_errors' => true]]));
if ($rootFp) {
    $rootMeta = stream_get_meta_data($rootFp);
    foreach ($rootMeta['wrapper_data'] ?? [] as $h) {
        if (preg_match('/^(X-Frame-Options|X-Content-Type-Options|Referrer-Policy|Set-Cookie):/i', $h)) {
            echo "    $h\n";
        }
    }
}

echo "\nSummary: " . ($allPassed ? "ALL TESTS PASSED!" : "SOME TESTS FAILED.") . "\n";
exit($allPassed ? 0 : 1);
