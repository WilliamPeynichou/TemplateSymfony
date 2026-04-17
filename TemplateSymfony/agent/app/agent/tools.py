import httpx
from langchain_core.tools import tool
from app.config import SYMFONY_API_URL, AGENT_SECRET
from app.rag import search_notes as rag_search_notes


def _headers(coach_id: int) -> dict:
    return {
        "X-Coach-Id":    str(coach_id),
        "Authorization": f"Bearer agent:{AGENT_SECRET}",
    }


def _get(path: str, coach_id: int) -> dict:
    r = httpx.get(f"{SYMFONY_API_URL}{path}", headers=_headers(coach_id), timeout=30)
    r.raise_for_status()
    return r.json()


def _post(path: str, payload: dict, coach_id: int) -> dict:
    r = httpx.post(f"{SYMFONY_API_URL}{path}", json=payload, headers=_headers(coach_id), timeout=30)
    r.raise_for_status()
    return r.json()


def _patch(path: str, payload: dict, coach_id: int) -> dict:
    r = httpx.patch(f"{SYMFONY_API_URL}{path}", json=payload, headers=_headers(coach_id), timeout=30)
    r.raise_for_status()
    return r.json()


def _delete(path: str, coach_id: int) -> dict:
    r = httpx.delete(f"{SYMFONY_API_URL}{path}", headers=_headers(coach_id), timeout=30)
    r.raise_for_status()
    return r.json()


# ─── ÉQUIPES ────────────────────────────────────────────────────────────────

@tool
def list_teams(coach_id: int) -> dict:
    """Liste toutes les équipes du coach."""
    return _get("/teams", coach_id)


@tool
def get_team(coach_id: int, team_id: int) -> dict:
    """Récupère les détails d'une équipe (nom, club, saison, nombre de joueurs)."""
    return _get(f"/teams/{team_id}", coach_id)


@tool
def create_team(coach_id: int, name: str, club: str | None = None, season: str | None = None) -> dict:
    """Crée une nouvelle équipe."""
    return _post("/teams", {"name": name, "club": club, "season": season}, coach_id)


@tool
def update_team(coach_id: int, team_id: int, name: str | None = None, club: str | None = None, season: str | None = None) -> dict:
    """Met à jour les informations d'une équipe."""
    payload = {k: v for k, v in {"name": name, "club": club, "season": season}.items() if v is not None}
    return _patch(f"/teams/{team_id}", payload, coach_id)


@tool
def delete_team(coach_id: int, team_id: int) -> dict:
    """Supprime une équipe et tous ses joueurs."""
    return _delete(f"/teams/{team_id}", coach_id)


# ─── JOUEURS ────────────────────────────────────────────────────────────────

@tool
def list_players(coach_id: int, team_id: int) -> dict:
    """Liste tous les joueurs d'une équipe avec leurs positions, pieds forts, âge."""
    return _get(f"/teams/{team_id}/players", coach_id)


@tool
def get_player(coach_id: int, team_id: int, player_id: int) -> dict:
    """Récupère les détails d'un joueur."""
    return _get(f"/teams/{team_id}/players/{player_id}", coach_id)


@tool
def create_player(
    coach_id: int,
    team_id: int,
    firstName: str,
    lastName: str,
    number: int,
    position: str,
    strongFoot: str | None = None,
    height: int | None = None,
    weight: int | None = None,
) -> dict:
    """
    Crée un nouveau joueur dans l'équipe.
    Positions valides : GK, CB, LB, RB, CDM, CM, CAM, LW, RW, ST
    Pied fort : right, left, both
    """
    return _post(f"/teams/{team_id}/players", {
        "firstName": firstName, "lastName": lastName,
        "number": number, "position": position,
        "strongFoot": strongFoot, "height": height, "weight": weight,
    }, coach_id)


@tool
def update_player(
    coach_id: int,
    team_id: int,
    player_id: int,
    firstName: str | None = None,
    lastName: str | None = None,
    number: int | None = None,
    position: str | None = None,
    strongFoot: str | None = None,
    height: int | None = None,
    weight: int | None = None,
) -> dict:
    """Met à jour les informations d'un joueur."""
    payload = {k: v for k, v in {
        "firstName": firstName, "lastName": lastName, "number": number,
        "position": position, "strongFoot": strongFoot, "height": height, "weight": weight,
    }.items() if v is not None}
    return _patch(f"/teams/{team_id}/players/{player_id}", payload, coach_id)


@tool
def delete_player(coach_id: int, team_id: int, player_id: int) -> dict:
    """Supprime un joueur de l'équipe."""
    return _delete(f"/teams/{team_id}/players/{player_id}", coach_id)


