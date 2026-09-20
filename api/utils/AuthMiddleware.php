<?php
// api/utils/AuthMiddleware.php

class AuthMiddleware
{
    public static function authenticate($pdo)
    {
        $authHeader = '';
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $val) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $authHeader = $val;
                    break;
                }
            }
        }
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        // Support "Bearer <token>"
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            // Fallback for simple testing
            $token = $authHeader;
        }

        if (!$token) {
            Response::error("Unauthorized: No token provided.", 401);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error("Unauthorized: Invalid token.", 401);
        }

        return $user;
    }
}
?>