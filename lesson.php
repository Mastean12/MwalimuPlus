<?php
/** Lesson page: renders a saved lesson and offers a print/save view. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT l.*, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.id = ? AND l.user_id = ?'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: dashboard.php');
    exit;
}

$payload = $lesson['payload'] !== null ? json_decode($lesson['payload'], true) : null;
$isUnknown = $lesson['status'] === 'UNKNOWN';

// Teacher-added media, grouped by the section it is attached to.
$resStmt = $pdo->prepare(
    'SELECT id, section, kind, label, url, file_name FROM lesson_resources
      WHERE lesson_id = ? ORDER BY id'
);
$resStmt->execute([$id]);
$mediaBySection = [];
foreach ($resStmt->fetchAll() as $r) {
    $mediaBySection[$r['section']][] = $r;
}
$csrf = csrf_token();

// Existing AI flashcards + Q&A for this lesson, if any.
$studyStmt = $pdo->prepare('SELECT status, payload FROM lesson_study_sets WHERE lesson_id = ?');
$studyStmt->execute([$id]);
$studyRow = $studyStmt->fetch();
$study = ($studyRow && $studyRow['payload'] !== null) ? json_decode($studyRow['payload'], true) : null;

$LESSON_SECTION_LABELS = [
    '' => 'Whole lesson',
    'objectives' => 'Learning objectives',
    'prerequisites' => 'Prerequisites',
    'teacher_explanation' => 'Teacher explanation',
    'examples' => 'Examples',
    'board_plan' => 'Board plan',
    'teacher_questions' => 'Suggested teacher questions',
    'common_misconceptions' => 'Common misconceptions',
    'quick_check' => 'Quick check',
    'teacher_notes' => 'Teacher notes',
];

/** One media item: icon or thumbnail, link, and an inline delete form. */
function media_item(array $r, int $lessonId, string $csrf): string
{
    $isUpload = $r['kind'] === 'pdf' || $r['kind'] === 'image';
    $href = $isUpload ? 'download.php?lr=' . (int) $r['id'] : htmlspecialchars((string) $r['url']);
    $label = htmlspecialchars($r['label'] !== '' ? $r['label'] : (string) $r['url']);
    $icon = ['youtube' => '▶', 'link' => '🔗', 'pdf' => '📄', 'image' => '🖼'][$r['kind']] ?? '🔗';

    $lead = $r['kind'] === 'image'
        ? '<a href="' . $href . '" target="_blank" rel="noopener"><img class="resource-thumb" src="' . $href . '" alt="' . $label . '"></a>'
        : '<span class="resource-icon" aria-hidden="true">' . $icon . '</span>';

    return '<li class="resource-item">'
        . $lead
        . '<a href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>'
        . '<form method="post" action="api/lesson-resource.php" class="resource-del" data-confirm>'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="lesson_id" value="' . $lessonId . '">'
        . '<input type="hidden" name="delete_id" value="' . (int) $r['id'] . '">'
        . '<button type="submit" class="btn-link-danger" aria-label="Remove" title="Remove">✕</button>'
        . '</form>'
        . '</li>';
}

$pageTitle    = $lesson['title'];
$activeNav    = 'lessons';
$showHeader   = true;
$pageIcon     = '📄';
$pageHeading  = $lesson['title'];
$pageSubtitle = $lesson['subject_name'] . ' · ' . $lesson['topic_name'] . ' · ' . (int) $lesson['duration_minutes'] . ' min';
$breadcrumbs  = [
    ['label' => 'Recent lessons', 'href' => 'dashboard.php#recent'],
    ['label' => $lesson['subject_name'], 'href' => 'subject.php?id=' . (int) $lesson['subject_id']],
    ['label' => $lesson['topic_name'], 'href' => 'topic.php?id=' . (int) $lesson['topic_id']],
    ['label' => $lesson['title']],
];
$pageActions  = ($isUnknown || $payload === null ? '' :
        '<button class="btn btn-primary" type="button" id="listen-lesson-btn" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none; color: #fff;"><span class="listen-icon">🔊</span> <span class="listen-label">Listen</span></button>'
        . ' <a class="btn btn-primary" href="present.php?id=' . $id . '">▶ Present</a>'
        . ' <a class="btn" href="ask.php?lesson=' . $id . '">💬 Ask the AI</a>')
    . ' <button class="btn" type="button" onclick="window.print()">Print / save as PDF</button>'
    . ' <button class="btn btn-primary" type="button" id="share-lesson-toggle" aria-expanded="false" aria-controls="share-panel">Share to parent</button>'
    . ' <a class="btn" href="topic.php?id=' . (int) $lesson['topic_id'] . '">Back to topic</a>';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/lesson-body.php';
