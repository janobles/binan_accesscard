$(function () {
    let memberIndex = 0;

    function addMemberRow() {
        const $row = $($('#memberTemplate').html());

        $row.find('[data-name]').each(function () {
            const field = $(this).data('name');
            $(this).attr('name', 'members[' + memberIndex + '][' + field + ']');
        });

        $('#memberRows').append($row);
        memberIndex++;
    }

    $('#addMemberBtn').on('click', addMemberRow);

    $('#memberRows').on('click', '.remove-member', function () {
        $(this).closest('.member-row').remove();
    });
});
