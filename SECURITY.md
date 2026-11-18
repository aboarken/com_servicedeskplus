# Security Policy

## Supported versions

Only the latest release of `com_servicedeskplus` receives security fixes. Update from **System → Update → Extensions**, or install the newest package from the [Releases page](https://github.com/aboarken/joomla-servicedeskplus/releases).

## Reporting a vulnerability

Please do **not** open a public issue for a security problem.

Report it privately through GitHub: open the repository's **Security** tab and choose **Report a vulnerability** ([direct link](https://github.com/aboarken/joomla-servicedeskplus/security/advisories/new)). Include:

- the component version (System → Manage → Extensions) and the Joomla and PHP versions;
- what an attacker can do, and the steps to reproduce it;
- any log lines from `administrator/logs/com_servicedeskplus.php` that help (they hold no keys or passwords).

You can expect a first answer within 7 days. A fix is released as a new version, with the issue described in the changelog under *Security* once the fix is available.

## What the component protects

See *Security & Permissions* in the [README](README.md): one ServiceDesk Plus API key is used for every user, so the component checks on every page and action that the logged-in user only sees and changes their own ServiceDesk Plus records.