?>
<?php if (!$isUnknown && $payload !== null): ?>
    <section class="panel" id="lesson-media">
        <h2>Media &amp; resources</h2>
        <p class="muted">Photos, YouTube videos, PDFs and links you add for this lesson. These are your own — the AI never adds media.</p>

        <?php
        $hasMedia = false;
        foreach ($LESSON_SECTION_LABELS as $key => $sectionLabel):
            if (empty($mediaBySection[$key])) {
                continue;
            }
            $hasMedia = true;
            ?>
            <div class="media-group">
                <h3><?= htmlspecialchars($sectionLabel) ?></h3>
                <ul class="resource-list">
                    <?php foreach ($mediaBySection[$key] as $r): ?>
                        <?= media_item($r, $id, $csrf) ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <?php if (!$hasMedia): ?>
            <p class="muted">Nothing added yet.</p>
        <?php endif; ?>

        <form method="post" action="api/lesson-resource.php" class="add-resource" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="lesson_id" value="<?= $id ?>">

            <label>Attach to <span class="field-hint">— hold Ctrl / Cmd to pick several</span>
                <select name="section[]" multiple size="6">
                    <?php foreach ($LESSON_SECTION_LABELS as $key => $sectionLabel): ?>
                        <option value="<?= htmlspecialchars($key) ?>"<?= $key === '' ? ' selected' : '' ?>><?= htmlspecialchars($sectionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="resource-type">
                <legend>Type</legend>
                <label class="radio"><input type="radio" name="kind" value="youtube" checked> YouTube</label>
                <label class="radio"><input type="radio" name="kind" value="link"> Web link</label>
                <label class="radio"><input type="radio" name="kind" value="image"> Image</label>
                <label class="radio"><input type="radio" name="kind" value="pdf"> PDF</label>
            </fieldset>

            <label>Label <span class="field-hint">— optional; used for every link you add</span>
                <input type="text" name="label" maxlength="190" placeholder="e.g. Area model photo">
            </label>

            <div data-resource-field="url">
                <span class="add-resource-label">Links</span>
                <div data-link-fields>
                    <input type="url" name="url[]" placeholder="https://…">
                    <input type="url" name="url[]" placeholder="https://…">
                </div>
                <button type="button" class="btn btn-small" data-add-link hidden>+ Add another link</button>
            </div>

            <label data-resource-field="file" hidden>File <span class="field-hint">— image (8 MB) or PDF (10 MB)</span>
                <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
            </label>

            <button type="submit" class="btn btn-primary">Add media</button>
        </form>
    </section>

    <section class="panel" id="study-set" data-lesson-id="<?= $id ?>">
        <h2>Flashcards &amp; Q&amp;A</h2>
        <p class="muted">Revision material generated from this lesson and its KICD source. AI output can be wrong — check it against the design.</p>

        <div id="study-body">
            <?php if ($study && (!empty($study['flashcards']) || !empty($study['qa']))): ?>
                <?php if (!empty($study['flashcards'])): ?>
                    <h3>Flashcards <span class="muted">— tap a card to flip</span></h3>
                    <div class="flashcard-grid">
                        <?php foreach ($study['flashcards'] as $c): ?>
                            <button type="button" class="flashcard" data-flip>
                                <span class="flashcard-face flashcard-front"><?= htmlspecialchars((string) ($c['front'] ?? '')) ?></span>
                                <span class="flashcard-face flashcard-back">
                                    <?= htmlspecialchars((string) ($c['back'] ?? '')) ?>
                                    <?php if (!empty($c['reference'])): ?><small class="citations"><?= htmlspecialchars((string) $c['reference']) ?></small><?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($study['qa'])): ?>
                    <h3>Questions &amp; answers</h3>
                    <ol class="qa-list">
                        <?php foreach ($study['qa'] as $q): ?>
                            <li>
                                <p class="qa-q"><?= htmlspecialchars((string) ($q['question'] ?? '')) ?></p>
                                <details><summary>Show answer</summary>
                                    <p><?= nl2br(htmlspecialchars((string) ($q['answer'] ?? ''))) ?>
                                    <?php if (!empty($q['reference'])): ?> <small class="citations"><?= htmlspecialchars((string) $q['reference']) ?></small><?php endif; ?></p>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">None yet.</p>
            <?php endif; ?>
        </div>

        <div id="study-result" hidden></div>
        <button type="button" class="btn btn-primary" id="study-btn">
            <?= $study ? 'Regenerate' : 'Generate flashcards &amp; Q&amp;A' ?>
        </button>
    </section>
<?php endif; ?>

<div class="print-actions">
    <button class="btn" onclick="window.print()">Print / save as PDF</button>
    <a class="btn" href="topic.php?id=<?= (int) $lesson['topic_id'] ?>">Back to topic</a>
</div>

<section class="panel share-panel" id="share-panel" hidden aria-label="Share this lesson with a parent">
    <h2>Share this lesson with a parent</h2>
    <p class="muted">Sends a PDF of <em><?= htmlspecialchars($lesson['title']) ?></em> to the parent&rsquo;s WhatsApp number.</p>
    <form id="share-form">
        <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
        <label for="share-phone">Parent&rsquo;s WhatsApp number</label>
        <input type="tel" id="share-phone" name="phone" inputmode="tel" autocomplete="tel"
               placeholder="e.g. 0712 345 678" required>
        <label for="share-message">Note to the parent (optional)</label>
        <textarea id="share-message" name="message" rows="2"
                  placeholder="e.g. Kindly review and sign the lesson notes."></textarea>
        <button type="submit" class="btn btn-primary" id="share-btn">Send PDF via WhatsApp</button>
        <p class="field-hint" id="share-status" role="status"></p>
    </form>
</section>
<script src="assets/js/share-lesson.js"></script>
<script src="assets/js/lesson.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
