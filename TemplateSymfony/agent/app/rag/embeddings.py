"""Embeddings client.

Supporte deux backends :
- Ollama (par défaut, modèle `nomic-embed-text` ou équivalent local).
- OpenAI-compatible (`text-embedding-3-small`) si OPENAI_API_KEY est défini.

Le choix se fait via la variable d'environnement EMBEDDINGS_BACKEND :
- "ollama" (défaut)
- "openai"
"""

from __future__ import annotations

import os
import httpx

EMBEDDINGS_BACKEND = os.getenv("EMBEDDINGS_BACKEND", "ollama").lower()
OLLAMA_BASE_URL = os.getenv("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_EMBEDDINGS_MODEL = os.getenv("OLLAMA_EMBEDDINGS_MODEL", "nomic-embed-text")
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
OPENAI_EMBEDDINGS_MODEL = os.getenv("OPENAI_EMBEDDINGS_MODEL", "text-embedding-3-small")


class EmbeddingError(RuntimeError):
    """Levée quand un backend d'embeddings échoue."""


def _embed_ollama(text: str) -> list[float]:
    url = f"{OLLAMA_BASE_URL}/api/embeddings"
    try:
        r = httpx.post(url, json={"model": OLLAMA_EMBEDDINGS_MODEL, "prompt": text}, timeout=60)
        r.raise_for_status()
    except httpx.HTTPError as e:
        raise EmbeddingError(f"Ollama embeddings failed: {e}") from e

    data = r.json()
    embedding = data.get("embedding")
    if not embedding:
        raise EmbeddingError(f"Ollama embeddings returned no vector: {data}")
    return list(embedding)


def _embed_openai(text: str) -> list[float]:
    if not OPENAI_API_KEY:
        raise EmbeddingError("OPENAI_API_KEY manquant pour le backend 'openai'.")
    url = "https://api.openai.com/v1/embeddings"
    try:
        r = httpx.post(
            url,
            headers={"Authorization": f"Bearer {OPENAI_API_KEY}"},
            json={"model": OPENAI_EMBEDDINGS_MODEL, "input": text},
            timeout=60,
        )
        r.raise_for_status()
    except httpx.HTTPError as e:
        raise EmbeddingError(f"OpenAI embeddings failed: {e}") from e

    data = r.json()
    embedding = data["data"][0]["embedding"]
    return list(embedding)


def embed_text(text: str) -> list[float]:
    """Retourne le vecteur d'embedding pour un texte (utilise le backend configuré)."""
    text = (text or "").strip()
    if not text:
        raise EmbeddingError("Texte vide, impossible d'encoder.")

    if EMBEDDINGS_BACKEND == "openai":
        return _embed_openai(text)
    return _embed_ollama(text)


def embed_batch(texts: list[str]) -> list[list[float]]:
    """Embeddings par lot. Implémentation simple : boucle sur embed_text."""
    return [embed_text(t) for t in texts]
