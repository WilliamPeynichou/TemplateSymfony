"""Indexer: transforme les notes (MatchNote Symfony) en chunks embeddés.

Stratégie de chunking simple : 1 note = 1 chunk. Le contenu des notes
de match dépasse rarement 2 000 caractères donc découpage inutile pour
cette itération. Pour des rapports plus longs, prévoir un splitter par
paragraphe (TODO phase 3).
"""

from __future__ import annotations

from .embeddings import embed_text
from .store import SearchHit, get_store


def index_note(
    coach_id: int,
    note: dict,
    source_type: str = "match_note",
) -> int:
    """Indexe une note (ou met à jour si déjà indexée).

    note : dict sérialisé Symfony avec au minimum id, content, éventuellement
           matchLabel, matchDate, team_id.
    """
    note_id = note.get("id")
    content = (note.get("content") or "").strip()
    if not content:
        return 0

    header = note.get("matchLabel") or note.get("title")
    text_to_embed = f"{header}\n\n{content}" if header else content

    vec = embed_text(text_to_embed)
    metadata = {
        "matchLabel": note.get("matchLabel"),
        "matchDate": note.get("matchDate"),
        "title": note.get("title"),
    }
    return get_store().upsert(
        coach_id=coach_id,
        source_type=source_type,
        source_id=int(note_id) if note_id is not None else None,
        content=content,
        embedding=vec,
        team_id=note.get("team_id") or note.get("teamId"),
        metadata=metadata,
    )


def index_match_notes_batch(coach_id: int, notes: list[dict], team_id: int | None = None) -> int:
    """Indexe un lot de notes. Retourne le nombre de chunks traités."""
    count = 0
    for n in notes:
        if team_id is not None and "team_id" not in n and "teamId" not in n:
            n["team_id"] = team_id
        index_note(coach_id, n, source_type="match_note")
        count += 1
    return count


def delete_note(coach_id: int, note_id: int, source_type: str = "match_note") -> int:
    return get_store().delete(coach_id, source_type, note_id)


def search_notes(
    coach_id: int,
    query: str,
    team_id: int | None = None,
    source_types: list[str] | None = None,
    limit: int = 5,
) -> list[SearchHit]:
    """Recherche sémantique dans les notes indexées."""
    if not query or not query.strip():
        return []
    q_vec = embed_text(query)
    return get_store().search(
        coach_id=coach_id,
        query_embedding=q_vec,
        team_id=team_id,
        source_types=source_types,
        limit=limit,
    )
