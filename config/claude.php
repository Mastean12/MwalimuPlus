<?php
/**
 * MwalimuPlus Claude integration.
 *
 * Config + thin cURL wrapper around the Anthropic Messages API.
 * The API key is a PLACEHOLDER: set CLAUDE_API_KEY in your environment,
 * or edit the constant below. Prefer an environment variable so the key
 * never lives in the repo.
 */

declare(strict_types=1);

const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
const CLAUDE_MODEL = 'claude-3-5-haiku-latest';
const CLAUDE_MAX_TOKENS = 2048;

/** Placeholder only — replace with your real key or set CLAUDE_API_KEY in the environment. */
function claude_api_key(): string
{
    $key = getenv('CLAUDE_API_KEY');
    if ($key === false || $key === '') {
        return 'YOUR_CLAUDE_API_KEY';
    }
    return $key;
}

/** Loads the KICD strand design text bundled on the server (the demo corpus). */
function load_strand_source(string $sourceFile): string
{
    $path = realpath(__DIR__ . '/../content/' . basename($sourceFile));
    if ($path === false || !is_file($path)) {
        return '';
    }
    $text = @file_get_contents($path);
    return $text === false ? '' : $text;
}

/** Builds the grounded system prompt with the corpus injected as SOURCES. */
function claude_system_prompt(string $subject, string $topic, string $sources): string
{
    $sources = trim($sources);
    if ($sources === '') {
        $sources = '(No curriculum source text was provided for this subject/topic.)';
    }

    return <<<PROMPT
You are Mwalimu AI, a lesson-prep assistant for a Grade 10 teacher in Kenya.
The teacher is the only user.

RULES, in priority order:
1. Use ONLY the SOURCES below (the KICD strand design). Treat nothing else as fact.
2. Every section you output cites the source it came from, e.g. [design p.14].
3. If the SOURCES don't support the request, do not generate a lesson. Return
   status "UNKNOWN" and a sijui message naming the section or office to check.
   Never guess. Never use general knowledge. Never fabricate a citation.
4. Produce teaching material only: objectives, explanation, prerequisites,
   examples, board plan, teacher questions, misconceptions, quick check, notes.
5. Never request or process learner names, learner work, or individual learner data.
6. Start output with the disclosure line. Return ONLY valid JSON in the agreed schema.

REQUESTED SUBJECT: {$subject}
REQUESTED TOPIC: {$topic}

SOURCES:
{$sources}
PROMPT;
}

/** Calls the Anthropic Messages API and returns the raw text content, or null on failure. */
function claude_complete(string $system, string $userPrompt): ?string
{
    $key = claude_api_key();
    if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
        return null; // Caller is responsible for surfacing the placeholder message.
    }

    $body = json_encode([
        'model' => CLAUDE_MODEL,
        'max_tokens' => CLAUDE_MAX_TOKENS,
        'system' => $system,
        'messages' => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ]);

    $ch = curl_init(CLAUDE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('Claude API error (' . $status . '): ' . ($error ?: $response));
        return null;
    }

    $decoded = json_decode($response, true);
    $text = $decoded['content'][0]['text'] ?? null;

    return is_string($text) ? $text : null;
}
