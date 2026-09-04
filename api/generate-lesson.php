<?php
/**
 * MwalimuPlus lesson generation (JSON API).
 *
 * POST /api/generate-lesson.php
 *
 * Input:
 *   {
 *     "subject": "Mathematics",
 *     "topic": "Solving quadratic equations by factorisation",
 *     "strand": "Quadratic Equations and Expressions",
 *     "duration": 40,
 *     "teacher_need": "I have never taught this topic before.",
 *     "resources": ["chalkboard", "chalk"],
 *     "scheme_id": 12
 *   }
 *
 * scheme_id is optional — set when generation is triggered from a scheme of
 * work row (scheme.php's "Generate lesson plan"). It must belong to the
 * signed-in teacher; the resulting lesson's scheme_id links back to it.
 *
 * Output follows the agreed contract:
 *   { "success": true, "disclosure": "...", "status": "SUPPORTED|NEEDS_VERIFICATION|UNKNOWN",
 *     "lesson": {...}|null, "sijui": null|"..." }
 *
 * Grounding: the matching KICD strand design is loaded from content/ and injected
 * into the system prompt as SOURCES. If the corpus cannot support the request the
 * API returns status UNKNOWN with a sijui message and never fabricates a lesson.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../config/session.php';
secure_session_start();

// A grounded generation call runs ~15-30s. Don't let PHP's own execution limit
// (often 30s on cPanel) kill it before cURL's timeout does.
set_time_limit(AI_TIMEOUT_SECONDS + 30);

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
    $topic = trim((string) ($input['topic'] ?? ''));
    $strand = trim((string) ($input['strand'] ?? ''));
    $duration = isset($input['duration']) ? (int) $input['duration'] : 40;
    $teacherNeed = trim((string) ($input['teacher_need'] ?? ''));
    $resources = $input['resources'] ?? [];
    $schemeId = isset($input['scheme_id']) ? (int) $input['scheme_id'] : null;

    if ($subject === '' || $topic === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'subject and topic are required.']);
        exit;
    }

    if (!is_array($resources)) {
        $resources = [];
    }
    $resources = array_values(array_map('strval', $resources));
    if ($duration < 1 || $duration > 240) {
        $duration = 40;
    }

    return [
        'subject' => $subject,
        'topic' => $topic,
        'strand' => $strand,
        'duration' => $duration,
        'teacher_need' => $teacherNeed,
        'resources' => $resources,
        'scheme_id' => $schemeId,
    ];
}

/** Confirms $schemeId belongs to the signed-in teacher, or exits with an error. */
function verify_scheme_ownership(PDO $pdo, ?int $schemeId, int $userId): void
{
    if ($schemeId === null) {
        return;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM schemes WHERE id = ? AND user_id = ?');
    $stmt->execute([$schemeId, $userId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
        exit;
    }
}

/** Absolute path to a subject's uploaded curriculum PDF (curriculum admin UI), if it has one. */
function subject_source_pdf_path(PDO $pdo, string $subjectName): ?string
{
    $stmt = $pdo->prepare('SELECT source_pdf FROM subjects WHERE name = ?');
    $stmt->execute([$subjectName]);
    $stored = $stmt->fetchColumn();
    if (!$stored) {
        return null;
    }
    $path = __DIR__ . '/../uploads/curriculum/' . basename((string) $stored);
    return is_file($path) ? $path : null;
}

/**
 * Resolves the curriculum source for this exact topic in this exact subject, in
 * priority order: the bundled KICD file, a teacher-pasted source_text, then the
 * subject's uploaded curriculum PDF — the first two as SOURCES text, the PDF as
 * a native document attached to the Claude request (see claude_complete()).
 *
 * No fuzzy fallback on the topic name itself, on purpose: if the ask isn't a
 * topic we seeded from a strand design, it is out of curriculum and must reach
 * the Sijui path. Stretching one strand file to cover topics it doesn't name is
 * exactly the failure the cite-or-Sijui rule exists to prevent. This also keeps
 * the later INSERT's topic_id lookup resolvable — it never proceeds without a
 * real matching topic row.
 */
function find_topic_sources(PDO $pdo, string $subjectName, string $topicName): array
{
    $stmt = $pdo->prepare(
        'SELECT t.source_file, t.source_text
           FROM topics t
           JOIN subjects s ON s.id = t.subject_id
          WHERE t.name = ? AND s.name = ?'
    );
    $stmt->execute([$topicName, $subjectName]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['text' => '', 'pdf_path' => null];
    }
    if ($row['source_file'] !== '') {
        return ['text' => load_strand_source($row['source_file']), 'pdf_path' => null];
    }
    if (trim((string) ($row['source_text'] ?? '')) !== '') {
        return ['text' => (string) $row['source_text'], 'pdf_path' => null];
    }

    return ['text' => '', 'pdf_path' => subject_source_pdf_path($pdo, $subjectName)];
}

$request = read_request();
$pdo = db();
verify_scheme_ownership($pdo, $request['scheme_id'], (int) $_SESSION['user_id']);
$resolved = find_topic_sources($pdo, $request['subject'], $request['topic']);
$sources = $resolved['text'];
$pdfPath = $resolved['pdf_path'];

// Out-of-source (no corpus at all): answer with sijui, never invent a lesson.
if (trim($sources) === '' && $pdfPath === null) {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'lesson' => null,
        'sijui' => "I can't answer that from the curriculum design I have. Check the KICD office for the full {$request['subject']} Grade 10 design.",
    ]);
    exit;
}

