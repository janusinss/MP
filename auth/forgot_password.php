<?php
// auth/forgot_password.php
// Password Reset Portal with Email OTP Verification for FreshCart
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1');

// Reset / Cancel OTP verification requested
if (isset($_GET['action']) && $_GET['action'] === 'cancel_reset') {
    unset($_SESSION['reset_password']);
    header("Location: login");
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
    $action = $_POST['auth_action'] ?? 'send_otp';

    if (!verify_csrf_token()) {
        $msg = "Security validation failed. Please refresh the page.";
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        $error = $msg;
        $toastMessage = $msg;
        $toastType = "danger";
        $toastTitle = "Security Notice";
    } elseif ($action === 'send_otp') {
        // Step 1: Send OTP to user's email
        $email = trim($_POST['email'] ?? '');

        if (!check_rate_limit('forgot_pw_' . md5($email ?: 'anon'), 5, 300)) {
            $msg = "Too many password reset requests. Please wait 5 minutes.";
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $error = $msg;
            $toastMessage = $msg;
            $toastType = "warning";
            $toastTitle = "Rate Limited";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Please enter a valid email address.";
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $error = $msg;
            $toastMessage = $msg;
            $toastType = "danger";
            $toastTitle = "Invalid Email";
        } else {
            // Find user in database
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Anti-enumeration defense: Generate OTP if found, but always respond with success
            $devFallback = false;
            $devOtp = '';

            if ($user) {
                $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $mailRes = send_password_reset_otp_email($user['email'], $user['full_name'], $otp);

                $isDev = ($isLocal ?? false) || !empty($mailRes['dev_fallback']);

                $_SESSION['reset_password'] = [
                    'user_id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'otp' => $otp,
                    'otp_expires' => time() + 600, // 10 minutes
                    'resend_available' => time() + 60, // 60s cooldown
                    'attempts' => 0,
                    'verified' => false,
                    'dev_fallback' => $isDev,
                    'dev_message' => $mailRes['message'] ?? '',
                ];

                $devFallback = $isDev;
                $devOtp = $devFallback ? $otp : '';
            } else {

                // Fake session state to prevent timing attacks, clear reset session
                unset($_SESSION['reset_password']);
                usleep(random_int(200000, 400000)); // 200-400ms timing normalization
            }

            $successMsg = "If an account is associated with {$email}, a 6-digit verification code has been sent.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'step' => 2,
                    'email' => $email,
                    'message' => $successMsg,
                    'dev_fallback' => $devFallback,
                    'dev_otp' => $devOtp,
                    'seconds_left' => 600,
                    'resend_seconds' => 60
                ]);
                exit;
            }

            $toastMessage = $successMsg;
            $toastType = "success";
            $toastTitle = "Verification Code Sent";
        }
    } elseif ($action === 'verify_otp') {
        // Step 2: Validate 6-digit OTP
        if (empty($_SESSION['reset_password'])) {
            $msg = "Session expired or missing. Please enter your email address again.";
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $error = $msg;
            $toastMessage = $msg;
            $toastType = "danger";
            $toastTitle = "Session Expired";
        } else {
            $reset = &$_SESSION['reset_password'];
            $reset['attempts'] = ($reset['attempts'] ?? 0) + 1;

            if ($reset['attempts'] > 5) {
                unset($_SESSION['reset_password']);
                $msg = "Too many incorrect attempts. Please request a new verification code.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(429);
                    echo json_encode(['success' => false, 'locked' => true, 'message' => $msg]);
                    exit;
                }
                $error = $msg;
                $toastMessage = $msg;
                $toastType = "danger";
                $toastTitle = "Reset Locked";
            } elseif (time() > $reset['otp_expires']) {
                $msg = "Verification code has expired. Please request a new code.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'expired' => true, 'message' => $msg]);
                    exit;
                }
                $error = $msg;
                $toastMessage = $msg;
                $toastType = "warning";
                $toastTitle = "Code Expired";
            } else {
                $rawOtp = trim($_POST['otp_code'] ?? '');
                $inputOtp = preg_replace('/[^0-9]/', '', $rawOtp);

                if (strlen($inputOtp) !== 6 || !hash_equals((string)$reset['otp'], $inputOtp)) {
                    $remaining = max(0, 5 - $reset['attempts']);
                    $msg = "Invalid verification code. ($remaining attempts remaining)";
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => $msg, 'remaining' => $remaining]);
                        exit;
                    }
                    $error = $msg;
                    $toastMessage = $msg;
                    $toastType = "danger";
                    $toastTitle = "Incorrect Code";
                } else {
                    $reset['verified'] = true;
                    $successMsg = "Code verified successfully! Please enter your new password.";
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'step' => 3, 'message' => $successMsg]);
                        exit;
                    }
                    $toastMessage = $successMsg;
                    $toastType = "success";
                    $toastTitle = "Code Verified";
                }
            }
        }
    } elseif ($action === 'resend_otp') {
        // Resend Code
        if (empty($_SESSION['reset_password'])) {
            $msg = "Session expired. Please enter your email address again.";
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $error = $msg;
        } else {
            $reset = &$_SESSION['reset_password'];
            if (time() < $reset['resend_available']) {
                $wait = $reset['resend_available'] - time();
                $msg = "Please wait {$wait}s before requesting a new code.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(429);
                    echo json_encode(['success' => false, 'message' => $msg, 'wait' => $wait]);
                    exit;
                }
                $error = $msg;
            } else {
                $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $reset['otp'] = $newOtp;
                $reset['otp_expires'] = time() + 600;
                $reset['resend_available'] = time() + 60;
                $reset['attempts'] = 0;

                $mailRes = send_password_reset_otp_email($reset['email'], $reset['full_name'], $newOtp);
                $reset['dev_fallback'] = $mailRes['dev_fallback'] ?? false;
                $reset['dev_message'] = $mailRes['message'] ?? '';

                $successMsg = "A fresh verification code has been dispatched to {$reset['email']}.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $successMsg,
                        'dev_fallback' => $reset['dev_fallback'],
                        'dev_otp' => $reset['dev_fallback'] ? $newOtp : '',
                        'seconds_left' => 600,
                        'resend_seconds' => 60
                    ]);
                    exit;
                }
                $toastMessage = $successMsg;
                $toastType = "success";
                $toastTitle = "Code Resent";
            }
        }
    } elseif ($action === 'reset_password') {
        // Step 3: Update password
        if (empty($_SESSION['reset_password']) || empty($_SESSION['reset_password']['verified'])) {
            $msg = "Unauthorized password reset attempt. Please complete verification first.";
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $error = $msg;
        } else {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 6) {
                $msg = "Password must be at least 6 characters.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
                $error = $msg;
            } elseif ($newPassword !== $confirmPassword) {
                $msg = "Passwords do not match. Please re-enter.";
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
                $error = $msg;
            } else {
                $userId = (int)$_SESSION['reset_password']['user_id'];
                $userEmail = $_SESSION['reset_password']['email'];
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed, $userId])) {
                    unset($_SESSION['reset_password']);
                    session_regenerate_id(true);

                    $_SESSION['flash_toast'] = [
                        'type' => 'success',
                        'title' => 'Password Reset!',
                        'message' => 'Your password has been updated successfully. Please sign in.'
                    ];

                    $successMsg = "Password reset successfully! Redirecting to login...";
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => $successMsg,
                            'redirect' => 'login',
                            'email' => $userEmail
                        ]);
                        exit;
                    }

                    $toastMessage = $successMsg;
                    $toastType = "success";
                    $toastTitle = "Password Reset!";
                    $redirectUrl = 'login';
                } else {
                    $msg = "Failed to update password. Please try again.";
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => $msg]);
                        exit;
                    }
                    $error = $msg;
                }
            }
        }
    }
}

