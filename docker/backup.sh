#!/bin/sh
# Nightly database dump and uploads archive, kept for BACKUP_KEEP_DAYS days.
set -e

mkdir -p /backups
while true; do
    now=$(date +%s)
    next=$(date -d "today ${BACKUP_TIME:-03:30}" +%s)
    [ "$next" -le "$now" ] && next=$((next + 86400))
    sleep $((next - now))

    stamp=$(date +%Y%m%d-%H%M)
    mariadb-dump --single-transaction --routines -h mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" | gzip > "/backups/db-$stamp.sql.gz"
    tar -czf "/backups/uploads-$stamp.tar.gz" -C /storage app/public
    find /backups -type f -mtime +"${BACKUP_KEEP_DAYS:-14}" -delete
    echo "backup $stamp done"
done
