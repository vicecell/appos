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

## Database and first admin (development)

```sh
# in .env: set APP_KEY (php -r 'echo base64_encode(random_bytes(32));'), DB_*; COOKIE_SECURE=false only for local http
docker run --rm --network serverwatch-dev_default --env-file .env -e DB_HOST=mysql -v "$PWD/dashboard:/app" -w /app \
  serverwatch-php-dev:8.3 php bin/migrate.php up        # image is built by scripts/check.sh dashboard
# ... same container command with:  php bin/provision.php org "Acme" acme
#                                   PROVISION_PASSWORD='a-long-passphrase' php bin/provision.php user "Ada" ada@example.test
#                                   php bin/provision.php member <org_id> <user_id> client_admin
```

`provision.php user` prints the TOTP secret and `otpauth://` URI once; scan it into an authenticator app. Without
`PROVISION_PASSWORD` a random password is generated and printed once. Platform-admin cross-tenant access:
`php bin/provision.php grant <user_id> <org_id> read|manage <ttl_seconds> <reason>` (max 7 days) and `revoke-grant <id>`.

Automated tests use a separate throwaway database (`DB_TEST_NAME`, must end in `_test`; created by
`deploy/mysql-init/10-test-db.sh` on first start of the dev MySQL volume). They drop and recreate its tables. For an
already-initialized volume run that script once: `docker compose ... exec mysql sh /docker-entrypoint-initdb.d/10-test-db.sh`.

## Production

Not covered in SW-001. Production runbooks (deploy, backup/restore, agent rollout, uninstall) arrive with SW-007 (load test, backup/restore) and pilot hardening.
