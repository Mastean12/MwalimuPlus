<?php
/** Loads .env into the process environment (getenv/putenv), once per request. */

declare(strict_types=1);

function load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = __DIR__ . '/../.env';
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) > 1 && $value[0] === '"' && $value[-1] === '"') {
            $value = substr($value, 1, -1);
        }

        // Real environment variables (set by the OS/webserver) always win.
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}
