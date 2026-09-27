# Fonctions communes à bin/backup-db.sh et bin/restore-db.sh (fichier
# chargé par `source`, pas exécuté directement).

# Charge les paramètres de connexion du fichier .env donné, sans l'exécuter
# et sans jamais afficher le mot de passe (exporté dans PGPASSWORD, que
# pg_dump/pg_restore lisent eux-mêmes).
load_db_env() {
  local env_file="$1"

  if [ ! -f "$env_file" ]; then
    echo "Erreur : $env_file introuvable." >&2
    exit 1
  fi

  read_env() {
    grep -E "^$1=" "$env_file" | tail -1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
  }

  DB_HOST="$(read_env DB_HOST)"
  DB_PORT="$(read_env DB_PORT)"
  DB_DATABASE="$(read_env DB_DATABASE)"
  DB_USERNAME="$(read_env DB_USERNAME)"
  PGPASSWORD="$(read_env DB_PASSWORD)"
  export PGPASSWORD

  if [ -z "$PGPASSWORD" ]; then
    echo "Erreur : DB_PASSWORD est vide dans $env_file (à renseigner à la main)." >&2
    exit 1
  fi

  conninfo="host=${DB_HOST} port=${DB_PORT} dbname=${DB_DATABASE} user=${DB_USERNAME} sslmode=require connect_timeout=20"
}

# Choisit les outils PostgreSQL : pg_dump/pg_restore doivent avoir une
# version majeure au moins égale à celle du serveur (Supabase : 17). Si ceux
# de la machine sont plus anciens (Fedora 40 : 16), l'image officielle
# postgres:<version> est utilisée via podman (ou docker).
choose_pg_tools() {
  server_major=""
  if command -v psql >/dev/null 2>&1; then
    server_major="$(psql "$conninfo" -Atc 'show server_version_num' | cut -c1-2)"
  fi
  server_major="${server_major:-17}"

  local_major=""
  if command -v pg_dump >/dev/null 2>&1; then
    local_major="$(pg_dump --version | grep -oE '[0-9]+' | head -1)"
  fi

  runner=""
  if [ -n "$local_major" ] && [ "$local_major" -ge "$server_major" ]; then
    echo "Outils    : PostgreSQL ${local_major} de la machine (serveur ${server_major})"
    return
  fi

  runner="$(command -v podman || command -v docker || true)"
  if [ -z "$runner" ]; then
    echo "Erreur : pg_dump ${server_major} introuvable (machine : ${local_major:-absent}), et ni podman ni docker." >&2
    echo "Voir DEPLOIEMENT.md, « Sauvegarde de la base », pour l'installer." >&2
    exit 1
  fi
  echo "Outils    : conteneur postgres:${server_major} via $(basename "$runner") (machine : ${local_major:-absent}, serveur ${server_major})"
}

# Lance pg_dump ou pg_restore avec les outils choisis. Le dossier $pg_dir
# (celui des sauvegardes) est monté au même chemin dans le conteneur.
run_pg() {
  if [ -z "$runner" ]; then
    "$@"
  else
    # -e PGPASSWORD sans valeur : transmise depuis l'environnement, jamais
    # écrite sur la ligne de commande.
    # Fichiers créés à votre nom : --userns=keep-id pour podman (sans
    # droits root), --user pour docker.
    local user_args=(--user "$(id -u):$(id -g)")
    if [ "$(basename "$runner")" = "podman" ]; then
      user_args=(--userns=keep-id)
    fi
    "$runner" run --rm -i -e PGPASSWORD "${user_args[@]}" \
      -v "${pg_dir}:${pg_dir}:Z" \
      "docker.io/library/postgres:${server_major}" "$@"
  fi
}
