<?php
/** Superadmin dashboard: app-wide stats and quick links to every admin control. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();

$teacherCount    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$pendingCount    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'pending'")->fetchColumn();
$subjectCount    = (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
$topicCount      = (int) $pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn();
$lessonCount     = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$schemeCount     = (int) $pdo->query('SELECT COUNT(*) FROM schemes')->fetchColumn();
$needsVerify     = (int) $pdo->query("SELECT COUNT(*) FROM lessons WHERE status IN ('NEEDS_VERIFICATION','UNKNOWN')")->fetchColumn();

$recentUsers = $pdo->query(
    "SELECT id, name, email, status, created_at FROM users WHERE role = 'teacher' ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$recentLessons = $pdo->query(
    'SELECT l.id, l.title, l.status, l.created_at, u.name AS teacher_name
       FROM lessons l
       LEFT JOIN users u ON u.id = l.user_id
      ORDER BY l.created_at DESC
      LIMIT 8'
)->fetchAll();

$recentSchemes = $pdo->query(
    'SELECT s.id, s.title, s.term, s.created_at, u.name AS teacher_name
       FROM schemes s
       LEFT JOIN users u ON u.id = s.user_id
      ORDER BY s.created_at DESC
      LIMIT 8'
)->fetchAll();

$pageTitle    = 'Super Admin';
$activeNav    = 'admin-home';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '🛡️';
$pageHeading  = 'Super Admin';
$pageSubtitle = 'App-wide control: every teacher account, the curriculum catalogue, and platform settings.';
$pageActions  = '<a href="superadmin-users.php" class="btn btn-primary">Manage teachers</a>';
require __DIR__ . '/includes/header.php';
?>
<section class="premium-metric-grid">
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-green"><span aria-hidden="true">👥</span></div>
        <div class="metric-content">
            <span class="metric-label">Teachers</span>
            <span class="metric-value"><?= $teacherCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-blue"><span aria-hidden="true">⏳</span></div>
        <div class="metric-content">
            <span class="metric-label">Pending approval</span>
            <span class="metric-value"><?= $pendingCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-violet"><span aria-hidden="true">📝</span></div>
        <div class="metric-content">
            <span class="metric-label">Lessons</span>
            <span class="metric-value"><?= $lessonCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-amber"><span aria-hidden="true">🗓️</span></div>
        <div class="metric-content">
            <span class="metric-label">Schemes of work</span>
            <span class="metric-value"><?= $schemeCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-green"><span aria-hidden="true">📚</span></div>
        <div class="metric-content">
            <span class="metric-label">Subjects</span>
            <span class="metric-value"><?= $subjectCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-blue"><span aria-hidden="true">🗂️</span></div>
        <div class="metric-content">
            <span class="metric-label">Topics</span>
            <span class="metric-value"><?= $topicCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-amber"><span aria-hidden="true">⚠️</span></div>
        <div class="metric-content">
            <span class="metric-label">To verify</span>
            <span class="metric-value"><?= $needsVerify ?></span>
        </div>
    </div>
</section>

<div class="dashboard-layout">
    <div class="dashboard-main">
        <section class="panel" id="recent-lessons" style="margin-top:0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Recent lessons — all teachers</h2>
            </div>
            <?php if ($recentLessons): ?>
                <table class="table">
                    <thead>
                        <tr><th>Title</th><th>Teacher</th><th>Status</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentLessons as $lesson): ?>
                        <tr>
                            <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                            <td><?= htmlspecialchars($lesson['teacher_name'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime((string) $lesson['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">No lessons generated yet.</p>
            <?php endif; ?>
        </section>

        <section class="panel" id="recent-schemes">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Recent schemes of work — all teachers</h2>
            </div>
            <?php if ($recentSchemes): ?>
                <table class="table">
                    <thead>
                        <tr><th>Title</th><th>Teacher</th><th>Term</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentSchemes as $scheme): ?>
                        <tr>
                            <td><a href="scheme.php?id=<?= (int) $scheme['id'] ?>"><?= htmlspecialchars($scheme['title']) ?></a></td>
                            <td><?= htmlspecialchars($scheme['teacher_name'] ?? '—') ?></td>
                            <td>Term <?= (int) $scheme['term'] ?></td>
                            <td><?= htmlspecialchars(date('d M Y', strtotime((string) $scheme['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">No schemes generated yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="dashboard-sidebar">
        <section class="panel" id="quick-links" style="margin-top:0;">
            <h2 style="margin: 0 0 1.5rem;">Admin controls</h2>
            <div style="display: flex; flex-direction: column; gap: .75rem;">
                <a class="premium-subject-card" href="superadmin-users.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">👥 Manage teachers</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Create, approve, suspend, or remove teacher accounts.</p>
                </a>
                <a class="premium-subject-card" href="superadmin-curriculum.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">📚 Curriculum management</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Subjects, strands, topics, and KICD documents.</p>
                </a>
                <a class="premium-subject-card" href="superadmin-ai.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">🤖 AI configuration</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Claude/OpenAI/DeepSeek provider and model selection.</p>
                </a>
                <a class="premium-subject-card" href="superadmin-content.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">📝 Lessons &amp; content</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Every generated lesson, filterable by verification status.</p>
                </a>
                <a class="premium-subject-card" href="superadmin-analytics.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">📊 Analytics</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Teachers, subjects, and lessons generated over time.</p>
                </a>
                <a class="premium-subject-card" href="superadmin-security.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">🔒 Security</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Roles, permissions, and the audit log.</p>
                </a>
                <a class="premium-subject-card" href="settings.php" style="padding: 1rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.25rem;">⚙️ Platform settings</h3>
                    <p class="muted" style="margin:0; font-size: 0.8rem;">Branding, theme, and AI provider configuration.</p>
                </a>
            </div>
        </section>

        <section class="panel" id="recent-users">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Newest accounts</h2>
                <a href="superadmin-users.php" class="btn btn-small btn-outline">All</a>
            </div>
            <?php if ($recentUsers): ?>
                <div style="display: flex; flex-direction: column; gap: .75rem;">
                    <?php foreach ($recentUsers as $u): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: .5rem;">
                            <div>
                                <strong style="display:block; font-size: .9rem;"><?= htmlspecialchars($u['name']) ?></strong>
                                <span class="muted" style="font-size: .78rem;"><?= htmlspecialchars($u['email']) ?></span>
                            </div>
                            <?php if ($u['status'] === 'pending'): ?>
                                <span class="badge badge-needs_verification">Pending</span>
                            <?php elseif ($u['status'] === 'suspended'): ?>
                                <span class="badge badge-unknown">Suspended</span>
                            <?php else: ?>
                                <span class="badge" style="background:var(--surface-2); color:var(--ink);">Teacher</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">No teacher accounts yet.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
