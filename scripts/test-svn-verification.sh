#!/usr/bin/env bash
set -euo pipefail
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/repo/scripts" "$fixture/repo/dist" "$fixture/source/omppm-override-phpmail-mailpoet" "$fixture/bin"
printf 'synthetic release\n' > "$fixture/source/omppm-override-phpmail-mailpoet/plugin.php"
cp "$repository_root/scripts/verify-svn-sync.sh" "$fixture/repo/scripts/"
cat > "$fixture/repo/scripts/build-release.sh" <<'BUILD'
#!/usr/bin/env bash
set -eu
(cd "$OMPPM_VERIFY_FIXTURE/source" && zip -qr "$OMPPM_VERIFY_FIXTURE/repo/dist/omppm-override-phpmail-mailpoet-1.2.5.zip" .)
BUILD
cat > "$fixture/bin/svn" <<'SVN'
#!/usr/bin/env bash
set -eu
for last; do :; done
cp -R "$OMPPM_VERIFY_FIXTURE/source/omppm-override-phpmail-mailpoet" "$last"
if [[ "${OMPPM_VERIFY_SCENARIO:-}" == trunk && "$*" == *'/trunk '* ]]; then printf 'drift\n' >> "$last/plugin.php"; fi
SVN
cat > "$fixture/bin/curl" <<'CURL'
#!/usr/bin/env bash
set -eu
args="$*"
while (( $# )); do
 if [[ "$1" == -o ]]; then destination="$2"; break; fi
 shift
done
if [[ "$args" == *api.wordpress.org* ]]; then
 version=1.2.5
 if [[ "${OMPPM_VERIFY_SCENARIO:-}" == metadata ]]; then version=1.2.4; fi
 printf '{"version":"%s","download_link":"https://downloads.wordpress.org/plugin/omppm-override-phpmail-mailpoet.%s.zip"}' "$version" "$version" > "$destination"
else
 cp "$OMPPM_VERIFY_FIXTURE/repo/dist/omppm-override-phpmail-mailpoet-1.2.5.zip" "$destination"
 if [[ "${OMPPM_VERIFY_SCENARIO:-}" == zip ]]; then
  printf 'unexpected archive member' > "$OMPPM_VERIFY_FIXTURE/extra-file"
  (cd "$OMPPM_VERIFY_FIXTURE" && zip -q "$destination" extra-file)
 fi
fi
CURL
chmod +x "$fixture/repo/scripts/build-release.sh" "$fixture/bin/"*
export OMPPM_VERIFY_FIXTURE="$fixture" OMPPM_VERIFY_WAIT_SECONDS=3
export PATH="$fixture/bin:$PATH"
"$fixture/repo/scripts/verify-svn-sync.sh" 1.2.5
for scenario in trunk metadata zip; do
 if OMPPM_VERIFY_SCENARIO="$scenario" "$fixture/repo/scripts/verify-svn-sync.sh" 1.2.5 > "$fixture/result" 2>&1; then
  printf 'FAIL: Verification accepted %s drift.\n' "$scenario" >&2
  exit 1
 fi
 printf 'PASS: Verification rejects %s drift.\n' "$scenario"
done
