<?php
/** JSON API: list and manage curriculum subjects.
 *  GET  /api/subjects.php                      -> { "success": true, "subjects": [...] }
 *  POST /api/subjects.php  { "action": "update", "id": 1,
 *                            "code", "name", "strand", "grade_level" }  -> edit a subject
 *  POST /api/subjects.php  { "id": 1 }                                  -> delete a subject
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

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = db();

    if ($method === 'GET') {
        $subjects = $pdo->query(
            'SELECT id, code, name, strand, grade_level FROM subjects ORDER BY name'
        )->fetchAll();

        echo json_encode(['success' => true, 'subjects' => $subjects]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request.']);
            exit;
        }

        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Subject id is required.']);
            exit;
        }

        // Confirm the subject exists before mutating.
        $check = $pdo->prepare('SELECT 1 FROM subjects WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Subject not found.']);
            exit;
        }

        if (($input['action'] ?? '') === 'update') {
            $code = strtoupper(trim((string) ($input['code'] ?? '')));
            $name = trim((string) ($input['name'] ?? ''));
            $strand = trim((string) ($input['strand'] ?? ''));
            $gradeLevel = trim((string) ($input['grade_level'] ?? '')) ?: 'Grade 10';

            if ($code === '' || $name === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Subject code and name are required.']);
                exit;
            }
            $code = clip($code, 10);
            $name = clip($name, 100);
            $strand = clip($strand, 190);
            $gradeLevel = clip($gradeLevel, 20);

            try {
                $stmt = $pdo->prepare(
                    'UPDATE subjects SET code = ?, name = ?, strand = ?, grade_level = ? WHERE id = ?'
                );
                $stmt->execute([$code, $name, $strand, $gradeLevel, $id]);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'error' => 'Another subject already uses that code.']);
                    exit;
                }
                throw $e;
            }

            echo json_encode(['success' => true, 'subject' => [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'strand' => $strand,
                'grade_level' => $gradeLevel,
            ]]);
            exit;
        }

        // Delete a subject. Topics (and their lessons/schemes) cascade via FK.
        $stmt = $pdo->prepare('DELETE FROM subjects WHERE id = ?');
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
