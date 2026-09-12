# Roadmap

Tracked development lives in [GitHub issues](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues). Seeded at repository adoption (2026-09-12):

- [#1](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/1) — Fix the broken PHP 8.0 support claim (enum parse error). **Shipped with 1.2.5** (minimum raised to PHP 8.1).
- [#3](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/3) — Harden admin output escaping and request unslashing.
- [#4](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/4) — Mask recipient addresses in debug logging (privacy).
- [#5](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/5) — Compatibility run with current WordPress and MailPoet; bump `Tested up to`.
- [#2](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/2) — Adopt the full WordPress Coding Standards ruleset.
- [#6](https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet/issues/6) — SVN hygiene: legacy `.DS_Store` files disappear with the first pipeline release.

Release cadence: fix #1 (+ ideally #3/#4) → version 1.2.5 through the tag-triggered pipeline as the first end-to-end deployment proof.
