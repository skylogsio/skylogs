# Security Policy

## Supported Versions

We provide security fixes for the latest minor release of Skylogs.
Older versions should upgrade to the current release.

| Version | Supported |
| ------- | --------- |
| latest release | ✅ |
| older releases | ❌ |

## Reporting a Vulnerability

**Please do not open public GitHub issues for security vulnerabilities.**

Report vulnerabilities privately via one of:

- GitHub's private vulnerability reporting: use the **"Report a
  vulnerability"** button under the repository's *Security* tab
- Email: **security@skylogs.io**

Please include a description of the issue, steps to reproduce, the affected
component and version, and any suggested remediation. We aim to acknowledge
reports within 72 hours and will keep you informed of progress. We're happy
to credit reporters in the release notes unless you prefer to stay anonymous.

## Scope notes

Skylogs handles alerting and on-call data. Issues we consider especially
critical: authentication/authorization bypass, notification-channel token
exposure, SSRF via integration webhooks, and injection in alert payload
processing.
