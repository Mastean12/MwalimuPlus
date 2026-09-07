<?php
/** Curriculum admin: add a subject or topic that isn't in the seeded KICD catalogue. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();
$subjects = $pdo->query('SELECT id, code, name FROM subjects ORDER BY name')->fetchAll();
$csrf = csrf_token();

$pageTitle    = 'Curriculum admin';
$activeNav    = 'admin-curriculum';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '🛠️';
$pageHeading  = 'Curriculum admin';
$pageSubtitle = 'Add a subject or topic that is not in the seeded KICD catalogue.';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Curriculum management', 'href' => 'superadmin-curriculum.php'],
    ['label' => 'Add subject / topic'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel" id="add-subject">
    <h2>Add a subject</h2>
    <p class="muted">Missing a subject from the catalogue? Add it here, then add its topics below.</p>

    <form id="subject-form" class="generate-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <label>Code
            <input type="text" name="code" maxlength="10" required placeholder="e.g. CHEM">
        </label>
        <label>Name
            <input type="text" name="name" maxlength="100" required placeholder="e.g. Chemistry">
        </label>
        <label>Strand
            <input type="text" name="strand" maxlength="190" required placeholder="e.g. Matter and Materials">
        </label>
        <label>Grade level
            <input type="text" name="grade_level" maxlength="20" value="Grade 10">
        </label>
        <label>Curriculum PDF (optional, 10 MB max)
            <input type="file" name="pdf" accept="application/pdf">
        </label>
        <p class="field-hint">Grounds lesson and scheme generation for every topic under this subject, until a topic has its own source.</p>
        <button type="submit" class="btn btn-primary" id="subject-btn">Add subject</button>
    </form>
    <div id="subject-result" hidden></div>
</section>

<section class="panel" id="add-topic">
    <h2>Add a topic</h2>
    <p class="muted">
        Paste the curriculum design text for this topic so lesson and scheme generation can cite it.
        Leave it blank to add the topic now and fill this in later — generation will say
        "not supported" for it until then.
    </p>

    <?php if ($subjects === []): ?>
        <p class="muted">Add a subject above first.</p>
    <?php else: ?>
        <form id="topic-form" class="generate-form">
            <input type="hidden" id="topic-csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label>Subject
                <select id="topic-subject" required>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?> (<?= htmlspecialchars($subject['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Topic name
                <input type="text" id="topic-name" maxlength="190" required placeholder="e.g. Acids and bases">
            </label>
            <label>Strand (optional — defaults to the subject's strand)
                <input type="text" id="topic-strand" maxlength="190" placeholder="e.g. Matter and Materials">
            </label>
            <label>Curriculum source text (optional)
                <textarea id="topic-source" rows="8" placeholder="Paste the KICD design pages for this topic…"></textarea>
            </label>
            <button type="submit" class="btn btn-primary" id="topic-btn">Add topic</button>
        </form>
        <div id="topic-result" hidden></div>
    <?php endif; ?>
</section>

<script src="assets/js/curriculum.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
