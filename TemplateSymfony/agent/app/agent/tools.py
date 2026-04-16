import httpx
from langchain_core.tools import tool
from app.config import SYMFONY_API_URL, AGENT_SECRET


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


ALL_TOOLS = [
    list_teams, get_team, create_team, update_team, delete_team,
    list_players, get_player, create_player, update_player, delete_player,
    get_match_notes, create_match_note,
    get_team_analysis,
    suggest_composition,
    coaching_report,
    analyze_player,
]
