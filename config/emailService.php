<?php
/**
 * Email Service - Resend / SMTP integration
 *
 * Provides a unified email sending interface. Preference order:
 *   1. Resend HTTP API (when RESEND_API_KEY is set)
 *   2. PHPMailer SMTP (when SMTP_HOST/SMTP_USER are set)
 *   3. PHP mail() fallback
 *
 * Configuration: Set constants below or use environment variables.
 *   RESEND_API_KEY, RESEND_FROM_EMAIL, RESEND_FROM_NAME
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_NAME, SMTP_FROM_EMAIL
 *
 * Usage:
 *   require_once __DIR__ . '/emailService.php';
 *   $result = sendLmsEmail('user@example.com', 'Subject', 'Body text', 'User Name');
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/securityLogger.php';

/* ── Resend Configuration ─────────────────────────────────────── */
/* Local development can keep the key in config/resend.local.php,
   which is gitignored. Real environment variables take precedence. */
$resendLocalConfig = [];
$resendLocalFile = __DIR__ . '/resend.local.php';
if (is_file($resendLocalFile)) {
    $loadedResendConfig = require $resendLocalFile;
    if (is_array($loadedResendConfig)) {
        $resendLocalConfig = $loadedResendConfig;
    }
}

if (!defined('RESEND_API_KEY')) define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: (string) ($resendLocalConfig['api_key'] ?? ''));
if (!defined('RESEND_FROM_EMAIL')) define('RESEND_FROM_EMAIL', getenv('RESEND_FROM_EMAIL') ?: (string) ($resendLocalConfig['from_email'] ?? 'verify@csdp-lms.tech'));
if (!defined('RESEND_FROM_NAME')) define('RESEND_FROM_NAME', getenv('RESEND_FROM_NAME') ?: (string) ($resendLocalConfig['from_name'] ?? 'CSDP LMS'));
if (!defined('RESEND_API_URL')) define('RESEND_API_URL', getenv('RESEND_API_URL') ?: (string) ($resendLocalConfig['api_url'] ?? 'https://api.resend.com/emails'));

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

/** Check if Resend is configured. */
function isResendConfigured(): bool { return RESEND_API_KEY !== ''; }

/**
 * Send an email using Resend, PHPMailer (SMTP) or fall back to mail().
 */
function sendLmsEmail(string $to, string $subject, string $body, string $toName = '', ?string $htmlBody = null): array
{
    $maskedTo = substr($to, 0, 3) . '***';
    if (isResendConfigured()) return sendViaResend($to, $toName, $subject, $body, $htmlBody, $maskedTo);
    if (isSmtpConfigured()) return sendViaSmtp($to, $toName, $subject, $body, $htmlBody, $maskedTo);
    return sendViaMail($to, $toName, $subject, $body, $maskedTo);
}

/**
 * Send via the Resend HTTP API (no SDK required).
 * Docs: https://resend.com/docs/api-reference/emails/send-email
 */
function sendViaResend(string $to, string $toName, string $subject, string $body, ?string $htmlBody, string $maskedTo): array
{
    $payload = [
        'from'    => RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>',
        'to'      => [$toName !== '' ? $toName . ' <' . $to . '>' : $to],
        'subject' => $subject,
        'text'    => $body,
    ];
    if ($htmlBody !== null && $htmlBody !== '') {
        $payload['html'] = $htmlBody;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        SecurityLogger::log('SYSTEM', "Resend payload encoding failed for $maskedTo", [], 'WARNING');
        return ['success' => false, 'method' => 'resend', 'error' => 'Could not encode email payload'];
    }

    if (!function_exists('curl_init')) {
        SecurityLogger::log('SYSTEM', "Resend unavailable (cURL missing) for $maskedTo", [], 'WARNING');
        return ['success' => false, 'method' => 'resend', 'error' => 'cURL extension is not available'];
    }

    $ch = curl_init(RESEND_API_URL);
    if ($ch === false) {
        SecurityLogger::log('SYSTEM', "Resend could not initialize for $maskedTo", [], 'WARNING');
        return ['success' => false, 'method' => 'resend', 'error' => 'Could not initialize the HTTP client'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response  = curl_exec($ch);
    $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        SecurityLogger::log('SYSTEM', "Resend transport failed for $maskedTo: $curlError", [], 'WARNING');
        return ['success' => false, 'method' => 'resend', 'error' => $curlError ?: 'Resend request failed'];
    }

    $decoded = json_decode($response, true);
    if ($status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['id'])) {
        SecurityLogger::log('SYSTEM', "Email sent via Resend to $maskedTo: $subject", [
            'method' => 'resend',
            'id'     => (string) $decoded['id'],
        ]);
        return ['success' => true, 'method' => 'resend', 'error' => null];
    }

    $error = is_array($decoded) && !empty($decoded['message'])
        ? (string) $decoded['message']
        : 'HTTP ' . $status;
    SecurityLogger::log('SYSTEM', "Resend email failed to $maskedTo: $error", ['status' => $status], 'WARNING');
    return ['success' => false, 'method' => 'resend', 'error' => $error];
}

/** Send via PHPMailer SMTP. */
function sendViaSmtp(string $to, string $toName, string $subject, string $body, ?string $htmlBody, string $maskedTo): array
{
    if (!class_exists(PHPMailer::class)) {
        SecurityLogger::log('SYSTEM', "SMTP unavailable (PHPMailer missing) for $maskedTo", [], 'WARNING');
        return ['success' => false, 'method' => 'smtp', 'error' => 'PHPMailer is not installed'];
    }

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