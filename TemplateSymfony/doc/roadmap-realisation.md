# Roadmap de réalisation — SaaS Coach

> Source : `audit-fonctionnel-saas-coach.md`
> Objectif : fermer la boucle `effectif → suivi humain → entrainement → feuille de presence → match → analyse`

---

## Phase 1 — Must-Have

### F1. Feuille de presence match persistante ⬜
**Complexité** : complexe | **Durée** : ~3 semaines

- [ ] Entité `FixtureAttendanceSheet` (OneToOne Fixture)
- [ ] Entité `FixtureAttendance` (player, sheet, status, reason, notes)
- [ ] Constantes `FixtureAttendance::STATUSES` (present / absent / late / excused)
- [ ] Constantes `FixtureAttendance::REASONS` (injury / suspension / school / discipline / family / transport / other)
- [ ] Migration Doctrine
- [ ] `MatchAttendanceController` : routes `/matches/{fixtureId}/attendance/edit` + `/save` (JSON)
- [ ] `MatchController::prepare()` : persiste Fixture + AttendanceSheet au lieu de session
- [ ] `MatchController::board()` : lit depuis DB
- [ ] `PlayerController` : route `/players/{id}/attendance-history`
- [ ] Template `match/attendance.html.twig` — liste joueurs + statuts de présence + motif
- [ ] Template `player/attendance_history.html.twig` — historique match / entraînement par statut
- [ ] Stimulus `attendance_controller.js` — autosave + filtres + saisie rapide

---

### F2. Disponibilités et absences ⬜
**Complexité** : simple | **Durée** : ~3 jours

- [ ] `Player::STATUSES` : ajouter `STATUS_SUSPENDED`, `STATUS_SCHOOL`, `STATUS_FAMILY`
- [ ] `Player` : champ `expectedReturnAt` (nullable DateTimeImmutable)
- [ ] Migration Doctrine
- [ ] `PlayerController` : route edit pour mettre à jour statut + date retour
- [ ] Template `player/show.html.twig` : bloc "Disponibilité" (badge + retour prévu)
- [ ] Intégration dans `match/attendance.html.twig` (F1) : badge statut visible par joueur

---

### F3. Dossier humain joueur (structuré) ⬜
**Complexité** : moyenne | **Durée** : ~1.5 semaine

- [ ] Entité `PlayerNote` (player, author, content TEXT, category enum, createdAt)
- [ ] Entité `PlayerGoal` (player, title, description, dueDate, status enum, progressNotes)
- [ ] Entité `PlayerReview` (player, period enum, startDate, endDate, strengths, improvements, overallRating 1-5, coachSummary)
- [ ] Entité `PlayerStrength` (player, type enum strength/improvement_axis, label, priority 1-5)
- [ ] Migration Doctrine
- [ ] `PlayerController` : routes `/players/{id}/journal` (notes / goals / reviews / strengths)
- [ ] Template `player/journal.html.twig` — timeline chronologique + formulaires inline Stimulus

---

### F4. Calendrier coach unifié (`CalendarEvent`) ⬜
**Complexité** : complexe | **Durée** : ~1.5 semaine

- [ ] Entité `CalendarEvent` (type enum, startAt, endAt, title, location, payload JSON, team, coach)
- [ ] Types : `training`, `match`, `meeting`, `availability_block`, `camp`, `player_appointment`, `team_event`
- [ ] Migration Doctrine
- [ ] `CalendarEventController` CRUD (`/events`)
- [ ] `CalendarController::show()` : agrège `CalendarEvent` + `TrainingSession` + `Fixture` (transitoire)
- [ ] Template `calendar/event_form.html.twig`
- [ ] Migration progressive des données existantes (backfill en 2e temps)

---

### F5. Sécurisation billing Stripe ⬜
**Complexité** : moyenne | **Durée** : ~1 semaine

- [ ] `composer require stripe/stripe-php`
- [ ] `BillingController.php:78` : remplacer TODO par `\Stripe\Webhook::constructEvent()`
- [ ] Env var `STRIPE_WEBHOOK_SECRET` dans `.env` + `.env.example`
- [ ] Gérer `customer.subscription.updated` (upgrade/downgrade)
- [ ] Garder fallback `STRIPE_MODE=mock` pour dev local
- [ ] `BillingWebhookTest.php` (PHPUnit) — payloads signés

---

## Phase 2 — Should-Have

### F6. Analytics tactiques reliés aux résultats ⬜
**Complexité** : moyenne | **Durée** : ~2 semaines

