<?php
/** Scheme page: renders a saved scheme of work as the KICD CBC grid. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: schemes.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT sc.*, s.name AS subject_name
       FROM schemes sc
       JOIN subjects s ON s.id = sc.subject_id
      WHERE sc.id = ? AND sc.user_id = ?'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$scheme = $stmt->fetch();

if (!$scheme) {
    header('Location: schemes.php');
    exit;
}

$payload = $scheme['payload'] !== null ? json_decode($scheme['payload'], true) : null;
$rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
$isUnknown = $scheme['status'] === 'UNKNOWN' || $rows === [];

/** Renders a scalar or a list value as an HTML fragment. */
function scheme_cell($value): string
{
    if (is_array($value)) {
        $items = array_filter(array_map('strval', $value), static fn ($v) => trim($v) !== '');
        if ($items === []) {
            return '';
        }
        return '<ul>' . implode('', array_map(
            static fn ($v) => '<li>' . htmlspecialchars($v) . '</li>',
            $items
        )) . '</ul>';
    }
    return htmlspecialchars((string) $value);
}

$pageTitle    = $scheme['title'];
$activeNav    = 'schemes';
$showHeader   = true;
$pageIcon     = '🗓️';
$pageHeading  = $scheme['title'];
$pageSubtitle = $scheme['subject_name'] . ' · Term ' . (int) $scheme['term'] . ' · ' . count($rows) . ' lessons';
$breadcrumbs  = [
    ['label' => 'Schemes of work', 'href' => 'schemes.php'],
    ['label' => $scheme['title']],
];
$pageActions  = '<button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>'
    . '<a class="btn" href="schemes.php">Back to schemes</a>';
require __DIR__ . '/includes/header.php';
?>
<p>
    <span class="badge badge-<?= strtolower($scheme['status']) ?>"><?= htmlspecialchars($scheme['status']) ?></span>
</p>
<p class="disclosure">This is an AI assistant. It can be wrong. The teacher makes the final decision.</p>

<?php if ($isUnknown || $payload === null): ?>
    <section class="panel panel-sijui">
        <h2>Not supported by the curriculum source</h2>
        <p>I can't build a scheme for this from the curriculum design I have. Check the KICD office or the Ministry of Education for guidance.</p>
    </section>
<?php else: ?>
    <?php $kiq = $payload['key_inquiry_questions'] ?? []; ?>
    <?php if (is_array($kiq) && $kiq !== []): ?>
        <section class="panel">
            <h2>Key inquiry questions</h2>
            <ul>
                <?php foreach ($kiq as $question): ?>
                    <li><?= htmlspecialchars((string) $question) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php $citations = $payload['citations'] ?? []; ?>
    <?php if (is_array($citations) && $citations !== []): ?>
        <p class="citations">Sources: <?= htmlspecialchars(implode(', ', array_map('strval', $citations))) ?></p>
    <?php endif; ?>

    <div class="table-scroll">
        <table class="table scheme-table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Lesson</th>
                    <th>Strand</th>
                    <th>Sub-strand</th>
                    <th>Specific learning outcomes</th>
                    <th>Key inquiry question(s)</th>
                    <th>Learning experiences</th>
                    <th>Learning resources</th>
                    <th>Assessment methods</th>
                    <th>Reflection</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int) ($row['week'] ?? 0) ?></td>
                    <td><?= (int) ($row['lesson'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($row['strand'] ?? $scheme['subject_name'])) ?></td>
                    <td>
                        <a href="subject.php?id=<?= (int) $scheme['subject_id'] ?>#topics"><?= htmlspecialchars((string) ($row['sub_strand'] ?? '')) ?></a>
                    </td>
                    <td><?= scheme_cell($row['specific_outcomes'] ?? '') ?></td>
                    <td><?= htmlspecialchars((string) ($row['key_inquiry_question'] ?? '')) ?></td>
                    <td><?= scheme_cell($row['learning_experiences'] ?? '') ?></td>
                    <td><?= scheme_cell($row['learning_resources'] ?? '') ?></td>
                    <td><?= htmlspecialchars((string) ($row['assessment'] ?? '')) ?></td>
                    <td></td>
                    <td><?= htmlspecialchars((string) ($row['reference'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="print-actions">
    <button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="schemes.php">Back to schemes</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
