#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
playground_version="3.1.46"
plugin_mount="$repository_root:/wordpress/wp-content/plugins/omppm-override-phpmail-mailpoet"

npx --yes "@wp-playground/cli@$playground_version" run-blueprint \
	--mount="$plugin_mount" \
	--blueprint="$repository_root/.playground/blueprint.json"
