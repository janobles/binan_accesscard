(function (window) {
    function createFamilyFormMemberHandlers(config) {
        const memberTemplate = config.memberTemplate;
        const memberRows = config.memberRows;
        const memberRowsEmpty = config.memberRowsEmpty;
        let memberIndex = 0;

        function setMemberRowsEmptyState() {
            if (!memberRows || !memberRowsEmpty) {
                return;
            }

            memberRowsEmpty.style.display = memberRows.children.length === 0 ? '' : 'none';
        }

        function createMemberRow(memberData) {
            if (!memberTemplate || !memberRows) {
                return;
            }

            const fragment = memberTemplate.content.cloneNode(true);
            const row = fragment.querySelector('.member-row');

            if (!row) {
                return;
            }

            row.querySelectorAll('[data-name]').forEach(function (input) {
                const fieldName = input.getAttribute('data-name') || '';

                if (fieldName === '') {
                    return;
                }

                const isArrayField = fieldName.endsWith('[]');
                const baseName = isArrayField ? fieldName.slice(0, -2) : fieldName;
                input.setAttribute('name', isArrayField
                    ? 'members[' + memberIndex + '][' + baseName + '][]'
                    : 'members[' + memberIndex + '][' + fieldName + ']');

                if (!memberData || typeof memberData !== 'object') {
                    return;
                }

                const value = Object.prototype.hasOwnProperty.call(memberData, fieldName)
                    ? memberData[fieldName]
                    : null;

                if (isArrayField) {
                    const arrayValue = Array.isArray(value) ? value.map(String) : [];

                    if (input instanceof HTMLSelectElement) {
                        Array.from(input.options).forEach(function (option) {
                            option.selected = arrayValue.includes(option.value);
                        });
                    }

                    return;
                }

                if (value === null || typeof value === 'undefined') {
                    return;
                }

                if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                    input.value = String(value);
                }
            });

            memberRows.appendChild(fragment);
            memberIndex += 1;
            setMemberRowsEmptyState();
        }

        function bindRemoveMember() {
            if (!memberRows) {
                return;
            }

            memberRows.addEventListener('click', function (event) {
                const target = event.target;

                if (target instanceof HTMLElement && target.classList.contains('remove-member')) {
                    const row = target.closest('.member-row');

                    if (row) {
                        row.remove();
                        setMemberRowsEmptyState();
                    }
                }
            });
        }

        return {
            createMemberRow: createMemberRow,
            setMemberRowsEmptyState: setMemberRowsEmptyState,
            bindRemoveMember: bindRemoveMember,
        };
    }

    window.createFamilyFormMemberHandlers = createFamilyFormMemberHandlers;
})(window);
