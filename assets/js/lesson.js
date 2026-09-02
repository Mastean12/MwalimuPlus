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

    function renderSuccess(data) {
        var lesson = data.lesson || {};
        var html = '<div class="result-success">' +
            '<p class="result-status">' + statusBadge(data.status) + '</p>' +
            '<p class="disclosure">' + escapeHtml(data.disclosure || '') + '</p>' +
            '<h3><a href="lesson.php?id=' + encodeURIComponent(data.saved_id || '') + '">' +
            escapeHtml(lesson.title || '') + '</a></h3>';

        if (lesson.citations && lesson.citations.length) {
            html += '<p class="citations">Sources: ' + escapeHtml(lesson.citations.join(', ')) + '</p>';
        }
        html += '</div>';
        resultBox.innerHTML = html;
    }

    function renderUnknown(data) {
        resultBox.innerHTML =
            '<div class="result-unknown">' +
            '<p class="result-status">' + statusBadge(data.status || 'UNKNOWN') + '</p>' +
            '<p>' + escapeHtml(data.sijui || 'I can\u2019t answer that from the curriculum design I have.') + '</p>' +
            '</div>';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

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

        button.disabled = true;
        button.textContent = 'Generating\u2026';

        window.Mwalimu.postJSON('api/generate-lesson.php', payload).then(function (data) {
            resultBox.hidden = false;

            if (!data.success) {
                resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.error || 'Something went wrong.') + '</p></div>';
                return;
            }

            if (data.demo_mode) {
                resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.message || '') + '</p></div>';
                return;
            }

            if (data.status === 'UNKNOWN' || !data.lesson) {
                renderUnknown(data);
                return;
            }

            renderSuccess(data);
            setTimeout(function () { window.location.href = 'lesson.php?id=' + data.saved_id; }, 900);
        }).catch(function () {
            resultBox.hidden = false;
            resultBox.innerHTML = '<div class="result-unknown"><p>Network error \u2014 could not reach the server.</p></div>';
        }).then(function () {
            button.disabled = false;
            button.textContent = 'Generate lesson';
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
