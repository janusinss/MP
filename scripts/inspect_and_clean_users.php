<?php
// scripts/inspect_and_clean_users.php
require_once __DIR__ . '/../config/db.php';

$roles = $pdo->query("SELECT DISTINCT role FROM users")->fetchAll(PDO::FETCH_COLUMN);
echo "DISTINCT ROLES: " . implode(', ', $roles) . "\n";

$admins = $pdo->query("SELECT id, email, full_name, role FROM users WHERE role = 'admin' OR email LIKE '%admin%'")->fetchAll(PDO::FETCH_ASSOC);
echo "ADMINS:\n";
print_r($admins);

$check = $pdo->query("SELECT id, email, full_name, password FROM users WHERE email = 'customer@example.com'")->fetch(PDO::FETCH_ASSOC);
echo "Customer ID: {$check['id']} | Email: {$check['email']} | Password 'password': " . (password_verify('password', $check['password']) ? 'VALID' : 'INVALID') . "\n";
