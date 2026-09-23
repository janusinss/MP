<?php
// auth/register.php
// Customer Registration Portal with Email OTP Verification for FreshCart
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reset / Cancel OTP verification requested
if (isset($_GET['action']) && $_GET['action'] === 'reset_otp') {
    unset($_SESSION['pending_reg']);
    header("Location: register");
    exit;
}

$error = '';
$success = '';
$toastMessage = '';
$toastType = 'info';
$toastTitle = 'Notice';
$redirectUrl = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['auth_action'] ?? 'init_register';

    if (!verify_csrf_token()) {
        $error = "Security validation failed. Please refresh the page.";
        $toastMessage = $error;
        $toastType = "danger";
        $toastTitle = "Security Notice";
    } elseif ($action === 'init_register') {
        // Step 1: User fills registration form
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = trim($_POST['address'] ?? '');

        if (!check_rate_limit('register_attempt', 5, 60)) {
            $error = "Too many registration attempts. Please wait 1 minute.";
            $toastMessage = $error;
            $toastType = "warning";
            $toastTitle = "Rate Limited";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $toastMessage = $error;
            $toastType = "danger";
            $toastTitle = "Invalid Email";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
            $toastMessage = $error;
            $toastType = "danger";
            $toastTitle = "Password Too Short";
        } else {
            // Check if email already registered
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email is already registered! Please sign in.";
                $toastMessage = $error;
                $toastType = "warning";
                $toastTitle = "Already Registered";
            } else {
                // Generate 6-digit cryptographic OTP
                $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $mailRes = send_otp_email($email, $name, $otp);

                $_SESSION['pending_reg'] = [
                    'full_name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'address' => $address,
                    'otp' => $otp,
                    'otp_expires' => time() + 600, // 10 minutes
                    'resend_available' => time() + 60, // 60s cooldown
                    'attempts' => 0,
                    'dev_fallback' => $mailRes['dev_fallback'] ?? false,
                    'dev_message' => $mailRes['message'] ?? '',
                ];

                $toastMessage = "Verification code dispatched to {$email}. Please enter the 6-digit code below.";
                $toastType = "success";
                $toastTitle = "Verification Code Sent";
            }
        }
    } elseif ($action === 'verify_otp') {
        // Step 2: Validate 6-digit OTP
        if (empty($_SESSION['pending_reg'])) {
            $error = "Session expired or missing. Please fill the registration form.";
            $toastMessage = $error;
            $toastType = "danger";
            $toastTitle = "Session Expired";
        } else {
            $pending = &$_SESSION['pending_reg'];
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;

            if ($pending['attempts'] > 5) {
                unset($_SESSION['pending_reg']);
                $error = "Too many incorrect attempts. Please start registration again.";
                $toastMessage = $error;
                $toastType = "danger";
                $toastTitle = "Verification Locked";
            } elseif (time() > $pending['otp_expires']) {
                $error = "Verification code has expired. Please request a new code.";
                $toastMessage = $error;
                $toastType = "warning";
                $toastTitle = "Code Expired";
            } else {
                $rawOtp = trim($_POST['otp_code'] ?? '');
                $inputOtp = preg_replace('/[^0-9]/', '', $rawOtp);

                if (strlen($inputOtp) !== 6 || !hash_equals((string)$pending['otp'], $inputOtp)) {
                    $remaining = max(0, 5 - $pending['attempts']);
                    $error = "Invalid verification code. ($remaining attempts remaining)";
                    $toastMessage = $error;
                    $toastType = "danger";
                    $toastTitle = "Incorrect Code";
                } else {
                    // Valid OTP: Persist user into users table
                    $sql = "INSERT INTO users (full_name, email, password, address, role) VALUES (?, ?, ?, ?, 'customer')";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([
                        $pending['full_name'],
                        $pending['email'],
                        $pending['password_hash'],
                        $pending['address']
                    ])) {
                        unset($_SESSION['pending_reg']);
                        // Set flash message for login screen
                        $_SESSION['flash_toast'] = [
                            'type' => 'success',
                            'title' => 'Account Verified!',
                            'message' => 'Your account has been verified and created successfully. Please sign in.'
                        ];
                        // Toast shown on register screen before transition
                        $toastMessage = "Account verified successfully! Redirecting you to login...";
                        $toastType = "success";
                        $toastTitle = "Account Verified!";
                        $redirectUrl = 'login';
                    } else {
                        $error = "Registration failed to persist. Please try again.";
                        $toastMessage = $error;
                        $toastType = "danger";
                        $toastTitle = "System Error";
                    }
                }
            }
        }
    } elseif ($action === 'resend_otp') {
        // Step 3: Resend code
        if (empty($_SESSION['pending_reg'])) {
            $error = "Session expired. Please fill the registration form.";
            $toastMessage = $error;
            $toastType = "danger";
            $toastTitle = "Session Expired";
        } else {
            $pending = &$_SESSION['pending_reg'];
            if (time() < $pending['resend_available']) {
                $wait = $pending['resend_available'] - time();
                $error = "Please wait {$wait}s before requesting a new code.";
                $toastMessage = $error;
                $toastType = "warning";
                $toastTitle = "Please Wait";
            } else {
                $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $pending['otp'] = $newOtp;
                $pending['otp_expires'] = time() + 600;
                $pending['resend_available'] = time() + 60;
                $pending['attempts'] = 0;

                $mailRes = send_otp_email($pending['email'], $pending['full_name'], $newOtp);
                $pending['dev_fallback'] = $mailRes['dev_fallback'] ?? false;
                $pending['dev_message'] = $mailRes['message'] ?? '';

                $toastMessage = "A fresh verification code has been dispatched to {$pending['email']}.";
                $toastType = "success";
                $toastTitle = "Code Resent";
            }
        }
    }
}

