<?php
/**
 * Public scheme page: renders a saved scheme of work, visible to anyone.
 * Hidden schemes (is_hidden = 1) or UNKNOWN schemes redirect to browse.php.
 * No login required.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
secure_session_start();
enforce_not_in_maintenance();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: browse.php#schemes');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT sc.id, sc.title, sc.term, sc.lessons_per_week, sc.start_week, sc.status,
            sc.payload, sc.created_at, s.name AS subject_name
       FROM schemes sc
       JOIN subjects s ON s.id = sc.subject_id
      WHERE sc.id = ? AND sc.is_hidden = 0 AND sc.status <> 'UNKNOWN'"
);
$stmt->execute([$id]);
$scheme = $stmt->fetch();

if (!$scheme) {
    header('Location: browse.php#schemes');
    exit;
}

$payload  = $scheme['payload'] !== null ? json_decode($scheme['payload'], true) : null;
$rows     = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
$kiq      = is_array($payload['key_inquiry_questions'] ?? null) ? $payload['key_inquiry_questions'] : [];
$citations = is_array($payload['citations'] ?? null) ? $payload['citations'] : [];

// Group rows by week
$byWeek = [];
foreach ($rows as $row) {
    $wk = (int) ($row['week'] ?? 0);
    $byWeek[$wk][] = $row;
}
ksort($byWeek);

$pageTitle    = $scheme['title'];
$publicHeader = true;
require __DIR__ . '/includes/header.php';
?>
<p><a href="browse.php#schemes">&larr; All schemes</a></p>

<div class="page-head">
    <div class="page-head-main">
        <span class="page-head-icon" aria-hidden="true">🗓️</span>
        <div>
            <h1><?= htmlspecialchars($scheme['title']) ?></h1>
            <p class="page-head-sub">
                <?= htmlspecialchars($scheme['subject_name']) ?>
                · Term <?= (int) $scheme['term'] ?>
                · <?= count($rows) ?> lessons
                · <span class="badge badge-<?= strtolower($scheme['status']) ?>"><?= htmlspecialchars($scheme['status']) ?></span>
            </p>
        </div>
    </div>
</div>

<p class="disclosure">This is an AI-generated scheme of work grounded in KICD curriculum designs. The teacher makes the final decision — always verify before classroom use.</p>

<?php if ($kiq): ?>
<section class="panel">
    <h2>Key Inquiry Questions</h2>
    <ul>
        <?php foreach ($kiq as $q): ?>
            <li><?= htmlspecialchars($q) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php foreach ($byWeek as $wk => $weekRows): ?>
<section class="panel scheme-week">
    <h2>Week <?= (int) $wk ?></h2>
    <?php foreach ($weekRows as $row): ?>
        <article class="scheme-lesson">
            <h3>Lesson <?= (int) ($row['lesson'] ?? 0) ?></h3>
            <table class="table">
                <tbody>
                    <?php if (!empty($row['sub_strand'])): ?>
                    <tr><th>Sub strand</th><td><?= htmlspecialchars($row['sub_strand']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($row['specific_outcomes'])): ?>
                    <tr><th>Specific outcomes</th>
                        <td><ul><?php foreach ((array)$row['specific_outcomes'] as $o): ?>
                            <li><?= htmlspecialchars($o) ?></li>
                        <?php endforeach; ?></ul></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($row['key_inquiry_question'])): ?>
                    <tr><th>Key inquiry question</th><td><?= htmlspecialchars($row['key_inquiry_question']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($row['learning_experiences'])): ?>
                    <tr><th>Learning experiences</th>
                        <td><ul><?php foreach ((array)$row['learning_experiences'] as $le): ?>
                            <li><?= htmlspecialchars($le) ?></li>
                        <?php endforeach; ?></ul></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($row['learning_resources'])): ?>
                    <tr><th>Learning resources</th>
                        <td><ul><?php foreach ((array)$row['learning_resources'] as $lr): ?>
                            <li><?= htmlspecialchars($lr) ?></li>
                        <?php endforeach; ?></ul></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($row['assessment'])): ?>
                    <tr><th>Assessment methods</th><td><?= htmlspecialchars($row['assessment']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($row['reference'])): ?>
                    <tr><th>Reference</th><td><?= htmlspecialchars($row['reference']) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    <?php endforeach; ?>
</section>
<?php endforeach; ?>

<?php if ($citations): ?>
<section class="panel">
    <h2>Citations</h2>
    <ul>
        <?php foreach ($citations as $cite): ?>
            <li><?= htmlspecialchars($cite) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
