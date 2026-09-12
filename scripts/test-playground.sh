#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
result_dir="$(mktemp -d)"
trap 'rm -rf "$result_dir"' EXIT
# Pin Node too: newer local Node releases may lack fs-ext native binaries.
node_version="22.16.0"
playground_version="3.1.46"
if [[ "${1:-}" == '--negative' ]]; then
    printf '1' > "$result_dir/force-failure"
fi
cli_status=0
npx --yes --package="node@$node_version" --package="@wp-playground/cli@$playground_version" \
    wp-playground-cli run-blueprint \
    --mount="$repository_root:/wordpress/wp-content/plugins/omppm-override-phpmail-mailpoet" \
    --mount="$result_dir:/omppm-assertions" \
    --blueprint="$repository_root/.playground/blueprint.json" || cli_status=$?
if [[ -f "$result_dir/negative-reached" ]]; then
    printf 'FAIL: Intentional negative control reached inside WordPress.\n' >&2
    exit 1
fi
if [[ "$cli_status" -ne 0 ]]; then
    exit "$cli_status"
fi
# CLI exit 0 alone does not prove that WordPress or its assertions ran.
if [[ ! -f "$result_dir/passed" ]] || [[ "$(cat "$result_dir/passed")" != 'OMPPM_ALIAS_PASS' ]]; then
    printf 'FAIL: WordPress assertion completion receipt is missing.\n' >&2
    exit 1
fi
printf 'PASS: WordPress + MailPoet alias assertion completed.\n'
