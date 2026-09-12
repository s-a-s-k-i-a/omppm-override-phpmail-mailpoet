# Native MailPoet / SMTP E2E

Run `./scripts/test-e2e.sh` with an available local disposable MySQL server. The script needs PHP (mysqli, mbstring, intl, curl, zip), Python 3, curl, unzip, OpenSSL and the MySQL command-line client. It downloads WordPress 7.1, MailPoet 5.38.0 and WP Mail SMTP 4.9.0. It creates and later drops its own uniquely named `omppm_e2e_*` database. It does not accept a WordPress installation path or import any existing data.

Set `OMPPM_E2E_DB_HOST`, `OMPPM_E2E_DB_USER` and `OMPPM_E2E_DB_PASS` for that **disposable local database server**; defaults are `127.0.0.1`, `root`, `root`. For a Unix socket, the host can be `localhost:/path/to/socket`. Never point this harness at a production database server. Ports 25281–25284 must be unused. The first three are exclusively bound to loopback; 25284 deliberately has no listener to test connection refusal.

## What runs

`queue.php` persists actual MailPoet Newsletter, Segment, Subscriber, SubscriberSegment, NewsletterSegment, ScheduledTask, SendingQueue and ScheduledTaskSubscriber entities. Named recipients use the reserved `.test` namespace. A real SMTP plugin handles `wp_mail()`; `smtp.py` accepts SMTP DATA locally without relay or message-body persistence and records synthetic envelope/response events.

- `cold`: public `SendingQueue::process()` selects the running task, renders MailPoet's real SimpleText template from an initially empty rendered-body cache, applies recipient personalization, processes the synthetic segment membership and finishes all three deliveries.
- `full`: public `process()` handles success → permanent 550/5.1.1 → success and completes the task with three processed rows, one explicitly failed row and exactly two SMTP acceptances. The cached template in this fault case isolates the transport fault from rendering.
- `permanent`: focused `processQueue()` runs the same recipient fault.
- `temporary`, `auth`, `connect`, `timeout`, `policy`: genuine SMTP 450, authentication 535, connection refusal, missing greeting timeout, and policy 550/5.7.1 respectively preserve unprocessed recipients and invoke native MailPoet retry. A **new WP-CLI/PHP process** resumes the persisted unprocessed rows after synthetic fault recovery, and the catcher asserts exactly one acceptance per healthy recipient. The harness explicitly resets MailerLog between controlled attempts to avoid sleeping for the production retry delay; it does not claim wall-clock scheduler timing coverage.
- `dsn`: accepted host-transport messages remain subscribed and the real MailPoet Bounce worker refuses its MailPoet Sending Service report path. There is no general inbound DSN processor in this plugin. This checks that boundary, not an invented inbound-mail integration. MailPoet documents manual bounce handling when using other sending methods: https://kb.mailpoet.com/article/180-bounce-management-in-mailpoet-3 .

MailPoet's processed/attempt counters are not delivery counters. In the permanent-recipient case the rejected task row is processed **and failed**, is not accepted by the sink, and the subscriber's global status remains subscribed. The test asserts each of those distinctions.

The final steps run the real-class recipient contract regression and a real WordPress password reset. A separate transactional test explicitly installs MailPoet’s real WordPressMailer through its native replacer, proves two wp_mail calls and exactly one SMTP acceptance. Only that PHP process enables mail(), pointing sendmail_path at the fixed sendmail.py shim: the shim rejects any non-.test envelope and forwards solely to the loopback sink. This exercises the native parent fallback without enabling external sendmail. Browser/admin debug controls, genuine MailPoet Settings test-email UI have separate smoke receipts.

## Isolation and evidence

An MU plugin blocks all WordPress HTTP requests and verifies the final PHPMailer transport uses only the local synthetic sink and `.test` recipients. PHP `mail()` and sendmail fallback are disabled before the first boot; cron and updates are disabled. No MailPoet service/API credentials are configured. The SMTP server has no relay implementation. These are safeguards for the exercised runtime paths, not an OS-wide firewall against arbitrary untrusted socket code.

All errors are fail-closed test failures; no `continue-on-error` wrapper is needed. The script prints its scratch artifact directory. It leaves synthetic fixture/SMTP receipts there, stops its own SMTP child and drops its own database on exit. CI should use a dedicated MySQL service and a 15-minute timeout. Browser smoke uses a separately controlled disposable runtime so this script cannot interfere with it.