# ─── NOTES POST-MATCH ───────────────────────────────────────────────────────

@tool
def get_match_notes(coach_id: int, team_id: int) -> dict:
    """
    Récupère toutes les notes post-match du coach pour une équipe.
    Ces notes contiennent les observations du coach après chaque match.
    Utilise-les pour analyser la forme des joueurs et proposer des stratégies.
    """
    return _get(f"/teams/{team_id}/match-notes", coach_id)


@tool
def create_match_note(coach_id: int, team_id: int, matchLabel: str, content: str, matchDate: str | None = None) -> dict:
    """
    Crée une note post-match.
    matchLabel : nom de l'adversaire ou intitulé du match
    content    : observations libres du coach
    matchDate  : format YYYY-MM-DD (optionnel, aujourd'hui par défaut)
    """
    payload = {"matchLabel": matchLabel, "content": content}
    if matchDate:
        payload["matchDate"] = matchDate
    return _post(f"/teams/{team_id}/match-notes", payload, coach_id)


# ─── ANALYSE ÉQUIPE ─────────────────────────────────────────────────────────

@tool
def get_team_analysis(coach_id: int, team_id: int) -> dict:
    """
    Agrège les données de l'équipe : joueurs + leurs profils + notes post-match récentes.
    Utilise ce tool pour avoir un contexte complet avant de proposer une composition
    ou un rapport de coaching.
    """
    players_resp = _get(f"/teams/{team_id}/players", coach_id)
    notes_resp   = _get(f"/teams/{team_id}/match-notes", coach_id)
    team_resp    = _get(f"/teams/{team_id}", coach_id)

    players = players_resp.get("data", [])
    notes   = notes_resp.get("data", [])
    team    = team_resp.get("data", {})

    # Résumé par position
    by_position: dict[str, list] = {}
    for p in players:
        pos = p.get("position", "?")
        by_position.setdefault(pos, []).append(p.get("fullName", ""))

    return {
        "success": True,
        "data": {
            "team": team,
            "player_count": len(players),
            "players": players,
            "players_by_position": by_position,
            "match_notes_count": len(notes),
            "match_notes": notes[-10:],  # 10 dernières notes
        },
        "error": None,
    }


@tool
def suggest_composition(coach_id: int, team_id: int, formation: str | None = None) -> dict:
    """
    Analyse les joueurs disponibles et les notes post-match pour proposer
    la composition la plus adaptée.
    formation optionnelle : '4-3-3', '4-4-2', '4-2-3-1', '3-5-2', '5-3-2'
    Retourne la liste des postes avec les joueurs recommandés et les justifications.
    """
    players_resp = _get(f"/teams/{team_id}/players", coach_id)
    notes_resp   = _get(f"/teams/{team_id}/match-notes", coach_id)

    players = players_resp.get("data", [])
    notes   = notes_resp.get("data", [])

    # Grouper les joueurs par position
    by_position: dict[str, list] = {}
    for p in players:
        pos = p.get("position", "?")
        by_position.setdefault(pos, []).append(p)

    # Extraire les mentions par joueur dans les notes
    player_mentions: dict[str, list[str]] = {}
    for note in notes[-10:]:
        content = note.get("content", "")
        label   = note.get("matchLabel", "")
        for p in players:
            name = p.get("fullName", "")
            if name and name.lower() in content.lower():
                player_mentions.setdefault(name, []).append(f"[{label}] {content[:120]}")

    return {
        "success": True,
        "data": {
            "requested_formation": formation or "auto",
            "players_by_position": by_position,
            "player_mentions_in_notes": player_mentions,
            "total_players": len(players),
            "notes_analyzed": len(notes[-10:]),
            "instruction": (
                "Propose une composition complète poste par poste. "
                "Pour chaque poste, choisis le joueur le plus adapté parmi ceux disponibles "
                "en tenant compte de : sa position native, ses mentions dans les notes (forme), "
                "son pied fort et sa complémentarité avec les autres. "
                "Justifie chaque choix en 1 phrase."
            ),
        },
        "error": None,
    }


