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
dev stack running; the check container then joins the compose network. Without `DB_PASSWORD` that test is skipped.

PHP 8.3 (`deploy/php-dev/Dockerfile`) and Go 1.22 run in containers, so Docker is the only host requirement.

## Production

Not covered in SW-001. Production runbooks (deploy, backup/restore, agent rollout, uninstall) arrive with SW-007 (load test, backup/restore) and pilot hardening.
