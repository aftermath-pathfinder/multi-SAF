# Security Policy

## Supported Versions

This package has not yet had a tagged `1.0.0` release. Until then, only
the `main` branch (and the latest pushed feature branch it's built from)
receives security fixes.

| Version | Supported |
| --- | --- |
| main / unreleased | ✅ |

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities
(e.g. SQL injection in the outbox store, deserialization issues in a
transport driver, credential leakage in logs).

Instead, report it privately via GitHub's "Report a vulnerability" flow
under the repository's Security tab, or open a private security advisory.
Include:

- The version/commit you tested against.
- A minimal reproduction, if possible.
- The impact you believe it has (data exposure, message duplication/loss,
  privilege escalation, etc.).

We aim to acknowledge reports within a few business days.
