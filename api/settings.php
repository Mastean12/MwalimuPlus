<?php
/**
 * Settings API: Handle image uploads and database updates.
 */
declare(strict_types=1);

define('SUPERADMIN_GUARD_JSON', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/require-superadmin.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed.']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON payload.']));
}

// Bulk theme save action
if (!empty($input['action']) && $input['action'] === 'save_theme') {
    $themeSettings = $input['settings'] ?? [];
    if (empty($themeSettings) || !is_array($themeSettings)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid settings payload.']));
    }
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    foreach ($themeSettings as $key => $value) {
        if (strpos($key, 'theme_') === 0) {
            $stmt->execute([$key, $value, $value]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Bulk AI provider selection save action
if (!empty($input['action']) && $input['action'] === 'save_ai') {
    require_once __DIR__ . '/../config/ai.php';

    $aiSettings = $input['settings'] ?? [];
    if (!is_array($aiSettings)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid settings payload.']));
    }

    $configured = ai_configured_providers();
    if ($configured === []) {
        http_response_code(400);
        exit(json_encode(['error' => 'No AI provider key is configured. Add at least one key in the .env file first.']));
    }

    $default = (string) ($aiSettings['ai_default_provider'] ?? '');
    $defaultModel = (string) ($aiSettings['ai_default_model'] ?? '');
    $fallback = (string) ($aiSettings['ai_fallback_provider'] ?? '');
    $fallbackModel = (string) ($aiSettings['ai_fallback_model'] ?? '');

    if (!in_array($default, $configured, true)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid default AI provider.']));
    }
    if (!ai_model_in_provider($default, $defaultModel)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid default AI model for the selected provider.']));
    }
    if ($fallback !== '' && (!in_array($fallback, $configured, true) || $fallback === $default)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid fallback AI provider. Pick a configured provider different from the default, or None.']));
    }
    if ($fallback === '') {
        $fallbackModel = '';
    }
    if ($fallback !== '' && !ai_model_in_provider($fallback, $fallbackModel)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid fallback AI model for the selected provider.']));
    }

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute(['ai_default_provider', $default, $default]);
    $stmt->execute(['ai_default_model', $defaultModel, $defaultModel]);
    $stmt->execute(['ai_fallback_provider', $fallback, $fallback]);
    $stmt->execute(['ai_fallback_model', $fallbackModel, $fallbackModel]);
    echo json_encode(['success' => true]);
    exit;
}

// Maintenance mode toggle: blocks every teacher (session or public browse
// pages) behind a 503 page while the superadmin keeps full access.
if (!empty($input['action']) && $input['action'] === 'toggle_maintenance') {
    $enabled = !empty($input['enabled']);
    $value = $enabled ? '1' : '0';

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute(['maintenance_mode', $value, $value]);

    audit_log((int) $_SESSION['user_id'], $enabled ? 'maintenance_enabled' : 'maintenance_disabled');
    echo json_encode(['success' => true, 'enabled' => $enabled]);
    exit;
}

// Save (encrypted) or clear a provider's API key, replacing the .env workflow.
if (!empty($input['action']) && $input['action'] === 'save_ai_key') {
    require_once __DIR__ . '/../config/ai.php';

    $provider = (string) ($input['provider'] ?? '');
    $apiKey = trim((string) ($input['api_key'] ?? ''));

    if (ai_provider_meta($provider) === null) {
        http_response_code(400);
        exit(json_encode(['error' => 'Unknown provider.']));
    }
    if ($apiKey === '' || strlen($apiKey) > 500) {
        http_response_code(422);
        exit(json_encode(['error' => 'Enter a valid API key (up to 500 characters).']));
    }

    $pdo = db();
    $encrypted = encrypt_secret($apiKey);
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $key = 'ai_secret_' . $provider;
    $stmt->execute([$key, $encrypted, $encrypted]);

    audit_log((int) $_SESSION['user_id'], 'ai_key_saved', 'setting', null, ['provider' => $provider]);
    echo json_encode(['success' => true]);
    exit;
}

if (!empty($input['action']) && $input['action'] === 'clear_ai_key') {
    require_once __DIR__ . '/../config/ai.php';

    $provider = (string) ($input['provider'] ?? '');
    if (ai_provider_meta($provider) === null) {
        http_response_code(400);
        exit(json_encode(['error' => 'Unknown provider.']));
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM settings WHERE setting_key = ?')->execute(['ai_secret_' . $provider]);

    audit_log((int) $_SESSION['user_id'], 'ai_key_cleared', 'setting', null, ['provider' => $provider]);
    echo json_encode(['success' => true]);
    exit;
}

if (empty($input['setting']) || empty($input['image'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request payload.']));
}

$setting = $input['setting'];
$imageData = $input['image'];

if (!in_array($setting, ['logo', 'favicon'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid setting type.']));
}

// Decode base64 image
if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
    $imageData = substr($imageData, strpos($imageData, ',') + 1);
    $type = strtolower($type[1]); // jpg, png, etc.

    if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Unsupported image type.']));
    }

    $imageData = base64_decode($imageData);
    if ($imageData === false) {
        http_response_code(400);
        exit(json_encode(['error' => 'Failed to decode image data.']));
    }
} else {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid image data format.']));
}

$filename = $setting . '_' . time() . '.' . $type;
$filepath = __DIR__ . '/../assets/uploads/settings/' . $filename;
$publicUrl = 'assets/uploads/settings/' . $filename;

if (!is_dir(dirname($filepath))) {
    mkdir(dirname($filepath), 0777, true);
}

if (!file_put_contents($filepath, $imageData)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to save image.']));
}

// Update database
$pdo = db();
$settingKey = $setting . '_url';

// Fetch old url to delete old file
$stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
$stmt->execute([$settingKey]);
$oldUrl = $stmt->fetchColumn();
if ($oldUrl && file_exists(__DIR__ . '/../' . $oldUrl)) {
    unlink(__DIR__ . '/../' . $oldUrl);
}

$stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
$stmt->execute([$settingKey, $publicUrl, $publicUrl]);

echo json_encode([
    'success' => true,
    'url' => $publicUrl
]);
