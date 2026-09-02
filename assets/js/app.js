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
