#!/usr/bin/env python3
"""
build_index.py — Index the repo with semantic embeddings (OpenAI API).

Usage:
    OPENAI_API_KEY=sk-... python3 .ai-tools/build_index.py [repo_root]

The index is stored in .ai-tools/index.db (SQLite).
Re-running this script clears and rebuilds the index.
"""

import os
import sys
import json
import sqlite3
from pathlib import Path

import numpy as np
from openai import OpenAI

# --- resolve repo root ---
REPO_ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path.cwd()
sys.path.insert(0, str(Path(__file__).parent))

from utils import (
    DB_PATH, IGNORED_DIRS, TARGETED_EXTENSIONS,
    get_db, embedding_to_blob,
)

CHUNK_SIZE = 150   # lines per chunk
EMBED_MODEL = "text-embedding-3-small"
BATCH_SIZE  = 50   # chunks per OpenAI batch call


def iter_source_files(root: Path):
    """Yield all targeted source files, skipping ignored directories."""
    for dirpath, dirnames, filenames in os.walk(root):
        # Prune ignored dirs in-place so os.walk won't recurse into them
        dirnames[:] = [d for d in dirnames if d not in IGNORED_DIRS]
        for fname in filenames:
            if Path(fname).suffix in TARGETED_EXTENSIONS:
                yield Path(dirpath) / fname


def chunk_file(path: Path, repo_root: Path) -> list[dict]:
    """Split a file into ~CHUNK_SIZE-line chunks."""
    try:
        lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    except Exception as e:
        print(f"  [WARN] Cannot read {path}: {e}")
        return []

    rel_path = str(path.relative_to(repo_root))
    chunks = []
    for start in range(0, len(lines), CHUNK_SIZE):
        end = min(start + CHUNK_SIZE, len(lines))
        content = "\n".join(lines[start:end])
        chunks.append({
            "path": rel_path,
            "start_line": start + 1,
            "end_line": end,
            "content": content,
        })
    return chunks


def embed_batch(client: OpenAI, texts: list[str]) -> list[list[float]]:
    """Embed a batch of texts using OpenAI."""
    response = client.embeddings.create(model=EMBED_MODEL, input=texts)
    return [item.embedding for item in response.data]


def main():
    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        print("[ERROR] OPENAI_API_KEY environment variable is not set.")
        sys.exit(1)

    client = OpenAI(api_key=api_key)

    # Collect all chunks
    print(f"[INFO] Scanning {REPO_ROOT} …")
    all_chunks: list[dict] = []
    file_count = 0

    for src_file in iter_source_files(REPO_ROOT):
        chunks = chunk_file(src_file, REPO_ROOT)
        if chunks:
            all_chunks.extend(chunks)
            file_count += 1

    print(f"[INFO] {file_count} files — {len(all_chunks)} chunks to embed")

    if not all_chunks:
        print("[WARN] No chunks found. Nothing to index.")
        return

    # Rebuild the DB (clear old data)
    if DB_PATH.exists():
        DB_PATH.unlink()
    conn = get_db()

    # Embed in batches and store
    inserted = 0
    for i in range(0, len(all_chunks), BATCH_SIZE):
        batch = all_chunks[i : i + BATCH_SIZE]
        texts = [c["content"] for c in batch]

        print(f"  Embedding chunks {i+1}–{i+len(batch)} / {len(all_chunks)} …", end="\r")
        embeddings = embed_batch(client, texts)

        rows = [
            (
                c["path"],
                c["start_line"],
                c["end_line"],
                c["content"],
                embedding_to_blob(emb),
            )
            for c, emb in zip(batch, embeddings)
        ]
        conn.executemany(
            "INSERT INTO chunks (path, start_line, end_line, content, embedding) VALUES (?,?,?,?,?)",
            rows,
        )
        conn.commit()
        inserted += len(batch)

    conn.close()
    print(f"\n[OK] Index built: {inserted} chunks stored in {DB_PATH}")


if __name__ == "__main__":
    main()
