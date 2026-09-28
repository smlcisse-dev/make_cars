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
# Fonctionnement (correctif 2026-09-28) :
# 1. pg_restore écrit le script SQL de la sauvegarde dans un fichier
#    temporaire (sans --clean), sans l'entrée du schéma public ni les
#    fonctions : le schéma public n'est jamais supprimé ni recréé (il porte
#    les droits accordés par Supabase à anon/authenticated/service_role), et
#    les fonctions de public viennent de Supabase (rls_auto_enable), pas des
#    migrations de l'application.
# 2. psql exécute, dans UNE transaction (--single-transaction, arrêt à la
#    première erreur) : la suppression avec CASCADE de toutes les tables,
#    vues et séquences du schéma public de la cible (hors objets
#    d'extensions), puis ce script. Les tables absentes de la sauvegarde
#    (migrations plus récentes dans la cible) sont donc supprimées elles
#    aussi, ce que --clean ne faisait pas. Aucun autre schéma n'est touché
#    (auth, storage, extensions… appartiennent à Supabase).
#
# Le fichier SQL est entièrement produit AVANT la connexion à la base : une
# sauvegarde illisible échoue sans rien toucher, et psql ne peut jamais
# valider une restauration tronquée.
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

# Fichiers temporaires dans le dossier de la sauvegarde (monté dans le
# conteneur), lisibles par vous seul (ils contiennent les données), supprimés
# à la sortie quoi qu'il arrive.
umask 077
list_file="$(mktemp "${pg_dir}/.restore-list.XXXXXX")"
clean_file="$(mktemp "${pg_dir}/.restore-clean.XXXXXX")"
sql_file="$(mktemp "${pg_dir}/.restore-data.XXXXXX")"
trap 'rm -f "$list_file" "$clean_file" "$sql_file"' EXIT

# Contenu restauré : tout, sauf le schéma public lui-même (et son
# commentaire) et les fonctions (voir en tête de fichier).
run_pg pg_restore --list "$file" \
  | grep -v -E '^[0-9]+; [0-9]+ [0-9]+ (SCHEMA - public |COMMENT - SCHEMA public |FUNCTION public )' \
  > "$list_file"
run_pg pg_restore --no-owner --no-privileges --use-list="$list_file" \
  --file="$sql_file" "$file"

# Vidage du schéma public de la cible : tables, vues (simples et
# matérialisées), tables étrangères et séquences, avec CASCADE (clés
# étrangères des tables plus récentes que la sauvegarde). Jamais le schéma
# lui-même, jamais un objet appartenant à une extension.
cat > "$clean_file" <<'SQL'
SET client_min_messages = warning;
DO $$
DECLARE
  obj record;
BEGIN
  FOR obj IN
    SELECT c.relname,
           CASE c.relkind
             WHEN 'm' THEN 'MATERIALIZED VIEW'
             WHEN 'v' THEN 'VIEW'
             WHEN 'f' THEN 'FOREIGN TABLE'
             WHEN 'S' THEN 'SEQUENCE'
             ELSE 'TABLE'
           END AS kind
    FROM pg_class c
    WHERE c.relnamespace = 'public'::regnamespace
      AND c.relkind IN ('r', 'p', 'v', 'm', 'f', 'S')
      AND NOT c.relispartition
      AND NOT EXISTS (
        SELECT 1 FROM pg_depend d
        WHERE d.classid = 'pg_class'::regclass
          AND d.objid = c.oid
          AND d.deptype = 'e'
      )
    ORDER BY CASE c.relkind WHEN 'm' THEN 1 WHEN 'v' THEN 2 WHEN 'S' THEN 4 ELSE 3 END
  LOOP
    EXECUTE format('DROP %s IF EXISTS public.%I CASCADE', obj.kind, obj.relname);
  END LOOP;
END
$$;
SQL

# Une seule transaction pour le vidage ET la restauration : à la première
# erreur, psql s'arrête et la transaction est annulée (base inchangée).
run_pg psql --no-psqlrc --quiet --set=ON_ERROR_STOP=1 --single-transaction \
  --file="$clean_file" --file="$sql_file" "$conninfo" > /dev/null

echo
echo "Restauration terminée dans la base de développement."
echo "Vérifier : php artisan migrate:status (environnement dev), puis connexion à l'application."
