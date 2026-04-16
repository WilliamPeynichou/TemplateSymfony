# Audit qualite et securite - Agent IA Coach Football

Date: 16/04/2026

## Perimetre
Ce compte rendu consolide l'analyse de ce qui a ete concu et implemente pour l'integration de l'agent IA Coach Football, a partir de:

- `doc/agent-ia-coach.md`
- `doc/session-16-04-2026.md`
- la lecture des fichiers Symfony, Python et front associes

L'audit porte sur:

- la coherence entre le plan initial et l'implementation reelle
- la qualite de code et la maintenabilite
- la robustesse fonctionnelle
- la securite applicative et operationnelle

## Resume executif
L'integration realisee constitue une base de travail solide pour un prototype local:

- architecture decouplee entre Symfony, agent Python et LLM local
- endpoints REST homogenes
- memoire conversationnelle en base
- widget de chat integre a l'application
- premier niveau de raisonnement metier centre sur le coaching football

En revanche, l'etat actuel ne permet pas de considerer l'ensemble comme pret pour un usage sensible ou multi-utilisateur. Plusieurs points critiques ont ete identifies, en particulier sur l'authentification inter-services, le controle d'acces objet et la fiabilite du mode de validation manuelle.

Conclusion:

- maturite actuelle: prototype local avance
- qualite globale: moyenne a fragile
- securite globale: insuffisante
- priorite immediate: corriger les points P0 avant toute exposition autre que locale

## Ce qui a ete fait
Le plan initial decrit dans `doc/agent-ia-coach.md` a bien ete transforme en implementation concrete, avec quelques ecarts structurants:

### Architecture retenue
- Symfony reste l'application principale et expose l'API REST
- un service Python `FastAPI + LangGraph` porte la logique agent
- le LLM local retenu en final est `Ollama + qwen3:1.7b`
- les conversations sont persistees en MySQL via des entites dediees
- une sidebar de chat est integree dans l'interface Symfony

### Ecarts entre plan et implementation
- `LocalAI` prevu initialement a ete remplace par `Ollama`
- `PlanNotesAfterMatch` a ete concretise sous la forme de l'entite `MatchNote`
- l'agent implemente des tools d'analyse (`suggest_composition`, `coaching_report`, `analyze_player`)
- le mode de validation "singular" a ete introduit mais reste incomplet dans son fonctionnement reel

### Valeur apportee
- centralisation des interactions coach/agent
- capacite de consultation et de modification des donnees via tools
- exploitation des notes post-match pour enrichir les recommandations
- base documentaire deja utile pour poursuivre l'industrialisation

## Evaluation globale
### Notation sur 10
| Axe | Note | Commentaire |
|---|---:|---|
| Architecture | 7 | Decouplage propre et pragmatique pour une v1 locale |
| Lisibilite du code | 6 | Structure assez claire, conventions globalement homogenes |
| Maintenabilite | 5 | Separation correcte des couches, mais manque de garde-fous |
| Robustesse fonctionnelle | 3 | Plusieurs flux critiques semblent incomplets ou cassables |
| Testabilite | 2 | Absence quasi totale de tests cibles |
| Securite auth/authz | 2 | Defauts graves sur l'authentification et le controle d'acces objet |
| Securite operationnelle | 3 | Exposition reseau et secrets insuffisamment durcis |

### Verdict
- qualite globale: `4/10`
- securite globale: `2/10`
- recommandation: ne pas considerer cette brique comme prete pour la production sans corrections structurantes

## Points forts
### 1. Architecture fonctionnelle claire
Le decouplage entre Symfony, l'agent Python et le moteur LLM local est pertinent. Il permet de faire evoluer separement:

- l'application metier
- le moteur conversationnel
- le fournisseur de modele local

### 2. API unifiee et exploitable
Le format de reponse uniforme `{success, data, error}` simplifie le dialogue entre les couches et limite la dispersion des conventions.

### 3. Bonne orientation produit
Le choix de persister les conversations, de contextualiser par equipe et d'exposer le widget partout dans l'app va dans le bon sens du point de vue usage.

### 4. Documentation utile
Les deux documents existants permettent de comprendre:

- les hypotheses initiales
- les arbitrages techniques
- les problemes rencontres
- l'architecture finale retenue

## Constats critiques
### P0 - Exposition directe du service agent sans authentification forte
Le service FastAPI est expose sur `:8001` et le endpoint `/chat` ne requiert qu'un header `X-Coach-Id`. En l'etat, un appel direct au service agent peut usurper l'identite d'un coach si le service est accessible.

Impact:

- usurpation d'identite applicative
- acces non autorise aux conversations et aux donnees du coach
- risque tres eleve si l'environnement sort du strict local isole

Niveau: critique

### P0 - Controle d'acces incomplet sur les conversations
Les routes de conversation manipulent directement une `AgentConversation` par identifiant, sans verifier explicitement qu'elle appartient a l'utilisateur courant.

Impact:

- lecture de conversations d'un autre coach
- ajout de messages dans une conversation tierce
- modification du titre d'une conversation tierce

Niveau: critique

### P0 - Controle d'acces incomplet sur les joueurs et notes
Les endpoints `Player` et `MatchNote` verifient l'acces au `teamId` de l'URL, mais pas l'appartenance reelle de l'objet charge (`Player` ou `MatchNote`) a cette equipe.

Impact:

- lecture ou modification d'objets hors perimetre
- contournement logique via combinaison `teamId` legitime + `id` etranger

