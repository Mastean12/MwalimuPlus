/* MwalimuPlus — lesson generator (topic.php) */
(function () {
    'use strict';

    var form = document.getElementById('generate-form');
    if (!form) {
        return;
    }

    var resultBox = document.getElementById('generate-result');
    var button = document.getElementById('generate-btn');

    function statusBadge(status) {
        var cls = String(status || '').toLowerCase();
        return '<span class="badge badge-' + cls + '">' + status + '</span>';
    }

    function listItems(items) {
        if (!Array.isArray(items)) {
            return '';
        }
        return '<ul>' + items.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var modal = document.getElementById('generation-modal');
    var stateLoading = document.getElementById('gen-state-loading');
    var stateSuccess = document.getElementById('gen-state-success');
    var stateError = document.getElementById('gen-state-error');
    var errorText = document.getElementById('gen-error-message');
    var closeBtn = document.getElementById('gen-close-btn');
    var cancelBtn = document.getElementById('gen-cancel-btn');
    var isCancelled = false;

    // Ensure modal is hidden on initial page load
    if (modal) {
        modal.style.display = 'none';
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            isCancelled = true;
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            if (button) {
                button.disabled = false;
                button.textContent = 'Generate lesson';
            }
        });
    }

    var formModal = document.getElementById('generate-lesson-form-modal');
    var openModalBtn = document.getElementById('open-gen-form-modal-btn');
    var closeModalBtn = document.getElementById('close-gen-form-modal');
    var cancelModalBtn = document.getElementById('cancel-gen-form-modal');

    function hideFormModal() {
        if (formModal) {
            formModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }

    function showFormModal() {
        if (formModal) {
            formModal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
    }

    if (openModalBtn) {
        openModalBtn.addEventListener('click', showFormModal);
    }
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', hideFormModal);
    }
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', hideFormModal);
    }

    var topicSelector = document.getElementById('topic-selector');
    if (topicSelector) {
        topicSelector.addEventListener('change', function () {
            var opt = topicSelector.options[topicSelector.selectedIndex];
            if (opt && opt.value) {
                document.getElementById('subject-name').value = opt.getAttribute('data-subject') || '';
                document.getElementById('topic-name').value = opt.getAttribute('data-topic') || '';
                document.getElementById('strand-name').value = opt.getAttribute('data-strand') || '';
            }
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        isCancelled = false;
        hideFormModal();

        var resources = document.getElementById('resources').value
            .split(',')
            .map(function (item) { return item.trim(); })
            .filter(Boolean);

        var payload = {
            subject: document.getElementById('subject-name').value,
            topic: document.getElementById('topic-name').value,
            strand: document.getElementById('strand-name').value,
            duration: parseInt(document.getElementById('duration').value, 10) || 40,
            teacher_need: document.getElementById('teacher-need').value.trim(),
            resources: resources
        };

        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
            stateLoading.classList.add('active');
            stateSuccess.classList.remove('active');
            stateError.classList.remove('active');
        }

        button.disabled = true;
        button.textContent = 'Generating\u2026';

        window.Mwalimu.postJSON('api/generate-lesson.php', payload).then(function (data) {
            if (isCancelled) {
                return;
            }
            if (modal) {
                stateLoading.classList.remove('active');
                if (!data.success) {
                    stateError.classList.add('active');
                    errorText.innerHTML = '<p>' + escapeHtml(data.error || 'Something went wrong.') + '</p>';
                } else if (data.demo_mode) {
                    stateError.classList.add('active');
                    errorText.innerHTML = '<p>' + escapeHtml(data.message || '') + '</p>';
                } else if (data.status === 'UNKNOWN' || !data.lesson) {
                    stateError.classList.add('active');
                    errorText.innerHTML = '<p class="result-status">' + statusBadge(data.status || 'UNKNOWN') + '</p><p>' + escapeHtml(data.sijui || 'I can\u2019t answer that from the curriculum design I have.') + '</p>';
                } else {
                    stateSuccess.classList.add('active');
                    setTimeout(function () { window.location.href = 'lesson.php?id=' + data.saved_id; }, 1200);
                }
            } else {
                // Fallback if modal is missing
                resultBox.hidden = false;
                if (!data.success) resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.error || 'Something went wrong.') + '</p></div>';
                else if (data.demo_mode) resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.message || '') + '</p></div>';
                else if (data.status === 'UNKNOWN' || !data.lesson) resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.sijui || 'Error') + '</p></div>';
                else {
                    resultBox.innerHTML = '<div class="result-success"><h3>Success</h3></div>';
                    setTimeout(function () { window.location.href = 'lesson.php?id=' + data.saved_id; }, 900);
                }
            }
        }).catch(function () {
            if (isCancelled) {
                return;
            }
            if (modal) {
                stateLoading.classList.remove('active');
                stateError.classList.add('active');
                errorText.innerHTML = '<p>Network error \u2014 could not reach the server.</p>';
            } else {
                resultBox.hidden = false;
                resultBox.innerHTML = '<div class="result-unknown"><p>Network error \u2014 could not reach the server.</p></div>';
            }
        }).then(function () {
            if (!isCancelled) {
                button.disabled = false;
                button.textContent = 'Generate lesson';
            }
        });
    });
})();

