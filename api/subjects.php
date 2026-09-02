<?php
/** JSON API: list curriculum subjects.
 *  GET /api/subjects.php  ->  { "success": true, "subjects": [...] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not signed in.']);
    exit;
}

try {
    $subjects = db()->query(
        'SELECT id, code, name, strand, grade_level FROM subjects ORDER BY name'
    )->fetchAll();

    echo json_encode(['success' => true, 'subjects' => $subjects]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
