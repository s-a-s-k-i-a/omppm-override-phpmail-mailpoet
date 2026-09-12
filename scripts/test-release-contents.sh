#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
test_root="$(mktemp -d)"
source_root="$test_root/source"
staging_root="$test_root/staging"

cleanup_test_root() {
	find "$test_root" -depth -delete
}
trap cleanup_test_root EXIT

mkdir -p \
	"$source_root/includes" \
	"$source_root/__MACOSX" \
	"$source_root/node_modules/package" \
	"$source_root/.git" \
	"$source_root/.wordpress-org" \
	"$source_root/docs" \
	"$source_root/tests" \
	"$staging_root"

touch \
	"$source_root/plugin.php" \
	"$source_root/.DS_Store" \
	"$source_root/includes/.DS_Store" \
	"$source_root/includes/._class-omppm-admin.php" \
	"$source_root/__MACOSX/metadata" \
	"$source_root/node_modules/package/index.js" \
	"$source_root/.git/config" \
	"$source_root/.wordpress-org/banner-772x250.png" \
	"$source_root/docs/development.md" \
	"$source_root/tests/test-plugin.php" \
	"$source_root/Thumbs.db" \
	"$source_root/.env" \
	"$source_root/debug.log" \
	"$source_root/archive.zip"

rsync -a --exclude-from="$repository_root/.distignore" "$source_root/" "$staging_root/"

[[ -f "$staging_root/plugin.php" ]] || {
	printf 'Expected distribution file was excluded.\n' >&2
	exit 1
}

"$repository_root/scripts/audit-release-contents.sh" "$staging_root" >/dev/null

# Prove the second guard fails independently if a forbidden file reaches staging.
touch "$staging_root/.DS_Store"
if "$repository_root/scripts/audit-release-contents.sh" "$staging_root" >/dev/null 2>&1; then
	printf 'Release audit accepted a synthetic .DS_Store file.\n' >&2
	exit 1
fi

printf 'Release exclusion and audit regressions passed.\n'
