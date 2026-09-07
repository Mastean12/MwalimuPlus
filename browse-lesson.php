<?php
/** Public lesson page: renders a generated lesson, any teacher's. No login required. */

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

$stmt = $pdo->prepare(
    'SELECT l.*, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.id = ?'
);
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: browse.php');
    exit;
}

$payload = $lesson['payload'] !== null ? json_decode($lesson['payload'], true) : null;
$isUnknown = $lesson['status'] === 'UNKNOWN';

$pageTitle    = $lesson['title'];
$publicHeader = true;
require __DIR__ . '/includes/header.php';
?>
<p>
    <a href="browse.php">All subjects</a>
    · <a href="browse-subject.php?id=<?= (int) $lesson['subject_id'] ?>"><?= htmlspecialchars($lesson['subject_name']) ?></a>
    · <a href="browse-topic.php?id=<?= (int) $lesson['topic_id'] ?>"><?= htmlspecialchars($lesson['topic_name']) ?></a>
</p>

<div class="page-head">
    <div class="page-head-main">
        <span class="page-head-icon" aria-hidden="true">📄</span>
        <div>
            <h1><?= htmlspecialchars($lesson['title']) ?></h1>
            <p class="page-head-sub"><?= htmlspecialchars($lesson['subject_name']) ?> · <?= htmlspecialchars($lesson['topic_name']) ?> · <?= (int) $lesson['duration_minutes'] ?> min</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/lesson-body.php'; ?>

<div class="print-actions">
    <button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="browse-topic.php?id=<?= (int) $lesson['topic_id'] ?>">Back to topic</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
