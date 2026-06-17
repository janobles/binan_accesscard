// Per-row audit "Details" modal.
//
// Clicking any .js-audit-detail button reads the who/what/when/where data carried
// on the button's data-* attributes and shows them in the shared #auditDetailModal,
// without any AJAX call or new backend route. Used on:
//   - Views/Admin/audit-trails.php       (admin Audit Trails table)
//   - Views/Employee/layout.php          (employee My Activity table)
// Depends on: Bootstrap Modal (bootstrap.bundle.min.js). jQuery not required.
(function (window, document) {
    'use strict';

    function text(value) {
        var str = (value === null || value === undefined) ? '' : String(value).trim();
        return str === '' ? '—' : str;
    }

    function setField(modal, id, value) {
        var el = modal.querySelector('#' + id);
        if (el) {
            el.textContent = text(value);
        }
    }

    function openFromButton(button) {
        var modalEl = document.getElementById('auditDetailModal');
        if (!modalEl || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            return;
        }

        setField(modalEl, 'auditDetailAction', button.getAttribute('data-action'));
        setField(modalEl, 'auditDetailUser', button.getAttribute('data-user'));
        setField(modalEl, 'auditDetailMember', button.getAttribute('data-member'));
        setField(modalEl, 'auditDetailWhen', button.getAttribute('data-when'));
        setField(modalEl, 'auditDetailIp', button.getAttribute('data-ip'));
        setField(modalEl, 'auditDetailUa', button.getAttribute('data-ua'));
        setField(modalEl, 'auditDetailFull', button.getAttribute('data-full'));

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-audit-detail');
        if (button) {
            event.preventDefault();
            openFromButton(button);
        }
    });

    // Keyboard support for row-style triggers (role="button" table rows): Enter / Space.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Spacebar') {
            return;
        }

        var trigger = event.target.closest('.js-audit-detail[role="button"]');
        if (trigger) {
            event.preventDefault();
            openFromButton(trigger);
        }
    });
})(window, document);