/* lesson.php — confirm before any [data-confirm] delete form submits. */
(function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Remove this item?')) {
                event.preventDefault();
            }
        });
    });
})();

/* lesson.php — add-media form: link fields vs file by the selected type,
   plus repeatable link rows with add / remove. */
(function () {
    var form = document.querySelector('.add-resource');
    if (!form) {
        return;
    }
    var urlField = form.querySelector('[data-resource-field="url"]');
    var fileField = form.querySelector('[data-resource-field="file"]');
    var firstUrl = urlField ? urlField.querySelector('input[name="url[]"]') : null;
    var fileInput = fileField ? fileField.querySelector('input') : null;
    var addLink = form.querySelector('[data-add-link]');
    var linkFields = form.querySelector('[data-link-fields]');
    var canAddLinks = addLink && linkFields && firstUrl;

    function sync() {
        var checked = form.querySelector('input[name="kind"]:checked');
        var isFile = checked && (checked.value === 'pdf' || checked.value === 'image');
        if (urlField) { urlField.hidden = isFile; }
        if (fileField) { fileField.hidden = !isFile; }
        if (firstUrl) { firstUrl.required = !isFile; }
        if (canAddLinks) { addLink.hidden = isFile; }
        if (fileInput) {
            fileInput.required = isFile;
            if (!isFile) { fileInput.value = ''; }
        }
    }

    form.querySelectorAll('input[name="kind"]').forEach(function (radio) {
        radio.addEventListener('change', sync);
    });

    if (canAddLinks) {
        var makeRow = function (input) {
            var row = document.createElement('div');
            row.className = 'link-row';
            row.appendChild(input);
            var minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'btn-link-danger link-remove';
            minus.setAttribute('aria-label', 'Remove this link');
            minus.title = 'Remove';
            minus.textContent = '−';
            row.appendChild(minus);
            return row;
        };

        Array.prototype.slice.call(linkFields.querySelectorAll('input[name="url[]"]'))
            .forEach(function (input) {
                linkFields.appendChild(makeRow(input));
            });

        addLink.addEventListener('click', function () {
            var input = firstUrl.cloneNode(true);
            input.value = '';
            input.required = false;
            linkFields.appendChild(makeRow(input));
            input.focus();
        });

        linkFields.addEventListener('click', function (event) {
            var minus = event.target.closest('.link-remove');
            if (!minus) {
                return;
            }
            if (linkFields.querySelectorAll('.link-row').length <= 1) {
                minus.parentNode.querySelector('input').value = '';
                return;
            }
            minus.parentNode.remove();
        });
    }

    sync();
})();

/* lesson.php — flashcards & Q&A: flip cards, generate/regenerate. */
(function () {
    var wrap = document.getElementById('study-set');
    if (!wrap) {
        return;
    }

    document.addEventListener('click', function (event) {
        var card = event.target.closest('[data-flip]');
        if (card && wrap.contains(card)) {
            card.classList.toggle('is-flipped');
        }
    });

    var btn = document.getElementById('study-btn');
    var result = document.getElementById('study-result');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = 'Generating…';
        if (result) { result.hidden = true; }

        window.Mwalimu.postJSON('api/generate-study.php', {
            lesson_id: wrap.getAttribute('data-lesson-id')
        }).then(function (data) {
            if (data && data.success && data.study && !data.demo_mode
                && (data.study.flashcards || []).length + (data.study.qa || []).length > 0) {
                window.location.reload();
                return;
            }
            if (result) {
                result.hidden = false;
                result.innerHTML = '<div class="result-unknown"><p>'
                    + (data && (data.message || data.sijui || data.error) || 'Could not generate study material.')
                    + '</p></div>';
            }
            btn.disabled = false;
            btn.textContent = 'Try again';
        }).catch(function () {
            if (result) {
                result.hidden = false;
                result.innerHTML = '<div class="result-unknown"><p>Network error — could not reach the server.</p></div>';
            }
            btn.disabled = false;
            btn.textContent = 'Try again';
        });
    });
})();

