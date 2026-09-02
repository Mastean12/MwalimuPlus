<?php
/** Lessons: every lesson this teacher has generated, filterable and paginated. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT) ?: null;
$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, ['SUPPORTED', 'NEEDS_VERIFICATION', 'UNKNOWN'], true) ? $status : '';
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ['l.user_id = ?'];
$params = [$userId];
if ($subjectId !== null) {
    $where[] = 'l.subject_id = ?';
    $params[] = $subjectId;
}
if ($status !== '') {
    $where[] = 'l.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = 'l.title LIKE ?';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM lessons l WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT l.id, l.title, l.status, l.duration_minutes, l.created_at, l.scheme_id,
            s.name AS subject_name, t.name AS topic_name, sc.title AS scheme_title
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
       LEFT JOIN schemes sc ON sc.id = l.scheme_id
      WHERE {$whereSql}
      ORDER BY l.created_at DESC
      LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();
$hasFilters = $subjectId !== null || $status !== '' || $q !== '';

/** Current filters as a query string, with $overrides layered on top (for pager links). */
function lessons_query(array $overrides): string
{
    $base = [
        'subject_id' => $_GET['subject_id'] ?? '',
        'status' => $_GET['status'] ?? '',
        'q' => $_GET['q'] ?? '',
        'page' => $_GET['page'] ?? '',
    ];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, static fn ($v) => $v !== '' && $v !== null);
    return http_build_query($merged);
}

$pageTitle    = 'Lessons';
$activeNav    = 'lessons';
$showHeader   = true;
$pageIcon     = '📝';
$pageHeading  = 'Lessons';
$pageSubtitle = $total . ' lesson' . ($total === 1 ? '' : 's') . ' generated so far.';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <form method="get" action="lessons.php" class="toolbar">
        <div class="field field-search">
            <label for="lessons-search">Search</label>
            <input type="search" id="lessons-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search title…">
        </div>
        <div class="field">
            <label for="lessons-subject">Subject</label>
            <select id="lessons-subject" name="subject_id">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"<?= $subjectId === (int) $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="lessons-status">Status</label>
            <select id="lessons-status" name="status">
                <option value="">All statuses</option>
                <option value="SUPPORTED"<?= $status === 'SUPPORTED' ? ' selected' : '' ?>>Supported</option>
                <option value="NEEDS_VERIFICATION"<?= $status === 'NEEDS_VERIFICATION' ? ' selected' : '' ?>>Needs verification</option>
                <option value="UNKNOWN"<?= $status === 'UNKNOWN' ? ' selected' : '' ?>>Unknown</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Apply filters</button>
        <?php if ($hasFilters): ?>
            <a class="btn" href="lessons.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($lessons): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Topic</th><th>Scheme</th><th>Status</th><th>Duration</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><?= htmlspecialchars($lesson['subject_name']) ?></td>
                    <td><?= htmlspecialchars($lesson['topic_name']) ?></td>
                    <td>
                        <?php if ($lesson['scheme_id']): ?>
                            <a href="scheme.php?id=<?= (int) $lesson['scheme_id'] ?>"><?= htmlspecialchars((string) $lesson['scheme_title']) ?></a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= (int) $lesson['duration_minutes'] ?> min</td>
                    <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav class="browse-pager">
                <?php if ($page > 1): ?>
                    <a class="btn" href="?<?= htmlspecialchars(lessons_query(['page' => $page - 1])) ?>">&larr; Previous</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn" href="?<?= htmlspecialchars(lessons_query(['page' => $page + 1])) ?>">Next &rarr;</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted"><?= $hasFilters ? 'No lessons match those filters.' : 'No lessons yet — open a subject and topic to generate your first one.' ?></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
