<?php
/** Lesson page: renders a saved lesson and offers a print/save view. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT l.*, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.id = ? AND l.user_id = ?'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: dashboard.php');
    exit;
}

$payload = $lesson['payload'] !== null ? json_decode($lesson['payload'], true) : null;
$isUnknown = $lesson['status'] === 'UNKNOWN';

$pageTitle    = $lesson['title'];
$activeNav    = 'lessons';
$showHeader   = true;
$pageIcon     = '📄';
$pageHeading  = $lesson['title'];
$pageSubtitle = $lesson['subject_name'] . ' · ' . $lesson['topic_name'] . ' · ' . (int) $lesson['duration_minutes'] . ' min';
$breadcrumbs  = [
    ['label' => 'Recent lessons', 'href' => 'dashboard.php#recent'],
    ['label' => $lesson['subject_name'], 'href' => 'subject.php?id=' . (int) $lesson['subject_id']],
    ['label' => $lesson['topic_name'], 'href' => 'topic.php?id=' . (int) $lesson['topic_id']],
    ['label' => $lesson['title']],
];
$pageActions  = '<button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>'
    . '<a class="btn" href="topic.php?id=' . (int) $lesson['topic_id'] . '">Back to topic</a>';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/lesson-body.php';
?>
<div class="print-actions">
    <button class="btn" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="topic.php?id=<?= (int) $lesson['topic_id'] ?>">Back to topic</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
