# Agent IA Coach Football — Documentation de développement

> Rédigé le 16/04/2026 — Plan validé, développement à venir.

---

## Contexte

L'application Symfony est une plateforme de gestion d'équipes de football destinée aux coachs (joueurs, compositions, plans tactiques, matchs). L'objectif est d'ajouter un **agent IA spécialisé coaching football** qui :

- Fait du CRUD sur toutes les entités via API REST
- Analyse les notes post-match du coach (`PlanNotesAfterMatch`) pour proposer des compositions optimales
- Demande validation avant chaque action (mode singulier ou automatique)
- Tourne en local sur Docker (CPU Intel, MacBook Pro)
- Conserve un historique de conversation en base de données MySQL

---

## Stack technique

| Composant | Choix | Justification |
|---|---|---|
| **Modèle LLM** | Qwen3 8B (quantifié Q4) | CPU Intel viable (~10-12 tok/s), excellent tool calling, bon français |
| **Serveur LLM** | LocalAI (latest-cpu) | Drop-in OpenAI API, Docker natif, pas de clé API, auto-download modèle |
| **Framework agent** | LangGraph (Python) | Gestion d'état, workflow multi-étapes, tool calling structuré |
| **Serveur agent** | FastAPI (Python) | Léger, async, idéal pour exposer l'agent via HTTP |
| **Communication** | API REST Symfony ↔ Agent Python | Découplage propre, auth gérée côté Symfony |
| **Mémoire** | MySQL (entités Doctrine) | Cohérent avec l'existant, pas de nouvelle infra |
| **Interface** | Widget sidebar flottant (Twig + JS) | Accessible depuis toutes les pages, non intrusif |

### Évolutivité prévue
- Swap vers GLM-4.7 ou tout modèle plus puissant : changer `LLM_MODEL` dans les variables d'environnement
- Activation GPU : changer l'image LocalAI de `latest-cpu` → `latest-gpu-nvidia-cuda-12`

---

## Architecture

```
Docker Compose
├── php (FrankenPHP + Symfony)     → App principale, API REST /api/v1/
├── database (MySQL 8)             → Données métier + conversations agent
├── localai (Qwen3 8B CPU)         → LLM local, API compatible OpenAI sur :8080
├── agent (Python FastAPI)         → LangGraph agent sur :8001
└── mailer (Mailpit)               → Dev mail
```

```
Coach (navigateur)
  ↕ HTTP (sidebar widget JS)
Symfony php — /api/v1/agent/chat
  ↕ REST HTTP
Agent Python (FastAPI + LangGraph)
  ↕ OpenAI-compatible /v1/chat/completions
LocalAI (Qwen3 8B)
```

---

## Features

### F1 — Infra Docker LocalAI
**Statut** : A faire | **Complexité** : Simple

Ajouter le service `localai` dans `compose.yaml`.

Fichiers concernés :
- `compose.yaml` — ajout du service LocalAI
- `localai/qwen3-8b.yaml` — config du modèle (à créer)

```yaml
# Extrait compose.yaml
localai:
  image: localai/localai:latest-cpu
  ports:
    - "8080:8080"
  volumes:
    - localai_models:/models
  environment:
    - MODELS_PATH=/models
```

Vérification :
```bash
curl http://localhost:8080/v1/models
curl http://localhost:8080/v1/chat/completions \
  -d '{"model":"qwen3-8b","messages":[{"role":"user","content":"hello"}]}'
```

---

### F2 — API REST Symfony
**Statut** : A faire | **Complexité** : Moyenne

Exposer des endpoints CRUD JSON pour que l'agent Python puisse lire et écrire les données.

Fichiers à créer :
- `src/Controller/Api/TeamApiController.php`
- `src/Controller/Api/PlayerApiController.php`
- `src/Controller/Api/CompositionApiController.php`
- `src/Controller/Api/PlanApiController.php`
- `src/Controller/Api/PlanNotesAfterMatchApiController.php`
- `src/Controller/Api/AgentChatController.php` — proxy vers l'agent Python
- `src/Controller/Api/ConversationApiController.php` — historique conversations

Format de réponse unifié :
```json
{ "success": true, "data": {...}, "error": null }
```

