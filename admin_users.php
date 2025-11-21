<?php
include 'db.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch All Users (Newest First)
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>👥 Registered Customers</h2>
        <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (count($users) > 0): ?>
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Joined Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <a href="delete_user.php?id=<?= $user['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure? This will verify delete their Order History too.');">
                                    🗑 Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info text-center">No users registered yet.</div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>