# Andfield — Backlog détaillé

Format : `T<phase>.<n> — Titre` · effort estimé · description · DoD (si spécifique sinon voir `DOD.md`).

## Phase 1 — SaaS-ready (en cours dans ce lot)

### T1.1 — Docs de pilotage · S

Créer `ROADMAP.md`, `BACKLOG.md`, `DOD.md`, `AGENTS.md`.
- DoD : 4 fichiers commités, liés depuis `README.md`.

### T1.2 — CI GitHub Actions · M

Workflow qui lance :
- `composer install` + `phpunit`
- `pip install -r agent/requirements.txt` + `pytest`
- `node --test` sur les tests JS
- `phpstan analyse` + `php-cs-fixer --dry-run`
- DoD : badge vert sur `main`, PR bloquée si un job échoue.

### T1.3 — Qualité PHP statique · S

- `phpstan.neon` niveau 6 minimum sur `src/`.
- `.php-cs-fixer.dist.php` aligné sur PSR-12 + rules Symfony.
- `Makefile` : `make test`, `make lint`, `make fix`, `make up`, `make down`, `make shell`.
- DoD : `make lint` passe à vert sur `main`.

### T1.4 — Onboarding dev · S

- `.env.example` complet (APP_SECRET, DATABASE_URL, AGENT_SECRET, AGENT_INTERNAL_SECRET, MAILER_DSN).
- `bin/bootstrap.sh` : génère secrets aléatoires, copie `.env.example` → `.env.local`, lance `docker compose up -d --build`.
- Section "Démarrage rapide" dans `README.md`.
- DoD : nouveau développeur peut `git clone` + `./bin/bootstrap.sh` et être up en < 5 minutes.

### T1.5 — Rate limiting · M

- Configuration `rate_limiter.yaml` : `anonymous` (5 req/min), `auth` (10 req/min), `agent_chat` (30 req/min par user).
- Intégration dans `AgentChatController`, `SecurityController`, `RegistrationController`.
- DoD : un burst de 100 requêtes/s sur `/login` rend 429, tracé dans les logs.

### T1.6 — Landing pricing · S

- `PricingController` + template Twig avec 3 plans (Free, Club, Club+).
- Lien depuis `home/index.html.twig`.
- Les boutons pointent vers `/register?plan=...` (Stripe arrive en Phase 2).
- DoD : page accessible en anonyme, mobile-friendly, pas de JS bloquant.

### T1.7 — Compose prod · M

- `compose.prod.yaml` : `php` en image prod (pas de volume bind), `database` avec volume persistant, healthchecks `php/healthz`, `database` ping, `ollama /api/tags`.
- Documentation dans `docs/DEPLOY.md` (sortie de ce lot, à faire).
- DoD : `docker compose -f compose.yaml -f compose.prod.yaml up -d` démarre sans erreur.

### T1.8 — Entité Fixture · M

- Entité `Fixture` : date, adversaire, domicile/extérieur, score, compétition, référence à `Team`.
- Migration, repository, `FixtureApiController` CRUD avec voter `COACH`.
- Outil agent `create_fixture`, `list_fixtures`, `fixture_report`.
- DoD : CRUD testé (PHPUnit entité + smoke API), tool agent testé (Pytest).

## Phase 2 — Produit MVP

### T2.1 — Stripe Checkout · L — ⚠️ Partiellement livré

- Entités `SubscriptionPlan` et `Subscription` + migration + repositories.
- `BillingController` : `/billing`, `/billing/checkout/{slug}` (CSRF), `/billing/webhook`.
- `StripeBillingService` avec **mode fallback** quand les clés Stripe ne sont pas configurées : bascule immédiate côté DB sans paiement (utile en dev).
- Template `billing/index.html.twig`.
- **À compléter pour la prod** :
  - `composer require stripe/stripe-php`.
  - Décommenter le bloc `Stripe::setApiKey(...)` dans `StripeBillingService::createCheckoutSession`.
  - Vérifier la signature webhook avec `\Stripe\Webhook::constructEvent`.
  - Provisionner les `stripe_price_id` dans la table `subscription_plan` via l'admin Stripe.
- DoD initial atteint : paiement test en mode fallback crée la `Subscription`. DoD prod non-atteint tant que le SDK n'est pas installé.

### T2.2 — Organizations & invitations · L — ✅

- Entités `Organization`, `OrganizationMembership`, `OrganizationInvitation` + migration.
- `OrganizationVoter` (`ORG_MEMBER`, `ORG_OWNER`).
- `OrganizationController` : list, create, show, invite (owner-only, CSRF via Symfony), accept (match email + token).
- Invitations envoyées par email (`TransactionalMailer::sendOrganizationInvitation`).
- **Non inclus (volontairement reporté)** : rattachement des `Team` existantes à une `Organization` (champ `organization_id` nullable à ajouter quand on consolide la migration des données).

### T2.3 — Emails transactionnels · M — ✅

