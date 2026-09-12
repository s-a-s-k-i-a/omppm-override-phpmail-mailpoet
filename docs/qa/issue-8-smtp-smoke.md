# Issue #8: isolated native SMTP smoke — 2026-09-12

This is a partial integration receipt, not a release or full MailPoet compatibility claim.

## Tested source and runtime

- Baseline: `origin/main` `dd8f2564e72f6faa3992cb071f574efeb1573c13`, plugin 1.2.4.
- Fixed plugin file SHA-256: `f2f009c6381ce5094accf743e71fa7bf065d0a1fe93e74331eac5ed8b7332049` (original subscriber retained; processed address uses a separate variable).
- Disposable WordPress 7.1; native PHP 8.5.8; MailPoet 5.38.0; WP Mail SMTP 4.9.0; MySQL 8.4.0.
- New local WordPress install and empty database. No production database, subscriber list, queue or credentials imported. No production system touched; no contact with the reporter.

## Isolation and transport

MySQL used a scratch datadir and Unix socket with `--skip-networking` and MySQL X disabled. WordPress had cron and automatic updates disabled before first boot. External WordPress HTTP was blocked both through `WP_HTTP_BLOCK_EXTERNAL` and a `pre_http_request` filter. PHP `mail()` was disabled and its sendmail path set to `/usr/bin/false`. No MailPoet service/API key was configured.

WP Mail SMTP used unauthenticated SMTP on `127.0.0.1:25281`, with TLS disabled for this local-only test. A final `phpmailer_init` assertion refused any transport other than that exact SMTP endpoint. The local socket server had no relay or forwarding implementation: it accepted DATA into an in-process sink and logged only synthetic recipient/response events. It returned deterministic RCPT responses: 250 for accepted recipients, 550 5.1.1 for `reject@example.test`, and 450 4.2.0 for `temporary@example.test`.

These are application/runtime safeguards for the exercised paths, not a claim of OS-wide egress isolation against arbitrary plugin socket code. No external SMTP or mail API was used.

## Results

| Check | Receipt |
|---|---|
| Activation | MailPoet, WP Mail SMTP and OMPPM activated successfully in the real new WordPress install. |
| Alias | Reflection resolved `MailPoet\Mailer\Methods\PHPMail` to `OMPPM\MyPHPMailOverride`. |
| Real SMTP sequence, reused mailer | `Mailer::send()` with `newsletter` payload: accepted-before → RCPT 550 → accepted-after → RCPT 450 → accepted-final returned `true,false,true,false,true`. The catcher logged DATA only for accepted recipients; no duplicate acceptance within each sequence. |
| Real failure details | `wp_mail_failed` contained the SMTP rejection text for each rejected synthetic address. OMPPM still mapped both to `PHPMail has returned an unknown error.` at `LEVEL_HARD`; this fix does not add SMTP classification. |
| Baseline formatter | After a real 550 or 450, `SubscriberError::getEmail()` returned an array; `getMessageWithFailedSubscribers()` threw `TypeError: MailPoet\Mailer\SubscriberError::__toString(): Return value must be of type string, array returned`. |
| Fixed formatter | After the same real rejections, `getEmail()` returned the original string; formatting produced `PHPMail has returned an unknown error. Unprocessed subscriber: (reject@example.test)` (and the corresponding temporary recipient). |
| Baseline real queue error consumer | Real DI-created `SendingErrorHandler::processError()` with the 550 result threw the same TypeError. Fresh MailerLog remained `retry_attempt=null`, `retry_at` unset, no recorded error. |
| Fixed real queue error consumer | Same handler and real 550 result produced normal `Exception: Sending is waiting to be retried.` MailerLog had `retry_attempt=1`, `retry_at` set, and the formatted error with the correct string recipient. |
| WordPress password reset | Real `retrieve_password('smoke')` returned true, and the catcher accepted its message to the synthetic administrator. MailPoet was active. This does not independently prove a configured MailPoet transactional re-entry path. |
| New real-class regression | `tests/playground/assert-error-contract.php` ran through WP-CLI in this native WordPress instance: PASS for plain/named recipients, false/exception errors, safe formatting and subsequent-send guard reset. This particular regression uses `pre_wp_mail`; it is separate from the real SMTP checks above. |

The sequential direct mailer calls are not a newsletter queue run. Their later successes prove the mailer can be reused; they do not prove a worker immediately continues past a rejected recipient. The consumer test proves the fixed error reaches native MailPoet retry bookkeeping. Both 550 and 450 still use the native hard-error classification, and the fixed consumer correctly pauses for retry rather than silently treating failure as success.

## Still required before release

- A real scheduled newsletter/test-list queue run, including progress, retries, no duplicates and subsequent worker state.
- MailPoet Settings test-email flow and Tools-page browser smoke (the runtime alias check above is not a browser check).
- Explicit transactional/recursion configuration smoke, debug on/off/clear UI, unknown email-type fallback and full release matrix.
- SMTP authentication/connect/timeout cases and later asynchronous DSN/bounce handling. No claim that both defects alleged by the support reporter are identified.
- All repository test gates, exact-commit PR CI and post-merge Main CI; this document does not substitute for those receipts.

