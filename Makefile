COMPOSE ?= docker compose
CONTAINER ?= $(COMPOSE) exec -T php php
PHP ?= php

.PHONY: help up down logs build seed reindex migrate search stats test analyse lint format bench

help: ## Show available targets
	@grep -E '^[a-z-]+:.*?##' $(MAKEFILE_LIST) | sed 's/:.*##/\t/' | expand -t22

up: ## Build the images and start MySQL, PHP-FPM and nginx
	$(COMPOSE) up --build -d

down: ## Stop the stack and drop its volumes
	$(COMPOSE) down -v

logs: ## Follow logs from every service
	$(COMPOSE) logs -f --tail=100

build: ## Rebuild the frontend assets into public/
	npm run build

migrate: ## Apply pending migrations
	$(CONTAINER) bin/console migrate

seed: ## Index the demo corpus
	$(CONTAINER) bin/console seed

reindex: ## Rebuild the inverted index from stored documents
	$(CONTAINER) bin/console reindex

search: ## Run a query from the CLI (make search Q='inverted index')
	$(CONTAINER) bin/console search $(Q)

stats: ## Print index statistics
	$(CONTAINER) bin/console statistics

test: ## Run the PHPUnit suites (needs PHP 8.4 and vendor/ locally)
	$(PHP) vendor/bin/phpunit

analyse: ## Run static analysis
	$(PHP) vendor/bin/phpstan analyse

lint: ## Lint the frontend
	npm run lint && npm run format:check

format: ## Format everything Prettier owns
	npm run format

bench: ## Run the benchmark suite (BENCH_DOCS controls corpus size)
	$(PHP) bench/run.php
