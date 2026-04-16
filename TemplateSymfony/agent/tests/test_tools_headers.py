import os

os.environ.setdefault("AGENT_SECRET", "test-secret")
os.environ.setdefault("AGENT_INTERNAL_SECRET", "test-internal")

from app.agent import tools  # noqa: E402


class _DummyResponse:
    def __init__(self, payload: dict):
        self._payload = payload

    def raise_for_status(self) -> None:
        return None

    def json(self) -> dict:
        return self._payload


def test_delete_uses_authorization_headers(monkeypatch):
    captured = {}

    def _fake_delete(url, headers, timeout):
        captured["url"] = url
        captured["headers"] = headers
        captured["timeout"] = timeout
        return _DummyResponse({"success": True})

    monkeypatch.setattr(tools.httpx, "delete", _fake_delete)

    result = tools._delete("/teams/42", coach_id=7)

    assert result["success"] is True
    assert captured["url"].endswith("/teams/42")
    assert captured["headers"]["X-Coach-Id"] == "7"
    assert captured["headers"]["Authorization"] == "Bearer agent:test-secret"
