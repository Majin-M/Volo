#!/bin/sh
# =============================================================================
# Entrypoint du conteneur backend VOLO
# =============================================================================
# Ce que fait ce script, et pourquoi il a fallu l'ecrire :
#
#   1. ATTENDRE LA BASE. `depends_on: service_healthy` garantit que MySQL
#      repond au ping, pas qu'il accepte des connexions applicatives. La
#      premiere migration echouait donc par intermittence.
#
#   2. JOUER LES MIGRATIONS. Rien ne les jouait. Une pile fraichement montee
#      avait une base VIDE, sans schema : toutes les requetes echouaient. Le
#      `docker-compose.yml` decrivait une application complete qui n'aurait
#      jamais pu servir une seule page.
#
#   3. GENERER LES CLES JWT AU DEMARRAGE, pas a la construction.
#      Le Dockerfile les generait dans un `RUN`, avec
#      `${JWT_PASSPHRASE:-VoLoJwT2026!}` — mais sans `ARG JWT_PASSPHRASE`
#      declare, cette variable est TOUJOURS vide au moment du build : le repli
#      etait systematiquement utilise. A l'execution, Compose injecte la vraie
#      passphrase. Le jour ou quelqu'un en definit une, la cle scellee dans
#      l'image ne correspond plus et la signature JWT casse.
#      Les generer ici resout le probleme et en supprime un second : les cles
#      vivent desormais dans un volume, donc une reconstruction d'image ne les
#      change plus — sans quoi chaque deploiement deconnectait tout le monde.
#
# Le script s'arrete a la premiere erreur (`set -e`) : mieux vaut un conteneur
# qui refuse de demarrer qu'un conteneur qui sert une application cassee.
# =============================================================================

set -e

echo "[volo] Demarrage du backend (APP_ENV=${APP_ENV:-prod})"

# --- 1. Attendre que la base accepte reellement les connexions --------------
# On interroge Doctrine lui-meme plutot que le port : c'est le seul test qui
# prouve que l'application peut se connecter, avec SES identifiants.
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
if [ ! -f config/jwt/private.pem ]; then
    echo "[volo] Generation de la paire de cles JWT."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    chown -R www-data:www-data config/jwt
else
    echo "[volo] Cles JWT deja presentes, conservees."
fi

# --- 3. Migrations ----------------------------------------------------------
# --allow-no-migration : un demarrage sans migration en attente est un cas
# NORMAL (redemarrage, montee en charge), pas une erreur. Sans ce drapeau, la
# commande sort en code non nul et le conteneur refuserait de redemarrer.
echo "[volo] Application des migrations."
php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration

echo "[volo] Migrations a jour."

# --- 4. Cache applicatif ----------------------------------------------------
php bin/console cache:warmup --no-interaction
chown -R www-data:www-data var/

echo "[volo] Pret. Passage a la main a php-fpm."

# exec : php-fpm devient le PID 1 et recoit donc les signaux d'arret de
# Docker. Sans `exec`, `docker compose down` attendrait le delai de grace
# complet avant de tuer le conteneur.
exec "$@"