Préfixe routes : `/api/v1/`
Auth : session Symfony existante

---

### F3 — Entité PlanNotesAfterMatch
**Statut** : A faire | **Complexité** : Simple

Notes d'observation du coach après chaque match (texte libre). L'agent les lira pour analyser les tendances de performance des joueurs.

Fichiers à créer :
- `src/Entity/PlanNotesAfterMatch.php`
- `src/Repository/PlanNotesAfterMatchRepository.php`
- `src/Form/PlanNotesAfterMatchType.php`
- `src/Controller/PlanNotesAfterMatchController.php`
- `templates/plan_notes_after_match/` (index, new, edit)
- Migration Doctrine

Champs :
| Champ | Type | Description |
|---|---|---|
| `id` | int | Auto-increment |
| `match` | ManyToOne | Match concerné |
| `content` | text | Observations libres du coach |
| `team` | ManyToOne | Équipe concernée |
| `coach` | ManyToOne | User (coach auteur) |
| `createdAt` | DateTimeImmutable | Date de création |

---

### F4 — Agent Python LangGraph (core)
**Statut** : A faire | **Complexité** : Complexe

Le cœur de l'agent : workflow LangGraph, tools CRUD, system prompt coaching football, mode validation.

Structure du service :
```
agent/
  Dockerfile
  requirements.txt
  app/
    main.py              # FastAPI server (point d'entrée HTTP)
    config.py            # Variables d'environnement
    agent/
      graph.py           # Workflow LangGraph (nœuds + transitions)
      state.py           # Définition de l'état (historique, mode validation, contexte équipe)
      tools.py           # Tools CRUD → appels API Symfony
      prompts.py         # System prompt spécialisé coaching football
```

Variables d'environnement :
```env
LLM_BASE_URL=http://localai:8080/v1
LLM_MODEL=qwen3-8b
SYMFONY_API_URL=http://php/api/v1
```

Workflow LangGraph :
1. Recevoir message + contexte (team_id, conversation_id)
2. Charger historique depuis MySQL via API
3. LLM raisonne avec contexte équipe + historique
4. Si action nécessaire → proposer avec détails
5. Mode validation :
   - **Singulier** : attendre confirmation utilisateur (`awaiting_confirmation`)
   - **Automatique** : exécuter directement
6. Exécuter l'action via tool → API REST Symfony
7. Persister les messages → API MySQL
8. Retourner la réponse

Tools disponibles :
- `list_players / get_player / create_player / update_player / delete_player`
- `list_teams / get_team / create_team / update_team / delete_team`
- `list_compositions / create_composition / update_composition / delete_composition`
- `list_plans / create_plan / update_plan / delete_plan`
- `get_match_notes(team_id)` — lecture PlanNotesAfterMatch
- `get_team_analysis(team_id)` — agrégation joueurs + notes

System prompt (base) :
- Rôle : assistant coach IA spécialisé football
- Formations : 4-3-3, 4-4-2, 3-5-2, 4-2-3-1, 5-3-2
- Principes : pressing haut/bas, contre-attaque, possession, transitions défensives/offensives
- Comportement : justifier chaque recommandation, citer les données, respecter le mode validation
- Langue : français

---

### F5 — Mémoire conversationnelle (MySQL)
**Statut** : A faire | **Complexité** : Moyenne

Persister les conversations coach ↔ agent en base de données.

Entités à créer :
- `src/Entity/AgentConversation.php`
- `src/Entity/AgentMessage.php`

**AgentConversation** :
| Champ | Type |
|---|---|
| `id` | int |
| `coach` | ManyToOne → User |
| `team` | ManyToOne → Team (nullable) |
| `title` | string (auto-généré) |
| `createdAt` | DateTimeImmutable |
| `updatedAt` | DateTimeImmutable |

**AgentMessage** :
| Champ | Type |
|---|---|
| `id` | int |
| `conversation` | ManyToOne → AgentConversation |
| `role` | string (user / assistant / tool) |
| `content` | text |
| `toolCalls` | json (nullable) |
| `createdAt` | DateTimeImmutable |

Limite de contexte : 50 derniers messages chargés par requête (configurable).

