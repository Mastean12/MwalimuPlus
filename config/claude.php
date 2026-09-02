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

/**
 * Generation model. `claude-opus-5` gives the strongest "cite every section or
 * say Sijui" discipline, which is the behaviour the day is judged on. If the
 * live demo feels slow, `claude-sonnet-5` is a one-line swap (faster, cheaper,
 * same request shape).
 */
const CLAUDE_MODEL = 'claude-opus-5';
const CLAUDE_MAX_TOKENS = 2500;

/** cURL timeout. A grounded lesson call runs ~15-30s; give it headroom. */
const CLAUDE_TIMEOUT_SECONDS = 120;

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
The teacher is the only user. You have exactly ONE source of truth: the SOURCES
block below, which is one official KICD strand design split into pages marked
"DESIGN PAGE <n>".

RULES, in priority order:
1. Use ONLY the SOURCES. Treat nothing else as fact — not your training, not
   "standard" teaching practice. If a fact is not on a DESIGN PAGE, it does not
   exist for this task.
2. Cite every section. Each objective, explanation, prerequisite, example,
   question, misconception and note ends with the page it came from, written
   exactly as [design p.<n>] using the DESIGN PAGE numbers in the SOURCES.
   Never invent or guess a page number.
3. Refuse when unsupported. If the requested topic is not directly covered by a
   DESIGN PAGE in the SOURCES, do NOT write a lesson: return {"lesson": null} and
   nothing else. Do not partially answer. Do not fill gaps from general knowledge.
4. Teaching material only: objectives, teacher_explanation, prerequisites,
   examples, board_plan, teacher_questions, common_misconceptions, quick_check,
   teacher_notes.
5. Never request or process learner names, learner work, or individual learner data.
6. Output ONLY the JSON object in the agreed schema — no prose, no markdown fences.

REQUESTED SUBJECT: {$subject}
REQUESTED TOPIC: {$topic}

SOURCES:
{$sources}
PROMPT;
}

/**
 * Builds the grounded system prompt for a scheme of work.
 *
 * Same source-of-truth discipline as claude_system_prompt(): one KICD strand
 * design, cite every row, refuse when unsupported.
 */
function claude_scheme_system_prompt(string $subject, string $strand, string $sources): string
{
    $sources = trim($sources);
    if ($sources === '') {
        $sources = '(No curriculum source text was provided for this subject.)';
    }

    return <<<PROMPT
You are Mwalimu AI, a lesson-prep assistant for a Grade 10 teacher in Kenya.
The teacher is the only user. You have exactly ONE source of truth: the SOURCES
block below, which is one official KICD strand design split into pages marked
"DESIGN PAGE <n>".

RULES, in priority order:
1. Use ONLY the SOURCES. Treat nothing else as fact — not your training, not
   "standard" teaching practice. If a fact is not on a DESIGN PAGE, it does not
   exist for this task.
2. Cite every row. Each row of the scheme ends with the DESIGN PAGE it came
   from in its "reference" field, written exactly as "design p.<n>". Never
   invent or guess a page number.
3. Refuse when unsupported. If the SOURCES do not lay out lessons for this
   strand, do NOT build a scheme: return {"scheme": null} and nothing else. Do
   not partially answer. Do not fill gaps from general knowledge.
4. Scheme-of-work material only: per lesson give sub_strand, specific_outcomes,
   key_inquiry_question, learning_experiences, learning_resources, assessment,
   reference. One row per lesson the DESIGN PAGES define — no invented lessons.
5. Never request or process learner names, learner work, or individual learner data.
6. Output ONLY the JSON object in the agreed schema — no prose, no markdown fences.

REQUESTED SUBJECT: {$subject}
REQUESTED STRAND: {$strand}

SOURCES:
{$sources}
PROMPT;
}

/**
 * Classifies a scheme as SUPPORTED unless the corpus offered nothing to check
 * a row against — i.e. no citations at all, no rows, or any row missing its
 * per-lesson reference. Shared by generation and the save endpoint so a
 * client can never claim SUPPORTED for a scheme that isn't grounded.
 */
function scheme_classify_status(array $rows, $citations): string
{
    if (!is_array($citations) || $citations === [] || $rows === []) {
        return 'NEEDS_VERIFICATION';
    }
    foreach ($rows as $row) {
        if (trim((string) ($row['reference'] ?? '')) === '') {
            return 'NEEDS_VERIFICATION';
        }
    }
    return 'SUPPORTED';
}

/** Calls the Anthropic Messages API and returns the raw text content, or null on failure. */
function claude_complete(string $system, string $userPrompt, int $maxTokens = CLAUDE_MAX_TOKENS): ?string
{
    $key = claude_api_key();
    if ($key === '' || $key === 'YOUR_CLAUDE_API_KEY') {
        return null; // Caller is responsible for surfacing the placeholder message.
    }

    $body = json_encode([
        'model' => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
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
        CURLOPT_TIMEOUT => CLAUDE_TIMEOUT_SECONDS,
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
    if (!is_array($decoded)) {
        error_log('Claude API returned non-JSON: ' . substr((string) $response, 0, 500));
        return null;
    }

    // A safety classifier can decline the request (HTTP 200, stop_reason
    // "refusal") — there is no usable lesson text in that case.
    if (($decoded['stop_reason'] ?? '') === 'refusal') {
        error_log('Claude declined the request: ' . json_encode($decoded['stop_details'] ?? null));
        return null;
    }

    // claude-opus-5 runs adaptive thinking by default, so content[] can lead
    // with a "thinking" block. Take the first real text block, not content[0].
    foreach ($decoded['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text' && isset($block['text']) && is_string($block['text'])) {
            return $block['text'];
        }
    }

    return null;
}
