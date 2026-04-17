# Andfield — Roadmap produit

> SaaS de coaching football pour entraîneurs amateurs et semi-pros.
> Positionnement : outil opérationnel (effectif, compo, match, notes) + copilote IA.

## Vision produit

Un coach amateur passe plus de temps à **organiser** (effectif, compo, carnet) qu'à **coacher**.
Andfield lui redonne ce temps : une seule app pour l'effectif, la tactique, les notes de match, et un agent IA qui digère tout ça.

Principe directeur : **chaque écran doit faire gagner 5 minutes à un coach** sinon il est retiré.

## Phases de livraison

### Phase 0 — Foundation (fait)

- Auth email/mot de passe (`User`, `ROLE_COACH`).
- CRUD équipes / joueurs / plans / compositions / notes de match.
- Agent IA local (Ollama + LangGraph) avec outils CRUD + analyse.
- Sécurité : Voter `TeamVoter`, authentification inter-service, secrets via env.
- Tests PHPUnit/Pytest/node:test de base.

### Phase 1 — SaaS-ready (en cours — livré par ce lot)

- [x] Docs de pilotage (`ROADMAP.md`, `BACKLOG.md`, `DOD.md`, `AGENTS.md`).
- [x] CI GitHub Actions : PHPUnit + Pytest + `node:test` + PHPStan + PHP-CS-Fixer.
- [x] Qualité : `phpstan.neon`, `.php-cs-fixer.dist.php`, `Makefile` DX.
- [x] Onboarding : `.env.example` complet, `bin/bootstrap.sh` clone→up en 1 commande.
- [x] Landing pricing + rate-limiting des endpoints sensibles.
- [x] Compose prod (`compose.prod.yaml`) + healthchecks services.
- [x] Domaine `Fixture` (match joué/à venir) : entity + API + agent tool + tests.

### Phase 2 — Produit MVP (livré ce lot)

- [~] Stripe checkout + webhooks : **mode fallback OK** ; mode Stripe réel nécessite `composer require stripe/stripe-php` et le décommentage du bloc SDK.
- [x] Multi-organisation : entité `Organization`, membership, invitation par email avec token.
- [x] Emails transactionnels : service `TransactionalMailer` + 4 templates + Mailpit en dev.
- [x] Stats joueur : entité `PlayerMatchStat` + API CRUD + agrégation saison.
- [x] Calendrier : entité `TrainingSession`, vue unifiée matchs+entraînements, export ICS.
- [x] Export CSV : roster + stats via `StreamedResponse`.
- [~] Rapports PDF : `PdfReportService` **dual mode** (Dompdf si installé, fallback HTML sinon).

### Phase 3 — IA avancée (livré ce lot pour le RAG)

- [x] **RAG sur les notes de match** : vector store SQLite maison + Ollama embeddings + indexation auto + tool agent `search_notes_semantic`.
- [~] Suggestions proactives : Messenger `WeeklyInsightMessage` + handler + commande. Base fonctionnelle ; à enrichir avec l'IA narrative.
- [ ] Génération entraînement personnalisé : modèle `TrainingSession` prêt, tool agent à écrire.
- [~] Vocal → transcription Whisper : endpoint FastAPI `POST /voice/transcribe` en stub, prêt à recevoir `faster-whisper`.

### Phase 4 — Scale & écosystème (base posée)

- [~] PWA offline-first : manifest + service worker actifs ; IndexedDB et Background Sync à faire.
- [x] API publique : `ApiKey` + `ApiKeyAuthenticator` + firewall dédié. OpenAPI auto via Nelmio prêt en `.example`.
- [ ] Intégrations tierces (Google Calendar, WhatsApp).
- [~] Observabilité Sentry : config `.example` + `SENTRY_DSN` dans `.env.example`. OpenTelemetry reporté.
- [~] I18n FR/EN : config `translation.yaml` + 2 fichiers de clés. Extraction depuis les templates à faire.

> Légende : `[x]` fait · `[~]` partiellement fait (détails dans `BACKLOG.md`) · `[ ]` à faire.

## Principes transverses

- **Sécurité** : secrets jamais commit, voters systématiques, `X-Agent-Internal-Secret` obligatoire.
- **Tests** : toute nouvelle route publique a au moins un test (smoke + authz).
- **DX** : `make up` doit suffire pour démarrer. Toute règle imposée par un outil doit être auto-exécutable (`make fix`).
- **Coût IA** : LLM local (Ollama) par défaut, OpenAI/Anthropic en option, avec compteur et rate-limit.

## Indicateurs de succès

| Étape            | KPI principal                                | Cible MVP |
|------------------|----------------------------------------------|-----------|
| Activation       | % users créant 1 équipe + 1 joueur sous 10 min | 60 %     |
| Engagement       | Notes de match/semaine/coach actif           | 2         |
| Usage IA         | Messages agent/utilisateur actif/semaine     | 6         |
| Rétention J30    | Comptes qui reviennent à J30                 | 40 %      |
| Conversion payée | Free → plan payant                           | 5 %       |
