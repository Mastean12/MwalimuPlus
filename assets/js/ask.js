/* MwalimuPlus — grounded lesson chat (ask.php) */
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

    function escapeHtml(v) {
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function addMsg(role, text, opts) {
        opts = opts || {};
        var div = document.createElement('div');
        div.className = 'chat-msg ' + (role === 'user' ? 'chat-user' : 'chat-ai') + (opts.unknown ? ' unknown' : '') + (opts.pending ? ' pending' : '');
        div.innerHTML = escapeHtml(text).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');
        div.innerHTML = '<p>' + div.innerHTML + '</p>';
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
        return div;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var q = input.value.trim();
        if (!q) {
            return;
        }

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
                    addMsg('ai', (data && data.error) || 'Something went wrong. Try again.', { unknown: true });
                    return;
                }
                addMsg('ai', data.answer || '(no answer)', { unknown: data.status === 'UNKNOWN' });
                history.push({ role: 'assistant', content: data.answer || '' });
            })
            .catch(function () {
                pending.remove();
                addMsg('ai', 'Network error — could not reach the server.', { unknown: true });
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

    /* Voice input via the Web Speech API, where available. */
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SR && micBtn) {
        micBtn.hidden = false;
        var rec = new SR();
        rec.lang = 'en-KE';
        rec.interimResults = false;
        rec.maxAlternatives = 1;
        var listening = false;

        micBtn.addEventListener('click', function () {
            if (listening) {
                rec.stop();
                return;
            }
            try {
                rec.start();
            } catch (e) {
                return;
            }
        });
        rec.onstart = function () { listening = true; micBtn.textContent = '● Listening'; micBtn.classList.add('is-live'); };
        rec.onend = function () { listening = false; micBtn.textContent = '🎤 Speak'; micBtn.classList.remove('is-live'); };
        rec.onerror = rec.onend;
        rec.onresult = function (event) {
            var text = event.results[0][0].transcript;
            input.value = (input.value ? input.value.trim() + ' ' : '') + text;
            input.focus();
        };
    }
})();
