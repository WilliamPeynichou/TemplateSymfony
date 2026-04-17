# Andfield

SaaS de coaching football : gestion d'équipe, composition tactique, notes de match et copilote IA.

## Démarrage rapide

Prérequis : Docker + Docker Compose V2, `openssl` (pour générer les secrets), `make`.

```bash
./bin/bootstrap.sh
```

Le script :

1. Copie `.env.example` vers `.env.local` avec des secrets aléatoires (`APP_SECRET`, `AGENT_SECRET`, `AGENT_INTERNAL_SECRET`).
2. Démarre la stack Docker (`docker compose up -d --build`).
3. Installe les dépendances Composer.
4. Crée la base de données et applique les migrations.

Puis :

```bash
make logs      # suivre les logs
make shell     # shell dans le container PHP
make test      # tous les tests (PHP + Python + JS)
make lint      # PHPStan + PHP-CS-Fixer dry-run
make fix       # corrige automatiquement le style PHP
make secrets   # génère de nouveaux secrets
```

Ouvrir ensuite : [http://localhost](http://localhost).

## Architecture

```
┌─────────────┐    HTTP     ┌─────────────┐    HTTP      ┌─────────────┐
│   Browser   │ ──────────▶ │   Symfony   │ ───────────▶ │   FastAPI   │
│  (sidebar)  │ ◀────────── │   (PHP 8.2) │ ◀─────────── │  LangGraph  │
└─────────────┘             └──────┬──────┘              └──────┬──────┘
                                   │                            │
                                   ▼                            ▼
                            ┌─────────────┐              ┌─────────────┐
                            │   MySQL 8   │              │   Ollama    │
                            │             │              │ (Qwen3 1.7B)│
                            └─────────────┘              └─────────────┘
```

L'agent FastAPI appelle l'API Symfony pour toutes les opérations CRUD, authentifié via un secret partagé (`AGENT_INTERNAL_SECRET` dans le header `X-Agent-Internal-Secret`).

### RAG — recherche sémantique sur les notes

L'agent dispose d'un **vector store SQLite** (`agent/var/rag.sqlite`) alimenté par le hook `AgentRagIndexer` côté Symfony. À chaque création/mise à jour de `MatchNote`, une requête `POST /rag/index` est envoyée à l'agent, qui stocke le chunk + son embedding.

- Embeddings : `nomic-embed-text` via Ollama (gratuit, local). Basculable vers OpenAI via `EMBEDDINGS_BACKEND=openai` + `OPENAI_API_KEY`.
- Tool agent : `search_notes_semantic` accessible via le chat ("quels joueurs ont manqué de rythme ?").
- Réindexation complète : `make` ou `docker compose exec php bin/console app:rag:reindex`.

### Services additionnels

- **Mailpit** (dev) : UI sur [http://localhost:8025](http://localhost:8025) pour voir tous les emails transactionnels.
- **Billing** : `/billing` — mode fallback sans clés Stripe (bascule directe), mode réel après `composer require stripe/stripe-php` + config `STRIPE_*`.
- **Organisations** : `/organizations` — invitations par email avec token expirable 7 jours.
- **Calendrier** : `/teams/{id}/calendar` + export ICS `/teams/{id}/calendar.ics`.
- **Rapports & exports** : `/teams/{id}/export/roster.csv`, `/export/stats.csv`, `/reports/season.pdf`.
- **API publique** : `/api/public/*` authentifiée via header `X-Api-Key` (entité `ApiKey`).
- **PWA** : manifest + service worker actifs dès le layout.

## Documentation

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — Vision produit et phases de livraison.
- [`docs/BACKLOG.md`](docs/BACKLOG.md) — Tickets détaillés par phase.
- [`docs/DOD.md`](docs/DOD.md) — Definition of Done.
- [`AGENTS.md`](AGENTS.md) — Conventions pour l'agent interne et les agents dev.
- [`doc/audit-qualite-securite-agent-ia.md`](doc/audit-qualite-securite-agent-ia.md) — Audit sécurité initial.
- [`doc/agent-ia-coach.md`](doc/agent-ia-coach.md) — Plan de l'agent IA.
- [`doc/session-16-04-2026.md`](doc/session-16-04-2026.md) — Journal session 1 (audit + corrections).
- [`doc/session-17-04-2026.md`](doc/session-17-04-2026.md) — Journal session 2 (MVP complet + RAG vectoriel).

## Production

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d
```

Cette combinaison active :
- PHP en image `frankenphp_prod` sans volume bind.
- Healthchecks sur `/healthz` (Symfony), `mysqladmin ping` (MySQL), `/api/tags` (Ollama), `/health` (agent).
- `restart: always` sur tous les services.

## CI

Workflow GitHub Actions dans `.github/workflows/ci.yml` :
- PHPUnit + PHPStan + PHP-CS-Fixer
- Pytest (agent Python)
- `node --test` (frontend)

## Sécurité

- Secrets obligatoires (`AGENT_SECRET`, `AGENT_INTERNAL_SECRET`), la stack refuse de démarrer sans.
- Voter `TeamVoter` + contrôle d'ownership explicite sur toutes les API sensibles.
- Rate limiting sur `/api/v1/agent/chat` (30 req/min/utilisateur).
- Endpoints `/login`, `/register`, `/pricing`, `/healthz` en `PUBLIC_ACCESS`, tout le reste exige `ROLE_USER`.
