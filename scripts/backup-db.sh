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

# L'archive est ecrite sous un nom temporaire, puis renommee SEULEMENT si
# mysqldump a reussi. Auparavant, un echec (mot de passe errone, base
# arretee) laissait une archive tronquee sous un nom valide : elle passait
# pour une sauvegarde, et la purge a 30 jours finissait par supprimer les
# vraies. Le motif `volo_*.sql.gz` de la purge ne correspond pas au fichier
# temporaire, qui n'est donc jamais compte comme sauvegarde.
TMP_FILE="${BACKUP_FILE}.partial"
trap 'rm -f "$TMP_FILE"' EXIT

mkdir -p "$BACKUP_DIR"

if [ "${1:-}" = "--local" ]; then
    # Local XAMPP mysqldump
    MYSQLDUMP="${MYSQLDUMP:-mysqldump}"
    "$MYSQLDUMP" -u root "${DB_NAME:-volo}" | gzip > "$TMP_FILE"
else
    # --- Trouver le conteneur de base DE CE DEPOT ------------------------------
    # L'ancienne version cherchait un conteneur par son NOM (volo-db, puis
    # volo-mysql). Or ces noms sont globaux a la machine Docker : un autre
    # projet peut tres bien avoir son propre `volo-mysql` — c'etait le cas sur
    # le poste de developpement. Si volo-db etait arrete, le script aurait
    # sauvegarde la base d'un AUTRE projet, sans aucun avertissement.
    #
    # `docker compose ps` ne voit que les conteneurs du projet decrit par le
    # fichier donne : on ne peut plus se tromper de base.
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
    # Plus aucun mot de passe dans ce script. On lit les variables avec
    # lesquelles le conteneur MySQL a ete initialise, donc toujours les bonnes.
    # MYSQL_PWD plutot que -p : un mot de passe passe en argument est visible
    # de tout utilisateur de la machine dans la liste des processus.
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
