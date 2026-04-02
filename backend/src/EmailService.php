<?php

namespace App;

class EmailService {

    /**
     * Send a password reset email to the user.
     *
     * @param string $email     Recipient email address
     * @param string $resetToken The raw reset token
     * @param int    $expiryTime Unix timestamp when the token expires
     * @return bool
     */
    public function sendPasswordResetEmail(string $email, string $resetToken, int $expiryTime): bool {
        $config = require __DIR__ . '/../config.php';
        $frontendUrl = $config['app']['frontend_url'] ?? '';
        $appBaseUrl  = $config['app']['base_url'] ?? '';

        $resetUrl = ($frontendUrl ?: $appBaseUrl) . '/reset-password?token=' . urlencode($resetToken);
        $expiryFormatted = date('Y-m-d H:i:s', $expiryTime) . ' UTC';

        $subject = 'Password Reset Request';
        $body = $this->buildResetEmailBody($resetUrl, $expiryFormatted);

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: no-reply@certificate-system.com\r\n";
        $headers .= "Reply-To: no-reply@certificate-system.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        return mail($email, $subject, $body, $headers);
    }

    private function buildResetEmailBody(string $resetUrl, string $expiryFormatted): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Password Reset</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">
  <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:8px;padding:32px;">
    <h2 style="color:#333;">Password Reset Request</h2>
    <p>You requested a password reset for your account. Click the button below to set a new password.</p>
    <p style="text-align:center;">
      <a href="{$resetUrl}"
         style="display:inline-block;padding:12px 24px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:6px;font-size:16px;">
        Reset Password
      </a>
    </p>
    <p>Or copy and paste this link into your browser:</p>
    <p style="word-break:break-all;color:#4F46E5;">{$resetUrl}</p>
    <p style="color:#888;font-size:13px;">This link will expire at <strong>{$expiryFormatted}</strong> (15 minutes from request).</p>
    <p style="color:#888;font-size:13px;">If you did not request a password reset, you can safely ignore this email.</p>
  </div>
</body>
</html>
HTML;
    }
}
