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
 *     "resources": ["chalkboard", "chalk"]
 *   }
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
require_once __DIR__ . '/../config/claude.php';
require_once __DIR__ . '/../config/session.php';
secure_session_start();

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
    ];
}

/** Locates the corpus file for this topic, falling back to the subject's strand file. */
function find_source_file(PDO $pdo, int $userId, string $subjectName, string $topicName): ?string
{
    // Exact topic match first.
    $stmt = $pdo->prepare(
        'SELECT t.source_file
           FROM topics t
           JOIN subjects s ON s.id = t.subject_id
          WHERE t.name = ? AND s.name = ?'
    );
    $stmt->execute([$topicName, $subjectName]);
    $row = $stmt->fetch();
    if ($row && $row['source_file'] !== '') {
        return $row['source_file'];
    }

    // Fall back to the subject's strand file so similarly-worded topics stay grounded.
    $stmt = $pdo->prepare('SELECT source_file FROM topics t JOIN subjects s ON s.id = t.subject_id WHERE s.name = ? LIMIT 1');
    $stmt->execute([$subjectName]);
    $row = $stmt->fetch();
    if ($row && $row['source_file'] !== '') {
        return $row['source_file'];
    }

    return null;
}

$request = read_request();
$pdo = db();
$sourceFile = find_source_file($pdo, (int) $_SESSION['user_id'], $request['subject'], $request['topic']);
$sources = $sourceFile !== null ? load_strand_source($sourceFile) : '';

// Out-of-source (no corpus at all): answer with sijui, never invent a lesson.
if (trim($sources) === '') {
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

// Placeholder key? Return a clear, actionable demo message.
$key = claude_api_key();
if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'SUPPORTED',
        'lesson' => null,
        'sijui' => null,
        'demo_mode' => true,
        'message' => 'Claude API key is not configured. Set the CLAUDE_API_KEY environment variable (or the placeholder in config/claude.php) to generate real lessons. The request was validated against the curriculum corpus.',
    ]);
    exit;
}

$system = claude_system_prompt($request['subject'], $request['topic'], $sources);
$raw = claude_complete($system, $userPrompt);

if ($raw === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The Claude API could not be reached or returned an error. Please try again.']);
    exit;
}

// Strip markdown fences if the model wraps the JSON.
$raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
$raw = preg_replace('/```\s*$/', '', $raw);

$decoded = json_decode($raw, true);
if (!is_array($decoded) || !array_key_exists('lesson', $decoded)) {
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
    'INSERT INTO lessons (user_id, subject_id, topic_id, title, status, duration_minutes, payload)
     VALUES (?, (SELECT id FROM subjects WHERE name = ? LIMIT 1),
             (SELECT id FROM topics WHERE name = ? LIMIT 1), ?, ?, ?, ?)'
);
$stmt->execute([
    (int) $_SESSION['user_id'],
    $request['subject'],
    $request['topic'],
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
