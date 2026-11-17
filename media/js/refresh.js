/**
 * The page was shown with results older than the cache time (see DisplayController). Load the
 * same page again in the background, without older results, and replace the content:
 *  - "Updating…" is shown meanwhile (role=status, so screen readers hear it);
 *  - if the user has started typing or choosing in the page, it is not replaced: a "Reload" link
 *    is offered instead, so nothing they entered is lost;
 *  - if the page no longer exists for the user (403/404), it is loaded normally instead;
 *  - if the refresh fails, or brings an error where the shown page had none (ServiceDesk Plus
 *    unavailable), the shown content stays and the note says so.
 * Scripts of the page re-attach to the new content on the "sdp:refreshed" event.
 *
 * @license GNU General Public License version 2 or later
 */
(() => {
  'use strict';

  const options = window.Joomla && Joomla.getOptions ? Joomla.getOptions('com_servicedeskplus.refresh') : null;

  if (!options || !options.url) {
    return;
  }

  const text = (key) => (Joomla.Text._(key) || key).replace('%s', options.since || '');

  const start = () => {
    const root = document.querySelector('.com-servicedeskplus');

    if (!root) {
      return;
    }

    let touched = false;
    const touch = () => { touched = true; };
    root.addEventListener('input', touch);
    root.addEventListener('change', touch);

    const note = document.createElement('p');
    note.className = 'sdp-refresh-note';
    note.setAttribute('role', 'status');
    note.textContent = text('COM_SERVICEDESKPLUS_REFRESH_UPDATING');
    root.prepend(note);
    root.setAttribute('aria-busy', 'true');

    const fail = () => {
      note.textContent = text('COM_SERVICEDESKPLUS_REFRESH_FAILED');
      note.classList.add('sdp-refresh-note--error');
    };

    fetch(options.url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((response) => {
        // The page no longer exists for this user (e.g. not theirs any more): never keep showing
        // the older copy; load it normally, so its real answer is shown.
        if ((response.status === 403 || response.status === 404) && options.page) {
          window.location.replace(options.page);

          return new Promise(() => {});
        }

        if (!response.ok) {
          throw new Error(String(response.status));
        }

        return response.text();
      })
      .then((html) => {
        const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.com-servicedeskplus');

        // Another page (e.g. login, "could not be matched") or an error where there was none.
        if (!fresh || fresh.className !== root.className
          || (fresh.querySelector('[role="alert"]') && !root.querySelector('[role="alert"]'))) {
          fail();

          return;
        }

        if (touched) {
          note.textContent = text('COM_SERVICEDESKPLUS_REFRESH_READY') + ' ';
          const link = document.createElement('a');
          link.href = window.location.href;
          link.textContent = text('COM_SERVICEDESKPLUS_REFRESH_RELOAD');
          note.append(link);

          return;
        }

        const node = document.importNode(fresh, true);
        root.replaceWith(node);
        document.dispatchEvent(new CustomEvent('sdp:refreshed', { detail: { root: node } }));
      })
      .catch(fail)
      .finally(() => root.removeAttribute('aria-busy'));
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
