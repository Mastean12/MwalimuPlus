<?php
/** Topic page: shows the topic plus a grounded lesson-generation form. */

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
    'SELECT t.id, t.name, t.strand, t.source_file, s.id AS subject_id, s.name AS subject_name
       FROM topics t
       JOIN subjects s ON s.id = t.subject_id
      WHERE t.id = ?'
);
$stmt->execute([$id]);
$topic = $stmt->fetch();

if (!$topic) {
    header('Location: dashboard.php');
    exit;
}

// Existing lessons for this topic (read-only list under the generator).
$stmt = $pdo->prepare(
    'SELECT id, title, status, created_at FROM lessons WHERE topic_id = ? AND user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$id, (int) $_SESSION['user_id']]);
$existing = $stmt->fetchAll();

$pageTitle    = $topic['name'];
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📝';
$pageHeading  = $topic['name'];
$pageSubtitle = 'Strand: ' . $topic['strand'];
$breadcrumbs  = [
    ['label' => 'Subjects', 'href' => 'subjects.php'],
    ['label' => $topic['subject_name'], 'href' => 'subject.php?id=' . (int) $topic['subject_id']],
    ['label' => $topic['name']],
];
$pageActions  = '<button type="button" class="btn btn-accent" id="open-gen-form-modal-btn">✨ Generate lesson</button>';
require __DIR__ . '/includes/header.php';
?>

<!-- Generate Lesson Form Modal Pop-Up -->
<div class="modal-backdrop" id="generate-lesson-form-modal" style="display: none;">
    <div class="modal premium-modal" style="max-width: 520px; width: 92%;">
        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="font-size: 1.5rem;">✨</span>
                <div>
                    <h2 style="margin: 0; font-size: 1.2rem;">Generate Lesson Plan</h2>
                    <p class="muted" style="margin: 0; font-size: 0.8rem;"><?= htmlspecialchars($topic['subject_name']) ?> · <?= htmlspecialchars($topic['name']) ?></p>
                </div>
            </div>
            <button type="button" class="btn-link-danger" id="close-gen-form-modal" style="font-size: 1.3rem; border: none; background: none; cursor: pointer; color: var(--muted);" aria-label="Close">✕</button>
        </div>

        <form id="generate-form" class="generate-form">
            <input type="hidden" id="topic-name" value="<?= htmlspecialchars($topic['name']) ?>">
            <input type="hidden" id="subject-name" value="<?= htmlspecialchars($topic['subject_name']) ?>">
            <input type="hidden" id="strand-name" value="<?= htmlspecialchars($topic['strand']) ?>">

            <label style="font-weight: 600; margin-top: 0;">Topic
                <input type="text" value="<?= htmlspecialchars($topic['name']) ?>" disabled style="background: var(--surface-2); color: var(--ink-2); opacity: 0.85; margin-top: 0.3rem;">
            </label>

            <label style="font-weight: 600; margin-top: 0.85rem;">Lesson duration (minutes)
                <input type="number" id="duration" value="40" min="5" max="240" step="5" style="margin-top: 0.3rem;">
            </label>

            <label style="font-weight: 600; margin-top: 0.85rem;">What do you need help with?
                <textarea id="teacher-need" rows="3" placeholder="e.g. I have never taught this topic before." style="margin-top: 0.3rem;"></textarea>
            </label>

            <label style="font-weight: 600; margin-top: 0.85rem;">Available resources <span class="field-hint">(comma-separated)</span>
                <input type="text" id="resources" value="chalkboard, chalk" placeholder="chalkboard, chalk" style="margin-top: 0.3rem;">
            </label>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
                <button type="button" class="btn" id="cancel-gen-form-modal">Cancel</button>
                <button type="submit" class="btn btn-accent" id="generate-btn" style="background: linear-gradient(135deg, var(--green-600), var(--green-500)); color: #fff; border: none; font-weight: 700;">✨ Generate lesson</button>
            </div>
        </form>

        <div id="generate-result" hidden></div>
    </div>
</div>

<section class="panel">
    <h2>Generated for this topic</h2>
    <?php if ($existing): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($existing as $lesson): ?>
                <tr>
                    <td><a href="lesson.php?id=<?= (int) $lesson['id'] ?>"><?= htmlspecialchars($lesson['title']) ?></a></td>
                    <td><span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span></td>
                    <td><?= htmlspecialchars($lesson['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No lessons generated for this topic yet.</p>
    <?php endif; ?>
</section>

<!-- Generation Modal -->
<div class="modal-backdrop" id="generation-modal" style="display: none;">
    <div class="modal premium-modal" style="max-width: 400px; width: 90%;">
        <div class="modal-body gen-modal-content">
            
            <!-- State: Generating -->
            <div class="gen-state active" id="gen-state-loading">
                <div class="gen-icon-container">
                    <div class="gen-icon-bg"></div>
                    <div class="gen-icon-core">✨</div>
                </div>
                <h2 style="margin: 0; font-size: 1.5rem;">AI is working...</h2>
                <p class="gen-message">Structuring your KICD lesson plan based on the curriculum design.</p>
                <button type="button" class="btn btn-outline" style="margin-top: 1.25rem; width: 100%;" id="gen-cancel-btn">Cancel</button>
            </div>
            
            <!-- State: Success -->
            <div class="gen-state gen-state-success" id="gen-state-success">
                <div class="gen-icon-container">
                    <div class="gen-icon-core">✓</div>
                </div>
                <h2 style="margin: 0; font-size: 1.5rem; color: var(--green-700);">Success!</h2>
                <p class="gen-message">Lesson generated successfully. Redirecting you...</p>
            </div>
            
            <!-- State: Error -->
            <div class="gen-state gen-state-error" id="gen-state-error">
                <div class="gen-icon-container">
                    <div class="gen-icon-core">!</div>
                </div>
                <h2 style="margin: 0; font-size: 1.5rem; color: #b91c1c;">Generation Failed</h2>
                <div class="gen-error-text" id="gen-error-message"></div>
                <button type="button" class="btn btn-outline" style="margin-top: 1.5rem; width: 100%;" id="gen-close-btn">Close and try again</button>
            </div>

        </div>
    </div>
</div>

<script src="assets/js/lesson.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
