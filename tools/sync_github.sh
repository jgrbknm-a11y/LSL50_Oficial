#!/usr/bin/env bash
# LSL50 — GitHub remote sync (one-shot push pipeline)
#
# Usage:
#   ./tools/sync_github.sh https://github.com/USUARIO/LSL50_Official_Project.git
#   GITHUB_REPO_URL=https://github.com/USUARIO/repo.git ./tools/sync_github.sh
#
# Requires: git, SSH key or HTTPS credentials for GitHub

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

REPO_URL="${1:-${GITHUB_REPO_URL:-}}"

if [[ -z "$REPO_URL" ]]; then
  echo "ERROR: Falta la URL del repositorio remoto."
  echo ""
  echo "Uso:"
  echo "  ./tools/sync_github.sh https://github.com/USUARIO/LSL50_Official_Project.git"
  echo ""
  echo "Crear repo vacío en GitHub (2 min):"
  echo "  1. https://github.com/new"
  echo "  2. Nombre sugerido: LSL50_Official_Project"
  echo "  3. Privado recomendado · NO marcar README/.gitignore/license"
  echo "  4. Copiar la URL HTTPS o SSH y ejecutar este script"
  exit 1
fi

BRANCH="${GITHUB_BRANCH:-main}"

echo "=== LSL50 GitHub Sync ==="
echo "Repo:   $REPO_URL"
echo "Branch: $BRANCH"
echo ""

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: No es un repositorio git."
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "ERROR: Working tree con cambios sin commit. Commit primero."
  git status -sb
  exit 1
fi

if git remote get-url origin >/dev/null 2>&1; then
  CURRENT="$(git remote get-url origin)"
  if [[ "$CURRENT" != "$REPO_URL" ]]; then
    echo "Actualizando origin: $CURRENT -> $REPO_URL"
    git remote set-url origin "$REPO_URL"
  else
    echo "origin ya configurado: $CURRENT"
  fi
else
  echo "Agregando origin: $REPO_URL"
  git remote add origin "$REPO_URL"
fi

echo ""
echo "Ejecutando tests pre-push..."
php LSL50_Website_System/tools/test_rebuild_rules.php
php LSL50_Website_System/tools/test_stats_public.php

echo ""
echo "Push a origin/$BRANCH..."
git push -u origin "$BRANCH"

echo ""
echo "OK — Checkpoint publicado en GitHub."
git remote -v
git log -1 --oneline
