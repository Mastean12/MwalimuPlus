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
 *   $publicHeader bool    render a bare topbar (brand + Log in) with no sidebar,
 *                         for pages reachable without a session; ignored if $showHeader is set
 *   $adminShell   bool    render the superadmin panel's own sidebar/nav (see
 *                         $adminNavGroups below) instead of the teacher nav —
 *                         superadmin is a fully separate account, never a
 *                         promoted teacher, so it gets its own shell entirely.
 */

declare(strict_types=1);
$pageTitle    = $pageTitle    ?? 'MwalimuPlus';
$activeNav    = $activeNav    ?? '';
$breadcrumbs  = $breadcrumbs  ?? [];
$pageHeading  = $pageHeading  ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$pageIcon     = $pageIcon     ?? '📋';
$pageActions  = $pageActions  ?? '';
$adminShell   = $adminShell   ?? false;
$homeHref     = $adminShell ? 'superadmin.php' : 'dashboard.php';

$navItems = [
    'dashboard' => ['label' => 'Dashboard',      'href' => 'dashboard.php',          'icon' => '🏠'],
    'subjects'  => ['label' => 'Subjects',        'href' => 'subjects.php',           'icon' => '📚'],
    'lessons'   => ['label' => 'Lessons',         'href' => 'lessons.php',            'icon' => '📝'],
    'schemes'   => ['label' => 'Schemes of work', 'href' => 'schemes.php',            'icon' => '🗓️'],
];

