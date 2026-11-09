#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /app 2>/dev/null || true

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
php -r '
$v = PHP_VERSION;
if (version_compare($v, "7.2.34", "<")) {
    fwrite(STDERR, "PHP {$v} is older than required 7.2.34\n");
    exit(1);
}
'

for ext in mbstring curl dom json xml xmlwriter tokenizer phar ctype; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --no-ansi >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_out}"; then
    echo 'composer update: missing exact success line Generating autoload files' >&2
    exit 1
fi
rm -f "${composer_out}"

php scripts/check-cluster-pins.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}"

php scripts/check-legacy-names.php
php scripts/check-psr-contracts.php

php -S 127.0.0.1:10002 tests/Integration/server.php >/tmp/integration-server.log 2>&1 &
server_pid=$!
trap 'kill "${server_pid}" 2>/dev/null || true; wait "${server_pid}" 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do
    if curl -fsS --max-time 1 "http://127.0.0.1:10002/" >/dev/null 2>&1; then
        break
    fi
    sleep 0.2
done
export TEST_SERVER=127.0.0.1:10002

phpunit_out="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi

# shellcheck source=scripts/validate-phpunit-output.sh
source /app/scripts/validate-phpunit-output.sh

if ! validate_phpunit_output "${phpunit_out}" "/app/scripts/allowed-skipped-tests.txt" \
    "/app/scripts/expected-phpunit-ok.txt" "/app/scripts/expected-phpunit-summary.txt"; then
    exit 1
fi
summary_line="$(grep -Fx "$(tr -d '\r' < /app/scripts/expected-phpunit-summary.txt | sed '/^$/d' | tail -n1)" "${phpunit_out}" || true)"
footer_line="$(grep -Fx "$(tr -d '\r' < /app/scripts/expected-phpunit-ok.txt | sed '/^$/d' | tail -n1)" "${phpunit_out}" || true)"
echo "Strict footer line: ${footer_line}"
echo "Strict summary line: ${summary_line}"

vendor/bin/phpunit --version | head -n1
rm -f "${phpunit_out}"

echo 'system-test: all steps passed.'
