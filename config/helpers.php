<?php
/**
 * Shared helpers used across pages and the auth API.
 * Requires config/database.php to be loaded first.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/** True when at least one user account exists in the database. */
function app_has_users(): bool
{
    static $has = null;

    if ($has === null) {
        $has = (bool) db()->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();
    }

    return $has;
}

/** Fetches a global setting from the database, returning $default if not set or empty. */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $cache[$key] = ($val !== false && $val !== '') ? (string) $val : null;
    }
    return $cache[$key] ?? $default;
}

/**
 * Truncates a string to at most $max bytes without splitting a UTF-8 sequence.
 * Used to fit user input into VARCHAR columns; mbstring is not assumed.
 */
function clip(string $value, int $max): string
{
    if (strlen($value) <= $max) {
        return $value;
    }
    $cut = substr($value, 0, $max);
    // Drop a trailing partial multi-byte sequence.
    while ($cut !== '' && (ord($cut[strlen($cut) - 1]) & 0xC0) === 0x80) {
        $cut = substr($cut, 0, -1);
    }
    if ($cut !== '' && (ord($cut[strlen($cut) - 1]) & 0x80) !== 0) {
        $cut = substr($cut, 0, -1); // lone lead byte
    }
    return $cut;
}

/** Sets a one-shot flash message shown on the next page render. */
function set_flash(string $kind, string $message): void
{
    $_SESSION['flash'] = ['kind' => $kind, 'message' => $message];
}

/** Returns and clears any pending flash message. */
function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Renders a one-shot flash message if one is pending. */
function render_flash(): void
{
    $flash = take_flash();
    if ($flash === null) {
        return;
    }
    $kind = in_array($flash['kind'], ['error', 'success', 'info'], true) ? $flash['kind'] : 'info';
    echo '<div class="alert alert-' . $kind . '" role="status">'
        . htmlspecialchars((string) $flash['message'])
        . '</div>';
}
