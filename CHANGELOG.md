# Changelog

All notable changes to `com_servicedeskplus` are documented in this file.
Versions follow the component manifest (`servicedeskplus.xml`) and use [Semantic Versioning](https://semver.org/).

## [1.7.0] - 2026-11-14

### Added
- **Updates from the Joomla backend.** The component now has an update server, so new versions appear under **System → Update → Extensions** and install with one click. Every GitHub release carries `updates.xml` (version, download link and SHA-256 checksum), which Joomla reads from `releases/latest/download/updates.xml`. Sites on 1.6.0 or older install 1.7.0 once by hand; later versions then arrive through Joomla.

### Changed
- **Page titles follow the menu item.** My Requests, New Request, My Loans and My Approvals show the menu item's title as page heading and browser title. The menu item's *Page Heading*, *Browser Page Title* and *Show Page Heading* settings are respected. Without a menu item, the component's own titles are used.
- **My Requests:** the *Submit a Request* button has the same size as the *Search* button and lines up with it.
- **Request details:** *Technician Group* is shown before *Technician*. Priority, Urgency, Technician Group, Technician, Category, Subcategory and Item now all show "Not assigned" when empty (Subcategory and Item showed "—").
- **Backend:** "Settings" is now **Integration Settings** and "User mappings" is **User Mappings**.
- **Extension description** (shown after installing): "Integrate ManageEngine ServiceDesk Plus with Joomla to provide a centralized IT self-service portal for users."

## [1.6.0] - 2026-11-14

### Removed
- **Settings → Approvals** section: the *Pending approval status* and *Requests checked for approvals* options. My Approvals keeps working with the built-in values, which match a standard ServiceDesk Plus setup: the status "Pending Approval" and the 50 most recent requests waiting for approval.
- **Test asset endpoint** toolbar button in Settings. It was a one-time diagnostic for checking the asset API; My Loans is not affected. **Test connection** and **Read certificate pin** stay.
- Saved values of the two removed options are deleted from the database when you upgrade.

## [1.5.0] - 2026-11-14

### New Request form
- The menu item type is now called **Request Form** (was "Submit Request").
- The page title is now **New Request** (was "Submit Request").
- The submit button is now labelled **Submit** (was "Submit Request").
- The **Cancel** button is now a neutral grey button, styled like the Search button on My Requests.
- Attachments are limited to **3 files**, **1 MB per file** (3 MB in total). Previously 5 files, 10 MB each, 20 MB in total.
  - Enforced on the server by `AttachmentValidator`: the size of the stored upload is used (never the size reported by the browser), and the file count includes every file sent, valid or not, and is checked before any file is read.
  - The form also checks files as soon as they are chosen and clears a selection that breaks the limits.
  - Error and help texts take their numbers from the validator constants, so they always match the enforced limits.

### My Requests
- The button at the top right is now labelled **Submit a Request**.
- Fixed the Search button: it sat in a one-column grid cell, so its label overflowed or wrapped at tablet and small desktop widths. The filter bar now uses a responsive layout and the button has a consistent grey style and the same height as the inputs.
- The status filter now defaults to **Open** when the page is opened without a filter (menu link, "Back to My Requests"). "All statuses" stays selected while paging (`status=all`). If no status named "Open" is configured, the list shows all statuses as before.

### Request details
- Request details now show **Urgency**, **Technician Group**, **Subcategory** and **Item**, read from the ServiceDesk Plus request (`urgency`, `group`, `subcategory`, `item`). Empty values show "—", and an unassigned group shows "Not assigned".
- Request details cached in the session by an older version still load after the upgrade.

### Other
- The portal stylesheet is now loaded on the New Request page.
- Web asset registry version raised to 1.5.0 so browsers load the new CSS and JavaScript.
- No database schema changes (empty `1.5.0.sql` update files).
- New **Release package** GitHub Actions workflow: builds and checks the installation package for every pull request and attaches `com_servicedeskplus.zip` to each GitHub release.

## [1.4.0]

- Baseline release imported into this repository.
