<?php
/**
 * Save or reset a teacher-edited slide deck for a lesson's Present view.
 *
 * POST /api/lesson-presentation.php  (JSON)
 *   { "lesson_id": 12, "slides": [ { "title": "", "lines": [], "hidden": false, "section": "" } ] }
 *   { "lesson_id": 12, "action": "reset" }        -> revert to the auto-built deck
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
secure_session_start();

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

$pdo = db();
$own = $pdo->prepare('SELECT id FROM lessons WHERE id = ? AND user_id = ?');
$own->execute([$lessonId, $userId]);
if (!$own->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lesson not found.']);
    exit;
}

if (($input['action'] ?? '') === 'reset') {
    $pdo->prepare('DELETE FROM lesson_presentations WHERE lesson_id = ?')->execute([$lessonId]);
    echo json_encode(['success' => true, 'reset' => true]);
    exit;
}

$rawSlides = is_array($input['slides'] ?? null) ? $input['slides'] : [];
$slides = [];
foreach (array_slice($rawSlides, 0, 60) as $s) {
    if (!is_array($s)) {
        continue;
    }
    $lines = [];
    foreach (array_slice(is_array($s['lines'] ?? null) ? $s['lines'] : [], 0, 40) as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $lines[] = clip($line, 2000);
        }
    }
    $slides[] = [
        'title' => clip(trim((string) ($s['title'] ?? '')), 300),
        'lines' => $lines,
        'hidden' => !empty($s['hidden']),
        'section' => preg_replace('/[^a-z_]/', '', (string) ($s['section'] ?? '')),
        'mono' => !empty($s['mono']),
    ];
}

if ($slides === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A deck needs at least one slide.']);
    exit;
}

$pdo->prepare(
    'INSERT INTO lesson_presentations (lesson_id, user_id, payload)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload),
                             created_at = CURRENT_TIMESTAMP'
)->execute([$lessonId, $userId, json_encode(['slides' => $slides])]);

echo json_encode(['success' => true]);
