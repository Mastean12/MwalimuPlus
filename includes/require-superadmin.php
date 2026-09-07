<?php
/**
 * Protects a superadmin-only page: requires a valid teacher session (via
 * auth-check.php) AND re-checks the role against the database, since a
 * revoked superadmin's existing session must lose access immediately
 * rather than waiting for their session cache to catch up.
 *
 * JSON API endpoints should `define('SUPERADMIN_GUARD_JSON', true);` before
 * requiring this file, so a denial responds with JSON 403 instead of a
 * page redirect.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth-check.php';

$stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([(int) $_SESSION['user_id']]);
$role = $stmt->fetchColumn();

// Keep the session cache in sync with the authoritative DB value either way.
$_SESSION['user_role'] = $role !== false ? $role : 'teacher';

if ($role !== 'superadmin') {
    if (defined('SUPERADMIN_GUARD_JSON')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'That action is limited to super admins.']);
        exit;
    }

    set_flash('error', 'That page is limited to super admins.');
    header('Location: dashboard.php');
    exit;
}
