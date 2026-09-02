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
