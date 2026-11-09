#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASELINE_SHA="7d0ed42"
IMAGE_TAG="psr7-system-test:php7.2.34"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
WORKDIR="${REPO_ROOT}/.baseline-worktree"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

if [[ -d "${WORKDIR}/.git" ]] || [[ -f "${WORKDIR}/.git" ]]; then
    git -C "${REPO_ROOT}" worktree remove --force "${WORKDIR}" 2>/dev/null || rm -rf "${WORKDIR}"
fi
git -C "${REPO_ROOT}" worktree add --detach "${WORKDIR}" "${BASELINE_SHA}"

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG}" "${REPO_ROOT}"

OK_LINE="$(
docker_cmd run --rm \
    -u "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -e COMPOSER_HOME=/tmp/composer \
    -v "${WORKDIR}:/app" \
    -w /app \
    "${IMAGE_TAG}" \
    bash -lc '
set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1
mkdir -p /tmp/composer
COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --no-ansi
php -S 127.0.0.1:10002 tests/Integration/server.php >/tmp/integration-server.log 2>&1 &
server_pid=$!
trap "kill ${server_pid} 2>/dev/null || true" EXIT
sleep 1
export TEST_SERVER=127.0.0.1:10002
vendor/bin/phpunit --colors=never 2>&1 | tee /tmp/phpunit.out
grep -Eo "^OK \\([0-9]+ tests?, [0-9]+ assertions?\\)$" /tmp/phpunit.out | tail -n 1
'
)"

git -C "${REPO_ROOT}" worktree remove --force "${WORKDIR}"

if [[ -z "${OK_LINE}" ]]; then
    echo "Failed to capture baseline OK line from ${BASELINE_SHA}" >&2
    exit 1
fi

printf '%s\n' "${OK_LINE}" > "${REPO_ROOT}/scripts/expected-phpunit-ok.txt"
echo "Wrote scripts/expected-phpunit-ok.txt: ${OK_LINE}"
