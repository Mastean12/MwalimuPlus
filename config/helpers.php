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
