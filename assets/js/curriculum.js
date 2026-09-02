/* MwalimuPlus — curriculum admin (curriculum.php) */
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

    var subjectForm = document.getElementById('subject-form');
    if (subjectForm) {
        var subjectResult = document.getElementById('subject-result');
        var subjectBtn = document.getElementById('subject-btn');

        subjectForm.addEventListener('submit', function (event) {
            event.preventDefault();
            subjectBtn.disabled = true;
            subjectResult.hidden = true;

            var formData = new FormData(subjectForm);
            formData.set('action', 'add_subject');

            fetch('api/curriculum.php', { method: 'POST', body: formData })
                .then(function (res) {
                    return res.json().catch(function () {
                        return { success: false, error: 'Unexpected server response.' };
                    });
                })
                .then(function (data) {
                    subjectResult.hidden = false;
                    if (!data || !data.success) {
                        subjectResult.innerHTML = '<div class="alert alert-error">' + escapeHtml((data && data.error) || 'Could not add the subject.') + '</div>';
                        return;
                    }
                    window.location.href = 'subject.php?id=' + encodeURIComponent(data.subject_id);
                }).catch(function () {
                    subjectResult.hidden = false;
                    subjectResult.innerHTML = '<div class="alert alert-error">Network error — could not reach the server.</div>';
                }).then(function () {
                    subjectBtn.disabled = false;
                });
        });
    }

    var topicForm = document.getElementById('topic-form');
    if (topicForm) {
        var topicResult = document.getElementById('topic-result');
        var topicBtn = document.getElementById('topic-btn');

        topicForm.addEventListener('submit', function (event) {
            event.preventDefault();
            topicBtn.disabled = true;
            topicResult.hidden = true;

            window.Mwalimu.postJSON('api/curriculum.php', {
                action: 'add_topic',
                csrf_token: document.getElementById('topic-csrf').value,
                subject_id: parseInt(document.getElementById('topic-subject').value, 10),
                name: document.getElementById('topic-name').value.trim(),
                strand: document.getElementById('topic-strand').value.trim(),
                source_text: document.getElementById('topic-source').value.trim()
            }).then(function (data) {
                topicResult.hidden = false;
                if (!data || !data.success) {
                    topicResult.innerHTML = '<div class="alert alert-error">' + escapeHtml((data && data.error) || 'Could not add the topic.') + '</div>';
                    return;
                }
                topicResult.innerHTML = '<div class="alert alert-success">Topic added.</div>';
                topicForm.reset();
            }).catch(function () {
                topicResult.hidden = false;
                topicResult.innerHTML = '<div class="alert alert-error">Network error — could not reach the server.</div>';
            }).then(function () {
                topicBtn.disabled = false;
            });
        });
    }
})();
