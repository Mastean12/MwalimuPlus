<?php
/**
 * Outbound mail config + the password reset email.
 *
 * Uses PHPMailer over SMTP (Mailtrap sandbox by default). SMTP settings can
 * be overridden with MAIL_* environment variables when moving to production.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Name shown as the sender of reset emails. */
function mailer_app_name(): string
{
    return getenv('MAIL_FROM_NAME') ?: 'MwalimuPlus';
}

/** Address reset emails are sent from. */
function mailer_from_address(): string
{
    return getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@mwalimuplus.ai';
}

/**
 * Sends one email over SMTP via PHPMailer with an HTML body and a plain-text
 * alternative. Returns true when the message was accepted for delivery.
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $phpmailer = new PHPMailer(true);

    try {
        $phpmailer->isSMTP();
        $phpmailer->Host = getenv('MAIL_HOST') ?: 'sandbox.smtp.mailtrap.io';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = (int) (getenv('MAIL_PORT') ?: 2525);
        $phpmailer->Username = getenv('MAIL_USERNAME') ?: '7b9c66cc829b3d';
        $phpmailer->Password = getenv('MAIL_PASSWORD') ?: 'bc81fc942dfa25';

        $phpmailer->setFrom(mailer_from_address(), mailer_app_name());
        $phpmailer->addAddress($to);

        $phpmailer->CharSet = PHPMailer::CHARSET_UTF8;
        $phpmailer->isHTML(true);
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $htmlBody;
        $phpmailer->AltBody = $textBody;

        return $phpmailer->send();
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $phpmailer->ErrorInfo);
        return false;
    }
}

/**
 * Builds the branded HTML reset email. Inline styles only (no <style> block)
 * so it renders consistently across Gmail, Outlook, and mobile clients.
 */
function password_reset_email_html(string $resetUrl): string
{
    $app = htmlspecialchars(mailer_app_name());
    $safeUrl = htmlspecialchars($resetUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background-color:#f0f4f1;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4f1;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(18,69,44,0.08);">

          <!-- Header -->
          <tr>
            <td style="background-color:#12452c;padding:28px 40px;">
              <span style="font-family:Segoe UI,Arial,sans-serif;font-size:22px;font-weight:700;color:#ffffff;">Mwalimu<span style="color:#7fd6a4;">Plus</span></span>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 40px 20px;">
              <h1 style="margin:0 0 8px;font-family:Segoe UI,Arial,sans-serif;font-size:22px;font-weight:700;color:#1c2b23;">Reset your password</h1>
              <p style="margin:0 0 20px;font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5c6b62;">A password reset was requested for your {$app} account. Use the button below to choose a new password.</p>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                <tr>
                  <td style="border-radius:8px;background-color:#1f6f43;">
                    <a href="{$safeUrl}" style="display:inline-block;padding:12px 28px;font-family:Segoe UI,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">Reset password</a>
                  </td>
                </tr>
              </table>

              <p style="margin:8px 0 0;font-family:Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.5;color:#8a948e;">The link works once and expires within an hour. If the button does not open, copy and paste this address into your browser:</p>
              <p style="margin:6px 0 0;font-family:Consolas,monospace;font-size:12px;line-height:1.5;color:#1c2b23;word-break:break-all;">{$safeUrl}</p>
            </td>
          </tr>

          <!-- Note -->
          <tr>
            <td style="padding:4px 40px 28px;">
              <p style="margin:0;font-family:Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.5;color:#8a948e;">If you did not ask to reset your password, you can ignore this email. Your current password will keep working.</p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#eef7f1;padding:18px 40px;">
              <p style="margin:0;font-family:Segoe UI,Arial,sans-serif;font-size:12px;color:#5c6b62;">{$app} · Lesson prep grounded in the KICD curriculum</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/** Builds the plain-text alternative for clients that block HTML. */
function password_reset_email_text(string $resetUrl): string
{
    $app = mailer_app_name();

    return <<<MAIL
Hello,

A password reset was requested for your {$app} account.

Open this link to choose a new password (it works once and expires within an hour):

{$resetUrl}

If you did not ask to reset your password, you can ignore this email. Your
current password will keep working.

{$app}
MAIL;
}
