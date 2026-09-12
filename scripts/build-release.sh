#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_version="${1:-$(php "$repository_root/scripts/check-version.php")}"
plugin_slug="omppm-override-phpmail-mailpoet"
build_temp="$(mktemp -d)"
artifact_dir="$repository_root/dist"
artifact_path="$artifact_dir/$plugin_slug-$release_version.zip"

cleanup_build_temp() {
	rm -rf "$build_temp"
}
trap cleanup_build_temp EXIT

php "$repository_root/scripts/check-version.php" "$release_version" >/dev/null
mkdir -p "$artifact_dir" "$build_temp/$plugin_slug"
rm -f "$artifact_path" "$artifact_path.sha256"
rsync -a --exclude='.phpunit.result.cache' --exclude-from="$repository_root/.distignore" "$repository_root/" "$build_temp/$plugin_slug/"
"$repository_root/scripts/audit-release-contents.sh" "$build_temp/$plugin_slug" >/dev/null

(
	cd "$build_temp"
	zip -q -r "$artifact_path" "$plugin_slug"
)

shasum -a 256 "$artifact_path" > "$artifact_path.sha256"
echo "$artifact_path"
