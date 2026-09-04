/* MwalimuPlus — subject management (subject.php):
   Edit subject (modal) and Delete subject (confirm), both via api/subjects.php. */
(function () {
    'use strict';

    var editBtn = document.querySelector('[data-edit-subject]');
    var deleteBtn = document.querySelector('[data-delete-subject]');
    if (!editBtn && !deleteBtn) {
        return;
    }

    var modal = null;
    var backdrop = null;
    var lastFocus = null;

    function ensureModal() {
        if (modal) {
            return;
        }
        modal = document.createElement('div');
        modal.className = 'modal';
        modal.hidden = true;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'subject-modal-title');
        modal.innerHTML =
            '<div class="modal-header">' +
            '<h2 id="subject-modal-title">Edit subject</h2>' +
            '<button type="button" class="modal-close" aria-label="Close" data-subject-modal-close>&times;</button>' +
            '</div>' +
            '<div class="modal-body" data-subject-modal-body></div>';

        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.hidden = true;

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);

        var closeBtn = modal.querySelector('[data-subject-modal-close]');
        closeBtn.addEventListener('click', close);
        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close();
            }
        });
    }

    function open() {
        ensureModal();
        lastFocus = document.activeElement;
        modal.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('modal-open');
        var first = modal.querySelector('input, select, textarea, button');
        if (first) {
            first.focus();
        }
    }

    function close() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        backdrop.hidden = true;
        document.body.classList.remove('modal-open');
        if (lastFocus) {
            lastFocus.focus();
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showError(message) {
        var body = modal.querySelector('[data-subject-modal-body]');
        var alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        body.insertBefore(alert, body.firstChild);
    }

    /* ---- Edit flow ---- */
    if (editBtn) {
        var subjectId = editBtn.getAttribute('data-edit-subject');

        editBtn.addEventListener('click', function () {
            var subject = {};
            try {
                subject = JSON.parse(subjectId);
            } catch (e) {
                subject = {};
            }

            var nameVal = subject.name || '';
            var codeVal = subject.code || '';
            var strandVal = subject.strand || '';
            var gradeVal = subject.grade_level || 'Grade 10';

            ensureModal();
            var body = modal.querySelector('[data-subject-modal-body]');
            body.innerHTML =
                '<form data-subject-form novalidate>' +
                '<label for="subject-name">Subject name</label>' +
                '<input type="text" id="subject-name" name="name" value="' + escapeHtml(nameVal) + '" required autocomplete="off" maxlength="100">' +

                '<label for="subject-code">Code</label>' +
                '<input type="text" id="subject-code" name="code" value="' + escapeHtml(codeVal) + '" required autocomplete="off" maxlength="10" style="text-transform:uppercase">' +

                '<label for="subject-grade">Grade level</label>' +
                '<select id="subject-grade" name="grade_level">' +
                '<option value="Grade 9">Grade 9</option>' +
                '<option value="Grade 10">Grade 10</option>' +
                '<option value="Grade 11">Grade 11</option>' +
                '<option value="Grade 12">Grade 12</option>' +
                '</select>' +

                '<label for="subject-strand">Strand <span class="field-hint">— what this subject covers</span></label>' +
                '<input type="text" id="subject-strand" name="strand" value="' + escapeHtml(strandVal) + '" autocomplete="off" maxlength="190">' +

                '<div class="modal-actions">' +
                '<button type="button" class="btn" data-subject-cancel>Cancel</button>' +
                '<button type="submit" class="btn btn-primary">Save changes</button>' +
                '</div>' +
                '</form>';

            var gradeSelect = body.querySelector('#subject-grade');
            if (gradeSelect) {
                gradeSelect.value = gradeVal;
            }

            body.querySelector('[data-subject-cancel]').addEventListener('click', close);
            body.querySelector('[data-subject-form]').addEventListener('submit', submitEdit);
            open();
        });

        function submitEdit(event) {
            event.preventDefault();
            var form = event.target;
            var submitBtn = form.querySelector('button[type="submit"]');
            var payload = {
                action: 'update',
                id: parseInt(subjectId, 10),
                code: form.querySelector('#subject-code').value.trim(),
                name: form.querySelector('#subject-name').value.trim(),
                strand: form.querySelector('#subject-strand').value.trim(),
                grade_level: form.querySelector('#subject-grade').value
            };

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving\u2026';

            window.Mwalimu.postJSON('api/subjects.php', payload).then(function (data) {
                if (data && data.success) {
                    // Reload so the page heading, subtitle, and any links reflect the new name.
                    window.location.href = 'subject.php?id=' + encodeURIComponent(payload.id);
                    return;
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save changes';
                showError((data && data.error) || 'Could not save the subject.');
            }).catch(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save changes';
                showError('Network error \u2014 could not save the subject.');
            });
        }
    }

    /* ---- Delete flow ---- */
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            var id = deleteBtn.getAttribute('data-delete-subject');
            var name = deleteBtn.getAttribute('data-subject-name') || 'this subject';

            if (!window.confirm(
                'Delete ' + name + '?\n\nThis also removes all of its topics, generated lessons, and schemes of work. This cannot be undone.'
            )) {
                return;
            }

            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting\u2026';

            window.Mwalimu.postJSON('api/subjects.php', { id: id }).then(function (data) {
                if (data && data.success) {
                    window.location.href = 'dashboard.php#subjects';
                    return;
                }
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete';
                window.alert((data && data.error) || 'Could not delete the subject.');
            }).catch(function () {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete';
                window.alert('Network error \u2014 could not delete the subject.');
            });
        });
    }
})();