@tool
def coaching_report(coach_id: int, team_id: int) -> dict:
    """
    Génère un rapport de coaching complet basé sur les notes post-match accumulées.
    Identifie : joueurs en forme, joueurs en méforme, patterns collectifs,
    axes de progression prioritaires.
    """
    players_resp = _get(f"/teams/{team_id}/players", coach_id)
    notes_resp   = _get(f"/teams/{team_id}/match-notes", coach_id)
    team_resp    = _get(f"/teams/{team_id}", coach_id)

    players = players_resp.get("data", [])
    notes   = notes_resp.get("data", [])

    # Regrouper les notes par joueur mentionné
    player_note_map: dict[str, list[dict]] = {}
    for note in notes:
        content = note.get("content", "")
        for p in players:
            name = p.get("fullName", "")
            if name and name.lower() in content.lower():
                player_note_map.setdefault(name, []).append({
                    "match": note.get("matchLabel", ""),
                    "date":  note.get("matchDate", ""),
                    "extract": content[:200],
                })

    # Stats globales
    recent_notes = notes[-8:]
    full_text    = " ".join(n.get("content", "") for n in recent_notes)

    return {
        "success": True,
        "data": {
            "team":                 team_resp.get("data", {}),
            "total_notes":          len(notes),
            "recent_notes_count":   len(recent_notes),
            "player_count":         len(players),
            "player_note_map":      player_note_map,
            "recent_full_text":     full_text[:2000],
            "players_summary":      [
                {
                    "name":       p.get("fullName"),
                    "position":   p.get("position"),
                    "age":        p.get("age"),
                    "strongFoot": p.get("strongFoot"),
                    "mentioned_in_notes": len(player_note_map.get(p.get("fullName", ""), [])),
                }
                for p in players
            ],
            "instruction": (
                "Rédige un rapport de coaching structuré avec ces sections : "
                "1) Joueurs en forme (citer les noms et les observations positives), "
                "2) Joueurs à surveiller (méforme, erreurs répétées), "
                "3) Points collectifs forts, "
                "4) Axes de progression prioritaires (max 3), "
                "5) Recommandation tactique pour le prochain match. "
                "Sois factuel, cite les matchs concernés."
            ),
        },
        "error": None,
    }


@tool
def analyze_player(coach_id: int, team_id: int, player_id: int) -> dict:
    """
    Analyse un joueur spécifique : profil complet + toutes les mentions
    dans les notes post-match du coach. Utile pour évaluer si un joueur
    est titularisable ou à mettre sur le banc.
    """
    player_resp = _get(f"/teams/{team_id}/players/{player_id}", coach_id)
    notes_resp  = _get(f"/teams/{team_id}/match-notes", coach_id)

    player = player_resp.get("data", {})
    notes  = notes_resp.get("data", [])
    name   = player.get("fullName", "").lower()

    mentions = []
    for note in notes:
        if name and name in note.get("content", "").lower():
            mentions.append({
                "match":   note.get("matchLabel", ""),
                "date":    note.get("matchDate", ""),
                "content": note.get("content", "")[:300],
            })

    return {
        "success": True,
        "data": {
            "player":   player,
            "mentions": mentions,
            "mention_count": len(mentions),
            "instruction": (
                "Analyse ce joueur en te basant sur son profil et ses mentions dans les notes. "
                "Évalue : sa forme récente, ses points forts, ses points faibles, "
                "et donne une recommandation (titulaire / banc / à surveiller)."
            ),
        },
        "error": None,
    }


# ─── MATCHS (FIXTURES) ──────────────────────────────────────────────────────

@tool
def list_fixtures(coach_id: int, team_id: int) -> dict:
    """
    Liste tous les matchs (à venir ou joués) d'une équipe, du plus récent au plus ancien.
    Utile pour savoir quels adversaires ont déjà été rencontrés.
    """
    return _get(f"/teams/{team_id}/fixtures", coach_id)


@tool
def get_fixture(coach_id: int, team_id: int, fixture_id: int) -> dict:
    """Récupère les détails d'un match (score, adversaire, notes)."""
    return _get(f"/teams/{team_id}/fixtures/{fixture_id}", coach_id)


@tool
def create_fixture(
    coach_id: int,
    team_id: int,
    opponent: str,
    matchDate: str,
    venue: str = "home",
    competition: str | None = None,
    scoreFor: int | None = None,
    scoreAgainst: int | None = None,
    status: str = "scheduled",
    notes: str | None = None,
) -> dict:
    """
    Crée un match.
    matchDate : format ISO 8601 (ex: 2026-04-20T15:00:00).
    venue     : 'home' ou 'away'.
    status    : 'scheduled', 'played' ou 'cancelled'.
    Fournis scoreFor/scoreAgainst uniquement si status='played'.
    """
    payload = {
        "opponent": opponent,
        "matchDate": matchDate,
        "venue": venue,
        "status": status,
    }
    if competition is not None:
        payload["competition"] = competition
    if scoreFor is not None:
        payload["scoreFor"] = scoreFor
    if scoreAgainst is not None:
        payload["scoreAgainst"] = scoreAgainst
    if notes is not None:
        payload["notes"] = notes
    return _post(f"/teams/{team_id}/fixtures", payload, coach_id)


