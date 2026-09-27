#!/usr/bin/env bash
# Sauvegarde MANUELLE d'une base Supabase (DEPLOIEMENT.md, « Sauvegarde de la
# base »). OBLIGATOIRE avant toute migration de la production et avant
# chaque mise en ligne : l'offre gratuite de Supabase n'en fait aucune.
#
#   bin/backup-db.sh prod     # base de production (.env.production)
#   bin/backup-db.sh dev      # base de développement (.env.development)
#
# - Connexion lue dans le fichier .env choisi, quel que soit l'environnement
#   actif ; mot de passe jamais affiché.
# - Seul le schéma `public` est sauvegardé : toutes les tables de
#   l'application (migrations Laravel comprises). Les schémas gérés par
#   Supabase (auth, storage…) ne sont pas à nous.
# - Les FICHIERS de Supabase Storage (justificatifs, photos) ne sont PAS
#   dans cette sauvegarde : seule la base l'est.
# - Format compressé de pg_dump (-Fc), fichier daté, dans BACKUP_DIR (par
#   défaut ~/makecars-sauvegardes, hors du dépôt : les sauvegardes
#   contiennent des données personnelles), lisible par vous seul.
set -euo pipefail

cd "$(dirname "$0")/.."
source bin/db-common.sh

case "${1:-}" in
  prod|production) env_file=".env.production"; label="production" ;;
  dev|development) env_file=".env.development"; label="developpement" ;;
  *)
    echo "Usage : bin/backup-db.sh {prod|dev}" >&2
    exit 1
    ;;
esac

load_db_env "$env_file"

pg_dir="${BACKUP_DIR:-$HOME/makecars-sauvegardes}"
mkdir -p "$pg_dir"
chmod 700 "$pg_dir"
pg_dir="$(cd "$pg_dir" && pwd)"
file="${pg_dir}/makecars-${label}-$(date +%Y-%m-%d_%H%M%S).dump"

echo "Base      : ${label} (${env_file})"
echo "Serveur   : ${DB_HOST}:${DB_PORT}, utilisateur ${DB_USERNAME}"
echo "Fichier   : ${file}"
choose_pg_tools

run_pg pg_dump --format=custom --schema=public --no-owner --no-privileges --file="$file" "$conninfo"
chmod 600 "$file"

tables="$(run_pg pg_restore --list "$file" | grep -c ' TABLE DATA ' || true)"

echo
echo "Sauvegarde terminée : ${file} ($(du -h "$file" | cut -f1), ${tables} tables)"
echo "Pour la tester : bin/restore-db.sh ${file}   (base de développement UNIQUEMENT)"
