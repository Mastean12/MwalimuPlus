<?php
/** Landing page: routes to the right place based on app state.
 *  - No users yet  -> setup.php (create the first account)
 *  - Signed in     -> dashboard.php
 *  - Otherwise     -> login.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: ' . (app_has_users() ? 'login.php' : 'setup.php'));
exit;
