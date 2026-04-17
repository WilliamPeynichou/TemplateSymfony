# Andfield — Definition of Done

Un ticket n'est *done* que si **toutes** les cases applicables sont cochées.

## Code

- [ ] Le code compile (`composer dump-autoload`, `python -m py_compile`, `node --check`).
- [ ] `declare(strict_types=1);` en tête de tout nouveau fichier PHP.
- [ ] Typehints complets sur la signature publique (args + return).
- [ ] Pas de `var_dump`, `dd`, `print_r`, `console.log` de debug laissés.
- [ ] Pas de secret en clair dans le code ni dans un `.env` commité.
- [ ] Commentaires expliquent le *pourquoi*, jamais le *quoi*.

## Tests

- [ ] Au moins un test unitaire pour la logique métier ajoutée/modifiée.
- [ ] Pour toute nouvelle route HTTP : un test smoke (200/302 attendu) + un test d'authz (403/404 quand un autre coach tente d'accéder).
- [ ] `vendor/bin/phpunit` reste vert.
- [ ] `pytest agent/tests/` reste vert.
- [ ] `node --test assets/tests/` reste vert.

## Sécurité

- [ ] Toutes les entrées utilisateur sont validées ou échappées.
- [ ] Les routes sensibles sont protégées par `#[IsGranted(...)]` ou un voter explicite.
- [ ] Les endpoints IDOR-sensibles vérifient l'ownership (`$team->getCoach() === $user`).
- [ ] Les erreurs API ne fuitent pas de stack trace en prod (`APP_ENV=prod` testé localement).
- [ ] Les logs ne contiennent pas `Authorization`, `X-Agent-Internal-Secret`, `password`.

## Qualité

- [ ] `make lint` passe (PHPStan + PHP-CS-Fixer dry-run).
- [ ] `make fix` n'a plus rien à corriger.
- [ ] Les migrations Doctrine sont réversibles (méthode `down()` implémentée).
- [ ] Pas de `TODO` / `FIXME` sans ticket associé dans `BACKLOG.md`.

## Documentation

- [ ] Si l'API publique change : section correspondante dans `docs/API.md` (à créer quand il existe des endpoints publics).
- [ ] Si un nouvel outil agent est ajouté : docstring claire + entrée dans `AGENTS.md`.
- [ ] Si une nouvelle variable d'environnement est nécessaire : ajoutée à `.env.example` avec commentaire.
- [ ] Le ticket dans `BACKLOG.md` passe de `[ ]` à `[x]` dans le commit qui le clôt.

## Observabilité & DX

- [ ] Les erreurs exploitables sont loggées avec un message actionnable.
- [ ] Toute commande manuelle documentée dans `Makefile`.
- [ ] Si la feature impacte le démarrage : `./bin/bootstrap.sh` reste fonctionnel.

## Accessibilité front (si UI)

- [ ] Contraste WCAG AA respecté.
- [ ] Navigation clavier possible sur les formulaires.
- [ ] Tout bouton interactif a un label accessible (texte ou `aria-label`).

## Revue

- [ ] PR revue par au moins une autre personne (ou par soi-même à J+1 si solo).
- [ ] La description de PR liste les tickets fermés (`Closes T1.x`).
- [ ] Les captures d'écran sont jointes pour tout changement UI.
