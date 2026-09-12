# Contributing

Please start with a focused GitHub issue that includes reproduction steps, expected and actual behavior, environment versions (WordPress, PHP, MailPoet, SMTP plugin), and acceptance criteria.

Development requires PHP 8.1+, Composer, and Node.js for WordPress Playground. Before opening a pull request, run:

```bash
composer install
composer test
composer lint
./scripts/test-playground.sh
```

Pull requests should reference their issue, add regression coverage for behavior changes, and update user-facing documentation when needed. Releases are performed by maintainers according to `docs/RELEASING.md`.
