# Joomla ServiceDesk Plus

**Bring ManageEngine ServiceDesk Plus directly into your Joomla website.**

`com_servicedeskplus` is a Joomla component designed to integrate [ManageEngine ServiceDesk Plus](https://www.manageengine.com/products/service-desk/) with Joomla, providing users with a centralized IT Service Management (ITSM) portal through their existing Joomla accounts.

The component connects to the ServiceDesk Plus REST API, allowing users to access IT services, manage support requests, review approvals, and view assigned assets without needing to access the ServiceDesk Plus interface directly.

## Overview

The goal of this project is to provide a seamless self-service experience for employees and end users while keeping ManageEngine ServiceDesk Plus as the central system for IT service management.

By integrating Joomla with ServiceDesk Plus, organizations can offer a unified portal for IT support, service requests, asset visibility, and other IT-related services.

## Features

### Service Requests
- View and track submitted service requests (**My Requests**), filtered to **Open** requests by default, with search, status filter and sorting.
- Submit new service requests with the **Request Form** menu item (page title **New Request**).
- Create requests using only a subject and description, with optional attachments.
- View request details: status, priority, urgency, technician, technician group, category, subcategory, item, dates, description and resolution.

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
- View pending approval requests.
- Review approval details.
- Perform available approval actions.

### Dashboard
- Centralized user dashboard.
- Overview of service requests.
- Request status and activity.
- Relevant IT service information.

### Assets & Equipment
- View assets associated with the user.
- Access assigned equipment information.
- View equipment and loan records in read-only mode.

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

1. Download the latest installation package (`com_servicedeskplus-<version>.zip`) from the [Releases page](https://github.com/aboarken/com_servicedeskplus/releases).
2. Log in to your Joomla Administrator.
3. Navigate to **System → Install → Extensions**.
4. Upload the component installation package.
5. Complete the installation.
6. Open the ServiceDesk Plus component from the Joomla Administrator.

To upgrade, install the newer package over the existing one; settings and user mappings are kept.

## Configuration

After installation, configure the integration using the component's administration interface.

The configuration includes:

- ServiceDesk Plus server URL.
- REST API authentication credentials.
- API connectivity settings.
- Joomla user-to-ServiceDesk Plus requester mapping.
- Component access permissions.

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
├── build/
│   └── build-package.sh
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

This writes `dist/com_servicedeskplus-<version>.zip`, taking the version from `servicedeskplus.xml`.

### Releases

Versions follow [Semantic Versioning](https://semver.org/) and are set in `servicedeskplus.xml`. Each version has an update file in `administrator/sql/updates/mysql/` and `administrator/sql/updates/postgresql/` (a comment-only file when the schema does not change). Releases are tagged `v<version>` and the installation ZIP is attached to the GitHub release. See [CHANGELOG.md](CHANGELOG.md) for the changes in each version.

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

- **GitHub Repository:** https://github.com/aboarken/com_servicedeskplus
- **ManageEngine ServiceDesk Plus:** https://www.manageengine.com/products/service-desk/
- **Joomla:** https://www.joomla.org/
- **ServiceDesk Plus API Documentation:** https://www.manageengine.com/products/service-desk/sdpod-v3-api/

---

**Developed for organizations that want to make IT services more accessible through Joomla and ManageEngine ServiceDesk Plus.**
