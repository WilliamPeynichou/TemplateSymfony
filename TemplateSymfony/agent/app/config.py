import os

LLM_BASE_URL: str    = os.getenv("LLM_BASE_URL", "http://ollama:11434/v1")
LLM_MODEL: str       = os.getenv("LLM_MODEL", "qwen3:1.7b")
SYMFONY_API_URL: str = os.getenv("SYMFONY_API_URL", "http://php/api/v1")
AGENT_SECRET: str | None = os.getenv("AGENT_SECRET")
AGENT_INTERNAL_SECRET: str | None = os.getenv("AGENT_INTERNAL_SECRET")

if not AGENT_SECRET:
    raise RuntimeError("AGENT_SECRET must be configured")

if not AGENT_INTERNAL_SECRET:
    raise RuntimeError("AGENT_INTERNAL_SECRET must be configured")

# Nombre max de messages chargés depuis l'historique
HISTORY_LIMIT: int = int(os.getenv("HISTORY_LIMIT", "50"))
