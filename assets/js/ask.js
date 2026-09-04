/* MwalimuPlus — grounded lesson chat, audio visualizers & rich error notices (ask.php) */
(function () {
    'use strict';

    var root = document.querySelector('.chat[data-lesson-id]');
    if (!root) {
        return;
    }
    var lessonId = root.getAttribute('data-lesson-id');
    var log = document.getElementById('chat-log');
    var form = document.getElementById('chat-form');
    var input = document.getElementById('chat-input');
    var sendBtn = document.getElementById('chat-send');
    var micBtn = document.getElementById('chat-mic');

    var history = []; // [{ role, content }]
    var activeSpeech = null; // Current SpeechSynthesisUtterance
    var activeMsgElem = null; // Currently speaking message element
    var activeListenBtn = null; // Currently active listen button
    var activePlayerDisplay = null; // Currently active audio display element
    var progressTimer = null; // Interval for updating progress bar

    function escapeHtml(v) {
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /* Floating Toast Notice Banner for transient alerts */
    function showToastNotice(message, type) {
        type = type || 'warn';
        var toast = document.createElement('div');
        toast.className = 'chat-toast-notice toast-' + type;
        
        var icon = type === 'error' ? '🛑' : (type === 'warn' ? '⚠️' : 'ℹ️');
        toast.innerHTML = '' +
            '<div style="display:flex; align-items:center; gap:0.5rem;">' +
                '<span>' + icon + '</span>' +
                '<span>' + escapeHtml(message) + '</span>' +
            '</div>' +
            '<button type="button" class="chat-toast-close" title="Dismiss">&times;</button>';

        form.parentNode.insertBefore(toast, form);

        var closeBtn = toast.querySelector('.chat-toast-close');
        var timer = setTimeout(function () {
            toast.remove();
        }, 4500);

        closeBtn.addEventListener('click', function () {
            clearTimeout(timer);
            toast.remove();
        });
    }

    /* Create a rich Error Card inside chat log with Retry and Edit action buttons */
    function addErrorCard(type, title, message, lastQuery) {
        var card = document.createElement('div');
        card.className = 'chat-error-card error-' + type;

        var badgeLabel = type === 'server' ? '⚠️ Server Error' : (type === 'unknown' ? 'ℹ️ Out of Scope' : '🌐 Network Error');
        
        var html = '' +
            '<div class="chat-error-header">' +
                '<span class="chat-error-badge">' + badgeLabel + '</span>' +
            '</div>' +
            '<h4 class="chat-error-title">' + escapeHtml(title) + '</h4>' +
            '<p class="chat-error-body">' + escapeHtml(message).replace(/\n/g, '<br>') + '</p>';

        if (type === 'unknown') {
            html += '<p style="font-size:0.8rem; color:var(--muted); margin-top:0.3rem;">💡 <em>Tip: Ask questions grounded in specific KICD strand outcomes, key inquiry questions, or lesson plans.</em></p>';
        }

        if (lastQuery) {
            html += '' +
                '<div class="chat-error-actions">' +
                    '<button type="button" class="btn-error-retry">🔄 Retry Question</button>' +
                    '<button type="button" class="btn-error-edit">✏️ Edit Input</button>' +
                '</div>';
        }

        card.innerHTML = html;
        log.appendChild(card);
        log.scrollTop = log.scrollHeight;

        if (lastQuery) {
            var retryBtn = card.querySelector('.btn-error-retry');
            var editBtn = card.querySelector('.btn-error-edit');

            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    input.value = lastQuery;
                    form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            }

            if (editBtn) {
                editBtn.addEventListener('click', function () {
                    input.value = lastQuery;
                    input.focus();
                    input.classList.add('chat-input-shake');
                    setTimeout(function () { input.classList.remove('chat-input-shake'); }, 400);
                });
            }
        }

        return card;
    }

    /* Extract clean plain text for text-to-speech reading */
    function getCleanText(element) {
        var clone = element.cloneNode(true);
        var toRemove = clone.querySelectorAll('.chat-msg-actions, .audio-player-display, .disclosure, .chat-error-actions');
        for (var i = 0; i < toRemove.length; i++) {
            toRemove[i].remove();
        }
        return (clone.textContent || '').trim();
    }

    /* Stop any active TTS audio playback and remove display widgets */
    function stopCurrentSpeech() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        if (activeListenBtn) {
            activeListenBtn.classList.remove('is-active');
            activeListenBtn.innerHTML = '<span class="listen-icon">🔊</span><span class="listen-label">Listen</span>';
        }
        if (activePlayerDisplay) {
            activePlayerDisplay.remove();
        }
        activeSpeech = null;
        activeMsgElem = null;
        activeListenBtn = null;
        activePlayerDisplay = null;
    }

    /* Create animated audio visualizer display box */
    function createAudioDisplayWidget(parentMsg, listenBtn, text) {
        var widget = document.createElement('div');
        widget.className = 'audio-player-display';
        
        widget.innerHTML = '' +
            '<div class="audio-player-header">' +
                '<div class="audio-status-pill">' +
                    '<span class="audio-live-dot"></span>' +
                    '<span class="audio-status-text">Playing audio response…</span>' +
                '</div>' +
                '<div class="audio-controls">' +
                    '<button type="button" class="audio-ctrl-btn btn-pause" title="Pause / Resume">⏸️</button>' +
                    '<button type="button" class="audio-ctrl-btn btn-stop" title="Stop audio">⏹️</button>' +
                '</div>' +
            '</div>' +
            '<div class="audio-visualizer-bars">' +
                '<span class="vbar" style="animation-delay: 0s; height: 40%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.15s; height: 80%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.3s; height: 60%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.08s; height: 95%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.22s; height: 50%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.35s; height: 85%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.1s; height: 70%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.28s; height: 100%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.18s; height: 45%;"></span>' +
                '<span class="vbar" style="animation-delay: 0.05s; height: 75%;"></span>' +
            '</div>' +
            '<div class="audio-progress-track">' +
                '<div class="audio-progress-fill"></div>' +
            '</div>';

        parentMsg.appendChild(widget);

        var pauseBtn = widget.querySelector('.btn-pause');
        var stopBtn = widget.querySelector('.btn-stop');

        pauseBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!('speechSynthesis' in window)) return;
            if (window.speechSynthesis.paused) {
                window.speechSynthesis.resume();
                pauseBtn.textContent = '⏸️';
                widget.querySelector('.audio-status-text').textContent = 'Playing audio response…';
                widget.querySelector('.audio-visualizer-bars').classList.remove('is-paused');
            } else if (window.speechSynthesis.speaking) {
                window.speechSynthesis.pause();
                pauseBtn.textContent = '▶️';
                widget.querySelector('.audio-status-text').textContent = 'Audio paused';
                widget.querySelector('.audio-visualizer-bars').classList.add('is-paused');
            }
        });

        stopBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            stopCurrentSpeech();
        });

        return widget;
    }

    /* Handle SpeechSynthesis (TTS) playback */
    function toggleSpeech(msgElem, listenBtn) {
        if (!('speechSynthesis' in window)) {
            showToastNotice('Text-to-speech audio is not supported on this browser.', 'warn');
            return;
        }

        if (activeMsgElem === msgElem) {
            if (window.speechSynthesis.paused) {
                window.speechSynthesis.resume();
                listenBtn.innerHTML = '<span class="listen-icon">⏸️</span><span class="listen-label">Pause</span>';
                if (activePlayerDisplay) {
                    activePlayerDisplay.querySelector('.audio-status-text').textContent = 'Playing audio response…';
                    activePlayerDisplay.querySelector('.audio-visualizer-bars').classList.remove('is-paused');
                }
            } else if (window.speechSynthesis.speaking) {
                window.speechSynthesis.pause();
                listenBtn.innerHTML = '<span class="listen-icon">▶️</span><span class="listen-label">Resume</span>';
                if (activePlayerDisplay) {
                    activePlayerDisplay.querySelector('.audio-status-text').textContent = 'Audio paused';
                    activePlayerDisplay.querySelector('.audio-visualizer-bars').classList.add('is-paused');
                }
            } else {
                stopCurrentSpeech();
            }
            return;
        }

        stopCurrentSpeech();

        var text = getCleanText(msgElem);
        if (!text) return;

        var utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.95;
        utterance.pitch = 1.0;

        var voices = window.speechSynthesis.getVoices();
        var enVoice = voices.find(function (v) { return v.lang.indexOf('en') === 0; });
        if (enVoice) {
            utterance.voice = enVoice;
        }

        activeSpeech = utterance;
        activeMsgElem = msgElem;
        activeListenBtn = listenBtn;
        listenBtn.classList.add('is-active');
        listenBtn.innerHTML = '<span class="listen-icon">⏸️</span><span class="listen-label">Pause</span>';

        activePlayerDisplay = createAudioDisplayWidget(msgElem, listenBtn, text);
        var progressFill = activePlayerDisplay.querySelector('.audio-progress-fill');

        var wordCount = text.split(/\s+/).length;
        var estimatedDurationMs = Math.max((wordCount / 2.5) * 1000, 3000);
        var startTime = Date.now();

        progressTimer = setInterval(function () {
            if (window.speechSynthesis.paused) return;
            var elapsed = Date.now() - startTime;
            var pct = Math.min((elapsed / estimatedDurationMs) * 100, 98);
            if (progressFill) progressFill.style.width = pct + '%';
        }, 100);

        utterance.onend = function () {
            if (progressFill) progressFill.style.width = '100%';
            setTimeout(stopCurrentSpeech, 200);
        };

        utterance.onerror = function () {
            stopCurrentSpeech();
            showToastNotice('Audio playback error occurred.', 'error');
        };

        window.speechSynthesis.speak(utterance);
    }

    /* Attach "Listen" button & actions to an AI message element */
    function attachListenButton(msgElem) {
        if (!msgElem || msgElem.querySelector('.chat-msg-actions') || msgElem.classList.contains('pending')) {
            return;
        }
        var actionsDiv = document.createElement('div');
        actionsDiv.className = 'chat-msg-actions';

        var listenBtn = document.createElement('button');
        listenBtn.type = 'button';
        listenBtn.className = 'btn-chat-listen';
        listenBtn.innerHTML = '<span class="listen-icon">🔊</span><span class="listen-label">Listen</span>';

        listenBtn.addEventListener('click', function () {
            toggleSpeech(msgElem, listenBtn);
        });

        actionsDiv.appendChild(listenBtn);
        msgElem.appendChild(actionsDiv);
    }

    function addMsg(role, text, opts) {
        opts = opts || {};
        var div = document.createElement('div');
        div.className = 'chat-msg ' + (role === 'user' ? 'chat-user' : 'chat-ai') + (opts.pending ? ' pending' : '');
        
        if (opts.pending) {
            div.innerHTML = '' +
                '<div class="ai-typing-indicator">' +
                    '<span class="ai-sparkle">✨</span>' +
                    '<span class="ai-typing-dots">' +
                        '<span class="dot"></span>' +
                        '<span class="dot"></span>' +
                        '<span class="dot"></span>' +
                    '</span>' +
                    '<span class="ai-typing-text">Mwalimu AI is thinking…</span>' +
                '</div>';
        } else {
            div.innerHTML = escapeHtml(text).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');
            div.innerHTML = '<p>' + div.innerHTML + '</p>';
        }

        log.appendChild(div);

        if (role === 'ai' && !opts.pending) {
            attachListenButton(div);
        }

        log.scrollTop = log.scrollHeight;
        return div;
    }

    // Attach Listen button to initial static AI welcome message
    var existingAiMsgs = log.querySelectorAll('.chat-ai');
    for (var i = 0; i < existingAiMsgs.length; i++) {
        attachListenButton(existingAiMsgs[i]);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var q = input.value.trim();
        if (!q) {
            input.classList.add('chat-input-shake');
            setTimeout(function () { input.classList.remove('chat-input-shake'); }, 400);
            showToastNotice('Please type or speak your question first.', 'warn');
            return;
        }

        stopCurrentSpeech();

        addMsg('user', q);
        history.push({ role: 'user', content: q });
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        sendBtn.textContent = 'Thinking…';
        var pending = addMsg('ai', '…', { pending: true });

        window.Mwalimu.postJSON('api/ask.php', { lesson_id: lessonId, messages: history })
            .then(function (data) {
                pending.remove();
                if (!data || !data.success) {
                    var errorMsg = (data && data.error) || 'Something went wrong on the server.';
                    addErrorCard('server', 'Unable to Answer Question', errorMsg, q);
                    return;
                }

                if (data.status === 'UNKNOWN') {
                    addErrorCard('unknown', 'Not Covered in KICD Design', data.answer || 'Sijui', q);
                } else {
                    addMsg('ai', data.answer || '(no answer)');
                }
                history.push({ role: 'assistant', content: data.answer || '' });
            })
            .catch(function () {
                pending.remove();
                addErrorCard('network', 'Connection Error', 'Could not reach the Mwalimu AI server. Please check your network connection and try again.', q);
            })
            .then(function () {
                input.disabled = false;
                sendBtn.disabled = false;
                sendBtn.textContent = 'Ask';
                input.focus();
            });
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    /* Voice input via Web Speech API with rich error handling */
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SR && micBtn) {
        micBtn.hidden = false;
        var rec = new SR();
        rec.lang = 'en-KE';
        rec.interimResults = false;
        rec.maxAlternatives = 1;
        var listening = false;
        var statusBanner = null;

        micBtn.addEventListener('click', function () {
            if (listening) {
                rec.stop();
                return;
            }
            try {
                rec.start();
            } catch (e) {
                showToastNotice('Microphone initialization failed.', 'error');
            }
        });

        rec.onstart = function () {
            listening = true;
            if (form) form.classList.add('is-listening');
            micBtn.classList.add('is-live');
            micBtn.innerHTML = '<span class="mic-pulse-ring">🎙️</span><span class="audio-mini-equalizer"><span class="mbar"></span><span class="mbar"></span><span class="mbar"></span><span class="mbar"></span></span><span>Listening…</span>';

            if (!statusBanner) {
                statusBanner = document.createElement('div');
                statusBanner.className = 'chat-mic-status-banner';
                statusBanner.innerHTML = '' +
                    '<div class="mic-status-info">' +
                        '<span class="mic-pulse-ring">🎙️</span>' +
                        '<span>Listening to your voice… Speak your question</span>' +
                    '</div>' +
                    '<div class="audio-visualizer-bars mini">' +
                        '<span class="vbar" style="animation-delay:0s;height:50%"></span>' +
                        '<span class="vbar" style="animation-delay:0.12s;height:90%"></span>' +
                        '<span class="vbar" style="animation-delay:0.25s;height:65%"></span>' +
                        '<span class="vbar" style="animation-delay:0.08s;height:100%"></span>' +
                        '<span class="vbar" style="animation-delay:0.2s;height:75%"></span>' +
                        '<span class="vbar" style="animation-delay:0.35s;height:45%"></span>' +
                        '<span class="vbar" style="animation-delay:0.18s;height:85%"></span>' +
                    '</div>';
                form.parentNode.insertBefore(statusBanner, form);
            }
        };

        rec.onend = function () {
            listening = false;
            if (form) form.classList.remove('is-listening');
            micBtn.classList.remove('is-live');
            micBtn.innerHTML = '🎤 Speak';
            if (statusBanner) {
                statusBanner.remove();
                statusBanner = null;
            }
        };

        rec.onerror = function (event) {
            rec.onend();
            var err = event.error || '';
            if (err === 'not-allowed') {
                showToastNotice('Microphone permission denied. Please enable mic access in your browser address bar.', 'error');
            } else if (err === 'no-speech') {
                showToastNotice('No speech detected. Please speak clearly into your mic.', 'warn');
            } else if (err === 'audio-capture') {
                showToastNotice('No microphone found on your device.', 'error');
            } else if (err !== 'aborted') {
                showToastNotice('Voice input error (' + err + '). Try again.', 'warn');
            }
        };

        rec.onresult = function (event) {
            var text = event.results[0][0].transcript;
            input.value = (input.value ? input.value.trim() + ' ' : '') + text;
            input.focus();
        };
    }
})();
