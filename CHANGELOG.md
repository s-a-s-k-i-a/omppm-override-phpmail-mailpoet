# Changelog

The user-facing changelog lives in `readme.txt` (WordPress.org format); this file condenses it for GitHub readers. Entries before 2026-09-12 predate this repository and were imported from the WordPress.org SVN history.

## 1.2.5 — 2026-09-12

- Minimum PHP requirement corrected to 8.1 (issue #1): the enum introduced in 1.2.0 is PHP 8.1+ syntax, so 1.2.0–1.2.4 could never load on PHP 8.0 (parse error). Dead 8.0 fallback branches removed.
- The GPL license file now ships with the plugin.
- First release built and deployed through the GitHub release pipeline.

## 1.2.4 — 2026-01-21

- Tested up to WordPress 6.9.

## 1.2.3 — 2026-01-21

- Critical fix for infinite send loops: a static recursion guard prevents `Maximum execution time exceeded` errors when WordPress system mails (e.g. password reset) re-enter the mailer while MailPoet is active.

## 1.2.2 — 2025-08-21

- Dynamic MailPoet email-type discovery via reflection over `NewsletterEntity` `TYPE_*` constants (cached), so new MailPoet types are supported automatically.

## 1.2.1 — 2025-08-20

- Extended email-type support with pattern matching: `automatic_*`, `automatic_woocommerce_*`, preview, email-stats and new-subscriber notifications.

## 1.2.0 — 2025-08-19

- PHP 8.3 compatibility pass with version-guarded fallbacks.

## 1.1.0 — 2025-08-11

- Translation normalization: en_US is the default locale.

## 1.0.11 – 1.0.15 — 2025-08-08

- Admin interface under Tools: debug toggle (`OMPPM_DEBUG` decoupled from `WP_DEBUG`), log management, plugin/alias status, test-email button, setup guide.

## 1.0.5 – 1.0.10 — 2025-07 – 2025-08

- Stabilized the class-alias approach (base class `PHPMailerMethod`), fixed AJAX/iframe fatals and a memory exhaustion loop, restored MailPoet 5.12 compatibility.

## 1.0.0 – 1.0.4 — 2025-01 – 2025-04

- Initial release and early email-type extensions (post notifications, welcome emails, automatic emails).
