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

$pageTitle = $lesson['title'];
$activeNav = 'dashboard';
$showHeader = true;
require __DIR__ . '/includes/header.php';
?>
<nav class="breadcrumbs">
    <a href="dashboard.php">Dashboard</a>
    <a href="subject.php?id=<?= (int) $lesson['subject_id'] ?>"><?= htmlspecialchars($lesson['subject_name']) ?></a>
    <a href="topic.php?id=<?= (int) $lesson['topic_id'] ?>"><?= htmlspecialchars($lesson['topic_name']) ?></a>
    <span>/</span>
    <span><?= htmlspecialchars($lesson['title']) ?></span>
</nav>

<h1><?= htmlspecialchars($lesson['title']) ?></h1>
<p class="lead">
    <?= htmlspecialchars($lesson['subject_name']) ?> · <?= htmlspecialchars($lesson['topic_name']) ?> ·
    <?= (int) $lesson['duration_minutes'] ?> min
</p>
<p><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></p>
<p class="disclosure">This is an AI assistant. It can be wrong. The teacher makes the final decision.</p>

<?php if ($isUnknown || $payload === null): ?>
    <section class="panel panel-sijui">
        <h2>Not supported by the curriculum source</h2>
        <p><?= htmlspecialchars($payload['sijui'] ?? 'I can\'t answer that from the curriculum design I have. Check the KICD office or the Ministry of Education for guidance.') ?></p>
    </section>
<?php else: ?>
    <?php $citations = $payload['citations'] ?? []; ?>
    <?php if ($citations !== []): ?>
        <p class="citations">Sources: <?= htmlspecialchars(implode(', ', $citations)) ?></p>
    <?php endif; ?>

    <section class="panel">
        <h2>Learning objectives</h2>
        <?php if (!empty($payload['objectives'])): ?>
            <ul>
                <?php foreach ($payload['objectives'] as $objective): ?>
                    <li><?= htmlspecialchars($objective) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">None listed.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Prerequisites</h2>
        <?php if (!empty($payload['prerequisites'])): ?>
            <ul>
                <?php foreach ($payload['prerequisites'] as $prerequisite): ?>
                    <li><?= htmlspecialchars($prerequisite) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">None listed.</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($payload['teacher_explanation'])): ?>
        <section class="panel">
            <h2>Teacher explanation</h2>
            <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_explanation'])) ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['examples'])): ?>
        <section class="panel">
            <h2>Examples</h2>
            <?php if (is_array($payload['examples'])): ?>
                <ol>
                    <?php foreach ($payload['examples'] as $example): ?>
                        <li><?= htmlspecialchars(is_array($example) ? json_encode($example) : $example) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['examples'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['board_plan'])): ?>
        <section class="panel">
            <h2>Board plan</h2>
            <pre class="board-plan"><?= htmlspecialchars($payload['board_plan']) ?></pre>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['teacher_questions'])): ?>
        <section class="panel">
            <h2>Suggested teacher questions</h2>
            <?php if (is_array($payload['teacher_questions'])): ?>
                <ul>
                    <?php foreach ($payload['teacher_questions'] as $question): ?>
                        <li><?= htmlspecialchars(is_array($question) ? json_encode($question) : $question) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_questions'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['common_misconceptions'])): ?>
        <section class="panel">
            <h2>Common misconceptions</h2>
            <?php if (is_array($payload['common_misconceptions'])): ?>
                <ul>
                    <?php foreach ($payload['common_misconceptions'] as $item): ?>
                        <li><?= htmlspecialchars(is_array($item) ? json_encode($item) : $item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['common_misconceptions'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['quick_check'])): ?>
        <section class="panel">
            <h2>Quick check</h2>
            <?php if (is_array($payload['quick_check'])): ?>
                <ul>
                    <?php foreach ($payload['quick_check'] as $item): ?>
                        <li><?= htmlspecialchars(is_array($item) ? json_encode($item) : $item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['quick_check'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['teacher_notes'])): ?>
        <section class="panel">
            <h2>Teacher notes</h2>
            <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_notes'])) ?></div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<div class="print-actions">
    <button class="btn" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="topic.php?id=<?= (int) $lesson['topic_id'] ?>">Back to topic</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
