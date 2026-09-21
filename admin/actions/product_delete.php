<?php
// admin/actions/product_delete.php
require_once __DIR__ . '/../../config/db.php';

$rootPath = function_exists('get_app_root') ? get_app_root() : '/';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: " . $rootPath . "login");
    exit;
}

// Enforce CSRF protection
$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    http_response_code(403);
    die("Security validation failed. Invalid or missing CSRF token.");
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: ../index.php?view=products&msg=updated");
exit;
?>
