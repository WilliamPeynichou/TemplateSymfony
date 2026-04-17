"""Tests unitaires pour les outils Fixture de l'agent.

Ces tests n'appellent pas l'API Symfony : on patche httpx pour valider
que les bonnes URLs, méthodes et payloads sont envoyés.
"""

import os
from unittest.mock import MagicMock, patch

os.environ.setdefault("AGENT_SECRET", "test-agent-secret")
os.environ.setdefault("AGENT_INTERNAL_SECRET", "test-internal-secret")

import httpx

from app.agent import tools


def _mock_response(payload: dict) -> MagicMock:
    resp = MagicMock()
    resp.raise_for_status = MagicMock()
    resp.json = MagicMock(return_value=payload)
    return resp


def test_list_fixtures_uses_correct_url_and_headers():
    with patch.object(httpx, "get", return_value=_mock_response({"success": True, "data": []})) as mget:
        result = tools.list_fixtures.invoke({"coach_id": 42, "team_id": 7})

    assert result == {"success": True, "data": []}
    args, kwargs = mget.call_args
    assert args[0].endswith("/teams/7/fixtures")
    headers = kwargs["headers"]
    assert headers["X-Coach-Id"] == "42"
    assert headers["Authorization"].startswith("Bearer agent:")


def test_create_fixture_sends_full_payload():
    captured: dict = {}

    def fake_post(url, json=None, headers=None, timeout=None):
        captured["url"] = url
        captured["json"] = json
        captured["headers"] = headers
        return _mock_response({"success": True, "data": {"id": 1}})

    with patch.object(httpx, "post", side_effect=fake_post):
        result = tools.create_fixture.invoke({
            "coach_id": 1,
            "team_id": 2,
            "opponent": "OM",
            "matchDate": "2026-05-01T15:00:00",
            "venue": "away",
            "competition": "Ligue 1",
            "status": "scheduled",
        })

    assert result["success"] is True
    assert captured["url"].endswith("/teams/2/fixtures")
    assert captured["json"]["opponent"] == "OM"
    assert captured["json"]["venue"] == "away"
    assert captured["json"]["status"] == "scheduled"
    assert "scoreFor" not in captured["json"]


def test_fixture_report_aggregates_played_matches():
    played = [
        {"status": "played", "result": "win", "scoreFor": 3, "scoreAgainst": 1},
        {"status": "played", "result": "draw", "scoreFor": 1, "scoreAgainst": 1},
        {"status": "played", "result": "loss", "scoreFor": 0, "scoreAgainst": 2},
        {"status": "scheduled", "result": None},
    ]
    with patch.object(tools, "_get", return_value={"success": True, "data": played}):
        report = tools.fixture_report.invoke({"coach_id": 1, "team_id": 1})

    data = report["data"]
    assert data["played_count"] == 3
    assert data["upcoming_count"] == 1
    assert data["wins"] == 1
    assert data["draws"] == 1
    assert data["losses"] == 1
    assert data["goals_for"] == 4
    assert data["goals_against"] == 4
    assert data["goal_diff"] == 0
