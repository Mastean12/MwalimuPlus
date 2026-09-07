<?php
/** Superadmin: AI / MwalimuPlus configuration overview. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';
require_once __DIR__ . '/config/ai.php';

$providers = ai_providers();
$configured = ai_configured_providers();
$defaultProvider = ai_default_provider();
$defaultModel = ai_selected_model('ai_default_model', $defaultProvider);
$fallbackProvider = ai_fallback_provider();
$fallbackModel = $fallbackProvider !== '' ? ai_selected_model('ai_fallback_model', $fallbackProvider) : '';

$pageTitle    = 'AI configuration';
$activeNav    = 'admin-ai';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '🤖';
$pageHeading  = 'AI / MwalimuPlus configuration';
$pageSubtitle = 'Claude configuration, system prompts, grounding rules, and the Sijui refusal rule.';
$pageActions  = '<a href="settings.php#panel-ai" class="btn btn-primary">Manage keys &amp; models</a>';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'AI configuration'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="margin-top:0;">
    <h2>Claude / provider configuration</h2>
    <p class="muted">API keys are entered on the Settings page and stored encrypted in the database — no server file access needed. A key saved there always takes priority over the same provider's key in <code>.env</code>.</p>
    <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-top: 1.5rem;">
        <?php foreach ($providers as $id => $meta): ?>
            <?php $isConfigured = ai_provider_configured($id); ?>
            <div class="card">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                    <div>
                        <h3 style="margin: 0;"><?= htmlspecialchars($meta['label']) ?></h3>
                        <p class="muted" style="margin: .25rem 0 0; font-size: .85rem;"><?= htmlspecialchars($meta['vendor']) ?></p>
                    </div>
                    <span style="white-space: nowrap; font-size: .72rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; <?= $isConfigured ? 'color: #15803d; background: #dcfce7;' : 'color: var(--muted); background: var(--surface-2); border: 1px solid var(--line);' ?>"><?= $isConfigured ? '● Connected' : '○ Key missing' ?></span>
                </div>
                <?php if ($isConfigured && $id === $defaultProvider): ?>
                    <p style="margin: .75rem 0 0;"><span class="badge badge-scheduled">Default — <?= htmlspecialchars($defaultModel) ?></span></p>
                <?php elseif ($isConfigured && $id === $fallbackProvider): ?>
                    <p style="margin: .75rem 0 0;"><span class="badge" style="background:var(--surface-2); color:var(--ink);">Fallback — <?= htmlspecialchars($fallbackModel) ?></span></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h2>System prompts</h2>
    <p class="muted">Every lesson/scheme/study-set/chat request builds its system prompt from a fixed template in <code>config/claude.php</code> (<code>claude_system_prompt()</code>), with the matched curriculum source spliced in as <code>SOURCES</code>. It isn't editable from the database yet — changing it means editing that file.</p>
</section>

<section class="panel">
    <h2>Grounding rules</h2>
    <p class="muted">Generation is cite-or-refuse: the model may only answer from the KICD <code>SOURCES</code> text (or attached PDF) for the matched subject/topic — see <code>find_topic_sources()</code> in <code>api/generate-lesson.php</code> and <code>resolve_subject_source()</code> in <code>api/generate-scheme.php</code>. No rule can be relaxed from this panel yet.</p>
</section>

<section class="panel">
    <h2>Sijui / refusal rules</h2>
    <p class="muted">"Sijui" (Swahili: "I don't know") is the refusal path — when nothing in the curriculum source supports an answer, the API returns <code>status: UNKNOWN</code> with a <code>sijui</code> message instead of inventing content. Lessons flagged this way surface under <a href="superadmin-content.php">Lessons &amp; Content</a>.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
