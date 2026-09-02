<?php
/**
 * Protects a page: redirects to login.php when no teacher session exists.
 * Pages that include this must be accessed over the same site as login.
 *
 * Self-contained: loads the DB config + shared helpers it relies on.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/** Convenience accessor for the logged-in teacher's name. */
function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Teacher';
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