The temporary SMTP and MySQL processes were stopped after testing; the isolated scratch files remain local for diagnosis. No existing LocalWP site or service was changed.

## Repeat after integration of Main 1.2.5

On the same date, Main advanced to 1.2.5 through PR #7. The fix branch integrated that update with merge commit `2d5f0aa49bf9628c6390a2c35d12fb0c873236fa`. The final plugin file SHA-256 is `e7b9ea1972c140f1f71d87ba712bfd338b3118b56f11ed2b5a9a1d248c66fa43`.

The scratch runtime was restarted with the same isolation, and the final plugin and admin module were copied into it. Runtime readback confirmed OMPPM **1.2.5**, MailPoet **5.38.0**, WP Mail SMTP **4.9.0**, WordPress **7.1**, PHP **8.5.8**, and the active override alias. The following checks were repeated successfully:

- Real SMTP sequence: accepted → 550 → accepted → 450 → accepted returned `true,false,true,false,true`; both failures retained string recipients and formatted safely.
- Real DI-created `SendingErrorHandler` with a fresh real 550 result produced the expected retry exception, `retry_attempt=1`, a set retry time and the correctly formatted error.
- Real WordPress password reset returned true and was captured locally.
- `tests/playground/assert-error-contract.php` passed against the real installed MailPoet classes.

The original limitations above remain unchanged. The test processes were again stopped after this repeat; the SMTP listener was closed and the private MySQL socket removed.

## Completion matrix: real queue worker and controlled fault recovery

The continuation of Issue #8 added a reproducible native harness in `scripts/test-e2e.sh` and `tests/e2e/`. The initial 1.2.5 receipt above remains historical evidence of the original failure and deliberately limited first fix. The subsequent SMTP capture fix additionally identifies narrowly supported permanent recipient responses; the tests below supersede the old open queue/transport items.

The complete harness passed from a newly installed disposable WordPress 7.1, MailPoet 5.38.0, WP Mail SMTP 4.9.0 and PHP 8.5.8/MySQL 8.4.0. It created its own database, downloaded the pinned real plugins, installed isolation before first WordPress boot and removed its own database/SMTP process afterward. No production data or credentials were used.

| Scenario | Observed native queue and SMTP result |
|---|---|
| Public worker + cold renderer | Actual Segment/Newsletter/Subscriber membership and running ScheduledTask; public `SendingQueue::process()` rendered MailPoet's SimpleText template from a null rendered-body cache and completed three successful SMTP acceptances. |
| Public worker + permanent recipient | Success → 550/5.1.1 → success: task completed, processed=3, failed=1, exactly two SMTP acceptances. Failed row retained its recipient-specific error; global subscriber status stayed subscribed. |
| Focused permanent recipient | Same result through `processQueue()`, including named recipients and real membership records. |
| Temporary recipient 450 | First healthy recipient processed; remaining two persisted unprocessed with native retry. A new PHP process resumed them after synthetic recovery: all three healthy recipients accepted exactly once, failed=0. |
| Authentication 535 / connection refusal / greeting timeout | No recipients processed or falsely marked failed/bounced during fault. Native retry persisted. A fresh process after transport recovery processed all three, each accepted once. |
| Policy 550/5.7.1 | Remained a native hard/retry error rather than a permanent subscriber failure. Fresh-process recovery accepted each recipient once when the synthetic policy fault was removed. |
| DSN boundary | Accepted messages remained subscribed; real Bounce worker `checkProcessingRequirements()` returned false in host/PHPMail mode. No inbound DSN engine or external mailbox was invented. MailPoet documents manual bounce handling for other sending methods. |
| Transactional recursion | Real MailPoet `WordpressMailerReplacer`/`WordPressMailer`, real WordPress password reset: two `wp_mail()` calls, bounded native fallback, exactly one SMTP acceptance. For this single process, PHP `mail()` used a fixed `.test`-only shim to the loopback sink; no external sendmail was enabled. |

Retry tests deliberately resume the native MailerLog between controlled runs instead of waiting for the production retry interval. This proves persisted recovery and avoids duplicate healthy sends; it is not a claim about wall-clock cron scheduling. The cold/full cases use the public worker selector. Fault-isolation cases use the real batch worker directly. The harness never equates MailPoet processed/attempt statistics with SMTP acceptance.

### Browser smoke receipt from the owning session

The owning session completed the disposable onboarding with telemetry and external libraries disabled and verified the final Tools/Admin UI:

- MailPoet Settings sending-method test to `browser-test@example.test`: UI success; independently read SMTP receipt `250 2.1.5 OK` followed by exactly one acceptance.
- Plugin test email to the synthetic administrator: UI success and local transport receipt.
- Debug enabled and persisted across refresh; only event codes/timestamps displayed. Debug disabled and log cleared through the UI; zero entries persisted afterward.

These browser observations belong to the owning session; this agent independently verified the MailPoet Settings SMTP receipt and exercised the transport/worker harness. The runtime remained available until the owner finished the UI checks.
