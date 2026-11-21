#!/usr/bin/env bash
# Выгружает ваши данные с локального компьютера для переноса на сервер:
#   deploy/data/database.sql     — база (товары, склад, закупки, касса…)
#   deploy/data/uploads.tar.gz   — фото товаров
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p deploy/data

IGNORE=""
for t in sessions cache cache_locks jobs job_batches failed_jobs; do IGNORE="$IGNORE --ignore-table=babybloom.$t"; done

docker compose exec -T -e MYSQL_PWD=secret mysql mysqldump -ubabybloom \
  --no-tablespaces --skip-comments --skip-add-locks --default-character-set=utf8mb4 $IGNORE babybloom \
  > deploy/data/database.sql

COPYFILE_DISABLE=1 tar --no-xattrs -czf deploy/data/uploads.tar.gz -C storage/app/public --exclude=.gitignore .

ls -lh deploy/data
