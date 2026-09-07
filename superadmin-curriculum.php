<?php
/** Superadmin: Curriculum Management — subjects, strands, topics, KICD documents. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();

$subjects = $pdo->query(
    "SELECT s.id, s.code, s.name, s.strand, s.grade_level, s.source_pdf,
            (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) AS topic_count
       FROM subjects s
      ORDER BY s.name"
)->fetchAll();

$strands = $pdo->query(
    'SELECT strand, COUNT(*) AS subject_count FROM subjects GROUP BY strand ORDER BY strand'
)->fetchAll();

$topicCount = (int) $pdo->query('SELECT COUNT(*) FROM topics')->fetchColumn();
$documentCount = (int) $pdo->query('SELECT COUNT(*) FROM subjects WHERE source_pdf IS NOT NULL')->fetchColumn();

$pageTitle    = 'Curriculum management';
$activeNav    = 'admin-curriculum';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '📚';
$pageHeading  = 'Curriculum management';
$pageSubtitle = 'Subjects, strands, topics, and KICD source documents grounding lesson and scheme generation.';
$pageActions  = '<a href="curriculum.php" class="btn btn-primary">+ Add subject / topic</a>';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Curriculum management'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="premium-metric-grid">
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-green"><span aria-hidden="true">📚</span></div>
        <div class="metric-content">
            <span class="metric-label">Subjects</span>
            <span class="metric-value"><?= count($subjects) ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-blue"><span aria-hidden="true">🧬</span></div>
        <div class="metric-content">
            <span class="metric-label">Strands</span>
            <span class="metric-value"><?= count($strands) ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-violet"><span aria-hidden="true">🗂️</span></div>
        <div class="metric-content">
            <span class="metric-label">Topics</span>
            <span class="metric-value"><?= $topicCount ?></span>
        </div>
    </div>
    <div class="premium-metric-card">
        <div class="metric-icon-wrap metric-icon-amber"><span aria-hidden="true">📄</span></div>
        <div class="metric-content">
            <span class="metric-label">KICD documents</span>
            <span class="metric-value"><?= $documentCount ?></span>
        </div>
    </div>
</section>

<section class="panel" style="margin-top:0;">
    <h2>Subjects</h2>
    <?php if ($subjects): ?>
        <table class="table">
            <thead>
                <tr><th>Code</th><th>Name</th><th>Strand</th><th>Grade</th><th>Topics</th><th>KICD document</th></tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['code']) ?></td>
                    <td><a href="subject.php?id=<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></a></td>
                    <td><?= htmlspecialchars($s['strand']) ?></td>
                    <td><?= htmlspecialchars($s['grade_level']) ?></td>
                    <td><?= (int) $s['topic_count'] ?></td>
                    <td><?= $s['source_pdf'] ? '<span class="badge badge-supported">Attached</span>' : '<span class="muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No subjects seeded yet.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Strands</h2>
    <?php if ($strands): ?>
        <table class="table">
            <thead>
                <tr><th>Strand</th><th>Subjects</th></tr>
            </thead>
            <tbody>
            <?php foreach ($strands as $st): ?>
                <tr>
                    <td><?= htmlspecialchars($st['strand']) ?></td>
                    <td><?= (int) $st['subject_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No strands yet.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Curriculum versions</h2>
    <p class="muted">Version history for curriculum edits (who changed what, and when) isn't tracked yet — today, subjects and topics are edited in place. This section is reserved for that once it's built.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
