<?php
/** Grounded chat about a single lesson — the teacher asks, the AI cites the design. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$lessonId = filter_input(INPUT_GET, 'lesson', FILTER_VALIDATE_INT);
if ($lessonId === null || $lessonId === false) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT l.id, l.title, l.status, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.id = ? AND l.user_id = ?'
);
$stmt->execute([$lessonId, (int) $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson || $lesson['status'] === 'UNKNOWN') {
    header('Location: lesson.php?id=' . $lessonId);
    exit;
}

$pageTitle    = 'Ask the AI';
$activeNav    = 'lessons';
$showHeader   = true;
$pageIcon     = '💬';
$pageHeading  = 'Ask the AI';
$pageSubtitle = 'About: ' . $lesson['title'] . ' · ' . $lesson['subject_name'] . ' · ' . $lesson['topic_name'];
$breadcrumbs  = [
    ['label' => 'Recent lessons', 'href' => 'dashboard.php#recent'],
    ['label' => $lesson['title'], 'href' => 'lesson.php?id=' . (int) $lesson['id']],
    ['label' => 'Ask the AI'],
];
$pageActions  = '<a class="btn" href="lesson.php?id=' . (int) $lesson['id'] . '">Back to lesson</a>';
require __DIR__ . '/includes/header.php';
?>
<section class="panel chat" data-lesson-id="<?= (int) $lesson['id'] ?>">
    <div id="chat-log" class="chat-log">
        <div class="chat-msg chat-ai">
            <p>Ask me anything about this lesson. I answer only from the KICD strand design it was built from, and every point cites its page. If the design doesn't cover something, I'll say <em>Sijui</em>.</p>
        </div>
    </div>

    <p class="disclosure">This is an AI assistant. It can be wrong. The teacher makes the final decision.</p>

    <form id="chat-form" class="chat-form">
        <textarea id="chat-input" rows="2" required
            placeholder="e.g. What visual model does the design recommend for factorising?"></textarea>
        <div class="chat-actions">
            <button type="button" id="chat-mic" class="btn" hidden title="Speak your question">🎤 Speak</button>
            <button type="submit" class="btn btn-primary" id="chat-send">Ask</button>
        </div>
    </form>
</section>

<script src="assets/js/ask.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
