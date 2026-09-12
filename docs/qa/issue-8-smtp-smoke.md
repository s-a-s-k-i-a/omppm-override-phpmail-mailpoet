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
