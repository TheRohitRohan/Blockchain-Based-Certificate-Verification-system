<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {

    private array $mailConfig;

    public function __construct() {
        $config = require __DIR__ . '/../config.php';
        $this->mailConfig = $config['mail'];
    }

    /**
     * Create and configure a PHPMailer instance with Gmail SMTP settings.
     */
    private function createMailer(): PHPMailer {
        $mail = new PHPMailer(true); // enable exceptions

        $mail->isSMTP();
        $mail->Host       = $this->mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->mailConfig['username'];
        $mail->Password   = $this->mailConfig['password'];
        $mail->SMTPSecure = $this->mailConfig['encryption'] === 'tls'
                            ? PHPMailer::ENCRYPTION_STARTTLS
                            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $this->mailConfig['port'];

        $mail->setFrom(
            $this->mailConfig['from_address'],
            $this->mailConfig['from_name']
        );

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

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

        try {
            $mail = $this->createMailer();
            $mail->addAddress($email);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = $this->buildResetEmailBody($resetUrl, $expiryFormatted);
            $mail->AltBody = "You requested a password reset. Visit: {$resetUrl} — This link expires at {$expiryFormatted}.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error (password reset to {$email}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a generic notification email.
     *
     * @param string $to      Recipient email
     * @param string $subject Email subject
     * @param string $body    HTML body
     * @param string $altBody Plain-text fallback (optional)
     * @return bool
     */
    public function sendEmail(string $to, string $subject, string $body, string $altBody = ''): bool {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error (to {$to}): " . $e->getMessage());
            return false;
        }
    }

    private function buildResetEmailBody(string $resetUrl, string $expiryFormatted): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Password Reset</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">
  <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
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
