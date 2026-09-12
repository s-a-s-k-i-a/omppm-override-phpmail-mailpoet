# Architecture

## What the plugin does

MailPoet sends its emails through internal "method" classes. When the sending
method is set to *Host / Web Server (PHP mail)*, MailPoet instantiates
`\MailPoet\Mailer\Methods\PHPMail`. This plugin replaces that class so every
such send goes through `wp_mail()` instead — which means the site's SMTP
plugin (WP Mail SMTP, FluentSMTP, Post SMTP, …) handles transport and logging.

## The class-alias mechanism

`omppm-override-phpmail-mailpoet.php` hooks `plugins_loaded` at priority 1 and
calls `class_alias( 'OMPPM\MyPHPMailOverride', '\MailPoet\Mailer\Methods\PHPMail' )`
**before** MailPoet's autoloader has loaded the real class. From then on, any
`new PHPMail(...)` inside MailPoet constructs our override. The override
extends the real `\MailPoet\Mailer\Methods\PHPMailerMethod`, so constructor
signature, blacklist handling, and error mapping stay MailPoet-native.

This is deliberately update-sensitive: it depends on MailPoet's internal class
names and load order. Every MailPoet release needs re-verification (the
Tools page shows "Class Alias Active" as a live indicator).

## Email-type routing

`send()` decides per email whether to reroute:

1. **Dynamic types** — reflection over `MailPoet\Entities\NewsletterEntity`
   `TYPE_*` constants (cached statically), so new MailPoet types are picked up
   automatically.
2. **Static list** — the `EmailType` enum values (newsletter,
   post_notification, transactional, …).
3. **Patterns** — `automatic_*` / `automatic_woocommerce_*` names.

Matching emails go through `wp_mail()` with reconstructed From/Reply-To and
content-type headers. Non-matching emails fall back to the original MailPoet
path via `parent::send()`.

## Recursion guard

`wp_mail()` can itself be routed back into MailPoet by other plugins, which
historically caused infinite loops and `Maximum execution time exceeded`
errors on WordPress system mails (fixed in 1.2.3). A static `$is_sending`
flag makes any re-entrant `send()` call delegate straight to the original
MailPoet method; the flag is always reset in `finally`.

## Admin module

`includes/class-omppm-admin.php` adds a Tools page (capability
`manage_options`, nonce-protected AJAX) with a debug toggle
(`omppm_debug_enabled` option → `OMPPM_DEBUG` constant at load), private bounded diagnostic-event
management, plugin status (MailPoet active, alias active, supported types),
and a `wp_mail()` test-email button.

## Test strategy mapping

- `tests/unit/` loads the real plugin file against MailPoet contract stubs
  (`tests/unit/stubs/mailpoet.php`) and asserts alias, routing, headers,
  blacklist, error mapping, and the recursion guard.
- `tests/playground/assert-alias.php` proves the alias inside a real
  WordPress + MailPoet instance (CI Playground job).

## SMTP failure contract

The original subscriber string remains intact for MailPoet error objects.
`SmtpErrorCapture` observes only the current WordPress send and restores its
hooks and PHPMailer diagnostic settings in `finally`. A correlated single
recipient RCPT rejection with a permanent address/mailbox status becomes a
subscriber-level sending failure: MailPoet records it and continues the batch.
Temporary, authentication, connection, policy and unclassified failures retain
native hard-error retry semantics. No failure is reported as successful.
This synchronous classification does not mark the subscriber globally bounced
or process later DSNs. See `docs/TESTING.md` for exact status codes and limits.

`tests/e2e/` runs the actual MailPoet queue, renderer and retry worker against
an isolated non-relaying SMTP server, including password-reset re-entry. The
Playground runner requires an in-WordPress completion receipt and deliberately
checks that a broken assertion fails.
