#!/bin/sh
# Entrypoint du backend : attend la base, génère les clés JWT si besoin,
# joue les migrations, puis lance php-fpm.
# set -e : le conteneur refuse de démarrer plutôt que de servir une application cassée.

set -e

echo "[volo] Demarrage du backend (APP_ENV=${APP_ENV:-prod})"

# --- 1. Attendre que la base accepte reellement les connexions --------------
# Test via Doctrine : le ping du healthcheck ne prouve pas que l'application se connecte.
ATTENTE_MAX=60
ecoule=0
until php bin/console dbal:run-sql "SELECT 1" --quiet 2>/dev/null; do
    ecoule=$((ecoule + 2))
    if [ "$ecoule" -ge "$ATTENTE_MAX" ]; then
        echo "[volo] ECHEC : base injoignable apres ${ATTENTE_MAX}s." >&2
        echo "[volo] Verifiez DATABASE_URL et l'etat du service 'db'." >&2
        exit 1
    fi
    echo "[volo] Base pas encore prete, nouvelle tentative... (${ecoule}s)"
    sleep 2
done
echo "[volo] Base joignable."

# --- 2. Cles JWT ------------------------------------------------------------
# Générées une seule fois, dans le volume jwt_keys, avec la passphrase d'exécution.
if [ ! -f config/jwt/private.pem ]; then
    echo "[volo] Generation de la paire de cles JWT."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    chown -R www-data:www-data config/jwt
else
    echo "[volo] Cles JWT deja presentes, conservees."
fi

# --- 3. Migrations ----------------------------------------------------------
# --allow-no-migration : aucune migration en attente n'est pas une erreur.
echo "[volo] Application des migrations."
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration

echo "[volo] Migrations a jour."

# --- 4. Cache applicatif ----------------------------------------------------
php bin/console cache:warmup --no-interaction
chown -R www-data:www-data var/

echo "[volo] Pret. Passage a la main a php-fpm."

# exec : php-fpm devient PID 1 et reçoit les signaux d'arrêt de Docker.
exec "$@"