$resourcesList = $request['resources'] === []
    ? '(none specified)'
    : implode(', ', $request['resources']);

$userPrompt = <<<PROMPT
Generate a lesson plan for this request using ONLY the SOURCES.

Subject: {$request['subject']}
Topic: {$request['topic']}
KICD strand: {$request['strand']}
Lesson duration (minutes): {$request['duration']}
Teacher's stated need: {$request['teacher_need']}
Available resources: {$resourcesList}

Return ONLY valid JSON matching exactly this schema:
{
  "lesson": {
    "title": "",
    "objectives": [],
    "teacher_explanation": "",
    "prerequisites": [],
    "examples": [],
    "board_plan": "",
    "teacher_questions": [],
    "common_misconceptions": [],
    "quick_check": [],
    "teacher_notes": "",
    "citations": ["design p.14", "design p.15"]
  }
}

If the SOURCES do not support this request, instead return:
{ "lesson": null }
PROMPT;

if (!ai_any_configured()) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'SUPPORTED',
        'lesson' => null,
        'sijui' => null,
        'demo_mode' => true,
        'message' => ai_unconfigured_message(),
    ]);
    exit;
}

$system = claude_system_prompt($request['subject'], $request['topic'], $sources, $pdfPath !== null);
$raw = ai_complete($system, $userPrompt, CLAUDE_MAX_TOKENS, $pdfPath);

if ($raw === null) {
    http_response_code(502);
    $hint = ($pdfPath !== null && !ai_document_capable_configured())
        ? ' The source is a PDF curriculum document, which requires the Claude provider to be configured.'
        : '';
    echo json_encode(['success' => false, 'error' => 'No configured AI provider could complete the request.' . $hint . ' Please try again.']);
    exit;
}

$decoded = claude_extract_json($raw);
if (!is_array($decoded) || !array_key_exists('lesson', $decoded)) {
    error_log('generate-lesson: unparseable model reply: ' . substr((string) $raw, 0, 1000));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The model returned an unexpected response. Please try again.']);
    exit;
}

$lesson = $decoded['lesson'];

// Out-of-source answer from the model: report UNKNOWN with sijui.
if ($lesson === null) {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'lesson' => null,
        'sijui' => "I can't answer that from the curriculum design I have. Check the KICD office or the Ministry of Education for guidance.",
    ]);
    exit;
}

// Classify: the model said SUPPORTED implicitly by returning a lesson; we mark
// NEEDS_VERIFICATION when the corpus lacks a page-level citation to check.
$status = 'SUPPORTED';
$citations = $lesson['citations'] ?? [];
if (!is_array($citations) || $citations === []) {
    $status = 'NEEDS_VERIFICATION';
}

// Persist the generated lesson.
$stmt = $pdo->prepare(
    'INSERT INTO lessons (user_id, subject_id, topic_id, scheme_id, title, status, duration_minutes, payload)
     VALUES (?, (SELECT id FROM subjects WHERE name = ? LIMIT 1),
             (SELECT id FROM topics WHERE name = ? LIMIT 1), ?, ?, ?, ?, ?)'
);
$stmt->execute([
    (int) $_SESSION['user_id'],
    $request['subject'],
    $request['topic'],
    $request['scheme_id'],
    $lesson['title'] !== '' ? $lesson['title'] : $request['topic'],
    $status,
    $request['duration'],
    json_encode($lesson),
]);

echo json_encode([
    'success' => true,
    'disclosure' => disclosure(),
    'status' => $status,
    'lesson' => $lesson,
    'sijui' => null,
    'saved_id' => (int) $pdo->lastInsertId(),
]);
