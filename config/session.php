<?php
/**
 * Centralized session bootstrap.
 *
 * Every entry point (pages and API files) calls secure_session_start()
 * instead of session_start(), so cookie flags and strict mode are applied
 * consistently across the app.
 */

declare(strict_types=1);

const SESSION_NAME = 'mwalimuplus_session';

/** Starts a hardened session. Safe to call more than once per request. */
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // The cookie is Secure only over HTTPS; localhost/HTTP dev still works.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();
}

/** Returns the params used for the session cookie (for clearing it on logout). */
function session_cookie_params(): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';

    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}
