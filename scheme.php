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

// Lessons already generated from this scheme, keyed by topic_id, so each row
// can offer "Generate lesson plan" or "View lesson plan" as appropriate.
$lessonStmt = $pdo->prepare('SELECT id, topic_id FROM lessons WHERE scheme_id = ? AND user_id = ?');
$lessonStmt->execute([$id, (int) $_SESSION['user_id']]);
$lessonsByTopic = [];
foreach ($lessonStmt->fetchAll() as $l) {
    $lessonsByTopic[(int) $l['topic_id']] = $l;
}

// A row's sub_strand is AI-generated text; match it to a real topic by name
// (same best-effort approach as subject.php's "Scheduled" badge) so the
// generated lesson can be filed under the right topic_id.
$topicStmt = $pdo->prepare('SELECT id, name FROM topics WHERE subject_id = ?');
$topicStmt->execute([(int) $scheme['subject_id']]);
$topicIdByName = [];
foreach ($topicStmt->fetchAll() as $t) {
    $topicIdByName[mb_strtolower(trim($t['name']))] = (int) $t['id'];
}

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

/** One material as a styled material card. */
function resource_item(array $r, int $schemeId, string $csrf): string
{
    $kind = strtolower((string) ($r['kind'] ?? 'link'));
    $icon = ['youtube' => '▶', 'link' => '🔗', 'pdf' => '📄'][$kind] ?? '🔗';
    $kindLabel = ['youtube' => 'YouTube', 'link' => 'Web Link', 'pdf' => 'PDF Document'][$kind] ?? 'Link';
    $href = $kind === 'pdf'
        ? 'download.php?id=' . (int) $r['id']
        : htmlspecialchars((string) $r['url']);
    $label = htmlspecialchars($r['label'] !== '' ? $r['label'] : ($r['file_name'] !== '' && $r['file_name'] !== null ? $r['file_name'] : (string) $r['url']));

    return '<div class="material-card">'
        . '<div class="material-card-body">'
        . '<span class="material-kind-badge kind-' . htmlspecialchars($kind) . '"><span aria-hidden="true">' . $icon . '</span> ' . htmlspecialchars($kindLabel) . '</span>'
        . '<a href="' . $href . '" class="material-title-link" target="_blank" rel="noopener" title="' . $label . '">' . $label . '</a>'
        . '</div>'
        . '<div class="material-card-actions">'
        . '<a href="' . $href . '" class="material-action-btn" target="_blank" rel="noopener">' . ($kind === 'pdf' ? 'Download' : 'Open') . '</a>'
        . '<form method="post" action="api/scheme-resource.php" style="display:inline;" data-confirm>'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="scheme_id" value="' . $schemeId . '">'
        . '<input type="hidden" name="delete_id" value="' . (int) $r['id'] . '">'
        . '<button type="submit" class="material-del-btn" aria-label="Remove material" title="Remove material">✕</button>'
        . '</form>'
        . '</div>'
        . '</div>';
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
                            <dt>Attached Materials</dt>
                            <dd>
                                <div class="materials-cards-grid">
                                    <?php foreach ($rowResources[$rowKey] as $r): ?>
                                        <?= resource_item($r, $id, $csrf) ?>
                                    <?php endforeach; ?>
                                </div>
                            </dd>
                        <?php endif; ?>
                    </dl>
                    <p class="citations">Reference: <?= htmlspecialchars((string) ($row['reference'] ?? '')) ?></p>
                    <?php
                        $rowTopicId = $topicIdByName[mb_strtolower(trim((string) ($row['sub_strand'] ?? '')))] ?? null;
                        $rowLesson = $rowTopicId !== null ? ($lessonsByTopic[$rowTopicId] ?? null) : null;
                    ?>
                    <?php if ($rowLesson): ?>
                        <p><a class="btn btn-small" href="lesson.php?id=<?= (int) $rowLesson['id'] ?>">View lesson plan</a></p>
                    <?php else: ?>
                        <p>
                            <button type="button" class="btn btn-small" data-generate-lesson
                                data-subject="<?= htmlspecialchars($scheme['subject_name']) ?>"
                                data-topic="<?= htmlspecialchars((string) ($row['sub_strand'] ?? '')) ?>"
                                data-strand="<?= htmlspecialchars((string) ($row['strand'] ?? $scheme['subject_name'])) ?>"
                                data-teacher-need="<?= htmlspecialchars((string) ($row['key_inquiry_question'] ?? '')) ?>"
                                data-scheme-id="<?= $id ?>">Generate lesson plan</button>
                        </p>
                    <?php endif; ?>
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
            <div class="materials-cards-grid" style="margin-top: 1rem;">
                <?php foreach ($schemeResources as $r): ?>
                    <?= resource_item($r, $id, $csrf) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-materials-card">
                <div class="empty-materials-icon">📎</div>
                <h3>No attached learning materials</h3>
                <p>Upload PDF guides, reference links, or YouTube videos to attach directly to this scheme of work for easy access during teaching.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="api/scheme-resource.php" class="add-resource" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="scheme_id" value="<?= $id ?>">

            <label>Attach to <span class="field-hint">— hold Ctrl / Cmd to pick several</span>
                <select name="row_key[]" multiple size="6">
                    <option value="" selected>Whole scheme</option>
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

            <label>Label <span class="field-hint">— optional; used for every link you add</span>
                <input type="text" name="label" maxlength="190" placeholder="e.g. Area model demo">
            </label>

            <div data-resource-field="url">
                <span class="add-resource-label">Links</span>
                <div data-link-fields>
                    <input type="url" name="url[]" placeholder="https://…">
                    <input type="url" name="url[]" placeholder="https://…">
                </div>
                <button type="button" class="btn btn-small" data-add-link hidden>+ Add another link</button>
            </div>

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
