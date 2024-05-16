import $ from 'jquery';
import Modal from 'TYPO3/CMS/Backend/Modal';

$(document).ready(() => {
  $('.t3js-modal-formsubmit-trigger').click(function(){
    const element = $(this);
    const title = element.data('title');
    const content = element.data('content');
    let severity = typeof top.TYPO3.Severity[element.data('severity')] !== 'undefined'
      ? top.TYPO3.Severity[element.data('severity')]
      : top.TYPO3.Severity.info;

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
  });
});
