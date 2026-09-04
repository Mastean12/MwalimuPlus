(function () {
    'use strict';

    // --- Tab Switching ---
    var tabBtns = document.querySelectorAll('.settings-tab');
    var tabPanels = document.querySelectorAll('.settings-panel');

    tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var targetId = e.target.getAttribute('data-target');
            
            // Remove active classes
            tabBtns.forEach(function(b) { b.classList.remove('active'); });
            tabPanels.forEach(function(p) { p.classList.remove('active'); });
            
            // Add active to clicked and target panel
            e.target.classList.add('active');
            var targetPanel = document.getElementById(targetId);
            if (targetPanel) targetPanel.classList.add('active');
        });
    });

    var currentSetting = null;
    var cropper = null;
    var cropperModal = document.getElementById('cropper-modal');
    var cropperImage = document.getElementById('cropper-image');
    var btnCancel = document.getElementById('cropper-cancel');
    var btnSave = document.getElementById('cropper-save');

    function openCropper(file, settingType) {
        currentSetting = settingType;
        var reader = new FileReader();
        reader.onload = function (e) {
            cropperImage.src = e.target.result;
            cropperModal.hidden = false;
            cropperModal.style.display = 'flex';
            
            var aspectRatio = NaN; // free form for logo
            if (settingType === 'favicon') {
                aspectRatio = 1; // 1:1 for favicon
            }

            // Small delay to ensure the modal is visible before initializing cropper
            setTimeout(function() {
                cropper = new Cropper(cropperImage, {
                    aspectRatio: aspectRatio,
                    viewMode: 1,
                    background: false,
                    autoCropArea: 1,
                });
            }, 50);
        };
        reader.readAsDataURL(file);
    }

    function closeCropper() {
        cropperModal.hidden = true;
        cropperModal.style.display = '';
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        cropperImage.src = '';
        currentSetting = null;
        document.getElementById('logo-upload').value = '';
        document.getElementById('favicon-upload').value = '';
    }

    btnCancel.addEventListener('click', closeCropper);

    btnSave.addEventListener('click', function () {
        if (!cropper) return;
        
        btnSave.disabled = true;
        btnSave.textContent = 'Saving...';
        
        var canvas = cropper.getCroppedCanvas({
            width: currentSetting === 'favicon' ? 256 : undefined,
            height: currentSetting === 'favicon' ? 256 : undefined,
            imageSmoothingQuality: 'high'
        });
        
        var dataUrl = canvas.toDataURL('image/png');
        
        fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                setting: currentSetting,
                image: dataUrl
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to upload image.');
                btnSave.disabled = false;
                btnSave.textContent = 'Save & Upload';
            }
        })
        .catch(function () {
            alert('Network error.');
            btnSave.disabled = false;
            btnSave.textContent = 'Save & Upload';
        });
    });

    document.getElementById('logo-upload').addEventListener('change', function (e) {
        if (e.target.files && e.target.files.length > 0) {
            openCropper(e.target.files[0], 'logo');
        }
    });

    document.getElementById('favicon-upload').addEventListener('change', function (e) {
        if (e.target.files && e.target.files.length > 0) {
            openCropper(e.target.files[0], 'favicon');
        }
    });

    // --- Theme Configurations ---
    var colorInputs = {
        topbarBg: document.getElementById('color-topbar-bg'),
        topbarText: document.getElementById('color-topbar-text'),
        sidebarBg: document.getElementById('color-sidebar-bg'),
        sidebarText: document.getElementById('color-sidebar-text'),
        pageBg: document.getElementById('color-page-bg')
    };

    var presets = {
        'default':  { topbarBg: '#ffffff', topbarText: '#2c332e', sidebarBg: '#ffffff', sidebarText: '#2c332e', pageBg: '#f5f6f3' },
        'midnight': { topbarBg: '#1e293b', topbarText: '#f8fafc', sidebarBg: '#0f172a', sidebarText: '#cbd5e1', pageBg: '#020617' },
        'emerald':  { topbarBg: '#064e3b', topbarText: '#ecfdf5', sidebarBg: '#022c22', sidebarText: '#d1fae5', pageBg: '#ecfdf5' },
        'amber':    { topbarBg: '#fffbeb', topbarText: '#78350f', sidebarBg: '#fef3c7', sidebarText: '#92400e', pageBg: '#fff7ed' },
        'ocean':    { topbarBg: '#0c4a6e', topbarText: '#e0f2fe', sidebarBg: '#082f49', sidebarText: '#bae6fd', pageBg: '#f0f9ff' },
        'crimson':  { topbarBg: '#4c0519', topbarText: '#ffe4e6', sidebarBg: '#22000a', sidebarText: '#fecdd3', pageBg: '#fff1f2' },
        'lavender': { topbarBg: '#2e1065', topbarText: '#f3e8ff', sidebarBg: '#1b0840', sidebarText: '#e9d5ff', pageBg: '#faf5ff' },
        'slate':    { topbarBg: '#334155', topbarText: '#f8fafc', sidebarBg: '#1e293b', sidebarText: '#e2e8f0', pageBg: '#f1f5f9' },
        'coffee':   { topbarBg: '#451a03', topbarText: '#fef3c7', sidebarBg: '#290f02', sidebarText: '#fde68a', pageBg: '#fdf8f6' },
        'sunset':   { topbarBg: '#9a3412', topbarText: '#ffedd5', sidebarBg: '#7c2d12', sidebarText: '#fed7aa', pageBg: '#fff7ed' },
        'forest':   { topbarBg: '#14532d', topbarText: '#f0fdf4', sidebarBg: '#052e16', sidebarText: '#bbf7d0', pageBg: '#f0fdf4' },
        'nordic':   { topbarBg: '#e0f2fe', topbarText: '#0c4a6e', sidebarBg: '#f0f9ff', sidebarText: '#0369a1', pageBg: '#ffffff' },
        'neon':     { topbarBg: '#09090b', topbarText: '#d946ef', sidebarBg: '#000000', sidebarText: '#22d3ee', pageBg: '#18181b' },
        'vintage':  { topbarBg: '#d6d3d1', topbarText: '#44403c', sidebarBg: '#e7e5e4', sidebarText: '#57534e', pageBg: '#fafaf9' },
        'spring':   { topbarBg: '#fce7f3', topbarText: '#831843', sidebarBg: '#fdf2f8', sidebarText: '#be185d', pageBg: '#ffffff' },
        'violet':   { topbarBg: '#4c1d95', topbarText: '#ede9fe', sidebarBg: '#2e1065', sidebarText: '#c4b5fd', pageBg: '#f5f3ff' },
        'solar':    { topbarBg: '#f59e0b', topbarText: '#fffbeb', sidebarBg: '#d97706', sidebarText: '#fef3c7', pageBg: '#fffbeb' },
        'monochrome': { topbarBg: '#171717', topbarText: '#f5f5f5', sidebarBg: '#0a0a0a', sidebarText: '#e5e5e5', pageBg: '#262626' },
        'aqua':     { topbarBg: '#0f766e', topbarText: '#f0fdfa', sidebarBg: '#042f2e', sidebarText: '#99f6e4', pageBg: '#f0fdfa' }
    };

    function updateSandbox() {
        if (!colorInputs.topbarBg) return;

        var topbar = document.getElementById('sandbox-topbar');
        var sidebar = document.getElementById('sandbox-sidebar');
        var body = document.getElementById('sandbox-body');
        var brand = document.getElementById('sandbox-brand');
        var nav1 = document.getElementById('sandbox-nav-1');
        var nav2 = document.getElementById('sandbox-nav-2');
        var nav3 = document.getElementById('sandbox-nav-3');
        var text1 = document.getElementById('sandbox-body-text1');
        var text2 = document.getElementById('sandbox-body-text2');
        
        if (topbar && sidebar && body) {
            topbar.style.backgroundColor = colorInputs.topbarBg.value;
            topbar.style.color = colorInputs.topbarText.value;
            
            sidebar.style.backgroundColor = colorInputs.sidebarBg.value;
            sidebar.style.color = colorInputs.sidebarText.value;
            brand.style.color = colorInputs.sidebarText.value;
            nav1.style.color = colorInputs.sidebarText.value;
            nav2.style.color = colorInputs.sidebarText.value;
            nav3.style.color = colorInputs.sidebarText.value;
            
            body.style.backgroundColor = colorInputs.pageBg.value;
            
            // Adjust body text color based on background luminance
            var hex = colorInputs.pageBg.value.replace('#', '');
            if (hex.length === 6) {
                var r = parseInt(hex.substr(0, 2), 16);
                var g = parseInt(hex.substr(2, 2), 16);
                var b = parseInt(hex.substr(4, 2), 16);
                var yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
                var textColor = (yiq >= 128) ? '#2c332e' : '#f5f6f3';
                text1.style.color = textColor;
                text2.style.color = textColor;
            }
        }
    }

    Object.keys(colorInputs).forEach(function(key) {
        if (colorInputs[key]) {
            colorInputs[key].addEventListener('input', updateSandbox);
        }
    });

    document.querySelectorAll('.preset-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var presetKey = e.target.getAttribute('data-preset');
            var preset = presets[presetKey];
            if (preset && colorInputs.topbarBg) {
                colorInputs.topbarBg.value = preset.topbarBg;
                colorInputs.topbarText.value = preset.topbarText;
                colorInputs.sidebarBg.value = preset.sidebarBg;
                colorInputs.sidebarText.value = preset.sidebarText;
                colorInputs.pageBg.value = preset.pageBg;
                updateSandbox();
            }
        });
    });

    updateSandbox();

    var btnSaveTheme = document.getElementById('save-theme-btn');
    if (btnSaveTheme) {
        btnSaveTheme.addEventListener('click', function() {
            btnSaveTheme.disabled = true;
            btnSaveTheme.textContent = 'Saving...';
            
            var payload = {
                action: 'save_theme',
                settings: {
                    theme_topbar_bg: colorInputs.topbarBg.value,
                    theme_topbar_text: colorInputs.topbarText.value,
                    theme_sidebar_bg: colorInputs.sidebarBg.value,
                    theme_sidebar_text: colorInputs.sidebarText.value,
                    theme_page_bg: colorInputs.pageBg.value
                }
            };
            
            fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save layout settings.');
                    btnSaveTheme.disabled = false;
                    btnSaveTheme.textContent = 'Save Layout Settings';
                }
            })
            .catch(function () {
                alert('Network error.');
                btnSaveTheme.disabled = false;
                btnSaveTheme.textContent = 'Save Layout Settings';
            });
        });
    }

    // --- AI Provider Configuration ---
    var selDefault = document.getElementById('ai-default-provider');
    var selDefaultModel = document.getElementById('ai-default-model');
    var selFallback = document.getElementById('ai-fallback-provider');
    var selFallbackModel = document.getElementById('ai-fallback-model');
    var btnSaveAi = document.getElementById('save-ai-btn');
    var aiModels = (window.MW_AI && window.MW_AI.models) || {};
    var aiModelMemory = {};

    if (window.MW_AI) {
        if (window.MW_AI.defaultProvider) {
            aiModelMemory[window.MW_AI.defaultProvider] = window.MW_AI.defaultModel;
        }
        if (window.MW_AI.fallbackProvider) {
            aiModelMemory[window.MW_AI.fallbackProvider] = window.MW_AI.fallbackModel;
        }
    }

    function aiConfiguredOptions() {
        var opts = [];
        if (!selDefault) return opts;
        for (var i = 0; i < selDefault.options.length; i++) {
            opts.push(selDefault.options[i].value);
        }
        return opts;
    }

    function aiProviderModels(provider) {
        return aiModels[provider] || [];
    }

    function aiPopulateModels(provider, modelSelect, initialState) {
        if (!modelSelect) return;
        var models = aiProviderModels(provider);
        var wanted = aiModelMemory[provider] || initialState || '';
        if (models.indexOf(wanted) === -1) {
            wanted = models.length > 0 ? models[0] : '';
        }
        modelSelect.innerHTML = '';
        models.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            if (m === wanted) opt.selected = true;
            modelSelect.appendChild(opt);
        });
        modelSelect.disabled = models.length === 0;
    }

    function aiRememberModel(provider, modelSelect) {
        if (provider && modelSelect) {
            aiModelMemory[provider] = modelSelect.value;
        }
    }

    function aiSyncModelBadges() {
        var defProv = selDefault ? selDefault.value : '';
        var defModel = selDefaultModel ? selDefaultModel.value : '';
        var fallProv = (selFallback && !selFallback.disabled) ? selFallback.value : '';
        var fallModel = (selFallbackModel && !selFallbackModel.disabled) ? selFallbackModel.value : '';

        var allBadges = document.querySelectorAll('.ai-model-badge');
        allBadges.forEach(function (badge) {
            var p = badge.getAttribute('data-provider');
            var m = badge.getAttribute('data-model');

            badge.classList.remove('is-default-active', 'is-fallback-active');
            var oldTag = badge.querySelector('.active-tag');
            if (oldTag) oldTag.remove();

            if (p === defProv && m === defModel) {
                badge.classList.add('is-default-active');
                var span = document.createElement('span');
                span.className = 'active-tag';
                span.textContent = '✓ Active Default';
                badge.appendChild(span);
            } else if (fallProv && p === fallProv && m === fallModel) {
                badge.classList.add('is-fallback-active');
                var span = document.createElement('span');
                span.className = 'active-tag';
                span.textContent = '✓ Fallback';
                badge.appendChild(span);
            }
        });
    }

    function aiSyncModelSelects() {
        if (selDefault && selDefaultModel) {
            aiPopulateModels(selDefault.value, selDefaultModel,
                window.MW_AI && window.MW_AI.defaultProvider === selDefault.value
                    ? window.MW_AI.defaultModel : '');
        }
        if (selFallback && selFallbackModel) {
            var hasFallback = selFallback.value !== '' && !selFallback.disabled;
            if (hasFallback) {
                aiPopulateModels(selFallback.value, selFallbackModel,
                    window.MW_AI && window.MW_AI.fallbackProvider === selFallback.value
                        ? window.MW_AI.fallbackModel : '');
            } else {
                selFallbackModel.innerHTML = '';
                selFallbackModel.disabled = true;
            }
        }
        aiSyncModelBadges();
    }

    function aiSyncFallback() {
        if (!selDefault || !selFallback) return;
        selFallback.disabled = aiConfiguredOptions().length < 2;
        if (selFallback.value === selDefault.value) {
            selFallback.value = '';
        }
        aiSyncModelSelects();
    }

    if (selDefault && selDefaultModel) {
        selDefault.addEventListener('change', function () {
            aiRememberModel(selDefault.value, selDefaultModel);
            aiSyncFallback();
        });
        selDefaultModel.addEventListener('change', function () {
            aiRememberModel(selDefault.value, selDefaultModel);
            aiSyncModelBadges();
        });
    }
    if (selFallback && selFallbackModel) {
        selFallback.addEventListener('change', function () {
            aiRememberModel(selFallback.value, selFallbackModel);
            aiSyncModelSelects();
        });
        selFallbackModel.addEventListener('change', function () {
            aiRememberModel(selFallback.value, selFallbackModel);
            aiSyncModelBadges();
        });
    }
    aiSyncFallback();

    document.querySelectorAll('.ai-model-badge').forEach(function (badge) {
        badge.addEventListener('click', function () {
            var provider = badge.getAttribute('data-provider');
            var model = badge.getAttribute('data-model');

            var providerOption = selDefault ? selDefault.querySelector('option[value="' + provider + '"]') : null;
            if (!providerOption) {
                alert('This provider key is missing in your .env configuration.');
                return;
            }

            selDefault.value = provider;
            aiRememberModel(provider, selDefaultModel);
            aiSyncFallback();
            if (selDefaultModel) selDefaultModel.value = model;
            aiRememberModel(provider, selDefaultModel);
            aiSyncModelBadges();
        });
    });

    if (btnSaveAi) {
        btnSaveAi.addEventListener('click', function() {
            if (!selDefault || !selFallback) return;
            btnSaveAi.disabled = true;
            btnSaveAi.textContent = 'Saving...';

            var fallbackActive = !selFallback.disabled && selFallback.value !== '';
            var payload = {
                action: 'save_ai',
                settings: {
                    ai_default_provider: selDefault.value,
                    ai_default_model: selDefaultModel ? selDefaultModel.value : '',
                    ai_fallback_provider: fallbackActive ? selFallback.value : '',
                    ai_fallback_model: fallbackActive && selFallbackModel ? selFallbackModel.value : ''
                }
            };

            fetch('api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to save AI settings.');
                    btnSaveAi.disabled = false;
                    btnSaveAi.textContent = 'Save AI Settings';
                }
            })
            .catch(function () {
                alert('Network error.');
                btnSaveAi.disabled = false;
                btnSaveAi.textContent = 'Save AI Settings';
            });
        });
    }

})();
