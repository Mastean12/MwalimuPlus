<?php
/** Public subject page: lists a subject's topics. No login required. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();
enforce_not_in_maintenance();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: browse.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id, code, name, strand, grade_level FROM subjects WHERE id = ?');
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: browse.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name FROM topics WHERE subject_id = ? ORDER BY name');
$stmt->execute([$id]);
$topics = $stmt->fetchAll();

$pageTitle    = $subject['name'];
$publicHeader = true;
require __DIR__ . '/includes/header.php';
?>
<p><a href="browse.php">&larr; All subjects</a></p>

<div class="page-head">
    <div class="page-head-main">
        <span class="page-head-icon" aria-hidden="true">📚</span>
        <div>
            <h1><?= htmlspecialchars($subject['name']) ?></h1>
            <p class="page-head-sub"><?= htmlspecialchars($subject['code']) ?> · <?= htmlspecialchars($subject['grade_level']) ?> · Strand: <?= htmlspecialchars($subject['strand']) ?></p>
        </div>
    </div>
</div>

<section class="panel">
    <h2>Topics</h2>
    <?php if ($topics): ?>
        <ul class="topic-list">
            <?php foreach ($topics as $topic): ?>
                <li>
                    <a href="browse-topic.php?id=<?= (int) $topic['id'] ?>">
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
