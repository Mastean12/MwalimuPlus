<?php
/** Subject page: lists a subject's KICD strand and its topics. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id, code, name, strand, grade_level FROM subjects WHERE id = ?');
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name FROM topics WHERE subject_id = ? ORDER BY name'
);
$stmt->execute([$id]);
$topics = $stmt->fetchAll();

// Topics this teacher has already scheduled, from their saved schemes of work
// for this subject. Matched by name against each scheme row's sub_strand —
// schemes don't store a topic_id, only the AI-generated sub_strand text.
$schemeStmt = $pdo->prepare(
    "SELECT term, payload FROM schemes WHERE user_id = ? AND subject_id = ? AND status <> 'UNKNOWN'"
);
$schemeStmt->execute([(int) $_SESSION['user_id'], $id]);

$scheduledTerms = [];
foreach ($schemeStmt->fetchAll() as $scheme) {
    $schemePayload = $scheme['payload'] !== null ? json_decode($scheme['payload'], true) : null;
    $schemeRows = is_array($schemePayload['rows'] ?? null) ? $schemePayload['rows'] : [];
    foreach ($schemeRows as $row) {
        $subStrand = trim((string) ($row['sub_strand'] ?? ''));
        if ($subStrand === '') {
            continue;
        }
        $scheduledTerms[mb_strtolower($subStrand)][(int) $scheme['term']] = true;
    }
}

$pageTitle    = $subject['name'];
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📚';
$pageHeading  = $subject['name'];
$pageSubtitle = $subject['code'] . ' · ' . $subject['grade_level'] . ' · Strand: ' . $subject['strand'];
$breadcrumbs  = [
    ['label' => 'Subjects', 'href' => 'subjects.php'],
    ['label' => $subject['name']],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h2>Topics</h2>
    <?php if ($topics): ?>
        <ul class="topic-list">
            <?php foreach ($topics as $topic): ?>
                <?php $terms = array_keys($scheduledTerms[mb_strtolower(trim($topic['name']))] ?? []); ?>
                <?php sort($terms); ?>
                <li>
                    <a href="topic.php?id=<?= (int) $topic['id'] ?>">
                        <?= htmlspecialchars($topic['name']) ?>
                    </a>
                    <?php if ($terms): ?>
                        <span class="badge badge-scheduled">Scheduled · Term <?= htmlspecialchars(implode(', ', $terms)) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">No topics found for this subject.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
