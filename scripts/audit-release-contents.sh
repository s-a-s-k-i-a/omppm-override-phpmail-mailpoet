#!/usr/bin/env bash
set -euo pipefail

release_root="${1:-}"

if [[ -z "$release_root" || ! -d "$release_root" ]]; then
	printf 'Usage: %s <extracted-plugin-directory>\n' "$0" >&2
	exit 2
fi

forbidden_entries=()

while IFS= read -r entry; do
	forbidden_entries+=( "${entry#"$release_root"/}" )
done < <(
	find "$release_root" \
		\( \
			-name '.DS_Store' -o \
			-name '._*' -o \
			-name '__MACOSX' -o \
			-name 'Thumbs.db' -o \
			-name '.git' -o \
			-name '.svn' -o \
			-name '.hg' -o \
			-name '.idea' -o \
			-name '.vscode' -o \
			-name '.env' -o \
			-name '.env.*' -o \
			-name 'node_modules' -o \
			-name '*.log' -o \
			-name '*.zip' -o \
			-name '*.swp' -o \
			-name '*~' \
		\) -print
)

for forbidden_top_level in \
	.github \
	.playground \
	.wordpress-org \
	dist \
	docs \
	scripts \
	tests \
	vendor \
	AGENTS.md \
	CLAUDE.md \
	CHANGELOG.md \
	CONTRIBUTING.md \
	SECURITY.md \
	composer.json \
	composer.lock \
	phpcs.xml.dist \
	phpunit.xml.dist
do
	if [[ -e "$release_root/$forbidden_top_level" ]]; then
		forbidden_entries+=( "$forbidden_top_level" )
	fi
done

if [[ ${#forbidden_entries[@]} -gt 0 ]]; then
	printf 'Forbidden release entries detected:\n' >&2
	printf ' - %s\n' "${forbidden_entries[@]}" >&2
	exit 1
fi

printf 'Release content audit passed.\n'
