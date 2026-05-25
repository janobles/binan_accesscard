(function (window) {
    function createFamilyFormSummaryUpdater(config) {
        const form = config.form;
        const q = config.q;
        const qa = config.qa;
        const textOrDash = config.textOrDash;
        const renderSummaryList = config.renderSummaryList;
        const headSummaryName = config.headSummaryName;
        const headSummaryBirthday = config.headSummaryBirthday;
        const headSummarySex = config.headSummarySex;
        const headSummaryCivil = config.headSummaryCivil;
        const headSummaryContact = config.headSummaryContact;
        const headSummaryEducation = config.headSummaryEducation;
        const headSummaryJob = config.headSummaryJob;
        const headSummaryIncome = config.headSummaryIncome;
        const headSummarySectors = config.headSummarySectors;
        const headSummaryServices = config.headSummaryServices;

        const summaryMap = [
            ['#head_birthday', headSummaryBirthday],
            ['#head_sex', headSummarySex],
            ['#head_civilstatus', headSummaryCivil],
            ['#head_contactnumber', headSummaryContact],
            ['#head_education', headSummaryEducation],
            ['#head_job', headSummaryJob],
        ];

        return function updateHeadSummary() {
            const firstName = (q(form, '#head_firstname') || {}).value || '';
            const middleName = (q(form, '#head_middlename') || {}).value || '';
            const lastName = (q(form, '#head_lastname') || {}).value || '';
            const suffix = (q(form, '#head_suffix') || {}).value || '';
            const fullName = [firstName, middleName, lastName, suffix].filter(Boolean).join(' ').trim();

            if (headSummaryName) {
                headSummaryName.textContent = textOrDash(fullName);
            }

            summaryMap.forEach(function (item) {
                const input = q(form, item[0]);
                const output = item[1];

                if (output) {
                    output.textContent = textOrDash(input && input.value);
                }
            });

            if (headSummaryIncome) {
                const salarySelect = q(form, '#head_salary');
                const label = salarySelect && salarySelect.selectedOptions.length > 0
                    ? salarySelect.selectedOptions[0].textContent
                    : '';
                headSummaryIncome.textContent = textOrDash(label);
            }

            const selectedSectors = qa(form, '#sectorNameList input[type="checkbox"]:checked').map(function (checkbox) {
                return String(checkbox.dataset.name || '').trim();
            }).filter(function (value) {
                return value !== '';
            });

            const selectedServices = qa(form, 'input[name="service_ids[]"]:checked').map(function (checkbox) {
                const label = checkbox.closest('label');
                const text = label ? label.textContent : '';

                return (text || '').trim();
            }).filter(function (value) {
                return value !== '';
            });

            renderSummaryList(headSummarySectors, selectedSectors);
            renderSummaryList(headSummaryServices, selectedServices);
        };
    }

    window.createFamilyFormSummaryUpdater = createFamilyFormSummaryUpdater;
})(window);
