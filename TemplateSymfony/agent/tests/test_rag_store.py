"""Tests unitaires pour le vector store SQLite.

On patche `embed_text` pour éviter tout appel réseau.
"""

from __future__ import annotations

import os
import tempfile
from unittest.mock import patch

os.environ.setdefault("AGENT_SECRET", "test")
os.environ.setdefault("AGENT_INTERNAL_SECRET", "test")

from app.rag.store import VectorStore


def _fake_embed(text: str) -> list[float]:
    # encode un texte en vecteur déterministe simple (présence des mots-clés)
    base = [0.0] * 8
    keywords = ["gardien", "attaque", "defense", "milieu", "rythme", "erreur", "but", "rapide"]
    for i, kw in enumerate(keywords):
        if kw in text.lower():
            base[i] = 1.0
    return base


def test_upsert_and_search_returns_most_similar():
    with tempfile.TemporaryDirectory() as d:
        store = VectorStore(os.path.join(d, "rag.sqlite"))

        store.upsert(
            coach_id=1, source_type="match_note", source_id=1,
            content="Le gardien a été solide, aucune erreur sur les tirs cadrés.",
            embedding=_fake_embed("gardien erreur"),
            team_id=10, metadata={"matchLabel": "vs OM"},
        )
        store.upsert(
            coach_id=1, source_type="match_note", source_id=2,
            content="L'attaque a manqué de rythme en seconde mi-temps.",
            embedding=_fake_embed("attaque rythme"),
            team_id=10, metadata={"matchLabel": "vs OL"},
        )
        store.upsert(
            coach_id=1, source_type="match_note", source_id=3,
            content="Defense compacte, milieu discret.",
            embedding=_fake_embed("defense milieu"),
            team_id=10, metadata={"matchLabel": "vs RCS"},
        )

        q_vec = _fake_embed("attaque rythme")
        hits = store.search(coach_id=1, query_embedding=q_vec, team_id=10, limit=2)

        assert len(hits) == 2
        assert hits[0].source_id == 2
        assert hits[0].score > hits[1].score


def test_upsert_updates_existing_chunk():
    with tempfile.TemporaryDirectory() as d:
        store = VectorStore(os.path.join(d, "rag.sqlite"))

        first = store.upsert(
            coach_id=1, source_type="match_note", source_id=42,
            content="old", embedding=_fake_embed("gardien"), team_id=1, metadata={},
        )
        second = store.upsert(
            coach_id=1, source_type="match_note", source_id=42,
            content="new", embedding=_fake_embed("attaque"), team_id=1, metadata={"v": 2},
        )
        assert first == second
        assert store.count(coach_id=1) == 1


def test_coach_isolation():
    with tempfile.TemporaryDirectory() as d:
        store = VectorStore(os.path.join(d, "rag.sqlite"))

        store.upsert(
            coach_id=1, source_type="match_note", source_id=1,
            content="coach 1 note", embedding=_fake_embed("gardien"), team_id=1, metadata={},
        )
        store.upsert(
            coach_id=2, source_type="match_note", source_id=1,
            content="coach 2 note", embedding=_fake_embed("gardien"), team_id=1, metadata={},
        )

        hits_1 = store.search(coach_id=1, query_embedding=_fake_embed("gardien"), limit=10)
        hits_2 = store.search(coach_id=2, query_embedding=_fake_embed("gardien"), limit=10)

        assert len(hits_1) == 1
        assert len(hits_2) == 1
        assert hits_1[0].content == "coach 1 note"
        assert hits_2[0].content == "coach 2 note"


def test_delete_removes_chunk():
    with tempfile.TemporaryDirectory() as d:
        store = VectorStore(os.path.join(d, "rag.sqlite"))

        store.upsert(
            coach_id=1, source_type="match_note", source_id=1,
            content="note", embedding=_fake_embed("gardien"), metadata={},
        )
        assert store.count(coach_id=1) == 1

        n = store.delete(coach_id=1, source_type="match_note", source_id=1)
        assert n == 1
        assert store.count(coach_id=1) == 0


def test_search_with_embed_text_patched():
    with tempfile.TemporaryDirectory() as d:
        os.environ["RAG_DB_PATH"] = os.path.join(d, "rag.sqlite")
        # Important: importer après pour prendre le chemin patché
        import importlib
        import app.rag.store as store_mod
        importlib.reload(store_mod)
        import app.rag.indexer as indexer_mod
        importlib.reload(indexer_mod)

        with patch.object(indexer_mod, "embed_text", side_effect=_fake_embed):
            indexer_mod.index_note(
                coach_id=1,
                note={"id": 1, "content": "Gardien solide, pas d'erreur", "matchLabel": "vs A", "team_id": 1},
            )
            indexer_mod.index_note(
                coach_id=1,
                note={"id": 2, "content": "Attaque en manque de rythme", "matchLabel": "vs B", "team_id": 1},
            )
            hits = indexer_mod.search_notes(coach_id=1, query="attaque rythme", team_id=1)
            assert hits[0].source_id == 2