$hasReset = !empty($_SESSION['reset_password']);
$isVerified = $hasReset && !empty($_SESSION['reset_password']['verified']);
$resetEmail = $hasReset ? $_SESSION['reset_password']['email'] : '';
$resendSeconds = $hasReset ? max(0, $_SESSION['reset_password']['resend_available'] - time()) : 0;
$otpSecondsLeft = $hasReset ? max(0, $_SESSION['reset_password']['otp_expires'] - time()) : 0;

$appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
$appRoot = $appRoot ? $appRoot . '/' : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FreshCart Market</title>
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="<?= $appRoot ?>assets/images/favicon.png">
    <link rel="shortcut icon" href="<?= $appRoot ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= $appRoot ?>assets/images/logo.png">
    <meta name="description" content="Reset your FreshCart account password securely using email OTP verification.">
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
                    <i class="bi bi-shield-check text-success" aria-hidden="true"></i>
                    <span>Account Security Protection</span>
                </div>
                <h1 class="auth-showcase-title">Your account security is our priority.</h1>
                <p class="auth-showcase-desc">We use single-use cryptographic verification codes dispatched directly to your inbox to protect your orders, harvest schedules, and account details.</p>
                
                <div class="auth-proof-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="otp-icon-badge m-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
                            <i class="bi bi-key-fill text-success" aria-hidden="true"></i>
                        </div>
                        <div class="auth-proof-content">
                            <span class="fw-bold d-block text-white" style="font-size: 0.95rem;">Encrypted &bull; Time-Limited</span>
                            <span class="text-white-50 small">Codes expire in 10 minutes and self-destruct after verification.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right: Auth Form Panel -->
        <section class="auth-form-panel" aria-label="Password Reset Portal">
            <div class="auth-card">
                <!-- Mobile App Header with Back Navigation -->
                <div class="auth-mobile-header">
                    <a href="login" class="auth-back-pill" aria-label="Back to Sign In">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <span>Sign In</span>
                    </a>
                    <a href="<?= $appRoot ?>" class="auth-mobile-logo d-inline-flex align-items-center gap-2" aria-label="FreshCart Home">
                        <img src="<?= $appRoot ?>assets/images/logo.png" alt="FreshCart Logo" class="auth-mobile-logo-img" width="28" height="28">
                        <span>FreshCart<span class="auth-mobile-logo-dot">.</span></span>
                    </a>
                </div>

                <?php if ($isVerified): ?>
                    <!-- ========================================== -->
                    <!-- STEP 3: SET NEW PASSWORD                   -->
                    <!-- ========================================== -->
                    <div class="auth-card-header">
                        <div class="otp-icon-badge mx-auto mb-3">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                        </div>
                        <h2 class="auth-card-title">Set New Password</h2>
                        <p class="auth-card-subtitle">Choose a secure password with at least 6 characters</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                            <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off" class="d-flex flex-column gap-3">
                        <?= csrf_input() ?>
                        <input type="hidden" name="auth_action" value="reset_password">

                        <div class="auth-field-group">
                            <div class="auth-field-label">
                                <label for="newPassword" class="m-0">New Password</label>
                                <span class="text-muted fw-normal small">Min 6 characters</span>
                            </div>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                                <input type="password" id="newPassword" name="new_password" class="auth-input" placeholder="Enter new password" minlength="6" required autofocus autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('newPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="auth-field-group">
                            <label for="confirmPassword" class="auth-field-label">Confirm New Password</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                                <input type="password" id="confirmPassword" name="confirm_password" class="auth-input" placeholder="Re-enter new password" minlength="6" required autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirmPassword', this)" aria-label="Show password" title="Toggle password visibility">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-auth-submit">
                            <span>Update Password</span>
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                        </button>

                        <div class="text-center mt-2">
                            <a href="login" class="auth-nav-link small">Return to Sign In</a>
                        </div>
                    </form>

                <?php elseif ($hasReset): ?>
                    <!-- ========================================== -->
                    <!-- STEP 2: ENTER OTP CODE                     -->
                    <!-- ========================================== -->
                    <div class="otp-verify-card">
                        <div class="text-center mb-2">
                            <div class="otp-icon-badge mx-auto">
                                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            </div>
                            <h2 class="auth-card-title mb-1">Verify Your Email</h2>
                            <p class="auth-card-subtitle text-muted mb-0">
                                Enter the 6-digit code sent to<br>
                                <strong class="text-dark"><?= htmlspecialchars($resetEmail) ?></strong>
                            </p>
                        </div>

                        <?php if (!empty($_SESSION['reset_password']['dev_fallback'])): ?>
                            <div class="otp-dev-hint" role="note">
                                <i class="bi bi-info-circle-fill text-success fs-5 flex-shrink-0" aria-hidden="true"></i>
                                <div>
                                    <div class="fw-bold mb-1">Sandbox / Dev Reset Code</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span>Use Code:</span>
                                        <span class="otp-dev-code"><?= htmlspecialchars($_SESSION['reset_password']['otp']) ?></span>
                                    </div>
                                    <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                        (Resend sandbox delivers directly to account owner: janusdominic0@gmail.com)
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                                <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                                <div><?= htmlspecialchars($error) ?></div>
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
                                <span>Verify Code</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
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

                            <a href="forgot_password.php?action=cancel_reset" class="small text-muted text-decoration-none">
                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Change Email
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ========================================== -->
                    <!-- STEP 1: ENTER EMAIL FORM                   -->
                    <!-- ========================================== -->
                    <div class="auth-card-header">
                        <div class="otp-icon-badge mx-auto mb-3">
                            <i class="bi bi-key" aria-hidden="true"></i>
                        </div>
                        <h2 class="auth-card-title">Reset Password</h2>
                        <p class="auth-card-subtitle">Enter your registered email to receive a 6-digit verification code</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 py-2 px-3 small" role="alert">
                            <i class="bi bi-exclamation-circle-fill text-danger flex-shrink-0" aria-hidden="true"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="on">
                        <?= csrf_input() ?>
                        <input type="hidden" name="auth_action" value="send_otp">

                        <div class="auth-field-group">
                            <label for="resetEmail" class="auth-field-label">Email Address</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <input type="email" id="resetEmail" name="email" class="auth-input" placeholder="you@example.com" value="<?= htmlspecialchars($email ?? '') ?>" required autofocus autocomplete="email" inputmode="email" autocapitalize="none" autocorrect="off">
                            </div>
                        </div>

                        <button type="submit" class="btn-auth-submit">
                            <span>Send Verification Code</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>

                        <div class="auth-footer-nav">
                            <div class="auth-switch-prompt">Remember your password? <a href="login" class="auth-nav-link">Sign In</a></div>
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
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Floating Toast Pop Up Container -->
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

    document.addEventListener('DOMContentLoaded', function() {
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
            otpInput.addEventListener('input', function() {
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
