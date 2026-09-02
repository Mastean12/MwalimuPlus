<?php
/**
 * MwalimuPlus scheme-of-work generation (JSON API).
 *
 * POST /api/generate-scheme.php
 *
 * Input:
 *   {
 *     "subject": "Mathematics",
 *     "term": 1,
 *     "lessons_per_week": 4,
 *     "start_week": 1,
 *     "focus": "spend an extra lesson on completing the square"
 *   }
 *
 * Output follows the same contract as generate-lesson.php:
 *   { "success": true, "disclosure": "...", "status": "SUPPORTED|NEEDS_VERIFICATION|UNKNOWN",
 *     "scheme": {...}|null, "sijui": null|"...", "saved_id": 12 }
 *
 * Grounding: the subject's KICD strand design is loaded from content/ and injected
 * into the system prompt as SOURCES. If the corpus cannot support a scheme the API
 * returns status UNKNOWN with a sijui message and never fabricates rows.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/claude.php';
require_once __DIR__ . '/../config/session.php';
secure_session_start();

// A grounded generation call runs ~15-40s. Don't let PHP's own execution limit
// kill it before cURL's timeout does.
set_time_limit(CLAUDE_TIMEOUT_SECONDS + 30);

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

/** Always-present disclosure line. */
function disclosure(): string
{
    return 'This is an AI assistant. It can be wrong. The teacher makes the final decision.';
}

/** Validates + normalises the request payload; echoes a JSON error and exits on failure. */
function read_request(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Request body must be valid JSON.']);
        exit;
    }

    $subject = trim((string) ($input['subject'] ?? ''));
    if ($subject === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'subject is required.']);
        exit;
    }

    $term = isset($input['term']) ? (int) $input['term'] : 1;
    if ($term < 1 || $term > 3) {
        $term = 1;
    }

    $lessonsPerWeek = isset($input['lessons_per_week']) ? (int) $input['lessons_per_week'] : 4;
    if ($lessonsPerWeek < 1 || $lessonsPerWeek > 10) {
        $lessonsPerWeek = 4;
    }

    $startWeek = isset($input['start_week']) ? (int) $input['start_week'] : 1;
    if ($startWeek < 1 || $startWeek > 52) {
        $startWeek = 1;
    }

    return [
        'subject' => $subject,
        'term' => $term,
        'lessons_per_week' => $lessonsPerWeek,
        'start_week' => $startWeek,
        'focus' => trim((string) ($input['focus'] ?? '')),
    ];
}

/** Resolves a subject's strand label and its strand source file (via any topic that carries one). */
function resolve_subject_source(PDO $pdo, string $subjectName): array
{
    $stmt = $pdo->prepare('SELECT id, strand FROM subjects WHERE name = ?');
    $stmt->execute([$subjectName]);
    $subject = $stmt->fetch();
    if (!$subject) {
        return ['subject_id' => 0, 'strand' => '', 'source_file' => null];
    }

    $stmt = $pdo->prepare(
        "SELECT source_file FROM topics
          WHERE subject_id = ? AND source_file <> ''
          LIMIT 1"
    );
    $stmt->execute([(int) $subject['id']]);
    $sourceFile = $stmt->fetchColumn();

    return [
        'subject_id' => (int) $subject['id'],
        'strand' => (string) $subject['strand'],
        'source_file' => $sourceFile !== false && $sourceFile !== '' ? (string) $sourceFile : null,
    ];
}

$request = read_request();
$pdo = db();
$resolved = resolve_subject_source($pdo, $request['subject']);
$sources = $resolved['source_file'] !== null ? load_strand_source($resolved['source_file']) : '';

// Out-of-source (no corpus at all): answer with sijui, never invent a scheme.
if ($resolved['subject_id'] === 0 || trim($sources) === '') {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'scheme' => null,
        'sijui' => "I don't have a KICD strand design for {$request['subject']} Grade 10. Check the KICD office for the full design before building a scheme.",
    ]);
    exit;
}

