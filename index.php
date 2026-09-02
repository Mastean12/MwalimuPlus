<?php
/** Landing page: sends logged-in teachers to the dashboard, everyone else to login. */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Location: ' . (!empty($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
exit;
