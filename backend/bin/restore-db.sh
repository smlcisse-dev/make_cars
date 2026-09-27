#!/usr/bin/env bash
# Restauration d'une sauvegarde faite par bin/backup-db.sh, TOUJOURS dans la
# base de DÉVELOPPEMENT (.env.development) — jamais la production, quel que
# soit l'environnement actif. Sert à vérifier qu'une sauvegarde est
# utilisable (DEPLOIEMENT.md, « Sauvegarde de la base »).
#
#   bin/restore-db.sh ~/makecars-sauvegardes/makecars-production-AAAA-MM-JJ_HHMMSS.dump
#
# Remplace TOUTES les tables de l'application (schéma public) de la base de
# développement par celles de la sauvegarde. En une seule transaction : en
# cas d'erreur, rien n'est modifié.
#
# Restaurer la PRODUCTION n'est volontairement pas prévu ici : c'est une
# opération exceptionnelle, à décider et à faire à la main (même commande
# pg_restore, connexion de production), après une nouvelle sauvegarde.
set -euo pipefail

cd "$(dirname "$0")/.."
source bin/db-common.sh

file="${1:-}"
if [ -z "$file" ] || [ ! -f "$file" ]; then
  echo "Usage : bin/restore-db.sh <fichier .dump>" >&2
  exit 1
fi
pg_dir="$(cd "$(dirname "$file")" && pwd)"
file="${pg_dir}/$(basename "$file")"

# Garde-fou : la cible ne doit jamais être le projet de production.
production_user=""
if [ -f .env.production ]; then
  production_user="$(grep -E '^DB_USERNAME=' .env.production | tail -1 | cut -d= -f2- | tr -d "\"'")"
fi

load_db_env ".env.development"

if [ -n "$production_user" ] && [ "$DB_USERNAME" = "$production_user" ]; then
  echo "Refus : .env.development pointe sur le même projet que .env.production (${DB_USERNAME})." >&2
  exit 1
fi

echo "Sauvegarde : ${file}"
echo "Cible      : base de DÉVELOPPEMENT, ${DB_HOST}:${DB_PORT}, utilisateur ${DB_USERNAME}"
echo
echo "Toutes les tables de l'application de la base de développement vont être"
echo "remplacées par celles de la sauvegarde."
read -r -p "Tapez RESTAURER pour continuer : " answer
if [ "$answer" != "RESTAURER" ]; then
  echo "Annulé."
  exit 1
fi

choose_pg_tools

run_pg pg_restore --clean --if-exists --no-owner --no-privileges \
  --single-transaction --exit-on-error --dbname="$conninfo" "$file"

echo
echo "Restauration terminée dans la base de développement."
echo "Vérifier : php artisan migrate:status (environnement dev), puis connexion à l'application."
