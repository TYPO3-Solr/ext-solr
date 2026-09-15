import Modal from '@typo3/backend/modal.js';

  document.querySelectorAll('.t3js-modal-formsubmit-trigger').forEach((el) => {
    el.addEventListener('click', function (event) {
      event.preventDefault();

      const title = this.dataset.title;
      const content = this.dataset.content;
      const severityKey = this.dataset.severity;
      const severity =
        typeof top.TYPO3.Severity[severityKey] !== 'undefined'
          ? top.TYPO3.Severity[severityKey]
          : top.TYPO3.Severity.info;

      Modal.confirm(title, content, severity, [
        {
          text: 'Confirm',
          active: true,
          trigger: function () {
            el.closest('form').submit();
            Modal.dismiss();
          },
        },
        {
          text: 'Abort!',
          trigger: function () {
            Modal.dismiss();
          },
        },
      ]);
    });
  });