$hasPending = !empty($_SESSION['pending_reg']);
$pendingEmail = $hasPending ? $_SESSION['pending_reg']['email'] : '';
$resendSeconds = $hasPending ? max(0, $_SESSION['pending_reg']['resend_available'] - time()) : 0;
$otpSecondsLeft = $hasPending ? max(0, $_SESSION['pending_reg']['otp_expires'] - time()) : 0;

$appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$appRoot = $appRoot ? $appRoot . '/' : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $hasPending ? 'Verify Email' : 'Create Account' ?> - FreshCart Market</title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="<?= $appRoot ?>assets/images/favicon.png">
    <link rel="shortcut icon" href="<?= $appRoot ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= $appRoot ?>assets/images/logo.png">
    <meta name="description" content="Create a FreshCart account to receive fresh farm-to-door organic deliveries and support independent growers.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $appRoot ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

    <main class="auth-split-wrapper">
        <!-- Left: Farmstead Showcase Panel -->
        <section class="auth-showcase-panel" style="background-image: url('https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=1200&auto=format&fit=crop&q=80');" aria-label="Farmstead Storytelling">
            <div class="auth-showcase-top">
                <a href="<?= $appRoot ?>" class="auth-brand-logo" title="Return to FreshCart Home">
                    <img src="<?= $appRoot ?>assets/images/logo.png" alt="FreshCart Logo" class="auth-brand-logo-img" width="36" height="36">
                    <span>FreshCart</span><span class="auth-brand-dot"></span>
                </a>
            </div>
            
            <div class="auth-showcase-bottom">
                <div class="auth-showcase-kicker">
                    <i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i>
                    <span>Regenerative Agriculture Network</span>
                </div>
                <h1 class="auth-showcase-title">Direct from the growers who cultivate the soil.</h1>
                <p class="auth-showcase-desc">Join our transparent food network to enjoy peak-season produce, pasture-raised eggs, and farmstead cheeses harvested within 24 hours of delivery.</p>
                
                <div class="auth-proof-card">
                    <img src="https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=120&auto=format&fit=crop&q=80" alt="Alvarez Organic Groves" class="auth-proof-avatar" width="48" height="48" loading="lazy">
                    <div class="auth-proof-content">
                        <p class="auth-proof-quote">&ldquo;78% of every customer purchase returns directly to our farm, preserving organic heritage seeds for tomorrow.&rdquo;</p>
                        <span class="auth-proof-author">Clara Alvarez &bull; Alvarez Organic Groves</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Auth Form Panel -->
        <section class="auth-form-panel" aria-label="Registration and Verification Portal">
            <div class="auth-card">
                <!-- Mobile App Header with Back Navigation -->
                <div class="auth-mobile-header">
                    <a href="<?= $hasPending ? 'register?action=reset_otp' : $appRoot ?>" class="auth-back-pill" aria-label="<?= $hasPending ? 'Edit details' : 'Back to Market' ?>">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <span><?= $hasPending ? 'Edit' : 'Market' ?></span>
                    </a>
                    <a href="<?= $appRoot ?>" class="auth-mobile-logo d-inline-flex align-items-center gap-2" aria-label="FreshCart Home">
                        <img src="<?= $appRoot ?>assets/images/logo.png" alt="FreshCart Logo" class="auth-mobile-logo-img" width="28" height="28">
                        <span>FreshCart<span class="auth-mobile-logo-dot">.</span></span>
                    </a>
                </div>

                <!-- 1-Tap Segmented Auth Switcher (Only in Step 1) -->
                <?php if (!$hasPending): ?>
                <div class="auth-segmented-switch" role="tablist" aria-label="Account Access Options">
                    <a href="login" class="auth-switch-tab" role="tab" aria-selected="false">Sign In</a>
                    <a href="register" class="auth-switch-tab active" role="tab" aria-selected="true">Create Account</a>
                </div>
                <?php endif; ?>

                <?php if ($hasPending): ?>
                    <!-- ========================================== -->
                    <!-- STEP 2: OTP VERIFICATION CODE SCREEN       -->
                    <!-- ========================================== -->
                    <div class="otp-verify-card">
                        <div class="text-center mb-2">
                            <div class="otp-icon-badge mx-auto">
                                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            </div>
                            <h2 class="auth-card-title mb-1">Verify Your Email</h2>
                            <p class="auth-card-subtitle text-muted mb-0">
                                Enter the 6-digit code sent to<br>
                                <strong class="text-dark"><?= htmlspecialchars($pendingEmail) ?></strong>
                            </p>
                        </div>

                        <?php if (!empty($_SESSION['pending_reg']['dev_fallback'])): ?>
                            <div class="otp-dev-hint" role="note">
                                <i class="bi bi-info-circle-fill text-success fs-5 flex-shrink-0" aria-hidden="true"></i>
                                <div>
                                    <div class="fw-bold mb-1">Sandbox / Dev Verification Code</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Use Code:</span>
                                        <span class="otp-dev-code"><?= htmlspecialchars($_SESSION['pending_reg']['otp']) ?></span>
                                    </div>
                                    <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                        (Resend sandbox delivers directly to account owner: janusdominic0@gmail.com)
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off" class="d-flex flex-column gap-3">
                            <?= csrf_input() ?>
                            <input type="hidden" name="auth_action" value="verify_otp">

                            <div>
                                <label for="otpCodeInput" class="auth-field-label text-center d-block mb-2">6-Digit One-Time Code</label>
                                <input type="text" 
                                       id="otpCodeInput" 
                                       name="otp_code" 
                                       class="otp-input-field" 
                                       placeholder="••••••" 
                                       maxlength="6" 
                                       pattern="[0-9]*" 
                                       inputmode="numeric" 
                                       autocomplete="one-time-code" 
                                       required 
                                       autofocus>
                            </div>

                            <div class="otp-timer-strip">
                                <span class="d-flex align-items-center gap-1">
                                    <i class="bi bi-stopwatch" aria-hidden="true"></i>
                                    <span>Code expires in:</span>
                                </span>
                                <span id="otpCountdown" class="otp-countdown-val" data-seconds="<?= $otpSecondsLeft ?>">
                                    <?= sprintf('%02d:%02d', floor($otpSecondsLeft / 60), $otpSecondsLeft % 60) ?>
                                </span>
                            </div>

                            <button type="submit" class="btn-auth-submit">
                                <span>Verify &amp; Activate Account</span>
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </button>
                        </form>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <form method="POST" class="d-inline m-0">
                                <?= csrf_input() ?>
                                <input type="hidden" name="auth_action" value="resend_otp">
                                <button type="submit" id="resendBtn" class="otp-resend-btn" <?= $resendSeconds > 0 ? 'disabled' : '' ?>>
                                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                                    <span id="resendText">Resend Code<?= $resendSeconds > 0 ? " ({$resendSeconds}s)" : '' ?></span>
                                </button>
                            </form>

                            <a href="register?action=reset_otp" class="small text-muted text-decoration-none">
                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Change Email
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ========================================== -->
                    <!-- STEP 1: INITIAL REGISTRATION FORM          -->
                    <!-- ========================================== -->
                    <div class="auth-card-header">
                        <h2 class="auth-card-title">Create Account</h2>
                        <p class="auth-card-subtitle">Start receiving farm-fresh harvests at your door</p>
                    </div>

                    <form method="POST" autocomplete="on">
                        <?= csrf_input() ?>
                        <input type="hidden" name="auth_action" value="init_register">
                        
                        <div class="auth-field-group">
                            <label for="regFullName" class="auth-field-label">Full Name</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <input type="text" id="regFullName" name="full_name" class="auth-input" placeholder="e.g. John Doe" value="<?= htmlspecialchars($name ?? '') ?>" required autofocus autocomplete="name" inputmode="text" autocapitalize="words">
                            </div>
                        </div>

                        <div class="auth-field-group">
                            <label for="regEmail" class="auth-field-label">Email Address</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <input type="email" id="regEmail" name="email" class="auth-input" placeholder="you@example.com" value="<?= htmlspecialchars($email ?? '') ?>" required autocomplete="email" inputmode="email" autocapitalize="none" autocorrect="off">
                            </div>
                        </div>

                        <div class="auth-field-group">
                            <div class="auth-field-label">
                                <label for="regPassword" class="m-0">Password</label>
                                <span class="text-muted fw-normal small">Min 6 characters</span>
                            </div>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                                <input type="password" id="regPassword" name="password" class="auth-input" placeholder="Create a secure password" minlength="6" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('regPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="auth-field-group">
                            <div class="auth-field-label">
                                <label for="regAddress" class="m-0">Delivery Address</label>
                                <span class="text-muted fw-normal small">Optional</span>
                            </div>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                                <input type="text" id="regAddress" name="address" class="auth-input" placeholder="Street Address, City, Postal Code" value="<?= htmlspecialchars($address ?? '') ?>" autocomplete="street-address" inputmode="text" autocapitalize="words">
                            </div>
                        </div>

                        <button type="submit" class="btn-auth-submit">
                            <span>Register Now</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>

                        <div class="auth-footer-nav">
                            <div class="auth-switch-prompt">Already have an account? <a href="login" class="auth-nav-link">Sign In</a></div>
                            <div>
                                <a href="<?= $appRoot ?>" class="auth-back-link">
                                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                    <span>Back to Market</span>
                                </a>
                            </div>
                        </div>

                        <div class="auth-trust-strip">
                            <span class="auth-trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i> SSL 256-Bit</span>
                            <span class="auth-trust-item"><i class="bi bi-patch-check" aria-hidden="true"></i> 100% Guaranteed</span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Floating Toast Pop Up Container (Bottom Left for Web / Mobile Docked) -->
    <div class="fresh-toast-container" id="freshToastContainer" aria-live="polite" aria-atomic="true"></div>

    <script src="<?= $appRoot ?>assets/js/toast.js?v=<?= time() ?>"></script>
    <script>
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

    // OTP Verification Timers & Auto-Focus
    document.addEventListener('DOMContentLoaded', function() {
        // Trigger Toast Pop-Up if message set
        <?php if (!empty($toastMessage)): ?>
        if (window.FreshToast) {
            FreshToast.show({
                type: <?= json_encode($toastType) ?>,
                title: <?= json_encode($toastTitle) ?>,
                message: <?= json_encode($toastMessage) ?>,
                duration: <?= !empty($redirectUrl) ? 3500 : 5000 ?>
            });
        }
        <?php endif; ?>

        <?php if (!empty($redirectUrl)): ?>
        setTimeout(function() {
            window.location.href = <?= json_encode($redirectUrl) ?>;
        }, 1800);
        <?php endif; ?>

        const otpInput = document.getElementById('otpCodeInput');
        if (otpInput) {
            otpInput.focus();
            // Automatically clean non-numeric inputs
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    const form = this.closest('form');
                    if (form) form.requestSubmit();
                }
            });
        }

        // Expiry Countdown
        const countdownEl = document.getElementById('otpCountdown');
        if (countdownEl) {
            let secondsLeft = parseInt(countdownEl.getAttribute('data-seconds'), 10) || 0;
            const timerInterval = setInterval(function() {
                if (secondsLeft <= 0) {
                    clearInterval(timerInterval);
                    countdownEl.textContent = 'Expired';
                    countdownEl.classList.remove('text-success');
                    countdownEl.classList.add('text-danger');
                } else {
                    secondsLeft--;
                    const m = Math.floor(secondsLeft / 60);
                    const s = secondsLeft % 60;
                    countdownEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                }
            }, 1000);
        }

        // Resend Cooldown Countdown
        const resendBtn = document.getElementById('resendBtn');
        const resendText = document.getElementById('resendText');
        if (resendBtn && resendText && resendBtn.disabled) {
            let match = resendText.textContent.match(/\((\d+)s\)/);
            let cooldown = match ? parseInt(match[1], 10) : 0;
            if (cooldown > 0) {
                const resendInterval = setInterval(function() {
                    cooldown--;
                    if (cooldown <= 0) {
                        clearInterval(resendInterval);
                        resendBtn.disabled = false;
                        resendText.textContent = 'Resend Code';
                    } else {
                        resendText.textContent = 'Resend Code (' + cooldown + 's)';
                    }
                }, 1000);
            }
        }
    });
    </script>
</body>
</html>
