# ───────────────────────────────────────────────────────────────
# Andfield — Makefile DX
# ───────────────────────────────────────────────────────────────

SHELL := /bin/bash

DOCKER_COMPOSE ?= docker compose
DOCKER_EXEC    := $(DOCKER_COMPOSE) exec php
PHPUNIT        := $(DOCKER_EXEC) vendor/bin/phpunit
CONSOLE        := $(DOCKER_EXEC) bin/console

.DEFAULT_GOAL := help

.PHONY: help
help: ## Liste les commandes disponibles
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_.-]+:.*?## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## ── Docker ──────────────────────────────────────────────────────
.PHONY: up
up: ## Démarre la stack (build si besoin)
	$(DOCKER_COMPOSE) up -d --build

.PHONY: down
down: ## Arrête la stack
	$(DOCKER_COMPOSE) down

.PHONY: restart
restart: down up ## Redémarre la stack

.PHONY: logs
logs: ## Affiche les logs de tous les services
	$(DOCKER_COMPOSE) logs -f --tail=100

.PHONY: shell
shell: ## Ouvre un shell dans le container PHP
	$(DOCKER_EXEC) bash

## ── Dépendances ─────────────────────────────────────────────────
.PHONY: install
install: ## composer install + importmap:install
	$(DOCKER_EXEC) composer install
	$(CONSOLE) importmap:install

.PHONY: db
db: ## Recrée la base + applique les migrations
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: migrate
migrate: ## Applique les migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

## ── Tests ───────────────────────────────────────────────────────
.PHONY: test
test: test-php test-python test-js ## Lance toute la suite de tests

.PHONY: test-php
test-php: ## Tests PHPUnit
	$(PHPUNIT)

.PHONY: test-python
test-python: ## Tests Pytest (agent)
	$(DOCKER_COMPOSE) exec agent pytest -q

.PHONY: test-js
test-js: ## Tests front node:test
	cd assets && node --test tests

## ── Qualité ────────────────────────────────────────────────────
.PHONY: lint
lint: ## PHPStan + PHP-CS-Fixer dry-run
	$(DOCKER_EXEC) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(DOCKER_EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

.PHONY: fix
fix: ## Corrige automatiquement le style PHP
	$(DOCKER_EXEC) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

## ── Agent IA ────────────────────────────────────────────────────
.PHONY: ollama-pull
ollama-pull: ## Télécharge le modèle Qwen3
	$(DOCKER_COMPOSE) exec ollama ollama pull qwen3:1.7b

.PHONY: agent-logs
agent-logs: ## Logs du service agent
	$(DOCKER_COMPOSE) logs -f --tail=100 agent

## ── Secrets ────────────────────────────────────────────────────
.PHONY: secrets
secrets: ## Génère AGENT_SECRET et AGENT_INTERNAL_SECRET (copier dans .env.local)
	@echo "AGENT_SECRET=$$(openssl rand -hex 32)"
	@echo "AGENT_INTERNAL_SECRET=$$(openssl rand -hex 32)"
