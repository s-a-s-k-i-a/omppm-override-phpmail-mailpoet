#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_version="${1:-$(php "$repository_root/scripts/check-version.php")}"
plugin_slug="omppm-override-phpmail-mailpoet"
comparison_temp="$(mktemp -d)"

cleanup_comparison_temp() {
    rm -rf "$comparison_temp"
}
trap cleanup_comparison_temp EXIT

"$repository_root/scripts/build-release.sh" "$release_version" >/dev/null
unzip -q "$repository_root/dist/$plugin_slug-$release_version.zip" -d "$comparison_temp/github"
for svn_path in "tags/$release_version" trunk; do
    destination="$comparison_temp/svn/$svn_path/$plugin_slug"
    mkdir -p "$(dirname "$destination")"
    svn export --non-interactive --quiet "https://plugins.svn.wordpress.org/$plugin_slug/$svn_path" "$destination"
    diff -ru "$comparison_temp/github/$plugin_slug" "$destination"
done
printf 'PASS: Release %s matches SVN tag and trunk.\n' "$release_version"

# WordPress.org's public API/CDN may lag the atomic SVN commit. Retry only
# this public propagation stage, for at most five minutes in total.
verification_seconds="${OMPPM_VERIFY_WAIT_SECONDS:-300}"
if [[ ! "$verification_seconds" =~ ^[0-9]+$ ]] || (( verification_seconds < 1 || verification_seconds > 300 )); then
    printf 'OMPPM_VERIFY_WAIT_SECONDS must be between 1 and 300.\n' >&2
    exit 1
fi
public_deadline=$((SECONDS + verification_seconds))
while (( SECONDS < public_deadline )); do
    remaining=$((public_deadline - SECONDS))
    request_timeout=$((remaining < 20 ? remaining : 20))
    if curl --fail --silent --show-error --max-time "$request_timeout" \
        --get 'https://api.wordpress.org/plugins/info/1.2/' \
        --data-urlencode 'action=plugin_information' \
        --data-urlencode "request[slug]=$plugin_slug" \
        --data-urlencode 'request[fields][sections]=0' \
        -o "$comparison_temp/public-info.json" 2> "$comparison_temp/public-error"; then
        # Validate both public metadata fields before requesting the ZIP.
        if php -r '
            $info = json_decode(file_get_contents($argv[1]), true);
            $expected = "https://downloads.wordpress.org/plugin/" . $argv[2] . "." . $argv[3] . ".zip";
            if (!is_array($info) || ($info["version"] ?? null) !== $argv[3] || ($info["download_link"] ?? null) !== $expected) {
                fwrite(STDERR, "Public version or download link has not reached the requested release.\n");
                exit(1);
            }
        ' "$comparison_temp/public-info.json" "$plugin_slug" "$release_version" 2> "$comparison_temp/public-error"; then
            remaining=$((public_deadline - SECONDS))
            if (( remaining <= 0 )); then break; fi
            request_timeout=$((remaining < 20 ? remaining : 20))
            if curl --fail --silent --show-error --location --max-time "$request_timeout" \
                "https://downloads.wordpress.org/plugin/$plugin_slug.$release_version.zip" \
                -o "$comparison_temp/public.zip" 2> "$comparison_temp/public-error"; then
                rm -rf "$comparison_temp/public"
                if unzip -q "$comparison_temp/public.zip" -d "$comparison_temp/public" 2> "$comparison_temp/public-error" \
                    && diff -ru "$comparison_temp/github" "$comparison_temp/public" > "$comparison_temp/public-error" 2>&1; then
                    printf 'PASS: Public WordPress.org version and downloadable ZIP match release %s.\n' "$release_version"
                    exit 0
                fi
            fi
        fi
    fi
    remaining=$((public_deadline - SECONDS))
    if (( remaining <= 0 )); then break; fi
    printf 'Waiting for WordPress.org public release propagation (%ss remaining).\n' "$remaining" >&2
    sleep_time=$((remaining < 15 ? remaining : 15))
    sleep "$sleep_time"
done
cat "$comparison_temp/public-error" >&2
printf 'FAIL: Public WordPress.org metadata/ZIP did not match within the bounded propagation window.\n' >&2
exit 1
