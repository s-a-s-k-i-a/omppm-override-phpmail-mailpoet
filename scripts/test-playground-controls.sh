#!/usr/bin/env bash
set -euo pipefail
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
"$repository_root/scripts/test-playground.sh"
negative_output="$(mktemp)"
trap 'rm -f "$negative_output"' EXIT
if "$repository_root/scripts/test-playground.sh" --negative > "$negative_output" 2>&1; then
    printf 'FAIL: Intentional assertion failure incorrectly passed.\n' >&2
    exit 1
fi
if ! grep -q 'Intentional negative control reached inside WordPress' "$negative_output"; then
    cat "$negative_output" >&2
    printf 'FAIL: Negative run failed before reaching the intended assertion.\n' >&2
    exit 1
fi
printf 'PASS: Intentional WordPress assertion failure was rejected.\n'
