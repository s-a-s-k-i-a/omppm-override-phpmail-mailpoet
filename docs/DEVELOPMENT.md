# Development

GitHub `main` is the canonical source. WordPress.org SVN is updated only from a tested numeric Git tag.

Install tooling with `composer install`. Production code targets PHP 8.1+ (declared minimum since 1.2.5, issue #1) and must stay compatible with the MailPoet-internal classes it overrides. Do not commit `vendor/`, Playground site state, generated ZIP files, or credentials.

Use one issue per independently reviewable defect or feature. Compatibility reports should identify the exact MailPoet and SMTP-plugin versions, then reduce the behavior to the routing contract in `tests/unit/` whenever possible. A real MailPoet activation and send smoke is still required before claiming compatibility with a specific MailPoet release.

The pragmatic PHPCS baseline covers PHP compatibility only; full WordPress Coding Standards adoption is tracked in issue #2.
