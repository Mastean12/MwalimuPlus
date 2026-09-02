<?php
/**
 * Serves a scheme's uploaded PDF to its owner only.
 *
 * GET /download.php?id=<scheme_resources.id>
 *
 * Files live under uploads/scheme-resources/ (blocked from direct web access);
 * this is the only route to them.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
secure_session_start();

const SCHEME_UPLOAD_DIR = __DIR__ . '/uploads/scheme-resources';

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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    not_found();
}

$stmt = db()->prepare(
    "SELECT sr.url, sr.file_name, sc.user_id
       FROM scheme_resources sr
       JOIN schemes sc ON sc.id = sr.scheme_id
      WHERE sr.id = ? AND sr.kind = 'pdf'"
);
$stmt->execute([$id]);
$resource = $stmt->fetch();

if (!$resource || (int) $resource['user_id'] !== (int) $_SESSION['user_id']) {
    not_found();
}

$path = SCHEME_UPLOAD_DIR . '/' . basename((string) $resource['url']);
if (!is_file($path)) {
    not_found();
}

$name = $resource['file_name'] !== '' ? $resource['file_name'] : 'material.pdf';
$name = str_replace(['"', "\r", "\n"], '', $name);

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
