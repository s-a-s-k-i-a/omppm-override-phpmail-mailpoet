# Testing

The test pyramid has four layers:

1. `composer test` verifies the routing contract against MailPoet stubs: class
   alias, email-type matching (static list, reflection-discovered types,
   `automatic_*` patterns), header construction, blacklist rejection, error
   mapping, wp_mail failure handling, and the recursion guard.
2. `composer lint` checks PHP 8.1+ compatibility (PHPCompatibilityWP). Full
   WPCS adoption is issue #2.
3. `./scripts/test-playground-controls.sh` boots a disposable WordPress with the
   current WordPress.org MailPoet build, activates this plugin, and asserts
   that `\MailPoet\Mailer\Methods\PHPMail` resolves to
   `OMPPM\MyPHPMailOverride`.
4. Manual smoke in a real disposable WordPress instance (LocalWP or staging),
   before every release and after every MailPoet update:
   - MailPoet sending method set to *Host / Web Server (PHP mail)*.
   - Tools → SMTP Mail Control: MailPoet active, class alias active.
   - MailPoet **test email** arrives through the configured SMTP plugin
     (verify SMTP acceptance in the test catcher or provider; some free
     SMTP plugins do not offer delivery logs).
   - One real **newsletter** to a test list arrives through SMTP.
   - A WordPress **system mail** (password reset) completes without timeout
     while MailPoet is active — this exercises the recursion guard.
   - Toggle debug on/off; confirm log entries appear and can be cleared.

For release testing, record WordPress, PHP, MailPoet, SMTP-plugin and browser
versions, the exact emails sent, and where their delivery was verified. Debug
log excerpts in issues must mask recipient addresses (see issue #4).

The Playground blueprint also runs `tests/playground/assert-error-contract.php`
against the installed MailPoet's real mapper and error classes. It intercepts
`wp_mail()` before transport and checks false returns, transport exceptions,
plain and named recipient strings, the error formatter used by the queue's hard
error handler, and subsequent sends on the same instance. This catches the
issue #8 array/string defect; it does not exercise SMTP or run a newsletter
worker. Native hard errors still pause sending. The disposable SMTP and queue
smokes above remain required before release.

Issue #8 error mapping now observes each `wp_mail()` call with temporary
`phpmailer_init`/`wp_mail_failed` hooks. For a single unchanged SMTP envelope
recipient, a structured RCPT rejection with 5xx plus enhanced status 5.1.1,
5.1.2, 5.1.3, 5.1.6 or 5.2.1 becomes MailPoet's subscriber-level (`LEVEL_SOFT`)
sending error. This means the queue can record that failed send and continue;
it does not change the subscriber's global status to bounced. Temporary errors,
missing enhanced status, quota/policy errors, authentication, connection and DATA
failures remain blocking errors. Alternative mail/API plugins without that
structured evidence retain the conservative blocking fallback. WordPress error
details are returned in MailPoet's transient error object; diagnostic events
never contain these messages, recipients or message content.

The observer captures structured SMTP errors before QUIT can clear them and
restores the previous diagnostic callback and levels. The original debug output
continues only at its original level (no output when it was disabled). Regression
coverage includes stale errors on reused objects, nested sends, changed/multiple
recipients, callback preservation, and hook cleanup. An SMTP acceptance followed
by a later DSN is outside this synchronous transport contract.
Playground uses pinned Node 22.16.0 and CLI 3.1.46 through npm. Its `runPHP`
step must write a fresh completion receipt from inside WordPress; exit 0
alone never passes. The controls script also forces a deliberate PHP
assertion failure and requires both a nonzero exit and its exact error marker.
This checks the alias and native error contract, not newsletter or database compatibility.
The native MySQL E2E job exercises the real MailPoet worker and SMTP failures.
