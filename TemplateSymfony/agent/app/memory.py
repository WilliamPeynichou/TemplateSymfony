"""
Couche de persistance des conversations via l'API Symfony (MySQL).
"""
import httpx
from app.config import SYMFONY_API_URL, HISTORY_LIMIT, AGENT_SECRET


def _headers(coach_id: int) -> dict:
    return {
        "X-Coach-Id":    str(coach_id),
        "Authorization": f"Bearer agent:{AGENT_SECRET}",
    }


def get_or_create_conversation(coach_id: int, conversation_id: int | None, team_id: int | None) -> dict:
    """Récupère une conversation existante ou en crée une nouvelle."""
    if conversation_id:
        r = httpx.get(f"{SYMFONY_API_URL}/conversations/{conversation_id}", headers=_headers(coach_id), timeout=10)
        if r.status_code == 200:
            return r.json().get("data", {})

    # Créer une nouvelle conversation
    payload: dict = {}
    if team_id:
        payload["team_id"] = team_id
    r = httpx.post(f"{SYMFONY_API_URL}/conversations", json=payload, headers=_headers(coach_id), timeout=10)
    r.raise_for_status()
    return r.json().get("data", {})


def get_conversation(coach_id: int, conversation_id: int) -> dict | None:
    """Recupere une conversation si elle existe et est accessible au coach."""
    r = httpx.get(f"{SYMFONY_API_URL}/conversations/{conversation_id}", headers=_headers(coach_id), timeout=10)
    if r.status_code != 200:
        return None
    return r.json().get("data")


def load_history(coach_id: int, conversation_id: int) -> list[dict]:
    """Charge les N derniers messages d'une conversation."""
    r = httpx.get(
        f"{SYMFONY_API_URL}/conversations/{conversation_id}/messages",
        params={"limit": HISTORY_LIMIT},
        headers=_headers(coach_id),
        timeout=10,
    )
    if r.status_code != 200:
        return []
    return r.json().get("data", [])


def save_message(coach_id: int, conversation_id: int, role: str, content: str, tool_calls: list | None = None) -> None:
    """Persiste un message dans la conversation."""
    payload = {"role": role, "content": content}
    if tool_calls:
        payload["tool_calls"] = tool_calls
    httpx.post(
        f"{SYMFONY_API_URL}/conversations/{conversation_id}/messages",
        json=payload,
        headers=_headers(coach_id),
        timeout=10,
    )


def update_title(coach_id: int, conversation_id: int, title: str) -> None:
    """Met à jour le titre de la conversation (généré depuis le premier message)."""
    httpx.patch(
        f"{SYMFONY_API_URL}/conversations/{conversation_id}/title",
        json={"title": title},
        headers=_headers(coach_id),
        timeout=10,
    )


def update_pending_action(coach_id: int, conversation_id: int, pending_action: dict | None) -> None:
    """Met a jour l'action en attente de confirmation sur la conversation."""
    httpx.patch(
        f"{SYMFONY_API_URL}/conversations/{conversation_id}/pending-action",
        json={"pending_action": pending_action},
        headers=_headers(coach_id),
        timeout=10,
    )


def list_conversations(coach_id: int, team_id: int | None = None) -> list[dict]:
    """Liste les conversations du coach."""
    params = {}
    if team_id:
        params["team_id"] = team_id
    r = httpx.get(
        f"{SYMFONY_API_URL}/conversations",
        params=params,
        headers=_headers(coach_id),
        timeout=10,
    )
    if r.status_code != 200:
        return []
    return r.json().get("data", [])