Niveau: critique

## Constats majeurs
### P1 - Flux de validation manuelle incomplet
Le mode `singular` repose sur `pending_action`, mais cet etat n'est pas persiste ni recharge de facon fiable entre deux requetes. La confirmation utilisateur risque donc de ne pas pouvoir rejouer l'action attendue.

Impact:

- mode manuel non fiable
- comportement incoherent entre proposition et execution
- risque de perte de confiance dans l'agent

Niveau: majeur

### P1 - Incoherence entre le service memory Python et l'API Symfony
La couche Python tente de recuperer une conversation via `GET /conversations/{id}`, alors que ce endpoint n'apparait pas dans le controller lu.

Impact:

- reprise de conversation fragile ou non fonctionnelle
- comportement implicite difficile a diagnostiquer

Niveau: majeur

### P1 - Suppressions agent potentiellement non authentifiees
La fonction `_delete()` des tools Python n'envoie pas le header `Authorization` utilise partout ailleurs pour l'agent.

Impact:

- erreurs 401 sur certaines actions
- incoherence de comportement entre les tools

Niveau: majeur

### P1 - Absence de tests automatises utiles
La base PHPUnit existe, mais aucun vrai test cible n'est present dans `tests/`. Cote Python, aucun test n'a ete trouve pour le graphe, les tools ou la memoire.

Impact:

- regressions faciles
- corrections futures plus risquees
- dette de qualite immediate

Niveau: majeur

## Constats secondaires
### P2 - Durcissement operationnel insuffisant
Les ports `3306`, `8001` et `11434` sont publies et les valeurs par defaut des secrets restent faibles pour autre chose qu'un usage local maitrise.

### P2 - Risque XSS dans le widget
Le widget injecte des fragments via `innerHTML` pour afficher la carte d'action. Si des donnees non nettoyees remontent dans les arguments, il existe un risque d'injection HTML/JS.

### P2 - Documentation partiellement desynchronisee
`doc/agent-ia-coach.md` reste utile comme document de cadrage, mais il ne reflete plus exactement la solution finale. Le document de session est aujourd'hui plus proche de l'etat reel.

## Evaluation qualite
### Criteres retenus
L'evaluation qualite repose sur les axes suivants:

- clarte de l'architecture
- cohesion des responsabilites
- robustesse des flux critiques
- capacite a diagnostiquer les erreurs
- presence de tests et garde-fous
- facilite d'evolution

### Appreciation
La qualite de code est correcte sur la forme, mais insuffisante sur la maitrise des cas limites et sur la securisation des flux sensibles. La solution est lisible, mais pas encore solidifiee.

## Evaluation securite
### Criteres retenus
L'evaluation securite couvre:

- authentification inter-services
- autorisation sur les ressources metier
- exposition reseau
- gestion des secrets
- surface d'attaque cote front

### Appreciation
La securite actuelle est insuffisante des qu'on sort du contexte de developpement strictement local. Les failles d'autorisation et l'absence de protection forte du service agent doivent etre traitees en priorite absolue.

## Plan de remediaton recommande
### P0 - A corriger immediatement
1. Restreindre l'acces au service agent:
   - supprimer l'exposition publique de `8001` si non necessaire
   - ou imposer une authentification inter-service forte
2. Ajouter des verifications d'appartenance sur:
   - `AgentConversation`
   - `Player`
   - `MatchNote`
3. Revoir la surface d'appel directe de l'agent pour empecher l'usurpation par `X-Coach-Id`

### P1 - A corriger avant toute extension fonctionnelle
1. Corriger le flux `pending_action` du mode manuel
2. Aligner la couche `memory.py` avec les routes Symfony reelles
3. Corriger l'authentification des requetes `DELETE`
4. Centraliser davantage la validation des entrees et des erreurs API

### P2 - A planifier
1. Durcir les secrets et la configuration Docker
2. Limiter les ports exposes aux besoins reels
3. Eviter `innerHTML` quand des donnees dynamiques sont injectees
4. Resynchroniser la documentation de cadrage avec l'implementation finale

## Strategie de tests recommandee
### Symfony
- tests d'acces et d'autorisation sur toutes les routes `/api/v1/*`
- tests d'isolation entre coachs
- tests de validation des payloads
- tests de conversation et de persistance des messages

### Python
- tests unitaires sur les tools
- tests sur `memory.py`
- tests du graphe LangGraph pour:
  - lecture simple
  - tool call de lecture
  - tool call d'ecriture en mode `auto`
  - proposition + confirmation en mode `singular`

### Front
- tests du widget:
  - chargement des conversations
  - affichage des messages
  - gestion des actions en attente
  - gestion des erreurs reseau

## Recommandation finale
Le travail realise est serieux pour une premiere iteration et montre une bonne capacite de conception et d'integration. Le projet a deja de la valeur en environnement local de demonstration.

En revanche, avant d'aller plus loin, il faut traiter la dette de securite et de robustesse. La priorite n'est pas d'ajouter des fonctionnalites IA, mais de fiabiliser:

- l'authentification
- l'autorisation
- le mode de validation
- les tests d'integration

Sans ces corrections, l'agent reste une preuve de concept evoluee plutot qu'un composant applicatif fiable.

## Statut du document
Type d'audit: revue documentaire et lecture de code

Limites:

- audit realise sans execution de la suite applicative complete
- audit realise sans tests automatiques executes pendant la revue
- conclusions basees sur les fichiers consultes a date
