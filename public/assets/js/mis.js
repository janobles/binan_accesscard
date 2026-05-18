$(function () {
    let memberIndex = 0;
    const $familyForm = $('#familyForm');
    const $memberRows = $('#memberRows');
    const $memberTemplate = $('#memberTemplate');
    const $alertBox = $('#familyFormAlert');

    function addMemberRow() {
        if ($memberTemplate.length === 0 || $memberRows.length === 0) {
            return;
        }

        const $row = $($memberTemplate.html());

        $row.find('[data-name]').each(function () {
            const field = $(this).data('name');
            $(this).attr('name', 'members[' + memberIndex + '][' + field + ']');
        });

        $memberRows.append($row);
        memberIndex++;
    }

    function renderAlert(type, message) {
        if ($alertBox.length === 0) {
            return;
        }

        let className = 'alert-danger';

        if (type === 'success') {
            className = 'alert-success';
        } else if (type === 'info') {
            className = 'alert-info';
        }

        $alertBox.html('<div class="alert ' + className + ' mb-0">' + message + '</div>');
    }

    function updateCsrfToken(tokenValue) {
        if ($familyForm.length === 0 || ! tokenValue) {
            return;
        }

        const csrfName = $familyForm.data('csrfName');

        if (! csrfName) {
            return;
        }

        $familyForm.find('input[name="' + csrfName + '"]').val(tokenValue);
    }

    function resetFamilyForm() {
        if ($familyForm.length === 0) {
            return;
        }

        $familyForm[0].reset();
        $memberRows.empty();
        memberIndex = 0;
    }

    $('#addMemberBtn').on('click', addMemberRow);

    $memberRows.on('click', '.remove-member', function () {
        $(this).closest('.member-row').remove();
    });

    $familyForm.on('submit', function (event) {
        if ($familyForm.data('ajax') !== true && $familyForm.data('ajax') !== 'true') {
            return;
        }

        event.preventDefault();

        const $submitButton = $familyForm.find('button[type="submit"]');
        $submitButton.prop('disabled', true);
        renderAlert('info', 'Saving family data...');

        $.ajax({
            url: $familyForm.attr('action'),
            method: 'POST',
            data: $familyForm.serialize(),
            dataType: 'json',
        })
            .done(function (response) {
                updateCsrfToken(response.csrf || '');

                if (response.status === 'success') {
                    renderAlert('success', response.message || 'Family and member data saved successfully.');
                    resetFamilyForm();
                    return;
                }

                renderAlert('error', response.message || 'The family form could not be saved.');
            })
            .fail(function (xhr) {
                const response = xhr.responseJSON || {};
                updateCsrfToken(response.csrf || '');
                renderAlert('error', response.message || 'The request failed. Please try again.');
            })
            .always(function () {
                $submitButton.prop('disabled', false);
            });
    });
});
