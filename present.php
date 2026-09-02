<?php
/**
 * Full-screen slide view of a saved lesson — for projecting in class.
 * One section per slide, keyboard / click navigation, Esc to return.
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

/** Renders a section's value as slide HTML. */
function slide_value($value, bool $mono = false): string
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
    $text = htmlspecialchars((string) $value);
    return $mono ? '<pre>' . $text . '</pre>' : '<p>' . nl2br($text) . '</p>';
}

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

// Build the slide list: title slide, then each non-empty section.
$slides = [];
$slides[] = '<div class="slide-kicker">' . htmlspecialchars($lesson['subject_name']) . ' · '
    . htmlspecialchars($lesson['topic_name']) . '</div>'
    . '<h1>' . htmlspecialchars($lesson['title']) . '</h1>'
    . '<p class="slide-sub">' . (int) $lesson['duration_minutes'] . ' minutes</p>'
    . slide_media($mediaBySection[''] ?? []);

foreach ($SECTIONS as $key => $label) {
    if (empty($payload[$key])) {
        continue;
    }
    $slides[] = '<h2>' . htmlspecialchars($label) . '</h2>'
        . slide_value($payload[$key], $key === 'board_plan')
        . slide_media($mediaBySection[$key] ?? []);
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
        :root { --green: #1f6f43; --ink: #10241a; --muted: #5c6b62; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
            background: #f4f7f4; color: var(--ink);
            display: flex; flex-direction: column;
        }
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
        .slide-kicker { text-transform: uppercase; letter-spacing: .08em; font-size: .9rem; color: var(--muted); }
        .slide-sub { color: var(--muted); }
        .slide-media { margin-top: 1.5em; display: flex; flex-wrap: wrap; gap: 1em; align-items: flex-start; }
        .slide-media img { max-width: 100%; max-height: 40vh; border-radius: 10px; border: 1px solid #dfe7e1; }
        .slide-links { width: 100%; }
        .slide-links a { color: var(--green); }
        .bar {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            padding: .6rem 1rem; background: #fff; border-top: 1px solid #dfe7e1;
        }
        .bar button, .bar a {
            font: inherit; border: 1px solid #dfe7e1; background: #fff; color: var(--ink);
            border-radius: 8px; padding: .45rem .9rem; cursor: pointer; text-decoration: none;
        }
        .bar button:disabled { opacity: .4; cursor: default; }
        .count { color: var(--muted); font-variant-numeric: tabular-nums; }
        @media print { .bar { display: none; } .slide { box-shadow: none; max-height: none; } }
    </style>
</head>
<body>
    <div class="stage">
        <?php foreach ($slides as $i => $html): ?>
            <article class="slide" data-slide<?= $i === 0 ? '' : ' hidden' ?>><?= $html ?></article>
        <?php endforeach; ?>
    </div>
    <div class="bar">
        <a href="lesson.php?id=<?= $id ?>">Exit</a>
        <span class="count"><span id="cur">1</span> / <?= count($slides) ?></span>
        <span>
            <button type="button" id="prev" disabled>◀ Prev</button>
            <button type="button" id="next"<?= count($slides) < 2 ? ' disabled' : '' ?>>Next ▶</button>
        </span>
    </div>
    <script>
        (function () {
            var slides = Array.prototype.slice.call(document.querySelectorAll('[data-slide]'));
            var cur = 0;
            var curEl = document.getElementById('cur');
            var prev = document.getElementById('prev');
            var next = document.getElementById('next');

            function show(n) {
                cur = Math.max(0, Math.min(slides.length - 1, n));
                slides.forEach(function (s, i) { s.hidden = i !== cur; });
                curEl.textContent = cur + 1;
                prev.disabled = cur === 0;
                next.disabled = cur === slides.length - 1;
                slides[cur].scrollTop = 0;
            }

            prev.addEventListener('click', function () { show(cur - 1); });
            next.addEventListener('click', function () { show(cur + 1); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') { e.preventDefault(); show(cur + 1); }
                else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); show(cur - 1); }
                else if (e.key === 'Escape') { window.location.href = 'lesson.php?id=<?= $id ?>'; }
            });
        })();
    </script>
</body>
</html>
