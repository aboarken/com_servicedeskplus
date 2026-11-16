# Changelog

All notable changes to `com_servicedeskplus` are documented in this file.
Versions follow the component manifest (`servicedeskplus.xml`) and use [Semantic Versioning](https://semver.org/).

## [1.18.3] - 2026-11-16

### Changed
- **Repository renamed** to `aboarken/joomla-servicedeskplus`: the update server, changelog and links point to the new address.

## [1.18.2] - 2026-11-16

### Changed
- **Approval comment in ServiceDesk Plus:** the approver's own text now follows the portal line directly, without "Custom comment:", e.g. `Approved by (username) Full Name via the Joomla portal: <the user's comment>`. Without a comment the line still ends "via the Joomla portal.". Decisions recorded earlier keep their wording.

## [1.18.1] - 2026-11-16

### Added
- **Dashboard, Announcements:** *End date* column ("No end date" when there is none).

### Changed
- **Filter bars** size each list to its longest option, so *Waiting for my approval* on My Approvals is shown in full; fields wrap to a second line on narrow screens.
- **Announcements list:** the *Posted by* column is removed. It is still shown on the announcement's own page.

## [1.18.0] - 2026-11-16

### Added
- **Dashboard, My Requests block:** *Resolved* and *Closed* tiles next to *Open* and *On Hold*. They are counted by ServiceDesk Plus (one-row searches with a total count), so they do not slow the Dashboard down. Each tile lists incidents first, then service requests.

### Changed
- **Open** colour is now #17426f (cards, tiles, badges, buttons).
- **All portal pages use the Dashboard design:** My Requests, My Approvals, My Loans and Announcements have a filter panel and a table panel with coloured status, type and decision badges; request, approval, loan and announcement pages show their fields in panels, the conversation as a timeline, and coloured Approve / Reject buttons; the request form is a panel too.
- **Filters apply at once:** there is no Search button any more. Choosing a status, type, show or sort option applies it; the search text is applied with Enter (or when cleared).

## [1.17.0] - 2026-11-16

### Changed
- **Dashboard redesign:** coloured summary cards (blue open, amber on hold, purple waiting approval, red/amber/green loans to return, teal announcements) that lift on hover; *Announcements* and *Recent Requests* in panels with a *View all* link and coloured status and type badges; *My Requests* as two tiles (Open, On Hold) with the service and incident split; *My Approvals* and *My Loans* as lists with coloured counts (green for approved and nothing overdue, red for overdue). The Dashboard has its own styles, so it looks the same on any site template.
- **My Requests filter** is labelled *Request type*, with the options *All types*, *Incident* and *Service*.

## [1.16.0] - 2026-11-16

### Added
- **My Requests type filter:** *Type* (all types, incident, service request) between *Search* and *Status*.
- **Loan details:** purchase date, warranty expiry date, department and the user the asset is loaned to.

### Changed
- **Repository renamed** to `aboarken/servicedeskplus`: the update server, changelog and links point to the new address.
- **Dashboard is faster:** only open and on-hold requests are read from ServiceDesk Plus (up to 500 of each, one search per status); resolved, closed and canceled requests are no longer counted.
- **Dashboard layout:** the *On Hold requests* card replaces *Overdue requests*; *Recent Requests* (open and on-hold only) sits under *Announcements* with the same heading size; *My Requests*, *My Approvals* and *My Loans* sit side by side in three columns (stacked on small screens), with the same tables as before.
- **Any site template:** the pages no longer depend on Cassiopeia's styles. Zero-specificity fallbacks in `portal.css` cover templates without Bootstrap, and the pagination and conversation layouts can be overridden in the template (`html/layouts/com_servicedeskplus`).

## [1.15.0] - 2026-11-15

### Added
- **Joomla changelog:** the version number in *System → Manage → Extensions* and the update view now show what changed. `changelog.xml` is built from this file for every release (`<changelogurl>`, as described in the Joomla manual).

### Changed
- **Cache time is set in minutes** (*Integration Settings → Limits → Cache time (minutes)*, 0–60, default 5). An existing value in seconds is converted, rounded up, until the settings are saved. With 0 every page reads ServiceDesk Plus again, which makes the Dashboard slow; 5 minutes is recommended.
- **Close** button on Integration Settings and User Mappings turns Joomla's red on hover.
- **Dashboard wording:** headings *My Requests*, *Recent Requests*, *My Approvals*, *My Loans*; cards *Waiting approval*, *Loans to return* and *Announcements*.

### Removed
- Unused code left from earlier versions (per-user key context, an unused asset helper and component getter, the announcement excerpt) and a reference to a missing document.

## [1.14.1] - 2026-11-15

### Changed
- **Dashboard** has the 1.11 design again: summary cards (open requests, overdue requests, waiting for my approval, loans to return within 30 days, current announcements), then the five newest announcements, *My requests* with service requests and incident requests by status and the five most recent requests, *My approvals* by type with approved and rejected by me, and *My loans*. Every figure links to its page with the matching filter. Request figures are counted from the user's 1000 most recent requests and include a **Canceled** row.

## [1.14.0] - 2026-11-15

### Added
- **Dashboard** (new menu item type): figures for the logged-in user, each opening its page with the matching filter. *Requests:* all and one per status of the My Requests filter (counted by ServiceDesk Plus, one call per status). *Approvals:* waiting for my approval, approved by me, rejected by me (decisions made through the portal). *Loans:* all, to return within 30 days (including overdue), overdue. *Announcements:* current announcements. Each section only for users with that page's permission; a section that cannot be read shows its error while the others still show. The Dashboard needs any one of the portal permissions.

### Changed
- **My Approvals** and **My Loans** work like My Requests: one filter bar (search, *Show*, type for approvals, sort, Search button), striped table with the ID first and ID and subject/name as links, and pages of 25.
  - My Approvals *Show*: waiting for my approval, approved by me, rejected by me, all my decisions (replaces the separate "My past decisions" table). Up to the user's 200 latest decisions are listed.
  - My Loans *Show*: all my loans, to return within 30 days, overdue. Sort by due date or name.
- Announcements use the same list code (no visible change).

### Removed
- The links between portal pages added in 1.13.0. The file is removed on update.

## [1.13.0] - 2026-11-15

### Added
- **Approvals on the request page:** when a request has approvals, its page shows them by level: approver, status, sent for approval, decided on and comments, plus the request's approval status in the details. Requests without approvals show no approvals section.
- **Links between portal pages:** My Requests, New Request, My Approvals, My Loans and Announcements link to each other at the top of each page. Only pages the user may open are shown; a link uses its menu item's title when there is one.
- **Close** button on the backend pages *Integration Settings* and *User Mappings* (back to the administrator dashboard).

### Changed
- **Announcements** are shown as a table like My Requests: ID, subject (both link to the announcement), type, priority, posted by, start date and end date, with search, type filter, sorting and pages. The announcement page shows its ID and who posted it.
- Joomla review: database queries use `createQuery()` (`getQuery(true)` is deprecated since Joomla 5); every site page loads the portal stylesheet; the manifest has an author URL.

## [1.12.1] - 2026-11-15

### Fixed
- **Internal announcements were never shown.** Announcements created for the team (not public) are now shown to **technicians** — users whose ServiceDesk Plus record has *is_technician* — together with every other announcement in its display period. Requesters still only see public announcements (and group ones for their groups).
- Each load of Announcements writes one line to `administrator/logs/com_servicedeskplus.php`: how many announcements were read and shown, and how many were hidden as outside the display period, internal, or for other user groups.

### Changed
- Messages now say **ServiceDesk Plus** instead of "the service desk", e.g. "The ServiceDesk Plus did not allow this action."

## [1.12.0] - 2026-11-15

### Added
- **Announcements** (new menu item type): ServiceDesk Plus announcements, newest first, with date, type, priority and a short preview; each opens on its own page. Shown only to users with the new **View announcements** permission (`sdp.announcement.view`, not granted by default). Only announcements ServiceDesk Plus shows to requesters are listed: public, inside their display period, and — when limited to user groups — only for members of those groups. Technician-only announcements never appear. The newest 100 announcements are read.

### Removed
- **My Dashboard.** Its files are removed on update, and existing *My Dashboard* menu items are moved to the trash.

## [1.11.0] - 2026-11-15

### Added
- **My Dashboard** (new menu item type): open requests, overdue requests, approvals waiting for the user and overdue loans at a glance; the user's **service requests and incident requests by status** (Open, On Hold, Resolved, Closed, other) with totals; the five most recent requests; waiting approvals by type (the modules ticked in Integration Settings); total and overdue loans. Built from the lists the portal already reads (up to the user's 1000 most recent requests); each part follows the permission of its own page.
- **Conversation on request pages:** the e-mails and notes of a request, oldest first, on the request page and on the approval page of a request. Only items ServiceDesk Plus marks as visible to the requester are shown; technicians' internal notes are never read. The newest 20 items are read.

### Changed
- **Backend addresses:** `view=settings` is now `view=integrationsettings` and `view=usermaps` is now `view=usermappings`. Old links and bookmarks are redirected; files of the old views are removed on update.

## [1.10.0] - 2026-11-14

### Added
- **"My past decisions" shows what each decision was about:** new *Type* and *Subject* columns. The subject and module are stored with each decision (two new columns in `#__sdp_audit`, added by the 1.10.0 database update). Decisions made before this version show "—" as subject.

### Fixed
- **The same approval listed twice.** When an approver is added twice to the same level, ServiceDesk Plus keeps two pending entries. My Approvals now shows one row per record and level, and a decision through the API technician removes all of that approver's pending entries on the level.

### Changed
- The type column in My Approvals is back to **Type** with plain text, as in 1.9.0 (1.9.1's "Approval For" heading and coloured labels are removed; the labels did not suit the site template).
- **Approval comment format** in ServiceDesk Plus: `Rejected by (username) Full Name via the Joomla portal. Custom comment: <the user's comment>`. The user's Joomla username is shown in brackets before their name; "Custom comment:" is added only when the user wrote a comment. The private request note uses the same name.

## [1.9.1] - 2026-11-14

### Fixed
- **Changes, releases and purchase orders were not listed in My Approvals.** On build 16030, `GET /api/v3/approvals` only returns the API technician's own approvals and ignores the approver filter. My Approvals now scans each module like requests: requests and changes with approval status *Pending Approval* (up to 50 and 25), purchase orders with status *Pending Approval* (25), and the 10 newest releases.
- **Approvals of closed records.** ServiceDesk Plus keeps a pending approval on a closed request, but lets nobody change its approvers, so the portal could not record a decision and showed a generic error. Closed and cancelled records are no longer listed, and if it happens anyway the message says the approval cannot be recorded from this site.

- **A rejection could end up as an approval.** ServiceDesk Plus's "Auto-approve requests if already approved by the same approver in the previous stages" (and "… when the requester is the approver") approved the API technician's added entry the moment its approval e-mail was sent, completing the level before the portal's rejection arrived ("Approval(s) doesn't belong to current level"). The portal now re-reads the added entry before deciding: if ServiceDesk Plus already decided it the other way, it does not touch the approver's own entry, removes the added one when ServiceDesk Plus allows it, logs an error for the administrator and tells the user the decision was not recorded. **Turn both "Auto-approve" options off** under Admin → Advanced Portal Settings → Approval.

### Added
- **Integration Settings → Approvals → Show approvals for:** choose which modules My Approvals checks (requests, changes, releases, purchase orders; all by default). Each module adds ServiceDesk Plus calls when the list is built.

### Changed
- My Approvals: the type column is now **Approval For** and shows the type as a coloured label.
- "ServiceDesk Plus did not allow this decision. Please contact IT." now ends with "Please contact the System Administrator."

## [1.9.0] - 2026-11-14

### Added
- **My Approvals covers changes, releases and purchase orders** as well as requests. A new *Type* column shows the module; the approval page shows the change, release or purchase order with its title, description and requester. Approve and reject work for all four, including the API technician workaround (adding the API technician, sending the approval e-mail, recording the decision, removing the approver's pending entry). Problems are not included: ServiceDesk Plus has no approvals for problems.
  - Calls follow ManageEngine's ServiceDesk Plus On-Premises Postman collection: `approve`/`reject` for requests and changes, `_approve`/`_reject` for releases and purchase orders; adding approvers with `{"approvals": [...]}` for changes, releases and purchase orders; `send_notification?ids=` (requests, changes) and `_send_notification` (releases, purchase orders). The other form is tried when ServiceDesk Plus answers "not found".
  - Private notes naming the approver are added to requests, changes and releases. Purchase orders have no notes in the API; the approval comment names the approver.

### Changed
- **Faster My Approvals.** The list now comes from one call, `GET /api/v3/approvals`, filtered by the user's approver id and the pending status, instead of up to 101 calls scanning recent requests. Every returned item is checked to belong to the user. If the build does not support this call, ignores the filter, or returns nothing, the previous request scan runs, so request approvals keep working as before.

## [1.8.2] - 2026-11-14

### Fixed
- **Sending the approval e-mail on ServiceDesk Plus On-Premises.** 1.8.1 used the Cloud addresses (`…/approvals/{id}/_send_notification`), which On-Premises answers with "Invalid URL". The portal now uses the On-Premises form first: `PUT …/approval_levels/{level}/approvals/send_notification?ids={approval_id}` with `{"approval": {"notification": {"title", "description"}}}`, as documented in the On-Premises v3 reference for change approvals. The Cloud forms remain as fallbacks.

## [1.8.1] - 2026-11-14

### Fixed
- **Approving through the API technician failed with "Recommendation mail is not yet sent."** ServiceDesk Plus accepts no decision on an approval until its approval e-mail has been sent. After adding the API technician as approver, the portal now sends that e-mail (to the API technician's mailbox) before recording the decision. Both the `send_notification` and the documented `_send_notification` paths are supported.

## [1.8.0] - 2026-11-14

### Added
- **Approve and reject from Joomla, even though ServiceDesk Plus only accepts the assigned approver.** ServiceDesk Plus refuses decisions sent with the API technician's key for approvals assigned to someone else (code 4201). With the new **Integration Settings → Approvals → API technician e-mail** set, the portal then:
  1. checks, as before, that the logged-in user is the assigned approver and the approval is pending;
  2. adds the API technician as approver on the same approval level (or reuses its pending entry);
  3. approves or rejects as the API technician, with "Approved by *name* via the Joomla portal." and the user's comment as approval comment;
  4. removes the user's own pending entry, so the level shows one decision;
  5. adds a private request note (not shown to the requester) naming the person who decided.
  If the decision fails, the added entry is removed again. When the level also needs other approvers, or the user's entry cannot be removed, the user is told so. Leave the e-mail empty to keep the previous behaviour.
- The portal still tries the direct decision first, so nothing changes where ServiceDesk Plus already accepts it.

### Changed
- Approval comments are sent as one line of at most 250 characters, as ServiceDesk Plus requires; the comment box allows 200 characters.

## [1.7.1] - 2026-11-14

### Fixed
- **Approvals refused by ServiceDesk Plus** (SDP code 4201, "You are not authorized to take action on this approval") now show "ServiceDesk Plus did not allow this decision. Please contact IT." instead of the generic "did not accept the data" message.
- **Short connection drops:** a read from ServiceDesk Plus that fails to connect (for example a dropped TLS handshake) is tried once more after 0.3 seconds. Creating requests and approving or rejecting are never repeated.

### Changed
- The message "The service desk did not accept the data that was sent." now reads "ServiceDesk Plus did not accept the data that was sent."
- Connection failures in `administrator/logs/com_servicedeskplus.php` now include the cURL error text (for example "SSL certificate problem: unable to get local issuer certificate"), so TLS problems can be diagnosed.

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
