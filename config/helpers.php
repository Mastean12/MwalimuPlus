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

/** True when a superadmin has switched Settings > System maintenance on. */
function maintenance_mode_enabled(): bool
{
    return get_setting('maintenance_mode', '0') === '1';
}

/**
 * Blocks the current request with a 503 maintenance page unless the
 * logged-in user is a superadmin. Call after confirming $_SESSION['user_id']
 * is set — a superadmin's own session keeps working normally so they can
 * turn maintenance mode back off.
 */
function enforce_not_in_maintenance(): void
{
    if (!maintenance_mode_enabled() || ($_SESSION['user_role'] ?? '') === 'superadmin') {
        return;
    }
    render_maintenance_page();
    exit;
}

/** Renders the branded "under maintenance" page and stops the script. */
function render_maintenance_page(): void
{
    http_response_code(503);
    header('Retry-After: 3600');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under maintenance · MwalimuPlus</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <meta name="theme-color" content="#1f6f43">
</head>
<body class="auth-body">
<div class="auth-shell auth-shell-single">
    <main class="auth-form-panel">
        <a class="brand auth-brand" href="login.php">
            <span class="brand-mark" aria-hidden="true">M+</span>
            <span class="brand-name">Mwalimu<span>Plus</span></span>
        </a>
        <h1>Down for maintenance</h1>
        <p class="auth-sub">MwalimuPlus is temporarily unavailable. Please check back shortly.</p>
    </main>
</div>
</body>
</html>
    <?php
}

/** Idle session timeout in minutes, set in Settings > System. 0 = never (default). */
function session_timeout_minutes(): int
{
    return max(0, (int) get_setting('session_timeout_minutes', '0'));
}

/**
 * Logs an idle session out once it exceeds the configured timeout, then
 * stamps the current request's time so an active session never expires
 * mid-use. Call only after confirming $_SESSION['user_id'] is set. A
 * timeout of 0 (the default) disables this entirely.
 */
function enforce_session_timeout(): void
{
    $minutes = session_timeout_minutes();
    if ($minutes > 0 && !empty($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $minutes * 60) {
        audit_log((int) $_SESSION['user_id'], 'session_timed_out');
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('info', 'You were signed out after being inactive for a while. Please sign in again.');
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Records a privileged action for the superadmin Security -> Audit logs
 * panel. $userId is the actor (null for an anonymous/failed login attempt).
 */
function audit_log(?int $userId, string $action, string $targetType = '', ?int $targetId = null, array $meta = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, target_type, target_id, meta) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $targetType, $targetId, $meta !== [] ? json_encode($meta) : null]);
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
