<?php
// auth/login.php
// Customer Authentication Portal for FreshCart
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$appRoot = $appRoot ? $appRoot . '/' : '/';

$flashToast = $_SESSION['flash_toast'] ?? null;
if ($flashToast) {
    unset($_SESSION['flash_toast']);
}

$prefillEmail = $_SESSION['prefill_email'] ?? '';
if ($prefillEmail) {
    unset($_SESSION['prefill_email']);
    $email = $prefillEmail;
}
unset($_SESSION['prefill_password']);

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!check_rate_limit('login_' . ($email ?: 'anon'), 6, 60)) {
        $error_message = "Too many login attempts. Please wait 1 minute.";
    } elseif (!verify_csrf_token()) {
        $error_message = "Security validation failed. Please refresh the page.";
    } else {
        $adminUser = function_exists('get_config_var') ? get_config_var('ADMIN_USERNAME') : getenv('ADMIN_USERNAME');
        $adminPass = function_exists('get_config_var') ? get_config_var('ADMIN_PASSWORD') : getenv('ADMIN_PASSWORD');

        // Only allow dev fallback in local environment when explicitly unset
        if (($isLocal ?? false) && (empty($adminUser) || empty($adminPass))) {
            $adminUser = $adminUser ?: 'admin';
            $adminPass = $adminPass ?: 'admin123';
        }

        if (!empty($adminUser) && !empty($adminPass) && hash_equals($adminUser, $email) && hash_equals($adminPass, $password)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = 'Administrator';
            $_SESSION['role'] = 'admin';
            header("Location: " . $appRoot . "admin/");
            exit;
        }

        // 2. Query user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'] ?? 'customer';

            if (($user['role'] ?? '') === 'admin') {
                $_SESSION['admin_logged_in'] = true;
                header("Location: " . $appRoot . "admin/");
                exit;
            }

            header("Location: " . $appRoot);
            exit;
        } else {
            $error_message = "Invalid email or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - FreshCart Market</title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="<?= $appRoot ?>assets/images/favicon.png">
    <link rel="shortcut icon" href="<?= $appRoot ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= $appRoot ?>assets/images/logo.png">
    <meta name="description" content="Sign in to your FreshCart account to access your farm-fresh deliveries and subscriptions.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $appRoot ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

    <main class="auth-split-wrapper">
        <!-- Left: Farmstead Showcase Panel -->
        <section class="auth-showcase-panel" style="background-image: url('https://images.unsplash.com/photo-1542838132-92c53300491e?w=1200&auto=format&fit=crop&q=80');" aria-label="Farmstead Storytelling">
            <div class="auth-showcase-top">
                <a href="<?= $appRoot ?>" class="auth-brand-logo" title="Return to FreshCart Home">
                    <img src="<?= $appRoot ?>assets/images/logo.png" alt="FreshCart Logo" class="auth-brand-logo-img" width="36" height="36">
                    <span>FreshCart</span><span class="auth-brand-dot"></span>
                </a>
            </div>
            
            <div class="auth-showcase-bottom">
                <div class="auth-showcase-kicker">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    <span>Direct Agricultural Sourcing</span>
                </div>
                <h1 class="auth-showcase-title">Sourced at dawn. At your table by evening.</h1>
                <p class="auth-showcase-desc">Log in to track your active 4&deg;C cold-chain harvest deliveries, manage seasonal produce subscriptions, and connect directly with independent family farms.</p>
                
                <div class="auth-proof-card">
                    <img src="https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=120&auto=format&fit=crop&q=80" alt="Mateo Alvarez, independent organic grower" class="auth-proof-avatar" width="48" height="48" loading="lazy">
                    <div class="auth-proof-content">
                        <p class="auth-proof-quote">&ldquo;FreshCart connects our family orchards directly to households that cherish clean, living food.&rdquo;</p>
                        <span class="auth-proof-author">Mateo Alvarez &bull; Alvarez Organic Groves</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Auth Form Panel -->
        <section class="auth-form-panel" aria-label="Sign In Form">
            <div class="auth-card">
                <!-- Mobile App Header with Back Navigation -->
                <div class="auth-mobile-header">
                    <a href="<?= $appRoot ?>" class="auth-back-pill" aria-label="Back to Storefront">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <span>Market</span>
                    </a>
                    <a href="<?= $appRoot ?>" class="auth-mobile-logo d-inline-flex align-items-center gap-2" aria-label="FreshCart Home">
                        <img src="<?= $appRoot ?>assets/images/logo.png" alt="FreshCart Logo" class="auth-mobile-logo-img" width="28" height="28">
                        <span>FreshCart<span class="auth-mobile-logo-dot">.</span></span>
                    </a>
                </div>

                <!-- 1-Tap Segmented Auth Switcher -->
                <div class="auth-segmented-switch" role="tablist" aria-label="Account Access Options">
                    <a href="login" class="auth-switch-tab active" role="tab" aria-selected="true">Sign In</a>
                    <a href="register" class="auth-switch-tab" role="tab" aria-selected="false">Create Account</a>
                </div>

                <div class="auth-card-header">
                    <h2 class="auth-card-title">Welcome Back</h2>
                    <p class="auth-card-subtitle">Sign in to manage your orders &amp; fresh harvests</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                        <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                        <div><?= htmlspecialchars($error_message) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <?= csrf_input() ?>
                    
                    <div class="auth-field-group">
                        <label for="loginEmail" class="auth-field-label">Email Address or Username</label>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" id="loginEmail" name="email" class="auth-input" placeholder="you@example.com or admin" value="<?= htmlspecialchars($email ?? '') ?>" required autofocus autocomplete="username" inputmode="email" autocapitalize="none" autocorrect="off">
                        </div>
                    </div>

                    <div class="auth-field-group">
                        <div class="auth-field-label d-flex align-items-center justify-content-between">
                            <label for="loginPassword" class="m-0">Password</label>
                            <a href="forgot-password" class="auth-forgot-link" id="btnForgotPass">Forgot Password?</a>
                        </div>
                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input type="password" id="loginPassword" name="password" class="auth-input" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit" id="btnLoginSubmit">
                        <span>Sign In</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>


                    <div class="auth-footer-nav">
                        <div class="auth-switch-prompt">Don't have an account? <a href="register" class="auth-nav-link">Create an account</a></div>
                        <div>
                            <a href="<?= $appRoot ?>" class="auth-back-link">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                <span>Back to Market</span>
                            </a>
                        </div>
                    </div>

                    <div class="auth-trust-strip">
                        <span class="auth-trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i> SSL 256-Bit</span>
                        <span class="auth-trust-item"><i class="bi bi-patch-check" aria-hidden="true"></i> Farm Guaranteed</span>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- ============================================================ -->
    <!-- FORGOT PASSWORD POP-UP MODAL (FreshCart Interactive Reset)   -->
    <!-- ============================================================ -->
    <div class="fc-modal-backdrop" id="forgotModal" role="dialog" aria-modal="true" aria-labelledby="modalForgotTitle" tabindex="-1">
        <div class="fc-modal-dialog">
            <div class="fc-modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="otp-icon-badge m-0" style="width: 38px; height: 38px; font-size: 1.15rem; border-radius: 10px;">
                        <i class="bi bi-key-fill" aria-hidden="true"></i>
                    </span>
                    <h5 class="m-0 fw-bold text-dark fs-6" id="modalForgotTitle">Reset Password</h5>
                </div>
                <button type="button" class="fc-modal-close" id="btnCloseForgotModal" aria-label="Close dialog">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            
            <div class="fc-modal-body">
                <!-- STEP 1: Enter Email -->
                <div id="forgotStep1">
                    <p class="text-muted small mb-3">
                        Enter your registered email address. We will dispatch a 6-digit OTP verification code to reset your password.
                    </p>
                    <div id="forgotAlert1" class="alert alert-danger py-2 px-3 small border-0 rounded-3 d-none mb-3"></div>
                    <form id="forgotForm1">
                        <div class="auth-field-group mb-3">
                            <label for="modalForgotEmail" class="auth-field-label">Email Address</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <input type="email" id="modalForgotEmail" name="email" class="auth-input" placeholder="you@example.com" required autocomplete="email" inputmode="email">
                            </div>
                        </div>
                        <button type="submit" class="btn-auth-submit mt-2" id="btnSubmitEmail">
                            <span>Send Verification Code</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <!-- STEP 2: Enter OTP Code -->
                <div id="forgotStep2" class="d-none">
                    <p class="text-muted small mb-2 text-center">
                        Enter the 6-digit verification code dispatched to<br>
                        <strong class="text-dark" id="displayResetEmail"></strong>
                    </p>

                    <div id="forgotAlert2" class="alert alert-danger py-2 px-3 small border-0 rounded-3 d-none mb-3"></div>

                    <form id="forgotForm2" class="d-flex flex-column gap-3">
                        <div>
                            <label for="modalOtpInput" class="auth-field-label text-center d-block mb-1">6-Digit One-Time Code</label>
                            <input type="text" id="modalOtpInput" name="otp_code" class="otp-input-field" placeholder="••••••" maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code" required>
                        </div>
                        <div class="otp-timer-strip">
                            <span class="d-flex align-items-center gap-1">
                                <i class="bi bi-stopwatch" aria-hidden="true"></i>
                                <span>Code expires in:</span>
                            </span>
                            <span id="modalOtpCountdown" class="otp-countdown-val">10:00</span>
                        </div>
                        <button type="submit" class="btn-auth-submit" id="btnSubmitOtp">
                            <span>Verify Code</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="d-flex align-items-center justify-content-between pt-3 mt-3 border-top">
                        <button type="button" id="modalResendBtn" class="otp-resend-btn" disabled>
                            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                            <span id="modalResendText">Resend Code (60s)</span>
                        </button>
                        <button type="button" id="btnBackToEmail" class="btn btn-link p-0 small text-muted text-decoration-none">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Change Email
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Set New Password -->
                <div id="forgotStep3" class="d-none">
                    <p class="text-muted small mb-3">
                        Create a secure, new password for your account (at least 6 characters).
                    </p>
                    <div id="forgotAlert3" class="alert alert-danger py-2 px-3 small border-0 rounded-3 d-none mb-3"></div>
                    <form id="forgotForm3" class="d-flex flex-column gap-3">
                        <div class="auth-field-group">
                            <div class="auth-field-label">
                                <label for="modalNewPassword" class="m-0">New Password</label>
                                <span class="text-muted fw-normal small">Min 6 characters</span>
                            </div>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                                <input type="password" id="modalNewPassword" name="new_password" class="auth-input" placeholder="Enter new password" minlength="6" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('modalNewPassword', this)" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="auth-field-group">
                            <label for="modalConfirmPassword" class="auth-field-label">Confirm New Password</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                                <input type="password" id="modalConfirmPassword" name="confirm_password" class="auth-input" placeholder="Confirm new password" minlength="6" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('modalConfirmPassword', this)" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-auth-submit" id="btnSubmitReset">
                            <span>Update Password</span>
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="fresh-toast-container" id="freshToastContainer" aria-live="polite" aria-atomic="true"></div>

    <script src="<?= $appRoot ?>assets/js/toast.js?v=<?= time() ?>"></script>
    <script>
    const csrfToken = <?= json_encode(get_csrf_token()) ?>;
    const forgotUrl = '<?= $appRoot ?>forgot-password?ajax=1';

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($flashToast): ?>
        if (window.FreshToast) {
            FreshToast.show({
                type: <?= json_encode($flashToast['type'] ?? 'success') ?>,
                title: <?= json_encode($flashToast['title'] ?? 'Notice') ?>,
                message: <?= json_encode($flashToast['message'] ?? '') ?>,
                duration: 5000
            });
        }
        <?php elseif (!empty($error_message)): ?>
        if (window.FreshToast) {
            FreshToast.show({
                type: 'danger',
                title: 'Sign In Notice',
                message: <?= json_encode($error_message) ?>,
                duration: 5000
            });
        }
        <?php endif; ?>

        // Forgot Password Modal Controller
        const forgotModal = document.getElementById('forgotModal');
        const btnForgotPass = document.getElementById('btnForgotPass');
        const btnCloseForgotModal = document.getElementById('btnCloseForgotModal');
        const step1 = document.getElementById('forgotStep1');
        const step2 = document.getElementById('forgotStep2');
        const step3 = document.getElementById('forgotStep3');
        const form1 = document.getElementById('forgotForm1');
        const form2 = document.getElementById('forgotForm2');
        const form3 = document.getElementById('forgotForm3');
        const alert1 = document.getElementById('forgotAlert1');
        const alert2 = document.getElementById('forgotAlert2');
        const alert3 = document.getElementById('forgotAlert3');
        const modalEmailInput = document.getElementById('modalForgotEmail');
        const modalOtpInput = document.getElementById('modalOtpInput');
        const displayResetEmail = document.getElementById('displayResetEmail');
        const modalOtpCountdown = document.getElementById('modalOtpCountdown');
        const modalResendBtn = document.getElementById('modalResendBtn');
        const modalResendText = document.getElementById('modalResendText');
        const btnBackToEmail = document.getElementById('btnBackToEmail');

        let otpTimer = null;
        let resendTimer = null;

        function openModal() {
            forgotModal.classList.add('show');
            document.body.style.overflow = 'hidden';
            if (form1) form1.reset();
            if (form2) form2.reset();
            if (form3) form3.reset();
            // Pre-fill email from login field if entered
            const loginEmailVal = document.getElementById('loginEmail')?.value.trim();
            if (loginEmailVal && loginEmailVal.includes('@')) {
                modalEmailInput.value = loginEmailVal;
            }
            showStep(1);
            modalEmailInput.focus();
        }

        function closeModal() {
            forgotModal.classList.remove('show');
            document.body.style.overflow = '';
            clearInterval(otpTimer);
            clearInterval(resendTimer);
        }

        function showStep(num) {
            step1.classList.add('d-none');
            step2.classList.add('d-none');
            step3.classList.add('d-none');
            alert1.classList.add('d-none');
            alert2.classList.add('d-none');
            alert3.classList.add('d-none');

            if (num === 1) {
                step1.classList.remove('d-none');
                modalEmailInput.focus();
            } else if (num === 2) {
                step2.classList.remove('d-none');
                modalOtpInput.value = '';
                modalOtpInput.focus();
            } else if (num === 3) {
                step3.classList.remove('d-none');
                document.getElementById('modalNewPassword')?.focus();
            }
        }

        function showAlert(el, msg) {
            el.textContent = msg;
            el.classList.remove('d-none');
        }

        function startOtpCountdown(seconds) {
            clearInterval(otpTimer);
            let left = seconds;
            function update() {
                if (left <= 0) {
                    clearInterval(otpTimer);
                    modalOtpCountdown.textContent = 'Expired';
                    modalOtpCountdown.classList.remove('text-success');
                    modalOtpCountdown.classList.add('text-danger');
                } else {
                    left--;
                    const m = Math.floor(left / 60);
                    const s = left % 60;
                    modalOtpCountdown.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                }
            }
            update();
            otpTimer = setInterval(update, 1000);
        }

        function startResendCooldown(seconds) {
            clearInterval(resendTimer);
            let left = seconds;
            modalResendBtn.disabled = true;
            function update() {
                if (left <= 0) {
                    clearInterval(resendTimer);
                    modalResendBtn.disabled = false;
                    modalResendText.textContent = 'Resend Code';
                } else {
                    modalResendText.textContent = 'Resend Code (' + left + 's)';
                    left--;
                }
            }
            update();
            resendTimer = setInterval(update, 1000);
        }

        btnForgotPass?.addEventListener('click', function(e) {
            e.preventDefault();
            openModal();
        });

        btnCloseForgotModal?.addEventListener('click', closeModal);
        forgotModal?.addEventListener('click', function(e) {
            if (e.target === forgotModal) closeModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && forgotModal.classList.contains('show')) {
                closeModal();
            }
        });

        btnBackToEmail?.addEventListener('click', function() {
            showStep(1);
        });

        // Clean numeric input on OTP field
        modalOtpInput?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                form2.requestSubmit();
            }
        });

        // Step 1: Send OTP
        form1?.addEventListener('submit', async function(e) {
            e.preventDefault();
            alert1.classList.add('d-none');
            const submitBtn = document.getElementById('btnSubmitEmail');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending...';

            const email = modalEmailInput.value.trim();
            const formData = new FormData();
            formData.append('auth_action', 'send_otp');
            formData.append('csrf_token', csrfToken);
            formData.append('email', email);

            try {
                const res = await fetch(forgotUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    if (window.FreshToast) {
                        FreshToast.show({
                            type: 'success',
                            title: 'Code Dispatched',
                            message: data.message || 'Verification code sent to your email.'
                        });
                    }
                    displayResetEmail.textContent = email;
                    startOtpCountdown(data.seconds_left || 600);
                    startResendCooldown(data.resend_seconds || 60);
                    showStep(2);
                } else {
                    showAlert(alert1, data.message || 'Failed to send verification code.');
                }
            } catch (err) {
                showAlert(alert1, 'Connection error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        // Step 2: Verify OTP
        form2?.addEventListener('submit', async function(e) {
            e.preventDefault();
            alert2.classList.add('d-none');
            const submitBtn = document.getElementById('btnSubmitOtp');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Verifying...';

            const otp = modalOtpInput.value.trim();
            const formData = new FormData();
            formData.append('auth_action', 'verify_otp');
            formData.append('csrf_token', csrfToken);
            formData.append('otp_code', otp);

            try {
                const res = await fetch(forgotUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    clearInterval(otpTimer);
                    clearInterval(resendTimer);
                    if (window.FreshToast) {
                        FreshToast.show({
                            type: 'success',
                            title: 'Code Verified!',
                            message: data.message || 'Please create your new password.',
                            duration: 4000
                        });
                    }
                    showStep(3);
                } else {
                    showAlert(alert2, data.message || 'Invalid verification code.');
                    if (data.locked || data.expired) {
                        setTimeout(() => showStep(1), 2000);
                    }
                }
            } catch (err) {
                showAlert(alert2, 'Connection error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        // Resend OTP
        modalResendBtn?.addEventListener('click', async function() {
            modalResendBtn.disabled = true;
            const originalText = modalResendText.textContent;
            modalResendText.textContent = 'Sending...';

            const formData = new FormData();
            formData.append('auth_action', 'resend_otp');
            formData.append('csrf_token', csrfToken);

            try {
                const res = await fetch(forgotUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    if (window.FreshToast) {
                        FreshToast.show({
                            type: 'success',
                            title: 'Code Resent',
                            message: data.message || 'A fresh code has been sent.'
                        });
                    }
                    startOtpCountdown(data.seconds_left || 600);
                    startResendCooldown(data.resend_seconds || 60);
                } else {
                    showAlert(alert2, data.message || 'Failed to resend code.');
                }
            } catch (err) {
                showAlert(alert2, 'Connection error. Please try again.');
            }
        });

        // Step 3: Reset Password
        form3?.addEventListener('submit', async function(e) {
            e.preventDefault();
            alert3.classList.add('d-none');
            const submitBtn = document.getElementById('btnSubmitReset');
            const newPass = document.getElementById('modalNewPassword').value;
            const confirmPass = document.getElementById('modalConfirmPassword').value;

            if (newPass.length < 6) {
                showAlert(alert3, 'Password must be at least 6 characters.');
                return;
            }
            if (newPass !== confirmPass) {
                showAlert(alert3, 'Passwords do not match.');
                return;
            }

            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';

            const formData = new FormData();
            formData.append('auth_action', 'reset_password');
            formData.append('csrf_token', csrfToken);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confirmPass);

            try {
                const res = await fetch(forgotUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    closeModal();
                    if (window.FreshToast) {
                        FreshToast.show({
                            type: 'success',
                            title: 'Password Updated!',
                            message: 'Your password was reset successfully. Please sign in.',
                            duration: 6000
                        });
                    }
                    if (data.email) {
                        const loginEmailEl = document.getElementById('loginEmail');
                        if (loginEmailEl) loginEmailEl.value = data.email;
                    }
                    const loginPassEl = document.getElementById('loginPassword');
                    if (loginPassEl) {
                        loginPassEl.value = '';
                        loginPassEl.focus();
                    }
                } else {
                    showAlert(alert3, data.message || 'Failed to update password.');
                }
            } catch (err) {
                showAlert(alert3, 'Connection error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    });
    </script>
</body>
</html>


