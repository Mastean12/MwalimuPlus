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
secure_session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/** Convenience accessor for the logged-in teacher's name. */
function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Teacher';
}
