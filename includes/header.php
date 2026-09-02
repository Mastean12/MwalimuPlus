<?php
/** Shared <head> opener + page header. Expects $pageTitle to be set before include. */

declare(strict_types=1);
$pageTitle = $pageTitle ?? 'MwalimuPlus';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · MwalimuPlus</title>
    <link rel="manifest" href="offline/manifest.json">
    <link rel="stylesheet" href="assets/css/app.css">
    <meta name="theme-color" content="#1f6f43">
</head>
<body>
<?php if (!empty($showHeader)): ?>
<header class="site-header">
    <a class="brand" href="dashboard.php">Mwalimu<span>Plus</span></a>
    <nav class="site-nav">
        <a href="dashboard.php"<?= $activeNav === 'dashboard' ? ' class="active"' : '' ?>>Dashboard</a>
        <span class="nav-user"><?= htmlspecialchars(current_user_name()) ?></span>
        <a class="nav-logout" href="logout.php">Log out</a>
    </nav>
</header>
<?php endif; ?>
<main class="container">
<?php render_flash(); ?>
