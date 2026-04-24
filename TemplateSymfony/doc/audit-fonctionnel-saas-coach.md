# Audit fonctionnel - SaaS oriente coach de football

## Positionnement

Le produit doit etre lu comme un SaaS oriente coach, pas comme un ERP complet de club.
Dans ce cadre, la base actuelle est deja coherente pour un outil de gestion d'equipe :

- effectif et fiches joueurs ;
- calendrier d'entrainements et de matchs ;
- preparation de match ;
- tactiques reutilisables ;
- premiers indicateurs d'usage tactique ;
- suivi de presence.

En revanche, plusieurs briques restent insuffisantes pour en faire un produit vraiment vendable a un coach sur la duree.

## Audit par priorite

### Indispensable

#### 1. Feuille de presence match et entrainement

Le flux de preparation de match n'est pas encore complet.
Le coach peut choisir une tactique et enregistrer un match, mais il manque une vraie feuille de presence exploitable pour les matchs et les entrainements.

Constat :

- le besoin est deja present dans `fonctionnalites_projet_coach.md` ;
- `Fixture` ne porte pas encore une feuille de presence metier suffisamment claire ;
- la preparation de match reste centree sur adversaire, date et tactique.

Impact produit :

- pas de suivi simple des presences match ;
- pas de vision claire des absences et motifs ;
- pas d'analyse saisonniere fiable de la presence des joueurs.

Priorite fonctionnelle :

- feuille de presence par match ;
- feuille de presence par entrainement ;
- statuts present / absent / retard / excuse ;
- motif d'absence ;
- historique par joueur.

#### 2. Dossier humain du joueur

Le modele joueur est aujourd'hui surtout administratif.
Pour un SaaS coach, il faut un vrai dossier de suivi individuel.

Constat :

- informations identitaires et physiques presentes ;
- historique de statut present ;
- suivi qualitatif humain trop faible.

Ce qui manque :

- forces ;
- axes de progression ;
- objectifs individuels ;
- notes de coach ;
- bilans periodiques ;
- comportement et implication ;
- suivi mental ou attitude.

Impact produit :

- la plateforme aide a administrer un effectif ;
- elle aide encore trop peu a faire progresser un joueur.

#### 3. Calendrier coach complet

Le calendrier couvre surtout entrainements et matchs.
Pour un coach, cela ne suffit pas a piloter le quotidien de son groupe.

Ce qui manque en priorite :

- rendez-vous joueurs ;
- reunions ;
- indisponibilites ;
- stages ;
- evenements internes d'equipe.

Impact produit :

- outil utile sur le sportif ;
- mais encore trop faible comme centre de pilotage quotidien.

#### 4. SaaS monetisable et fiable

La facturation existe, mais la couche d'abonnement n'est pas totalement prete pour la production.

Constat :

- la logique de billing est presente ;
- la verification de signature webhook Stripe n'est pas finalisee.

Impact produit :

- risque de fragilite sur l'abonnement ;
- blocage pour un lancement SaaS propre.

### Utile

#### 5. Analyse tactique exploitable

Les tactiques sont enregistrables, reutilisables et leur usage est compte.
Mais l'analyse reste encore trop simple pour un vrai usage coach.

Ce qui manque :

- lien entre tactique et resultat ;
- analyse par adversaire ;
- analyse domicile / exterieur ;
- analyse par periode de saison ;
- comparaison entre tactiques.

Impact produit :

- la tactique est archivee ;
- elle n'est pas encore transformee en outil d'aide a la decision.

#### 6. Modele joueur plus riche sur le terrain

Le joueur est encore trop peu decrit du point de vue football.

Exemples utiles :

- poste principal ;
- postes secondaires ;
- polyvalence ;
- role prefere ;
- pied fort ;
- disponibilite reelle ;
- forme recente.

Impact produit :

- meilleure preparation tactique ;
- meilleure gestion des presences ;
- meilleure lisibilite pour le coach.

