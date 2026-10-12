# Runbooks

## Dev stack

```sh
cp .env.example .env            # then set MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD, REDIS_PASSWORD, DB_PASSWORD
docker compose --env-file .env -f deploy/docker-compose.dev.yml up -d
docker compose --env-file .env -f deploy/docker-compose.dev.yml down      # stop; keeps data volumes
```

DB_PASSWORD must equal MYSQL_PASSWORD. Services listen on 127.0.0.1 only (3306, 6379, 8428).

## Quality gates

```sh
sh scripts/check.sh              # all: dashboard, ingestion, agent, compose
sh scripts/check.sh dashboard    # or: ingestion | agent | compose
```

To include the MySQL integration test, export the `.env` variables first (`set -a; . ./.env; set +a`) with the
dev stack running; without `DB_*` that test is skipped.

Go runs in `golang:1.22-alpine` via Docker, so no local Go toolchain is needed.

## Production

Not covered in Sprint 0. Production runbooks (deploy, backup/restore, agent rollout, uninstall) are Sprint 7-8.
