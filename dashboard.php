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
<section class="stat-grid">
    <div class="stat-card">
        <div>
            <p class="stat-label">Subjects</p>
            <p class="stat-value"><?= $subjectCount ?></p>
        </div>
        <span class="stat-icon stat-icon-green" aria-hidden="true">📚</span>
    </div>
    <div class="stat-card">
        <div>
            <p class="stat-label">Topics available</p>
            <p class="stat-value"><?= $topicCount ?></p>
        </div>
        <span class="stat-icon stat-icon-blue" aria-hidden="true">🗂️</span>
    </div>
    <div class="stat-card">
        <div>
            <p class="stat-label">Lessons generated</p>
            <p class="stat-value"><?= $lessonCount ?></p>
        </div>
        <span class="stat-icon stat-icon-violet" aria-hidden="true">📝</span>
    </div>
    <div class="stat-card">
        <div>
            <p class="stat-label">Needs verification</p>
            <p class="stat-value"><?= $needsVerification ?></p>
        </div>
        <span class="stat-icon stat-icon-amber" aria-hidden="true">⚠️</span>
    </div>
</section>

<section class="panel" id="subjects">
    <h2>Subjects</h2>
    <?php if ($subjects): ?>
        <div class="card-grid">
            <?php foreach ($subjects as $subject): ?>
                <a class="card subject-card" href="subject.php?id=<?= (int) $subject['id'] ?>">
                    <span class="subject-code"><?= htmlspecialchars($subject['code']) ?></span>
                    <h3><?= htmlspecialchars($subject['name']) ?></h3>
                    <p class="muted"><?= htmlspecialchars($subject['strand']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">No subjects seeded yet — run <code>php database/seed.php</code>.</p>
    <?php endif; ?>
</section>

<section class="panel" id="recent">
    <h2>Recent lessons</h2>
    <?php if ($recent): ?>
        <div class="toolbar">
            <div class="field field-search">
                <label for="lesson-search">Search</label>
                <input type="search" id="lesson-search" placeholder="Search title or topic…"
                       data-filter-for="recent-table">
            </div>
            <div class="field">
                <label for="lesson-subject">Subject</label>
                <select id="lesson-subject" data-filter-for="recent-table" data-filter-col="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= htmlspecialchars($subject['name']) ?>"><?= htmlspecialchars($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="lesson-status">Status</label>
                <select id="lesson-status" data-filter-for="recent-table" data-filter-col="status">
                    <option value="">All statuses</option>
                    <option value="SUPPORTED">Supported</option>
                    <option value="NEEDS_VERIFICATION">Needs verification</option>
                    <option value="UNKNOWN">Unknown</option>
                </select>
            </div>
        </div>
        <table class="table" id="recent-table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $lesson): ?>
                <tr data-subject="<?= htmlspecialchars($lesson['subject_name']) ?>"
                    data-status="<?= htmlspecialchars($lesson['status']) ?>">
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><?= htmlspecialchars($lesson['subject_name']) ?> · <?= htmlspecialchars($lesson['topic_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" data-filter-empty="recent-table" hidden>No lessons match those filters.</p>
    <?php else: ?>
        <p class="muted">No lessons yet — open a subject and topic to generate your first one.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
