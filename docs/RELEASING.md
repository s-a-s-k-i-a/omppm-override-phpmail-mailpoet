# Releasing

## Preconditions

- All issue acceptance criteria and tests are green.
- Version values match in the plugin header, `OMPPM_Admin::PLUGIN_VERSION`, `readme.txt` stable tag, `README.md` stable tag, and both changelogs (`php scripts/check-version.php` verifies the file side).
- `Tested up to` reflects a real test, not an assumption.
- The GitHub `wordpress.org` environment has a required maintainer approval and the `SVN_USERNAME` and `SVN_PASSWORD` secrets.
- `./scripts/test-release-contents.sh` passes; the build also audits its staged files before creating the ZIP.

## Release

1. Merge the reviewed pull request to `main`.
2. Create and push a numeric annotated tag, for example `1.2.5`.
3. The release workflow validates, tests, builds, and publishes the GitHub release ZIP.
4. After the protected-environment approval, the same tag contents are deployed to WordPress.org SVN `trunk` and `tags/<version>`, and the `.wordpress-org/` banners and icons are synced to the SVN `assets/` directory.
5. Verify the SVN tag, the public plugin page version, and the downloaded WordPress.org ZIP against the release manifest (`./scripts/verify-svn-sync.sh <version>`).

Never edit SVN independently. If emergency SVN recovery is unavoidable, immediately import the exact committed result back into Git and document the divergence.

Note: SVN tags up to 1.2.4 predate this pipeline and contain versioned `.DS_Store` files (issue #6); from 1.2.5 on, `verify-svn-sync.sh` compares strictly with no junk-file exclusions.

The release workflow calls the same complete CI workflow as pull requests,
including the PHP matrix, Playground, Plugin Check and native MailPoet SMTP
E2E. GitHub publication and SVN deployment cannot start if one of these fails.
The disposable E2E environment contains only generated `.test` recipients
and sends only to a loopback SMTP catcher.

GitHub settings were read back on 2026-09-12: the repository is public and the
`wordpress.org` environment requires approval by `s-a-s-k-i-a`. Administrators
can bypass that environment rule; self-review is permitted and deployment
branches are unrestricted. At audit time `main` had no branch protection.
These are control-plane settings, not guarantees enforced by these files;
read them back before relying on them and record any subsequent changes.
