<?php
/** Subjects: the full KICD curriculum catalogue, searchable. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$q = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT id, code, name, strand, grade_level FROM subjects';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE name LIKE ? OR code LIKE ? OR strand LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

$topicCounts = $pdo->query('SELECT subject_id, COUNT(*) AS n FROM topics GROUP BY subject_id')
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$lessonCountStmt = $pdo->prepare('SELECT subject_id, COUNT(*) AS n FROM lessons WHERE user_id = ? GROUP BY subject_id');
$lessonCountStmt->execute([$userId]);
$lessonCounts = $lessonCountStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle    = 'Subjects';
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📚';
$pageHeading  = 'Subjects';
$pageSubtitle = 'Browse the KICD curriculum by subject.';
$pageActions  = '<a class="btn btn-primary" href="curriculum.php">+ Add subject</a>';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <form method="get" action="subjects.php" class="toolbar">
        <div class="field field-search">
            <label for="subjects-search">Search</label>
            <input type="search" id="subjects-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name, code or strand…">
        </div>
        <button type="submit" class="btn">Search</button>
        <?php if ($q !== ''): ?>
            <a class="btn" href="subjects.php">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($subjects): ?>
        <div class="card-grid">
            <?php foreach ($subjects as $subject): ?>
                <?php
                    $topicN = (int) ($topicCounts[$subject['id']] ?? 0);
                    $lessonN = (int) ($lessonCounts[$subject['id']] ?? 0);
                ?>
                <a class="card subject-card" href="subject.php?id=<?= (int) $subject['id'] ?>">
                    <span class="subject-code"><?= htmlspecialchars($subject['code']) ?></span>
                    <h3><?= htmlspecialchars($subject['name']) ?></h3>
                    <p class="muted"><?= htmlspecialchars($subject['grade_level']) ?> · Strand: <?= htmlspecialchars($subject['strand']) ?></p>
                    <p class="muted"><?= $topicN ?> topic<?= $topicN === 1 ? '' : 's' ?> · <?= $lessonN ?> of your lessons</p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted"><?= $q !== '' ? 'No subjects match your search.' : 'No subjects yet.' ?></p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
