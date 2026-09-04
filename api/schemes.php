<?php
/** JSON API: list, fetch, save, edit and delete schemes of work.
 *  GET  /api/schemes.php                    -> list (newest first)
 *  GET  /api/schemes.php?id=1               -> one scheme with full payload
 *  POST /api/schemes.php  { "id": 1 }       -> delete
 *  POST /api/schemes.php  { "action": "save", "subject_id", "term",
 *                            "lessons_per_week", "start_week", "scheme" }
 *                                            -> persist a generate-scheme.php draft
 *                                               (possibly teacher-edited); returns saved_id
 *  POST /api/schemes.php  { "action": "rename", "id": 1, "title": "..." }
 *  POST /api/schemes.php  { "action": "update", "id": 1, "scheme": {...} }
 *                                            -> overwrite an already-saved scheme's
 *                                               content with teacher edits
 *  POST /api/schemes.php  { "action": "hide", "id": 1, "is_hidden": 1|0 }
 *                                            -> toggle public/hidden visibility
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/claude.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

/** Coerces a value to a list of trimmed, non-empty strings. */
function sanitize_string_list($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $items = array_map(static fn ($v) => trim((string) $v), $value);
    return array_values(array_filter($items, static fn ($v) => $v !== ''));
}

/** Coerces a submitted (possibly teacher-edited) rows array back into the stored schema. */
function sanitize_scheme_rows($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $rows = [];
    foreach ($value as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[] = [
            'week' => (int) ($row['week'] ?? 0),
            'lesson' => (int) ($row['lesson'] ?? 0),
            'strand' => trim((string) ($row['strand'] ?? '')),
            'sub_strand' => trim((string) ($row['sub_strand'] ?? '')),
            'specific_outcomes' => sanitize_string_list($row['specific_outcomes'] ?? null),
            'key_inquiry_question' => trim((string) ($row['key_inquiry_question'] ?? '')),
            'learning_experiences' => sanitize_string_list($row['learning_experiences'] ?? null),
            'learning_resources' => sanitize_string_list($row['learning_resources'] ?? null),
            'assessment' => trim((string) ($row['assessment'] ?? '')),
            'reference' => trim((string) ($row['reference'] ?? '')),
        ];
    }
    return $rows;
}

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

        if (is_array($input) && ($input['action'] ?? '') === 'save') {
            $subjectId = (int) ($input['subject_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT name FROM subjects WHERE id = ?');
            $stmt->execute([$subjectId]);
            $subjectName = $stmt->fetchColumn();

            if ($subjectName === false) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Unknown subject.']);
                exit;
            }

            $term = (int) ($input['term'] ?? 1);
            $term = ($term >= 1 && $term <= 3) ? $term : 1;

            $lessonsPerWeek = (int) ($input['lessons_per_week'] ?? 4);
            $lessonsPerWeek = ($lessonsPerWeek >= 1 && $lessonsPerWeek <= 10) ? $lessonsPerWeek : 4;

            $startWeek = (int) ($input['start_week'] ?? 1);
            $startWeek = ($startWeek >= 1 && $startWeek <= 52) ? $startWeek : 1;

            $rows = sanitize_scheme_rows($input['scheme']['rows'] ?? null);
            if ($rows === []) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'The scheme needs at least one lesson.']);
                exit;
            }

            $scheme = [
                'key_inquiry_questions' => sanitize_string_list($input['scheme']['key_inquiry_questions'] ?? null),
                'rows' => $rows,
                'citations' => sanitize_string_list($input['scheme']['citations'] ?? null),
            ];
            $status = scheme_classify_status($rows, $scheme['citations']);
            $title = sprintf('%s scheme of work — Term %d', $subjectName, $term);

            $stmt = $pdo->prepare(
                'INSERT INTO schemes (user_id, subject_id, title, term, lessons_per_week, start_week, status, payload)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId, $subjectId, $title, $term, $lessonsPerWeek, $startWeek, $status, json_encode($scheme),
            ]);

            echo json_encode(['success' => true, 'saved_id' => (int) $pdo->lastInsertId(), 'status' => $status]);
            exit;
        }

        if (empty($input['id'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Scheme id is required.']);
            exit;
        }
        $id = (int) $input['id'];

        if (($input['action'] ?? '') === 'hide') {
            $isHidden = !empty($input['is_hidden']) ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE schemes SET is_hidden = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$isHidden, $id, $userId]);
            
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT 1 FROM schemes WHERE id = ? AND user_id = ?');
                $check->execute([$id, $userId]);
                if (!$check->fetchColumn()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
                    exit;
                }
            }
            
            echo json_encode(['success' => true]);
            exit;
        }

        if (($input['action'] ?? '') === 'rename') {
            $title = trim((string) ($input['title'] ?? ''));
            if ($title === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Title cannot be empty.']);
                exit;
            }
            $title = clip($title, 190);

            $stmt = $pdo->prepare('UPDATE schemes SET title = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$title, $id, $userId]);

            if ($stmt->rowCount() === 0) {
                // rowCount is 0 when the title is unchanged too; confirm ownership.
                $check = $pdo->prepare('SELECT 1 FROM schemes WHERE id = ? AND user_id = ?');
                $check->execute([$id, $userId]);
                if (!$check->fetchColumn()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
                    exit;
                }
            }

            echo json_encode(['success' => true, 'title' => $title]);
            exit;
        }

        if (($input['action'] ?? '') === 'update') {
            $check = $pdo->prepare('SELECT 1 FROM schemes WHERE id = ? AND user_id = ?');
            $check->execute([$id, $userId]);
            if (!$check->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Scheme not found.']);
                exit;
            }

            $rows = sanitize_scheme_rows($input['scheme']['rows'] ?? null);
            if ($rows === []) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'The scheme needs at least one lesson.']);
                exit;
            }

            $scheme = [
                'key_inquiry_questions' => sanitize_string_list($input['scheme']['key_inquiry_questions'] ?? null),
                'rows' => $rows,
                'citations' => sanitize_string_list($input['scheme']['citations'] ?? null),
            ];
            $status = scheme_classify_status($rows, $scheme['citations']);

            $stmt = $pdo->prepare('UPDATE schemes SET status = ?, payload = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$status, json_encode($scheme), $id, $userId]);

            echo json_encode(['success' => true, 'status' => $status]);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM schemes WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

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