---

### F6 — Widget sidebar chat
**Statut** : A faire | **Complexité** : Moyenne

Interface de chat flottante accessible depuis toutes les pages Symfony.

Fichiers à créer/modifier :
- `templates/_partials/agent_sidebar.html.twig`
- `assets/js/agent-chat.js`
- `assets/styles/agent-chat.css`
- `templates/base.html.twig` — inclure le widget

Fonctionnalités :
- Bouton flottant bas-droite (icône assistant)
- Sidebar slide depuis la droite au clic
- Zone messages scrollable + input texte
- Toggle **mode validation** (singulier / automatique) visible dans la sidebar
- Boutons **Valider / Refuser** inline quand l'agent propose une action
- Indicateur de chargement pendant le traitement
- Passage automatique du `team_id` de la page courante comme contexte
- Accès aux conversations précédentes

---

### F7 — Analyse & recommandations
**Statut** : A faire | **Complexité** : Complexe

Capacités analytiques avancées basées sur les PlanNotesAfterMatch.

Fichiers à modifier :
- `agent/app/agent/tools.py` — nouveaux tools d'analyse
- `agent/app/agent/prompts.py` — enrichissement du system prompt

Nouveaux tools :
- `analyze_team(team_id)` : charge joueurs + notes → résumé structuré pour le LLM
- `suggest_composition(team_id, formation?)` : propose une compo justifiée poste par poste
- `coaching_report(team_id)` : rapport points forts/faibles, forme/méforme, axes de progression

Enrichissement system prompt :
- Critères de sélection : forme récente, complémentarité, pied fort, gabarit, poste
- Détection de patterns dans les notes (ex: mention répétée d'un problème → signal de méforme)
- Principes tactiques par formation pour justifier les choix

---

## Ordre d'implémentation

```
Étape 1 (parallèle) : F1 + F2 + F3
  └── F1 : Infra Docker LocalAI
  └── F2 : API REST Symfony
  └── F3 : Entité PlanNotesAfterMatch

Étape 2 : F4 — Agent Python LangGraph (core)
  └── dépend de F1 (LocalAI) + F2 (API Symfony)

Étape 3 (parallèle) : F5 + F6
  └── F5 : Mémoire conversationnelle MySQL
  └── F6 : Widget sidebar chat

Étape 4 : F7 — Analyse & recommandations
  └── dépend de F3 + F4 + F5
```

---

## Journal de développement

| Date | Feature | Action | Notes |
|---|---|---|---|
| 16/04/2026 | — | Plan validé | Architecture et découpage finalisés |
| 16/04/2026 | F1 | ✅ Terminé | compose.yaml + localai/qwen3-8b.yaml |
| 16/04/2026 | F2 | ✅ Terminé | 17 endpoints REST API Symfony |
| 16/04/2026 | F3 | ✅ Terminé | Entité MatchNote + CRUD + migration |
| 16/04/2026 | F4 | ✅ Terminé | Agent Python LangGraph + FastAPI |
| 16/04/2026 | F5 | ✅ Terminé | Mémoire MySQL + API conversations |
| 16/04/2026 | F6 | ✅ Terminé | Widget sidebar flottant |
| 16/04/2026 | F7 | ✅ Terminé | suggest_composition, coaching_report, analyze_player |
| 16/04/2026 | Audit | ✅ Correctifs lancés | Verrous P0/P1 + durcissement P2 (auth interne, authz objet, pending_action persistant) |

---

## Correctifs post-audit (securite et robustesse)

Suite a l'audit qualite/securite, les decisions techniques suivantes ont ete retenues:

- Le service `agent` n'est plus expose publiquement par Docker Compose
- Les endpoints FastAPI sensibles (`/chat`, `/conversations`) exigent un secret interne (`X-Agent-Internal-Secret`)
- Les controles d'acces objet sont renforces sur conversations, joueurs et notes
- L'etat `pending_action` est persiste en base cote conversation pour fiabiliser le mode `singular`
- Les appels `DELETE` de l'agent utilisent desormais la meme authentification que `GET/POST/PATCH`

Objectif: garder une architecture locale simple tout en supprimant les failles critiques identifiees.

