"""Vector store SQLite pour les notes d'entraînement et de match.

Schéma :

rag_chunks(
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    coach_id      INTEGER NOT NULL,
    team_id       INTEGER,
    source_type   TEXT NOT NULL,   -- 'match_note' | 'training' | 'ad_hoc'
    source_id     INTEGER,         -- id de la note dans la BDD Symfony
    content       TEXT NOT NULL,
    metadata_json TEXT,
    embedding     BLOB NOT NULL,   -- float32 numpy tobytes()
    dim           INTEGER NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(coach_id, source_type, source_id)
)

On utilise numpy pour la similarité cosinus en RAM. Pour < 100 000 chunks
par coach c'est largement suffisant. Au-delà, migrer vers pgvector.
"""

from __future__ import annotations

import json
import os
import sqlite3
import threading
from contextlib import contextmanager
from dataclasses import dataclass
from typing import Optional

import numpy as np

RAG_DB_PATH = os.getenv("RAG_DB_PATH", "/app/var/rag.sqlite")


@dataclass
class SearchHit:
    id: int
    score: float
    content: str
    source_type: str
    source_id: Optional[int]
    team_id: Optional[int]
    metadata: dict


class VectorStore:
    """SQLite-backed vector store, thread-safe via un Lock."""

    def __init__(self, db_path: str) -> None:
        self.db_path = db_path
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        self._lock = threading.Lock()
        self._init_schema()

    @contextmanager
    def _conn(self):
        """Context manager : ouvre une connexion SQLite, commit à la sortie, et
        la ferme toujours (évite les leaks de file handles sur un serveur long-lived)."""
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row
        try:
            yield conn
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()

    def _init_schema(self) -> None:
        with self._lock, self._conn() as conn:
            conn.execute("""
                CREATE TABLE IF NOT EXISTS rag_chunks(
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    coach_id      INTEGER NOT NULL,
                    team_id       INTEGER,
                    source_type   TEXT NOT NULL,
                    source_id     INTEGER,
                    content       TEXT NOT NULL,
                    metadata_json TEXT,
                    embedding     BLOB NOT NULL,
                    dim           INTEGER NOT NULL,
                    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(coach_id, source_type, source_id)
                )
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_rag_chunks_coach_team
                ON rag_chunks(coach_id, team_id)
            """)

    def upsert(
        self,
        coach_id: int,
        source_type: str,
        source_id: int | None,
        content: str,
        embedding: list[float],
        team_id: int | None = None,
        metadata: dict | None = None,
    ) -> int:
        vec = np.asarray(embedding, dtype=np.float32)
        blob = vec.tobytes()
        dim = int(vec.shape[0])
        meta_json = json.dumps(metadata or {}, ensure_ascii=False)

        with self._lock, self._conn() as conn:
            row = conn.execute(
                "SELECT id FROM rag_chunks WHERE coach_id=? AND source_type=? AND source_id IS ?",
                (coach_id, source_type, source_id),
            ).fetchone()
            if row:
                conn.execute(
                    "UPDATE rag_chunks SET content=?, embedding=?, dim=?, metadata_json=?, team_id=? WHERE id=?",
                    (content, blob, dim, meta_json, team_id, row["id"]),
                )
                return int(row["id"])

            cur = conn.execute(
                "INSERT INTO rag_chunks(coach_id, team_id, source_type, source_id, content, metadata_json, embedding, dim) "
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                (coach_id, team_id, source_type, source_id, content, meta_json, blob, dim),
            )
            return int(cur.lastrowid)

    def delete(self, coach_id: int, source_type: str, source_id: int) -> int:
        with self._lock, self._conn() as conn:
            cur = conn.execute(
                "DELETE FROM rag_chunks WHERE coach_id=? AND source_type=? AND source_id=?",
                (coach_id, source_type, source_id),
            )
            return int(cur.rowcount)

    def count(self, coach_id: int, team_id: int | None = None) -> int:
        with self._lock, self._conn() as conn:
            if team_id is None:
                row = conn.execute(
                    "SELECT COUNT(*) AS n FROM rag_chunks WHERE coach_id=?",
                    (coach_id,),
                ).fetchone()
            else:
                row = conn.execute(
                    "SELECT COUNT(*) AS n FROM rag_chunks WHERE coach_id=? AND team_id=?",
                    (coach_id, team_id),
                ).fetchone()
            return int(row["n"])

    def search(
        self,
        coach_id: int,
        query_embedding: list[float],
        team_id: int | None = None,
        source_types: list[str] | None = None,
        limit: int = 5,
    ) -> list[SearchHit]:
        if not query_embedding:
            return []

        q = np.asarray(query_embedding, dtype=np.float32)
        q_norm = np.linalg.norm(q)
        if q_norm == 0.0:
            return []
        q_unit = q / q_norm

        sql = "SELECT id, team_id, source_type, source_id, content, metadata_json, embedding, dim FROM rag_chunks WHERE coach_id=?"
        params: list = [coach_id]
        if team_id is not None:
            sql += " AND team_id=?"
            params.append(team_id)
        if source_types:
            placeholders = ",".join("?" * len(source_types))
            sql += f" AND source_type IN ({placeholders})"
            params.extend(source_types)

        with self._lock, self._conn() as conn:
            rows = conn.execute(sql, params).fetchall()

        if not rows:
            return []

        hits: list[tuple[float, sqlite3.Row]] = []
        for row in rows:
            vec = np.frombuffer(row["embedding"], dtype=np.float32)
            if vec.shape[0] != q.shape[0]:
                continue
            n = np.linalg.norm(vec)
            if n == 0.0:
                continue
            score = float(np.dot(vec, q_unit) / n)
            hits.append((score, row))

        hits.sort(key=lambda x: x[0], reverse=True)
        top = hits[:limit]

        return [
            SearchHit(
                id=int(r["id"]),
                score=s,
                content=str(r["content"]),
                source_type=str(r["source_type"]),
                source_id=int(r["source_id"]) if r["source_id"] is not None else None,
                team_id=int(r["team_id"]) if r["team_id"] is not None else None,
                metadata=json.loads(r["metadata_json"] or "{}"),
            )
            for s, r in top
        ]


_store: VectorStore | None = None


def get_store() -> VectorStore:
    global _store
    if _store is None:
        _store = VectorStore(RAG_DB_PATH)
    return _store
