#!/bin/sh
# Dev only: throwaway database for automated tests (tests drop and recreate its tables).
set -eu
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS serverwatch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON serverwatch_test.* TO '${MYSQL_USER}'@'%';
SQL
