(function (window) {
    function q(root, selector) {
        return root.querySelector(selector);
    }

    function qa(root, selector) {
        return Array.from(root.querySelectorAll(selector));
    }

    function textOrDash(value) {
        return value && String(value).trim() !== '' ? String(value).trim() : '-';
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char] || char;
        });
    }

    function renderSummaryList(container, items) {
        if (!container) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            container.textContent = '-';

            return;
        }

        container.innerHTML = '<ul>' + items.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
        }).join('') + '</ul>';
    }

    function collectSelectedSectorIds(form) {
        return qa(form, '#sectorNameList input[type="checkbox"]:checked').map(function (checkbox) {
            return checkbox.value;
        });
    }

    window.familyFormUtils = {
        q: q,
        qa: qa,
        textOrDash: textOrDash,
        renderSummaryList: renderSummaryList,
        collectSelectedSectorIds: collectSelectedSectorIds,
    };
})(window);
