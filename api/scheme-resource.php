<?php
/**
 * Add or remove a learning material attached to a scheme of work.
 *
 * Plain form target (multipart, so PDF upload works without JS). On completion
 * it sets a flash message and redirects back to scheme.php (Post/Redirect/Get).
 *
 * POST fields:
 *   csrf_token   required
 *   scheme_id    required, must belong to the signed-in teacher
 *   delete_id    -> remove that resource (and its file, if a PDF no row still uses)
 *   otherwise add:
 *     row_key[]  one or more of '' (whole scheme) / 'w{week}l{lesson}' grid rows
 *     kind       youtube | link | pdf
 *     label      optional; when blank it is derived per link/file
 *     url        youtube/link: one or more links, one per line
 *     file       pdf: a single file (<= 10 MB, application/pdf)
 *
 * A resource is created for every (selected row x link) pair.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
secure_session_start();

const SCHEME_UPLOAD_DIR = __DIR__ . '/../uploads/scheme-resources';
const MAX_PDF_BYTES = 10 * 1024 * 1024;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];

// App root as an absolute path, so the redirect works from /api/ and from a
// sub-directory install alike (SCRIPT_NAME is /api/scheme-resource.php or
// /<subdir>/api/scheme-resource.php).
$appRoot = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x', 2)), '/');

$schemeId = (int) ($_POST['scheme_id'] ?? 0);
$backUrl = $appRoot . ($schemeId > 0 ? '/scheme.php?id=' . $schemeId : '/schemes.php');

/** Flash a message and return to the scheme page. */
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

// PHP silently drops the whole body (and $_POST/$_FILES) when the request
// exceeds post_max_size — the usual cause of an "empty" upload POST.
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $backUrl = $appRoot . '/schemes.php';
    back('error', 'That upload is too large. Choose a PDF under 10 MB.');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back('error', 'Your session expired. Please try again.');
}

$pdo = db();

$stmt = $pdo->prepare('SELECT payload FROM schemes WHERE id = ? AND user_id = ?');
$stmt->execute([$schemeId, $userId]);
$scheme = $stmt->fetch();
if (!$scheme) {
    $backUrl = $appRoot . '/schemes.php';
    back('error', 'Scheme not found.');
}

/* ---- Delete -------------------------------------------------------------- */

if (!empty($_POST['delete_id'])) {
    $stmt = $pdo->prepare(
        'SELECT id, kind, url FROM scheme_resources WHERE id = ? AND scheme_id = ?'
    );
    $stmt->execute([(int) $_POST['delete_id'], $schemeId]);
    $resource = $stmt->fetch();

    if (!$resource) {
        back('error', 'That material was not found.');
    }

    if ($resource['kind'] === 'pdf' && $resource['url'] !== '') {
        // The same file can back several rows — only unlink when this is the last.
        $others = $pdo->prepare('SELECT COUNT(*) FROM scheme_resources WHERE url = ? AND id <> ?');
        $others->execute([$resource['url'], (int) $resource['id']]);
        if ((int) $others->fetchColumn() === 0) {
            $path = SCHEME_UPLOAD_DIR . '/' . basename($resource['url']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $pdo->prepare('DELETE FROM scheme_resources WHERE id = ?')->execute([(int) $resource['id']]);
    back('success', 'Material removed.');
}

/* ---- Add -------------------------------------------------------------- */

$kind = (string) ($_POST['kind'] ?? '');
if (!in_array($kind, ['youtube', 'link', 'pdf'], true)) {
    back('error', 'Choose a material type.');
}

$label = clip(trim((string) ($_POST['label'] ?? '')), 190);

// Selected rows: '' (whole scheme) and/or 'w{n}l{n}' keys that exist in the grid.
$payload = $scheme['payload'] !== null ? json_decode($scheme['payload'], true) : null;
$validKeys = [''];
foreach ($payload['rows'] ?? [] as $row) {
    $validKeys[] = sprintf('w%dl%d', (int) ($row['week'] ?? 0), (int) ($row['lesson'] ?? 0));
}
$selected = $_POST['row_key'] ?? '';
$rowKeys = array_values(array_unique(array_filter(
    array_map('strval', is_array($selected) ? $selected : [$selected]),
    static fn ($k) => in_array($k, $validKeys, true)
)));
if ($rowKeys === []) {
    $rowKeys = [''];
}

// Build the list of things to attach: each entry is [url, file_name, file_size].
$targets = [];

if ($kind === 'pdf') {
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = is_array($file) && in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        back('error', $tooBig ? 'That PDF is too large (10 MB max).' : 'Choose a PDF file to upload.');
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > MAX_PDF_BYTES) {
        back('error', 'That PDF is too large (10 MB max).');
    }

    $mime = function_exists('finfo_open')
        ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name'])
        : ($file['type'] ?? '');
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($mime !== 'application/pdf' || $ext !== 'pdf') {
        back('error', 'Only PDF files can be uploaded.');
    }

    if (!is_dir(SCHEME_UPLOAD_DIR)) {
        @mkdir(SCHEME_UPLOAD_DIR, 0775, true);
    }

    $stored = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], SCHEME_UPLOAD_DIR . '/' . $stored)) {
        back('error', 'The upload could not be saved. Please try again.');
    }

    $original = preg_replace('/[^\w.\- ]+/u', '_', (string) $file['name']) ?: 'material.pdf';
    $targets[] = ['scheme-resources/' . $stored, clip(trim($original), 190), (int) $file['size']];
} else {
    $seen = [];
    foreach (preg_split('/\R/', (string) ($_POST['url'] ?? '')) ?: [] as $line) {
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
        back('error', 'Enter at least one valid http(s) link (one per line).');
    }
}

$ins = $pdo->prepare(
    'INSERT INTO scheme_resources (scheme_id, user_id, row_key, kind, label, url, file_name, file_size)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$added = 0;
foreach ($rowKeys as $rk) {
    foreach ($targets as [$u, $fn, $fs]) {
        $lbl = $label !== '' ? $label : derive_label($kind, $u, $fn);
        $ins->execute([$schemeId, $userId, $rk, $kind, $lbl, $u, $fn, $fs]);
        $added++;
        if ($added >= 200) {
            break 2;
        }
    }
}

back('success', $added === 1 ? 'Material added.' : "Added {$added} materials.");
