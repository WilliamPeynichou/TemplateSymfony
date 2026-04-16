import json
from typing import Literal

from langchain_core.messages import AIMessage, HumanMessage, SystemMessage, ToolMessage
from langchain_ollama import ChatOllama
from langgraph.graph import END, StateGraph

from app.agent.prompts import SYSTEM_PROMPT
from app.agent.state import AgentState
from app.agent.tools import ALL_TOOLS
from app.config import LLM_BASE_URL, LLM_MODEL

# ─── LLM ────────────────────────────────────────────────────────────────────
# ChatOllama utilise l'API native Ollama (/api/chat) — supporte think=False
# LLM_BASE_URL = "http://ollama:11434/v1" → on prend juste l'origine
_ollama_base = LLM_BASE_URL.rstrip("/").removesuffix("/v1")

llm = ChatOllama(
    base_url=_ollama_base,
    model=LLM_MODEL,
    temperature=0.3,
    num_predict=1024,      # max tokens en sortie
    reasoning=False,       # désactive le mode thinking Qwen3 (langchain-ollama >=1.1.0)
).bind_tools(ALL_TOOLS)

tools_by_name = {t.name: t for t in ALL_TOOLS}

# ─── NŒUDS DU GRAPHE ────────────────────────────────────────────────────────

def call_llm(state: AgentState) -> dict:
    """Appelle le LLM avec le contexte complet."""
    system = SystemMessage(content=SYSTEM_PROMPT)
    response = llm.invoke([system] + state.messages)
    return {"messages": [response]}


def execute_tools(state: AgentState) -> dict:
    """Exécute les tool calls retournés par le LLM."""
    last_msg = state.messages[-1]
    results = []

    for tool_call in last_msg.tool_calls:
        tool_name = tool_call["name"]
        tool_args = tool_call["args"]

        # Injecter coach_id automatiquement dans tous les tools
        tool_args["coach_id"] = state.coach_id

        try:
            result = tools_by_name[tool_name].invoke(tool_args)
            content = json.dumps(result, ensure_ascii=False)
        except Exception as e:
            content = json.dumps({"success": False, "error": str(e)})

        results.append(ToolMessage(content=content, tool_call_id=tool_call["id"]))

    return {"messages": results}


def request_validation(state: AgentState) -> dict:
    """
    En mode singular : détecte les actions CRUD dans la réponse LLM
    et met l'action en attente de confirmation.
    """
    last_msg = state.messages[-1]

    if not hasattr(last_msg, "tool_calls") or not last_msg.tool_calls:
        return {}

    # En mode auto on laisse passer directement
    if state.validation_mode == "auto":
        return {}

    # En mode singular on bloque sur la première action détectée
    tool_call = last_msg.tool_calls[0]
    write_tools = {
        "create_team", "update_team", "delete_team",
        "create_player", "update_player", "delete_player",
        "create_match_note",
    }
    if tool_call["name"] in write_tools:
        return {"pending_action": tool_call}

    return {}


def apply_confirmed_action(state: AgentState) -> dict:
    """Exécute l'action confirmée par le coach."""
    if state.confirm_action == "cancel" or not state.pending_action:
        cancel_msg = AIMessage(content="Action annulée.")
        return {"messages": [cancel_msg], "pending_action": None, "confirm_action": None}

    tool_call = state.pending_action
    tool_args = dict(tool_call["args"])
    tool_args["coach_id"] = state.coach_id

    try:
        result = tools_by_name[tool_call["name"]].invoke(tool_args)
        content = json.dumps(result, ensure_ascii=False)
        ok_msg = AIMessage(content=f"Action effectuée : {result.get('data', result)}")
    except Exception as e:
        content = json.dumps({"success": False, "error": str(e)})
        ok_msg = AIMessage(content=f"Erreur lors de l'action : {e}")

    return {
        "messages": [ok_msg],
        "pending_action": None,
        "confirm_action": None,
    }


# ─── ROUTAGE ────────────────────────────────────────────────────────────────

def route_after_llm(state: AgentState) -> Literal["execute_tools", "request_validation", "__end__"]:
    last_msg = state.messages[-1]

    if not hasattr(last_msg, "tool_calls") or not last_msg.tool_calls:
        return END

    if state.validation_mode == "singular":
        write_tools = {
            "create_team", "update_team", "delete_team",
            "create_player", "update_player", "delete_player",
            "create_match_note",
        }
        if last_msg.tool_calls[0]["name"] in write_tools:
            return "request_validation"

    return "execute_tools"


def route_after_validation(state: AgentState) -> Literal["apply_confirmed_action", "__end__"]:
    if state.pending_action is not None:
        return END  # On attend la confirmation du coach
    return "apply_confirmed_action"


# ─── CONSTRUCTION DU GRAPHE ─────────────────────────────────────────────────

def build_graph() -> StateGraph:
    g = StateGraph(AgentState)

    g.add_node("call_llm", call_llm)
    g.add_node("execute_tools", execute_tools)
    g.add_node("request_validation", request_validation)
    g.add_node("apply_confirmed_action", apply_confirmed_action)

    g.set_entry_point("call_llm")

    g.add_conditional_edges("call_llm", route_after_llm, {
        "execute_tools":      "execute_tools",
        "request_validation": "request_validation",
        END:                  END,
    })

    g.add_edge("execute_tools", "call_llm")

    g.add_conditional_edges("request_validation", route_after_validation, {
        END:                      END,
        "apply_confirmed_action": "apply_confirmed_action",
    })

    g.add_edge("apply_confirmed_action", END)

    return g.compile()


graph = build_graph()
