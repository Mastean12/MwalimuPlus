<?php
/**
 * Serves an uploaded file to the teacher who owns it.
 *
 *   GET /download.php?id=<scheme_resources.id>   -> a scheme's PDF
 *   GET /download.php?lr=<lesson_resources.id>   -> a lesson's PDF or image
 *
 * Files live under uploads/ (blocked from direct web access); this is the
 * only route to them.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
secure_session_start();

const SCHEME_UPLOAD_DIR = __DIR__ . '/uploads/scheme-resources';
const LESSON_UPLOAD_DIR = __DIR__ . '/uploads/lesson-resources';

const EXT_MIME = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
];

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];
$pdo = db();

$schemeResId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$lessonResId = filter_input(INPUT_GET, 'lr', FILTER_VALIDATE_INT);

if ($schemeResId) {
    $stmt = $pdo->prepare(
        "SELECT sr.url, sr.file_name, sc.user_id
           FROM scheme_resources sr
           JOIN schemes sc ON sc.id = sr.scheme_id
          WHERE sr.id = ? AND sr.kind = 'pdf'"
    );
    $stmt->execute([$schemeResId]);
    $row = $stmt->fetch();
    $dir = SCHEME_UPLOAD_DIR;
} elseif ($lessonResId) {
    $stmt = $pdo->prepare(
        "SELECT lr.url, lr.file_name, l.user_id
           FROM lesson_resources lr
           JOIN lessons l ON l.id = lr.lesson_id
          WHERE lr.id = ? AND lr.kind IN ('pdf', 'image')"
    );
    $stmt->execute([$lessonResId]);
    $row = $stmt->fetch();
    $dir = LESSON_UPLOAD_DIR;
} else {
    not_found();
}

if (!$row || (int) $row['user_id'] !== $userId) {
    not_found();
}

$path = $dir . '/' . basename((string) $row['url']);
if (!is_file($path)) {
    not_found();
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = EXT_MIME[$ext] ?? 'application/octet-stream';

$name = $row['file_name'] !== '' ? $row['file_name'] : ('file.' . $ext);
$name = str_replace(['"', "\r", "\n"], '', $name);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
