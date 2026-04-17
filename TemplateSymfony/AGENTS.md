# AGENTS.md — Conventions agents IA

Ce fichier documente comment l'agent LangGraph interne **et** les agents de développement (Claude Code, Codex, etc.) doivent se comporter dans ce repo.

## Agent interne (LangGraph)

### Identité

- Rôle : *copilote tactique* pour un coach de football amateur/semi-pro.
- Ton : tutoiement, direct, factuel.
- Périmètre : il n'invente jamais de données, il passe par les outils.

### Outils disponibles

Source : `agent/app/agent/tools.py`.

| Tool                 | Effet de bord ? | But                                                                 |
|----------------------|-----------------|---------------------------------------------------------------------|
| `list_teams`         | non             | Liste les équipes du coach                                          |
| `get_team`           | non             | Détails d'une équipe                                                |
| `create_team`        | oui             | Crée une équipe                                                     |
| `update_team`        | oui             | Met à jour une équipe                                               |
| `delete_team`        | oui             | Supprime une équipe (cascade joueurs)                               |
| `list_players`       | non             | Liste les joueurs d'une équipe                                      |
| `get_player`         | non             | Détails joueur                                                      |
| `create_player`      | oui             | Crée un joueur                                                      |
| `update_player`      | oui             | Met à jour un joueur                                                |
| `delete_player`      | oui             | Supprime un joueur                                                  |
| `get_match_notes`    | non             | Notes de match d'une équipe                                         |
| `create_match_note`  | oui             | Crée une note post-match                                            |
| `list_fixtures`      | non             | Liste les matchs (joués/à venir) d'une équipe                       |
| `get_fixture`        | non             | Détails d'un match                                                  |
| `create_fixture`     | oui             | Crée un match                                                       |
| `update_fixture`     | oui             | Met à jour un match (score, note)                                   |
| `delete_fixture`     | oui             | Supprime un match                                                   |
| `get_team_analysis`  | non             | Synthèse équipe (joueurs + notes récentes)                          |
| `suggest_composition`| non             | Agrège les données pour proposer une compo                          |
| `coaching_report`    | non             | Rapport de coaching structuré                                       |
| `analyze_player`     | non             | Focus sur un joueur                                                 |

### Règles d'usage

1. **Toute modification (effet de bord)** passe par une confirmation explicite (flux `pending_action`).
2. **Jamais de supposition sur `coach_id`** : il vient toujours du token, pas du prompt.
3. **Pas de fan-out** : un seul tool à la fois, séquencé.
4. **Erreurs** : si un outil renvoie `success: false`, l'agent l'annonce au coach et propose une action corrective, il ne réessaie pas en boucle.

## Agents de développement

### Claude Code

Voir `CLAUDE.md` à la racine : workflow `ripgrep` → `semantic_search.py` → `codex_review.sh`.

Points clés :
- `rg` en premier pour les recherches par motif.
- `.ai-tools/semantic_search.py` pour les questions conceptuelles.
- Indexer avant la première recherche : `python3 .ai-tools/build_index.py`.

### Codex / Cursor Agents

- Respecte la DoD (`docs/DOD.md`).
- Ne modifie jamais un fichier sans `Read` préalable.
- Les secrets restent dans `.env.local`.

### Règles de PR pour un agent

1. Branche dédiée `feat/…`, `fix/…`, `chore/…`.
2. 1 ticket `BACKLOG.md` = 1 PR.
3. PR doit mentionner l'ID ticket (`T1.5`) dans le titre.
4. Lancer `make lint && make test` avant push.

## Ajouter un nouvel outil agent

Checklist :

1. Écrire le tool dans `agent/app/agent/tools.py` avec docstring.
2. L'ajouter à `ALL_TOOLS`.
3. Ajouter un test Pytest dans `agent/tests/`.
4. Documenter dans la table ci-dessus.
5. Si l'outil a un effet de bord : vérifier que `pending_action` le prend en charge côté front.