@tool
def update_fixture(
    coach_id: int,
    team_id: int,
    fixture_id: int,
    opponent: str | None = None,
    matchDate: str | None = None,
    venue: str | None = None,
    competition: str | None = None,
    scoreFor: int | None = None,
    scoreAgainst: int | None = None,
    status: str | None = None,
    notes: str | None = None,
) -> dict:
    """Met à jour un match (ex: renseigner le score après la rencontre)."""
    payload = {k: v for k, v in {
        "opponent": opponent, "matchDate": matchDate, "venue": venue,
        "competition": competition, "scoreFor": scoreFor, "scoreAgainst": scoreAgainst,
        "status": status, "notes": notes,
    }.items() if v is not None}
    return _patch(f"/teams/{team_id}/fixtures/{fixture_id}", payload, coach_id)


@tool
def delete_fixture(coach_id: int, team_id: int, fixture_id: int) -> dict:
    """Supprime un match."""
    return _delete(f"/teams/{team_id}/fixtures/{fixture_id}", coach_id)


@tool
def fixture_report(coach_id: int, team_id: int) -> dict:
    """
    Synthèse des matchs d'une équipe : bilan V/N/D sur les matchs joués,
    prochains matchs programmés, buts marqués/encaissés.
    """
    fixtures_resp = _get(f"/teams/{team_id}/fixtures", coach_id)
    fixtures = fixtures_resp.get("data", [])

    played = [f for f in fixtures if f.get("status") == "played"]
    upcoming = [f for f in fixtures if f.get("status") == "scheduled"]

    wins = sum(1 for f in played if f.get("result") == "win")
    draws = sum(1 for f in played if f.get("result") == "draw")
    losses = sum(1 for f in played if f.get("result") == "loss")

    goals_for = sum((f.get("scoreFor") or 0) for f in played)
    goals_against = sum((f.get("scoreAgainst") or 0) for f in played)

    return {
        "success": True,
        "data": {
            "played_count": len(played),
            "upcoming_count": len(upcoming),
            "wins": wins,
            "draws": draws,
            "losses": losses,
            "goals_for": goals_for,
            "goals_against": goals_against,
            "goal_diff": goals_for - goals_against,
            "last_5_played": played[:5],
            "next_3_upcoming": upcoming[:3] if upcoming else [],
            "instruction": (
                "Rédige un bilan synthétique : forme actuelle, tendance "
                "(victoires consécutives, série négative), points forts offensifs/défensifs "
                "et un conseil pour le prochain match."
            ),
        },
        "error": None,
    }


# ─── RAG — RECHERCHE SÉMANTIQUE DES NOTES ───────────────────────────────────

@tool
def search_notes_semantic(
    coach_id: int,
    query: str,
    team_id: int | None = None,
    limit: int = 5,
) -> dict:
    """
    Recherche dans les notes de match du coach par similarité sémantique.
    Retourne les extraits les plus proches de la requête en langage naturel.

    Exemples d'usage :
    - "joueurs qui ont manqué d'intensité en seconde mi-temps"
    - "matchs où la défense a été dépassée"
    - "Lucas erreurs de placement"

    Plus pertinent que `get_match_notes` quand la question est abstraite.
    """
    try:
        hits = rag_search_notes(coach_id=coach_id, query=query, team_id=team_id, limit=limit)
    except Exception as e:
        return {"success": False, "data": None, "error": f"RAG search failed: {e}"}

    return {
        "success": True,
        "data": {
            "query": query,
            "results": [
                {
                    "score": round(h.score, 4),
                    "source_type": h.source_type,
                    "source_id": h.source_id,
                    "team_id": h.team_id,
                    "matchLabel": h.metadata.get("matchLabel"),
                    "matchDate": h.metadata.get("matchDate"),
                    "extract": h.content[:600],
                }
                for h in hits
            ],
            "count": len(hits),
        },
        "error": None,
    }


ALL_TOOLS = [
    list_teams, get_team, create_team, update_team, delete_team,
    list_players, get_player, create_player, update_player, delete_player,
    get_match_notes, create_match_note,
    list_fixtures, get_fixture, create_fixture, update_fixture, delete_fixture,
    fixture_report,
    get_team_analysis,
    suggest_composition,
    coaching_report,
    analyze_player,
    search_notes_semantic,
]
