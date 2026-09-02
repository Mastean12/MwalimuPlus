<?php
/** Teacher dashboard: lists subjects and recent generated lessons. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();

$subjects = $pdo->query(
    'SELECT id, code, name, strand FROM subjects ORDER BY name'
)->fetchAll();

$recent = $pdo->prepare(
    'SELECT l.id, l.title, l.status, l.created_at, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.user_id = ?
      ORDER BY l.created_at DESC
      LIMIT 10'
);
$recent->execute([(int) $_SESSION['user_id']]);
$recent = $recent->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$showHeader = true;
require __DIR__ . '/includes/header.php';
?>
<h1>Karibu, <?= htmlspecialchars(current_user_name()) ?></h1>
<p class="lead">Pick a subject to browse its KICD strands and topics, then generate a grounded lesson plan.</p>

<section class="card-grid">
    <?php foreach ($subjects as $subject): ?>
        <a class="card subject-card" href="subject.php?id=<?= (int) $subject['id'] ?>">
            <span class="subject-code"><?= htmlspecialchars($subject['code']) ?></span>
            <h2><?= htmlspecialchars($subject['name']) ?></h2>
            <p><?= htmlspecialchars($subject['strand']) ?></p>
        </a>
    <?php endforeach; ?>
</section>

<section class="panel">
    <h2>Recent lessons</h2>
    <?php if ($recent): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><?= htmlspecialchars($lesson['subject_name']) ?> · <?= htmlspecialchars($lesson['topic_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No lessons yet — open a subject and topic to generate your first one.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
