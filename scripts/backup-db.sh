#!/bin/bash
# Database backup script for VOLO
# Usage:
#   ./scripts/backup-db.sh                   # backup via le conteneur de base de CE depot
#   ./scripts/backup-db.sh --local            # backup via local mysqldump (XAMPP)
#
# Cron example (daily at 3:00 AM):
#   0 3 * * * /path/to/volo/scripts/backup-db.sh >> /var/log/volo-backup.log 2>&1

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backups"
RETENTION_DAYS=30
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/volo_${TIMESTAMP}.sql.gz"

# Nom temporaire, renommé seulement si la sauvegarde est valide : une archive
# tronquée ne passe jamais pour une sauvegarde ni n'est comptée par la purge.
TMP_FILE="${BACKUP_FILE}.partial"
trap 'rm -f "$TMP_FILE"' EXIT

mkdir -p "$BACKUP_DIR"

if [ "${1:-}" = "--local" ]; then
    # Local XAMPP mysqldump
    MYSQLDUMP="${MYSQLDUMP:-mysqldump}"
    "$MYSQLDUMP" -u root "${DB_NAME:-volo}" | gzip > "$TMP_FILE"
else
    # --- Trouver le conteneur de base DE CE DEPOT ------------------------------
    # Recherche par projet Compose et non par nom de conteneur, global à la
    # machine : impossible de sauvegarder la base d'un autre projet.
    if [ -z "${DB_CONTAINER:-}" ]; then
        # Pile complete (docker-compose.yml racine), service `db`.
        DB_CONTAINER=$(docker compose -f "${ROOT_DIR}/docker-compose.yml" ps -q db 2>/dev/null || true)
    fi
    if [ -z "${DB_CONTAINER:-}" ] && [ -f "${ROOT_DIR}/backend/compose.yaml" ]; then
        # Pile d'appoint (backend/compose.yaml), service `volo-db`.
        DB_CONTAINER=$(docker compose -f "${ROOT_DIR}/backend/compose.yaml" ps -q volo-db 2>/dev/null || true)
    fi

    if [ -z "${DB_CONTAINER:-}" ]; then
        echo "Aucun conteneur de base de ce depot ne tourne." >&2
        echo "Demarrez la pile, definissez DB_CONTAINER, ou utilisez --local (XAMPP)." >&2
        exit 1
    fi

    # --- Identifiants : ceux du conteneur lui-meme ------------------------------
    # Lus dans le conteneur MySQL : aucun mot de passe dans ce script.
    # MYSQL_PWD plutôt que -p : un argument est visible dans la liste des processus.
    docker exec "$DB_CONTAINER" sh -c '
        user="${MYSQL_USER:-root}"
        if [ "$user" = "root" ]; then pass="${MYSQL_ROOT_PASSWORD:-}"; else pass="${MYSQL_PASSWORD:-}"; fi
        MYSQL_PWD="$pass" exec mysqldump --single-transaction --no-tablespaces \
            -u"$user" "${MYSQL_DATABASE:-volo}"
    ' | gzip > "$TMP_FILE"
fi

# Controle d'integrite avant de declarer la sauvegarde valide : l'archive doit
# se decompresser, et contenir au moins une creation de table. Une base
# injoignable peut produire un gzip parfaitement valide... et vide.
if ! gzip -t "$TMP_FILE" 2>/dev/null || ! gzip -dc "$TMP_FILE" | grep -q "CREATE TABLE"; then
    echo "[$(date)] ECHEC : archive invalide ou vide, sauvegarde NON conservee." >&2
    exit 1
fi

mv "$TMP_FILE" "$BACKUP_FILE"

SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "[$(date)] Backup created: $BACKUP_FILE ($SIZE)"

# Purge backups older than retention period
DELETED=$(find "$BACKUP_DIR" -name "volo_*.sql.gz" -mtime +${RETENTION_DAYS} -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    echo "[$(date)] Purged $DELETED backup(s) older than ${RETENTION_DAYS} days"
fi
