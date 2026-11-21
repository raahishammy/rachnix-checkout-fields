# Security policy

Rachnix Checkout Field Editor for WooCommerce runs on the checkout, where customers type their details. We take reports seriously and thank everyone who reports responsibly.

## Supported versions

Only the latest release gets security fixes. Fixes are not backported.

| Version | Supported |
| --- | --- |
| Latest release | Yes |
| Anything older | No, please update |

## Reporting a vulnerability

Please **do not** open a public issue, a pull request, or a WordPress.org support topic for a security problem.

Report it privately through GitHub: on this repository, open **Security > Report a vulnerability** (GitHub's private vulnerability reporting). Only the maintainers can see the report.

Please include:

- the plugin version, and the WordPress, WooCommerce and PHP versions;
- which checkout (classic or Checkout block) and which screen or REST route is affected;
- steps to reproduce, or a proof of concept, against a test site of your own;
- what an attacker could do, and which user role they need (logged out, customer, shop manager, administrator).

Never test against a store you don't own, and never access, change or keep real customer data.

## What happens next

| When | What we do |
| --- | --- |
| Within 72 hours | Confirm we received the report |
| Within 7 days | Tell you whether we can reproduce it and how severe we think it is |
| As fast as possible | Release a fix. For a critical issue we aim for 72 hours after we confirm it |
| After the release | Publish an advisory and credit you, unless you'd rather stay anonymous |

We'd like you to keep the details private until a fixed version is released, or 90 days after your report, whichever comes first.

## Scope

In scope: code in this repository, and the plugin as released on WordPress.org.

Out of scope, so report them elsewhere:

- WordPress core, WooCommerce, themes and other plugins. Report those to their own projects.
- The configuration of a particular server or host, such as missing security headers. See [docs/hosting-security.md](docs/hosting-security.md).
- Issues that need an administrator account to exploit, unless they let that user do something an administrator normally can't.

## How the plugin is checked

Every change runs through PHPCS with the WordPress security sniffs, PHPStan at level 8, the official Plugin Check, Semgrep security rules, `composer audit` and `npm audit`, and tests that call every REST route as a logged-out visitor and as a customer and expect a refusal. Before each major release we run an OWASP ZAP scan of a test store.
