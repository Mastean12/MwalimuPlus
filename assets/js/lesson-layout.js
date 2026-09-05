/* MwalimuPlus — lesson.php declutter: turns the long stack of sections into
 * a tap-to-open accordion. Pure progressive enhancement: everything renders
 * fully open if this script never runs (see the CSS: .is-collapsed is the
 * only thing that hides content, and only JS adds that class). */
(function () {
    'use strict';

    var main = document.querySelector('main.content');
    if (!main) {
        return;
    }

    var sections = Array.prototype.filter.call(
        main.querySelectorAll(':scope > section.panel'),
        function (section) {
            return !section.classList.contains('panel-sijui') && section.id !== 'share-panel';
        }
    );
    if (sections.length < 2) {
        return; // nothing worth collapsing
    }

    sections.forEach(function (section, index) {
        var heading = section.querySelector('h2');
        if (!heading) {
            return;
        }

        heading.classList.add('accordion-toggle');
        heading.setAttribute('role', 'button');
        heading.setAttribute('tabindex', '0');

        var collapsed = index !== 0; // the first section starts open
        section.classList.toggle('is-collapsed', collapsed);
        heading.setAttribute('aria-expanded', String(!collapsed));

        function toggle(event) {
            // Let a nested control (e.g. the "Listen to section" button) do
            // its own thing instead of also opening/closing the section.
            if (event.target !== heading && event.target.closest('button, a, input, select, textarea')) {
                return;
            }
            var nowCollapsed = !section.classList.contains('is-collapsed');
            section.classList.toggle('is-collapsed', nowCollapsed);
            heading.setAttribute('aria-expanded', String(!nowCollapsed));
        }

        heading.addEventListener('click', toggle);
        heading.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle(event);
            }
        });
    });
})();
