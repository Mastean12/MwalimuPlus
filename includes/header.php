<?php
/**
 * Shared app shell: <head>, left sidebar, top bar, and the page header block.
 *
 * Set before including (all optional except where noted):
 *   $pageTitle    string  browser tab title (also the default heading)
 *   $activeNav    string  key of the active sidebar item: dashboard|subjects|lessons
 *   $showHeader   bool    render the full shell (sidebar + top bar); pages set this true
 *   $breadcrumbs  array   [['label' => 'Subjects', 'href' => 'dashboard.php#subjects'], ['label' => 'Algebra']]
 *   $pageHeading  string  <h1> text for the page header block
 *   $pageSubtitle string  muted line under the heading
 *   $pageIcon     string  emoji shown in the header block tile
 *   $pageActions  string  raw HTML for the top-right actions (buttons/links)
 */

declare(strict_types=1);
$pageTitle    = $pageTitle    ?? 'MwalimuPlus';
$activeNav    = $activeNav    ?? '';
$breadcrumbs  = $breadcrumbs  ?? [];
$pageHeading  = $pageHeading  ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$pageIcon     = $pageIcon     ?? '📋';
$pageActions  = $pageActions  ?? '';

$navItems = [
    'dashboard' => ['label' => 'Dashboard',      'href' => 'dashboard.php',          'icon' => '🏠'],
    'subjects'  => ['label' => 'Subjects',        'href' => 'dashboard.php#subjects', 'icon' => '📚'],
    'lessons'   => ['label' => 'Recent lessons',  'href' => 'dashboard.php#recent',   'icon' => '📝'],
];
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
<body class="app-body">
<?php if (!empty($showHeader)): ?>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="dashboard.php">Mwalimu<span>Plus</span></a>
        <nav class="sidebar-nav" aria-label="Main">
            <p class="sidebar-label">Menu</p>
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"<?= $activeNav === $key ? ' class="active" aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <p class="sidebar-note">Mwalimu AI is a prep assistant. It can be wrong — you make the final call in your classroom.</p>
    </aside>
    <div class="sidebar-backdrop" hidden data-sidebar-backdrop></div>

    <div class="app-main">
        <header class="topbar">
            <button class="topbar-toggle" type="button" aria-label="Toggle menu" data-sidebar-toggle>☰</button>
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <a href="dashboard.php">Home</a>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <span class="crumb-sep">/</span>
                    <?php if (!empty($crumb['href'])): ?>
                        <a href="<?= htmlspecialchars($crumb['href']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                    <?php else: ?>
                        <span><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="topbar-right">
                <time class="topbar-clock" id="topbar-clock" datetime=""></time>
                <div class="topbar-user">
                    <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr(current_user_name(), 0, 1))) ?></span>
                    <span class="user-name"><?= htmlspecialchars(current_user_name()) ?></span>
                    <a class="nav-logout" href="logout.php">Log out</a>
                </div>
            </div>
        </header>

        <main class="content">
            <?php render_flash(); ?>
            <div class="page-head">
                <div class="page-head-main">
                    <span class="page-head-icon" aria-hidden="true"><?= $pageIcon ?></span>
                    <div>
                        <h1><?= htmlspecialchars($pageHeading) ?></h1>
                        <?php if ($pageSubtitle !== ''): ?>
                            <p class="page-head-sub"><?= htmlspecialchars($pageSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($pageActions !== ''): ?>
                    <div class="page-head-actions"><?= $pageActions ?></div>
                <?php endif; ?>
            </div>
<?php else: ?>
<main class="container">
    <?php render_flash(); ?>
<?php endif; ?>
