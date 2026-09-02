/* MwalimuPlus — shared helpers (all pages) */
(function () {
    'use strict';

    /** Minimal JSON POST helper with the auth cookie applied automatically. */
    function postJSON(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data || {})
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, error: 'Unexpected server response.' };
            });
        });
    }

    /** Creates DOM from an HTML string. */
    function el(html) {
        var template = document.createElement('template');
        template.innerHTML = html.trim();
        return template.content.firstChild;
    }

    window.Mwalimu = {
        postJSON: postJSON,
        el: el
    };
})();

/* Live clock in the top bar */
(function () {
    var node = document.getElementById('topbar-clock');
    if (!node) {
        return;
    }
    function tick() {
        var now = new Date();
        var date = now.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
        var time = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        node.textContent = date + ' · ' + time;
        node.setAttribute('datetime', now.toISOString());
    }
    tick();
    setInterval(tick, 30000);
})();

/* Mobile sidebar drawer */
(function () {
    var btn = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    if (!btn || !sidebar) {
        return;
    }

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) {
            backdrop.hidden = !open;
        }
    }

    btn.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('open'));
    });
    if (backdrop) {
        backdrop.addEventListener('click', function () { setOpen(false); });
    }
    sidebar.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            setOpen(false);
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();

/* Generic client-side table filtering.
   Controls carry data-filter-for="<tableId>"; add data-filter-col="<key>" to
   match a row's data-<key> exactly, otherwise the control does a text search.
   An optional [data-filter-empty="<tableId>"] element shows when nothing matches. */
(function () {
    var controls = document.querySelectorAll('[data-filter-for]');
    if (!controls.length) {
        return;
    }

    var groups = {};
    Array.prototype.forEach.call(controls, function (control) {
        var id = control.getAttribute('data-filter-for');
        (groups[id] = groups[id] || []).push(control);
    });

    Object.keys(groups).forEach(function (id) {
        var table = document.getElementById(id);
        if (!table || !table.tBodies.length) {
            return;
        }
        var rows = Array.prototype.slice.call(table.tBodies[0].rows);
        var emptyMsg = document.querySelector('[data-filter-empty="' + id + '"]');

        function apply() {
            var shown = 0;
            rows.forEach(function (row) {
                var match = groups[id].every(function (control) {
                    var value = (control.value || '').trim().toLowerCase();
                    if (!value) {
                        return true;
                    }
                    var col = control.getAttribute('data-filter-col');
                    if (col) {
                        return (row.getAttribute('data-' + col) || '').toLowerCase() === value;
                    }
                    return row.textContent.toLowerCase().indexOf(value) !== -1;
                });
                row.hidden = !match;
                if (match) {
                    shown++;
                }
            });
            if (emptyMsg) {
                emptyMsg.hidden = shown !== 0;
            }
        }

        groups[id].forEach(function (control) {
            control.addEventListener('input', apply);
        });
    });
})();

/* Profile modal: opens profile.php's content in a dialog instead of
   navigating there. Falls back to a normal page load when JS is off, since
   the trigger is a real link and the forms inside post to profile.php. */
(function () {
    var trigger = document.querySelector('[data-open-profile]');
    var modal = document.querySelector('[data-profile-modal]');
    var backdrop = document.querySelector('[data-profile-modal-backdrop]');
    var body = document.querySelector('[data-profile-modal-body]');
    var closeBtn = document.querySelector('[data-profile-modal-close]');
    if (!trigger || !modal || !backdrop || !body || !closeBtn) {
        return;
    }

    function loadContent() {
        body.innerHTML = '<p class="muted">Loading…</p>';
        fetch('profile.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function () {
                body.innerHTML = '<div class="alert alert-error">Could not load your profile. Please try again.</div>';
            });
    }

    function open() {
        modal.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('modal-open');
        loadContent();
        closeBtn.focus();
    }

    function close() {
        modal.hidden = true;
        backdrop.hidden = true;
        document.body.classList.remove('modal-open');
        trigger.focus();
    }

    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        open();
    });
    closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });

    // Submit the account-details/change-password forms without leaving the modal.
    body.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        event.preventDefault();

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                body.innerHTML = html;
                body.scrollTop = 0;

                // Keep the topbar in sync if the name changed.
                var nameInput = body.querySelector('#name');
                if (nameInput && nameInput.value) {
                    var topbarName = document.querySelector('.topbar-user-link .user-name');
                    var topbarAvatar = document.querySelector('.topbar-user-link .user-avatar');
                    if (topbarName) {
                        topbarName.textContent = nameInput.value;
                    }
                    if (topbarAvatar) {
                        topbarAvatar.textContent = nameInput.value.charAt(0).toUpperCase();
                    }
                }
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                body.insertAdjacentHTML('afterbegin', '<div class="alert alert-error">Could not save changes. Please try again.</div>');
            });
    });

    // Password show/hide toggle, delegated so it keeps working after the
    // modal body's content is replaced (e.g. after a submit re-render).
    body.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-toggle-password]');
        if (!toggle) {
            return;
        }
        var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        toggle.textContent = isHidden ? 'Hide' : 'Show';
        toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });

    // Password strength meter + confirm-match check, same delegated approach.
    function scorePassword(value) {
        var score = 0;
        if (value.length >= 8) { score++; }
        if (value.length >= 12) { score++; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score++; }
        if (/\d/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value)) { score++; }
        return score;
    }

    function updatePasswordHints() {
        var password = body.querySelector('#password');
        var confirmInput = body.querySelector('#confirm');
        var strengthBar = body.querySelector('[data-strength-bar]');
        var strengthLabel = body.querySelector('[data-strength-label]');
        var matchHint = body.querySelector('[data-match-hint]');

        if (password && strengthBar && strengthLabel) {
            var value = password.value;
            if (value === '') {
                strengthBar.className = 'strength-bar';
                strengthLabel.textContent = 'At least 8 characters.';
                strengthLabel.className = 'field-hint';
            } else {
                var levels = [
                    { label: 'Weak', className: 'strength-1' },
                    { label: 'Weak', className: 'strength-1' },
                    { label: 'Fair', className: 'strength-2' },
                    { label: 'Good', className: 'strength-3' },
                    { label: 'Strong', className: 'strength-4' },
                    { label: 'Strong', className: 'strength-4' }
                ];
                var level = levels[scorePassword(value)];
                strengthBar.className = 'strength-bar ' + level.className;
                strengthLabel.textContent = 'Strength: ' + level.label;
                strengthLabel.className = 'field-hint strength-label-' + level.className;
            }
        }

        if (confirmInput && matchHint) {
            var confirmValue = confirmInput.value;
            if (confirmValue === '') {
                matchHint.textContent = '';
                matchHint.className = 'field-hint match-hint';
            } else if (password && confirmValue === password.value) {
                matchHint.textContent = 'Passwords match.';
                matchHint.className = 'field-hint match-hint match-ok';
            } else {
                matchHint.textContent = 'Passwords do not match.';
                matchHint.className = 'field-hint match-hint match-error';
            }
        }
    }

    body.addEventListener('input', function (event) {
        if (event.target.id === 'password' || event.target.id === 'confirm') {
            updatePasswordHints();
        }
    });
})();
