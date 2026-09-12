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

Playground uses pinned Node 22.16.0 and CLI 3.1.46 through npm. Its `runPHP`
step must write a fresh completion receipt from inside WordPress; exit 0
alone never passes. The controls script also forces a deliberate PHP
assertion failure and requires both a nonzero exit and its exact error marker.
This is an alias smoke, not a newsletter or database-compatibility test.
The native MySQL E2E job exercises the real MailPoet worker and SMTP failures.