#### 7. Discipline et disponibilite

Pour un coach, la disponibilite effective d'un joueur est cle.

Ce qui doit etre mieux visible :

- cartons jaunes ;
- cartons rouges ;
- suspensions ;
- blessures ;
- absences justifiees ;
- absences injustifiees.

Impact produit :

- facilite la preparation de match ;
- evite des oublis de gestion sportive ;
- renforce la qualite du suivi de saison.

#### 8. Organisations a garder simples

Le systeme d'organisations et d'invitations existe deja.
Pour une cible coach, ce sujet ne doit pas devenir prioritaire trop tot.

Position recommande :

- garder une organisation legere ;
- permettre au coach de gerer ses equipes ;
- ne pas faire du multi-staff complexe un prerequis produit.

### Differenciant

#### 9. Dossier de progression coachable

Le vrai levier de valeur n'est pas seulement la gestion de l'effectif.
C'est la capacite a montrer la progression d'un joueur dans le temps.

Fonctions differentiantes :

- objectifs individuels dates ;
- bilans mensuels ;
- notes apres seance ;
- evolution des points forts / faibles ;
- historique de progression.

Valeur produit :

- le coach voit l'effet de son travail ;
- le joueur devient un parcours, pas juste une fiche.

#### 10. Analyse saisonniere des tactiques

Si la plateforme relie tactique, contexte et performance, elle devient un vrai assistant de coaching.

Fonctions differentiantes :

- tactique la plus utilisee ;
- tactique la plus performante ;
- tactique par type d'adversaire ;
- tactique par competition ;
- tendance d'usage sur la saison.

Valeur produit :

- forte valeur analytique ;
- vraie aide a la decision sportive.

#### 11. Vue croisee entrainement -> match

La plateforme serait plus forte si elle reliait la vie de la semaine aux performances du week-end.

Exemples :

- presences aux entrainements avant match ;
- charge de semaine ;
- contenu de seance ;
- lien avec performance ou resultat.

Valeur produit :

- angle tres metier ;
- plus difficile a copier qu'un simple CRUD de gestion d'equipe.

#### 12. Experience coach mobile-first

Un coach utilise souvent son outil dans des contextes de terrain, de vestiaire ou de deplacement.

Valeur produit :

- rapidite d'acces ;
- saisie simple ;
- usage pendant les routines reelles de coaching.

## Lecture produit

Aujourd'hui, le produit est credible comme base de SaaS coach.
Il n'est pas encore assez profond pour devenir un outil qu'un educateur paie durablement si l'ambition est un usage hebdomadaire structurant.

Le point cle est le suivant :

- il ne faut pas ajouter du CRUD pour ajouter du CRUD ;
- il faut fermer la boucle metier du coach.

Boucle cible :

`effectif -> suivi humain -> entrainement -> feuille de presence -> match -> analyse`

## Recommandation de roadmap

### Phase 1 - Must have

1. Feuille de presence match et entrainement
2. Disponibilites et absences
3. Dossier humain joueur
4. Calendrier coach enrichi
5. Securisation billing

### Phase 2 - Should have

1. Analytics tactiques relies aux resultats
2. Discipline et suspensions
3. Modele joueur plus riche
4. Tableaux de bord de saison

### Phase 3 - Nice to have / Differenciation

1. Progression individuelle dans le temps
2. Vue croisee entrainement -> match
3. Experience mobile-first renforcee
4. Recommandations coach basees sur l'usage et les donnees

## Conclusion

Le produit ne doit pas chercher a devenir trop tot une plateforme de gestion globale de club.
Sa meilleure trajectoire est celle d'un SaaS coach specialise :

- simple sur l'administration ;
- fort sur le suivi humain ;
- fort sur la preparation sportive ;
- fort sur l'analyse de saison.

Autrement dit :

- moins de largeur ;
- plus de profondeur metier pour le coach.
