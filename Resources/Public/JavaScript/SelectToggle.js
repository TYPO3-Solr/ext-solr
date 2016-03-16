define([
    'jquery'
], function($, Modal) {
    $(document).ready(function() {

        $('.t3js-toggle-checkboxes').each(function() {
            var $checkbox = $(this);
            var $table = $checkbox.closest('table');
            var $checkboxes = $table.find('.t3js-checkbox');
            var checkIt = $checkboxes.length === $table.find('.t3js-checkbox:checked').length;
            $checkbox.prop('checked', checkIt);
        });
        $(document).on('change', '.t3js-toggle-checkboxes', function(e) {
            e.preventDefault();
            var $checkbox = $(this);
            var $table = $checkbox.closest('table');
            var $checkboxes = $table.find('.t3js-checkbox');
            var checkIt = $checkboxes.length !== $table.find('.t3js-checkbox:checked').length;
            $checkboxes.prop('checked', checkIt);
            $checkbox.prop('checked', checkIt);
        });
        $(document).on('change', '.t3js-checkbox', function(e) {
            FormEngine.updateCheckboxState(this);
        });
    });
});
