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

enforce_not_in_maintenance();

/** Convenience accessor for the logged-in teacher's name. */
function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Teacher';
}

/** Role of the logged-in account, cached in the session since login.php. */
function current_user_role(): string
{
    return $_SESSION['user_role'] ?? 'teacher';
}

/** True when the logged-in account is a superadmin (session-cached; fast, non-authoritative). */
function is_superadmin(): bool
{
    return current_user_role() === 'superadmin';
}
