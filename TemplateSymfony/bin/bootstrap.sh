#!/usr/bin/env bash
# Andfield — bootstrap dev environment
# Idempotent : peut être ré-exécuté sans casse.

set -euo pipefail

cd "$(dirname "$0")/.."

log() { printf '\033[36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[33m[warn]\033[0m %s\n' "$*"; }
err() { printf '\033[31m[err]\033[0m %s\n' "$*" >&2; }

# ── 1. Sanity checks ───────────────────────────────────────────
for bin in docker openssl; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    err "Dépendance manquante : $bin"
    exit 1
  fi
done

if ! docker compose version >/dev/null 2>&1; then
  err "Docker Compose V2 requis (docker compose ...)."
  exit 1
fi

# ── 2. .env.local ──────────────────────────────────────────────
if [[ ! -f .env.local ]]; then
  log "Création de .env.local depuis .env.example"
  cp .env.example .env.local

  APP_SECRET=$(openssl rand -hex 16)
  AGENT_SECRET=$(openssl rand -hex 32)
  AGENT_INTERNAL_SECRET=$(openssl rand -hex 32)

  # Remplacements portables (macOS/Linux) via perl
  perl -pi -e "s|^APP_SECRET=.*|APP_SECRET=${APP_SECRET}|" .env.local
  perl -pi -e "s|^AGENT_SECRET=.*|AGENT_SECRET=${AGENT_SECRET}|" .env.local
  perl -pi -e "s|^AGENT_INTERNAL_SECRET=.*|AGENT_INTERNAL_SECRET=${AGENT_INTERNAL_SECRET}|" .env.local

  log ".env.local généré avec des secrets aléatoires."
else
  warn ".env.local déjà présent, je le laisse tel quel."
fi

# ── 3. Build + up ──────────────────────────────────────────────
log "docker compose up -d --build"
docker compose up -d --build

# ── 4. Composer + migrations ───────────────────────────────────
log "composer install"
docker compose exec -T php composer install --no-interaction

log "Création DB + migrations"
docker compose exec -T php bin/console doctrine:database:create --if-not-exists --no-interaction
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# ── 5. Done ────────────────────────────────────────────────────
log "Prêt. Ouvre http://localhost"
log "Pour les logs : make logs"