/* lesson.php — Listen / Text-to-Speech audio playback */
(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var listenMainBtn = document.getElementById('listen-lesson-btn');
    var sectionBtns = document.querySelectorAll('[data-listen-section]');
    if (!listenMainBtn && sectionBtns.length === 0) {
        return;
    }

    var audioBar = null;
    var currentSpeech = null;
    var isPlaying = false;
    var currentSpeed = 1.0;

    function stopSpeech() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        isPlaying = false;
        if (audioBar) {
            audioBar.remove();
            audioBar = null;
        }
        if (listenMainBtn) {
            var icon = listenMainBtn.querySelector('.listen-icon');
            var label = listenMainBtn.querySelector('.listen-label');
            if (icon) icon.textContent = '🔊';
            if (label) label.textContent = 'Listen';
        }
    }

    function createAudioBar(sectionTitle) {
        if (audioBar) {
            audioBar.remove();
        }

        audioBar = document.createElement('div');
        audioBar.className = 'lesson-audio-player-bar';
        audioBar.innerHTML = 
            '<div class="lesson-audio-info">' +
                '<div class="audio-mini-equalizer">' +
                    '<span class="mbar"></span><span class="mbar"></span><span class="mbar"></span><span class="mbar"></span>' +
                '</div>' +
                '<div>' +
                    '<div class="audio-status-title">Playing Lesson Audio</div>' +
                    '<div class="audio-status-sub">' + escapeHtml(sectionTitle || 'Full lesson') + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="lesson-audio-controls">' +
                '<button type="button" class="audio-speed-btn" id="audio-speed-toggle" title="Playback Speed">1.0x</button>' +
                '<button type="button" class="audio-btn-circle" id="audio-pause-btn" title="Pause / Play">⏸️</button>' +
                '<button type="button" class="audio-btn-circle" id="audio-stop-btn" title="Stop Audio">⏹️</button>' +
            '</div>';

        var head = document.querySelector('.page-head') || document.body.firstElementChild;
        if (head && head.parentNode) {
            head.parentNode.insertBefore(audioBar, head.nextSibling);
        } else {
            document.body.prepend(audioBar);
        }

        var pauseBtn = audioBar.querySelector('#audio-pause-btn');
        var stopBtn = audioBar.querySelector('#audio-stop-btn');
        var speedBtn = audioBar.querySelector('#audio-speed-toggle');

        pauseBtn.addEventListener('click', function () {
            if (!('speechSynthesis' in window)) return;
            if (window.speechSynthesis.paused) {
                window.speechSynthesis.resume();
                pauseBtn.textContent = '⏸️';
                audioBar.querySelector('.audio-status-title').textContent = 'Playing Lesson Audio';
            } else if (window.speechSynthesis.speaking) {
                window.speechSynthesis.pause();
                pauseBtn.textContent = '▶️';
                audioBar.querySelector('.audio-status-title').textContent = 'Audio Paused';
            }
        });

        stopBtn.addEventListener('click', stopSpeech);

        speedBtn.addEventListener('click', function () {
            if (currentSpeed === 1.0) currentSpeed = 1.25;
            else if (currentSpeed === 1.25) currentSpeed = 1.5;
            else currentSpeed = 1.0;
            speedBtn.textContent = currentSpeed + 'x';
        });
    }

    function speakText(text, title) {
        if (!('speechSynthesis' in window)) {
            window.alert('Text-to-speech audio is not supported in this browser.');
            return;
        }

        if (isPlaying) {
            stopSpeech();
            return;
        }

        stopSpeech();
        if (!text || text.trim() === '') return;

        var utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = currentSpeed;
        utterance.pitch = 1.0;

        var voices = window.speechSynthesis.getVoices();
        var enVoice = voices.find(function (v) { return v.lang.indexOf('en') === 0; });
        if (enVoice) {
            utterance.voice = enVoice;
        }

        utterance.onend = function () {
            stopSpeech();
        };

        utterance.onerror = function () {
            stopSpeech();
        };

        createAudioBar(title);
        currentSpeech = utterance;
        isPlaying = true;
        if (listenMainBtn) {
            var icon = listenMainBtn.querySelector('.listen-icon');
            var label = listenMainBtn.querySelector('.listen-label');
            if (icon) icon.textContent = '⏹️';
            if (label) label.textContent = 'Stop Audio';
        }
        window.speechSynthesis.speak(utterance);
    }

    if (listenMainBtn) {
        listenMainBtn.addEventListener('click', function () {
            var heading = document.querySelector('.page-head h1');
            var title = heading ? heading.textContent.trim() : 'Lesson';
            var mainContent = document.querySelectorAll('section.panel');
            var text = (heading ? heading.textContent + '. ' : '');
            mainContent.forEach(function(sec) {
                if (sec.id !== 'lesson-media' && sec.id !== 'study-set') {
                    text += sec.innerText.replace('🔊', '').trim() + '. ';
                }
            });
            speakText(text, title);
        });
    }

    sectionBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var panel = btn.closest('.panel');
            if (!panel) return;
            var h2 = panel.querySelector('h2');
            var title = h2 ? h2.childNodes[0].textContent.trim() : 'Section';
            var text = panel.innerText.replace('🔊', '').trim();
            speakText(text, title);
        });
    });
})();

