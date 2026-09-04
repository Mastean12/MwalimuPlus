<?php
/**
 * Turns a saved lesson row (+ decoded payload) into PDF bytes for WhatsApp.
 *
 * Renders the same content as lesson-body.php but with self-contained,
 * printer-safe markup, then hands it to Dompdf. Returns null when the payload
 * is empty or when the vendor autoloader (composer install) is unavailable.
 *
 * Requires the autoloader; safe to include from CLI for testing.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/wasender.php';

/** HTML-escapes a scalar for the PDF template. */
function pdf_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** One <li> per entry; non-array prose becomes a single paragraph. */
function pdf_items_html(string $label, $items): string
{
    if (empty($items)) {
        return '';
    }

    $body = is_array($items)
        ? '<ul>' . implode('', array_map(
            static fn ($item) => '<li>' . pdf_esc(is_array($item) ? json_encode($item) : $item) . '</li>',
            array_values($items)
        )) . '</ul>'
        : '<p>' . pdf_esc($items) . '</p>';

    return '<h2>' . pdf_esc($label) . '</h2>' . $body;
}

/**
 * Renders the lesson PDF. Expects a full lessons row (id, title, subject_name,
 * topic_name, duration_minutes, status) plus its decoded payload.
 */
function lesson_pdf_bytes(array $lesson, ?array $payload): ?string
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if ($payload === null || !is_file($autoload)) {
        return null;
    }

    require_once $autoload;

    $sections = '';

    $citations = $payload['citations'] ?? [];
    if (is_array($citations) && $citations !== []) {
        $sections .= '<p class="citations">Sources: ' . pdf_esc(implode(', ', $citations)) . '</p>';
    }

    $blocks = [
        ['Learning objectives', $payload['objectives'] ?? null],
        ['Prerequisites', $payload['prerequisites'] ?? null],
        ['Teacher explanation', $payload['teacher_explanation'] ?? null],
        ['Examples', $payload['examples'] ?? null],
        ['Board plan', $payload['board_plan'] ?? null],
        ['Suggested teacher questions', $payload['teacher_questions'] ?? null],
        ['Common misconceptions', $payload['common_misconceptions'] ?? null],
        ['Quick check', $payload['quick_check'] ?? null],
        ['Teacher notes', $payload['teacher_notes'] ?? null],
    ];

    foreach ($blocks as [$label, $content]) {
        if (empty($content)) {
            continue;
        }
        if ($label === 'Board plan') {
            $sections .= '<h2>' . pdf_esc($label) . '</h2><pre>' . pdf_esc($content) . '</pre>';
            continue;
        }
        $sections .= pdf_items_html($label, $content);
    }

    $status = $lesson['status'] ?? 'SUPPORTED';
    $title = pdf_esc($lesson['title'] ?? 'Lesson plan');
    $subject = pdf_esc($lesson['subject_name'] ?? '');
    $topic = pdf_esc($lesson['topic_name'] ?? '');
    $duration = (int) ($lesson['duration_minutes'] ?? 0);
    $statusHtml = pdf_esc($status);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #1c2b23; line-height: 1.45; }
    h1 { font-size: 17pt; margin: 0 0 4pt; color: #12452c; }
    h2 { font-size: 12pt; margin: 14pt 0 4pt; color: #12452c; border-bottom: 0.6pt solid #cfe0d6; padding-bottom: 2pt; }
    p  { margin: 4pt 0; }
    ul, ol { margin: 4pt 0 4pt 0; padding-left: 16pt; }
    li { margin: 2pt 0; }
    .meta { color: #5c6b62; font-size: 9.5pt; margin: 0 0 2pt; }
    .status { display: inline-block; padding: 1pt 6pt; border-radius: 8pt; font-size: 8.5pt;
              background: #eef7f1; color: #1f6f43; border: 0.6pt solid #bfe0cd; }
    .disclosure { color: #5c6b62; font-size: 9pt; }
    pre { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; white-space: pre-wrap;
          background: #f6f9f7; border: 0.6pt solid #dfe9e3; border-radius: 4pt; padding: 8pt; }
    .footer { margin-top: 18pt; padding-top: 6pt; border-top: 0.6pt solid #dfe9e3;
              color: #8a948e; font-size: 8.5pt; }
</style>
</head>
<body>
    <h1>{$title}</h1>
    <p class="meta">{$subject} &middot; {$topic} &middot; {$duration} min</p>
    <p class="status">{$statusHtml}</p>
    <p class="disclosure">AI-assisted lesson plan. The teacher makes the final decision before use.</p>
    {$sections}
    <p class="footer">Prepared with MwalimuPlus &middot; https://mwalimuplus.ai</p>
</body>
</html>
HTML;

    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/** A filesystem-safe PDF name derived from the lesson title. */
function lesson_pdf_filename(array $lesson): string
{
    $slug = strtolower(trim((string) ($lesson['title'] ?? 'lesson')));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'lesson';
    $slug = trim((string) $slug, '-');
    $slug = $slug !== '' ? $slug : 'lesson';

    return 'lesson-' . substr($slug, 0, 70) . '.pdf';
}
