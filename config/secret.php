<?php
/**
 * Encrypts values (AI provider API keys) before they are stored in the
 * `settings` table, so a leaked database dump alone doesn't expose them.
 *
 * The master key never lives in the database — that would defeat the
 * point, since anyone who can read the encrypted column could also read
 * the key sitting next to it. It comes from an APP_ENCRYPTION_KEY
 * environment variable if set, otherwise from storage/app.key, a
 * git-ignored file generated once on first use.
 */

declare(strict_types=1);

/** Returns the raw 32-byte AES-256 master key, generating and persisting one on first use. */
function app_encryption_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }

    $fromEnv = getenv('APP_ENCRYPTION_KEY');
    if (is_string($fromEnv) && $fromEnv !== '') {
        $decoded = base64_decode($fromEnv, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $key = $decoded;
        }
    }

    $keyFile = __DIR__ . '/../storage/app.key';
    if (is_file($keyFile)) {
        $decoded = base64_decode(trim((string) file_get_contents($keyFile)), true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return $key = $decoded;
        }
    }

    $generated = random_bytes(32);
    $dir = dirname($keyFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    file_put_contents($keyFile, base64_encode($generated));
    @chmod($keyFile, 0600);

    return $key = $generated;
}

/** Encrypts $plaintext (e.g. an API key) for storage in the settings table. */
function encrypt_secret(string $plaintext): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', app_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Could not encrypt value.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

/** Decrypts a value produced by encrypt_secret(); null if it's missing, malformed, or tampered with. */
function decrypt_secret(string $payload): ?string
{
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', app_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? null : $plaintext;
}
