/* MwalimuPlus — scheme-of-work generator (schemes.php) */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* Toggle the "New scheme" panel from the page-header button. */
    var toggle = document.querySelector('[data-toggle="new-scheme"]');
    var panel = document.getElementById('new-scheme');
    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            if (!panel.hidden) {
                var subject = document.getElementById('scheme-subject');
                if (subject) {
                    subject.focus({ preventScroll: true });
                }
            }
        });
    }

    /* Delete a saved scheme. */
    document.querySelectorAll('[data-delete-scheme]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-delete-scheme');
            if (!window.confirm('Delete this scheme? This cannot be undone.')) {
                return;
            }
            button.disabled = true;
            window.Mwalimu.postJSON('api/schemes.php', { id: id }).then(function (data) {
                if (data && data.success) {
                    var row = button.closest('tr');
                    if (row) {
                        row.parentNode.removeChild(row);
                    }
                } else {
                    button.disabled = false;
                    window.alert((data && data.error) || 'Could not delete the scheme.');
                }
            }).catch(function () {
                button.disabled = false;
                window.alert('Network error — could not delete the scheme.');
            });
        });
    });

    /* Generate a new scheme -> editable preview -> explicit save. */
    var form = document.getElementById('scheme-form');
    if (!form) {
        return;
    }

    var resultBox = document.getElementById('scheme-result');
    var button = document.getElementById('scheme-btn');
    var previewBox = document.getElementById('scheme-preview');
    var previewBody = document.getElementById('scheme-preview-body');
    var discardBtn = document.getElementById('scheme-discard-btn');
    var saveBtn = document.getElementById('scheme-save-btn');

    var LIST_FIELDS = ['specific_outcomes', 'learning_experiences', 'learning_resources'];
    var draftMeta = null; // { subject_id, term, lessons_per_week, start_week }
    var draftScheme = null; // { key_inquiry_questions, rows, citations } as last generated

    function field(label, name, value, isList) {
        var val = isList ? (value || []).join('\n') : (value || '');
        var control = isList
            ? '<textarea class="scheme-input" data-field="' + name + '" rows="3">' + escapeHtml(val) + '</textarea>'
            : '<input type="text" class="scheme-input" data-field="' + name + '" value="' + escapeHtml(val) + '">';
        var hint = isList ? ' <span class="field-hint-inline">(one per line)</span>' : '';
        return '<label>' + escapeHtml(label) + hint + control + '</label>';
    }

    function renderPreview(scheme) {
        var rows = scheme.rows || [];
        var weeks = {};
        var order = [];
        rows.forEach(function (row, index) {
            var wk = parseInt(row.week, 10) || 0;
            if (!weeks[wk]) {
                weeks[wk] = [];
                order.push(wk);
            }
            weeks[wk].push(index);
        });
        order.sort(function (a, b) { return a - b; });

        var html = '<div class="panel">' +
            field('Key inquiry questions', 'key_inquiry_questions', scheme.key_inquiry_questions, true) +
            '</div>';

        order.forEach(function (wk) {
            html += '<section class="panel scheme-week"><h2>Week ' + wk + '</h2>';
            weeks[wk].forEach(function (index) {
                var row = rows[index];
                html += '<article class="scheme-lesson" data-row-index="' + index + '">' +
                    '<h3>Lesson ' + (parseInt(row.lesson, 10) || 0) + '</h3>' +
                    field('Sub-strand', 'sub_strand', row.sub_strand, false) +
                    field('Specific learning outcomes', 'specific_outcomes', row.specific_outcomes, true) +
                    field('Key inquiry question', 'key_inquiry_question', row.key_inquiry_question, false) +
                    field('Learning experiences', 'learning_experiences', row.learning_experiences, true) +
                    field('Learning resources', 'learning_resources', row.learning_resources, true) +
                    field('Assessment methods', 'assessment', row.assessment, false) +
                    field('Reference', 'reference', row.reference, false) +
                    '</article>';
            });
            html += '</section>';
        });

        previewBody.innerHTML = html;
        previewBox.hidden = false;
    }

    function collectEditedScheme() {
        var rows = (draftScheme.rows || []).map(function (row) {
            // Carry week/lesson through unchanged — only the descriptive fields are editable.
            return {
                week: row.week,
                lesson: row.lesson,
                strand: row.strand
            };
        });

        previewBody.querySelectorAll('.scheme-lesson').forEach(function (article) {
            var index = parseInt(article.getAttribute('data-row-index'), 10);
            var row = rows[index];
            if (!row) {
                return;
            }
            article.querySelectorAll('.scheme-input').forEach(function (input) {
                var name = input.getAttribute('data-field');
                row[name] = LIST_FIELDS.indexOf(name) !== -1
                    ? input.value.split('\n').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; })
                    : input.value.trim();
            });
        });

        var kiqInput = previewBody.querySelector('[data-field="key_inquiry_questions"]');
        var kiq = kiqInput
            ? kiqInput.value.split('\n').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; })
            : (draftScheme.key_inquiry_questions || []);

        return {
            key_inquiry_questions: kiq,
            rows: rows,
            citations: draftScheme.citations || []
        };
    }

    function resetPreview() {
        previewBox.hidden = true;
        previewBody.innerHTML = '';
        draftMeta = null;
        draftScheme = null;
    }

    discardBtn.addEventListener('click', resetPreview);

    saveBtn.addEventListener('click', function () {
        if (!draftMeta || !draftScheme) {
            return;
        }
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        window.Mwalimu.postJSON('api/schemes.php', {
            action: 'save',
            subject_id: draftMeta.subject_id,
            term: draftMeta.term,
            lessons_per_week: draftMeta.lessons_per_week,
            start_week: draftMeta.start_week,
            scheme: collectEditedScheme()
        }).then(function (data) {
            if (data && data.success) {
                window.location.href = 'scheme.php?id=' + encodeURIComponent(data.saved_id);
                return;
            }
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save scheme';
            window.alert((data && data.error) || 'Could not save the scheme.');
        }).catch(function () {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save scheme';
            window.alert('Network error — could not save the scheme.');
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var payload = {
            subject: document.getElementById('scheme-subject').value,
            term: parseInt(document.getElementById('scheme-term').value, 10) || 1,
            lessons_per_week: parseInt(document.getElementById('scheme-lpw').value, 10) || 4,
            start_week: parseInt(document.getElementById('scheme-start').value, 10) || 1,
            focus: document.getElementById('scheme-focus').value.trim(),
            details: document.getElementById('scheme-details').value.trim()
        };

        button.disabled = true;
        button.textContent = 'Generating…';
        resultBox.hidden = true;
        resetPreview();

        window.Mwalimu.postJSON('api/generate-scheme.php', payload).then(function (data) {
            resultBox.hidden = false;

            if (!data.success) {
                resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.error || 'Something went wrong.') + '</p></div>';
                return;
            }

            if (data.demo_mode) {
                resultBox.innerHTML = '<div class="result-unknown"><p>' + escapeHtml(data.message || '') + '</p></div>';
                return;
            }

            if (data.status === 'UNKNOWN' || !data.scheme) {
                resultBox.innerHTML =
                    '<div class="result-unknown">' +
                    '<p class="result-status">UNKNOWN</p>' +
                    '<p>' + escapeHtml(data.sijui || 'I can’t build a scheme for that from the curriculum design I have.') + '</p>' +
                    '</div>';
                return;
            }

            resultBox.innerHTML =
                '<div class="result-success">' +
                '<p class="result-status">' + escapeHtml(data.status) + '</p>' +
                '<p>Review the draft below, then save it.</p>' +
                '</div>';

            draftMeta = {
                subject_id: data.subject_id,
                term: data.term,
                lessons_per_week: data.lessons_per_week,
                start_week: data.start_week
            };
            draftScheme = data.scheme;
            renderPreview(data.scheme);
        }).catch(function () {
            resultBox.hidden = false;
            resultBox.innerHTML = '<div class="result-unknown"><p>Network error — could not reach the server.</p></div>';
        }).then(function () {
            button.disabled = false;
            button.textContent = 'Generate scheme';
        });
    });
})();

