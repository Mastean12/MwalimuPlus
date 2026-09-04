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

    /* Layout toggle for schemes list/grid */
    var schemesContainer = document.getElementById('schemes-container');
    var btnList = document.getElementById('toggle-list');
    var btnGrid = document.getElementById('toggle-grid');
    if (schemesContainer && btnList && btnGrid) {
        var currentLayout = localStorage.getItem('schemesLayout') || 'list';
        
        function setLayout(layout) {
            if (layout === 'list') {
                schemesContainer.classList.remove('premium-card-grid');
                schemesContainer.classList.add('premium-card-list');
                btnList.classList.remove('btn-outline');
                btnGrid.classList.add('btn-outline');
            } else {
                schemesContainer.classList.remove('premium-card-list');
                schemesContainer.classList.add('premium-card-grid');
                btnGrid.classList.remove('btn-outline');
                btnList.classList.add('btn-outline');
            }
            localStorage.setItem('schemesLayout', layout);
        }
        
        setLayout(currentLayout);
        
        btnList.addEventListener('click', function() { setLayout('list'); });
        btnGrid.addEventListener('click', function() { setLayout('grid'); });
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

    /* Hide / Unhide a saved scheme (visibility toggle). */
    document.querySelectorAll('[data-hide-scheme]').forEach(function (button) {
        button.addEventListener('click', function () {
            var id = button.getAttribute('data-hide-scheme');
            var currentlyHidden = button.getAttribute('data-is-hidden') === '1';
            var nowHidden = !currentlyHidden;

            button.disabled = true;
            window.Mwalimu.postJSON('api/schemes.php', {
                action: 'hide',
                id: id,
                is_hidden: nowHidden ? 1 : 0
            }).then(function (data) {
                if (data && data.success) {
                    // Update button state
                    button.setAttribute('data-is-hidden', nowHidden ? '1' : '0');
                    button.textContent = nowHidden ? '\uD83D\uDC41 Unhide' : '\uD83D\uDE48 Hide';
                    button.classList.toggle('btn-outline', nowHidden);

                    // Update visibility badge in the same row
                    var row = button.closest('tr');
                    if (row) {
                        var badge = row.querySelector('td:nth-child(5) .badge');
                        if (badge) {
                            if (nowHidden) {
                                badge.textContent = '\uD83D\uDE48 Hidden';
                                badge.style.background = 'var(--surface-2)';
                                badge.style.color = 'var(--muted)';
                            } else {
                                badge.textContent = '\uD83D\uDC41 Public';
                                badge.style.background = 'var(--green-50)';
                                badge.style.color = 'var(--green-700)';
                            }
                        }
                    }
                } else {
                    window.alert((data && data.error) || 'Could not update visibility.');
                }
                button.disabled = false;
            }).catch(function () {
                button.disabled = false;
                window.alert('Network error \u2014 could not update visibility.');
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
                html += '<article class="edit-lesson-card scheme-lesson" data-row-index="' + index + '">' +
                    '<div class="edit-lesson-header"><h3>Lesson ' + (parseInt(row.lesson, 10) || 0) + (row.sub_strand ? ' — ' + escapeHtml(row.sub_strand) : '') + '</h3></div>' +
                    '<div class="edit-fields-grid">' +
                    field('Sub strand', 'sub_strand', row.sub_strand, false) +
                    field('Specific learning outcomes', 'specific_outcomes', row.specific_outcomes, true) +
                    field('Key inquiry question', 'key_inquiry_question', row.key_inquiry_question, false) +
                    field('Learning experiences', 'learning_experiences', row.learning_experiences, true) +
                    field('Learning resources', 'learning_resources', row.learning_resources, true) +
                    field('Assessment methods', 'assessment', row.assessment, false) +
                    field('Reference', 'reference', row.reference, false) +
                    '</div>' +
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
    var firstUrl = urlField ? urlField.querySelector('input[name="url[]"]') : null;
    var fileInput = fileField ? fileField.querySelector('input') : null;
    var addLink = form.querySelector('[data-add-link]');
    var linkFields = form.querySelector('[data-link-fields]');
    var canAddLinks = addLink && linkFields && firstUrl;

    function sync() {
        var checked = form.querySelector('input[name="kind"]:checked');
        var isPdf = checked && checked.value === 'pdf';
        if (urlField) { urlField.hidden = isPdf; }
        if (fileField) { fileField.hidden = !isPdf; }
        if (firstUrl) { firstUrl.required = !isPdf; }
        if (canAddLinks) { addLink.hidden = isPdf; }
        if (fileInput) {
            fileInput.required = isPdf;
            if (!isPdf) { fileInput.value = ''; }
        }
    }

    form.querySelectorAll('input[name="kind"]').forEach(function (radio) {
        radio.addEventListener('change', sync);
    });

    // Repeatable link rows: "+ Add another link" adds a row, "−" removes it.
    if (canAddLinks) {
        var makeRow = function (input) {
            var row = document.createElement('div');
            row.className = 'link-row';
            row.appendChild(input); // detaches input from wherever it was
            var minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'btn-link-danger link-remove';
            minus.setAttribute('aria-label', 'Remove this link');
            minus.title = 'Remove';
            minus.textContent = '−';
            row.appendChild(minus);
            return row;
        };

        // Wrap the static inputs (kept in order — each append re-adds at the end).
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

/* scheme.php — edit an already-saved scheme's content in place. */
(function () {
    var editBtn = document.getElementById('scheme-edit-btn');
    var viewBox = document.getElementById('scheme-view');
    var editBox = document.getElementById('scheme-edit-container');
    if (!editBtn || !viewBox || !editBox || !window.MWALIMU_SCHEME_DATA) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var LIST_FIELDS = ['specific_outcomes', 'learning_experiences', 'learning_resources'];
    var schemeId = window.MWALIMU_SCHEME_ID;
    var data = window.MWALIMU_SCHEME_DATA;
    var rendered = false;

    function field(label, name, value, isList) {
        var val = isList ? (value || []).join('\n') : (value || '');
        var control = isList
            ? '<textarea class="scheme-input" data-field="' + name + '" rows="3">' + escapeHtml(val) + '</textarea>'
            : '<input type="text" class="scheme-input" data-field="' + name + '" value="' + escapeHtml(val) + '">';
        var hint = isList ? ' <span class="field-hint-inline">(one per line)</span>' : '';
        return '<label>' + escapeHtml(label) + hint + control + '</label>';
    }

    function render() {
        var rows = data.rows || [];
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

        var html = '<div class="scheme-edit-banner">' +
            '<div class="banner-title">' +
            '<span class="banner-icon">✏️</span>' +
            '<div>' +
            '<strong>Edit Scheme of Work</strong>' +
            '<p>Customize learning outcomes, key inquiry questions, and resources below.</p>' +
            '</div>' +
            '</div>' +
            '<div class="banner-actions">' +
            '<button type="button" class="btn" id="scheme-edit-cancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="scheme-edit-save">Save changes</button>' +
            '</div>' +
            '</div>';

        html += '<div class="panel">' +
            field('Key inquiry questions', 'key_inquiry_questions', data.key_inquiry_questions, true) +
            '</div>';

        order.forEach(function (wk) {
            html += '<section class="panel scheme-week"><h2>Week ' + wk + '</h2>';
            weeks[wk].forEach(function (index) {
                var row = rows[index];
                html += '<article class="edit-lesson-card scheme-lesson" data-row-index="' + index + '">' +
                    '<div class="edit-lesson-header"><h3>Lesson ' + (parseInt(row.lesson, 10) || 0) + (row.sub_strand ? ' — ' + escapeHtml(row.sub_strand) : '') + '</h3></div>' +
                    '<div class="edit-fields-grid">' +
                    field('Sub strand', 'sub_strand', row.sub_strand, false) +
                    field('Specific learning outcomes', 'specific_outcomes', row.specific_outcomes, true) +
                    field('Key inquiry question', 'key_inquiry_question', row.key_inquiry_question, false) +
                    field('Learning experiences', 'learning_experiences', row.learning_experiences, true) +
                    field('Learning resources', 'learning_resources', row.learning_resources, true) +
                    field('Assessment methods', 'assessment', row.assessment, false) +
                    field('Reference', 'reference', row.reference, false) +
                    '</div>' +
                    '</article>';
            });
            html += '</section>';
        });

        html += '<div class="scheme-preview-actions" style="margin-top: 1.5rem;">' +
            '<button type="button" class="btn" id="scheme-edit-cancel-bottom">Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="scheme-edit-save-bottom">Save changes</button>' +
            '</div>';

        editBox.innerHTML = html;
        rendered = true;
    }

    function collectEditedScheme() {
        var rows = (data.rows || []).map(function (row) {
            // Carry week/lesson/strand through unchanged — only descriptive fields are editable.
            return { week: row.week, lesson: row.lesson, strand: row.strand };
        });

        editBox.querySelectorAll('.scheme-lesson').forEach(function (article) {
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

        var kiqInput = editBox.querySelector('[data-field="key_inquiry_questions"]');
        var kiq = kiqInput
            ? kiqInput.value.split('\n').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; })
            : (data.key_inquiry_questions || []);

        return { key_inquiry_questions: kiq, rows: rows, citations: data.citations || [] };
    }

    editBtn.addEventListener('click', function () {
        if (!rendered) {
            render();
        }
        viewBox.hidden = true;
        editBox.hidden = false;
    });

    editBox.addEventListener('click', function (event) {
        var isCancel = event.target.id === 'scheme-edit-cancel' || event.target.id === 'scheme-edit-cancel-bottom';
        var isSave = event.target.id === 'scheme-edit-save' || event.target.id === 'scheme-edit-save-bottom';

        if (isCancel) {
            viewBox.hidden = false;
            editBox.hidden = true;
            return;
        }
        if (!isSave) {
            return;
        }
        var saveBtn = event.target;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        window.Mwalimu.postJSON('api/schemes.php', {
            action: 'update',
            id: schemeId,
            scheme: collectEditedScheme()
        }).then(function (res) {
            if (res && res.success) {
                window.location.reload();
                return;
            }
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save changes';
            window.alert((res && res.error) || 'Could not save changes.');
        }).catch(function () {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save changes';
            window.alert('Network error — could not save changes.');
        });
    });
})();

/* scheme.php — generate a lesson plan for a single scheme row. */
(function () {
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-generate-lesson]');
        if (!btn) {
            return;
        }

        btn.disabled = true;
        var original = btn.textContent;
        btn.textContent = 'Generating…';

        window.Mwalimu.postJSON('api/generate-lesson.php', {
            subject: btn.getAttribute('data-subject'),
            topic: btn.getAttribute('data-topic'),
            strand: btn.getAttribute('data-strand'),
            duration: 40,
            teacher_need: btn.getAttribute('data-teacher-need') || '',
            resources: [],
            scheme_id: parseInt(btn.getAttribute('data-scheme-id'), 10)
        }).then(function (data) {
            if (!data || !data.success) {
                btn.disabled = false;
                btn.textContent = original;
                window.alert((data && data.error) || 'Could not generate the lesson plan.');
                return;
            }
            if (data.demo_mode) {
                btn.disabled = false;
                btn.textContent = original;
                window.alert(data.message || 'AI generation is not configured yet.');
                return;
            }
            if (data.status === 'UNKNOWN' || !data.saved_id) {
                btn.disabled = false;
                btn.textContent = original;
                window.alert(data.sijui || 'I can’t build a lesson for this from the curriculum design I have.');
                return;
            }

            var link = document.createElement('a');
            link.className = 'btn btn-small';
            link.href = 'lesson.php?id=' + encodeURIComponent(data.saved_id);
            link.textContent = 'View lesson plan';
            var wrap = btn.closest('p');
            if (wrap) {
                wrap.replaceWith(link);
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = original;
            window.alert('Network error — could not generate the lesson plan.');
        });
    });
})();
