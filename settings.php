<?php
/**
 * Settings: Configure global application settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/config/ai.php';

$pageTitle = 'Settings';
$activeNav = 'settings';
$showHeader = true;
$pageIcon = '⚙️';
$pageHeading = 'Settings';
$pageSubtitle = 'Configure global application settings like logo, favicon, layout and AI providers.';

require __DIR__ . '/includes/header.php';
?>

<style>
    .settings-tabs { display: inline-flex; background: var(--surface-2); padding: 0.35rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--line); }
    .settings-tab { background: transparent; border: none; padding: .6rem 1.5rem; font-size: .95rem; font-weight: 600; color: var(--muted); cursor: pointer; transition: all 0.2s ease; border-radius: calc(var(--radius-lg) - 0.25rem); font-family: inherit; }
    .settings-tab:hover { color: var(--ink); }
    .settings-tab.active { color: var(--ink); background: var(--surface); box-shadow: var(--shadow-sm); }
    .settings-panel { display: none; }
    .settings-panel.active { display: block; }
</style>

<div class="settings-tabs">
    <button type="button" class="settings-tab active" data-target="panel-branding">Branding</button>
    <button type="button" class="settings-tab" data-target="panel-theme">Theme Layout</button>
    <button type="button" class="settings-tab" data-target="panel-ai">AI</button>
</div>

<div id="panel-branding" class="settings-panel active">
<section class="panel">
    <h2>Branding</h2>
    <p class="muted">Upload a custom logo and favicon for your application.</p>
    
    <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top: 2rem;">
        <div class="card">
            <h3>Logo</h3>
            <p class="muted" style="margin-bottom: 1rem;">Displayed in the top left of the sidebar.</p>
            <div style="margin: 1rem 0; min-height: 90px; display: flex; align-items: center;">
                <?php if ($logo = get_setting('logo_url')): ?>
                    <img src="<?= htmlspecialchars($logo) ?>" alt="Current Logo" style="max-height: 80px; max-width: 100%; border: 1px solid var(--line); border-radius: 4px; padding: .5rem; background: #fff;">
                <?php else: ?>
                    <div style="padding: 1rem; width: 100%; border: 1px dashed var(--line); color: var(--muted); text-align: center; border-radius: 4px;">No logo set</div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('logo-upload').click()">Upload Logo</button>
            <input type="file" id="logo-upload" accept="image/png, image/jpeg, image/svg+xml" hidden data-setting="logo">
        </div>
        
        <div class="card">
            <h3>Favicon</h3>
            <p class="muted" style="margin-bottom: 1rem;">Displayed in the browser tab (must be square).</p>
            <div style="margin: 1rem 0; min-height: 90px; display: flex; align-items: center;">
                <?php if ($fav = get_setting('favicon_url')): ?>
                    <img src="<?= htmlspecialchars($fav) ?>" alt="Current Favicon" style="height: 64px; width: 64px; border: 1px solid var(--line); border-radius: 4px; padding: .5rem; object-fit: contain; background: #fff;">
                <?php else: ?>
                    <div style="height: 64px; width: 64px; border: 1px dashed var(--line); color: var(--muted); display: flex; align-items: center; justify-content: center; border-radius: 4px;">None</div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline" onclick="document.getElementById('favicon-upload').click()">Upload Favicon</button>
            <input type="file" id="favicon-upload" accept="image/png, image/jpeg, image/x-icon" hidden data-setting="favicon">
        </div>
    </div>
</section>
</div>

<div id="panel-theme" class="settings-panel">
<section class="panel">
    <h2>Theme Configurations</h2>
    <p class="muted">Customize the colors of your topbar, sidebar, and page background, or choose a preset.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 3rem; margin-top: 2rem;">
        
        <div>
            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1rem; font-weight: 500;">Or choose a layout preset:</h3>
            <div class="card-grid" style="grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-bottom: 2rem; max-height: 200px; overflow-y: auto; padding-right: 10px; border-bottom: 1px solid var(--line); padding-bottom: 1rem;">
                <button type="button" class="btn btn-outline preset-btn" data-preset="default">Default</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="midnight">Midnight</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="emerald">Emerald</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="amber">Royal Amber</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="ocean">Ocean Breeze</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="crimson">Crimson Rose</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="lavender">Lavender Dream</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="slate">Slate Minimal</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="coffee">Cozy Coffee</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="sunset">Sunset Glow</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="forest">Deep Forest</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="nordic">Nordic Winter</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="neon">Neon Nights</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="vintage">Vintage Sepia</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="spring">Pastel Spring</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="violet">Midnight Violet</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="solar">Solar Flare</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="monochrome">Monochrome Dark</button>
                <button type="button" class="btn btn-outline preset-btn" data-preset="aqua">Aqua Marine</button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Topbar Background</label>
                    <input type="color" id="color-topbar-bg" value="<?= htmlspecialchars(get_setting('theme_topbar_bg') ?: '#ffffff') ?>" style="width: 100%; height: 48px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0; cursor: pointer;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Topbar Text / Brand</label>
                    <input type="color" id="color-topbar-text" value="<?= htmlspecialchars(get_setting('theme_topbar_text') ?: '#2c332e') ?>" style="width: 100%; height: 48px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0; cursor: pointer;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Sidebar Background</label>
                    <input type="color" id="color-sidebar-bg" value="<?= htmlspecialchars(get_setting('theme_sidebar_bg') ?: '#ffffff') ?>" style="width: 100%; height: 48px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0; cursor: pointer;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Sidebar Links Color</label>
                    <input type="color" id="color-sidebar-text" value="<?= htmlspecialchars(get_setting('theme_sidebar_text') ?: '#2c332e') ?>" style="width: 100%; height: 48px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0; cursor: pointer;">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Global Page Background</label>
                    <input type="color" id="color-page-bg" value="<?= htmlspecialchars(get_setting('theme_page_bg') ?: '#f5f6f3') ?>" style="width: 100%; height: 48px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0; cursor: pointer;">
                </div>
            </div>
            
            <button type="button" class="btn btn-primary" id="save-theme-btn" style="width: 100%; padding: .8rem; font-size: 1.05rem;">Save Layout Settings</button>
        </div>

        <div>
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: .5rem; font-size: 1rem;"><span aria-hidden="true">👁️</span> Theme Sandbox Preview</h3>
            <div class="theme-sandbox-preview" style="border: 1px solid var(--line); border-radius: var(--radius-lg); overflow: hidden; height: 380px; display: flex; box-shadow: var(--shadow-1); background: var(--canvas);">
                
                <!-- Sidebar Preview -->
                <div id="sandbox-sidebar" style="width: 32%; border-right: 1px solid var(--line); display: flex; flex-direction: column; padding: 1.25rem 1rem; transition: background-color 0.2s, color 0.2s;">
                    <div id="sandbox-brand" style="font-weight: 800; margin-bottom: 2rem; font-family: var(--font-display); font-size: 1.1rem; line-height: 1.1; transition: color 0.2s;">MwalimuPlus</div>
                    <div style="display: flex; flex-direction: column; gap: .35rem;">
                        <div id="sandbox-nav-1" style="background: rgba(0,0,0,0.06); padding: .65rem; border-radius: var(--radius-sm); font-weight: 600; font-size: .8rem; transition: color 0.2s;">Dashboard</div>
                        <div id="sandbox-nav-2" style="padding: .65rem; font-size: .8rem; opacity: 0.8; font-weight: 500; transition: color 0.2s;">Subjects</div>
                        <div id="sandbox-nav-3" style="padding: .65rem; font-size: .8rem; opacity: 0.8; font-weight: 500; transition: color 0.2s;">Settings</div>
                    </div>
                </div>
                
                <!-- Main Area Preview -->
                <div style="flex: 1; display: flex; flex-direction: column;">
                    
                    <!-- Topbar Preview -->
                    <div id="sandbox-topbar" style="height: 54px; border-bottom: 1px solid var(--line); display: flex; align-items: center; padding: 0 1.25rem; font-size: .85rem; font-weight: 600; transition: background-color 0.2s, color 0.2s; font-family: var(--font-display);">
                        Home / Settings
                    </div>
                    
                    <!-- Body Preview -->
                        <div id="sandbox-body" style="flex: 1; padding: 1.5rem; transition: background-color 0.2s;">
                        <h1 id="sandbox-body-text1" style="font-size: 1.25rem; margin-top: 0; transition: color 0.2s;">Preview Dashboard</h1>
                        <p id="sandbox-body-text2" style="font-size: .8rem; opacity: 0.7; margin-bottom: 1.5rem; transition: color 0.2s;">This is a live sandbox of your portal theme layout structure.</p>
                        <div style="background: rgba(255,255,255,0.7); border: 1px solid var(--line); border-radius: var(--radius); height: 140px; display: flex; flex-direction: column; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                             <div style="height: 12px; background: rgba(0,0,0,0.1); width: 35%; border-radius: 10px; margin-bottom: auto;"></div>
                             <div style="height: 8px; background: rgba(0,0,0,0.05); width: 100%; border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
</div>

<?php
$aiAllProviders = ai_providers();
$aiConfiguredList = ai_configured_providers();
$aiHasProviders = $aiConfiguredList !== [];
$aiActiveDefault = ai_default_provider();
$aiDefaultSaved = (string) get_setting('ai_default_provider', '');
$aiFallbackSaved = (string) get_setting('ai_fallback_provider', '');
$aiDefaultSelected = $aiDefaultSaved !== '' && in_array($aiDefaultSaved, $aiConfiguredList, true)
    ? $aiDefaultSaved
    : ($aiConfiguredList[0] ?? '');
$aiFallbackSelected = $aiFallbackSaved !== '' && in_array($aiFallbackSaved, $aiConfiguredList, true)
    ? $aiFallbackSaved
    : '';
$aiHasFallbackOption = count($aiConfiguredList) > 1;
$aiModelsByProvider = [];
foreach ($aiAllProviders as $id => $meta) {
    $aiModelsByProvider[$id] = ai_provider_models($id);
}
$aiDefaultProviderRef = $aiDefaultSelected !== '' ? $aiDefaultSelected : ($aiConfiguredList[0] ?? 'claude');
$aiFallbackProviderRef = $aiFallbackSelected !== '' ? $aiFallbackSelected : ($aiConfiguredList[1] ?? $aiDefaultProviderRef);
$aiDefaultModelSelected = ai_selected_model('ai_default_model', $aiDefaultProviderRef);
$aiFallbackModelSelected = ai_selected_model('ai_fallback_model', $aiFallbackProviderRef);
?>

<div id="panel-ai" class="settings-panel">
<section class="panel">
    <h2>AI Providers</h2>
    <p class="muted">Choose which AI provider and model the app uses by default, plus a fallback provider (and model) it automatically switches to if the default fails or times out. API keys are read from the server's <code>.env</code> file — they are never stored in the database or shown here.</p>

    <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-top: 2rem;">
        <?php foreach ($aiAllProviders as $id => $meta): ?>
            <?php
            $isConfigured = ai_provider_configured($id);
            $isDefault = $isConfigured && $id === $aiActiveDefault;
            $providerModels = ai_provider_models($id);
            ?>
            <div class="card" style="display: flex; flex-direction: column; gap: .5rem;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                    <div>
                        <h3 style="margin: 0;"><?= htmlspecialchars($meta['label']) ?></h3>
                        <p class="muted" style="margin: .25rem 0 0; font-size: .85rem;"><?= htmlspecialchars($meta['vendor']) ?></p>
                    </div>
                    <span style="white-space: nowrap; font-size: .72rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; <?= $isConfigured ? 'color: #15803d; background: #dcfce7;' : 'color: var(--muted); background: var(--surface-2); border: 1px solid var(--line);' ?>"><?= $isConfigured ? '● Connected' : '○ Key missing' ?></span>
                </div>
                <div style="font-size: .85rem;">
                    <span style="display: block; font-weight: 600; margin-bottom: .4rem;">Models:</span>
                    <div style="display: flex; flex-wrap: wrap; gap: .35rem;" class="model-badge-container">
                    <?php foreach ($providerModels as $m): ?>
                        <?php
                        $isDefaultModel = ($id === $aiDefaultProviderRef && $m === $aiDefaultModelSelected);
                        $isFallbackModel = ($id === $aiFallbackProviderRef && $m === $aiFallbackModelSelected && $aiFallbackSelected !== '');
                        ?>
                        <code class="ai-model-badge <?= $isDefaultModel ? 'is-default-active' : ($isFallbackModel ? 'is-fallback-active' : '') ?>"
                              data-provider="<?= htmlspecialchars($id) ?>"
                              data-model="<?= htmlspecialchars($m) ?>"
                              style="cursor: pointer;"
                              title="Click to select model">
                            <?= htmlspecialchars($m) ?>
                            <?php if ($isDefaultModel): ?>
                                <span class="active-tag">✓ Active Default</span>
                            <?php elseif ($isFallbackModel): ?>
                                <span class="active-tag">✓ Fallback</span>
                            <?php endif; ?>
                        </code>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($isConfigured && $isDefault): ?>
                    <span style="align-self: flex-start; font-size: .72rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; color: var(--green-700, #166534); background: var(--green-100, #dcfce7);">Default</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($aiHasProviders): ?>
    <div style="margin-top: 2rem; max-width: 640px; display: grid; gap: 1.4rem;">
        <div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label for="ai-default-provider" style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Default AI Provider</label>
                    <select id="ai-default-provider">
                        <?php foreach ($aiConfiguredList as $id): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= $id === $aiDefaultSelected ? 'selected' : '' ?>><?= htmlspecialchars($aiAllProviders[$id]['label'] ?? $id) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ai-default-model" style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Default AI Model</label>
                    <select id="ai-default-model">
                        <?php foreach ($aiModelsByProvider[$aiDefaultProviderRef] ?? [] as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $aiDefaultModelSelected ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="muted" style="margin: .5rem 0 0; font-size: .85rem;">Used for lesson, scheme and study-material generation and the grounded lesson chat.</p>
        </div>

        <div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label for="ai-fallback-provider" style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Fallback AI Provider</label>
                    <select id="ai-fallback-provider" <?= $aiHasFallbackOption ? '' : 'disabled' ?>>
                        <option value="" <?= $aiFallbackSelected === '' ? 'selected' : '' ?>>None</option>
                        <?php foreach ($aiConfiguredList as $id): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= $id === $aiFallbackSelected ? 'selected' : '' ?>><?= htmlspecialchars($aiAllProviders[$id]['label'] ?? $id) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ai-fallback-model" style="display: block; font-weight: 600; font-size: .75rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em;">Fallback AI Model</label>
                    <select id="ai-fallback-model" <?= ($aiHasFallbackOption && $aiFallbackSelected !== '') ? '' : 'disabled' ?>>
                        <?php foreach ($aiModelsByProvider[$aiFallbackProviderRef] ?? [] as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $aiFallbackModelSelected ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="muted" style="margin: .5rem 0 0; font-size: .85rem;">If the default provider fails or times out, the request is retried with the fallback provider and model automatically.</p>
            <?php if (!$aiHasFallbackOption): ?>
                <p class="muted" style="margin: .5rem 0 0; font-size: .85rem;">Add a second provider key in <code>.env</code> to enable a fallback.</p>
            <?php endif; ?>
        </div>

        <div>
            <button type="button" class="btn btn-primary" id="save-ai-btn" style="padding: .8rem 1.6rem; font-size: 1.05rem;">Save AI Settings</button>
        </div>
    </div>
    <?php else: ?>
    <div style="margin-top: 2rem; padding: 1.25rem 1.5rem; border: 1px dashed var(--line-strong, var(--line)); border-radius: var(--radius);">
        <h3 style="margin-top: 0;">No AI providers configured</h3>
        <p class="muted" style="margin: 0 0 .75rem;">AI features are turned off until at least one provider key is added.</p>
        <p style="margin: 0; font-size: .9rem;">Edit the server's <code>.env</code> file and add any of:<br>
            <code>CLAUDE_API_KEY</code>, <code>OPENAI_API_KEY</code>, <code>DEEPSEEK_API_KEY</code></p>
    </div>
    <?php endif; ?>
</section>
</div>

<!-- Cropper Modal -->
<div id="cropper-modal" hidden style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--surface); padding: 1.5rem; border-radius: var(--radius); width: 90%; max-width: 600px; display: flex; flex-direction: column;">
        <h2 style="margin-top: 0;">Crop Image</h2>
        <div style="flex: 1; height: 50vh; background: #333; margin-bottom: 1.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            <img id="cropper-image" src="" alt="Crop preview" style="max-width: 100%; max-height: 100%; display: block;">
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" id="cropper-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="cropper-save">Save &amp; Upload</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
window.MW_AI = {
    models: <?= json_encode($aiModelsByProvider) ?>,
    defaultProvider: <?= json_encode($aiDefaultSelected) ?>,
    defaultModel: <?= json_encode($aiDefaultModelSelected) ?>,
    fallbackProvider: <?= json_encode($aiFallbackSelected) ?>,
    fallbackModel: <?= json_encode($aiFallbackModelSelected) ?>
};
</script>
<script src="assets/js/settings.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
