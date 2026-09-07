<?php
/**
 * JSON API: curriculum admin — add a subject or topic that isn't in the
 * seeded KICD catalogue.
 *
 *  POST /api/curriculum.php (multipart) { "action": "add_subject", "csrf_token",
 *                             "code", "name", "strand", "grade_level", "pdf" (file, optional) }
 *  POST /api/curriculum.php { "action": "add_topic", "csrf_token",
 *                             "subject_id", "name", "strand", "source_text" }
 *
 * A topic's source_text (teacher-pasted) and a subject's pdf (teacher-uploaded)
 * are both grounding fallbacks for when there's no bundled KICD source_file —
 * see find_topic_sources() in api/generate-lesson.php and resolve_subject_source()
 * in api/generate-scheme.php. The PDF is attached to Claude as a native document,
 * not text-extracted, so add_subject must be a real (multipart) form POST.
 */

declare(strict_types=1);

define('SUPERADMIN_GUARD_JSON', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../includes/require-superadmin.php';

const CURRICULUM_UPLOAD_DIR = __DIR__ . '/../uploads/curriculum';
const MAX_PDF_BYTES = 10 * 1024 * 1024;

header('Content-Type: application/json; charset=utf-8');

// PHP silently drops the whole body (and $_POST/$_FILES) when the request
// exceeds post_max_size — the usual cause of an "empty" upload POST.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'That upload is too large. Choose a PDF under 10 MB.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? '';

if (!csrf_verify($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session expired. Please try again.']);
    exit;
}

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

        $storedPdf = null;
        $pdfFile = $_FILES['pdf'] ?? null;
        if (is_array($pdfFile) && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
                $tooBig = in_array($pdfFile['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $tooBig ? 'That PDF is too large (10 MB max).' : 'The PDF could not be uploaded.']);
                exit;
            }
            if ($pdfFile['size'] <= 0 || $pdfFile['size'] > MAX_PDF_BYTES) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'That PDF is too large (10 MB max).']);
                exit;
            }

            $mime = function_exists('finfo_open')
                ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $pdfFile['tmp_name'])
                : ($pdfFile['type'] ?? '');
            $ext = strtolower(pathinfo((string) $pdfFile['name'], PATHINFO_EXTENSION));
            if ($mime !== 'application/pdf' || $ext !== 'pdf') {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Only PDF files can be uploaded.']);
                exit;
            }

            if (!is_dir(CURRICULUM_UPLOAD_DIR)) {
                @mkdir(CURRICULUM_UPLOAD_DIR, 0775, true);
            }

            $storedPdf = bin2hex(random_bytes(16)) . '.pdf';
            if (!move_uploaded_file($pdfFile['tmp_name'], CURRICULUM_UPLOAD_DIR . '/' . $storedPdf)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'The PDF could not be saved. Please try again.']);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO subjects (code, name, strand, grade_level, source_pdf) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $name, $strand, $gradeLevel, $storedPdf]);

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
    // The subject row failed after the PDF was already saved (e.g. duplicate
    // code) — don't leave an orphaned upload behind.
    if (isset($storedPdf) && $storedPdf !== null) {
        @unlink(CURRICULUM_UPLOAD_DIR . '/' . $storedPdf);
    }

    if (($e->errorInfo[1] ?? null) === 1062) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'A subject with that code already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
