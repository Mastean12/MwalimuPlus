<?php
/** JSON API: list and fetch generated lessons.
 *  GET /api/lessons.php            -> list (optional ?topic_id=)
 *  GET /api/lessons.php?id=1       -> one lesson with full payload
 *  POST /api/lessons.php           -> delete { "id": 1 } (destructive; kept minimal)
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

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $topicId = filter_input(INPUT_GET, 'topic_id', FILTER_VALIDATE_INT);

        if ($id !== null && $id !== false) {
            $stmt = $pdo->prepare(
                'SELECT l.*, s.name AS subject_name, t.name AS topic_name
                   FROM lessons l
                   JOIN subjects s ON s.id = l.subject_id
                   JOIN topics t ON t.id = l.topic_id
                  WHERE l.id = ? AND l.user_id = ?'
            );
            $stmt->execute([$id, $userId]);
            $lesson = $stmt->fetch();

            if (!$lesson) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Lesson not found.']);
                exit;
            }

            $lesson['payload'] = $lesson['payload'] !== null ? json_decode($lesson['payload'], true) : null;
            echo json_encode(['success' => true, 'lesson' => $lesson]);
            exit;
        }

        if ($topicId !== null && $topicId !== false) {
            $stmt = $pdo->prepare(
                'SELECT id, title, status, duration_minutes, created_at
                   FROM lessons
                  WHERE topic_id = ? AND user_id = ?
                  ORDER BY created_at DESC'
            );
            $stmt->execute([$topicId, $userId]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, subject_id, topic_id, title, status, duration_minutes, created_at
                   FROM lessons
                  WHERE user_id = ?
                  ORDER BY created_at DESC'
            );
            $stmt->execute([$userId]);
        }

        echo json_encode(['success' => true, 'lessons' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Lesson id is required.']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM lessons WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $input['id'], $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Lesson not found.']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
