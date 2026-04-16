from typing import Annotated, Literal
from langgraph.graph.message import add_messages
from pydantic import BaseModel, Field


class AgentState(BaseModel):
    # Historique des messages (user / assistant / tool)
    messages: Annotated[list, add_messages] = Field(default_factory=list)

    # Contexte session
    coach_id: int
    team_id: int | None = None
    conversation_id: str | None = None

    # Mode de validation : "singular" = demande confirmation | "auto" = exécute directement
    validation_mode: Literal["singular", "auto"] = "singular"

    # Si en mode singular, action en attente de confirmation
    pending_action: dict | None = None

    # Résultat de confirmation envoyé par le coach ("confirm" | "cancel")
    confirm_action: Literal["confirm", "cancel"] | None = None