/* scheme.php — confirm before any [data-confirm] delete form submits. */
(function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Remove this material?')) {
                event.preventDefault();
            }
        });
    });
})();

/* scheme.php — add-material form: show URL vs file by the selected type. */
(function () {
    var form = document.querySelector('.add-resource');
    if (!form) {
        return;
    }
    var urlField = form.querySelector('[data-resource-field="url"]');
    var fileField = form.querySelector('[data-resource-field="file"]');
    var urlInput = urlField ? urlField.querySelector('input') : null;
    var fileInput = fileField ? fileField.querySelector('input') : null;

    function sync() {
        var checked = form.querySelector('input[name="kind"]:checked');
        var isPdf = checked && checked.value === 'pdf';
        if (urlField) { urlField.hidden = isPdf; }
        if (fileField) { fileField.hidden = !isPdf; }
        if (urlInput) {
            urlInput.required = !isPdf;
            if (isPdf) { urlInput.value = ''; }
        }
        if (fileInput) {
            fileInput.required = isPdf;
            if (!isPdf) { fileInput.value = ''; }
        }
    }

    form.querySelectorAll('input[name="kind"]').forEach(function (radio) {
        radio.addEventListener('change', sync);
    });
    sync();
})();

/* scheme.php — rename the scheme from the header button. */
(function () {
    var btn = document.querySelector('[data-rename-scheme]');
    var heading = document.querySelector('.page-head h1');
    if (!btn || !heading) {
        return;
    }
    var id = btn.getAttribute('data-rename-scheme');

    btn.addEventListener('click', function () {
        var current = heading.textContent.trim();
        var next = window.prompt('Rename this scheme', current);
        if (next === null) {
            return;
        }
        next = next.trim();
        if (!next || next === current) {
            return;
        }
        btn.disabled = true;
        window.Mwalimu.postJSON('api/schemes.php', { action: 'rename', id: id, title: next })
            .then(function (data) {
                if (data && data.success) {
                    heading.textContent = data.title;
                    document.title = data.title + ' · MwalimuPlus';
                    var crumb = document.querySelector('.topbar .breadcrumbs > *:last-child');
                    if (crumb) {
                        crumb.textContent = data.title;
                    }
                } else {
                    window.alert((data && data.error) || 'Could not rename the scheme.');
                }
            })
            .catch(function () {
                window.alert('Network error — could not rename the scheme.');
            })
            .then(function () {
                btn.disabled = false;
            });
    });
})();
