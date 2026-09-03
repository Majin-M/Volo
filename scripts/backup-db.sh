#!/bin/bash
# Database backup script for VOLO
# Usage:
#   ./scripts/backup-db.sh                   # backup via Docker container
#   ./scripts/backup-db.sh --local            # backup via local mysqldump (XAMPP)
#
# Cron example (daily at 3:00 AM):
#   0 3 * * * /path/to/volo/scripts/backup-db.sh >> /var/log/volo-backup.log 2>&1

set -euo pipefail

BACKUP_DIR="$(cd "$(dirname "$0")/.." && pwd)/backups"
RETENTION_DAYS=30
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/volo_${TIMESTAMP}.sql.gz"

# Database credentials (match docker-compose.yml)
DB_NAME="volo"
DB_USER="volo_user"
DB_PASS="volo_password"
DB_CONTAINER="volo-db"

mkdir -p "$BACKUP_DIR"

if [ "${1:-}" = "--local" ]; then
    # Local XAMPP mysqldump
    MYSQLDUMP="${MYSQLDUMP:-mysqldump}"
    "$MYSQLDUMP" -u root "$DB_NAME" | gzip > "$BACKUP_FILE"
else
    # Docker container mysqldump
    docker exec "$DB_CONTAINER" \
        mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        | gzip > "$BACKUP_FILE"
fi

SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "[$(date)] Backup created: $BACKUP_FILE ($SIZE)"

# Purge backups older than retention period
DELETED=$(find "$BACKUP_DIR" -name "volo_*.sql.gz" -mtime +${RETENTION_DAYS} -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    echo "[$(date)] Purged $DELETED backup(s) older than ${RETENTION_DAYS} days"
fi
