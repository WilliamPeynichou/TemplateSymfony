"""RAG module: vector store + embeddings + indexer for coach notes."""

from .embeddings import embed_text, embed_batch
from .store import VectorStore, get_store
from .indexer import index_note, index_match_notes_batch, search_notes

__all__ = [
    "embed_text",
    "embed_batch",
    "VectorStore",
    "get_store",
    "index_note",
    "index_match_notes_batch",
    "search_notes",
]
