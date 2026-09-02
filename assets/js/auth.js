/* MwalimuPlus — auth page helpers: password visibility toggle,
 * password strength meter, and confirm-password match check.
 * All progressive enhancements; the forms work without JS. */
(function () {
    'use strict';

    // --- Visibility toggle (login + setup + reset) ---
    var toggles = document.querySelectorAll('[data-toggle-password]');
    toggles.forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }
        toggle.addEventListener('click', function () {
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggle.textContent = isHidden ? 'Hide' : 'Show';
            toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            input.focus();
        });
    });

    // --- Strength meter (setup page only) ---
    var password = document.getElementById('password');
    var strengthBar = document.querySelector('[data-strength-bar]');
    var strengthLabel = document.querySelector('[data-strength-label]');

    function scorePassword(value) {
        var score = 0;
        if (value.length >= 8) {
            score++;
        }
        if (value.length >= 12) {
            score++;
        }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
            score++;
        }
        if (/\d/.test(value)) {
            score++;
        }
        if (/[^A-Za-z0-9]/.test(value)) {
            score++;
        }
        return score;
    }

    function renderStrength(value) {
        if (!strengthBar || !strengthLabel) {
            return;
        }
        if (value === '') {
            strengthBar.className = 'strength-bar';
            strengthLabel.textContent = 'At least 8 characters.';
            strengthLabel.className = 'field-hint';
            return;
        }

        var score = scorePassword(value);
        var levels = [
            { label: 'Weak', className: 'strength-1' },
            { label: 'Weak', className: 'strength-1' },
            { label: 'Fair', className: 'strength-2' },
            { label: 'Good', className: 'strength-3' },
            { label: 'Strong', className: 'strength-4' },
            { label: 'Strong', className: 'strength-4' }
        ];
        var level = levels[score];
        strengthBar.className = 'strength-bar ' + level.className;
        strengthLabel.textContent = 'Strength: ' + level.label;
        strengthLabel.className = 'field-hint strength-label-' + level.className;
    }

    if (password && strengthBar) {
        password.addEventListener('input', function () {
            renderStrength(password.value);
            checkMatch();
        });
    }

    // --- Confirm match check (setup page only) ---
    var confirmInput = document.getElementById('confirm');
    var matchHint = document.querySelector('[data-match-hint]');

    function checkMatch() {
        if (!confirmInput || !matchHint) {
            return;
        }
        var confirmValue = confirmInput.value;
        if (confirmValue === '') {
            matchHint.textContent = '';
            matchHint.className = 'field-hint match-hint';
            return;
        }
        if (password && confirmValue === password.value) {
            matchHint.textContent = 'Passwords match.';
            matchHint.className = 'field-hint match-hint match-ok';
        } else {
            matchHint.textContent = 'Passwords do not match.';
            matchHint.className = 'field-hint match-hint match-error';
        }
    }

    if (confirmInput && matchHint) {
        confirmInput.addEventListener('input', checkMatch);
    }
})();
