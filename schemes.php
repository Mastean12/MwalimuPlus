<?php
/** Schemes of work: list saved schemes and start a new grounded generation. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();

$stmt = $pdo->prepare(
    'SELECT sc.id, sc.title, sc.term, sc.status, sc.is_hidden, sc.created_at, s.name AS subject_name
       FROM schemes sc
       JOIN subjects s ON s.id = sc.subject_id
      WHERE sc.user_id = ?
      ORDER BY sc.created_at DESC'
);
$stmt->execute([$userId]);
$schemes = $stmt->fetchAll();

$pageTitle    = 'Schemes of work';
$activeNav    = 'schemes';
$showHeader   = true;
$pageIcon     = '🗓️';
$pageHeading  = 'Schemes of work';
$pageSubtitle = 'Generate a KICD scheme of work for a strand, grounded only in the design on the server.';
$pageActions  = '<button type="button" class="btn btn-accent" data-toggle="new-scheme">＋ New scheme</button>';
require __DIR__ . '/includes/header.php';
?>
<section class="glass-panel" id="new-scheme" hidden style="margin-bottom: 2rem">
    <h2>New scheme of work</h2>
    <p class="muted">AI output can be wrong — the teacher makes the final decision. Every row cites the design page it came from.</p>

    <form id="scheme-form" class="generate-form">
        <label>Subject
            <select id="scheme-subject" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= htmlspecialchars($subject['name']) ?>"><?= htmlspecialchars($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Term
            <select id="scheme-term">
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </label>

        <label>Lessons per week
            <input type="number" id="scheme-lpw" value="4" min="1" max="10" step="1">
        </label>

        <label>Start at week
            <input type="number" id="scheme-start" value="1" min="1" max="52" step="1">
        </label>

        <label>Focus
            <textarea id="scheme-focus" rows="2" required
                placeholder="e.g. spend an extra lesson on completing the square"></textarea>
        </label>

        <label>Additional details (optional)
            <textarea id="scheme-details" rows="2"
                placeholder="e.g. class has no graphing calculators; revising for a CAT in week 4"></textarea>
        </label>

        <button type="submit" class="btn btn-accent" id="scheme-btn">Generate scheme</button>
    </form>

    <div id="scheme-result" hidden></div>

    <div id="scheme-preview" hidden>
        <h2>Preview</h2>
        <p class="muted">Nothing is saved yet — edit any field below, then save when it looks right.</p>
        <div id="scheme-preview-body"></div>
        <div class="scheme-preview-actions">
            <button type="button" class="btn" id="scheme-discard-btn">Discard</button>
            <button type="button" class="btn btn-primary" id="scheme-save-btn">Save scheme</button>
        </div>
    </div>
</section>

<section class="panel" style="background: transparent; border: none; box-shadow: none; padding: 0;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
        <h2 style="margin-bottom: 0;">Saved schemes</h2>
        <div class="layout-toggle" style="display: flex; gap: .25rem; background: var(--surface); padding: .25rem; border-radius: var(--radius-sm); border: 1px solid var(--line);">
            <button type="button" class="btn btn-small btn-outline" id="toggle-list" style="margin: 0; border: none;" title="List View">
                <span aria-hidden="true">☰</span>
            </button>
            <button type="button" class="btn btn-small btn-outline" id="toggle-grid" style="margin: 0; border: none;" title="Grid View">
                <span aria-hidden="true">⊞</span>
            </button>
        </div>
    </div>
    <?php if ($schemes): ?>
        <div class="premium-card-list" id="schemes-container">
            <?php foreach ($schemes as $scheme): ?>
                <div class="premium-card" data-scheme-id="<?= (int) $scheme['id'] ?>">
                    <div class="premium-card-body">
                        <div class="card-main-info">
                            <h3><a href="scheme.php?id=<?= (int) $scheme['id'] ?>"><?= htmlspecialchars($scheme['title']) ?></a></h3>
                            <div class="premium-card-meta">
                                <span class="badge" style="background:var(--surface-2);"><span aria-hidden="true">📚</span> <?= htmlspecialchars($scheme['subject_name']) ?></span>
                                <span class="badge" style="background:var(--surface-2);">Term <?= (int) $scheme['term'] ?></span>
                                <span class="badge badge-<?= strtolower($scheme['status']) ?>"><?= htmlspecialchars($scheme['status']) ?></span>
                                <?php if ($scheme['is_hidden']): ?>
                                    <span class="badge" style="background:var(--surface-2);color:var(--muted)">🙈 Hidden</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--green-50);color:var(--green-700)">👁 Public</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="muted card-date" style="font-size: .85rem; margin-top: .5rem;">Created: <?= htmlspecialchars(date('d M Y, H:i', strtotime($scheme['created_at']))) ?></p>
                    </div>
                    <div class="premium-card-footer">
                        <a class="btn btn-small" href="scheme.php?id=<?= (int) $scheme['id'] ?>">Edit</a>
                        <button type="button"
                                class="btn btn-small <?= $scheme['is_hidden'] ? '' : 'btn-outline' ?>"
                                data-hide-scheme="<?= (int) $scheme['id'] ?>"
                                data-is-hidden="<?= (int) $scheme['is_hidden'] ?>">
                            <?= $scheme['is_hidden'] ? '👁 Unhide' : '🙈 Hide' ?>
                        </button>
                        <button type="button" class="btn btn-small btn-link-danger" data-delete-scheme="<?= (int) $scheme['id'] ?>">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">No schemes yet — use <strong>New scheme</strong> to generate your first one.</p>
    <?php endif; ?>
</section>

<script src="assets/js/scheme.js?v=<?= time() ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
