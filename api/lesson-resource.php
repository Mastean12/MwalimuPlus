<?php
/**
 * Add or remove a media/resource attached to a lesson plan.
 *
 * Plain form target (multipart, so image/PDF upload works without JS). On
 * completion it flashes a message and redirects back to lesson.php (PRG).
 *
 * POST fields:
 *   csrf_token   required
 *   lesson_id    required, must belong to the signed-in teacher
 *   delete_id    -> remove that resource (and its file, if uploaded)
 *   otherwise add:
 *     section[]  one or more of '' (whole lesson) / a known payload section key
 *     kind       youtube | link | image | pdf
 *     label      optional; when blank it is derived per link/file
 *     url[]      youtube/link: one or more link fields (newlines also split)
 *     file       image/pdf: a single file
 *
 * A resource is created for every (selected section x link) pair.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
secure_session_start();

const LESSON_UPLOAD_DIR = __DIR__ . '/../uploads/lesson-resources';
const MAX_PDF_BYTES = 10 * 1024 * 1024;
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

const LESSON_SECTIONS = [
    'objectives', 'prerequisites', 'teacher_explanation', 'examples', 'board_plan',
    'teacher_questions', 'common_misconceptions', 'quick_check', 'teacher_notes',
];

const IMAGE_EXT_MIME = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
];

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];

$appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x', 2)), '/');

$lessonId = (int) ($_POST['lesson_id'] ?? 0);
$backUrl = $appRoot . ($lessonId > 0 ? '/lesson.php?id=' . $lessonId : '/dashboard.php');

/** Flash a message and return to the lesson page. */
function back(string $kind, string $message): void
{
    global $backUrl;
    set_flash($kind, $message);
    header('Location: ' . $backUrl);
    exit;
}

/** A readable fallback label when the teacher leaves the field blank. */
function derive_label(string $kind, string $url, string $fileName): string
{
    if ($kind === 'pdf') {
        return $fileName !== '' ? $fileName : 'PDF';
    }
    if ($kind === 'image') {
        return $fileName !== '' ? $fileName : 'Image';
    }
    if ($kind === 'youtube') {
        return 'YouTube video';
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    return $host !== '' ? $host : 'Link';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('error', 'Nothing to do.');
}

// PHP drops the whole body when the request exceeds post_max_size.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $backUrl = $appRoot . '/dashboard.php';
    back('error', 'That upload is too large.');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back('error', 'Your session expired. Please try again.');
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM lessons WHERE id = ? AND user_id = ?');
$stmt->execute([$lessonId, $userId]);
if (!$stmt->fetch()) {
    $backUrl = $appRoot . '/dashboard.php';
    back('error', 'Lesson not found.');
}

/* ---- Delete ----------------------------------------------------------------- */

if (!empty($_POST['delete_id'])) {
    $stmt = $pdo->prepare(
        'SELECT id, kind, url FROM lesson_resources WHERE id = ? AND lesson_id = ?'
    );
    $stmt->execute([(int) $_POST['delete_id'], $lessonId]);
    $resource = $stmt->fetch();

    if (!$resource) {
        back('error', 'That item was not found.');
    }

    if (in_array($resource['kind'], ['pdf', 'image'], true) && $resource['url'] !== '') {
        $others = $pdo->prepare('SELECT COUNT(*) FROM lesson_resources WHERE url = ? AND id <> ?');
        $others->execute([$resource['url'], (int) $resource['id']]);
        if ((int) $others->fetchColumn() === 0) {
            $path = LESSON_UPLOAD_DIR . '/' . basename($resource['url']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $pdo->prepare('DELETE FROM lesson_resources WHERE id = ?')->execute([(int) $resource['id']]);
    back('success', 'Item removed.');
}

/* ---- Add ------------------------------------------------------------------- */

$kind = (string) ($_POST['kind'] ?? '');
if (!in_array($kind, ['youtube', 'link', 'image', 'pdf'], true)) {
    back('error', 'Choose a media type.');
}

$label = clip(trim((string) ($_POST['label'] ?? '')), 190);

// Selected sections: '' (whole lesson) and/or known payload section keys.
$selected = $_POST['section'] ?? '';
$sections = array_values(array_unique(array_filter(
    array_map('strval', is_array($selected) ? $selected : [$selected]),
    static fn ($s) => $s === '' || in_array($s, LESSON_SECTIONS, true)
)));
if ($sections === []) {
    $sections = [''];
}

// Build the list to attach: each entry is [url, file_name, file_size].
$targets = [];

if ($kind === 'pdf' || $kind === 'image') {
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = is_array($file) && in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        back('error', $tooBig ? 'That file is too large.' : 'Choose a file to upload.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $mime = function_exists('finfo_open')
        ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name'])
        : ($file['type'] ?? '');

    if ($kind === 'pdf') {
        if (($file['size'] ?? 0) <= 0 || $file['size'] > MAX_PDF_BYTES) {
            back('error', 'That PDF is too large (10 MB max).');
        }
        if ($mime !== 'application/pdf' || $ext !== 'pdf') {
            back('error', 'Only PDF files can be uploaded here.');
        }
        $stored = bin2hex(random_bytes(16)) . '.pdf';
    } else {
        if (($file['size'] ?? 0) <= 0 || $file['size'] > MAX_IMAGE_BYTES) {
            back('error', 'That image is too large (8 MB max).');
        }
        if (!isset(IMAGE_EXT_MIME[$ext]) || $mime !== IMAGE_EXT_MIME[$ext]) {
            back('error', 'Upload a JPG, PNG, WEBP or GIF image.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    }

    if (!is_dir(LESSON_UPLOAD_DIR)) {
        @mkdir(LESSON_UPLOAD_DIR, 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], LESSON_UPLOAD_DIR . '/' . $stored)) {
        back('error', 'The upload could not be saved. Please try again.');
    }

    $original = preg_replace('/[^\w.\- ]+/u', '_', (string) $file['name']) ?: 'file';
    $targets[] = ['lesson-resources/' . $stored, clip(trim($original), 190), (int) $file['size']];
} else {
    $lines = [];
    foreach ((array) ($_POST['url'] ?? []) as $chunk) {
        foreach (preg_split('/\R/', (string) $chunk) ?: [] as $line) {
            $lines[] = $line;
        }
    }

    $seen = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || isset($seen[$line]) || !filter_var($line, FILTER_VALIDATE_URL)) {
            continue;
        }
        $sch = strtolower((string) parse_url($line, PHP_URL_SCHEME));
        if (!in_array($sch, ['http', 'https'], true)) {
            continue;
        }
        $seen[$line] = true;
        $targets[] = [clip($line, 600), '', 0];
    }
    if ($targets === []) {
        back('error', 'Enter at least one valid http(s) link.');
    }
}

$ins = $pdo->prepare(
    'INSERT INTO lesson_resources (lesson_id, user_id, section, kind, label, url, file_name, file_size)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$added = 0;
foreach ($sections as $section) {
    foreach ($targets as [$u, $fn, $fs]) {
        $lbl = $label !== '' ? $label : derive_label($kind, $u, $fn);
        $ins->execute([$lessonId, $userId, $section, $kind, $lbl, $u, $fn, $fs]);
        $added++;
        if ($added >= 200) {
            break 2;
        }
    }
}

back('success', $added === 1 ? 'Media added.' : "Added {$added} items.");
