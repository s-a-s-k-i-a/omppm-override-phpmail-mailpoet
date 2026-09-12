# Testing

The test pyramid has four layers:

1. `composer test` verifies the routing contract against MailPoet stubs: class
   alias, email-type matching (static list, reflection-discovered types,
   `automatic_*` patterns), header construction, blacklist rejection, error
   mapping, wp_mail failure handling, and the recursion guard.
2. `composer lint` checks PHP 8.1+ compatibility (PHPCompatibilityWP). Full
   WPCS adoption is issue #2.
3. `./scripts/test-playground.sh` boots a disposable WordPress with the
   current WordPress.org MailPoet build, activates this plugin, and asserts
   that `\MailPoet\Mailer\Methods\PHPMail` resolves to
   `OMPPM\MyPHPMailOverride`.
4. Manual smoke in a real disposable WordPress instance (LocalWP or staging),
   before every release and after every MailPoet update:
   - MailPoet sending method set to *Host / Web Server (PHP mail)*.
   - Tools → SMTP Mail Control: MailPoet active, class alias active.
   - MailPoet **test email** arrives through the configured SMTP plugin
     (verify in the SMTP plugin's log, not just the inbox).
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
