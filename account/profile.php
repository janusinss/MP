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
            <!-- Mobile Compact Identity Header & Quick Actions (<lg) -->
            <div class="col-12 d-lg-none">
                <div class="profile-mobile-identity-card shadow-sm">
                    <div class="d-flex align-items-center gap-3">
                        <div class="profile-avatar-mobile shadow-sm" aria-hidden="true">
                            <?= strtoupper(substr($user['full_name'] ?: 'U', 0, 1)) ?>
                        </div>
                        <div class="profile-mobile-info flex-grow-1 min-w-0">
                            <h2 class="profile-mobile-name text-truncate mb-0"><?= htmlspecialchars($user['full_name']) ?></h2>
                            <div class="profile-mobile-email text-truncate"><?= htmlspecialchars($user['email']) ?></div>
                            <div class="profile-member-pill mt-1">
                                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                <span>Farmstead Member</span>
                            </div>
                        </div>
                    </div>
                    <!-- Quick Horizontal Switcher Tabs -->
                    <nav class="profile-mobile-quick-nav mt-3 pt-2 border-top border-light-subtle" aria-label="Mobile Profile Tabs">
                        <a href="<?= $rootPath ?>profile" class="profile-quick-pill active">
                            <i class="bi bi-person-gear" aria-hidden="true"></i>
                            <span>Settings</span>
                        </a>
                        <a href="<?= $rootPath ?>orders" class="profile-quick-pill">
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            <span>My Orders</span>
                        </a>
                        <a href="<?= $rootPath ?: './' ?>#categories" class="profile-quick-pill">
                            <i class="bi bi-basket" aria-hidden="true"></i>
                            <span>Browse Shop</span>
                        </a>
                        <a href="<?= $rootPath ?>logout" class="profile-quick-pill text-danger">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            <span>Sign Out</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Left Sidebar Profile Overview (Desktop >=lg) -->
            <div class="col-lg-4 d-none d-lg-block">
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
            <div class="col-lg-8 col-12">
                <div class="profile-content-card shadow-sm">
                    <div class="profile-content-header">
                        <h1 class="profile-heading">Account Settings</h1>
                        <p class="profile-subheading">Update your delivery address, personal details, and account security.</p>
                    </div>

                    <?php if ($msg): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 border-0 shadow-sm rounded-3 mb-4 py-2 px-3" role="alert">
                            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                            <div><?= htmlspecialchars($msg) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 shadow-sm rounded-3 mb-4 py-2 px-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="profile" class="profile-edit-form">
                        <?= csrf_input() ?>

                        <div class="profile-form-section mb-4">
                            <h2 class="profile-section-legend">
                                <i class="bi bi-person text-success me-1" aria-hidden="true"></i>
                                <span>Personal Details</span>
                            </h2>
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="profile-field-label" for="profileFullName">Full Name</label>
                                    <div class="input-group profile-input-group">
                                        <span class="input-group-text bg-white text-muted">
                                            <i class="bi bi-person" aria-hidden="true"></i>
                                        </span>
                                        <input type="text" id="profileFullName" name="full_name" class="form-control profile-field-input" value="<?= htmlspecialchars($user['full_name']) ?>" required autocomplete="name">
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <label class="profile-field-label">Account Email <span class="badge bg-light text-muted border ms-1 fw-normal"><i class="bi bi-lock-fill"></i> Locked</span></label>
                                    <div class="input-group profile-input-group is-locked">
                                        <span class="input-group-text bg-light text-muted">
                                            <i class="bi bi-envelope" aria-hidden="true"></i>
                                        </span>
                                        <input type="email" class="form-control profile-field-input bg-light" value="<?= htmlspecialchars($user['email']) ?>" disabled aria-label="Account email locked">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-form-section mb-4">
                            <h2 class="profile-section-legend">
                                <i class="bi bi-geo-alt text-success me-1" aria-hidden="true"></i>
                                <span>Default Delivery Address</span>
                            </h2>
                            <div class="input-group profile-input-group">
                                <span class="input-group-text bg-white text-muted align-self-stretch pt-2">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                </span>
                                <textarea id="profileAddress" name="address" class="form-control profile-field-input" rows="3" placeholder="Street name, apartment, unit, city, state, postal code" autocomplete="street-address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="profile-form-section mb-4">
                            <h2 class="profile-section-legend">
                                <i class="bi bi-shield-lock text-success me-1" aria-hidden="true"></i>
                                <span>Security Credentials</span>
                            </h2>
                            <label class="profile-field-label" for="profilePassword">
                                New Password <span class="text-muted fw-normal" style="text-transform: none;">(Leave blank to keep current)</span>
                            </label>
                            <div class="input-group profile-input-group">
                                <span class="input-group-text bg-white text-muted">
                                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                </span>
                                <input type="password" id="profilePassword" name="password" class="form-control profile-field-input" placeholder="Minimum 6 characters" autocomplete="new-password">
                                <button type="button" class="btn btn-password-toggle text-muted" onclick="togglePasswordVisibility('profilePassword', this)" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="profile-form-actions d-flex justify-content-between align-items-center flex-wrap gap-3 pt-2">
                            <button type="submit" class="profile-save-btn">
                                <i class="bi bi-check2" aria-hidden="true"></i>
                                <span>Save Changes</span>
                            </button>
                            <span class="text-muted small"><i class="bi bi-shield-check text-success me-1" aria-hidden="true"></i> Encrypted with 256-bit SSL</span>
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
