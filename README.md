# Joomla ServiceDesk Plus

**Bring ManageEngine ServiceDesk Plus directly into your Joomla website.**

`com_servicedeskplus` is a Joomla component designed to integrate [ManageEngine ServiceDesk Plus](https://www.manageengine.com/products/service-desk/) with Joomla, providing users with a centralized IT Service Management (ITSM) portal through their existing Joomla accounts.

The component connects to the ServiceDesk Plus REST API, allowing users to access IT services, manage support requests, review approvals, and view assigned assets without needing to access the ServiceDesk Plus interface directly.

## Overview

The goal of this project is to provide a seamless self-service experience for employees and end users while keeping ManageEngine ServiceDesk Plus as the central system for IT service management.

By integrating Joomla with ServiceDesk Plus, organizations can offer a unified portal for IT support, service requests, asset visibility, and other IT-related services.

## Features

### Dashboard
- **Summary cards:** open requests, on-hold requests, waiting for my approval, loans to return within 30 days, current announcements.
- **Announcements:** the five newest announcements with start and end date, right under the cards.
- **Recent Requests:** the five newest open and on-hold requests, under the announcements.
- **My Requests block:** Open, On Hold, Resolved and Closed tiles, each with its incident and service request count. Resolved and closed requests are counted by ServiceDesk Plus without reading them.
- **My Requests, My Approvals, My Loans** side by side in three columns (stacked on small screens):
  - *My Requests:* open and on-hold service requests and incidents with totals. Only open and on-hold requests are read from ServiceDesk Plus (up to 500 of each), so the Dashboard stays fast for users with many closed requests.
  - *My Approvals:* waiting approvals by type, approved by me and rejected by me (decisions made through the portal).
  - *My Loans:* all loans, to return within 30 days (including overdue), overdue.
- Coloured cards, panels and counts (green for good, amber and red for what needs attention), styled by the component so the Dashboard looks the same on any site template. All other portal pages use the same design.
- List filters apply as soon as an option is chosen; the search is applied with Enter (no Search button).
- Every figure opens its page with the matching filter. Each part is shown only to users with the permission for its page.

### Service Requests
- View and track submitted service requests (**My Requests**), filtered to **Open** requests by default, with search, *Request type* filter (incident or service), status filter and sorting.
- Submit new service requests with the **Request Form** menu item (page title **New Request**).
- Create requests using only a subject and description, with optional attachments.
- View request details: status, priority, urgency, technician, technician group, category, subcategory, item, dates, approval status, description, resolution, the **approvals** of the request when it has any (level, approver, status, sent and decision dates, comments) and the **conversation** (e-mails and notes visible to the requester).

### Attachments
- Allowed types: JPG, PNG, PDF, Excel (.xlsx) and Word (.docx); the real file content is checked, not just the extension.
- At most **3 files** per request, **1 MB per file** (3 MB in total).
- The limits are enforced on the server; the form only checks early for convenience.

### IT Support
- Access the user's support requests.
- View request progress and updates.
- Review available request actions.
- Access relevant IT service information.

### Approvals
- View pending approvals for **requests, changes, releases and purchase orders** in one list (ServiceDesk Plus has no approvals for problems), and the decisions made through the portal. The list works like My Requests: search, *Show* (waiting for my approval, approved by me, rejected by me, all my decisions), type, sort and pages.
- Review approval details.
- Approve or reject from Joomla, for example while away from the company network.

ServiceDesk Plus only accepts an approval decision from the assigned approver, while the portal talks to ServiceDesk Plus with a single API technician key. To let approvers decide from Joomla anyway, enter the API technician's e-mail under **Integration Settings → Approvals → API technician e-mail**. When ServiceDesk Plus refuses a decision, the portal adds the API technician as approver on the same level, records the decision under that account with the real approver's name in the comment and in a private request note, and removes the approver's pending entry. In ServiceDesk Plus the decision therefore appears under the API technician. Approval levels that need all approvers still wait for the others. The API technician's role needs permission to edit and manage approvals in each module used (requests, changes, releases, purchase orders). Turn off both "Auto-approve…" options under ServiceDesk Plus **Admin → Advanced Portal Settings → Approval**: otherwise ServiceDesk Plus can approve the API technician's added entry by itself, even when the user rejected. Closed records cannot be decided from the portal, and purchase requests have no API.

### Announcements
- The **Announcements** menu item lists ServiceDesk Plus announcements in a table like My Requests: ID, subject, type, priority, start date and end date (the announcement's page also shows who posted it). Click the ID or subject to open an announcement. Search (ID, subject or text), filter by type, sort (newest, oldest, ending soonest, subject) and pages of 25.
- Users need the **View announcements** permission (*System → Global Configuration → ServiceDesk Plus → Permissions*, or the component's *Options*). It is not granted by default.
- Only announcements inside their display period (from / to date) appear.
- **Technicians** (users whose ServiceDesk Plus record is a technician) see all of them, including internal announcements for the team.
- **Requesters** see public announcements; announcements limited to user groups only when their ServiceDesk Plus record lists one of those groups. Internal (technician-only) announcements are hidden from requesters.
- Each time the list is loaded, a line in `administrator/logs/com_servicedeskplus.php` tells how many announcements were read, shown and hidden (and why).

### Assets & Equipment
- View assets associated with the user.
- Access assigned equipment information.
- View equipment and loan records in read-only mode.
- *My Loans* works like My Requests: search, *Show* (all, to return within 30 days, overdue), sort and pages.
- A loan's page shows type, product, serial number, state, purchase date, warranty expiry date, department, the user it is loaned to, loan start and due date.

### Site templates
The portal pages follow the installed site template; they are not tied to Cassiopeia. They use Bootstrap 5 class names, which most Joomla templates style; `media/com_servicedeskplus/css/portal.css` adds zero-specificity fallbacks for templates without Bootstrap, so the template's own styles always win. Page layouts (`tmpl`) and the shared layouts (`layouts/pagination.php`, `layouts/conversation.php`) can be overridden in the template (*System → Site Templates → Create Overrides*; layouts under `html/layouts/com_servicedeskplus`).

**Note:** Equipment and loan information is provided for viewing only. Submitting loan requests, requesting loan extensions, and requesting equipment returns are not part of this component.

## Integration

The component integrates directly with the ManageEngine ServiceDesk Plus REST API.

**Integration workflow:**

1. A user signs in to the Joomla website.
2. The component identifies the corresponding ServiceDesk Plus requester using the user's email address.
3. The component communicates with ServiceDesk Plus through its REST API.
4. Relevant information is retrieved and displayed in the Joomla portal.
5. User-submitted requests and supported actions are processed through ServiceDesk Plus.

Joomla acts as the user-facing portal, while ServiceDesk Plus remains the primary system responsible for IT service management and request processing.

## Requirements

| Requirement | Version / Details |
|---|---|
| Joomla | 6.2 |
| PHP | Compatible with the installed Joomla 6.2 requirements |
| ManageEngine ServiceDesk Plus | REST API access |
| ServiceDesk Plus deployment | On-Premises |
| Database | Supported Joomla database engine |

## Installation

1. Download `com_servicedeskplus.zip` from the latest release on the [Releases page](https://github.com/aboarken/joomla-servicedeskplus/releases).
2. Log in to your Joomla Administrator.
3. Navigate to **System → Install → Extensions**.
4. Upload the component installation package.
5. Complete the installation.
6. Open the ServiceDesk Plus component from the Joomla Administrator.

### Updating

From version 1.7.0, Joomla finds new versions by itself: open **System → Update → Extensions** (or **System → Update Sites** to check that "ServiceDesk Plus" is enabled), then select the component and choose **Update**. Settings and user mappings are kept.

Sites on 1.6.0 or older have no update server yet: install 1.7.0 once by uploading `com_servicedeskplus.zip`, and later updates arrive through Joomla.

You can always upgrade by installing a newer package over the existing one.

### Menu items

Create front-end pages with **Menus → Add New Menu Item → ServiceDesk Plus**: *Dashboard*, *My Requests*, *Request Form*, *My Loans*, *My Approvals* and *Announcements*. The menu item title is shown as the page heading and browser title; the menu item's *Page Display* settings (*Browser Page Title*, *Show Page Heading*, *Page Heading*) are respected.

## Configuration

After installation, configure the integration using the component's administration interface.

The configuration includes:

- ServiceDesk Plus server URL.
- REST API authentication credentials.
- API connectivity settings.
- Joomla user-to-ServiceDesk Plus requester mapping.
- Component access permissions.

### Cache

*Integration Settings → Limits → Cache time (minutes)* sets how long each user's ServiceDesk Plus results (requests, approvals, loans, announcements and the Dashboard figures) are kept, 0 to 60 minutes; default 5. A user's own new requests and approval decisions refresh their lists at once. With 0, every page reads ServiceDesk Plus again; the Dashboard then makes many calls (approvals are found by scanning requests, changes, releases and purchase orders) and loads slowly.

### User Mapping

The component uses email addresses to associate Joomla users with their corresponding ServiceDesk Plus requesters.

For successful integration, the Joomla user's email address must match the email address registered in ServiceDesk Plus.

### API Connectivity

The Joomla server must be able to communicate with the ServiceDesk Plus server through the REST API.

For on-premises deployments, ensure that the required network connectivity, firewall rules, and API permissions are configured.

## Notifications

ServiceDesk Plus remains responsible for request-related notifications.

The component does not generate separate Joomla email notifications for submitted requests. Notification management remains centralized within ManageEngine ServiceDesk Plus.

## Security & Permissions

The component is designed to work with Joomla's access control system (ACL).

Administrators can manage access permissions for the component and its available functionality.

API credentials should be stored securely and should never be exposed to frontend users or committed to the source repository.

## Project Structure

The repository follows the Joomla component architecture.

```text
com_servicedeskplus/
├── administrator/
│   ├── forms/
│   ├── services/
│   ├── sql/
│   ├── src/
│   ├── tmpl/
│   ├── access.xml
│   └── config.xml
├── media/
│   ├── css/
│   └── js/
├── site/
│   ├── language/
│   ├── src/
│   └── tmpl/
├── .github/workflows/
│   └── release.yml
├── build/
│   ├── build-package.sh
│   ├── make-update-xml.py
│   └── verify-package.py
├── CHANGELOG.md
├── LICENSE
├── README.md
├── script.php
└── servicedeskplus.xml
```

## Development

This project is developed as a Joomla component using Joomla's component architecture and PHP.

Contributions, suggestions, bug reports, and feature requests are welcome.

To contribute:

1. Fork the repository.
2. Create a feature branch.
3. Implement your changes.
4. Test your changes in a Joomla development environment.
5. Submit a pull request.

### Building the installation package

```bash
build/build-package.sh
```

This writes `dist/com_servicedeskplus-<version>.zip`, taking the version from `servicedeskplus.xml`. Check a package with `build/verify-package.py <zip> [version]`.

### Releases

Versions follow [Semantic Versioning](https://semver.org/) and are set in `servicedeskplus.xml`. Each version has an update file in `administrator/sql/updates/mysql/` and `administrator/sql/updates/postgresql/` (a comment-only file when the schema does not change), release notes in `docs/release-notes/<version>.md` and an entry in [CHANGELOG.md](CHANGELOG.md).

The **Release package** GitHub Actions workflow (`.github/workflows/release.yml`) builds and checks the package for every pull request and attaches **`com_servicedeskplus.zip`** and the Joomla update file **`updates.xml`** (made by `build/make-update-xml.py`) to each release. Joomla sites read `releases/latest/download/updates.xml`, so publishing a release is all it takes to offer an update:

- **From the Actions tab:** run *Release package* on `main`. It creates the release `v<version>` with the release notes and the package.
- **From the Releases page:** publish a release tagged `v<version>` (it must match the manifest version); the workflow then attaches the package.

The latest package can always be downloaded from `https://github.com/aboarken/joomla-servicedeskplus/releases/latest/download/com_servicedeskplus.zip`.

## Roadmap

The project aims to provide an increasingly complete self-service portal for ManageEngine ServiceDesk Plus.

Future improvements may include:

- Expanded request management capabilities.
- Improved dashboard and reporting experience.
- Enhanced approval workflows.
- Additional asset management capabilities.
- Improved API integration and error handling.
- Continued compatibility with future Joomla releases.

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0). See the [LICENSE](LICENSE) file for details.

## Links

- **GitHub Repository:** https://github.com/aboarken/joomla-servicedeskplus
- **ManageEngine ServiceDesk Plus:** https://www.manageengine.com/products/service-desk/
- **Joomla:** https://www.joomla.org/
- **ServiceDesk Plus API Documentation:** https://www.manageengine.com/products/service-desk/sdpod-v3-api/

---

**Developed for organizations that want to make IT services more accessible through Joomla and ManageEngine ServiceDesk Plus.**
