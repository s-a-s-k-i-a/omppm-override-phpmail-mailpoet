# Agent instructions

## Source of truth

This GitHub repository is the canonical development source. WordPress.org SVN is a generated release mirror, never the place for independent code changes. Preserve official WordPress.org plugin guidelines and the GPL-compatible distribution.

## Required workflow

1. Read this file, `CLAUDE.md`, the linked development docs, and the issue before editing.
2. Work issue-first. A verified bug needs reproduction evidence, root cause, acceptance criteria, and regression coverage.
3. Keep the requested issue number locked. Put adjacent discoveries in separate issues.
4. Run `composer test`, `composer lint`, PHP syntax checks, and the Playground assertion.
5. Test in a real disposable WordPress instance with MailPoet active: class-alias status on the Tools page, MailPoet test email, a real newsletter send through an SMTP plugin, and a WordPress system mail (password reset) to exercise the recursion guard.
6. Merge only after checks pass. A merge is not a release.
7. Release with a numeric tag such as `1.2.5`. The plugin header, `OMPPM_Admin::PLUGIN_VERSION`, `readme.txt` stable tag, `README.md` stable tag, both changelogs, Git tag, GitHub release, release ZIP, SVN `trunk`, and SVN tag must match. `scripts/check-version.php` enforces the file-side parity.
8. Verify the public WordPress.org version and downloadable ZIP after deployment.

## Safety and scope

- This plugin overrides the MailPoet-internal class `\MailPoet\Mailer\Methods\PHPMail` via `class_alias`. Every MailPoet update can break the override: never claim compatibility with a MailPoet version without a real activation and send test.
- Do not add telemetry, remote code execution, a custom updater, premium licensing, or promotional admin notices.
- Never log or commit real subscriber email addresses, mail contents, or SMTP credentials. Debug logging privacy is tracked in issue #4.
- Do not make deliverability guarantees. Describe deterministic routing behavior and tested conditions precisely.
- Never place SVN credentials in the repository. GitHub Actions uses the protected `wordpress.org` environment and `SVN_USERNAME`/`SVN_PASSWORD` secrets.
- The minimum PHP version is 8.1 (since 1.2.5, issue #1); keep header, readme files, phpcs `testVersion`, and the CI matrix aligned when it changes.

## Commands

```bash
composer install
composer test
composer lint
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/test-playground.sh
./scripts/build-release.sh 1.2.4
./scripts/verify-svn-sync.sh
```

See `docs/DEVELOPMENT.md`, `docs/TESTING.md`, and `docs/RELEASING.md` for details.
