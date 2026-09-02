<?php
/**
 * Protects a page: redirects to login.php when no teacher session exists.
 * Pages that include this must be accessed over the same site as login.
 */

declare(strict_types=1);

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
