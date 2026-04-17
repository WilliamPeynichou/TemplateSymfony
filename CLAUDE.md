# CLAUDE.md — AI Pipeline & Code Search Strategy

## Stratégie de recherche obligatoire

Toute exploration de code suit ce protocole dans l'ordre :

### 1. Ripgrep d'abord (lexical)
Commence toujours par une recherche lexicale rapide :
```bash
rg "pattern" --type ts --type py -l
```
- Utilise `-l` pour lister les fichiers uniquement
- Utilise `-n` pour avoir les numéros de lignes
- Affine avec des patterns précis avant de lire des fichiers

### 2. Recherche sémantique si >2 fichiers pertinents
Si ripgrep remonte plus de 2 fichiers candidats ou si la recherche est conceptuelle :
```bash
OPENAI_API_KEY=$OPENAI_API_KEY python3 .ai-tools/semantic_search.py "ta query" 5
```
Ne lit en détail que les top résultats (score > 0.7 recommandé).

### 3. Lecture ciblée uniquement
- Ne jamais charger tout le repo
- Lire seulement les chunks pertinents identifiés (path + lignes)
- Maximum 3-5 fichiers par session

### 4. Codex uniquement pour
- Review de code ciblé (bugs, edge cases)
- Refactor critique (après validation humaine)
- Génération de tests manquants

Ne jamais appeler Codex pour de l'exploration ou de la compréhension générale.

---

## Commandes prêtes à l'emploi

### Construire l'index sémantique
```bash
OPENAI_API_KEY=sk-... python3 .ai-tools/build_index.py
```
Durée estimée : ~2-5 min selon taille du repo. Rebuilde l'index depuis zéro.

### Recherche sémantique
```bash
OPENAI_API_KEY=sk-... python3 .ai-tools/semantic_search.py "authentification JWT" 5
```
Retourne un JSON avec path, lignes, score, et snippet.

### Review Codex
```bash
./.ai-tools/codex_review.sh "cherche les bugs et edge cases" src/auth/login.ts src/user/service.ts
```

### Ripgrep rapide (lexical)
```bash
# Chercher une fonction
rg "function login" --type ts -n

# Chercher dans les fichiers Python uniquement
rg "def authenticate" --type py -l

# Chercher avec contexte (3 lignes avant/après)
rg "TODO|FIXME|HACK" -C 3 --type ts
```

---

## Optimisation des tokens

- **Minimiser le contexte** : ne charger que les chunks nécessaires
- **Éviter la duplication** : ne pas re-lire un fichier déjà chargé dans la session
- **Snippets courts** : utiliser start_line/end_line pour lire des plages précises
- **Progressive disclosure** : commencer par les signatures/interfaces avant les implémentations

---

## Structure du pipeline

```
.ai-tools/
├── build_index.py     # Indexation sémantique (OpenAI embeddings → SQLite)
├── semantic_search.py # Recherche par similarité cosinus
├── codex_review.sh    # Review/refactor/tests via Codex CLI
├── utils.py           # Fonctions partagées (DB, embeddings, cosine)
└── index.db           # Index généré (gitignored)
```

---

## Dépendances Python requises

```bash
pip3 install openai numpy
```

## Installation Codex CLI (si absent)

```bash
npm install -g @openai/codex
```