- `TransactionalMailer` : welcome, password reset, org invitation, weekly insight.
- Templates Twig HTML + text.
- Mailpit dans `compose.yaml` (UI http://localhost:8025), `MAILER_DSN=smtp://mailpit:1025`.

### T2.4 — Stats joueur & match · L — ✅

- Entité `PlayerMatchStat` + migration + repository (avec `aggregateForPlayer`).
- `PlayerMatchStatApiController` : CRUD sous `/api/v1/teams/{teamId}/players/{playerId}/stats`.
- UI à brancher (formulaire d'édition inline) en phase suivante.

### T2.5 — Calendrier · M — ✅

- Entité `TrainingSession` + migration + repository.
- `CalendarController` : vue HTML + formulaire new training + export ICS.
- `IcsExporter` : implémentation RFC 5545 sans dépendance externe.

### T2.6 — Export CSV · S — ✅

- `ExportController` : `/teams/{id}/export/roster.csv` et `/export/stats.csv` via `StreamedResponse`.

### T2.7 — Rapports PDF · M — ⚠️ Partiellement livré

- `PdfReportService` : **double mode**. Si `dompdf/dompdf` est installé → PDF natif. Sinon → fallback HTML imprimable.
- Template `reports/season.html.twig`.
- Pour activer le mode PDF pur : `composer require dompdf/dompdf`.

## Phase 3 — IA avancée

### T3.1 — RAG sur notes de match · L — ✅

- Vector store SQLite custom (`agent/app/rag/store.py`) avec similarité cosinus numpy — suffisant pour < 100 k chunks/coach, migrer vers pgvector à plus grande échelle.
- Embeddings pluggables (Ollama `nomic-embed-text` par défaut, OpenAI optionnel).
- Indexer (`agent/app/rag/indexer.py`).
- Endpoints FastAPI : `POST /rag/index`, `POST /rag/index-batch`, `POST /rag/search`, `GET /rag/stats`.
- Tool agent `search_notes_semantic` exposé dans `ALL_TOOLS`.
- Hook auto côté Symfony : `AgentRagIndexer` appelé dans `MatchNoteApiController` create/update.
- Commande `php bin/console app:rag:reindex` pour backfill.
- Tests Pytest sur le store (`agent/tests/test_rag_store.py`).

### T3.2 — Suggestions proactives · M — ⚠️ Scaffolding

- `WeeklyInsightMessage` + `WeeklyInsightHandler` (Messenger) + `WeeklyInsightDispatchCommand`.
- Version basique : liste matchs à venir. À enrichir en branchant l'agent IA pour générer un résumé narratif.
- Ajouter une cron ou un Scheduler pour exécuter hebdomadairement.

### T3.3 — Gén. entraînement personnalisé · L — ⏳ Reporté

- Modèle `TrainingSession` en place (via T2.5), l'entité stocke déjà un champ `plan`.
- L'outil agent `generate_training_plan` reste à écrire — il devra combiner `coaching_report` existant + `search_notes_semantic` nouveau.

### T3.4 — Vocal → note de match · L — ⚠️ Stub

- Endpoint `POST /voice/transcribe` ajouté dans FastAPI, accepte un fichier audio, renvoie un placeholder.
- Pour activer Whisper en local : installer `openai-whisper` (lourd) ou brancher `faster-whisper`. Pipeline : audio → transcription → `create_match_note`.

## Phase 4 — Scale & écosystème

### T4.1 — PWA offline · L — ⚠️ Base posée

- `public/manifest.webmanifest`.
- `public/sw.js` : install cache shell, network-first pour les pages (hors `/api`), cache-first pour les assets.
- `PwaController` avec route `/offline`.
- Layout `app.html.twig` enregistre le service worker.
- **À compléter** : icônes `/icons/icon-192.png` et `/icons/icon-512.png` à produire, IndexedDB pour l'effectif offline, Background Sync pour les notes de match.

### T4.2 — API publique · M — ✅ (sans OpenAPI auto)

- Entité `ApiKey` (hash + prefix, jamais en clair).
- `ApiKeyAuthenticator` sur firewall dédié `api_public`.
- Contrôleur exemple `PublicTeamsController` (`GET /api/public/teams`).
- Config Nelmio en `.example` (à activer après `composer require nelmio/api-doc-bundle`).

### T4.3 — Observabilité · M — ⚠️ Config prête

- `config/packages/sentry.yaml.example` documenté.
- `SENTRY_DSN` dans `.env.example`.
- Activer via `composer require sentry/sentry-symfony` puis renommer le fichier.
- OpenTelemetry non inclus (trop de deps et de setup pour ce lot).

### T4.4 — I18n · M — ✅ Base

- `translation.yaml` configuré avec locales `fr` et `en`, fallback `fr`.
- `translations/messages.fr.yaml` et `messages.en.yaml` avec clés de navigation, pricing, common.
- **À faire ensuite** : extraction des chaînes depuis les templates Twig existants (`php bin/console translation:extract en --force --domain=messages`) et traduction manuelle.

## Dette à résorber (transverse)

- [ ] Tests E2E (Panther ou Playwright) sur les parcours critiques : register → create team → invite compo → agent chat.
- [ ] Typehints stricts partout (`declare(strict_types=1);` ajouté dans chaque fichier `src/`).
- [ ] Harmoniser les formats de dates dans les API (toujours ISO 8601).
- [ ] Cloisonner logs sensibles : jamais logger `Authorization` ou `X-Agent-Internal-Secret`.