// The superadmin panel's nav mirrors its own control tree — grouped, not
// flat like the teacher sidebar — since it is a separate account and shell.
$adminNavGroups = [
    ['label' => 'Overview',              'items' => ['admin-home'       => ['label' => 'Dashboard',        'href' => 'superadmin.php',              'icon' => '🏠']]],
    ['label' => 'Manage Teachers',       'items' => ['admin-teachers'   => ['label' => 'Teachers',          'href' => 'superadmin-users.php',        'icon' => '👥']]],
    ['label' => 'Curriculum Management', 'items' => ['admin-curriculum' => ['label' => 'Curriculum',        'href' => 'superadmin-curriculum.php',   'icon' => '📚']]],
    ['label' => 'AI Configuration',      'items' => ['admin-ai'         => ['label' => 'AI & Prompts',      'href' => 'superadmin-ai.php',           'icon' => '🤖']]],
    ['label' => 'Lessons & Content',     'items' => ['admin-content'    => ['label' => 'Content',           'href' => 'superadmin-content.php',      'icon' => '📝']]],
    ['label' => 'Analytics',             'items' => ['admin-analytics'  => ['label' => 'Analytics',         'href' => 'superadmin-analytics.php',    'icon' => '📊']]],
    ['label' => 'Security',              'items' => ['admin-security'   => ['label' => 'Security',          'href' => 'superadmin-security.php',     'icon' => '🔒']]],
    ['label' => 'Platform Settings',     'items' => ['admin-settings'   => ['label' => 'Settings',          'href' => 'settings.php',                'icon' => '⚙️']]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · MwalimuPlus</title>
    <link rel="manifest" href="offline/manifest.json">
<?php $favicon = get_setting('favicon_url', 'assets/icons/icon-192.png'); ?>
    <link rel="icon" href="<?= htmlspecialchars($favicon) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($favicon) ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= time() ?>">
    <meta name="theme-color" content="#1f6f43">
    <style>
        :root {
            <?php if ($theme_topbar_bg = get_setting('theme_topbar_bg')): ?>--theme-topbar-bg: <?= htmlspecialchars($theme_topbar_bg) ?>;<?php endif; ?>
            <?php if ($theme_topbar_text = get_setting('theme_topbar_text')): ?>--theme-topbar-text: <?= htmlspecialchars($theme_topbar_text) ?>;<?php endif; ?>
            <?php if ($theme_sidebar_bg = get_setting('theme_sidebar_bg')): ?>--theme-sidebar-bg: <?= htmlspecialchars($theme_sidebar_bg) ?>;<?php endif; ?>
            <?php if ($theme_sidebar_text = get_setting('theme_sidebar_text')): ?>--theme-sidebar-text: <?= htmlspecialchars($theme_sidebar_text) ?>;<?php endif; ?>
            <?php if ($theme_page_bg = get_setting('theme_page_bg')): ?>--theme-page-bg: <?= htmlspecialchars($theme_page_bg) ?>;<?php endif; ?>
        }
    </style>
</head>
<body class="app-body">
<?php if (!empty($showHeader)): ?>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= htmlspecialchars($homeHref) ?>" style="align-items: center; display: flex;">
            <?php if ($logo = get_setting('logo_url')): ?>
                <div class="sidebar-logo-container">
                    <img src="<?= htmlspecialchars($logo) ?>" alt="MwalimuPlus Logo" style="max-height: 26px; width: auto; object-fit: contain;">
                </div>
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true">M+</span>
                <span class="brand-name">Mwalimu<span>Plus</span></span>
            <?php endif; ?>
        </a>
        <nav class="sidebar-nav" aria-label="Main">
            <?php if ($adminShell): ?>
                <?php foreach ($adminNavGroups as $group): ?>
                    <p class="sidebar-label"><?= htmlspecialchars($group['label']) ?></p>
                    <?php foreach ($group['items'] as $key => $item): ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>"<?= $activeNav === $key ? ' class="active" aria-current="page"' : '' ?>>
                            <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="sidebar-label">Menu</p>
                <?php foreach ($navItems as $key => $item): ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"<?= $activeNav === $key ? ' class="active" aria-current="page"' : '' ?>>
                        <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <p class="sidebar-note"><?= $adminShell
            ? 'Super Admin panel — full control over every teacher account, the curriculum, and platform settings.'
            : 'Mwalimu AI is a prep assistant. It can be wrong — you make the final call in your classroom.' ?></p>
    </aside>
    <div class="sidebar-backdrop" hidden data-sidebar-backdrop></div>

    <div class="app-main">
        <header class="topbar">
            <button class="topbar-toggle" type="button" aria-label="Toggle menu" data-sidebar-toggle>☰</button>
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <a href="<?= htmlspecialchars($homeHref) ?>" title="Home">Home</a>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <span class="crumb-sep">/</span>
                    <?php if (!empty($crumb['href'])): ?>
                        <a href="<?= htmlspecialchars($crumb['href']) ?>" title="<?= htmlspecialchars($crumb['label']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                    <?php else: ?>
                        <span title="<?= htmlspecialchars($crumb['label']) ?>"><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="topbar-right">
                <?php
                require_once __DIR__ . '/../config/ai.php';
                $aiActiveProvider = ai_default_provider();
                $aiActiveModel = ai_selected_model('ai_default_model', $aiActiveProvider);
                $aiChipTitle = 'AI model in use — set in Settings → AI';
                $aiFallbackProvider = ai_fallback_provider();
                if ($aiFallbackProvider !== '') {
                    $aiFallbackMeta = ai_provider_meta($aiFallbackProvider);
                    $aiChipTitle .= ' · Fallback: '
                        . ($aiFallbackMeta['label'] ?? $aiFallbackProvider)
                        . ' · ' . ai_selected_model('ai_fallback_model', $aiFallbackProvider);
                }
                ?>
                <?php if (ai_provider_configured($aiActiveProvider) && $aiActiveModel !== ''): ?>
                    <?php $aiActiveMeta = ai_provider_meta($aiActiveProvider); ?>
                    <span class="topbar-ai-chip" title="<?= htmlspecialchars($aiChipTitle) ?>"><span class="chip-dot" aria-hidden="true"></span><?= htmlspecialchars(($aiActiveMeta['label'] ?? $aiActiveProvider) . ' · ' . $aiActiveModel) ?></span>
                <?php endif; ?>
                <time class="topbar-clock" id="topbar-clock" datetime=""></time>
                <div class="topbar-user">
                    <a class="topbar-user-link" href="profile.php" data-open-profile>
                        <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr(current_user_name(), 0, 1))) ?></span>
                        <span class="user-name"><?= htmlspecialchars(current_user_name()) ?></span>
                    </a>
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
<?php elseif (!empty($publicHeader)): ?>
<header class="topbar topbar-public">
    <a class="brand" href="browse.php">
        <span class="brand-mark" aria-hidden="true">M+</span>
        <span class="brand-name">Mwalimu<span>Plus</span></span>
    </a>
    <div class="topbar-right">
        <a class="btn btn-primary" href="login.php">Log in</a>
    </div>
</header>
<main class="container">
    <?php render_flash(); ?>
<?php else: ?>
<main class="container">
    <?php render_flash(); ?>
<?php endif; ?>
