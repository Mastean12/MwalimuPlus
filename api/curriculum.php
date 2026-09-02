<?php
/**
 * JSON API: curriculum admin — add a subject or topic that isn't in the
 * seeded KICD catalogue.
 *
 *  POST /api/curriculum.php { "action": "add_subject", "code", "name", "strand", "grade_level" }
 *  POST /api/curriculum.php { "action": "add_topic", "subject_id", "name", "strand", "source_text" }
 *
 * A topic's source_text is a teacher-pasted curriculum design excerpt, used
 * the same way as the bundled KICD source_file when generating a lesson or
 * scheme for it — see find_topic_sources() in api/generate-lesson.php and
 * resolve_subject_source() in api/generate-scheme.php.
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

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'add_subject') {
        $code = clip(trim((string) ($input['code'] ?? '')), 10);
        $name = clip(trim((string) ($input['name'] ?? '')), 100);
        $strand = clip(trim((string) ($input['strand'] ?? '')), 190);
        $gradeLevel = clip(trim((string) ($input['grade_level'] ?? '')), 20);
        if ($gradeLevel === '') {
            $gradeLevel = 'Grade 10';
        }

        if ($code === '' || $name === '' || $strand === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Code, name and strand are required.']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO subjects (code, name, strand, grade_level) VALUES (?, ?, ?, ?)');
        $stmt->execute([$code, $name, $strand, $gradeLevel]);

        echo json_encode(['success' => true, 'subject_id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'add_topic') {
        $subjectId = (int) ($input['subject_id'] ?? 0);
        $name = clip(trim((string) ($input['name'] ?? '')), 190);
        $strand = clip(trim((string) ($input['strand'] ?? '')), 190);
        $sourceText = trim((string) ($input['source_text'] ?? ''));

        if ($subjectId <= 0 || $name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Subject and topic name are required.']);
            exit;
        }

        $check = $pdo->prepare('SELECT strand FROM subjects WHERE id = ?');
        $check->execute([$subjectId]);
        $subjectStrand = $check->fetchColumn();
        if ($subjectStrand === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Subject not found.']);
            exit;
        }
        if ($strand === '') {
            $strand = (string) $subjectStrand;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO topics (subject_id, name, strand, source_file, source_text) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$subjectId, $name, $strand, '', $sourceText !== '' ? $sourceText : null]);

        echo json_encode(['success' => true, 'topic_id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A subject with that code already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
