# Fonctionnalités du projet Coach

## Objectif
Créer une application de gestion d’équipe orientée coach, permettant de centraliser les informations joueurs, l’organisation sportive, le suivi humain, les statistiques, la tactique et la logistique.

---

## 1. Gestion des utilisateurs et des rôles

### Authentification
- Connexion sécurisée à l’application
- Gestion des comptes utilisateurs
- Accès réservé selon le rôle

### Rôles
- **Coach**
- **Joueur**
- Évolution possible vers d’autres rôles : assistant, dirigeant, préparateur, administrateur

### Profils utilisateurs
- Chaque utilisateur dispose d’un profil
- Un joueur peut avoir plusieurs postes
- Informations de base du joueur : nom, prénom, date de naissance, contact, poste principal, postes secondaires

---

## 2. Gestion des joueurs

### Fiche joueur
Chaque joueur doit avoir une fiche complète avec :
- Informations personnelles
- Postes occupés
- Statut actuel
- Historique de présence
- Temps de jeu
- Suivi disciplinaire
- Observations du staff

### Statut du joueur
Mettre en place un système de statut avec les valeurs suivantes :
- **Présent**
- **Blessé**
- **Absent**

### Motif du statut
Pour les statuts **Blessé** et **Absent**, prévoir un champ texte permettant de renseigner le motif.

Exemples :
- Blessure à la cheville
- Maladie
- Absence personnelle
- Convocation scolaire ou professionnelle

### Historique
Conserver un historique des changements de statut pour chaque joueur.

---

## 3. Suivi de présence

### Présences par événement
Pour chaque entraînement, match ou rassemblement, enregistrer la présence des joueurs.

### Calcul de présence
Mettre en place un calcul de présence sur la saison avec :
- Nombre total de convocations
- Nombre de présences
- Nombre d’absences
- Nombre d’absences justifiées
- Pourcentage de présence

### Exploitation
Ces données doivent pouvoir être consultées :
- Par joueur
- Par période
- En résumé de saison

---

## 4. Calendrier sportif

### Événements du calendrier
Créer un calendrier contenant les éléments suivants :
- Date
- Heure
- Lieu
- Type d’événement
- Rencontre / adversaire
- Commentaire éventuel

### Types d’événements
- Entraînement
- Match
- Réunion
- Stage
- Autre événement d’équipe

### Calendrier partagé
Prévoir un calendrier partagé afin que les membres autorisés puissent consulter les événements de l’équipe.

---

## 5. Gestion des rencontres et matchs

### Informations de match
Pour chaque rencontre, enregistrer :
- Adversaire
- Lieu
- Heure
- Compétition
- Groupe convoqué
- Résultat
- Commentaires du coach

### Convocations
Permettre au coach de définir les joueurs convoqués pour un match.

---

## 6. Gestion tactique

### Plans de composition
Mettre en place une gestion des compositions d’équipe avec :
- Schéma tactique
- Joueurs positionnés
- Variantes possibles
- Historique des compositions utilisées

### Statistiques d’utilisation
Associer aux plans de composition :
- Nombre d’utilisations
- Pourcentage d’utilisation
- Résultats associés si nécessaire

### Intégration Tactical Pad
Prévoir l’intégration d’une **iframe Tactical Pad** pour afficher ou exploiter un support tactique visuel.

---

## 7. Suivi sportif individuel

### Temps de jeu
Pour chaque joueur, suivre :
- Temps de jeu total
- Temps de jeu par match
- Temps de jeu effectif

### Suivi disciplinaire
Mettre en place un suivi disciplinaire lié aux cartons :
- Cartons jaunes
- Cartons rouges
- Historique des sanctions
- Matchs de suspension si besoin

### Statistiques individuelles
Prévoir une base pour ajouter ensuite d’autres statistiques selon les besoins.

---

## 8. Gestion humaine des joueurs

### Évaluation qualitative
Prévoir un espace de suivi humain permettant de renseigner :
- Points forts
- Points à améliorer
- Observations générales
- Objectifs de progression

### Suivi du joueur
Permettre au staff d’avoir une vision globale du joueur au-delà de la performance pure.

---

## 9. Gestion d’effectif type Football Manager

### Vision globale de l’effectif
Créer une gestion des joueurs inspirée d’un fonctionnement type **Football Manager**, avec :
- Vue d’ensemble de l’effectif
- Tri par poste
- Tri par statut
- Consultation rapide des fiches joueurs
- Analyse des profils disponibles

### Aide à la décision
Cette partie doit aider le coach à :
- Construire ses groupes
- Préparer ses compositions
- Identifier les absents et blessés
- Suivre la répartition du temps de jeu

---

## 10. Gestion de stock liée à l’équipe

### Matériel
Prévoir une gestion de stock liée à une équipe.

Exemples :
- Maillots
- Ballons
- Coupelles
- Chasubles
- Pharmacie
- Matériel d’entraînement

### Fonctions attendues
- Liste du matériel
- Quantité disponible
- Affectation éventuelle à une équipe
- Historique simple si nécessaire

---

## 11. Statistiques et indicateurs

### Statistiques collectives
Prévoir des indicateurs globaux sur l’équipe :
- Taux de présence
- Répartition des statuts
- Temps de jeu total
- Utilisation des systèmes tactiques

### Statistiques liées à la tactique
Mettre en avant des pourcentages autour du système utilisé avec les plans de composition.

Exemples :
- Système le plus utilisé
- Répartition des schémas tactiques
- Résultats associés à un système

---

## 12. API externe à étudier

### API Footclub
Analyser l’API Footclub afin de vérifier si elle permet de récupérer ou synchroniser certaines informations joueurs.

### Vérifications attendues
- Données disponibles
- Mode d’authentification
- Limitations techniques
- Possibilités d’intégration
- Contraintes réglementaires ou d’accès

---

## 13. Résumé fonctionnel global

L’application doit permettre de centraliser :
- La gestion des utilisateurs et des rôles
- La gestion complète des joueurs
- Le suivi des présences
- Le calendrier sportif partagé
- Les convocations et matchs
- La tactique et les compositions
- Le suivi du temps de jeu
- Le suivi disciplinaire
- La gestion humaine des joueurs
- La gestion de stock de l’équipe
- Les statistiques et indicateurs
- Une éventuelle connexion à l’API Footclub

---

## 14. Priorité fonctionnelle recommandée

### Priorité 1
- Authentification
- Rôles
- Gestion des joueurs
- Statuts joueurs
- Motifs d’absence / blessure
- Calendrier
- Présences

### Priorité 2
- Temps de jeu
- Suivi disciplinaire
- Gestion humaine
- Convocations
- Calendrier partagé

### Priorité 3
- Plans de composition
- Statistiques tactiques
- Intégration Tactical Pad
- Gestion de stock
- Connexion API Footclub

