<?php
/**
 * Grounded Q&A chat about one lesson. The AI answers ONLY from the lesson's
 * KICD source + the lesson itself, cites the design page, or says "Sijui".
 * Ephemeral — nothing is stored; the client sends the running history.
 *
 * POST /api/ask.php  (JSON)
 *   { "lesson_id": 12, "messages": [ { "role": "user", "content": "..." }, ... ] }
 *
 * Output: { "success": true, "answer": "...", "status": "SUPPORTED|UNKNOWN" }
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

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Request body must be valid JSON.']);
    exit;
}

$lessonId = (int) ($input['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'lesson_id is required.']);
    exit;
}

// Normalise the running conversation: {role, content}, alternating-ish, capped.
$messages = [];
foreach (array_slice(is_array($input['messages'] ?? null) ? $input['messages'] : [], -16) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim((string) ($m['content'] ?? ''));
    if ($content === '') {
        continue;
    }
    $messages[] = ['role' => $role, 'content' => substr($content, 0, 4000)];
}
if ($messages === [] || end($messages)['role'] !== 'user') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Ask a question first.']);
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
$lessonJson = (string) ($lesson['payload'] ?? '{}');

$key = claude_api_key();
if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
    echo json_encode([
        'success' => true,
        'status' => 'SUPPORTED',
        'answer' => 'The Claude API key is not configured, so I can\'t answer questions yet. Set CLAUDE_API_KEY on the server.',
        'demo_mode' => true,
    ]);
    exit;
}

$sourcesBlock = trim($sources) !== '' ? $sources : '(No separate strand file — rely on the LESSON JSON, which was itself built from the KICD design.)';

$system = <<<PROMPT
You are Mwalimu AI, answering a Grade 10 teacher's questions about ONE lesson
they are preparing. You have exactly two sources of truth: the SOURCES (an
official KICD strand design, pages marked "DESIGN PAGE <n>") and the LESSON JSON
(already built from that design).

RULES:
1. Answer ONLY from the SOURCES and the LESSON. Never use outside knowledge or
   "standard practice". If it is not in either, you do not know it.
2. End each factual statement with the page it came from, written exactly as
   [design p.<n>]. Never invent a page number.
3. If the SOURCES and LESSON do not cover the question, reply with exactly one
   line, starting "Sijui — ", naming the office or section to check. Do not guess
   and do not partially answer.
4. Be brief and practical — the teacher is prepping, not a student. Plain text,
   short paragraphs or a short list. No markdown headings.
5. Never request or process learner names, learner work, or individual learner data.

LESSON: "{$lesson['title']}" ({$lesson['subject_name']} - {$lesson['topic_name']})

SOURCES:
{$sourcesBlock}

LESSON (JSON):
{$lessonJson}
PROMPT;

$answer = claude_chat($messages, $system, 1500);

if ($answer === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The Claude API could not be reached. Please try again.']);
    exit;
}

$answer = trim($answer);
$status = stripos($answer, 'sijui') === 0 ? 'UNKNOWN' : 'SUPPORTED';

echo json_encode(['success' => true, 'status' => $status, 'answer' => $answer]);
