<?php
declare(strict_types=1);

require_once __DIR__ . '/claude.php';
require_once __DIR__ . '/helpers.php';

const AI_TIMEOUT_SECONDS = 120;
const AI_OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
const AI_DEEPSEEK_URL = 'https://api.deepseek.com/chat/completions';

function ai_providers(): array
{
    return [
        'claude' => [
            'label' => 'Claude',
            'vendor' => 'Anthropic',
            'env' => 'CLAUDE_API_KEY',
            'supports_documents' => true,
            'models' => [CLAUDE_MODEL, 'claude-sonnet-5', 'claude-haiku-5'],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'vendor' => 'OpenAI',
            'env' => 'OPENAI_API_KEY',
            'supports_documents' => false,
            'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'vendor' => 'DeepSeek',
            'env' => 'DEEPSEEK_API_KEY',
            'supports_documents' => false,
            'models' => ['deepseek-v4-pro', 'deepseek-v4-flash', 'deepseek-chat', 'deepseek-reasoner'],
        ],
    ];
}

function ai_provider_meta(string $provider): ?array
{
    $providers = ai_providers();
    return $providers[$provider] ?? null;
}

function ai_provider_models(string $provider): array
{
    $meta = ai_provider_meta($provider);
    return is_array($meta) && is_array($meta['models'] ?? null) ? array_values($meta['models']) : [];
}

function ai_provider_default_model(string $provider): string
{
    $models = ai_provider_models($provider);
    return $models[0] ?? '';
}

function ai_model_in_provider(string $provider, string $model): bool
{
    return in_array($model, ai_provider_models($provider), true);
}

function ai_provider_configured(string $provider): bool
{
    $meta = ai_provider_meta($provider);
    if ($meta === null) {
        return false;
    }
    $key = getenv($meta['env']);
    if (!is_string($key)) {
        return false;
    }
    $key = trim($key);
    return $key !== '' && strpos($key, 'YOUR_') !== 0;
}

function ai_configured_providers(): array
{
    $configured = [];
    foreach (ai_providers() as $provider => $meta) {
        if (ai_provider_configured($provider)) {
            $configured[] = $provider;
        }
    }
    return $configured;
}

function ai_any_configured(): bool
{
    return ai_configured_providers() !== [];
}

function ai_document_capable_configured(): bool
{
    foreach (ai_providers() as $provider => $meta) {
        if ($meta['supports_documents'] && ai_provider_configured($provider)) {
            return true;
        }
    }
    return false;
}

function ai_default_provider(): string
{
    $configured = ai_configured_providers();
    if ($configured === []) {
        return 'claude';
    }
    $saved = (string) get_setting('ai_default_provider', '');
    if ($saved !== '' && in_array($saved, $configured, true)) {
        return $saved;
    }
    return $configured[0];
}

function ai_fallback_provider(): string
{
    $configured = ai_configured_providers();
    if ($configured === []) {
        return '';
    }
    $default = ai_default_provider();
    $saved = (string) get_setting('ai_fallback_provider', '');
    if ($saved !== '' && $saved !== $default && in_array($saved, $configured, true)) {
        return $saved;
    }
    return '';
}

function ai_selected_model(string $settingKey, string $provider): string
{
    $models = ai_provider_models($provider);
    if ($models === []) {
        return '';
    }
    $saved = (string) get_setting($settingKey, '');
    if ($saved !== '' && ai_model_in_provider($provider, $saved)) {
        return $saved;
    }
    return $models[0];
}

function ai_chain(): array
{
    $chain = [];
    $default = ai_default_provider();
    if ($default !== '') {
        $chain[] = [
            'provider' => $default,
            'model' => ai_selected_model('ai_default_model', $default),
        ];
    }
    $fallback = ai_fallback_provider();
    if ($fallback !== '' && $fallback !== $default) {
        $chain[] = [
            'provider' => $fallback,
            'model' => ai_selected_model('ai_fallback_model', $fallback),
        ];
    }
    return $chain;
}

function ai_config_hint(): string
{
    $envs = array_map(static fn (array $meta): string => $meta['env'], ai_providers());
    return 'No AI provider is configured. Add at least one of ' . implode(', ', $envs)
        . ' to the server .env file to enable AI features.';
}

function ai_unconfigured_message(): string
{
    return 'No AI provider key is configured, so this can\'t run yet. '
        . 'Set CLAUDE_API_KEY, OPENAI_API_KEY or DEEPSEEK_API_KEY in the server .env file.';
}

