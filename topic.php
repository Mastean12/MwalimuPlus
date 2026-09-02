<?php
/** Topic page: shows the topic plus a grounded lesson-generation form. */

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
    'SELECT t.id, t.name, t.strand, t.source_file, s.id AS subject_id, s.name AS subject_name
       FROM topics t
       JOIN subjects s ON s.id = t.subject_id
      WHERE t.id = ?'
);
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) {
    header('Location: dashboard.php');
    exit;
}

// Existing lessons for this topic (read-only list under the generator).
$stmt = $pdo->prepare(
    'SELECT id, title, status, created_at FROM lessons WHERE topic_id = ? AND user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$existing = $stmt->fetchAll();

$pageTitle    = $topic['name'];
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📝';
$pageHeading  = $topic['name'];
$pageSubtitle = 'Strand: ' . $topic['strand'];
$breadcrumbs  = [
    ['label' => 'Subjects', 'href' => 'dashboard.php#subjects'],
    ['label' => $topic['subject_name'], 'href' => 'subject.php?id=' . (int) $topic['subject_id']],
    ['label' => $topic['name']],
];
$pageActions  = '<a class="btn btn-primary" href="#generate-form">＋ Generate lesson</a>';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h2>Generate a lesson plan</h2>
    <p class="muted">Grounded only in the KICD strand design on the server. AI output can be wrong — the teacher makes the final decision.</p>

    <form id="generate-form" class="generate-form">
        <input type="hidden" id="topic-name" value="<?= htmlspecialchars($topic['name']) ?>">
        <input type="hidden" id="subject-name" value="<?= htmlspecialchars($topic['subject_name']) ?>">
        <input type="hidden" id="strand-name" value="<?= htmlspecialchars($topic['strand']) ?>">

        <label>Lesson duration (minutes)
            <input type="number" id="duration" value="40" min="5" max="240" step="5">
        </label>

        <label>What do you need help with?
            <textarea id="teacher-need" rows="3"
                placeholder="e.g. I have never taught this topic before."></textarea>
        </label>

        <label>Available resources (comma-separated)
            <input type="text" id="resources" value="chalkboard, chalk" placeholder="chalkboard, chalk">
        </label>

        <button type="submit" class="btn btn-primary" id="generate-btn">Generate lesson</button>
    </form>

    <div id="generate-result" hidden></div>
</section>

<section class="panel">
    <h2>Generated for this topic</h2>
    <?php if ($existing): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($existing as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No lessons generated for this topic yet.</p>
    <?php endif; ?>
</section>

<script src="assets/js/lesson.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
