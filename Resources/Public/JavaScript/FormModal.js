define([
    'jquery',
    'TYPO3/CMS/Backend/Modal'
], function($, Modal) {
    $(document).ready(function() {


        $('.t3js-modal-formsubmit-trigger').click(function(){

            var element = $(this);
            var title = element.data('title');
            var content = element.data('content');
            var severity = (typeof top.TYPO3.Severity[element.data('severity')] !== 'undefined') ? top.TYPO3.Severity[element.data('severity')] : top.TYPO3.Severity.info;

            Modal.confirm(title, content, severity, [
                {
                    text: 'Confirm',
                    active: true,
                    trigger: function() {
                        element.parents('form:first').submit();
                        Modal.dismiss();
                    }
                }, {
                    text: 'Abort!',
                    trigger: function() {
                        Modal.dismiss();
                    }
                }
            ]);

            return false;
        })
    });
});