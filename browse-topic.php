<?php
/**
 * Public topic page: topic details, Next/Previous navigation, a sidebar of
 * sibling topics, and every lesson generated for this topic (any teacher).
 * No login required.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: browse.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT t.id, t.name, t.strand, s.id AS subject_id, s.name AS subject_name
       FROM topics t
       JOIN subjects s ON s.id = t.subject_id
      WHERE t.id = ?'
);
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) {
    header('Location: browse.php');
    exit;
}

// Same order as browse-subject.php, so Next/Previous and the sidebar agree with it.
$stmt = $pdo->prepare('SELECT id, name FROM topics WHERE subject_id = ? ORDER BY name');
$stmt->execute([(int) $topic['subject_id']]);
$siblings = $stmt->fetchAll();

$position = null;
foreach ($siblings as $index => $sibling) {
    if ((int) $sibling['id'] === $id) {
        $position = $index;
        break;
    }
}
$prevTopic = $position !== null && $position > 0 ? $siblings[$position - 1] : null;
$nextTopic = $position !== null && $position < count($siblings) - 1 ? $siblings[$position + 1] : null;

// Lessons generated for this topic by any teacher — this page is public by design.
$stmt = $pdo->prepare(
    'SELECT id, title, status, duration_minutes, created_at FROM lessons WHERE topic_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$id]);
$lessons = $stmt->fetchAll();

$pageTitle    = $topic['name'];
$publicHeader = true;
require __DIR__ . '/includes/header.php';
?>
<p>
    <a href="browse.php">All subjects</a>
    · <a href="browse-subject.php?id=<?= (int) $topic['subject_id'] ?>"><?= htmlspecialchars($topic['subject_name']) ?></a>
</p>

<div class="page-head">
    <div class="page-head-main">
        <span class="page-head-icon" aria-hidden="true">📝</span>
        <div>
            <h1><?= htmlspecialchars($topic['name']) ?></h1>
            <p class="page-head-sub">Strand: <?= htmlspecialchars($topic['strand']) ?></p>
        </div>
    </div>
</div>

<div class="browse-layout">
    <div class="browse-main">
        <nav class="browse-pager">
            <?php if ($prevTopic): ?>
                <a class="btn" href="browse-topic.php?id=<?= (int) $prevTopic['id'] ?>">&larr; <?= htmlspecialchars($prevTopic['name']) ?></a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($nextTopic): ?>
                <a class="btn" href="browse-topic.php?id=<?= (int) $nextTopic['id'] ?>"><?= htmlspecialchars($nextTopic['name']) ?> &rarr;</a>
            <?php endif; ?>
        </nav>

        <section class="panel">
            <h2>Lessons for this topic</h2>
            <?php if ($lessons): ?>
                <table class="table">
                    <thead>
                        <tr><th>Title</th><th>Status</th><th>Duration</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><a href="browse-lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                            <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                            <td><?= (int) $lesson['duration_minutes'] ?> min</td>
                            <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">No lessons generated for this topic yet.</p>
            <?php endif; ?>
        </section>
    </div>

    <aside class="browse-sidebar panel">
        <h2>Other topics in <?= htmlspecialchars($topic['subject_name']) ?></h2>
        <ul class="topic-list">
            <?php foreach ($siblings as $sibling): ?>
                <li>
                    <a href="browse-topic.php?id=<?= (int) $sibling['id'] ?>"<?= (int) $sibling['id'] === $id ? ' class="active" aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($sibling['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
