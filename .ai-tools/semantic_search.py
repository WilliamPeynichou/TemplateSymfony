#!/usr/bin/env python3
"""
semantic_search.py — Semantic code search using embeddings stored in index.db.

Usage:
    OPENAI_API_KEY=sk-... python3 .ai-tools/semantic_search.py "your query" [top_k]

Output: JSON array of top_k matching chunks.
"""

import os
import sys
import json
from pathlib import Path

import numpy as np
from openai import OpenAI

sys.path.insert(0, str(Path(__file__).parent))
from utils import DB_PATH, get_db, blob_to_embedding, cosine_similarity

EMBED_MODEL = "text-embedding-3-small"
SNIPPET_LINES = 30


def embed_query(client: OpenAI, query: str) -> np.ndarray:
    response = client.embeddings.create(model=EMBED_MODEL, input=[query])
    return np.array(response.data[0].embedding, dtype=np.float32)


def search(query: str, top_k: int = 5) -> list[dict]:
    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        print("[ERROR] OPENAI_API_KEY not set.", file=sys.stderr)
        sys.exit(1)

    if not DB_PATH.exists():
        print("[ERROR] index.db not found. Run build_index.py first.", file=sys.stderr)
        sys.exit(1)

    client = OpenAI(api_key=api_key)
    query_vec = embed_query(client, query)

    conn = get_db()
    rows = conn.execute(
        "SELECT path, start_line, end_line, content, embedding FROM chunks"
    ).fetchall()
    conn.close()

    scored = []
    for path, start_line, end_line, content, emb_blob in rows:
        vec = blob_to_embedding(emb_blob)
        score = cosine_similarity(query_vec, vec)
        scored.append((score, path, start_line, end_line, content))

    scored.sort(key=lambda x: x[0], reverse=True)
    top = scored[:top_k]

    results = []
    for score, path, start_line, end_line, content in top:
        snippet_lines = content.splitlines()[:SNIPPET_LINES]
        results.append({
            "path": path,
            "start_line": start_line,
            "end_line": end_line,
            "score": round(score, 4),
            "snippet": "\n".join(snippet_lines),
        })

    return results


def main():
    if len(sys.argv) < 2:
        print("Usage: semantic_search.py <query> [top_k]", file=sys.stderr)
        sys.exit(1)

    query = sys.argv[1]
    top_k = int(sys.argv[2]) if len(sys.argv) > 2 else 5

    results = search(query, top_k)
    print(json.dumps(results, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
