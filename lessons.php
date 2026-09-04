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
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <h2 style="margin: 0;">Generated Lessons</h2>
            <span class="badge" style="background: var(--surface-2); color: var(--muted); font-weight: 600; font-size: 0.78rem; padding: 0.25rem 0.65rem; border-radius: 999px;"><?= $total ?> total</span>
        </div>
        <div class="layout-toggle" style="display: flex; gap: .25rem; background: var(--surface-2); padding: .25rem; border-radius: 999px; border: 1px solid var(--line);">
            <button type="button" class="btn btn-small btn-outline" id="toggle-list" style="margin: 0; border: none; border-radius: 999px; padding: 0.35rem 0.75rem;" title="List View">
                <span aria-hidden="true">☰ List</span>
            </button>
            <button type="button" class="btn btn-small btn-outline" id="toggle-grid" style="margin: 0; border: none; border-radius: 999px; padding: 0.35rem 0.75rem;" title="Grid View">
                <span aria-hidden="true">⊞ Grid</span>
            </button>
        </div>
    </div>

    <form method="get" action="lessons.php" class="lessons-filter-bar">
        <div class="filter-search-field">
            <span aria-hidden="true" style="color: var(--muted); font-size: 0.9rem;">🔍</span>
            <input type="search" id="lessons-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search title…">
        </div>

        <div class="filter-select-field">
            <span aria-hidden="true" style="font-size: 0.85rem;">📚</span>
            <select id="lessons-subject" name="subject_id">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"<?= $subjectId === (int) $subject['id'] ? ' selected' : '' ?>><?= htmlspecialchars($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-select-field">
            <span aria-hidden="true" style="font-size: 0.85rem;">⚡</span>
            <select id="lessons-status" name="status">
                <option value="">All statuses</option>
                <option value="SUPPORTED"<?= $status === 'SUPPORTED' ? ' selected' : '' ?>>Supported</option>
                <option value="NEEDS_VERIFICATION"<?= $status === 'NEEDS_VERIFICATION' ? ' selected' : '' ?>>Needs verification</option>
                <option value="UNKNOWN"<?= $status === 'UNKNOWN' ? ' selected' : '' ?>>Unknown</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply filters</button>
            <?php if ($hasFilters): ?>
                <a href="lessons.php" class="filter-clear-link">✕ Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($lessons): ?>
        <div class="premium-card-list" id="lessons-container">
            <?php foreach ($lessons as $lesson): ?>
                <div class="premium-card">
                    <div class="premium-card-body">
                        <div class="card-main-info">
                            <h3 style="margin-top: 0; margin-bottom: .5rem; font-size: 1.15rem;"><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></h3>
                            <div class="premium-card-meta">
                                <span class="badge" style="background:var(--surface-2); color:var(--ink);"><span aria-hidden="true" style="opacity: 0.6;">📚</span> <?= htmlspecialchars($lesson['subject_name']) ?></span>
                                <span class="badge" style="background:var(--surface-2); color:var(--ink);"><span aria-hidden="true" style="opacity: 0.6;">📑</span> <?= htmlspecialchars($lesson['topic_name']) ?></span>
                                <?php if ($lesson['scheme_id']): ?>
                                    <a href="scheme.php?id=<?= (int) $lesson['scheme_id'] ?>" class="badge" style="background:var(--green-50); color:var(--green-700); text-decoration: none;"><span aria-hidden="true">🗓️</span> <?= htmlspecialchars((string) $lesson['scheme_title']) ?></a>
                                <?php endif; ?>
                                <span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span>
                            </div>
                        </div>
                        <p class="muted card-date" style="font-size: .85rem; margin-top: 1rem; display: flex; gap: 1rem;">
                            <span>⏱️ <?= (int) $lesson['duration_minutes'] ?> min</span>
                            <span>Created: <?= htmlspecialchars(date('d M Y, H:i', strtotime($lesson['created_at']))) ?></span>
                        </p>
                    </div>
                    <div class="premium-card-footer">
                        <a class="btn btn-small" href="lesson.php?id=<?= (int) $lesson['id'] ?>" style="margin-left: auto;">View Lesson</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('lessons-container');
    var btnList = document.getElementById('toggle-list');
    var btnGrid = document.getElementById('toggle-grid');
    
    if (container && btnList && btnGrid) {
        var currentPref = localStorage.getItem('lessons_layout_pref') || 'list';
        function applyLayout(mode) {
            if (mode === 'grid') {
                container.classList.remove('premium-card-list');
                container.classList.add('premium-card-grid');
                btnGrid.classList.add('active');
                btnList.classList.remove('active');
            } else {
                container.classList.remove('premium-card-grid');
                container.classList.add('premium-card-list');
                btnList.classList.add('active');
                btnGrid.classList.remove('active');
            }
            localStorage.setItem('lessons_layout_pref', mode);
        }
        
        applyLayout(currentPref);
        
        btnList.addEventListener('click', function() { applyLayout('list'); });
        btnGrid.addEventListener('click', function() { applyLayout('grid'); });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
