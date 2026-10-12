#!/usr/bin/env sh
# Run every quality gate. Everything runs in pinned containers (PHP 8.3, Go 1.22), so only Docker is required.
#   Usage: scripts/check.sh            (all)
#          scripts/check.sh dashboard  (one component: dashboard|ingestion|agent|compose)
# Integration tests need the dev stack and DB_NAME/DB_USER/DB_PASSWORD exported (e.g. `set -a; . ./.env; set +a`);
# they are skipped otherwise.
set -eu

ROOT=$(cd "$(dirname "$0")/.." && pwd)
GO_IMAGE=${GO_IMAGE:-golang:1.22-alpine}
PHP_IMAGE=serverwatch-php-dev:8.3
DEV_NETWORK=serverwatch-dev_default

check_dashboard() {
  docker build -q -t "$PHP_IMAGE" "$ROOT/deploy/php-dev" >/dev/null
  net_args=""
  if [ -n "${DB_PASSWORD:-}" ] && docker network inspect "$DEV_NETWORK" >/dev/null 2>&1; then
    # Join the dev compose network so the container reaches MySQL as host "mysql".
    net_args="--network $DEV_NETWORK -e DB_HOST=mysql -e DB_PORT=3306 -e DB_NAME=${DB_NAME:-serverwatch} -e DB_USER=${DB_USER:-serverwatch} -e DB_PASSWORD"
  fi
  # shellcheck disable=SC2086
  MSYS_NO_PATHCONV=1 docker run --rm $net_args -v "$ROOT/dashboard:/app" -w /app "$PHP_IMAGE" \
    sh -c 'composer install --no-interaction --no-progress && composer check'
}

check_go() {
  dir=$1
  MSYS_NO_PATHCONV=1 docker run --rm -v "$ROOT/$dir:/src" -w /src "$GO_IMAGE" sh -c \
    'test -z "$(gofmt -l .)" || { echo "gofmt needed:"; gofmt -l .; exit 1; }; go vet ./... && go test ./... && go build ./...'
}

check_compose() {
  # Placeholder secrets: validates structure only; nothing is started.
  MYSQL_ROOT_PASSWORD=x MYSQL_PASSWORD=x REDIS_PASSWORD=x \
    docker compose -f "$ROOT/deploy/docker-compose.dev.yml" config --quiet
}

target=${1:-all}
case "$target" in
  dashboard) check_dashboard ;;
  ingestion) check_go ingestion ;;
  agent) check_go agent ;;
  compose) check_compose ;;
  all) check_dashboard; check_go ingestion; check_go agent; check_compose ;;
  *) echo "unknown target: $target" >&2; exit 2 ;;
esac
echo "OK: $target"
