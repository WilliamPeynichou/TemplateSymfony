import os

os.environ.setdefault("AGENT_SECRET", "test-secret")
os.environ.setdefault("AGENT_INTERNAL_SECRET", "test-internal")

from app import memory  # noqa: E402


class _DummyResponse:
    def __init__(self, payload: dict, status_code: int = 200):
        self._payload = payload
        self.status_code = status_code

    def raise_for_status(self) -> None:
        if self.status_code >= 400:
            raise RuntimeError("http error")

    def json(self) -> dict:
        return self._payload


def test_get_or_create_fetches_conversation_by_id(monkeypatch):
    calls = []

    def _fake_get(url, headers, timeout):
        calls.append(("get", url, headers))
        return _DummyResponse({"data": {"id": 5}})

    monkeypatch.setattr(memory.httpx, "get", _fake_get)

    conv = memory.get_or_create_conversation(coach_id=3, conversation_id=5, team_id=None)

    assert conv["id"] == 5
    assert calls[0][1].endswith("/conversations/5")
    assert calls[0][2]["Authorization"] == "Bearer agent:test-secret"


def test_update_pending_action_sends_patch_payload(monkeypatch):
    calls = []

    def _fake_patch(url, json, headers, timeout):
        calls.append((url, json, headers))
        return _DummyResponse({})

    monkeypatch.setattr(memory.httpx, "patch", _fake_patch)

    pending = {"name": "create_player", "args": {"team_id": 1}}
    memory.update_pending_action(coach_id=2, conversation_id=9, pending_action=pending)

    assert calls[0][0].endswith("/conversations/9/pending-action")
    assert calls[0][1] == {"pending_action": pending}
    assert calls[0][2]["X-Coach-Id"] == "2"
