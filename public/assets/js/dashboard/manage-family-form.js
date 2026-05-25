// Restores edit-mode family data into the dynamic family wizard fields.
(function (window) {
    function parseJsonNode(node, fallbackValue) {
        if (!node) {
            return fallbackValue;
        }

        try {
            return JSON.parse(node.textContent || 'null') || fallbackValue;
        } catch (error) {
            return fallbackValue;
        }
    }

    function normalizeIds(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        return values.map(function (id) {
            return String(id || '').trim();
        }).filter(function (id) {
            return id !== '';
        });
    }

    function initManageFamilyForm(config) {
        if (!config || !config.form) {
            return;
        }

        const selectedSectorIdsNode = config.form.querySelector('#selectedSectorIdsData');
        const initialFamilyDataNode = config.form.querySelector('#initialFamilyData');
        const selectedSectorIds = normalizeIds(parseJsonNode(selectedSectorIdsNode, []));
        const initialFamilyData = parseJsonNode(initialFamilyDataNode, {});

        if (typeof config.setSelectedSectorIds === 'function') {
            config.setSelectedSectorIds(selectedSectorIds);
        }

        if (Array.isArray(initialFamilyData.existingMembers) && typeof config.createMemberRow === 'function') {
            initialFamilyData.existingMembers.forEach(function (member) {
                config.createMemberRow(member);
            });
        }

        if (selectedSectorIds.length > 0 && typeof config.populateSectorsByCategory === 'function') {
            config.populateSectorsByCategory();

            return;
        }

        if (typeof config.resetSectorSelection === 'function') {
            config.resetSectorSelection();
        }
    }

    window.initManageFamilyForm = initManageFamilyForm;
})(window);
