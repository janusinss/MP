<?php
// tests/run_all.php
// Master Test Runner for FreshCart ADS Platform
// Executes all backend, security, web flow, and REST API regression test suites.

echo "====================================================\n";
echo "   FRESHCART MASTER TEST HARNESS & REGRESSION SUITE  \n";
echo "   Standardized QA Pipeline                         \n";
echo "====================================================\n\n";

$scriptsDir = dirname(__DIR__) . '/scripts';
$suites = [
    'Backend Standards & ADS Relational Routines' => "$scriptsDir/verify_backend_standards.php",
    'Security Hardening & Penetration Defense' => "$scriptsDir/verify_security_suite.php",
    'Storefront Web Routing & Canonical Redirection' => "$scriptsDir/verify_web_flow.php",
    'RESTful API v1 Auth, Cart & Order Operations' => "$scriptsDir/verify_api.php",
    'User Isolation & Session Boundary Enforcement' => "$scriptsDir/verify_user_isolation.php"
];

$overallSuccess = true;
$suiteResults = [];

foreach ($suites as $name => $path) {
    echo "----------------------------------------------------\n";
    echo ">> RUNNING: $name\n";
    echo "----------------------------------------------------\n";

    if (!file_exists($path)) {
        echo "[ERROR] Suite file not found: $path\n\n";
        $overallSuccess = false;
        $suiteResults[$name] = 'MISSING';
        continue;
    }

    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($path);
    exec($cmd, $output, $returnCode);

    echo implode("\n", $output) . "\n\n";

    if ($returnCode === 0) {
        $suiteResults[$name] = 'PASS';
    } else {
        $suiteResults[$name] = 'FAIL';
        $overallSuccess = false;
    }
    unset($output);
}

echo "====================================================\n";
echo "                 FINAL HARNESS SUMMARY              \n";
echo "====================================================\n";
foreach ($suiteResults as $name => $status) {
    $tag = ($status === 'PASS') ? "[PASS]" : "[FAIL]";
    echo sprintf("  %-50s %s\n", $name, $tag);
}
echo "====================================================\n";
echo "HARNESS VERDICT: " . ($overallSuccess ? "ALL SUITES PASSED (0 ERRORS)" : "SUITE FAILURES DETECTED") . "\n";
echo "====================================================\n";

exit($overallSuccess ? 0 : 1);
