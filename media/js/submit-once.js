/**
 * Forms marked data-sdp-submit-once:
 *  - buttons with data-sdp-confirm ask for confirmation first;
 *  - the clicked button's value is copied into the hidden [data-sdp-decision] input, because
 *    disabled buttons are not submitted;
 *  - after submit all submit buttons are disabled and show a busy state.
 * The server also rejects a second POST with the same one-time token, so this is a convenience.
 *
 * @license GNU General Public License version 2 or later
 */
(() => {
  'use strict';

  const lock = (form) => {
    form.dataset.sdpSubmitted = '1';

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    });
  };

  const showBusy = (button) => {
    if (!button) {
      return;
    }

    const spinner = button.querySelector('[data-sdp-spinner]');
    const label = button.querySelector('[data-sdp-label]');

    if (spinner) {
      spinner.classList.remove('d-none');
    }

    if (label && label.dataset.sdpBusyText) {
      label.textContent = label.dataset.sdpBusyText;
    }
  };

  // Early check of chosen files (type by extension, count, sizes). The server checks again,
  // including the real file content.
  const filesAreValid = (form) => {
    let ok = true;

    form.querySelectorAll('input[type="file"][data-sdp-extensions]').forEach((input) => {
      const files = Array.from(input.files || []);
      const allowed = (input.dataset.sdpExtensions || '').split(',');
      const maxFiles = parseInt(input.dataset.sdpMaxFiles || '0', 10);
      const maxBytes = parseInt(input.dataset.sdpMaxBytes || '0', 10);
      const maxTotal = parseInt(input.dataset.sdpMaxTotal || '0', 10);
      let total = 0;

      if (maxFiles && files.length > maxFiles) {
        ok = false;
      }

      files.forEach((file) => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        total += file.size;

        if (!allowed.includes(ext) || file.size === 0 || (maxBytes && file.size > maxBytes)) {
          ok = false;
        }
      });

      if (maxTotal && total > maxTotal) {
        ok = false;
      }

      if (!ok && input.dataset.sdpErrorText) {
        window.alert(input.dataset.sdpErrorText);
        input.focus();
      }
    });

    return ok;
  };

  const init = () => {
    document.querySelectorAll('form[data-sdp-submit-once]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (form.dataset.sdpSubmitted === '1') {
          event.preventDefault();
          return;
        }

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
          return;
        }

        if (!filesAreValid(form)) {
          event.preventDefault();
          return;
        }

        const submitter = event.submitter || form.querySelector('button[type="submit"]');
        const question = submitter ? submitter.dataset.sdpConfirm : '';

        if (question && !window.confirm(question)) {
          event.preventDefault();
          return;
        }

        const hidden = form.querySelector('[data-sdp-decision]');

        if (hidden && submitter && submitter.name === hidden.name) {
          hidden.value = submitter.value;
        }

        showBusy(submitter);
        lock(form);
      });
    });
  };

  // Re-enable forms when the page is restored from the back/forward cache.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      window.location.reload();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
