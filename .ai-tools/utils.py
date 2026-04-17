"""
Utilities shared between build_index.py and semantic_search.py
"""

import os
import sqlite3
import json
import numpy as np
from pathlib import Path

DB_PATH = Path(__file__).parent / "index.db"

IGNORED_DIRS = {
    "node_modules", ".git", "dist", "build", ".next",
    "venv", "__pycache__", ".cache", "vendor", "coverage",
}

TARGETED_EXTENSIONS = {
    ".ts", ".tsx", ".js", ".jsx", ".py", ".go", ".rs", ".java",
}


def get_db() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS chunks (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            path     TEXT NOT NULL,
            start_line INTEGER NOT NULL,
            end_line   INTEGER NOT NULL,
            content  TEXT NOT NULL,
            embedding BLOB NOT NULL
        )
    """)
    conn.commit()
    return conn


def embedding_to_blob(embedding: list[float]) -> bytes:
    return np.array(embedding, dtype=np.float32).tobytes()


def blob_to_embedding(blob: bytes) -> np.ndarray:
    return np.frombuffer(blob, dtype=np.float32)


def cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
    norm_a = np.linalg.norm(a)
    norm_b = np.linalg.norm(b)
    if norm_a == 0 or norm_b == 0:
        return 0.0
    return float(np.dot(a, b) / (norm_a * norm_b))
