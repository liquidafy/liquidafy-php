# Security Policy

## Reporting a vulnerability

**Never report security vulnerabilities through public GitHub issues, discussions, or pull requests.**

Instead, use one of these private channels:

- E-mail: **security@liquidafy.com**
- GitHub [private vulnerability reporting](https://github.com/liquidafy/liquidafy-php/security/advisories/new) on this repository

Please include as much of the following as you can:

- Type of issue (e.g. signature bypass, key leakage in logs, request smuggling)
- Affected version(s) and file/class
- Step-by-step reproduction or proof-of-concept
- Impact assessment as you see it

## What to expect

- **Acknowledgement within 5 business days.**
- We will keep you informed of progress towards a fix and coordinate the disclosure timeline with you.
- Credit is given in the release notes unless you prefer to stay anonymous.

## Scope

This policy covers the **`liquidafy/liquidafy-php` SDK** — the code in this repository (HTTP client, retry logic, webhook signature verification, key masking, etc.).

Vulnerabilities in the **Liquidafy API or platform** (api.liquidafy.com, app.liquidafy.com, pay pages) are out of scope for this repository — report them to **security@liquidafy.com** as well, and they will be routed to the platform team.

## Supported versions

| Version | Supported |
|---|---|
| 1.x | ✅ |
| < 1.0 | ❌ |

## Handling secrets

- Never commit API keys (`lr_live_*` / `lr_test_*`) or webhook secrets — use environment configuration.
- If you accidentally expose a key, rotate it immediately at [app.liquidafy.com](https://app.liquidafy.com) and notify security@liquidafy.com if it was a live key.
