<?php
// api/v1/auth/login.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method Not Allowed", 405);
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    Response::error("Email and password are required.");
}

$email = trim((string)($data->email ?? ''));
$password = (string)($data->password ?? '');

// Rate limiting: Max 5 failed attempts per 15 min per IP/account (security.md Phase 2.1)
if (!check_rate_limit('api_login_' . ($email ?: 'anon'), 5, 900)) {
    Response::error("Too many login attempts. Please try again in 15 minutes.", 429);
}

try {
    // 1. Find user
    $stmt = $pdo->prepare("SELECT id, full_name, password, api_token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        Response::error("Invalid credentials.", 401);
    }

    // 2. Generate new token if not exists or refresh it (Optional: simple approach reuse/create)
    // For simplicity, we generate a new one on login
    $newToken = bin2hex(random_bytes(32));

    $updateStmt = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
    $updateStmt->execute([$newToken, $user['id']]);

    // 3. Return Token
    Response::success([
        'user_id' => $user['id'],
        'name' => $user['full_name'],
        'token' => $newToken
    ], "Login successful");

} catch (Exception $e) {
    error_log("API Auth Login Error: " . $e->getMessage());
    Response::error("An internal error occurred during authentication.", 500);
}
?>