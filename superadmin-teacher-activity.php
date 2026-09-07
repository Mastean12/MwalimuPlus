<?php
/** Superadmin: one teacher's lessons and schemes of work. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();
$teacherId = (int) ($_GET['id'] ?? 0);

$teacherStmt = $pdo->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ?');
$teacherStmt->execute([$teacherId]);
$teacher = $teacherStmt->fetch();

if ($teacher === false) {
    set_flash('error', 'Teacher not found.');
    header('Location: superadmin-users.php');
    exit;
}

$lessonsStmt = $pdo->prepare(
    'SELECT l.id, l.title, l.status, l.created_at, s.name AS subject_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
      WHERE l.user_id = ?
      ORDER BY l.created_at DESC'
);
$lessonsStmt->execute([$teacherId]);
$lessons = $lessonsStmt->fetchAll();

$schemesStmt = $pdo->prepare(
    'SELECT sc.id, sc.title, sc.term, sc.status, sc.created_at, s.name AS subject_name
       FROM schemes sc
       JOIN subjects s ON s.id = sc.subject_id
      WHERE sc.user_id = ?
      ORDER BY sc.created_at DESC'
);
$schemesStmt->execute([$teacherId]);
$schemes = $schemesStmt->fetchAll();

$pageTitle    = $teacher['name'] . ' — activity';
$activeNav    = 'admin-teachers';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '👤';
$pageHeading  = htmlspecialchars($teacher['name']);
$pageSubtitle = $teacher['email'] . ' · joined ' . date('d M Y', strtotime((string) $teacher['created_at']));
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Manage teachers', 'href' => 'superadmin-users.php'],
    ['label' => $teacher['name']],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="margin-top:0;">
    <h2>Lessons (<?= count($lessons) ?>)</h2>
    <?php if ($lessons): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><?= htmlspecialchars($lesson['subject_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string) $lesson['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No lessons generated yet.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Schemes of work (<?= count($schemes) ?>)</h2>
    <?php if ($schemes): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Term</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($schemes as $scheme): ?>
                <tr>
                    <td><a href="scheme.php?id=<?= (int) $scheme['id'] ?>"><?= htmlspecialchars($scheme['title']) ?></a></td>
                    <td><?= htmlspecialchars($scheme['subject_name']) ?></td>
                    <td>Term <?= (int) $scheme['term'] ?></td>
                    <td><span class="badge badge-<?= strtolower($scheme['status']) ?>"><?= htmlspecialchars($scheme['status']) ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string) $scheme['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No schemes of work generated yet.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
