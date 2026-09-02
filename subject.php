<?php
/** Subject page: lists a subject's KICD strand and its topics. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id, code, name, strand, grade_level FROM subjects WHERE id = ?');
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name FROM topics WHERE subject_id = ? ORDER BY name'
);
$stmt->execute([$id]);
$topics = $stmt->fetchAll();

$pageTitle    = $subject['name'];
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📚';
$pageHeading  = $subject['name'];
$pageSubtitle = $subject['code'] . ' · ' . $subject['grade_level'] . ' · Strand: ' . $subject['strand'];
$breadcrumbs  = [
    ['label' => 'Subjects', 'href' => 'dashboard.php#subjects'],
    ['label' => $subject['name']],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h2>Topics</h2>
    <?php if ($topics): ?>
        <ul class="topic-list">
            <?php foreach ($topics as $topic): ?>
                <li>
                    <a href="topic.php?id=<?= (int) $topic['id'] ?>">
                        <?= htmlspecialchars($topic['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">No topics found for this subject.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
