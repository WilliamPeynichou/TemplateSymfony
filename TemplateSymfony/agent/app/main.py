from fastapi import FastAPI, HTTPException, Header, UploadFile, File
from pydantic import BaseModel
from typing import Literal

from langchain_core.messages import HumanMessage, AIMessage, ToolMessage

from app.agent.graph import graph, apply_confirmed_action
from app.agent.state import AgentState
from app import memory
from app.config import AGENT_INTERNAL_SECRET
from app.rag import index_note, index_match_notes_batch, search_notes
from app.rag.store import get_store as get_rag_store

app = FastAPI(title="Coach IA Football Agent", version="1.0.0")


def _check_internal(secret: str | None) -> None:
    if secret != AGENT_INTERNAL_SECRET:
        raise HTTPException(status_code=401, detail="Invalid internal agent secret")


# ─── SCHÉMAS ────────────────────────────────────────────────────────────────

class ChatRequest(BaseModel):
    message: str
    conversation_id: int | None = None
    team_id: int | None = None
    validation_mode: Literal["singular", "auto"] = "singular"
    confirm_action: Literal["confirm", "cancel"] | None = None


class ChatResponse(BaseModel):
    success: bool
    conversation_id: int
    reply: str
    pending_action: dict | None = None
    error: str | None = None


# ─── HELPERS ────────────────────────────────────────────────────────────────

def _db_messages_to_langchain(db_msgs: list[dict]) -> list:
    """Convertit les messages BDD en objets LangChain."""
    result = []
    for m in db_msgs:
        role    = m.get("role", "user")
        content = m.get("content", "")
        if role == "user":
            result.append(HumanMessage(content=content))
        elif role == "assistant":
            tool_calls = m.get("tool_calls")
            if tool_calls:
                result.append(AIMessage(content=content, tool_calls=tool_calls))
            else:
                result.append(AIMessage(content=content))
        elif role == "tool":
            result.append(ToolMessage(content=content, tool_call_id="persisted"))
    return result


def _extract_reply(messages: list) -> str:
    """Extrait la dernière réponse textuelle de l'assistant.
    Supprime les balises <think>...</think> (mode réflexion Qwen3) si présentes.
    """
    import re
    for msg in reversed(messages):
        if isinstance(msg, AIMessage) and msg.content:
            text = re.sub(r'<think>.*?</think>', '', msg.content, flags=re.DOTALL).strip()
            return text
    return ""


# ─── ENDPOINTS ──────────────────────────────────────────────────────────────

@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/chat", response_model=ChatResponse)
async def chat(
    body: ChatRequest,
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
):
    _check_internal(x_agent_internal_secret)

    # 1. Récupérer ou créer la conversation en BDD
    conv = memory.get_or_create_conversation(x_coach_id, body.conversation_id, body.team_id)
    conv_id: int = conv["id"]
    persisted_pending_action = conv.get("pending_action")

    # 2. Charger l'historique depuis MySQL
    db_history = memory.load_history(x_coach_id, conv_id)
    history    = _db_messages_to_langchain(db_history)

    # 3. Construire l'état LangGraph
    state = AgentState(
        messages=history + [HumanMessage(content=body.message)],
        coach_id=x_coach_id,
        team_id=body.team_id,
        conversation_id=str(conv_id),
        validation_mode=body.validation_mode,
        pending_action=persisted_pending_action if body.confirm_action else None,
        confirm_action=body.confirm_action,
    )

    # 4. Invoquer le graphe (ou appliquer directement une confirmation)
    try:
        if body.confirm_action in ("confirm", "cancel") and persisted_pending_action is not None:
            result = apply_confirmed_action(state)
            final_messages = result["messages"]
        else:
            result = await graph.ainvoke(state)
            final_messages = result["messages"]
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

    # 5. Extraire la réponse
    ai_reply = _extract_reply(final_messages)

    # 6. Persister le message utilisateur + réponse agent
    memory.save_message(x_coach_id, conv_id, "user", body.message)
    if ai_reply:
        memory.save_message(x_coach_id, conv_id, "assistant", ai_reply)

    # 7. Auto-titre sur le premier message
    if not db_history:
        title = body.message[:80] + ("…" if len(body.message) > 80 else "")
        memory.update_title(x_coach_id, conv_id, title)

    pending = result.get("pending_action")
    if pending is not None:
        memory.update_pending_action(x_coach_id, conv_id, pending)
    elif body.confirm_action in ("confirm", "cancel") and persisted_pending_action is not None:
        memory.update_pending_action(x_coach_id, conv_id, None)

    return ChatResponse(
        success=True,
        conversation_id=conv_id,
        reply=ai_reply or "Je réfléchis…",
        pending_action=pending,
        error=None,
    )


@app.get("/conversations")
async def list_conversations(
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
    team_id: int | None = None,
):
    _check_internal(x_agent_internal_secret)

    convs = memory.list_conversations(x_coach_id, team_id)
    return {"success": True, "data": convs, "error": None}


# ─── RAG ────────────────────────────────────────────────────────────────────

class RagIndexRequest(BaseModel):
    source_type: Literal["match_note", "training", "ad_hoc"] = "match_note"
    note: dict


class RagIndexBatchRequest(BaseModel):
    team_id: int | None = None
    notes: list[dict]


class RagSearchRequest(BaseModel):
    query: str
    team_id: int | None = None
    limit: int = 5


@app.post("/rag/index")
def rag_index(
    body: RagIndexRequest,
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
):
    _check_internal(x_agent_internal_secret)
    try:
        chunk_id = index_note(x_coach_id, body.note, source_type=body.source_type)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Indexing failed: {e}") from e
    return {"success": True, "data": {"chunk_id": chunk_id}, "error": None}


@app.post("/rag/index-batch")
def rag_index_batch(
    body: RagIndexBatchRequest,
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
):
    _check_internal(x_agent_internal_secret)
    try:
        n = index_match_notes_batch(x_coach_id, body.notes, team_id=body.team_id)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Batch indexing failed: {e}") from e
    return {"success": True, "data": {"indexed": n}, "error": None}


@app.post("/rag/search")
def rag_search(
    body: RagSearchRequest,
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
):
    _check_internal(x_agent_internal_secret)
    try:
        hits = search_notes(
            coach_id=x_coach_id,
            query=body.query,
            team_id=body.team_id,
            limit=body.limit,
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Search failed: {e}") from e

    return {
        "success": True,
        "data": [
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
        "error": None,
    }


@app.get("/rag/stats")
def rag_stats(
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
    team_id: int | None = None,
):
    _check_internal(x_agent_internal_secret)
    return {
        "success": True,
        "data": {"indexed_chunks": get_rag_store().count(x_coach_id, team_id)},
        "error": None,
    }


# ─── VOICE (stub) ───────────────────────────────────────────────────────────

@app.post("/voice/transcribe")
async def voice_transcribe(
    audio: UploadFile = File(...),
    x_coach_id: int = Header(..., alias="X-Coach-Id"),
    x_agent_internal_secret: str | None = Header(None, alias="X-Agent-Internal-Secret"),
):
    """Stub : en phase 3 on brancherait Whisper local ici.

    Pour l'instant on accepte l'upload et on renvoie un placeholder
    pour que la chaîne front/back puisse être testée.
    """
    _check_internal(x_agent_internal_secret)
    content = await audio.read()
    size = len(content)
    return {
        "success": True,
        "data": {
            "transcription": "[transcription whisper non connectée - taille audio: "
            f"{size} bytes]",
            "coach_id": x_coach_id,
        },
        "error": None,
    }