- [ ] `TacticalStrategyRepository::findUsageStats(Team)` — W/D/L par stratégie
- [ ] `TacticalStrategyRepository::findByOpponent(Team, string)` — performance par adversaire
- [ ] `TacticalStrategyRepository::findByCompetition(Team, string)` — par compétition
- [ ] `TacticalStrategyRepository::findBySeasonPeriod(Team, from, to)` — tendance saisonnière
- [ ] `StrategyController::analytics()` → `/strategies/analytics`
- [ ] `StrategyController::stats()` → `/strategies/{id}/stats`
- [ ] Template `strategy/analytics.html.twig` — tableau comparatif + Chart.js
- [ ] Template `strategy/stats.html.twig` — fiche détaillée

---

### F7. Discipline et suspensions automatiques ⬜
**Complexité** : moyenne | **Durée** : ~1 semaine

- [ ] Entité `DisciplinaryRecord` (player, fixture, type enum yellow/red/suspension, reason, suspensionMatches, servedAt)
- [ ] Migration Doctrine
- [ ] `Player::isSuspended()` — calcul via `DisciplinaryRecord`
- [ ] Event listener : `PlayerMatchStat` cartons → alimente `DisciplinaryRecord` à clôture match
- [ ] Section "Discipline" sur fiche joueur
- [ ] Alert "suspendu" dans `attendance.html.twig` (F1)

---

### F8. Modèle joueur enrichi football ⬜
**Complexité** : simple | **Durée** : ~3 jours

- [ ] `Player` : champ `secondaryPositions` (JSON array)
- [ ] `Player` : champ `preferredRole` (string, ref RoleLibrary)
- [ ] `Player` : champ `versatilityScore` (int 0-100)
- [ ] Migration Doctrine
- [ ] Formulaire joueur mis à jour
- [ ] `SquadAnalyzer::suggestBestEleven()` : exploite les nouveaux champs

---

### F9. Dashboards de saison ⬜
**Complexité** : simple-moyenne | **Durée** : ~1 semaine

- [ ] `ReportController::seasonDashboard()`
- [ ] Template `dashboard/season.html.twig` — W/D/L, buts, tactique dominante, joueurs clés, tendance
- [ ] Template `dashboard/team.html.twig` — fiche équipe enrichie F6/F7

---

## Phase 3 — Différenciant

### F10. Progression coachable dans le temps ⬜
**Complexité** : complexe | **Durée** : ~2 semaines

- [ ] Entité `PlayerAttributesSnapshot` (copie datée de PlayerAttributes)
- [ ] Cron mensuel ou déclenchement sur `PlayerReview`
- [ ] `PlayerController` : route `/players/{id}/progression`
- [ ] Template `player/progression.html.twig` — radar évolutif Chart.js + timeline narrative

---

### F11. Vue croisée entrainement → match ⬜
**Complexité** : moyenne | **Durée** : ~1 semaine

- [ ] `FixtureRepository::withWeeklyContext(Fixture)` — Training + Attendance des 7j avant
- [ ] Template `match/weekly_context.html.twig` — affiché depuis la fiche fixture

---

### F12. PWA renforcée (push + sync) ⬜
**Complexité** : moyenne-complexe | **Durée** : ~2 semaines

- [ ] `public/sw.js` : Background Sync API
- [ ] Web Push API : abonnements sur `User`, service `PushNotifier`
- [ ] `PushController` : gestion des abonnements
- [ ] Cas d'usage push : rappel de feuille de présence 24h avant match ou entraînement

---

### F13. Recommandations coach basées sur données ⬜
**Complexité** : moyenne | **Durée** : ~2 semaines

- [ ] Enrichir contexte prompt `AgentConversation` avec stats F6
- [ ] Injecter dossier humain F3 (forces/axes) dans le prompt
- [ ] Injecter progression F10 (joueurs en hausse/baisse)
- [ ] Endpoints internes exposés pour l'agent

---

## Suivi d'avancement

| # | Feature | Statut | Phase |
|---|---|---|---|
| F1 | Feuille de présence match | ⬜ À faire | Must |
| F2 | Disponibilités et absences | ⬜ À faire | Must |
| F3 | Dossier humain joueur | ⬜ À faire | Must |
| F4 | Calendrier CalendarEvent | ⬜ À faire | Must |
| F5 | Billing Stripe signé | ⬜ À faire | Must |
| F6 | Analytics tactiques | ⬜ À faire | Should |
| F7 | Discipline / suspensions | ⬜ À faire | Should |
| F8 | Joueur enrichi football | ⬜ À faire | Should |
| F9 | Dashboards de saison | ⬜ À faire | Should |
| F10 | Progression coachable | ⬜ À faire | Différenciant |
| F11 | Vue training → match | ⬜ À faire | Différenciant |
| F12 | PWA push + sync | ⬜ À faire | Différenciant |
| F13 | IA recommandations | ⬜ À faire | Différenciant |
