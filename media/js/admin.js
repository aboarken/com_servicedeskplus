/**
 * @license GNU General Public License version 2 or later
 *
 * Find Endpoints: copy the Diagnostics report to the clipboard.
 */
document.addEventListener('DOMContentLoaded', () => {
  const button = document.getElementById('sdp-diag-copy');
  const report = document.getElementById('sdp-diag-report');

  if (!button || !report) {
    return;
  }

  button.addEventListener('click', async () => {
    report.select();

    try {
      await navigator.clipboard.writeText(report.value);
    } catch (e) {
      document.execCommand('copy');
    }

    button.textContent = button.dataset.done;
  });
});
