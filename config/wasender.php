<?php
/**
 * Wasender WhatsApp API configuration and helpers.
 *
 * Authentication uses the SESSION API KEY from your Wasender dashboard
 * (Session Management screen, shown once the WhatsApp session is connected),
 * supplied via WASENDER_API_KEY. Sending messages only works with that
 * session key — the account-level Personal Access Token is not accepted by
 * /send-message. When the key is empty sharing is disabled.
 */

declare(strict_types=1);

const WASENDER_API_BASE = 'https://www.wasenderapi.com/api';

/** The configured Wasender bearer token, or '' when not set up. */
function wasender_api_key(): string
{
    static $key = null;

    if ($key === null) {
        $key = trim((string) getenv('WASENDER_API_KEY'));
    }
    return $key;
}

/** True when the site is configured to send WhatsApp messages. */
function wasender_enabled(): bool
{
    return wasender_api_key() !== '';
}

/**
 * Normalizes a phone number to E.164 digit form (no leading +).
 *
 * Accepts 0712 345 678, +254712345678, 254712345678 and 712345678
 * (Kenyan numbers without the leading zero or country code).
 */
function wasender_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        $digits = '254' . $digits;
    }

    return preg_match('/^254[17]\d{8}$/', $digits) === 1 ? $digits : '';
}

/**
 * Performs an authorized JSON POST against the Wasender API.
 *
 * @param array<string, mixed> $payload
 * @return array{ok: bool, status: int, body: mixed}
 */
function wasender_post_json(string $path, array $payload): array
{
    $url = WASENDER_API_BASE . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . wasender_api_key(),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'body' => ['error' => 'curl: ' . ($error ?: "errno {$errno}")]];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : ['raw' => (string) $raw],
    ];
}

/**
 * Uploads raw file bytes to Wasender so it is reachable via a short-lived
 * public URL, then sends it as a document. Returns the API's message id.
 */
function wasender_send_pdf(string $toPhone, string $fileName, string $pdfBytes, string $text): array
{
    $uploadUrl = WASENDER_API_BASE . '/upload';

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . wasender_api_key(),
            'Content-Type: application/pdf',
        ],
        CURLOPT_POSTFIELDS     => $pdfBytes,
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => 'Upload to Wasender failed: ' . ($error ?: "errno {$errno}")];
    }

    $upload = json_decode((string) $raw, true);
    if (!is_array($upload) || empty($upload['publicUrl'])) {
        return [
            'ok' => false,
            'error' => 'Wasender rejected the upload (HTTP ' . $status . '): '
                . ($upload['message'] ?? ($upload['error'] ?? 'unexpected response')),
        ];
    }

    $sent = wasender_post_json('/send-message', [
        'to'          => $toPhone,
        'text'        => $text,
        'documentUrl' => (string) $upload['publicUrl'],
        'fileName'    => $fileName,
    ]);

    if (!$sent['ok']) {
        $body = is_array($sent['body']) ? $sent['body'] : [];
        $detail = $body['message'] ?? $body['error'] ?? json_encode($sent['body']);
        if ($sent['status'] === 401 || $sent['status'] === 403) {
            $detail .= '. Use your WhatsApp SESSION API key (Session Management screen), not your Personal Access Token.';
        }
        return ['ok' => false, 'error' => 'Wasender could not send the message (HTTP ' . $sent['status'] . '): ' . $detail];
    }

    $data = is_array($sent['body']['data'] ?? null) ? $sent['body']['data'] : [];
    return ['ok' => true, 'msgId' => $data['msgId'] ?? null];
}
