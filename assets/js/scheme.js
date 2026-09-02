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

    /* Generate a new scheme. */
    var form = document.getElementById('scheme-form');
    if (!form) {
        return;
    }

    var resultBox = document.getElementById('scheme-result');
    var button = document.getElementById('scheme-btn');

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var payload = {
            subject: document.getElementById('scheme-subject').value,
            term: parseInt(document.getElementById('scheme-term').value, 10) || 1,
            lessons_per_week: parseInt(document.getElementById('scheme-lpw').value, 10) || 4,
            start_week: parseInt(document.getElementById('scheme-start').value, 10) || 1,
            focus: document.getElementById('scheme-focus').value.trim()
        };

        button.disabled = true;
        button.textContent = 'Generating…';
        resultBox.hidden = true;

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
                '<p>Scheme saved. Opening it…</p>' +
                '</div>';
            setTimeout(function () {
                window.location.href = 'scheme.php?id=' + encodeURIComponent(data.saved_id);
            }, 700);
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
    var urlInput = urlField ? urlField.querySelector('input, textarea') : null;
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
