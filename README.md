# TemplateSymfony

> Symfony project template.

---

## AI Pipeline

Pipeline de recherche et d'analyse de code combinant ripgrep, embeddings sémantiques, et Codex CLI.

### Installation

**Prérequis système :**
```bash
# macOS — ripgrep (si absent)
brew install ripgrep

# Python deps
pip3 install openai numpy

# Codex CLI (optionnel)
npm install -g @openai/codex
```

**Variable d'environnement requise :**
```bash
export OPENAI_API_KEY=sk-...
```

### Usage

#### 1. Construire l'index sémantique
```bash
python3 .ai-tools/build_index.py
```
Lance l'indexation récursive du repo. Génère `.ai-tools/index.db`.
- Ignore : `node_modules`, `.git`, `dist`, `build`, `venv`, `__pycache__`
- Cible : `.ts`, `.tsx`, `.js`, `.jsx`, `.py`, `.go`, `.rs`, `.java`
- Chunks de ~150 lignes avec embeddings OpenAI `text-embedding-3-small`

#### 2. Recherche sémantique
```bash
python3 .ai-tools/semantic_search.py "gestion des erreurs HTTP" 5
```
Retourne un JSON trié par score de similarité :
```json
[
  {
    "path": "src/exception/http.filter.ts",
    "start_line": 12,
    "end_line": 45,
    "score": 0.87,
    "snippet": "..."
  }
]
```

#### 3. Review Codex
```bash
./.ai-tools/codex_review.sh "analyse bugs et edge cases" src/auth/login.ts
```
Produit un rapport structuré : bugs, edge cases, refactor, tests manquants.

#### 4. Recherche lexicale (ripgrep)
```bash
rg "AuthGuard" --type ts -n
rg "TODO|FIXME" -C 2
```

### Limites

- L'index doit être **reconstruit manuellement** après des changements majeurs
- Les embeddings OpenAI coûtent des tokens API (faible coût avec `text-embedding-3-small`)
- Codex CLI requiert une clé API OpenAI séparée avec accès activé
- La recherche cosinus est en mémoire — tient sur des repos jusqu'à ~50k chunks
- Python 3.9 minimum requis

### Améliorations possibles

- Indexation incrémentale (hash des fichiers pour détecter les changements)
- Index local sans API OpenAI avec `sentence-transformers` (HuggingFace, offline)
- Interface CLI unifiée (`ai-search` wrappant ripgrep + sémantique)
- Intégration VSCode pour recherche inline
- Cache des embeddings pour éviter les re-calculs
