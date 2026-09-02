<?php
/**
 * Full-screen slide view of a saved lesson — for projecting in class.
 * Auto-builds one slide per section; the teacher can edit / reorder / hide
 * slides and save the deck (stored in lesson_presentations).
 */

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

$payload = ($lesson && $lesson['payload'] !== null) ? json_decode($lesson['payload'], true) : null;
if (!$lesson || $payload === null || $lesson['status'] === 'UNKNOWN') {
    header('Location: lesson.php?id=' . $id);
    exit;
}

// Media grouped by section, so a slide can show its own images.
$mediaBySection = [];
$rs = $pdo->prepare('SELECT id, section, kind, label, url FROM lesson_resources WHERE lesson_id = ? ORDER BY id');
$rs->execute([$id]);
foreach ($rs->fetchAll() as $r) {
    $mediaBySection[$r['section']][] = $r;
}

$SECTIONS = [
    'objectives' => 'Learning objectives',
    'prerequisites' => 'Prerequisites',
    'teacher_explanation' => 'Teacher explanation',
    'examples' => 'Examples',
    'board_plan' => 'Board plan',
    'teacher_questions' => 'Questions to ask',
    'common_misconceptions' => 'Common misconceptions',
    'quick_check' => 'Quick check',
    'teacher_notes' => 'Teacher notes',
];

/** A payload value as an array of text lines. */
function value_lines($value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map(
            static fn ($v) => trim(is_array($v) ? json_encode($v) : (string) $v),
            $value
        ), static fn ($v) => $v !== ''));
    }
    $text = trim((string) $value);
    return $text === '' ? [] : preg_split('/\R/', $text);
}

// Auto-built deck: title slide, then each non-empty section.
$auto = [];
$auto[] = [
    'title' => (string) $lesson['title'],
    'lines' => [
        $lesson['subject_name'] . ' · ' . $lesson['topic_name'],
        (int) $lesson['duration_minutes'] . ' minutes',
    ],
    'mono' => false,
    'section' => '',
    'hidden' => false,
];
foreach ($SECTIONS as $key => $label) {
    if (empty($payload[$key])) {
        continue;
    }
    $auto[] = [
        'title' => $label,
        'lines' => value_lines($payload[$key]),
        'mono' => $key === 'board_plan',
        'section' => $key,
        'hidden' => false,
    ];
}

// A saved deck, if the teacher edited one, wins.
$saved = null;
$pstmt = $pdo->prepare('SELECT payload FROM lesson_presentations WHERE lesson_id = ?');
$pstmt->execute([$id]);
$prow = $pstmt->fetch();
if ($prow && $prow['payload'] !== null) {
    $decoded = json_decode($prow['payload'], true);
    if (is_array($decoded['slides'] ?? null) && $decoded['slides'] !== []) {
        $saved = $decoded['slides'];
    }
}
$slides = $saved ?? $auto;
$isCustom = $saved !== null;

/** Media block for a section (images inline, other links listed). */
function slide_media(array $items): string
{
    if ($items === []) {
        return '';
    }
    $html = '<div class="slide-media">';
    foreach ($items as $m) {
        if ($m['kind'] === 'image') {
            $html .= '<img src="download.php?lr=' . (int) $m['id'] . '" alt="' . htmlspecialchars((string) $m['label']) . '">';
        }
    }
    $links = array_filter($items, static fn ($m) => $m['kind'] !== 'image');
    if ($links) {
        $html .= '<ul class="slide-links">';
        foreach ($links as $m) {
            $href = $m['kind'] === 'pdf' ? 'download.php?lr=' . (int) $m['id'] : htmlspecialchars((string) $m['url']);
            $icon = $m['kind'] === 'youtube' ? '▶' : ($m['kind'] === 'pdf' ? '📄' : '🔗');
            $html .= '<li>' . $icon . ' <a href="' . $href . '" target="_blank" rel="noopener">'
                . htmlspecialchars($m['label'] !== '' ? $m['label'] : (string) $m['url']) . '</a></li>';
        }
        $html .= '</ul>';
    }
    return $html . '</div>';
}

/** Renders one slide's body (title comes from the caller). */
function slide_body(array $slide, array $mediaBySection): string
{
    $lines = array_values(array_filter(array_map('strval', $slide['lines'] ?? []), static fn ($l) => trim($l) !== ''));
    if (!empty($slide['mono'])) {
        $body = '<pre>' . htmlspecialchars(implode("\n", $lines)) . '</pre>';
    } elseif (count($lines) > 1) {
        $body = '<ul>' . implode('', array_map(
            static fn ($l) => '<li>' . htmlspecialchars($l) . '</li>',
            $lines
        )) . '</ul>';
    } else {
        $body = '<p>' . htmlspecialchars($lines[0] ?? '') . '</p>';
    }
    return $body . slide_media($mediaBySection[$slide['section'] ?? ''] ?? []);
}

