#!/usr/bin/env bash
# Bascule backend/.env entre l'environnement de developpement et de production
# (CLAUDE.md §4, ajout 2026-09-18). backend/.env est un lien symbolique vers
# .env.development ou .env.production ; ce script deplace ce lien et affiche
# toujours quel environnement est actif, pour qu'on ne l'oublie jamais.
set -euo pipefail

cd "$(dirname "$0")/.."

show_status() {
  if [ -L .env ]; then
    local target
    target="$(readlink .env)"
    echo "Environnement actif : ${target}"
    grep -E '^DB_(HOST|USERNAME)=' "$target" | sed 's/^/  /'
  elif [ -e .env ]; then
    echo "Attention : .env est un fichier ordinaire, pas un lien symbolique."
    echo "La bascule via ce script n'a jamais ete initialisee (voir CLAUDE.md §4)."
  else
    echo "Aucun .env present."
  fi
}

action="${1:-status}"

case "$action" in
  dev|development)
    file=".env.development"
    ;;
  prod|production)
    file=".env.production"
    ;;
  status)
    show_status
    exit 0
    ;;
  *)
    echo "Usage : bin/switch-env.sh {dev|prod|status}" >&2
    exit 1
    ;;
esac

if [ ! -f "$file" ]; then
  echo "Erreur : $file introuvable." >&2
  exit 1
fi

ln -sfn "$file" .env
echo "Bascule effectuee."
show_status
echo
echo "Pensez a redemarrer 'php artisan serve' si le serveur tournait deja (le .env n'est relu qu'au demarrage)."
