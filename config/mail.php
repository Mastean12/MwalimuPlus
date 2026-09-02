<?php
/**
 * Outbound mail config + the password reset email.
 *
 * mail() is used by default; override the from address/app name via
 * environment variables. Swap in SMTP later by replacing send_mail().
 */

declare(strict_types=1);

/** Name shown as the sender of reset emails. */
function mailer_app_name(): string
{
    return getenv('MWALIMU_MAIL_FROM_NAME') ?: 'MwalimuPlus';
}

/** Address reset emails are sent from. */
function mailer_from_address(): string
{
    return getenv('MWALIMU_MAIL_FROM') ?: 'no-reply@mwalimuplus.ai';
}

/**
 * Sends one plain-text email via PHP mail(). Returns true when PHP accepted
 * the message for delivery.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $headers = 'From: ' . mailer_app_name() . ' <' . mailer_from_address() . ">\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\n"
        . "MIME-Version: 1.0\r\n";

    return @mail($to, $subject, $body, $headers);
}

/** Builds the reset email body. $resetUrl is the single-use link. */
function password_reset_email_body(string $resetUrl): string
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