$focusLine = $request['focus'] === '' ? '(none)' : $request['focus'];

$userPrompt = <<<PROMPT
Build a Grade 10 scheme of work for this strand using ONLY the SOURCES.

Subject: {$request['subject']}
Strand: {$resolved['strand']}
Term: {$request['term']}
Lessons per week: {$request['lessons_per_week']}
Start numbering at week: {$request['start_week']}
Teacher focus (optional): {$focusLine}

Produce one row per lesson the DESIGN PAGES define, in order. Number the lessons
into weeks: {$request['lessons_per_week']} lessons per week, the first lesson in
week {$request['start_week']}. Do not invent lessons beyond the DESIGN PAGES.

Return ONLY valid JSON matching exactly this schema:
{
  "scheme": {
    "key_inquiry_questions": [],
    "rows": [
      {
        "week": 0,
        "lesson": 0,
        "strand": "",
        "sub_strand": "",
        "specific_outcomes": [],
        "key_inquiry_question": "",
        "learning_experiences": [],
        "learning_resources": [],
        "assessment": "",
        "reference": "design p.0"
      }
    ],
    "citations": ["design p.12"]
  }
}

If the SOURCES do not lay out lessons for this strand, instead return:
{ "scheme": null }
PROMPT;

// Placeholder key? Return a clear, actionable demo message.
$key = claude_api_key();
if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'SUPPORTED',
        'scheme' => null,
        'sijui' => null,
        'demo_mode' => true,
        'message' => 'Claude API key is not configured. Set the CLAUDE_API_KEY environment variable (or the placeholder in config/claude.php) to generate a real scheme. The request was validated against the curriculum corpus.',
    ]);
    exit;
}

$system = claude_scheme_system_prompt($request['subject'], $resolved['strand'], $sources);
$raw = claude_complete($system, $userPrompt, 4000);

if ($raw === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The Claude API could not be reached or returned an error. Please try again.']);
    exit;
}

// Strip markdown fences if the model wraps the JSON.
$raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
$raw = preg_replace('/```\s*$/', '', $raw);

$decoded = json_decode($raw, true);
if (!is_array($decoded) || !array_key_exists('scheme', $decoded)) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The model returned an unexpected response. Please try again.']);
    exit;
}

$scheme = $decoded['scheme'];

// Out-of-source answer from the model: report UNKNOWN with sijui.
if ($scheme === null) {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'scheme' => null,
        'sijui' => "I can't build a scheme for that from the curriculum design I have. Check the KICD office or the Ministry of Education for guidance.",
    ]);
    exit;
}

$rows = is_array($scheme['rows'] ?? null) ? $scheme['rows'] : [];
$citations = $scheme['citations'] ?? [];

// Classify: SUPPORTED unless the corpus offered nothing to check a row against.
$status = 'SUPPORTED';
if (!is_array($citations) || $citations === [] || $rows === []) {
    $status = 'NEEDS_VERIFICATION';
} else {
    foreach ($rows as $row) {
        if (trim((string) ($row['reference'] ?? '')) === '') {
            $status = 'NEEDS_VERIFICATION';
            break;
        }
    }
}

$title = sprintf('%s scheme of work — Term %d', $request['subject'], $request['term']);

$stmt = $pdo->prepare(
    'INSERT INTO schemes (user_id, subject_id, title, term, lessons_per_week, start_week, status, payload)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    (int) $_SESSION['user_id'],
    $resolved['subject_id'],
    $title,
    $request['term'],
    $request['lessons_per_week'],
    $request['start_week'],
    $status,
    json_encode($scheme),
]);

echo json_encode([
    'success' => true,
    'disclosure' => disclosure(),
    'status' => $status,
    'scheme' => $scheme,
    'sijui' => null,
    'saved_id' => (int) $pdo->lastInsertId(),
]);
