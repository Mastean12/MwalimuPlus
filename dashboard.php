<?php
/** Teacher dashboard: at-a-glance stats, subjects, and recent generated lessons. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$subjects = $pdo->query(
    'SELECT id, code, name, strand FROM subjects ORDER BY name'
)->fetchAll();

$recentStmt = $pdo->prepare(
    'SELECT l.id, l.title, l.status, l.created_at, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.user_id = ?
      ORDER BY l.created_at DESC
      LIMIT 10'
);
$recentStmt->execute([$userId]);
$recent = $recentStmt->fetchAll();

// Header KPIs.
$subjectCount = (int) $pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
$topicCount   = (int) $pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn();

$lessonCountStmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE user_id = ?');
$lessonCountStmt->execute([$userId]);
$lessonCount = (int) $lessonCountStmt->fetchColumn();

$verifyStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM lessons WHERE user_id = ? AND status = 'NEEDS_VERIFICATION'"
);
$verifyStmt->execute([$userId]);
$needsVerification = (int) $verifyStmt->fetchColumn();

$pageTitle    = 'Dashboard';
$activeNav    = 'dashboard';
$showHeader   = true;
$pageIcon     = '📋';
$pageHeading  = 'Dashboard';
$pageSubtitle = 'Karibu, ' . current_user_name() . ' — pick a subject to browse its KICD strands and topics, then generate a grounded lesson plan.';
require __DIR__ . '/includes/header.php';
?>
<section class="premium-metric-grid">
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
        <div class="metric-icon-wrap metric-icon-violet"><span aria-hidden="true">📝</span></div>
        <div class="metric-content">
            <span class="metric-label">Lessons</span>
            <span class="metric-value"><?= $lessonCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-amber"><span aria-hidden="true">⚠️</span></div>
        <div class="metric-content">
            <span class="metric-label">To Verify</span>
            <span class="metric-value"><?= $needsVerification ?></span>
        </div>
    </div>
</section>

<div class="dashboard-layout">
    <div class="dashboard-main">
        <section class="panel" id="recent" style="margin-top:0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Recent lessons</h2>
                <a href="lessons.php" class="btn btn-small btn-outline">View All</a>
            </div>
            
            <?php if ($recent): ?>
                <div class="premium-card-list">
                    <?php foreach ($recent as $lesson): ?>
                        <div class="premium-card">
                            <div class="premium-card-body">
                                <div class="card-main-info">
                                    <h3 style="margin-top: 0; margin-bottom: .25rem; font-size: 1.1rem;"><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></h3>
                                    <div class="premium-card-meta">
                                        <span class="badge" style="background:var(--surface-2); color:var(--ink);"><span aria-hidden="true" style="opacity: 0.6;">📚</span> <?= htmlspecialchars($lesson['subject_name']) ?></span>
                                        <span class="badge" style="background:var(--surface-2); color:var(--ink);"><span aria-hidden="true" style="opacity: 0.6;">📑</span> <?= htmlspecialchars($lesson['topic_name']) ?></span>
                                        <span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span>
                                    </div>
                                </div>
                                <p class="muted card-date" style="font-size: .8rem; margin-top: .75rem;"><?= htmlspecialchars(date('d M Y', strtotime($lesson['created_at']))) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">No lessons yet — open a subject and topic to generate your first one.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="dashboard-sidebar">
        <section class="panel" id="subjects" style="margin-top:0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Subjects</h2>
                <a href="subjects.php" class="btn btn-small btn-outline">All</a>
            </div>

            <?php if ($subjects): ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach (array_slice($subjects, 0, 5) as $subject): ?>
                        <a class="premium-subject-card" href="subject.php?id=<?= (int) $subject['id'] ?>" style="padding: 1rem;">
                            <span class="subject-code-badge" style="top: 1rem; right: 1rem;"><?= htmlspecialchars($subject['code']) ?></span>
                            <h3 style="font-size: 1rem; margin-bottom: 0.25rem;"><?= htmlspecialchars($subject['name']) ?></h3>
                            <p class="muted" style="margin:0; font-size: 0.8rem;"><?= htmlspecialchars($subject['strand']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">No subjects seeded yet — run <code>php database/seed.php</code>.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
