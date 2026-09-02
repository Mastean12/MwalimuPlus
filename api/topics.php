<?php
/** JSON API: list topics for a subject.
 *  GET /api/topics.php?subject_id=1  ->  { "success": true, "topics": [...] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
if ($subjectId === null || $subjectId === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'subject_id is required.']);
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT id, name, strand, source_file
           FROM topics
          WHERE subject_id = ?
          ORDER BY name'
    );
    $stmt->execute([$subjectId]);

    echo json_encode(['success' => true, 'topics' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
