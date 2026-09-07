<?php
/** Superadmin: every generated lesson, filterable by verification status. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();

$filter = $_GET['status'] ?? 'all';
$validStatuses = ['SUPPORTED', 'NEEDS_VERIFICATION', 'UNKNOWN'];
if (!in_array($filter, $validStatuses, true)) {
    $filter = 'all';
}

$sql = 'SELECT l.id, l.title, l.status, l.created_at, u.name AS teacher_name, s.name AS subject_name
          FROM lessons l
          LEFT JOIN users u ON u.id = l.user_id
          JOIN subjects s ON s.id = l.subject_id';
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE l.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY l.created_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

$counts = $pdo->query(
    "SELECT status, COUNT(*) AS n FROM lessons GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle    = 'Lessons & content';
$activeNav    = 'admin-content';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '📝';
$pageHeading  = 'Lessons & content';
$pageSubtitle = 'Every generated lesson across every teacher. "Needs verification" and "Unknown" are content flagged by the grounding rule for human review.';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Lessons & content'],
];
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex; gap:.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
    <a class="btn btn-small <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" href="superadmin-content.php">All (<?= array_sum($counts) ?>)</a>
    <a class="btn btn-small <?= $filter === 'SUPPORTED' ? 'btn-primary' : 'btn-outline' ?>" href="superadmin-content.php?status=SUPPORTED">Supported (<?= (int) ($counts['SUPPORTED'] ?? 0) ?>)</a>
    <a class="btn btn-small <?= $filter === 'NEEDS_VERIFICATION' ? 'btn-primary' : 'btn-outline' ?>" href="superadmin-content.php?status=NEEDS_VERIFICATION">Needs verification (<?= (int) ($counts['NEEDS_VERIFICATION'] ?? 0) ?>)</a>
    <a class="btn btn-small <?= $filter === 'UNKNOWN' ? 'btn-primary' : 'btn-outline' ?>" href="superadmin-content.php?status=UNKNOWN">Flagged / unknown (<?= (int) ($counts['UNKNOWN'] ?? 0) ?>)</a>
</div>

<section class="panel" style="margin-top:0;">
    <?php if ($lessons): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Teacher</th><th>Subject</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><?= htmlspecialchars($lesson['teacher_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($lesson['subject_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string) $lesson['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No lessons match this filter.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
