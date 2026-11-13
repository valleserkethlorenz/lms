<?php
/**
 * Email Service - SMTP integration via PHPMailer
 * 
 * Provides a unified email sending interface with SMTP support.
 * Falls back to PHP mail() if SMTP is not configured.
 *
 * Configuration: Set constants below or use environment variables.
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME, SMTP_FROM_EMAIL
 *
 * Usage:
 *   require_once __DIR__ . '/emailService.php';
 *   $result = sendLmsEmail('user@example.com', 'Subject', 'Body text', 'User Name');
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/securityLogger.php';

/* ── SMTP Configuration ──────────────────────────────────────── */
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
if (!defined('SMTP_USER')) define('SMTP_USER', getenv('SMTP_USER') ?: '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@lms.local');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'League Management System');

/** Check if SMTP is configured. */
function isSmtpConfigured(): bool { return SMTP_HOST !== '' && SMTP_USER !== ''; }

/**
 * Send an email using PHPMailer (SMTP) or fall back to mail().
 */
function sendLmsEmail(string $to, string $subject, string $body, string $toName = '', ?string $htmlBody = null): array
{
    $maskedTo = substr($to, 0, 3) . '***';
    if (isSmtpConfigured()) return sendViaSmtp($to, $toName, $subject, $body, $htmlBody, $maskedTo);
    return sendViaMail($to, $toName, $subject, $body, $maskedTo);
}

/** Send via PHPMailer SMTP. */
function sendViaSmtp(string $to, string $toName, string $subject, string $body, ?string $htmlBody, string $maskedTo): array
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = 15;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->isHTML($htmlBody !== null);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody ?? $body;
        $mail->AltBody = $body;
        $mail->CharSet = 'UTF-8';
        $mail->addCustomHeader('X-Mailer', 'LMS/1.0');
        $mail->send();
        SecurityLogger::log('SYSTEM', "Email sent via SMTP to $maskedTo: $subject", ['method' => 'smtp', 'host' => SMTP_HOST]);
        return ['success' => true, 'method' => 'smtp', 'error' => null];
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
        SecurityLogger::log('SYSTEM', "SMTP email failed to $maskedTo: $errorMsg", ['subject' => $subject], 'WARNING');
        $fallback = sendViaMail($to, $toName, $subject, $body, $maskedTo);
        if ($fallback['success']) $fallback['error'] = "SMTP failed ($errorMsg), fell back to mail()";
        return $fallback;
    }
}

/** Send via PHP's native mail() function (fallback). */
function sendViaMail(string $to, string $toName, string $subject, string $body, string $maskedTo): array
{
    try {
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\nX-Mailer: LMS/1.0\r\n";
        $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $result = @mail($to, $subject, $body, $headers);
        if ($result) {
            SecurityLogger::log('SYSTEM', "Email sent via mail() to $maskedTo: $subject", ['method' => 'mail']);
            return ['success' => true, 'method' => 'mail', 'error' => null];
        }
        SecurityLogger::log('SYSTEM', "mail() failed for $maskedTo: $subject", [], 'WARNING');
        return ['success' => false, 'method' => 'mail', 'error' => 'mail() returned false'];
    } catch (\Throwable $e) {
        SecurityLogger::log('SYSTEM', "mail() exception for $maskedTo: " . $e->getMessage(), [], 'CRITICAL');
        return ['success' => false, 'method' => 'mail', 'error' => $e->getMessage()];
    }
}

/** Build an HTML email template wrapping plain content. */
function buildHtmlEmail(string $title, string $bodyText, string $actionUrl = '', string $buttonLabel = ''): string
{
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:40px 0;"><tr><td align="center">';
    $html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">';
    $html .= '<tr><td style="background:linear-gradient(135deg,#09173c,#2563eb);padding:24px 32px;color:#fff;"><h1 style="margin:0;font-size:20px;">' . htmlspecialchars($title) . '</h1></td></tr>';
    $html .= '<tr><td style="padding:32px;">' . nl2br(htmlspecialchars($bodyText));
    if ($actionUrl !== '') {
        $html .= '<div style="text-align:center;margin:28px 0;"><a href="' . htmlspecialchars($actionUrl) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 32px;border-radius:6px;font-weight:600;">' . htmlspecialchars($buttonLabel ?: 'Click Here') . '</a></div>';
    }
    $html .= '</td></tr>';
    $html .= '<tr><td style="padding:20px 32px;background:#f9fafb;text-align:center;font-size:12px;color:#6b7280;">&copy; ' . date('Y') . ' League Management System. All rights reserved.</td></tr>';
    $html .= '</table></td></tr></table></body></html>';
    return $html;
}