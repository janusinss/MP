<?php
// api/config/cors.php

$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, '192.168.');

if ($httpOrigin) {
    $parsed = parse_url($httpOrigin);
    $originHost = $parsed['host'] ?? '';
    
    // Allow local origins or matching host origin
    if ($originHost === 'localhost' || $originHost === '127.0.0.1' || $originHost === $host) {
        header("Access-Control-Allow-Origin: " . $httpOrigin);
        header("Access-Control-Allow-Credentials: true");
    }
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}