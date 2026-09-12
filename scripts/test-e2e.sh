#!/usr/bin/env bash
set -euo pipefail
repo="$(cd "$(dirname "$0")/.." && pwd)"
scratch="$(mktemp -d "${TMPDIR:-/tmp}/omppm-e2e.XXXXXX")"
db="omppm_e2e_$(date +%s)_$$"
wp_root="$scratch/wordpress"
smtp_pid=""
wp() { php -d memory_limit=512M -d disable_functions=mail -d sendmail_path=/usr/bin/false "$scratch/wp-cli.phar" --path="$wp_root" "$@"; }
cleanup() {
 if [[ -n "$smtp_pid" ]]; then kill "$smtp_pid" 2>/dev/null || true; wait "$smtp_pid" 2>/dev/null || true; fi
 if [[ -f "$wp_root/wp-config.php" && "$db" == omppm_e2e_* ]]; then wp db drop --yes >/dev/null 2>&1 || true; fi
 echo "E2E artifacts: $scratch (synthetic only)"
}
trap cleanup EXIT
curl --fail --silent --show-error --location https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$scratch/wp-cli.phar"
wp core download --version=7.1 --quiet
wp config create --dbname="$db" --dbuser="${OMPPM_E2E_DB_USER:-root}" --dbpass="${OMPPM_E2E_DB_PASS-root}" --dbhost="${OMPPM_E2E_DB_HOST:-127.0.0.1}" --skip-check --extra-php <<'PHP'
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_AUTO_UPDATE_CORE', false);
define('AUTOMATIC_UPDATER_DISABLED', true);
PHP
mkdir -p "$wp_root/wp-content/mu-plugins" "$wp_root/wp-content/plugins/omppm-override-phpmail-mailpoet"
cp "$repo/tests/e2e/isolation.php" "$wp_root/wp-content/mu-plugins/omppm-e2e-isolation.php"
# Artifact downloads occur outside WordPress; its outbound HTTP stays disabled from first boot.
for spec in mailpoet.5.38.0 wp-mail-smtp.4.9.0; do
 curl --fail --silent --show-error --location "https://downloads.wordpress.org/plugin/$spec.zip" -o "$scratch/$spec.zip"
 unzip -q "$scratch/$spec.zip" -d "$wp_root/wp-content/plugins"
done
cp "$repo/omppm-override-phpmail-mailpoet.php" "$wp_root/wp-content/plugins/omppm-override-phpmail-mailpoet/"
cp -R "$repo/includes" "$wp_root/wp-content/plugins/omppm-override-phpmail-mailpoet/"
wp db create
wp core install --url=http://localhost --title='Isolated E2E' --admin_user=smoke --admin_password="$(openssl rand -hex 16)" --admin_email=admin@example.test --skip-email
wp option update wp_mail_smtp '{"mail":{"from_email":"sender@example.test","from_name":"Synthetic","mailer":"smtp"},"smtp":{"host":"127.0.0.1","port":25281,"encryption":"none","auth":false,"autotls":false}}' --format=json
wp plugin activate wp-mail-smtp mailpoet omppm-override-phpmail-mailpoet
python3 "$repo/tests/e2e/smtp.py" --log "$scratch/smtp.jsonl" > "$scratch/smtp-server.log" 2>&1 &
smtp_pid=$!
python3 - <<'PY'
import socket,time
for i in range(50):
 try:
  s=socket.create_connection(('127.0.0.1',25281),timeout=.2);s.close();break
 except OSError:time.sleep(.1)
else:raise RuntimeError('Local SMTP sink did not start')
PY
kill -0 "$smtp_pid"
for scenario in cold full permanent temporary auth connect timeout dsn policy; do
 export OMPPM_E2E_SCENARIO="$scenario" OMPPM_E2E_STATE="$scratch/$scenario.json" OMPPM_E2E_PHASE=initial
 wp eval-file "$repo/tests/e2e/queue.php" | tee "$scratch/$scenario-initial.json"
 if [[ "$scenario" =~ ^(temporary|auth|connect|timeout|policy)$ ]]; then
  export OMPPM_E2E_PHASE=retry
  wp eval-file "$repo/tests/e2e/queue.php" | tee "$scratch/$scenario-retry.json"
 fi
 python3 "$repo/tests/e2e/assert-capture.py" "$scratch/smtp.jsonl" "$scratch/$scenario.json" "$scenario"
done
wp eval-file "$repo/tests/playground/assert-error-contract.php"
wp eval 'if(retrieve_password("smoke")!==true)throw new RuntimeException("Password reset failed"); echo "PASS password reset\n";'
# Native recursion fallback intentionally enables PHP mail only through this non-relaying local shim.
export OMPPM_E2E_SMTP_LOG="$scratch/smtp.jsonl"
php -d memory_limit=512M -d "sendmail_path=python3 '$repo/tests/e2e/sendmail.py'" "$scratch/wp-cli.phar" --path="$wp_root" eval-file "$repo/tests/e2e/recursion.php"
echo 'PASS real SMTP and persisted MailPoet queue matrix'
