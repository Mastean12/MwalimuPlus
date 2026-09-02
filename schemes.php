<?php
/** Schemes of work: list saved schemes and start a new grounded generation. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();

$stmt = $pdo->prepare(
    'SELECT sc.id, sc.title, sc.term, sc.status, sc.created_at, s.name AS subject_name
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
$pageActions  = '<button type="button" class="btn btn-primary" data-toggle="new-scheme">＋ New scheme</button>';
require __DIR__ . '/includes/header.php';
?>
<section class="panel" id="new-scheme" hidden>
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

        <label>Focus (optional)
            <textarea id="scheme-focus" rows="2"
                placeholder="e.g. spend an extra lesson on completing the square"></textarea>
        </label>

        <button type="submit" class="btn btn-primary" id="scheme-btn">Generate scheme</button>
    </form>

    <div id="scheme-result" hidden></div>
</section>

<section class="panel">
    <h2>Saved schemes</h2>
    <?php if ($schemes): ?>
        <table class="table">
            <thead>
                <tr><th>Title</th><th>Subject</th><th>Term</th><th>Status</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($schemes as $scheme): ?>
                <tr>
                    <td><a href="scheme.php?id=<?= (int) $scheme['id'] ?>"><?= htmlspecialchars($scheme['title']) ?></a></td>
                    <td><?= htmlspecialchars($scheme['subject_name']) ?></td>
                    <td><?= (int) $scheme['term'] ?></td>
                    <td><span class="badge badge-<?= strtolower($scheme['status']) ?>"><?= htmlspecialchars($scheme['status']) ?></span></td>
                    <td><?= htmlspecialchars($scheme['created_at']) ?></td>
                    <td><button type="button" class="btn btn-link-danger" data-delete-scheme="<?= (int) $scheme['id'] ?>">Delete</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No schemes yet — use <strong>New scheme</strong> to generate your first one.</p>
    <?php endif; ?>
</section>

<script src="assets/js/scheme.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
