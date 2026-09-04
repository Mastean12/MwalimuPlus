/* MwalimuPlus — share a lesson PDF to a parent over WhatsApp (lesson.php) */
(function () {
    'use strict';

    var panel = document.getElementById('share-panel');
    var toggle = document.getElementById('share-lesson-toggle');
    var form = document.getElementById('share-form');
    if (!panel || !toggle || !form) {
        return;
    }

    var phoneInput = document.getElementById('share-phone');
    var messageInput = document.getElementById('share-message');
    var submitBtn = document.getElementById('share-btn');
    var status = document.getElementById('share-status');

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && phoneInput) {
            phoneInput.focus();
        }
    }

    function report(kind, text) {
        status.className = 'alert ' + (kind === 'ok' ? 'alert-success' : 'alert-error');
        status.textContent = text;
    }

    toggle.addEventListener('click', function () {
        setOpen(panel.hidden);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var phone = (phoneInput.value || '').trim();
        if (!phone) {
            report('error', 'Enter the parent\u2019s WhatsApp number.');
            phoneInput.focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending\u2026';
        status.className = '';
        status.textContent = '';

        window.Mwalimu.postJSON('api/share-lesson.php', {
            id: parseInt(form.querySelector('input[name="id"]').value, 10),
            phone: phone,
            message: (messageInput ? messageInput.value : '').trim()
        }).then(function (data) {
            if (data && data.success) {
                report('ok', data.message || 'PDF sent via WhatsApp.');
                if (phoneInput) {
                    phoneInput.value = '';
                }
                if (messageInput) {
                    messageInput.value = '';
                }
                submitBtn.textContent = 'Sent \u2014 send another';
            } else {
                report('error', (data && data.error) || 'Something went wrong. Please try again.');
            }
        }).catch(function () {
            report('error', 'Network error \u2014 could not reach the server.');
        }).then(function () {
            submitBtn.disabled = false;
            if (!/sent/i.test(submitBtn.textContent)) {
                submitBtn.textContent = 'Send PDF via WhatsApp';
            }
        });
    });
})();
