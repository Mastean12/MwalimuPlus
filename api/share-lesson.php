<?php
/**
 * Shares a saved lesson with a parent over WhatsApp as a PDF.
 *
 * The lesson is rendered into a PDF with Dompdf, uploaded to Wasender (which
 * returns a short-lived public URL), then sent to the parent's number as a
 * WhatsApp document.
 *
 * POST /api/share-lesson.php
 *   { "id": 1, "phone": "+254712345678", "message": "optional caption" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/wasender.php';
require_once __DIR__ . '/../includes/lesson-pdf.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');

function share_error(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    share_error(401, 'Not signed in.');
}

if (!wasender_enabled()) {
    share_error(503, 'WhatsApp sharing is not configured yet. Add WASENDER_API_KEY to the server .env file to enable it.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    share_error(400, 'Invalid request body.');
}

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    share_error(422, 'Lesson id is required.');
}

$phone = wasender_phone((string) ($input['phone'] ?? ''));
if ($phone === '') {
    share_error(
        422,
        'Enter a valid WhatsApp number, e.g. 0712 345 678 or +254712345678.'
    );
}

$message = trim((string) ($input['message'] ?? ''));
$message = clip($message, 1000);

$userId = (int) $_SESSION['user_id'];

$stmt = db()->prepare(
    'SELECT l.*, s.name AS subject_name, t.name AS topic_name
       FROM lessons l
       JOIN subjects s ON s.id = l.subject_id
       JOIN topics t ON t.id = l.topic_id
      WHERE l.id = ? AND l.user_id = ?'
);
$stmt->execute([$id, $userId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    share_error(404, 'Lesson not found.');
}

$payload = $lesson['payload'] !== null ? json_decode($lesson['payload'], true) : null;
if ($lesson['status'] === 'UNKNOWN' || $payload === null) {
    share_error(422, 'This lesson has no curriculum content to share.');
}

$pdf = lesson_pdf_bytes($lesson, $payload);
if ($pdf === null || $pdf === '') {
    share_error(500, 'The lesson PDF could not be generated. Run "composer install" on the server and try again.');
}

$text = $message !== ''
    ? $message
    : sprintf(
        'Hello parent, this is the %s lesson "%s" (%d minutes) prepared with MwalimuPlus. The teacher may follow up with you.',
        $lesson['subject_name'],
        $lesson['title'],
        (int) $lesson['duration_minutes']
    );

$result = wasender_send_pdf($phone, lesson_pdf_filename($lesson), $pdf, $text);

if (!$result['ok']) {
    error_log('Wasender share failed for lesson ' . $id . ': ' . $result['error']);
    share_error(502, 'WhatsApp could not deliver the PDF. ' . $result['error']);
}

echo json_encode([
    'success' => true,
    'message' => 'PDF sent to ' . $phone . ' via WhatsApp.',
    'msgId'   => $result['msgId'] ?? null,
]);