$visibleCount = 0;
foreach ($slides as $s) {
    if (empty($s['hidden'])) {
        $visibleCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lesson['title']) ?> · Present</title>
    <meta name="theme-color" content="#12452c">
    <style>
        :root { --green: #1f6f43; --ink: #10241a; --muted: #5c6b62; --line: #dfe7e1; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
            background: #f4f7f4; color: var(--ink);
            display: flex; flex-direction: column;
        }
        [hidden] { display: none !important; }
        .stage { flex: 1; display: flex; align-items: center; justify-content: center; padding: 4vmin; }
        .slide {
            width: min(1100px, 92vw); max-height: 84vh; overflow-y: auto;
            background: #fff; border-radius: 16px; padding: 6vmin;
            box-shadow: 0 10px 40px rgba(16, 36, 26, .12);
        }
        .slide h1 { font-size: clamp(1.8rem, 5vw, 3rem); margin: .2em 0; }
        .slide h2 { font-size: clamp(1.4rem, 3.5vw, 2.2rem); color: var(--green); margin: 0 0 .6em; }
        .slide p, .slide li { font-size: clamp(1.05rem, 2.2vw, 1.5rem); line-height: 1.5; }
        .slide ul { margin: 0; padding-left: 1.3em; }
        .slide li { margin: .4em 0; }
        .slide pre {
            font-family: "Cascadia Mono", Consolas, monospace;
            font-size: clamp(.95rem, 1.9vw, 1.3rem); line-height: 1.6;
            background: #f4f7f4; border-radius: 10px; padding: 1em; overflow-x: auto; white-space: pre-wrap;
        }
        .slide-media { margin-top: 1.5em; display: flex; flex-wrap: wrap; gap: 1em; align-items: flex-start; }
        .slide-media img { max-width: 100%; max-height: 40vh; border-radius: 10px; border: 1px solid var(--line); }
        .slide-links { width: 100%; }
        .slide-links a { color: var(--green); }
        .bar {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: .6rem 1rem; background: #fff; border-top: 1px solid var(--line); flex-wrap: wrap;
        }
        .bar button, .bar a {
            font: inherit; border: 1px solid var(--line); background: #fff; color: var(--ink);
            border-radius: 8px; padding: .45rem .9rem; cursor: pointer; text-decoration: none;
        }
        .bar button:disabled { opacity: .4; cursor: default; }
        .bar .primary { background: var(--green); color: #fff; border-color: var(--green); }
        .count { color: var(--muted); font-variant-numeric: tabular-nums; }

        /* Edit mode */
        body.editing .stage { display: block; padding: 3vmin; }
        body.editing .slide {
            width: min(900px, 94vw); max-height: none; margin: 0 auto 1rem;
            display: block !important;
        }
        body.editing .slide[hidden] { display: block !important; opacity: .5; }
        .edit-tools { display: none; gap: .4rem; margin-bottom: .6rem; align-items: center; flex-wrap: wrap; }
        body.editing .edit-tools { display: flex; }
        .edit-tools button, .edit-tools label {
            font: inherit; font-size: .85rem; border: 1px solid var(--line); background: #fff;
            border-radius: 6px; padding: .25rem .6rem; cursor: pointer;
        }
        .edit-title, .edit-lines {
            display: none; width: 100%; font: inherit; border: 1px solid var(--line);
            border-radius: 8px; padding: .5rem .7rem;
        }
        body.editing .edit-title, body.editing .edit-lines { display: block; }
        body.editing .slide-view { display: none; }
        .edit-title { font-size: 1.3rem; font-weight: 700; margin-bottom: .5rem; }
        .edit-lines { min-height: 7rem; font-size: 1rem; line-height: 1.5; resize: vertical; }

        @media print {
            .bar, .edit-tools { display: none; }
            .slide { box-shadow: none; max-height: none; display: block !important; page-break-after: always; }
        }
    </style>
</head>
<body data-lesson-id="<?= $id ?>">
    <div class="stage">
        <?php foreach ($slides as $i => $s): ?>
            <article class="slide" data-slide
                     data-mono="<?= !empty($s['mono']) ? '1' : '0' ?>"
                     data-section="<?= htmlspecialchars((string) ($s['section'] ?? '')) ?>"
                     <?= !empty($s['hidden']) ? 'data-hidden hidden' : '' ?>
                     <?= $i === 0 ? '' : 'hidden' ?>>
                <div class="edit-tools">
                    <button type="button" data-move="-1" title="Move up">↑</button>
                    <button type="button" data-move="1" title="Move down">↓</button>
                    <label><input type="checkbox" data-hide <?= !empty($s['hidden']) ? 'checked' : '' ?>> Hide</label>
                </div>
                <div class="slide-view">
                    <?php if (($s['section'] ?? '') === ''): ?>
                        <h1><?= htmlspecialchars((string) $s['title']) ?></h1>
                    <?php else: ?>
                        <h2><?= htmlspecialchars((string) $s['title']) ?></h2>
                    <?php endif; ?>
                    <?= slide_body($s, $mediaBySection) ?>
                </div>
                <input type="text" class="edit-title" value="<?= htmlspecialchars((string) $s['title']) ?>">
                <textarea class="edit-lines" rows="6"><?= htmlspecialchars(implode("\n", array_map('strval', $s['lines'] ?? []))) ?></textarea>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="bar">
        <span>
            <a href="lesson.php?id=<?= $id ?>">Exit</a>
            <button type="button" id="edit-toggle">Edit slides</button>
        </span>
        <span class="count" data-live-mode>
            <span id="cur">1</span> / <span id="total"><?= max(1, $visibleCount) ?></span>
        </span>
        <span data-live-mode>
            <button type="button" id="prev" disabled>◀ Prev</button>
            <button type="button" id="next"<?= $visibleCount < 2 ? ' disabled' : '' ?>>Next ▶</button>
        </span>
        <span data-edit-mode hidden>
            <?php if ($isCustom): ?><button type="button" id="reset">Reset to auto</button><?php endif; ?>
            <button type="button" id="cancel">Cancel</button>
            <button type="button" class="primary" id="save">Save deck</button>
        </span>
    </div>

    <script>
    (function () {
        var body = document.body;
        var lessonId = body.getAttribute('data-lesson-id');
        var stage = document.querySelector('.stage');
        var cur = 0;
        var curEl = document.getElementById('cur');
        var totalEl = document.getElementById('total');
        var prev = document.getElementById('prev');
        var next = document.getElementById('next');

        function slides() { return Array.prototype.slice.call(stage.querySelectorAll('[data-slide]')); }
        function visible() { return slides().filter(function (s) { return !s.hasAttribute('data-hidden'); }); }

        function show(n) {
            var vis = visible();
            cur = Math.max(0, Math.min(vis.length - 1, n));
            slides().forEach(function (s) { s.hidden = true; });
            if (vis[cur]) { vis[cur].hidden = false; vis[cur].scrollTop = 0; }
            curEl.textContent = vis.length ? cur + 1 : 0;
            totalEl.textContent = Math.max(1, vis.length);
            prev.disabled = cur === 0;
            next.disabled = cur >= vis.length - 1;
        }

        prev.addEventListener('click', function () { show(cur - 1); });
        next.addEventListener('click', function () { show(cur + 1); });
        document.addEventListener('keydown', function (e) {
            if (body.classList.contains('editing')) { return; }
            if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') { e.preventDefault(); show(cur + 1); }
            else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); show(cur - 1); }
            else if (e.key === 'Escape') { window.location.href = 'lesson.php?id=' + lessonId; }
        });

        /* ---- Edit mode ---- */
        var editToggle = document.getElementById('edit-toggle');
        var saveBtn = document.getElementById('save');
        var cancelBtn = document.getElementById('cancel');
        var resetBtn = document.getElementById('reset');

        function setEditing(on) {
            body.classList.toggle('editing', on);
            document.querySelectorAll('[data-live-mode]').forEach(function (el) { el.hidden = on; });
            document.querySelectorAll('[data-edit-mode]').forEach(function (el) { el.hidden = !on; });
            editToggle.textContent = on ? 'Editing…' : 'Edit slides';
            if (!on) {
                slides().forEach(function (s) { s.hidden = true; });
                show(0);
            } else {
                slides().forEach(function (s) { s.hidden = false; });
            }
        }
        editToggle.addEventListener('click', function () { setEditing(!body.classList.contains('editing')); });
        cancelBtn.addEventListener('click', function () { window.location.reload(); });

        stage.addEventListener('click', function (e) {
            var moveBtn = e.target.closest('[data-move]');
            if (moveBtn) {
                var art = moveBtn.closest('[data-slide]');
                var dir = parseInt(moveBtn.getAttribute('data-move'), 10);
                if (dir < 0 && art.previousElementSibling) {
                    art.parentNode.insertBefore(art, art.previousElementSibling);
                } else if (dir > 0 && art.nextElementSibling) {
                    art.parentNode.insertBefore(art.nextElementSibling, art);
                }
            }
        });
        stage.addEventListener('change', function (e) {
            var hide = e.target.closest('[data-hide]');
            if (hide) {
                hide.closest('[data-slide]').toggleAttribute('data-hidden', hide.checked);
            }
        });

        function post(payload) {
            return fetch('api/lesson-presentation.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json().catch(function () { return { success: false }; }); });
        }

        saveBtn.addEventListener('click', function () {
            var deck = slides().map(function (art) {
                return {
                    title: art.querySelector('.edit-title').value,
                    lines: art.querySelector('.edit-lines').value.split('\n')
                        .map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; }),
                    hidden: art.hasAttribute('data-hidden'),
                    section: art.getAttribute('data-section') || '',
                    mono: art.getAttribute('data-mono') === '1'
                };
            });
            saveBtn.disabled = true; saveBtn.textContent = 'Saving…';
            post({ lesson_id: lessonId, slides: deck }).then(function (d) {
                if (d && d.success) { window.location.reload(); return; }
                saveBtn.disabled = false; saveBtn.textContent = 'Save deck';
                window.alert((d && d.error) || 'Could not save the deck.');
            });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!window.confirm('Discard your edits and go back to the auto-built slides?')) { return; }
                post({ lesson_id: lessonId, action: 'reset' }).then(function () { window.location.reload(); });
            });
        }

        show(0);
    })();
    </script>
</body>
</html>
