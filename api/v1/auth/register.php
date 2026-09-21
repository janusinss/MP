<?php
// api/v1/auth/register.php
include '../../config/cors.php';
include '../../config/database.php';
include '../../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method Not Allowed", 405);
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    Response::error("Malformed JSON payload.");
}

$fullName = trim((string)($data->full_name ?? ''));
$email = strtolower(trim((string)($data->email ?? '')));
$password = (string)($data->password ?? '');
$address = trim((string)($data->address ?? ''));

// Rate limiting: Max 20 registration attempts per 15 min per IP (security.md Phase 2.1)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'anon';
if (!check_rate_limit('api_register_' . $ip, 20, 900)) {
    Response::error("Too many registration attempts. Please try again in 15 minutes.", 429);
}

// Zero-trust validation
if (empty($fullName) || empty($email) || empty($password)) {
    Response::error("Full name, email, and password are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error("Invalid email address format.");
}

if (strlen($fullName) > 100) {
    Response::error("Full name cannot exceed 100 characters.");
}

if (strlen($password) < 8) {
    Response::error("Password must be at least 8 characters long.");
}

if (strlen($password) > 72) {
    Response::error("Password cannot exceed 72 characters.");
}

if (strlen($address) > 255) {
    Response::error("Address cannot exceed 255 characters.");
}

try {
    // Check for existing duplicate email
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        Response::error("An account with this email already exists.", 409);
    }

    // Hash password securely with native bcrypt
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate API bearer token
    $newToken = bin2hex(random_bytes(32));

    // Persist new user with strictly assigned 'customer' role
    $stmtInsert = $pdo->prepare("INSERT INTO users (full_name, email, password, address, role, api_token) VALUES (?, ?, ?, ?, 'customer', ?)");
    $stmtInsert->execute([$fullName, $email, $hashedPassword, $address, $newToken]);
    $userId = (int) $pdo->lastInsertId();

    Response::created([
        'user_id' => $userId,
        'name' => $fullName,
        'email' => $email,
        'token' => $newToken
    ], "Account created successfully.");

} catch (Exception $e) {
    error_log("API Auth Register Error: " . $e->getMessage());
    Response::error("An internal error occurred during account registration.", 500);
}
