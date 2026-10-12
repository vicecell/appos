#!/usr/bin/env sh
# Run every Sprint 0 quality gate. PHP runs on the host (or CI runner); Go runs in
# a pinned container so no local Go toolchain is required.
#   Usage: scripts/check.sh            (all)
#          scripts/check.sh dashboard  (one component: dashboard|ingestion|agent|compose)
set -eu

ROOT=$(cd "$(dirname "$0")/.." && pwd)
GO_IMAGE=${GO_IMAGE:-golang:1.22-alpine}

check_dashboard() {
  (cd "$ROOT/dashboard" && composer install --no-interaction --no-progress && composer check)
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
