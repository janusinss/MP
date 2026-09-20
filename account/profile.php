<?php
// account/profile.php
// Customer Account Settings & Profile Dashboard for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        http_response_code(403);
        die("Security validation failed. Please refresh and try again.");
    }
    
    $name = trim($_POST['full_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name)) {
        $error = "Full name cannot be blank.";
    } else {
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $error = "New password must be at least 6 characters.";
            } else {
                $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name=?, address=?, password=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $address, $pass, $user_id]);
                $_SESSION['user_name'] = $name;
                $msg = "Account details and security credentials updated successfully.";
            }
        } else {
            $sql = "UPDATE users SET full_name=?, address=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $address, $user_id]);
            $_SESSION['user_name'] = $name;
            $msg = "Profile updated successfully.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<main class="profile-page-wrapper">
    <div class="container">
        
        <nav class="profile-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $rootPath ?: './' ?>"><i class="bi bi-house-door"></i> Home</a>
            <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            <span class="text-dark fw-medium">Account Settings</span>
        </nav>

        <div class="row g-4">
            <!-- Left Sidebar Profile Overview -->
            <div class="col-lg-4 col-md-5">
                <aside class="profile-sidebar-card">
                    <div class="profile-avatar-large shadow-sm">
                        <?= strtoupper(substr($user['full_name'] ?: 'U', 0, 1)) ?>
                    </div>
                    <h3 class="profile-user-name"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <div class="profile-user-email"><?= htmlspecialchars($user['email']) ?></div>
                    
                    <div class="profile-member-pill">
                        <i class="bi bi-patch-check-fill"></i>
                        <span>Active Farmstead Member</span>
                    </div>

                    <nav class="profile-nav-list" aria-label="Account Navigation">
                        <a href="<?= $rootPath ?>profile" class="profile-nav-btn active">
                            <i class="bi bi-person-gear"></i>
                            <span>Account Settings</span>
                        </a>
                        <a href="<?= $rootPath ?>orders" class="profile-nav-btn">
                            <i class="bi bi-receipt"></i>
                            <span>Order History</span>
                        </a>
                        <a href="<?= $rootPath ?: './' ?>#catalog" class="profile-nav-btn">
                            <i class="bi bi-basket"></i>
                            <span>Browse Fresh Harvest</span>
                        </a>
                        <a href="<?= $rootPath ?>logout" class="profile-nav-btn text-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Sign Out</span>
                        </a>
                    </nav>
                </aside>
            </div>

            <!-- Right Content: Profile Form -->
            <div class="col-lg-8 col-md-7">
                <div class="profile-content-card">
                    <div class="profile-content-header">
                        <h1 class="profile-heading">Account Settings</h1>
                        <p class="profile-subheading">Update your delivery address, personal details, and account security.</p>
                    </div>

                    <?php if ($msg): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm rounded-3 mb-4" role="alert">
                            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                            <div><?= htmlspecialchars($msg) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 shadow-sm rounded-3 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile">
                        <?= csrf_input() ?>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="profile-field-label" for="profileFullName">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 10px 0 0 10px; border-color: rgba(0,0,0,0.12);">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" id="profileFullName" name="full_name" class="form-control profile-field-input border-start-0 ps-0" style="border-radius: 0 10px 10px 0;" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="profile-field-label">Account Email <span class="badge bg-light text-muted border ms-1 fw-normal"><i class="bi bi-lock-fill"></i> Locked</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 10px 0 0 10px; border-color: rgba(0,0,0,0.08);">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control profile-field-input border-start-0 ps-0" style="border-radius: 0 10px 10px 0;" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="profile-field-label" for="profileAddress">Default Delivery Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted align-self-start pt-3" style="border-radius: 10px 0 0 10px; border-color: rgba(0,0,0,0.12);">
                                    <i class="bi bi-geo-alt"></i>
                                </span>
                                <textarea id="profileAddress" name="address" class="form-control profile-field-input border-start-0 ps-0" style="border-radius: 0 10px 10px 0;" rows="3" placeholder="Street, apartment, city, state, postal code"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="profile-field-label" for="profilePassword">
                                New Password <span class="text-muted fw-normal" style="text-transform: none;">(Leave blank to keep current)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 10px 0 0 10px; border-color: rgba(0,0,0,0.12);">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <input type="password" id="profilePassword" name="password" class="form-control profile-field-input border-start-0 border-end-0 px-0" placeholder="Minimum 6 characters">
                                <button type="button" class="btn btn-white border border-start-0 text-muted" style="border-radius: 0 10px 10px 0; border-color: rgba(0,0,0,0.12); background: #fff;" onclick="togglePasswordVisibility('profilePassword', this)" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-2">
                            <button type="submit" class="profile-save-btn">
                                <i class="bi bi-check2"></i>
                                <span>Save Changes</span>
                            </button>
                            <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i> Data encrypted with 256-bit SSL</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
