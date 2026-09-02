<?php
/**
 * Generates flashcards + Q&A for a saved lesson, grounded in the same KICD
 * strand design the lesson was built from. One set per lesson (regenerating
 * replaces it).
 *
 * POST /api/generate-study.php   { "lesson_id": 12 }
 *
 * Output:
 *   { "success": true, "disclosure": "...", "status": "SUPPORTED|NEEDS_VERIFICATION|UNKNOWN",
 *     "study": { "flashcards": [...], "qa": [...] } | null, "sijui": null|"..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/claude.php';
require_once __DIR__ . '/../config/session.php';
secure_session_start();

set_time_limit(CLAUDE_TIMEOUT_SECONDS + 30);
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}
$userId = (int) $_SESSION['user_id'];

function disclosure(): string
{
    return 'This is an AI assistant. It can be wrong. The teacher makes the final decision.';
}

$input = json_decode(file_get_contents('php://input'), true);
$lessonId = (int) ($input['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'lesson_id is required.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT l.title, l.payload, l.status, t.name AS topic_name, s.name AS subject_name,
            t.source_file, t.source_text
       FROM lessons l
       JOIN topics t ON t.id = l.topic_id
       JOIN subjects s ON s.id = l.subject_id
      WHERE l.id = ? AND l.user_id = ?'
);
$stmt->execute([$lessonId, $userId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lesson not found.']);
    exit;
}

$sources = $lesson['source_file'] !== ''
    ? load_strand_source($lesson['source_file'])
    : trim((string) ($lesson['source_text'] ?? ''));

if ($lesson['status'] === 'UNKNOWN' || $lesson['payload'] === null || trim($sources) === '') {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'study' => null,
        'sijui' => "I can't build study material without the curriculum design this lesson came from.",
    ]);
    exit;
}

$key = claude_api_key();
if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'SUPPORTED',
        'study' => null,
        'sijui' => null,
        'demo_mode' => true,
        'message' => 'Claude API key is not configured. Set CLAUDE_API_KEY to generate flashcards and Q&A.',
    ]);
    exit;
}

$lessonJson = (string) $lesson['payload'];

$system = <<<PROMPT
You are Mwalimu AI, helping a Grade 10 teacher in Kenya turn one lesson into
revision material. You have exactly TWO inputs: the SOURCES (one official KICD
strand design, pages marked "DESIGN PAGE <n>") and the LESSON already built from
it.

RULES:
1. Use ONLY the SOURCES and the LESSON. No outside knowledge, no invented facts.
2. Every flashcard and every Q&A ends with a "reference" of the DESIGN PAGE it
   comes from, written exactly as "design p.<n>". Never guess a page number.
3. If the SOURCES do not support revision material for this lesson, return
   {"study": null} and nothing else.
4. Flashcards are short recall prompts (front = a term/question, back = a concise
   answer). Q&A are fuller understanding questions with model answers.
5. Never request or process learner names or individual learner data.
6. Output ONLY the JSON object below — no prose, no markdown fences.

SOURCES:
{$sources}

LESSON (JSON):
{$lessonJson}
PROMPT;

$userPrompt = <<<PROMPT
Create revision material for this lesson: "{$lesson['title']}" ({$lesson['subject_name']} - {$lesson['topic_name']}).

Return ONLY valid JSON matching exactly:
{
  "study": {
    "flashcards": [
      { "front": "", "back": "", "reference": "design p.13" }
    ],
    "qa": [
      { "question": "", "answer": "", "reference": "design p.13" }
    ]
  }
}

Aim for 6-10 flashcards and 4-6 Q&A. If the SOURCES cannot support this, return { "study": null }.
PROMPT;

$raw = claude_complete($system, $userPrompt, 8000);
if ($raw === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The Claude API could not be reached or returned an error. Please try again.']);
    exit;
}

$decoded = claude_extract_json($raw);
if (!is_array($decoded) || !array_key_exists('study', $decoded)) {
    error_log('generate-study: unparseable model reply: ' . substr((string) $raw, 0, 1000));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The model returned an unexpected response. Please try again.']);
    exit;
}

$study = $decoded['study'];
if ($study === null) {
    echo json_encode([
        'success' => true,
        'disclosure' => disclosure(),
        'status' => 'UNKNOWN',
        'study' => null,
        'sijui' => "I can't build study material for this from the curriculum design I have.",
    ]);
    exit;
}

$flashcards = is_array($study['flashcards'] ?? null) ? array_values($study['flashcards']) : [];
$qa = is_array($study['qa'] ?? null) ? array_values($study['qa']) : [];

// Classify: NEEDS_VERIFICATION when nothing carries a citation to check.
$status = 'SUPPORTED';
$hasRef = false;
foreach (array_merge($flashcards, $qa) as $item) {
    if (trim((string) ($item['reference'] ?? '')) !== '') {
        $hasRef = true;
        break;
    }
}
if (!$hasRef || ($flashcards === [] && $qa === [])) {
    $status = 'NEEDS_VERIFICATION';
}

$normalised = ['flashcards' => $flashcards, 'qa' => $qa];

$stmt = $pdo->prepare(
    'INSERT INTO lesson_study_sets (lesson_id, user_id, status, payload)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), status = VALUES(status),
                             payload = VALUES(payload), created_at = CURRENT_TIMESTAMP'
);
$stmt->execute([$lessonId, $userId, $status, json_encode($normalised)]);

echo json_encode([
    'success' => true,
    'disclosure' => disclosure(),
    'status' => $status,
    'study' => $normalised,
    'sijui' => null,
]);
