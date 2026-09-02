<?php
/** Scheme page: the KICD CBC grid plus teacher-added learning materials. */

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

// Teacher-added materials, split into scheme-level and per-row.
$resStmt = $pdo->prepare(
    'SELECT id, row_key, kind, label, url, file_name FROM scheme_resources
      WHERE scheme_id = ? ORDER BY id'
);
$resStmt->execute([$id]);
$schemeResources = [];
$rowResources = [];
foreach ($resStmt->fetchAll() as $r) {
    if ($r['row_key'] === '') {
        $schemeResources[] = $r;
    } else {
        $rowResources[$r['row_key']][] = $r;
    }
}

$csrf = csrf_token();

// Group lessons by week for the document-style layout, in week order.
$weeks = [];
foreach ($rows as $row) {
    $weeks[(int) ($row['week'] ?? 0)][] = $row;
}
ksort($weeks);

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

/** One material as a list item: icon, link, and an inline delete form. */
function resource_item(array $r, int $schemeId, string $csrf): string
{
    $icon = ['youtube' => '▶', 'link' => '🔗', 'pdf' => '📄'][$r['kind']] ?? '🔗';
    $href = $r['kind'] === 'pdf'
        ? 'download.php?id=' . (int) $r['id']
        : htmlspecialchars((string) $r['url']);
    $label = htmlspecialchars($r['label'] !== '' ? $r['label'] : (string) $r['url']);

    return '<li class="resource-item">'
        . '<span class="resource-icon" aria-hidden="true">' . $icon . '</span>'
        . '<a href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>'
        . '<form method="post" action="api/scheme-resource.php" class="resource-del" data-confirm>'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="scheme_id" value="' . $schemeId . '">'
        . '<input type="hidden" name="delete_id" value="' . (int) $r['id'] . '">'
        . '<button type="submit" class="btn-link-danger" aria-label="Remove material" title="Remove">✕</button>'
        . '</form>'
        . '</li>';
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
$pageActions  = (!$isUnknown && $payload !== null ? '<button class="btn" type="button" id="scheme-edit-btn">Edit</button>' : '')
    . '<button class="btn" type="button" data-rename-scheme="' . $id . '">Rename</button>'
    . '<button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>'
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

    <div id="scheme-view">
    <?php foreach ($weeks as $weekNumber => $weekRows): ?>
        <section class="panel scheme-week">
            <h2>Week <?= (int) $weekNumber ?></h2>
            <?php foreach ($weekRows as $row): ?>
                <?php $rowKey = sprintf('w%dl%d', (int) ($row['week'] ?? 0), (int) ($row['lesson'] ?? 0)); ?>
                <article class="scheme-lesson">
                    <h3>
                        Lesson <?= (int) ($row['lesson'] ?? 0) ?>
                        · <a href="subject.php?id=<?= (int) $scheme['subject_id'] ?>#topics"><?= htmlspecialchars((string) ($row['sub_strand'] ?? '')) ?></a>
                    </h3>
                    <dl class="scheme-fields">
                        <dt>Specific learning outcomes</dt>
                        <dd><?= scheme_cell($row['specific_outcomes'] ?? '') ?></dd>

                        <dt>Key inquiry question</dt>
                        <dd><?= htmlspecialchars((string) ($row['key_inquiry_question'] ?? '')) ?></dd>

                        <dt>Learning experiences</dt>
                        <dd><?= scheme_cell($row['learning_experiences'] ?? '') ?></dd>

                        <dt>Learning resources</dt>
                        <dd><?= scheme_cell($row['learning_resources'] ?? '') ?></dd>

                        <dt>Assessment methods</dt>
                        <dd><?= htmlspecialchars((string) ($row['assessment'] ?? '')) ?></dd>

                        <dt>Reflection</dt>
                        <dd><span class="scheme-reflection" aria-hidden="true"></span></dd>

                        <?php if (!empty($rowResources[$rowKey])): ?>
                            <dt>Materials</dt>
                            <dd>
                                <ul class="resource-list">
                                    <?php foreach ($rowResources[$rowKey] as $r): ?>
                                        <?= resource_item($r, $id, $csrf) ?>
                                    <?php endforeach; ?>
                                </ul>
                            </dd>
                        <?php endif; ?>
                    </dl>
                    <p class="citations">Reference: <?= htmlspecialchars((string) ($row['reference'] ?? '')) ?></p>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    </div>
    <div id="scheme-edit-container" hidden></div>

    <section class="panel" id="materials">
        <h2>Learning materials</h2>
        <p class="muted">PDFs, YouTube videos and web links you add for this scheme. These are your own — the AI never adds links.</p>

        <?php if ($schemeResources !== []): ?>
            <ul class="resource-list">
                <?php foreach ($schemeResources as $r): ?>
                    <?= resource_item($r, $id, $csrf) ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No materials added yet.</p>
        <?php endif; ?>

        <form method="post" action="api/scheme-resource.php" class="add-resource" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="scheme_id" value="<?= $id ?>">

            <label>Attach to
                <select name="row_key">
                    <option value="">Whole scheme</option>
                    <?php foreach ($rows as $row): ?>
                        <?php $k = sprintf('w%dl%d', (int) ($row['week'] ?? 0), (int) ($row['lesson'] ?? 0)); ?>
                        <option value="<?= $k ?>">Week <?= (int) ($row['week'] ?? 0) ?> · Lesson <?= (int) ($row['lesson'] ?? 0) ?><?= ($row['sub_strand'] ?? '') !== '' ? ' — ' . htmlspecialchars((string) $row['sub_strand']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="resource-type">
                <legend>Type</legend>
                <label class="radio"><input type="radio" name="kind" value="youtube" checked> YouTube</label>
                <label class="radio"><input type="radio" name="kind" value="link"> Web link</label>
                <label class="radio"><input type="radio" name="kind" value="pdf"> PDF file</label>
            </fieldset>

            <label>Label
                <input type="text" name="label" maxlength="190" required placeholder="e.g. Area model demo">
            </label>

            <label data-resource-field="url">Link
                <input type="url" name="url" placeholder="https://…">
            </label>

            <label data-resource-field="file" hidden>PDF file (10 MB max)
                <input type="file" name="file" accept="application/pdf">
            </label>

            <button type="submit" class="btn btn-primary">Add material</button>
        </form>
    </section>
<?php endif; ?>

<div class="print-actions">
    <button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="schemes.php">Back to schemes</a>
</div>

<script>
window.MWALIMU_SCHEME_ID = <?= $id ?>;
<?php if (!$isUnknown && $payload !== null): ?>
window.MWALIMU_SCHEME_DATA = <?= json_encode([
    'key_inquiry_questions' => $payload['key_inquiry_questions'] ?? [],
    'rows' => $rows,
    'citations' => $payload['citations'] ?? [],
]) ?>;
<?php endif; ?>
</script>
<script src="assets/js/scheme.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
