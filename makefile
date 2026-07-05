# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test lint lint-fix reset-database reset-database-hard

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with phpunit, pass the parameter "c=" to add options to phpunit, example: make test c="--group e2e --stop-on-failure"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)


## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Lint 🧹 ——————————————————————————————————————————————————————————————————
lint: ## Run super-linter locally (same checks as CI) via Docker
	@docker run --rm \
		-e RUN_LOCAL=true \
		-e DEFAULT_BRANCH=main \
		-e GITHUB_ACTIONS_CONFIG_FILE=actionlint.yaml \
		-e VALIDATE_CHECKOV=false \
		-e VALIDATE_TRIVY=false \
		-e VALIDATE_BIOME_FORMAT=false \
		-e VALIDATE_BIOME_LINT=false \
		-e VALIDATE_PHP_BUILTIN=false \
		-e VALIDATE_PHP_PHPCS=false \
		-e VALIDATE_PHP_PHPSTAN=false \
		-e VALIDATE_PHP_PSALM=false \
		-v $(PWD):/tmp/lint \
		ghcr.io/super-linter/super-linter:slim-v8

lint-fix: ## Auto-fix lint issues where possible (YAML, Markdown, JSON, shell, .env)
	@docker run --rm \
		-e RUN_LOCAL=true \
		-e DEFAULT_BRANCH=main \
		-e GITHUB_ACTIONS_CONFIG_FILE=actionlint.yaml \
		-e VALIDATE_CHECKOV=false \
		-e VALIDATE_TRIVY=false \
		-e VALIDATE_BIOME_FORMAT=false \
		-e VALIDATE_BIOME_LINT=false \
		-e VALIDATE_PHP_BUILTIN=false \
		-e VALIDATE_PHP_PHPCS=false \
		-e VALIDATE_PHP_PHPSTAN=false \
		-e VALIDATE_PHP_PSALM=false \
		-e FIX_YAML_PRETTIER=true \
		-e FIX_MARKDOWN=true \
		-e FIX_MARKDOWN_PRETTIER=true \
		-e FIX_JSON_PRETTIER=true \
		-e FIX_SHELL_SHFMT=true \
		-e FIX_ENV=true \
		-v $(PWD):/tmp/lint \
		ghcr.io/super-linter/super-linter:slim-v8

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

migration: ## Generate a new migration
	@$(SYMFONY) make:migration

migrate: ## Run database migrations
	@$(SYMFONY) doctrine:migrations:migrate

reset-database: ## Last resort: wipe the Docker volumes, restart the stack and replay migrations
	@$(DOCKER_COMP) down --volumes
	@$(DOCKER_COMP) up --detach --wait
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction

entity: ## Create a new entity in the chosen domain
	@$(SYMFONY) do:entity

battery-reminders: ## Send battery recharge reminders that are due
	@$(SYMFONY) app:battery:send-reminders

generate-keypair: ## Generate a new encryption key
	@$(SYMFONY) lexik:jwt:generate-keypair
