<?php
// includes/mailer.php
// Centralized Email Dispatch Service for FreshCart Market using Resend API (HTTPS REST)

require_once __DIR__ . '/../config/db.php';

/**
 * Dispatches a 6-digit OTP verification code via Resend HTTPS REST API.
 *
 * @param string $toEmail Recipient's email address
 * @param string $recipientName Recipient's full name
 * @param string $otpCode 6-digit numeric OTP code
 * @return array ['success' => bool, 'message' => string, 'dev_fallback' => bool, 'otp' => string]
 */
function send_otp_email($toEmail, $recipientName, $otpCode) {
    $apiKey = get_config_var('RESEND_API_KEY');
    $fromAddress = get_config_var('MAIL_FROM', 'onboarding@resend.dev');
    $fromName = 'FreshCart Market';
    $fromHeader = "{$fromName} <{$fromAddress}>";
    $subject = "Your FreshCart Verification Code: {$otpCode}";
    $htmlContent = get_otp_email_template($recipientName, $otpCode);

    // If no API key is set, immediately provide dev fallback
    if (empty($apiKey) || str_starts_with($apiKey, 're_your_api_key')) {
        error_log("[FreshCart Dev Mailer] Resend API key not configured. OTP for {$toEmail}: {$otpCode}");
        return [
            'success' => true,
            'dev_fallback' => true,
            'message' => 'Dev Mode: Code generated (API key not configured).',
            'otp' => $otpCode
        ];
    }

    $payload = [
        'from' => $fromHeader,
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => $htmlContent,
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: FreshCart-App/1.0',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[FreshCart Mailer] cURL Error: " . $curlErr);
        // Fall back gracefully so user is not blocked
        return [
            'success' => true,
            'dev_fallback' => true,
            'message' => 'Email network timeout. Dev code available.',
            'otp' => $otpCode
        ];
    }

    $responseData = json_decode($rawResponse, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'dev_fallback' => false,
            'id' => $responseData['id'] ?? null,
            'message' => 'Verification code sent to your email.'
        ];
    }

    // Handle Resend Sandbox restriction (when sending to an email other than account owner)
    $errorMessage = $responseData['message'] ?? 'Failed to deliver email.';
    error_log("[FreshCart Mailer] Resend API HTTP {$httpCode}: {$errorMessage}");

    return [
        'success' => true,
        'dev_fallback' => true,
        'message' => $errorMessage,
        'otp' => $otpCode
    ];
}

/**
 * Generates responsive, high-contrast HTML template for the OTP email.
 */
function get_otp_email_template($name, $code) {
    $safeName = htmlspecialchars($name ?: 'Valued Customer', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreshCart Verification Code</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f8fafc; padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
        .header { background: #166534; padding: 32px 32px 24px 32px; text-align: center; }
        .brand-logo { font-size: 26px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; text-decoration: none; }
        .brand-dot { color: #86efac; }
        .content { padding: 32px; color: #1e293b; }
        .title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0; }
        .text { font-size: 15px; line-height: 1.6; color: #475569; margin: 0 0 24px 0; }
        .code-box { background: #f0fdf4; border: 2px dashed #22c55e; border-radius: 12px; padding: 20px 16px; text-align: center; margin: 24px 0; }
        .otp-digits { font-family: 'SF Mono', Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 38px; font-weight: 800; color: #15803d; letter-spacing: 12px; margin-left: 12px; display: inline-block; }
        .expiry-note { font-size: 13px; color: #64748b; margin-top: 8px; font-weight: 500; }
        .alert-box { background: #fefce8; border-left: 4px solid #eab308; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #713f12; margin: 24px 0; }
        .footer { padding: 24px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="brand-logo">FreshCart<span class="brand-dot">.</span></div>
                <div style="font-size: 13px; color: #bbf7d0; margin-top: 4px; font-weight: 500;">Farm-to-Door Organic Market</div>
            </div>
            <div class="content">
                <h1 class="title">Verify Your Email Address</h1>
                <p class="text">Hi <strong>{$safeName}</strong>, thank you for registering with FreshCart. Please use the following 6-digit one-time password (OTP) to complete your account setup:</p>
                
                <div class="code-box">
                    <div class="otp-digits">{$safeCode}</div>
                    <div class="expiry-note">Valid for 10 minutes &bull; Single use only</div>
                </div>

                <div class="alert-box">
                    <strong>Security Notice:</strong> Never share this code with anyone. FreshCart staff will never ask for your verification code.
                </div>

                <p class="text" style="font-size: 13px; color: #64748b; margin-bottom: 0;">
                    If you did not request this registration, you can safely disregard this email.
                </p>
            </div>
            <div class="footer">
                &copy; {$year} FreshCart Market Inc. All rights reserved.<br>
                Radical transparency from soil to doorstep.
            </div>
        </div>
    </div>
</body>
</html>
HTML;
}