function ai_openai_compatible_chat(string $url, string $model, string $apiKey, array $messages, string $system, int $maxTokens, string $vendor): ?string
{
    $body = json_encode([
        'model' => $model,
        'max_tokens' => $maxTokens,
        'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => AI_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log($vendor . ' API error (' . $status . '): '
            . ($error !== '' ? $error : substr((string) $response, 0, 500)));
        return null;
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        error_log($vendor . ' API returned non-JSON: ' . substr((string) $response, 0, 500));
        return null;
    }

    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || trim($content) === '') {
        $refusal = $decoded['choices'][0]['message']['refusal'] ?? null;
        error_log($vendor . ' returned no text content'
            . (is_string($refusal) && $refusal !== '' ? ' (refusal): ' . $refusal : ''));
        return null;
    }

    return $content;
}

function ai_openai_chat(array $messages, string $system, int $maxTokens, string $model): ?string
{
    $key = getenv('OPENAI_API_KEY');
    if (!is_string($key) || trim($key) === '') {
        return null;
    }
    return ai_openai_compatible_chat(
        AI_OPENAI_URL,
        $model,
        trim($key),
        $messages,
        $system,
        $maxTokens,
        'OpenAI'
    );
}

function ai_deepseek_chat(array $messages, string $system, int $maxTokens, string $model): ?string
{
    $key = getenv('DEEPSEEK_API_KEY');
    if (!is_string($key) || trim($key) === '') {
        return null;
    }
    return ai_openai_compatible_chat(
        AI_DEEPSEEK_URL,
        $model,
        trim($key),
        $messages,
        $system,
        $maxTokens,
        'DeepSeek'
    );
}

function ai_openai_complete(string $system, string $userPrompt, int $maxTokens, string $model): ?string
{
    return ai_openai_chat([['role' => 'user', 'content' => $userPrompt]], $system, $maxTokens, $model);
}

function ai_deepseek_complete(string $system, string $userPrompt, int $maxTokens, string $model): ?string
{
    return ai_deepseek_chat([['role' => 'user', 'content' => $userPrompt]], $system, $maxTokens, $model);
}

function ai_chat_with(string $provider, string $model, array $messages, string $system, int $maxTokens): ?string
{
    switch ($provider) {
        case 'claude':
            return claude_chat($messages, $system, $maxTokens, $model);
        case 'openai':
            return ai_openai_chat($messages, $system, $maxTokens, $model);
        case 'deepseek':
            return ai_deepseek_chat($messages, $system, $maxTokens, $model);
        default:
            return null;
    }
}

function ai_chat(array $messages, string $system, int $maxTokens = 8000): ?string
{
    foreach (ai_chain() as $attempt) {
        $answer = ai_chat_with($attempt['provider'], $attempt['model'], $messages, $system, $maxTokens);
        if ($answer !== null) {
            return $answer;
        }
        error_log('ai_chat: provider "' . $attempt['provider'] . '" with model "'
            . $attempt['model'] . '" failed; trying next if any.');
    }
    return null;
}

function ai_complete_with(string $provider, string $model, string $system, string $userPrompt, int $maxTokens, ?string $pdfPath): ?string
{
    switch ($provider) {
        case 'claude':
            return claude_complete($system, $userPrompt, $maxTokens, $pdfPath, $model);
        case 'openai':
            return ai_openai_complete($system, $userPrompt, $maxTokens, $model);
        case 'deepseek':
            return ai_deepseek_complete($system, $userPrompt, $maxTokens, $model);
        default:
            return null;
    }
}

function ai_complete(string $system, string $userPrompt, int $maxTokens = 8000, ?string $pdfPath = null): ?string
{
    foreach (ai_chain() as $attempt) {
        $meta = ai_provider_meta($attempt['provider']);
        if ($pdfPath !== null && is_array($meta) && empty($meta['supports_documents'])) {
            error_log('ai_complete: provider "' . $attempt['provider']
                . '" cannot read attached PDF curriculum documents; skipped.');
            continue;
        }
        $raw = ai_complete_with(
            $attempt['provider'],
            $attempt['model'],
            $system,
            $userPrompt,
            $maxTokens,
            $pdfPath
        );
        if ($raw !== null) {
            return $raw;
        }
        error_log('ai_complete: provider "' . $attempt['provider'] . '" with model "'
            . $attempt['model'] . '" failed; trying next if any.');
    }
    return null;
}
