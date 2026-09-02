<?php
/** JSON API: list, fetch and delete schemes of work.
 *  GET  /api/schemes.php         -> list (newest first)
 *  GET  /api/schemes.php?id=1    -> one scheme with full payload
 *  POST /api/schemes.php         -> delete { "id": 1 }
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

        if ($id !== null && $id !== false) {
            $stmt = $pdo->prepare(
                'SELECT sc.*, s.name AS subject_name
                   FROM schemes sc
                   JOIN subjects s ON s.id = sc.subject_id
                  WHERE sc.id = ? AND sc.user_id = ?'
            );
            $stmt->execute([$id, $userId]);
            $scheme = $stmt->fetch();

            if (!$scheme) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
                exit;
            }

            $scheme['payload'] = $scheme['payload'] !== null ? json_decode($scheme['payload'], true) : null;
            echo json_encode(['success' => true, 'scheme' => $scheme]);
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT sc.id, sc.subject_id, s.name AS subject_name, sc.title, sc.term,
                    sc.status, sc.created_at
               FROM schemes sc
               JOIN subjects s ON s.id = sc.subject_id
              WHERE sc.user_id = ?
              ORDER BY sc.created_at DESC'
        );
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'schemes' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['id'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Scheme id is required.']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM schemes WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $input['id'], $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
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
