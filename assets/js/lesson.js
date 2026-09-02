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
