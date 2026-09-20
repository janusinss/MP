<?php
// config/security.php
// Central Security Architecture, Session Hardening, and CSRF Defense

// 1. Enforce strict session cookie flags before session start
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!function_exists('get_app_root')) {
    function get_app_root(): string {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strpos($scriptName, '/grocery_app');
        if ($pos !== false) {
            return substr($scriptName, 0, $pos + strlen('/grocery_app')) . '/';
        }
        $appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        return $appRoot ? $appRoot . '/' : '/';
    }
}

// 2. Deliver Defense-in-Depth HTTP Security Headers & Prevent Sensitive Caching (OWASP CWE-525 / security.md Phase 6)
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

    // Enforce strict anti-caching on dynamic application responses to defeat browser back-button history inspection
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// 3. Cryptographically Secure Anti-CSRF Functions
if (!function_exists('get_csrf_token')) {
    function get_csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string {
        $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($sessionToken) || empty($token)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}

// 4. Rate Limiting Helper (Session/IP bounded)
if (!function_exists('check_rate_limit')) {
    function check_rate_limit(string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool {
        $now = time();
        if (!isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = [];
        }
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            fn($timestamp) => ($now - $timestamp) < $decaySeconds
        );
        if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
            return false;
        }
        $_SESSION['rate_limits'][$key][] = $now;
        return true;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text ?: 'product');
    }
}

// 5. Enforce Canonical Clean URLs (Zero Technology Stack Exposure)
if (!function_exists('enforce_clean_url')) {
    function enforce_clean_url(): void {
        if (php_sapi_name() === 'cli') return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (empty($requestUri)) return;

        $parsed = parse_url($requestUri);
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
        
        // Exclude admin and scripts
        if (str_contains($path, '/admin/') || str_contains($path, '/scripts/')) {
            return;
        }

        $routeMap = [
            '/auth/login.php' => '/login',
            '/auth/register.php' => '/register',
            '/auth/logout.php' => '/logout',
            '/cart/index.php' => '/cart',
            '/orders/checkout.php' => '/checkout',
            '/orders/index.php' => '/orders',
            '/account/profile.php' => '/profile',
            '/pages/about.php' => '/about',
            '/pages/contact.php' => '/contact',
            '/pages/farmers.php' => '/farmers',
            '/pages/sustainability.php' => '/sustainability',
            '/pages/privacy_policy.php' => '/privacy',
            '/pages/terms_of_service.php' => '/terms',
            '/index.php' => '/',
        ];

        foreach ($routeMap as $phpPath => $cleanRoute) {
            if (str_ends_with($path, $phpPath)) {
                $base = substr($path, 0, strlen($path) - strlen($phpPath));
                $target = rtrim($base, '/') . $cleanRoute . $query;
                header("Location: " . $target, true, 301);
                exit;
            }
        }

        if (str_ends_with($path, '/orders/details.php')) {
            $base = substr($path, 0, strlen($path) - strlen('/orders/details.php'));
            if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
                header("Location: " . rtrim($base, '/') . '/order/' . (int)$_GET['order_id'], true, 301);
                exit;
            } else {
                header("Location: " . rtrim($base, '/') . '/orders', true, 301);
                exit;
            }
        }
    }
}
enforce_clean_url();
